<?php

namespace eLife\Recommendations;

use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\StopwatchMiddleware;
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
use eLife\ApiSdk\Model\ExternalArticle;
use eLife\ApiSdk\Model\HasPublishedDate;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiSdk\Model\PodcastEpisodeChapterModel;
use eLife\ContentNegotiator\Silex\ContentNegotiationProvider;
use eLife\Logging\LoggingFactory;
use eLife\Ping\Silex\PingControllerProvider;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
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
use function GuzzleHttp\Promise\all;

$configFile = __DIR__.'/../config.php';

$config = array_merge($config ?? [], file_exists($configFile) ? require $configFile : []);

$app = new Application([
    'api.uri' => $config['api.uri'] ?? 'https://api.elifesciences.org/',
    'api.timeout' => $config['api.timeout'] ?? 1,
    'debug' => $config['debug'] ?? false,
    'logger.path' => $config['logger.path'] ?? __DIR__.'/../var/logs',
    'logger.level' => $config['logger.level'] ?? LogLevel::INFO,
]);

$app->register(new ApiProblemProvider());
$app->register(new ContentNegotiationProvider());
$app->register(new PingControllerProvider());

if ($app['debug']) {
    $app->register(new HttpFragmentServiceProvider());
    $app->register(new ServiceControllerServiceProvider());
    $app->register(new TwigServiceProvider());
    $app->register(new WebProfilerServiceProvider(), [
        'profiler.cache_dir' => __DIR__.'/../var/cache/profiler',
        'profiler.mount_prefix' => '/_profiler',
    ]);
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

$app['elife.api_sdk'] = function () use ($app) {
    return new ApiSdk($app['elife.api_client']);
};

$app['elife.api_sdk.serializer'] = function () use ($app) {
    return $app['elife.api_sdk']->getSerializer();
};

$app['elife.logger.factory'] = function (Application $app) {
    return new LoggingFactory($app['logger.path'], 'recommendations-api', $app['logger.level']);
};

$app['logger'] = function (Application $app) {
    return $app['elife.logger.factory']->logger();
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

    $article = $app['elife.api_sdk']->articles()->getHistory($id);

    $relations = $app['elife.api_sdk']->articles()
        ->getRelatedArticles($id)
        ->sort(function (Article $a, Article $b) {
            static $order = [
                'retraction' => 1,
                'correction' => 2,
                'external-article' => 3,
                'registered-report' => 4,
                'replication-study' => 5,
                'research-advance' => 6,
                'scientific-correspondence' => 7,
                'research-article' => 8,
                'tools-resources' => 9,
                'feature' => 10,
                'insight' => 11,
                'editorial' => 12,
                'short-report' => 13,
            ];

            if ($order[$a->getType()] === $order[$b->getType()]) {
                $aDate = $a instanceof HasPublishedDate ? $a->getPublishedDate() : new DateTimeImmutable('0000-00-00');
                $bDate = $b instanceof HasPublishedDate ? $b->getPublishedDate() : new DateTimeImmutable('0000-00-00');

                return $bDate <=> $aDate;
            }

            return $order[$a->getType()] <=> $order[$b->getType()];
        });

    $collections = $app['elife.api_sdk']->collections()
        ->containing(Identifier::article($id))
        ->slice(0, 100);

    $ignoreSelf = function (Article $article) use ($id) {
        return $article->getId() !== $id;
    };

    $mostRecentWithSubject = new PromiseSequence($article
        ->then(function (ArticleHistory $history) use ($app, $ignoreSelf) {
            $article = $history->getVersions()[0];

            if ($article->getSubjects()->isEmpty()) {
                return new EmptySequence();
            }

            $subject = $article->getSubjects()[0];

            return $app['elife.api_sdk']->search()
                ->forType('editorial', 'feature', 'insight', 'research-advance', 'research-article', 'registered-report', 'replication-study', 'scientific-correspondence', 'short-report', 'tools-resources')
                ->sortBy('date')
                ->forSubject($subject->getId())
                ->slice(0, 5)
                ->filter($ignoreSelf);
        }));

    $podcastEpisodeChapters = $app['elife.api_sdk']->podcastEpisodes()
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

    $appendFirstThatDoesNotAlreadyExist = function (Sequence $recommendations, Sequence $toInsert) : Sequence {
        foreach ($toInsert as $item) {
            foreach ($recommendations as $recommendation) {
                if (
                    get_class($item) === get_class($recommendation)
                    &&
                    (
                        ($item instanceof ExternalArticle && $item->getId() === $recommendation->getId())
                        ||
                        ($item instanceof PodcastEpisodeChapterModel && $item->getEpisode()->getNumber() === $recommendation->getEpisode()->getNumber() && $item->getChapter()->getNumber() === $recommendation->getChapter()->getNumber())
                        ||
                        $item->getIdentifier() == $recommendation->getIdentifier()
                    )
                ) {
                    continue 2;
                }
            }

            return $recommendations->append($item);
        }

        return $recommendations;
    };

    try {
        all([$article, $relations, $collections, $podcastEpisodeChapters, $mostRecentWithSubject])->wait();
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
    $recommendations = $appendFirstThatDoesNotAlreadyExist($recommendations, $mostRecentWithSubject);

    $content = [
        'total' => count($recommendations),
    ];

    $recommendations = $recommendations->slice(($page * $perPage) - $perPage, $perPage);

    if ($page < 1 || (0 === count($recommendations) && $page > 1)) {
        throw new NotFoundHttpException('No page '.$page);
    }

    if ('asc' === $request->query->get('order', 'desc')) {
        $recommendations = $recommendations->reverse();
    }

    $content['items'] = $recommendations
        ->map(function (Model $model) use ($app) {
            return json_decode($app['elife.api_sdk.serializer']->serialize($model, 'json', [
                'snippet' => true,
                'type' => true,
            ]), true);
        })
        ->toArray();

    $headers = ['Content-Type' => $type->getNormalizedValue()];

    return new ApiResponse(
        $content,
        Response::HTTP_OK,
        $headers
    );
})->before($app['negotiate.accept'](
    'application/vnd.elife.recommendations+json; version=1'
));

$app->after(function (Request $request, Response $response, Application $app) {
    if ($response->isCacheable()) {
        $response->headers->set('ETag', md5($response->getContent()));
        $response->isNotModified($request);
    }
});

return $app;
