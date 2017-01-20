<?php

namespace eLife\App;

use Aws\Sqs\SqsClient;
use Closure;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PuliSchemaFinder;
use eLife\Bus\Limit\CompositeLimit;
use eLife\Bus\Limit\LoggingMiddleware;
use eLife\Bus\Limit\MemoryLimit;
use eLife\Bus\Limit\SignalsLimit;
use eLife\Bus\Queue\SqsMessageTransformer;
use eLife\Bus\Queue\SqsWatchableQueue;
use eLife\Logging\LoggingFactory;
use eLife\Logging\Monitoring;
use eLife\Recommendations\Command\GenerateDatabaseCommand;
use eLife\Recommendations\Command\MysqlRepoQueueCommand;
use eLife\Recommendations\Command\PopulateRulesCommand;
use eLife\Recommendations\Process\Hydration;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RecommendationResultDiscriminator;
use eLife\Recommendations\Rule\BidirectionalRelationship;
use eLife\Recommendations\Rule\CollectionContents;
use eLife\Recommendations\Rule\MostRecent;
use eLife\Recommendations\Rule\MostRecentWithSubject;
use eLife\Recommendations\Rule\NormalizedPersistence;
use eLife\Recommendations\Rule\PodcastEpisodeContents;
use eLife\Recommendations\RuleModelRepository;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Silex\Application;
use Silex\Provider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\VarDumperServiceProvider;
use Sorien\Provider\DoctrineProfilerServiceProvider;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Webmozart\Json\JsonDecoder;

final class Kernel implements MinimalKernel
{
    const ROOT = __DIR__.'/../..';

    public static $routes = [
        '/recommendations/{type}/{id}' => 'indexAction',
        '/recommendations' => 'allAction',
        '/ping' => 'pingAction',
    ];

    private $app;

