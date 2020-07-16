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
     * @dataProvider typeProvider
     */
    public function it_negotiates_type(string $type, int $statusCode)
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);

        $client->request('GET', '/recommendations/article/1234', [], [], ['HTTP_ACCEPT' => $type]);
        $response = $client->getResponse();
        $this->assertSame($statusCode, $response->getStatusCode());
    }

    public function typeProvider() : Traversable
    {
        $types = [
            'application/vnd.elife.recommendations+json' => 200,
            'application/vnd.elife.recommendations+json; version=0' => 406,
            'application/vnd.elife.recommendations+json; version=1' => 200,
            'application/vnd.elife.recommendations+json; version=2' => 200,
            'application/vnd.elife.recommendations+json; version=3' => 406,
            'text/plain' => 406,
        ];

        foreach ($types as $type => $statusCode) {
            yield $type => [$type, $statusCode];
        }
    }

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

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
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

        $insightSnippet = $this->createArticlePoA('1235', 'insight');
        $shortReportSnippet = $this->createArticlePoA('1236', 'short-report');
        $research1Snippet = $this->createArticlePoA('1237', 'research-article', [], new DateTimeImmutable('yesterday'));
        $research2Snippet = $this->createArticlePoA('1238', 'research-article', [], new DateTimeImmutable('today'));
        $research3Snippet = $this->createArticlePoA('1239', 'research-article', [], new DateTimeImmutable('2 days ago'));

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234')]);
        $this->mockRelatedArticlesCall('1234', [$insightSnippet, $shortReportSnippet, $research1Snippet, $research2Snippet, $research3Snippet]);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->MockArticlePoACall('1235', $insight = $this->createArticlePoA('1235', 'insight', [], null, false));
        $this->MockArticlePoACall('1236', $shortReport = $this->createArticlePoA('1236', 'short-report', [], null, false));
        $this->MockArticlePoACall('1237', $research1 = $this->createArticlePoA('1237', 'research-article', [], new DateTimeImmutable('yesterday'), false));
        $this->MockArticlePoACall('1238', $research2 = $this->createArticlePoA('1238', 'research-article', [], new DateTimeImmutable('today'), false));
        $this->MockArticlePoACall('1239', $research3 = $this->createArticlePoA('1239', 'research-article', [], new DateTimeImmutable('2 days ago'), false));

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 5,
                'items' => array_map([$this, 'normalize'], [
                    $research2,
                    $research1,
                    $research3,
                    $insight,
                    $shortReport,
                ]),
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

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
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

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));

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
    public function it_returns_3_most_recent_articles_with_first_subject_recommendations_for_an_article()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234', 'research-article', ['subject2', 'subject1'])]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['editorial', 'feature', 'insight', 'research-advance', 'research-article', 'research-communication', 'registered-report', 'replication-study', 'review-article', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);
        $this->MockArticlePoACall('1235', $insight = $this->createArticlePoA('1235', 'insight', [], null, false));
        $this->MockArticlePoACall('1236', $shortReport = $this->createArticlePoA('1236', 'short-report', [], null, false));
        $this->MockArticlePoACall('1237', $researchArticle = $this->createArticlePoA('1237', 'research-article', [], null, false));

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 3,
                'items' => array_map([$this, 'normalize'], [$insight, $shortReport, $researchArticle]),
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_returns_fewer_recent_articles_with_first_subject_recommendations_if_there_are_already_some()
    {
        $client = static::createClient();

        $episode1Chapter1 = $this->createPodcastEpisodeChapter(1, [$this->createArticlePoA('1234'), $this->createArticlePoA('1235')]);
        $episode1 = $this->createPodcastEpisode(1, [$episode1Chapter1]);

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234', 'research-article', ['subject2', 'subject1'])]);
        $this->mockRelatedArticlesCall('1234', [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report')]);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodeCall($episode1);
        $this->mockSearchCall(0, [$this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1238', 'research-article'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['editorial', 'feature', 'insight', 'research-advance', 'research-article', 'research-communication', 'registered-report', 'replication-study', 'review-article', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);
        $this->MockArticlePoACall('1235', $insight = $this->createArticlePoA('1235', 'insight', [], null, false));
        $this->MockArticlePoACall('1236', $shortReport = $this->createArticlePoA('1236', 'short-report', [], null, false));
        $this->MockArticlePoACall('1238', $researchArticle = $this->createArticlePoA('1238', 'research-article', [], null, false));

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 3,
                'items' => array_map([$this, 'normalize'], [
                    $insight, // from related articles
                    $shortReport, // from related articles
                    $researchArticle, // from subject
                ]),
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_does_not_return_any_recent_articles_with_first_subject_recommendations_if_there_are_already_three()
    {
        $client = static::createClient();

        $episode1Chapter1 = $this->createPodcastEpisodeChapter(1, [$this->createArticlePoA('1234'), $this->createArticlePoA('1235')]);
        $episode1 = $this->createPodcastEpisode(1, [$episode1Chapter1]);

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234', 'research-article', ['subject2', 'subject1'])]);
        $this->mockRelatedArticlesCall('1234', [$this->createArticlePoA('1235', 'insight')]);
        $this->mockCollectionsCall(1, [$this->createCollection('1234')], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(1, [$episode1], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodeCall($episode1);
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1238', 'research-article'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['editorial', 'feature', 'insight', 'research-advance', 'research-article', 'research-communication', 'registered-report', 'replication-study', 'review-article', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);
        $this->MockArticlePoACall('1235', $insight = $this->createArticlePoA('1235', 'insight', [], null, false));

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 3,
                'items' => [
                    $this->normalize($insight),
                    $this->normalize($this->createCollection('1234')),
                    $this->normalize(new PodcastEpisodeChapterModel($episode1, $episode1->getChapters()[0])),
                ],
            ],
            $response->getContent()
        );
        $this->assertTrue($response->isCacheable());
    }

    /**
     * @test
     */
    public function it_does_not_recommend_itself()
    {
        $client = static::createClient();

        $this->mockArticleVersionsCall('1234', [$this->createArticlePoA('1234', 'research-article', ['subject2', 'subject1'])]);
        $this->mockRelatedArticlesCall('1234', []);
        $this->mockCollectionsCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockPodcastEpisodesCall(0, [], 1, 100, [Identifier::article('1234')]);
        $this->mockSearchCall(1, [$this->createArticlePoA('1234', 'research-article'), $this->createArticlePoA('1235', 'insight')], 1, 5, ['editorial', 'feature', 'insight', 'research-advance', 'research-article', 'research-communication', 'registered-report', 'replication-study', 'review-article', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);
        $this->MockArticlePoACall('1235', $insight = $this->createArticlePoA('1235', 'insight', [], null, false));

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 1,
                'items' => [
                    $this->normalize($insight),
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
        $this->mockSearchCall(0, [$this->createArticlePoA('1235', 'insight'), $this->createArticlePoA('1236', 'short-report'), $this->createArticlePoA('1238', 'research-article'), $this->createArticlePoA('1237', 'research-article')], 1, 5, ['editorial', 'feature', 'insight', 'research-advance', 'research-article', 'research-communication', 'registered-report', 'replication-study', 'review-article', 'scientific-correspondence', 'short-report', 'tools-resources'], ['subject2']);
        $this->MockArticlePoACall('1235', $insight = $this->createArticlePoA('1235', 'insight', [], null, false));
        $this->MockArticlePoACall('1236', $shortReport = $this->createArticlePoA('1236', 'short-report', [], null, false));
        $this->MockArticlePoACall('1237', $researchArticle = $this->createArticlePoA('1237', 'research-article', [], null, false));

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.elife.recommendations+json; version=2', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(
            [
                'total' => 8,
                'items' => [
                    $this->normalize($researchArticle),
                    $this->normalize($insight),
                    $this->normalize($shortReport),
                    $this->normalize($this->createCollection('1234')),
                    $this->normalize($this->createCollection('5678')),
                    $this->normalize(new PodcastEpisodeChapterModel($episode2, $episode1->getChapters()[0])),
                    $this->normalize(new PodcastEpisodeChapterModel($episode1, $episode1->getChapters()[0])),
                    $this->normalize(new PodcastEpisodeChapterModel($episode1, $episode1->getChapters()[2])),
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

        $client->request('GET', "/recommendations/article/1234?page=$page");
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['title' => "No page $page", 'type' => 'about:blank'], $response->getContent());
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
        $this->assertJsonStringEqualsJson(['title' => 'Not an article', 'type' => 'about:blank'], $response->getContent());
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

        $client->request('GET', '/recommendations/article/1234');
        $response = $client->getResponse();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $this->assertResponseIsValid($response);
        $this->assertJsonStringEqualsJson(['title' => 'article/1234 does not exist', 'type' => 'about:blank'], $response->getContent());
        $this->assertFalse($client->getResponse()->isCacheable());
    }
}
