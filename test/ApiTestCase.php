<?php

namespace test\eLife\Recommendations;

use ComposerLocator;
use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\MockMiddleware;
use eLife\ApiClient\ApiClient\ArticlesClient;
use eLife\ApiClient\ApiClient\SearchClient;
use eLife\ApiClient\HttpClient;
use eLife\ApiClient\HttpClient\Guzzle6HttpClient;
use eLife\ApiClient\MediaType;
use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\HasIdentifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PathBasedSchemaFinder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use function GuzzleHttp\json_encode;

abstract class ApiTestCase extends TestCase
{
    use HasDiactorosFactory;

    /** @var InMemoryStorageAdapter */
    private $storage;

    /** @var HttpClient */
    private $httpClient;

    /** @var JsonMessageValidator */
    private $validator;

    /**
     * @before
     */
    final public function setUpValidator()
    {
        $this->validator = new JsonMessageValidator(
            new PathBasedSchemaFinder(ComposerLocator::getPath('elife/api').'/dist/model'),
            new Validator()
        );
    }

    /**
     * @after
     */
    final public function resetMocks()
    {
        $this->httpClient = null;
    }

    final protected function getHttpClient() : HttpClient
    {
        if (null === $this->httpClient) {
            $this->storage = new ValidatingStorageAdapter(new InMemoryStorageAdapter(), $this->validator);

            $stack = HandlerStack::create();
            $stack->push(new MockMiddleware($this->storage, 'replay'));

            $this->httpClient = new Guzzle6HttpClient(new Client([
                'base_uri' => 'http://api.elifesciences.org',
                'handler' => $stack,
            ]));
        }

        return $this->httpClient;
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
        array $types = []
    ) {
        $typesQuery = implode('', array_map(function (string $type) {
            return "&type[]=$type";
        }, $types));

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
                "http://api.elifesciences.org/search?for=&page=$page&per-page=$perPage&sort=date&order=desc$typesQuery&use-date=default",
                ['Accept' => new MediaType(SearchClient::TYPE_SEARCH, 1)]
            ),
            new Response(
                200,
                ['Content-Type' => new MediaType(SearchClient::TYPE_SEARCH, 1)],
                json_encode($json)
            )
        );
    }

    final protected function createArticlePoA(string $id, string $type = 'research-article', array $subjects = []) : ArticlePoA
    {
        return $this->denormalize([
            'status' => 'poa',
            'id' => $id,
            'version' => 1,
            'type' => $type,
            'doi' => "10.7554/eLife.$id",
            'title' => "Article $id",
            'stage' => 'published',
            'published' => '2016-03-28T00:00:00Z',
            'statusDate' => '2016-03-28T00:00:00Z',
            'volume' => 5,
            'elocationId' => "e$id",
        ], ArticlePoA::class);
    }

    final protected function denormalize(array $json, string $type) : Model
    {
        return $this->getApiSdk()->getSerializer()->denormalize($json, $type, 'json', ['snippet' => true]);
    }

    final protected function normalize(Model $model) : array
    {
        return $this->getApiSdk()->getSerializer()->normalize($model, 'json', ['snippet' => true]);
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
