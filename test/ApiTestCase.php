<?php

namespace test\eLife\Recommendations;

use ComposerLocator;
use Csa\GuzzleHttp\Middleware\Cache\MockMiddleware;
use DateTimeImmutable;
use eLife\ApiClient\ApiClient\ArticlesClient;
use eLife\ApiClient\ApiClient\CollectionsClient;
use eLife\ApiClient\ApiClient\PodcastClient;
use eLife\ApiClient\ApiClient\SearchClient;
use eLife\ApiClient\MediaType;
use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\Collection;
use eLife\ApiSdk\Model\HasIdentifier;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PathBasedSchemaFinder;
use function GuzzleHttp\json_encode;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

abstract class ApiTestCase extends TestCase
{
    use HasDiactorosFactory;

    /** @var InMemoryStorageAdapter */
    private $storage;

    /** @var MockMiddleware */
    private $mock;

    /** @var JsonMessageValidator */
    private $validator;

    /**
     * @before
     */
    final public function setUpMock()
    {
        $this->validator = new JsonMessageValidator(
            new PathBasedSchemaFinder(ComposerLocator::getPath('elife/api').'/dist/model'),
            new Validator()
        );
        $this->storage = new ValidatingStorageAdapter(new InMemoryStorageAdapter(), $this->validator);
        $this->mock = new MockMiddleware($this->storage, 'replay');
    }

    final protected function getMock() : MockMiddleware
    {
        return $this->mock;
    }

    abstract protected function getApiSdk();

