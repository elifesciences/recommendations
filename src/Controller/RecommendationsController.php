<?php

namespace eLife\Recommendations\Controller;

use DateTimeImmutable;
use eLife\ApiClient\Exception\BadResponse;
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
use eLife\Recommendations\ApiResponse;
use InvalidArgumentException;
use Negotiation\Accept;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function GuzzleHttp\Promise\all;

final class RecommendationsController
{
    private $apiSdk;

    public function __construct(ApiSdk $apiSdk)
    {
        $this->apiSdk = $apiSdk;
    }

    public function recommendationsAction(Request $request, Accept $type, string $contentType, string $id) : Response
    {
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

        $collections = $this->apiSdk->collections()
            ->containing(Identifier::article($id))
            ->slice(0, 100);

        $mostRecent = $this->apiSdk->search()
            ->forType('research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study')
            ->sortBy('date')
            ->slice(0, 5);

        $mostRecentWithSubject = new PromiseSequence($article
            ->then(function (ArticleHistory $history) {
                $article = $history->getVersions()[0];

                if ($article->getSubjects()->isEmpty()) {
                    return new EmptySequence();
                }

                $subject = $article->getSubjects()[0];

                return $this->apiSdk->search()
                    ->forType('correction', 'editorial', 'feature', 'insight', 'research-advance', 'research-article', 'retraction', 'registered-report', 'replication-study', 'scientific-correspondence', 'short-report', 'tools-resources')
                    ->sortBy('date')
                    ->forSubject($subject->getId())
                    ->slice(0, 5);
            }));

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
            all([$article, $relations, $collections, $podcastEpisodeChapters, $mostRecent, $mostRecentWithSubject])->wait();
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
        $recommendations = $appendFirstThatDoesNotAlreadyExist($recommendations, $mostRecent);

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
            ->map(function (Model $model) {
                return json_decode($this->apiSdk->getSerializer()->serialize($model, 'json', [
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
    }
}
