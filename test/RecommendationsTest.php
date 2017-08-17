<?php

namespace test\eLife\Recommendations;

use Traversable;

final class RecommendationsTest extends WebTestCase
{
    /**
     * @test
     */
    public function it_returns_empty_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['total' => 0, 'items' => []], $response->getContent());
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_returns_order_related_article_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1237', 'research-article')]);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 3,
                'items' => [
                    $this->normalize($this->createArticlePoA('1237', 'research-article')),
                    $this->normalize($this->createArticlePoA('1235', 'insight')),
                    $this->normalize($this->createArticlePoA('1236', 'short-report')),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_returns_most_recent_article_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 1,
                'items' => [
                    $this->normalize($this->createArticlePoA('1235', 'insight')),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_does_not_duplicate_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1237', 'research-article')]);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1238', 'research-article'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 4,
                'items' => [
                    $this->normalize($this->createArticlePoA('1237', 'research-article')),
                    $this->normalize($this->createArticlePoA('1235', 'insight')),
                    $this->normalize($this->createArticlePoA('1236', 'short-report')),
                    $this->normalize($this->createArticlePoA('1238', 'research-article')),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     * @dataProvider invalidPageProvider
     */
    public function it_returns_a_404_for_an_invalid_page(string $page)
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', "/recommendations/article/1234?page=$page");
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['title' => "No page $page"], $response->getContent());
        $this->assertFalse($response->isCacheable());
    }

    public function invalidPageProvider() : Traversable
    {
        foreach (['-1', '0', '2', 'foo'] as $page) {
            yield 'page '.$page => [$page];
        }
    }

    /**
     * @test
     */
    public function it_returns_a_400_for_a_non_article()
    {
        $client = static::createClient();

        $client->request('GET', '/recommendations/interview/1234');
        $response = $client->getResponse();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['title' => 'Not an article'], $response->getContent());
        $this->assertFalse($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_displays_a_404_if_the_article_is_not_found()
    {
        $client = static::createClient();

        $this->mockNotFound('articles/1234/versions', ['Accept' => 'application/vnd.elife.article-history+json; version=1']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['title' => 'article/1234 does not exist'], $response->getContent());
        $this->assertFalse($client->getResponse()->isCacheable());
    }
}
