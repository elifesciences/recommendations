<?php

namespace eLife\Recommendations;

use Csa\GuzzleHttp\Middleware\Stopwatch\StopwatchMiddleware;
use DateTimeImmutable;
use eLife\ApiClient\Exception\BadResponse;
use eLife\ApiClient\HttpClient;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiClient\HttpClient\WarningCheckingHttpClient;
use eLife\ApiProblem\Silex\ApiProblemProvider;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Collection\EmptySequence;
use eLife\ApiSdk\Collection\PromiseSequence;
use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ArticleHistory;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\Block;
use eLife\ApiSdk\Model\HasPublishedDate;
use eLife\ApiSdk\Model\HasSubjects;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiSdk\Model\PodcastEpisodeChapterModel;
use eLife\ApiSdk\Model\ReviewedPreprint;
use eLife\ContentNegotiator\Silex\ContentNegotiationProvider;
use eLife\Logging\Silex\LoggerProvider;
use eLife\Ping\Silex\PingControllerProvider;
use eLife\Recommendations\HttpClient as RecommendationsHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use function GuzzleHttp\Promise\all;
use InvalidArgumentException;
use LogicException;
use Negotiation\Accept;
use Psr\Log\LogLevel;
use Silex\Application;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$configFile = __DIR__.'/../config.php';

$config = array_merge($config ?? [], file_exists($configFile) ? require $configFile : []);

$app = new Application([
    'api.uri' => $config['api.uri'] ?? 'https://api.elifesciences.org/',
    'api.timeout' => $config['api.timeout'] ?? 1,
    'debug' => $config['debug'] ?? false,
    'logger.channel' => 'recommendations',
    'logger.path' => $config['logger.path'] ?? __DIR__.'/../var/logs',
    'logger.level' => $config['logger.level'] ?? LogLevel::INFO,
]);

$app->register(new ApiProblemProvider());
$app->register(new ContentNegotiationProvider());
$app->register(new LoggerProvider());
$app->register(new PingControllerProvider());

if ($app['debug']) {
    $app->register(new HttpFragmentServiceProvider());
    $app->register(new ServiceControllerServiceProvider());
    $app->register(new TwigServiceProvider());
    $app->register(new WebProfilerServiceProvider(), [
        'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
        'profiler.mount_prefix' => '/_profiler',
    ]);
    $app->get('/error', function () use ($app) {
        $app['logger']->debug('Simulating error');
        throw new LogicException('Simulated error');
    });
}

$app['elife.guzzle_client.handler'] = function () {
    return HandlerStack::create();
};

if ($app['debug']) {
    $app->extend('elife.guzzle_client.handler', function (HandlerStack $stack) use ($app) {
        $stack->unshift(new StopwatchMiddleware($app['stopwatch']));

        return $stack;
    });
}

$app['elife.guzzle_client'] = function () use ($app) {
    return new Client([
        'base_uri' => $app['api.uri'],
        'connect_timeout' => 0.5,
        'handler' => $app['elife.guzzle_client.handler'],
        'timeout' => $app['api.timeout'],
    ]);
};

$app['elife.api_client'] = function () use ($app) {
    return new Guzzle6HttpClient($app['elife.guzzle_client']);
};

if ($app['debug']) {
    $app->extend('elife.api_client', function (HttpClient $httpClient) use ($app) {
        return new WarningCheckingHttpClient($httpClient, $app['logger']);
    });
}

$app->extend('elife.api_client', function (HttpClient $httpClient) use ($app) {
    return new RecommendationsHttpClient($httpClient);
});

$app['elife.api_sdk'] = function () use ($app) {
    return new ApiSdk($app['elife.api_client']);
};

$app['elife.api_sdk.serializer'] = function () use ($app) {
    return $app['elife.api_sdk']->getSerializer();
};

