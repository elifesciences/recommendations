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

        $client->request('GET', '/recommendations/interviews/1234');

        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $this->assertSame('application/problem+json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertJsonStringEqualsJson(['type' => 'about:blank'], $client->getResponse()->getContent());
        $this->assertFalse($client->getResponse()->isCacheable());
    }

    /**
     * @test
     */
    public function it_displays_a_404_if_the_article_is_not_found()
    {
        $client = static::createClient();

        $this->mockNotFound('articles/1234/related', ['Accept' => 'application/vnd.elife.article-related+json; version=1']);

        $client->request('GET', '/recommendations/article/1234');

        $this->assertSame(404, $client->getResponse()->getStatusCode());
        $this->assertSame('application/problem+json', $client->getResponse()->headers->get('Content-Type'));
        $this->assertJsonStringEqualsJson(['type' => 'about:blank', 'title' => 'article/1234 does not exist'], $client->getResponse()->getContent());
    }
}
