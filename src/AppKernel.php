<?php

namespace eLife\Recommendations;

use ComposerLocator;
use Crell\ApiProblem\ApiProblem;
use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\MockMiddleware;
use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\StopwatchMiddleware;
use DateTimeImmutable;
use eLife\ApiClient\Exception\BadResponse;
use eLife\ApiClient\HttpClient;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiClient\HttpClient\WarningCheckingHttpClient;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Collection\EmptySequence;
use eLife\ApiSdk\Collection\PromiseSequence;
use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ArticleHistory;
use eLife\ApiSdk\Model\ExternalArticle;
use eLife\ApiSdk\Model\HasPublishedDate;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiSdk\Model\PodcastEpisodeChapterModel;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PathBasedSchemaFinder;
use eLife\Logging\LoggingFactory;
use eLife\Ping\Silex\PingControllerProvider;
use eLife\Recommendations\Controller\RecommendationsController;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use JsonSchema\Validator;
use Negotiation\Accept;
use Pimple\Psr11\Container as Psr11Container;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Silex\Application;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use test\eLife\Recommendations\InMemoryStorageAdapter;
use test\eLife\Recommendations\ValidatingStorageAdapter;
use Throwable;
use function GuzzleHttp\Promise\all;

final class AppKernel implements HttpKernelInterface, TerminableInterface
{
    private $app;
    private $container;

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
        $this->container = new Psr11Container($this->app);

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

        $this->app['negotiator'] = function () {
            return new VersionedNegotiator();
        };

        $this->app->get('/recommendations/{type}/{id}', function (Request $request, string $type, string $id) {
            $controller = new RecommendationsController($this->app['elife.api_sdk'], $this->app['negotiator']);

            return $controller->recommendationsAction($request, $type, $id);
        });

        $this->app->after(function (Request $request, Response $response, Application $app) {
            if ($response->isCacheable()) {
                $response->headers->set('ETag', md5($response->getContent()));
                $response->isNotModified($request);
            }
        });

        $this->app->error(function (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage();
                $extra = [];
            } elseif ($e instanceof UnsupportedVersion) {
                $status = Response::HTTP_NOT_ACCEPTABLE;
                $message = $e->getMessage();
                $extra = [];
            } else {
                $status = Response::HTTP_INTERNAL_SERVER_ERROR;
                $extra = [
                    'exception' => $e->getMessage(),
                    'stacktrace' => $e->getTraceAsString(),
                ];
            }

            $problem = new ApiProblem(empty($message) ? 'Error' : $message, null);

            foreach ($extra as $key => $value) {
                $problem[$key] = $value;
            }

            return new JsonResponse(
                $problem->asArray(),
                $status,
                ['Content-Type' => 'application/problem+json']
            );
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

    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }
}
