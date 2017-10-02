<?php

namespace test\eLife\Recommendations;

use DateTimeImmutable;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\PodcastEpisodeChapterModel;
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
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
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

        $insight = $this->createArticlePoA('1235', 'insight');
        $shortReport = $this->createArticlePoA('1236', 'short-report');
        $research1 = $this->createArticlePoA('1237', 'research-article', [], new DateTimeImmutable('yesterday'));
        $research2 = $this->createArticlePoA('1238', 'research-article', [], new DateTimeImmutable('today'));
        $research3 = $this->createArticlePoA('1239', 'research-article', [], new DateTimeImmutable('2 days ago'));

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', [$insight, $shortReport, $research1, $research2, $research3]);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 5,
                'items' => [
                    $this->normalize($research2),
                    $this->normalize($research1),
                    $this->normalize($research3),
                    $this->normalize($insight),
                    $this->normalize($shortReport),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_returns_collection_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [$this->createCollection('1234'), $this->createCollection('5678')], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 2,
                'items' => [
                    $this->normalize($this->createCollection('1234')),
                    $this->normalize($this->createCollection('5678')),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_returns_podcast_episode_chapter_recommendations_for_an_article()
    {
        $client = static::createClient();

        $chapter = $this->createPodcastEpisodeChapter(1, [$this->createArticlePoA('1234'), $this->createArticlePoA('1235')]);
        $episode = $this->createPodcastEpisode(1, [$chapter]);

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [$episode], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodeCall($episode);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));

        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 1,
                'items' => [
                    $this->normalize(new PodcastEpisodeChapterModel($episode, $episode->getChapters()[0])),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_returns_most_recent_article_with_first_subject_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234', 'research-article', ['subject2', 'subject1'])]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['correction', 'editorial', 'feature', 'insight', 'research-advance', 'research-article', 'retraction', 'registered-report', 'replication-study', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);

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
    public function it_returns_most_recent_article_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
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

        $episode1Chapter1 = $this->createPodcastEpisodeChapter(1, [$this->createArticlePoA('1234'), $this->createArticlePoA('1235')]);
        $episode1Chapter2 = $this->createPodcastEpisodeChapter(2, [$this->createArticlePoA('1235')]);
        $episode1Chapter3 = $this->createPodcastEpisodeChapter(3, [$this->createArticlePoA('1234')]);
        $episode2Chapter1 = $this->createPodcastEpisodeChapter(1, [$this->createArticlePoA('1236'), $this->createArticlePoA('1234')]);
        $episode1 = $this->createPodcastEpisode(1, [$episode1Chapter1, $episode1Chapter2, $episode1Chapter3]);
        $episode2 = $this->createPodcastEpisode(2, [$episode2Chapter1]);

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234', 'research-article', ['subject2', 'subject1'])]);
        $this->mockRelatedArticlesCall('1234', [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1237', 'research-article')]);
        $this->mockCollectionsCall(0, [$this->createCollection('1234'), $this->createCollection('5678')], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [$episode2, $episode1], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodeCall($episode1);
        $this->mockPodcastEpisodeCall($episode2);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1238', 'research-article'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['correction', 'editorial', 'feature', 'insight', 'research-advance', 'research-article', 'retraction', 'registered-report', 'replication-study', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1238', 'research-article'), $this->createArticlePoA('1240', 'research-article'), $this->createArticlePoA('1239', 'research-article')], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=1', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 10,
                'items' => [
                    $this->normalize($this->createArticlePoA('1237', 'research-article')),
                    $this->normalize($this->createArticlePoA('1235', 'insight')),
                    $this->normalize($this->createArticlePoA('1236', 'short-report')),
                    $this->normalize($this->createCollection('1234')),
                    $this->normalize($this->createCollection('5678')),
                    $this->normalize(new PodcastEpisodeChapterModel($episode2, $episode1->getChapters()[0])),
                    $this->normalize(new PodcastEpisodeChapterModel($episode1, $episode1->getChapters()[0])),
                    $this->normalize(new PodcastEpisodeChapterModel($episode1, $episode1->getChapters()[2])),
                    $this->normalize($this->createArticlePoA('1238', 'research-article')),
                    $this->normalize($this->createArticlePoA('1240', 'research-article')),
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
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
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
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockSearchCall(0, [], 1, 5, ['research-advance', 'research-article', 'scientific-correspondence', 'short-report', 'tools-resources', 'replication-study']);

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['title' => 'article/1234 does not exist'], $response->getContent());
        $this->assertFalse($client->getResponse()->isCacheable());
    }
}
