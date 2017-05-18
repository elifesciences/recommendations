<?php

namespace eLife\App;

use Aws\Sqs\SqsClient;
use Closure;
use ComposerLocator;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiClient\HttpClient\UserAgentPrependingHttpClient;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PathBasedSchemaFinder;
use eLife\Bus\Command\QueueCleanCommand;
use eLife\Bus\Command\QueueCountCommand;
use eLife\Bus\Limit\CompositeLimit;
use eLife\Bus\Limit\LoggingMiddleware;
use eLife\Bus\Limit\MemoryLimit;
use eLife\Bus\Limit\SignalsLimit;
use eLife\Bus\Queue\CachedTransformer;
use eLife\Bus\Queue\SingleItemRepository;
use eLife\Bus\Queue\SqsWatchableQueue;
use eLife\Logging\LoggingFactory;
use eLife\Logging\Monitoring;
use eLife\Recommendations\Command\GenerateDatabaseCommand;
use eLife\Recommendations\Command\MysqlRepoQueueCommand;
use eLife\Recommendations\Command\PopulateRulesCommand;
use eLife\Recommendations\Process\Hydration;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RecommendationResultDiscriminator;
use eLife\Recommendations\Response\PrivateResponse;
use eLife\Recommendations\Rule\BidirectionalRelationship;
use eLife\Recommendations\Rule\CollectionContents;
use eLife\Recommendations\Rule\Common\MicroSdk;
use eLife\Recommendations\Rule\MostRecent;
use eLife\Recommendations\Rule\MostRecentWithSubject;
use eLife\Recommendations\Rule\NormalizedPersistence;
use eLife\Recommendations\Rule\PodcastEpisodeContents;
use eLife\Recommendations\RuleModelRepository;
use GuzzleHttp\Client;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use JsonSchema\Validator;
use PackageVersions\Versions;
use Psr\Log\LoggerInterface;
use Silex\Application;
use Silex\Provider;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\VarDumperServiceProvider;
use Sorien\Provider\DoctrineProfilerServiceProvider;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use function GuzzleHttp\json_encode;

final class Kernel implements MinimalKernel
{
    const ROOT = __DIR__.'/../..';
    const CACHE_DIR = self::ROOT.'/var/cache';

    public static $routes = [
        '/recommendations/{type}/{id}' => 'indexAction',
        '/recommendations' => 'allAction',
        '/ping' => 'pingAction',
    ];

    private $app;
    private $startTime;
    private $version;

    public function __construct($config = [], $autoRun = true)
    {
        $this->startTime = microtime(true);

        $originalVersion = Versions::getVersion('elife/recommendations');
        list($version, $reference) = explode('@', $originalVersion);
        if (false !== strpos($version, 'dev')) {
            if (40 === strlen($reference)) {
                $version = implode('@', [$version, substr($reference, 0, 7)]);
            } else {
                $version = $originalVersion;
            }
        }

        $this->version = $version;
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
            'ttl' => 300,
            'process_memory_limit' => 200,
            'file_logs_path' => self::ROOT.'/var/logs',
            'tables' => [
                'rules' => 'Rules',
                'references' => 'Relations',
            ],
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
                'profiler.cache_dir' => self::CACHE_DIR.'/profiler',
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
        $this->app = $autoRun ? $this->applicationFlow($app) : $app;
    }

    public function setupFlow()
    {
        // Add to class once set up.
        $this->app = $this->applicationFlow($this->app);
    }