    public function __construct($config = [])
    {
        $app = new Application();
        if (file_exists(self::ROOT.'/config/db.ini')) {
            $ini = parse_ini_string(file_get_contents(self::ROOT.'/config/db.ini'), true);
            $config['db'] = array_merge($config['db'] ?? [], $ini['db'] ?? []);
        }
        // Load config
        $app['config'] = array_merge([
            'cli' => false,
            'api_url' => 'http://0.0.0.0:1234',
            'debug' => false,
            'validate' => false,
            'annotation_cache' => true,
            'ttl' => 3600,
            'process_memory_limit' => 200,
            'file_logs_path' => self::ROOT.'/var/logs',
            'db' => array_merge([
                'driver' => 'pdo_mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'dbname' => 'recommendations',
                'user' => 'eLife',
                'password' => '',
                'charset' => 'utf8mb4',
            ], $config['db'] ?? []),
            'aws' => array_merge([
                'credential_file' => false,
                'mock_queue' => true,
                'queue_name' => 'eLife-recommendations',
                'key' => '-----------------------',
                'secret' => '-------------------------------',
                'region' => '---------',
            ], $config['aws'] ?? []),
        ], $config);
        // Annotations.
        AnnotationRegistry::registerAutoloadNamespace(
            'JMS\Serializer\Annotation', self::ROOT.'/vendor/jms/serializer/src'
        );
        if ($app['config']['debug']) {
            $app->register(new VarDumperServiceProvider());
            $app->register(new Provider\HttpFragmentServiceProvider());
            $app->register(new Provider\ServiceControllerServiceProvider());
            $app->register(new Provider\TwigServiceProvider());
            $app->register(new Provider\WebProfilerServiceProvider(), [
                'profiler.cache_dir' => self::ROOT.'/var/cache/profiler',
                'profiler.mount_prefix' => '/_profiler', // this is the default
            ]);
            $app->register(new DoctrineProfilerServiceProvider());
        }

        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => $app['config']['db'],
        ));
        // DI.
        $this->dependencies($app);
        // Add to class once set up.
        $this->app = $this->applicationFlow($app);
    }

    public function dependencies(Application $app)
    {

        //#####################################################
        // -------------------- Basics -----------------------
        //#####################################################

        // Serializer.
        $app['serializer'] = function () {
            return SerializerBuilder::create()
                ->configureListeners(function (EventDispatcher $dispatcher) {
                    // Configure discriminators and subscribers here.
                    $dispatcher->addSubscriber(new RecommendationResultDiscriminator());
                })
                ->setCacheDir(self::ROOT.'/var/cache')
                ->build();
        };
        $app['serializer.context'] = function () {
            return SerializationContext::create();
        };
        // Puli.
        $app['puli.factory'] = function () {
            $factoryClass = PULI_FACTORY_CLASS;

            return new $factoryClass();
        };
        // Puli repo.
        $app['puli.repository'] = function (Application $app) {
            return $app['puli.factory']->createRepository();
        };
        // General cache.
        $app['cache'] = function () {
            return new FilesystemCache(self::ROOT.'/var/cache');
        };
        // Annotation reader.
        $app['annotations.reader'] = function (Application $app) {
            if ($app['config']['annotation_cache'] === false) {
                return new AnnotationReader();
            }

            return new CachedReader(
                new AnnotationReader(),
                $app['cache'],
                $app['config']['debug']
            );
        };
        // PSR-7 Bridge
        $app['psr7.bridge'] = function () {
            return new DiactorosFactory();
        };
        // Validator.
        $app['puli.validator'] = function (Application $app) {
            return new JsonMessageValidator(
                new PuliSchemaFinder($app['puli.repository']),
                new JsonDecoder()
            );
        };

        $app['monitoring'] = function () {
            return new Monitoring();
        };

        /* @internal */
        $app['limit._memory'] = function (Application $app) {
            return MemoryLimit::mb($app['config']['process_memory_limit']);
        };

        /* @internal */
        $app['limit._signals'] = function () {
            return SignalsLimit::stopOn(['SIGINT', 'SIGTERM', 'SIGHUP']);
        };

        $app['limit.long_running'] = function (Application $app) {
            return new LoggingMiddleware(
                new CompositeLimit(
                    $app['limit._memory'],
                    $app['limit._signals']
                ),
                $app['logger']
            );
        };

        $app['limit.interactive'] = function (Application $app) {
            return new LoggingMiddleware(
                $app['limit._signals'],
                $app['logger']
            );
        };

        $app['logger'] = function (Application $app) {
            $logger = new LoggingFactory($app['config']['file_logs_path'], 'recommendations-api');

            return $logger->logger();
        };

        //######################################################
        // ------------------ Rule Specific --------------------
        //######################################################
        $app['rules.repository'] = function (Application $app) {
            return new RuleModelRepository($app['db']);
        };

        $app['rules.process'] = function (Application $app) {
            return new Rules(
                $app['logger'],
                new NormalizedPersistence(
                    $app['rules.repository'],
                    /* 1 */ new BidirectionalRelationship($app['api.sdk'], 'retraction', $app['rules.repository'], $app['logger']),
                    /* 2 */ new BidirectionalRelationship($app['api.sdk'], 'correction', $app['rules.repository'], $app['logger']),
                    /* 3 is part of BidirectionalRelationship. */
                    /* 4 */ new BidirectionalRelationship($app['api.sdk'], 'research-article', $app['rules.repository'], $app['logger']),
                    /* 5 */ new BidirectionalRelationship($app['api.sdk'], 'research-exchange', $app['rules.repository'], $app['logger']),
                    /* 6 */ new BidirectionalRelationship($app['api.sdk'], 'research-advance', $app['rules.repository'], $app['logger']),
                    /* 7 */ new BidirectionalRelationship($app['api.sdk'], 'tools-resources', $app['rules.repository'], $app['logger']),
                    /* 8 */ new BidirectionalRelationship($app['api.sdk'], 'feature', $app['rules.repository'], $app['logger']),
                    /* 9 */ new BidirectionalRelationship($app['api.sdk'], 'insight', $app['rules.repository'], $app['logger']),
                    /* 10 */ new BidirectionalRelationship($app['api.sdk'], 'editorial', $app['rules.repository'], $app['logger']),
                    /* 11 */ new CollectionContents($app['api.sdk'], $app['rules.repository']),
                    /* 12 */ new PodcastEpisodeContents($app['api.sdk'], $app['rules.repository'])
                ),
                /* 13 */ new MostRecent(),
                /* 14 */ new MostRecentWithSubject($app['api.sdk'], $app['rules.repository'])
            );
        };

        //#####################################################
        // --------------------- Queue -----------------------
        //#####################################################

        $app['aws.sqs'] = function (Application $app) {
            $config = [
                'version' => '2012-11-05',
                'region' => $app['config']['aws']['region'],
            ];
            if (isset($app['config']['aws']['endpoint'])) {
                $config['endpoint'] = $app['config']['aws']['endpoint'];
            }
            if (!isset($app['config']['aws']['credential_file']) || $app['config']['aws']['credential_file'] === false) {
                $config['credentials'] = [
                    'key' => $app['config']['aws']['key'],
                    'secret' => $app['config']['aws']['secret'],
                ];
            }

            return new SqsClient($config);
        };

        $app['aws.queue'] = function (Application $app) {
            return new SqsWatchableQueue($app['aws.sqs'], $app['config']['aws']['queue_name']);
        };

        $app['aws.queue_transformer'] = function (Application $app) {
            return new SqsMessageTransformer($app['api.sdk']);
        };

        //#####################################################
        // ------------------- Commands ----------------------
        //#####################################################

        $app['console.populate_rules'] = function (Application $app) {
            return new PopulateRulesCommand(
                $app['api.sdk'],
                $app['rules.repository'],
                $app['rules.process'],
                $app['logger'],
                $app['monitoring'],
                $app['limit.interactive']
            );
        };

        $app['console.generate_database'] = function (Application $app) {
            return new GenerateDatabaseCommand($app['db'], $app['logger']);
        };

        $app['console.queue'] = function (Application $app) {
            return new MysqlRepoQueueCommand($app['rules.process'], $app['logger'], $app['aws.queue'], $app['aws.queue_transformer'], $app['monitoring'], $app['limit.long_running']);
        };

        //#####################################################
        // ------------------ Networking ---------------------
        //#####################################################

        $app['guzzle'] = function (Application $app) {
            // Create default HandlerStack
            $stack = HandlerStack::create();
            $stack->push(
                new CacheMiddleware(
                    new PublicCacheStrategy(
                        new DoctrineCacheStorage(
                            $app['cache']
                        )
                    )
                ),
                'cache'
            );

            return new Client([
                'base_uri' => $app['config']['api_url'],
                'handler' => $stack,
            ]);
        };

        $app['api.sdk'] = function (Application $app) {
            return new ApiSdk(
                new Guzzle6HttpClient(
                    $app['guzzle']
                )
            );
        };

        $app['hydration'] = function (Application $app) {
            return new Hydration($app['api.sdk']);
        };

        $app['default_controller'] = function (Application $app) {
            return new DefaultController($app['rules.process'], $app['hydration'], $app['serializer'], $app['rules.repository']);
        };
    }

    public function applicationFlow(Application $app): Application
    {
        // Routes
        $this->routes($app);
        // Validate.
        if ($app['config']['validate']) {
            $app->after([$this, 'validate'], 2);
        }
        // Cache.
        if ($app['config']['ttl'] > 0) {
            $app->after([$this, 'cache'], 3);
        }
        // Error handling.
        if (!$app['config']['debug']) {
            $app->error([$this, 'handleException']);
        }
        // Return
        return $app;
    }

    public function routes(Application $app)
    {
        foreach (self::$routes as $route => $action) {
            $app->get($route, [$app['default_controller'], $action]);
        }
    }

    public function handleException($e): Response
    {
        return $response;
    }

    public function withApp(callable $fn)
    {
        $boundFn = Closure::bind($fn, $this);
        $boundFn($this->app);

        return $this;
    }

    public function run()
    {
        return $this->app->run();
    }

    public function get($d)
    {
        return $this->app[$d];
    }

    public function validate(Request $request, Response $response)
    {
        try {
            if (strpos($response->headers->get('Content-Type'), 'json')) {
                $this->app['puli.validator']->validate(
                    $this->app['psr7.bridge']->createResponse($response)
                );
            }
        } catch (Throwable $e) {
            if ($this->app['config']['debug']) {
                throw $e;
            }
        }
    }

    public function cache(Request $request, Response $response)
    {
    }
}
