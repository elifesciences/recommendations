<?php

namespace eLife\App;

use Closure;
use Dflydev\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PuliSchemaFinder;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RecommendationResultDiscriminator;
use eLife\Recommendations\Rule\BidirectionalRelationship;
use eLife\Recommendations\Rule\CollectionContents;
use eLife\Recommendations\Rule\MostRecent;
use eLife\Recommendations\Rule\MostRecentWithSubject;
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
    ];

    private $app;

    public function __construct($config = [])
    {
        $app = new Application();
        // Load config
        $app['config'] = array_merge([
            'cli' => false,
            'api_url' => 'http://0.0.0.0:1234',
            'debug' => false,
            'validate' => false,
            'annotation_cache' => true,
            'ttl' => 3600,
            'db' => array_merge([
                'driver' => 'pdo_mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'dbname' => 'recommendations',
                'user' => 'eLife',
                'password' => '',
                'charset' => 'utf8mb4',
            ], $config['db'] ?? []),
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
                'profiler.cache_dir' => self::ROOT.'/cache/profiler',
                'profiler.mount_prefix' => '/_profiler', // this is the default
            ]);
            $app->register(new DoctrineProfilerServiceProvider());
        }

        $app->register(new DoctrineServiceProvider(), array(
            'db.options' => $app['config']['db'],
        ));

//        $app->register(new DoctrineOrmServiceProvider, array(
//            'orm.proxies_dir' => '/path/to/proxies',
//            'orm.em.options' => array(
//                'mappings' => array(
//                    // Using actual filesystem paths
//                    array(
//                        'type' => 'annotation',
//                        'namespace' => 'Foo\Entities',
//                        'path' => __DIR__.'/src/Foo/Entities',
//                    ),
//                    array(
//                        'type' => 'xml',
//                        'namespace' => 'Bat\Entities',
//                        'path' => __DIR__.'/src/Bat/Resources/mappings',
//                    ),
//                ),
//            ),
//        ));

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
                ->setCacheDir(self::ROOT.'/cache')
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
            return new FilesystemCache(self::ROOT.'/cache');
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
        $app['rules.repository'] = function (Application $app) {
            return new RuleModelRepository($app['db']);
        };

        //#####################################################
        // ------------------ Rule Process --------------------
        //#####################################################
        $app['rules.process'] = function (Application $app) {
            return new Rules(
                /* 1 */ new BidirectionalRelationship($app['api.sdk'], 'retraction', $app['rules.repository']),
                /* 2 */ new BidirectionalRelationship($app['api.sdk'], 'correction', $app['rules.repository']),
                /* 3 is part of BidirectionalRelationship. */
                /* 4 */ new BidirectionalRelationship($app['api.sdk'], 'research-article', $app['rules.repository']),
                /* 5 */ new BidirectionalRelationship($app['api.sdk'], 'research-exchange', $app['rules.repository']),
                /* 6 */ new BidirectionalRelationship($app['api.sdk'], 'research-advance', $app['rules.repository']),
                /* 7 */ new BidirectionalRelationship($app['api.sdk'], 'tools-resources', $app['rules.repository']),
                /* 8 */ new BidirectionalRelationship($app['api.sdk'], 'feature', $app['rules.repository']),
                /* 9 */ new BidirectionalRelationship($app['api.sdk'], 'insight', $app['rules.repository']),
                /* 10 */ new BidirectionalRelationship($app['api.sdk'], 'editorial', $app['rules.repository']),
                /* 11 */ new CollectionContents($app['api.sdk']),
                /* 12 */ new PodcastEpisodeContents($app['api.sdk']),
                /* 13 */ new MostRecent(),
                /* 14 */ new MostRecentWithSubject($app['api.sdk'])
            );
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

        $app['default_controller'] = function (Application $app) {
            return new DefaultController($app['rules.process'], null, $app['serializer']);
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
