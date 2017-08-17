<?php

namespace eLife\Recommendations;

use Crell\ApiProblem\ApiProblem;
use eLife\ApiClient\Exception\BadResponse;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Negotiation\Accept;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

require_once __DIR__.'/../vendor/autoload.php';

$config = $config ?? [];

$app = new Application([
    'api.uri' => $config['api.uri'] ?? 'https://api.elifesciences.org/',
    'api.timeout' => $config['api.timeout'] ?? 1,
]);

$app['elife.guzzle_client'] = function () use ($app) {
    return new Client([
        'base_uri' => $app['api.uri'],
        'connect_timeout' => 0.5,
        'timeout' => $app['api.timeout'],
    ]);
};

$app['elife.api_client'] = function () use ($app) {
    return new Guzzle6HttpClient($app['elife.guzzle_client']);
};

$app['elife.api_sdk'] = function () use ($app) {
    return new ApiSdk($app['elife.api_client']);
};

$app['elife.api_sdk.serializer'] = function () use ($app) {
    return $app['elife.api_sdk']->getSerializer();
};

$app['negotiator'] = function () {
    return new VersionedNegotiator();
};

$app->get('/recommendations/{type}/{id}', function (Request $request, string $type, string $id) use ($app) {
    try {
        $identifier = Identifier::fromString("{$type}/{$id}");

        if ('article' !== $type) {
            throw new BadRequestHttpException('Not an article');
        }
    } catch (InvalidArgumentException $e) {
        throw new NotFoundHttpException();
    }

    $accepts = [
        'application/vnd.elife.recommendations+json; version=1',
    ];

    /** @var Accept $type */
    $type = $app['negotiator']->getBest($request->headers->get('Accept'), $accepts);

    $version = (int) $type->getParameter('version');
    $type = $type->getType();

    $page = $request->query->get('page', 1);
    $perPage = $request->query->get('per-page', 20);

    try {
        $article = $app['elife.api_sdk']->articles()->getHistory($id)->wait()->getVersions()[0];
    } catch (BadResponse $e) {
        switch ($e->getResponse()->getStatusCode()) {
            case Response::HTTP_GONE:
            case Response::HTTP_NOT_FOUND:
                throw new HttpException($e->getResponse()->getStatusCode(), "$identifier does not exist", $e);
        }

        throw $e;
    }

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

            return $order[$a->getType()] <=> $order[$b->getType()];
        });

    $recommendations = $relations;

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

    $headers = ['Content-Type' => sprintf('%s; version=%s', $type, $version)];

    return new ApiResponse(
        $content,
        Response::HTTP_OK,
        $headers
    );
});

$app->get('ping', function () use ($app) {
    return new Response(
        'pong',
        Response::HTTP_OK,
        [
            'Cache-Control' => 'must-revalidate, no-cache, no-store, private',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]
    );
});

$app->after(function (Request $request, Response $response, Application $app) {
    if ($response->isCacheable()) {
        $response->headers->set('ETag', md5($response->getContent()));
        $response->isNotModified($request);
    }
});

$app->error(function (Throwable $e) {
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

return $app;