    public function getApp()
    {
        return $this->app;
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
                ->setCacheDir(self::CACHE_DIR)
                ->build();
        };
        $app['serializer.context'] = function () {
            return SerializationContext::create();
        };
        // General cache.
        $app['cache'] = function () {
            return new FilesystemCache(self::CACHE_DIR);
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
        $app['message-validator'] = function (Application $app) {
            return new JsonMessageValidator(
                new PathBasedSchemaFinder(ComposerLocator::getPath('elife/api').'/dist/model'),
                new Validator()
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
            return new RuleModelRepository(
                $app['db'],
                $app['config']['tables']['rules'],
                $app['config']['tables']['references']
            );
        };

        $app['rules.micro_sdk'] = function (Application $app) {
            return new MicroSdk($app['api.sdk']);
        };

        $app['rules.process'] = function (Application $app) {
            return new Rules(
                $app['monitoring'],
                $app['logger'],
                new NormalizedPersistence(
                    $app['rules.repository'],
                    /* 1 - 10 */
                    new BidirectionalRelationship($app['rules.micro_sdk'], $app['rules.repository'], $app['logger']),
                    /* 11 */
                    new CollectionContents($app['rules.micro_sdk'], $app['rules.repository']),
                    /* 12 */
                    new PodcastEpisodeContents($app['rules.micro_sdk'], $app['rules.repository'])
                ),
                /* 13 */
                new MostRecent($app['rules.repository'], $app['logger']),
                /* 14 */
                new MostRecentWithSubject($app['hydration.single_item_repository'], $app['rules.micro_sdk'], $app['rules.repository'], $app['logger'])
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
            return new CachedTransformer($app['api.sdk'], $app['cache'], $app['logger'], 3600);
        };

        //#####################################################
        // ------------------- Commands ----------------------
        //#####################################################

        $app['console.populate_rules'] = function (Application $app) {
            return new PopulateRulesCommand(
                $app['api.sdk'],
                $app['rules.repository'],
                $app['aws.queue'],
                $app['rules.process'],
                $app['logger'],
                $app['monitoring'],
                $app['limit.interactive']
            );
        };

        $app['console.generate_database'] = function (Application $app) {
            return new GenerateDatabaseCommand(
                $app['db'],
                $app['logger'],
                $app['monitoring'],
                $app['config']['tables']['rules'],
                $app['config']['tables']['references']
            );
        };

        $app['console.queue_count'] = function (Application $app) {
            return new QueueCountCommand($app['aws.queue']);
        };

        $app['console.queue_clean'] = function (Application $app) {
            return new QueueCleanCommand($app['aws.queue'], $app['logger']);
        };

        $app['console.queue'] = function (Application $app) {
            return new MysqlRepoQueueCommand(
                $app['rules.process'],
                $app['logger'],
                $app['aws.queue'],
                $app['aws.queue_transformer'],
                $app['monitoring'],
                $app['limit.long_running']
            );
        };

        //#####################################################
        // ------------------ Networking ---------------------
        //#####################################################

        $app['guzzle'] = function (Application $app) {
            return new Client([
                'base_uri' => $app['config']['api_url'],
            ]);
        };

        $app['api.sdk'] = function (Application $app) {
            return new ApiSdk(
                new UserAgentPrependingHttpClient(
                    new Guzzle6HttpClient(
                        $app['guzzle']
                    ),
                    'elifeRecommendations/'.$this->version
                )
            );
        };

        $app['hydration.single_item_repository'] = function (Application $app) : SingleItemRepository {
            return $app['aws.queue_transformer'];
        };

        $app['hydration'] = function (Application $app) {
            return new Hydration($app['rules.micro_sdk'], $app['hydration.single_item_repository']);
        };

        $app['default_controller'] = function (Application $app) {
            return new DefaultController($app['rules.process'], $app['hydration'], $app['serializer'], $app['rules.repository'], $app['logger']);
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
            $app->after([$this, 'cacheDirectives'], 3);
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

    public function handleException(Throwable $e): Response
    {
        /** @var LoggerInterface $logger */
        $logger = $this->get('logger');
        if ($e instanceof HttpException) {
            $logger->error('An http exception was thrown', [
                'exception' => $e,
            ]);

            return new JsonResponse(array_filter([
                'error' => $e->getMessage(),
                'trace' => $this->app['config']['debug'] ? $e->getTraceAsString() : null,
            ]), $e->getCode() ? $e->getCode() : Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $logger->error('An unknown exception was thrown', [
            'exception' => $e,
        ]);
        $errorMessage = '
            Internal server error – We are unable to server your request, 
            but it has been logged and we will look into the issue.
        ';
        // This should never be hit, it is a last resort.
        return new JsonResponse(
            $this->app['config']['debug'] ? [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'time' => microtime(true) - $this->startTime,
            ] : [
                'error' => trim($errorMessage),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                $this->get('message-validator')->validate(
                    $this->get('psr7.bridge')->createResponse($response)
                );
            }
        } catch (Throwable $e) {
            if ($this->app['config']['debug']) {
                $json = json_decode($response->getContent());
                $json->_validationInfo = $e->getMessage();
                $json->_time = microtime(true) - $this->startTime;
                $response->setContent(json_encode($json));
            }
            $this->get('logger')->warning('Invalid JSON provided to user', [
                'exception' => $e,
                'request' => $request,
                'response' => $response,
            ]);
        }

        return $response;
    }

    public function cacheDirectives(Request $request, Response $response)
    {
        if ($response instanceof PrivateResponse) {
            return $response;
        }
        $response->setMaxAge($this->app['config']['ttl']);
        $response->headers->addCacheControlDirective('stale-while-revalidate', $this->app['config']['ttl']);
        $response->headers->addCacheControlDirective('stale-if-error', 86400);
        $response->setVary('Accept');
        $response->setEtag(md5($response->getContent()));
        $response->setPublic();
        $response->isNotModified($request);

        return $response;
    }
}
