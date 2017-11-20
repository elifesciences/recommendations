<?php

namespace eLife\Recommendations;

use ComposerLocator;
use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\MockMiddleware;
use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\StopwatchMiddleware;
use eLife\ApiClient\HttpClient;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiClient\HttpClient\WarningCheckingHttpClient;
use eLife\ApiProblem\Silex\ApiProblemProvider;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PathBasedSchemaFinder;
use eLife\ContentNegotiator\Silex\ContentNegotiationProvider;
use eLife\Logging\LoggingFactory;
use eLife\Ping\Silex\PingControllerProvider;
use eLife\Recommendations\Controller\RecommendationsController;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use JsonSchema\Validator;
use Negotiation\Accept;
use Pimple\Exception\UnknownIdentifierException;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Silex\Application;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use test\eLife\Recommendations\InMemoryStorageAdapter;
use test\eLife\Recommendations\ValidatingStorageAdapter;

final class AppKernel implements ContainerInterface, HttpKernelInterface, TerminableInterface
{
    private $app;

    public function __construct(string $environment = 'dev')
    {
        $configFile = __DIR__.'/../config.php';

        $config = array_merge(file_exists($configFile) ? require $configFile : [], require __DIR__."/../config/{$environment}.php");

        $this->app = new Application([
            'api.uri' => $config['api.uri'] ?? 'https://api.elifesciences.org/',
            'api.timeout' => $config['api.timeout'] ?? 1,
            'debug' => $config['debug'] ?? false,
            'logger.path' => $config['logger.path'] ?? __DIR__.'/../var/logs',
            'logger.level' => $config['logger.level'] ?? LogLevel::INFO,
            'mock' => $config['mock'] ?? false,
        ]);

        $this->app->register(new ApiProblemProvider());
        $this->app->register(new ContentNegotiationProvider());
        $this->app->register(new PingControllerProvider());

        if ($this->app['debug']) {
            $this->app->register(new HttpFragmentServiceProvider());
            $this->app->register(new ServiceControllerServiceProvider());
            $this->app->register(new TwigServiceProvider());
            $this->app->register(new WebProfilerServiceProvider(), [
                'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
                'profiler.mount_prefix' => '/_profiler',
            ]);
        }

        $this->app['elife.guzzle_client.handler'] = function () {
            return HandlerStack::create();
        };

        if ($this->app['mock']) {
            $this->app['elife.json_message_validator'] = function () {
                return new JsonMessageValidator(
                    new PathBasedSchemaFinder(ComposerLocator::getPath('elife/api').'/dist/model'),
                    new Validator()
                );
            };

            $this->app['elife.guzzle_client.mock.storage'] = function () {
                return new ValidatingStorageAdapter(new InMemoryStorageAdapter(), $this->app['elife.json_message_validator']);
            };

            $this->app['elife.guzzle_client.mock'] = function () {
                return new MockMiddleware($this->app['elife.guzzle_client.mock.storage'], 'replay');
            };

            $this->app->extend('elife.guzzle_client.handler', function (HandlerStack $stack) {
                $stack->push($this->app['elife.guzzle_client.mock']);

                return $stack;
            });
        }

        if ($this->app['debug']) {
            $this->app->extend('elife.guzzle_client.handler', function (HandlerStack $stack) {
                $stack->unshift(new StopwatchMiddleware($this->app['stopwatch']));

                return $stack;
            });
        }

        $this->app['elife.guzzle_client'] = function () {
            return new Client([
                'base_uri' => $this->app['api.uri'],
                'connect_timeout' => 0.5,
                'handler' => $this->app['elife.guzzle_client.handler'],
                'timeout' => $this->app['api.timeout'],
            ]);
        };

        $this->app['elife.api_client'] = function () {
            return new Guzzle6HttpClient($this->app['elife.guzzle_client']);
        };

        if ($this->app['debug']) {
            $this->app->extend('elife.api_client', function (HttpClient $httpClient) {
                return new WarningCheckingHttpClient($httpClient, $this->app['logger']);
            });
        }

        $this->app['elife.api_sdk'] = function () {
            return new ApiSdk($this->app['elife.api_client']);
        };

        $this->app['elife.api_sdk.serializer'] = function () {
            return $this->app['elife.api_sdk']->getSerializer();
        };

        $this->app['elife.logger.factory'] = function () {
            return new LoggingFactory($this->app['logger.path'], 'recommendations-api', $this->app['logger.level']);
        };

        $this->app['logger'] = function (Application $app) {
            return $this->app['elife.logger.factory']->logger();
        };

        $this->app['controllers.recommendations'] = function () {
            return new RecommendationsController($this->app['elife.api_sdk']);
        };

        $this->app->get('/recommendations/{contentType}/{id}', 'controllers.recommendations:recommendationsAction')
            ->before($this->app['negotiate.accept'](
                'application/vnd.elife.recommendations+json; version=1'
            ));

        $this->app->after(function (Request $request, Response $response, Application $app) {
            if ($response->isCacheable()) {
                $response->headers->set('ETag', md5($response->getContent()));
                $response->isNotModified($request);
            }
        });
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true) : Response
    {
        return $this->app->handle($request, $type, $catch);
    }

    public function terminate(Request $request, Response $response)
    {
        $this->app->terminate($request, $response);
    }

    public function get($id)
    {
        if (!isset($this->app[$id])) {
            throw new UnknownIdentifierException($id);
        }

        return $this->app[$id];
    }

    public function has($id) : bool
    {
        return isset($this->app[$id]);
    }
}