$app->get('/recommendations/{contentType}/{id}', function (Request $request, Accept $type, string $contentType, string $id) use ($app) {
    try {
        $identifier = Identifier::fromString("{$contentType}/{$id}");

        if ('article' !== $contentType) {
            throw new BadRequestHttpException('Not an article');
        }
    } catch (InvalidArgumentException $e) {
        throw new NotFoundHttpException();
    }

    $page = $request->query->get('page', 1);
    $perPage = $request->query->get('per-page', 20);

    /** @var ApiSdk $appSdk  */
    $appSdk = $app['elife.api_sdk'];
    $article =  $appSdk->articles()->getHistory($id);

    $relations = $appSdk->articles()
        ->getRelatedArticles($id)
        ->sort(function (Model $a, Model $b) {
            $aType = $a instanceof ReviewedPreprint ? 'reviewed-preprint' : $a->getType();
            $bType = $b instanceof ReviewedPreprint ? 'reviewed-preprint' : $b->getType();

            static $order = [
                'retraction' => 1,
                'correction' => 2,
                'expression-concern' => 3,
                'external-article' => 4,
                'registered-report' => 5,
                'replication-study' => 6,
                'research-advance' => 7,
                'scientific-correspondence' => 8,
                'research-article' => 9,
                'research-communication' => 10,
                'tools-resources' => 11,
                'feature' => 12,
                'insight' => 13,
                'editorial' => 14,
                'short-report' => 15,
                'review-article' => 16,
                'reviewed-preprint' => 17,
            ];

            if ($order[$aType] === $order[$bType]) {
                $aDate = $a instanceof HasPublishedDate ? $a->getPublishedDate() : new DateTimeImmutable('0000-00-00');
                $bDate = $b instanceof HasPublishedDate ? $b->getPublishedDate() : new DateTimeImmutable('0000-00-00');

                return $bDate <=> $aDate;
            }

            return $order[$aType] <=> $order[$bType];
        });

    $collections = $appSdk->collections()
        ->containing(Identifier::article($id))
        ->slice(0, 100);

    $ignoreSelf = function (Article $article) use ($id) {
        return $article->getId() !== $id;
    };

    $podcastEpisodeChapters = $appSdk->podcastEpisodes()
        ->containing(Identifier::article($id))
        ->slice(0, 100)
        ->reduce(function (Sequence $chapters, PodcastEpisode $episode) use ($id) {
            foreach ($episode->getChapters() as $chapter) {
                foreach ($chapter->getContent() as $content) {
                    if ($id === $content->getId()) {
                        $chapters = $chapters->append(new PodcastEpisodeChapterModel($episode, $chapter));
                        continue 2;
                    }
                }
            }

            return $chapters;
        }, new EmptySequence());

    $recommendations = $relations;

    try {
        all([$article, $relations, $collections, $podcastEpisodeChapters])->wait();
    } catch (BadResponse $e) {
        switch ($e->getResponse()->getStatusCode()) {
            case Response::HTTP_GONE:
            case Response::HTTP_NOT_FOUND:
                throw new HttpException($e->getResponse()->getStatusCode(), "$identifier does not exist", $e);
        }

        throw $e;
    }

    $recommendations = $recommendations->append(...$collections);
    $recommendations = $recommendations->append(...$podcastEpisodeChapters);

    foreach ($recommendations as $model) {
        if ($model instanceof ReviewedPreprint && $type->getParameter('version') < 3) {
            throw new HttpException(406, 'This recommendation requires version 3.');
        }
    }

    $content = [
        'total' => count($recommendations),
    ];

    $recommendations = $recommendations->slice(((int) $page * $perPage) - $perPage, $perPage);

    if ($page < 1 || (0 === count($recommendations) && $page > 1)) {
        throw new NotFoundHttpException('No page '.$page);
    }

    if ('asc' === $request->query->get('order', 'desc')) {
        $recommendations = $recommendations->reverse();
    }

    $content['items'] = $recommendations
        ->map(function (Model $model) use ($app, $type) {
            $shouldContainAbstract = $type->getParameter('version') > 1;
            if ($shouldContainAbstract && $model instanceof ArticleVersion) {
                $abstract = $app['elife.api_sdk']->articles()
                    ->get($model->getId())
                    ->then(function (ArticleVersion $complete) use ($app) {
                        if ($complete->getAbstract()) {
                            $abstract = [
                                'content' => $complete->getAbstract()->getContent()->map(function (Block $block) use ($app) {
                                    return json_decode($app['elife.api_sdk.serializer']->serialize($block, 'json'), true);
                                })->toArray(),
                            ];

                            if ($complete->getAbstract()->getDoi()) {
                                $abstract['doi'] = $complete->getAbstract()->getDoi();
                            }

                            return $abstract;
                        }
                    })
                    ->wait();
                if ($abstract) {
                    $data['abstract'] = $abstract;
                }
            }

            return ($data ?? []) + json_decode($app['elife.api_sdk.serializer']->serialize($model, 'json', [
                    'snippet' => true,
                    'type' => true,
                ]), true);
        })
        ->toArray();

    $headers = ['Content-Type' => $type->getNormalizedValue()];

    $app['logger']->info('Calls made to ApiSdk: '.$app['elife.api_client']->count(), [
        'count' => $app['elife.api_client']->count(),
        'identifier' => $identifier->getType().'/'.$identifier->getId(),
        'details' => $app['elife.api_client']->getDetails(),
    ]);

    return new ApiResponse(
        $content,
        Response::HTTP_OK,
        $headers
    );
})->before($app['negotiate.accept'](
    'application/vnd.elife.recommendations+json; version=3',
    'application/vnd.elife.recommendations+json; version=2'
));

$app->after(function (Request $request, Response $response, Application $app) {
    if ($response->isCacheable()) {
        $response->headers->set('ETag', md5($response->getContent()));
        $response->isNotModified($request);
    }
});

return $app;