    final protected function mockNotFound(string $uri, array $headers = [])
    {
        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/$uri",
                $headers
            ),
            new Response(
                404,
                ['Content-Type' => 'application/problem+json'],
                json_encode([
                    'title' => 'Not found',
                ])
            )
        );
    }

    final protected function mockArticleVersionsCall(string $id, array $versions)
    {
        $response = new Response(
            200,
            ['Content-Type' => new MediaType(ArticlesClient::TYPE_ARTICLE_HISTORY, 1)],
            json_encode([
                'versions' => array_map([$this, 'normalize'], $versions),
            ])
        );

        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/articles/$id/versions",
                [
                    'Accept' => [
                        new MediaType(ArticlesClient::TYPE_ARTICLE_HISTORY, 1),
                    ],
                ]
            ),
            $response
        );
    }

    final protected function mockCollectionsCall(
        int $total,
        array $collections,
        int $page = 1,
        int $perPage = 100,
        array $containing = []
    ) {
        $containingQuery = implode('', array_map(function (Identifier $identifier) {
            return "&containing[]=$identifier";
        }, $containing));

        $json = [
            'total' => $total,
            'items' => array_map([$this, 'normalize'], $collections),
        ];

        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/collections?page=$page&per-page=$perPage&order=desc$containingQuery",
                ['Accept' => new MediaType(CollectionsClient::TYPE_COLLECTION_LIST, 1)]
            ),
            new Response(
                200,
                ['Content-Type' => new MediaType(CollectionsClient::TYPE_COLLECTION_LIST, 1)],
                json_encode($json)
            )
        );
    }

    final protected function mockPodcastEpisodeCall(PodcastEpisode $episode)
    {
        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/podcast-episodes/{$episode->getNumber()}",
                ['Accept' => new MediaType(PodcastClient::TYPE_PODCAST_EPISODE, 1)]
            ),
            new Response(
                200,
                ['Content-Type' => new MediaType(PodcastClient::TYPE_PODCAST_EPISODE, 1)],
                json_encode($this->normalize($episode, false))
            )
        );
    }

    final protected function mockPodcastEpisodesCall(
        int $total,
        array $podcastEpisodes,
        int $page = 1,
        int $perPage = 100,
        array $containing = []
    ) {
        $containingQuery = implode('', array_map(function (Identifier $identifier) {
            return "&containing[]=$identifier";
        }, $containing));

        $json = [
            'total' => $total,
            'items' => array_map([$this, 'normalize'], $podcastEpisodes),
        ];

        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/podcast-episodes?page=$page&per-page=$perPage&order=desc$containingQuery",
                ['Accept' => new MediaType(PodcastClient::TYPE_PODCAST_EPISODE_LIST, 1)]
            ),
            new Response(
                200,
                ['Content-Type' => new MediaType(PodcastClient::TYPE_PODCAST_EPISODE_LIST, 1)],
                json_encode($json)
            )
        );
    }

    final protected function mockRelatedArticlesCall(string $id, array $articles)
    {
        $response = new Response(
            200,
            ['Content-Type' => new MediaType(ArticlesClient::TYPE_ARTICLE_RELATED, 1)],
            json_encode(array_map([$this, 'normalize'], $articles))
        );

        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/articles/$id/related",
                [
                    'Accept' => [
                        new MediaType(ArticlesClient::TYPE_ARTICLE_RELATED, 1),
                    ],
                ]
            ),
            $response
        );
    }

    final protected function mockSearchCall(
        int $total,
        array $items,
        int $page = 1,
        int $perPage = 100,
        array $types = [],
        array $subjects = []
    ) {
        $typesQuery = implode('', array_map(function (string $type) {
            return "&type[]=$type";
        }, $types));

        $subjectsQuery = implode('', array_map(function (string $subject) {
            return "&subject[]=$subject";
        }, $subjects));

        $json = [
            'total' => $total,
            'items' => array_map([$this, 'normalize'], $items),
            'subjects' => [],
            'types' => array_reduce([
                'correction',
                'editorial',
                'feature',
                'insight',
                'research-advance',
                'research-article',
                'research-communication',
                'retraction',
                'registered-report',
                'replication-study',
                'scientific-correspondence',
                'short-report',
                'tools-resources',
                'blog-article',
                'collection',
                'interview',
                'labs-post',
                'podcast-episode',
            ], function (array $carry, string $type) use ($items) {
                $carry[$type] = count(array_filter($items, function (HasIdentifier $model) use ($type) {
                    return $type === $model->getIdentifier();
                }));

                return $carry;
            }, []),
        ];

        $this->storage->save(
            new Request(
                'GET',
                "http://api.elifesciences.org/search?for=&page=$page&per-page=$perPage&sort=date&order=desc$subjectsQuery$typesQuery&use-date=default",
                ['Accept' => new MediaType(SearchClient::TYPE_SEARCH, 1)]
            ),
            new Response(
                200,
                ['Content-Type' => new MediaType(SearchClient::TYPE_SEARCH, 1)],
                json_encode($json)
            )
        );
    }

    final protected function createArticlePoA(string $id, string $type = 'research-article', array $subjects = [], DateTimeImmutable $publishedDate = null) : ArticlePoA
    {
        if (!$publishedDate) {
            $publishedDate = DateTimeImmutable::createFromFormat(DATE_ATOM, '2016-03-28T00:00:00Z');
        }

        return $this->denormalize(array_filter([
            'status' => 'poa',
            'id' => $id,
            'version' => 1,
            'type' => $type,
            'doi' => "10.7554/eLife.$id",
            'title' => "Article $id",
            'stage' => 'published',
            'published' => $publishedDate->format(DATE_ATOM),
            'statusDate' => $publishedDate->format(DATE_ATOM),
            'volume' => 5,
            'elocationId' => "e$id",
            'subjects' => array_map(function (string $subject) {
                return array_fill_keys(['id', 'name'], $subject);
            }, $subjects),
        ]), ArticlePoA::class);
    }

    final protected function createCollection(string $id) : Collection
    {
        return $this->denormalize(
            [
                'id' => $id,
                'title' => "Collection $id",
                'published' => '2015-09-16T11:19:26Z',
                'image' => [
                    'thumbnail' => [
                        'uri' => 'https://www.example.com/image',
                        'alt' => '',
                        'source' => [
                            'mediaType' => 'image/jpeg',
                            'uri' => 'https://www.example.com/image/full/full/0/default.jpg',
                            'filename' => 'image.jpg',
                        ],
                        'size' => [
                            'width' => 1000,
                            'height' => 1000,
                        ],
                    ],
                ],
                'selectedCurator' => [
                    'id' => 'person',
                    'type' => [
                        'id' => 'senior-editor',
                        'label' => 'Senior Editor',
                    ],
                    'name' => [
                        'preferred' => 'Person',
                        'index' => 'Curator',
                    ],
                ],
            ], Collection::class);
    }

    final protected function createPodcastEpisode(int $number, array $chapters) : PodcastEpisode
    {
        return $this->denormalize(
            [
                'number' => $number,
                'title' => "Episode $number",
                'published' => '2016-07-01T08:30:15Z',
                'image' => [
                    'banner' => [
                        'uri' => 'https://www.example.com/image',
                        'alt' => '',
                        'source' => [
                            'mediaType' => 'image/jpeg',
                            'uri' => 'https://www.example.com/image/full/full/0/default.jpg',
                            'filename' => 'image.jpg',
                        ],
                        'size' => [
                            'width' => 1000,
                            'height' => 1000,
                        ],
                    ],
                    'thumbnail' => [
                        'uri' => 'https://www.example.com/image',
                        'alt' => '',
                        'source' => [
                            'mediaType' => 'image/jpeg',
                            'uri' => 'https://www.example.com/image/full/full/0/default.jpg',
                            'filename' => 'image.jpg',
                        ],
                        'size' => [
                            'width' => 1000,
                            'height' => 1000,
                        ],
                    ],
                ],
                'sources' => [
                    [
                        'mediaType' => 'audio/mpeg',
                        'uri' => "https://www.example.com/episode$number.mp3",
                    ],
                ],
                'chapters' => $chapters,
            ], PodcastEpisode::class, false);
    }

    final protected function createPodcastEpisodeChapter(int $number, array $content) : array
    {
        return [
            'number' => $number,
            'title' => "Chapter $number",
            'time' => $number,
            'content' => array_map([$this, 'normalize'], $content),
        ];
    }

    final protected function denormalize(array $json, string $type, bool $snippet = true) : Model
    {
        return $this->getApiSdk()->getSerializer()->denormalize($json, $type, 'json', ['snippet' => $snippet, 'type' => $snippet]);
    }

    final protected function normalize(Model $model, bool $snippet = true) : array
    {
        return $this->getApiSdk()->getSerializer()->normalize($model, 'json', ['snippet' => $snippet, 'type' => $snippet]);
    }

    final protected function assertResponseIsValid(HttpFoundationResponse $response)
    {
        $this->assertMessageIsValid($this->getDiactorosFactory()->createResponse($response));
    }

    final protected function assertMessageIsValid(MessageInterface $message)
    {
        $this->validator->validate($message);
    }
}
