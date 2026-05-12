<?php

namespace eLife\Recommendations\Controller;

use DateTimeImmutable;
use eLife\ApiClient\Exception\BadResponse;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Collection\EmptySequence;
use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\Block;
use eLife\ApiSdk\Model\HasPublishedDate;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiSdk\Model\PodcastEpisodeChapterModel;
use eLife\ApiSdk\Model\ReviewedPreprint;
use eLife\ContentNegotiator\ContentNegotiator;
use eLife\Recommendations\ApiResponse;
use eLife\Recommendations\HttpClient;
use function GuzzleHttp\Promise\all;
use InvalidArgumentException;
use Negotiation\Accept;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
final class RecommendationsController
{
    public function __construct(
        private ApiSdk $apiSdk,
        private HttpClient $httpClient,
        private ContentNegotiator $contentNegotiator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, string $contentType, string $id): Response
    {
        $this->contentNegotiator->negotiate($request, [
            'application/vnd.elife.recommendations+json; version=3',
            'application/vnd.elife.recommendations+json; version=2',
        ]);
        /** @var Accept $type */
        $type = $request->attributes->get('_accept');

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

        $article = $this->apiSdk->articles()->getHistory($id);

        $relations = $this->apiSdk->articles()
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

        $collections = $this->apiSdk->collections()
            ->containing(Identifier::article($id))
            ->slice(0, 100);

        $podcastEpisodeChapters = $this->apiSdk->podcastEpisodes()
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

        $serializer = $this->apiSdk->getSerializer();

        $content['items'] = $recommendations
            ->map(function (Model $model) use ($type, $serializer) {
                $data = [];
                if ($type->getParameter('version') > 1 && $model instanceof ArticleVersion) {
                    $abstract = $this->apiSdk->articles()
                        ->get($model->getId())
                        ->then(function (ArticleVersion $complete) use ($serializer) {
                            if ($complete->getAbstract()) {
                                $abstract = [
                                    'content' => $complete->getAbstract()->getContent()->map(function (Block $block) use ($serializer) {
                                        return json_decode($serializer->serialize($block, 'json'), true);
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

                return $data + json_decode($serializer->serialize($model, 'json', [
                    'snippet' => true,
                    'type' => true,
                ]), true);
            })
            ->toArray();

        $this->logger->info('Calls made to ApiSdk: '.$this->httpClient->count(), [
            'count' => $this->httpClient->count(),
            'identifier' => $identifier->getType().'/'.$identifier->getId(),
            'details' => $this->httpClient->getDetails(),
            'request_headers' => $request->headers->all(),
        ]);

        return new ApiResponse(
            $content,
            Response::HTTP_OK,
            ['Content-Type' => $type->getNormalizedValue()]
        );
    }
}
