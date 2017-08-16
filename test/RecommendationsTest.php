<?php

namespace test\eLife\Recommendations;

final class RecommendationsTest extends WebTestCase
{
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
        $this->assertJsonStringEqualsJson(['title' => 'Not an article'], $response->getContent());
        $this->assertFalse($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_displays_a_404_if_the_article_is_not_found()
    {
        $client = static::createClient();

        $this->mockNotFound('articles/1234/related', ['Accept' => 'application/vnd.elife.article-related+json; version=1']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertJsonStringEqualsJson(['title' => 'article/1234 does not exist'], $response->getContent());
    }
}
