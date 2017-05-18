<?php

namespace tests\eLife\Web;

use DateTimeImmutable;

/**
 * @group web
 */
class ArticleRecommendationsTest extends WebTestCase
{
    public function setUp()
    {
        parent::setUp();

        $a1 = $this->addArticle('001', 'research-article', [
            ['id' => 'cell-biology', 'name' => 'Cell Biology'],
            ['id' => 'immunology', 'name' => 'Immunology'],
        ], (new DateTimeImmutable())->setDate(2017, 1, 1));

        $a2 = $this->addArticle('002', 'research-article', [
            ['id' => 'immunology', 'name' => 'Immunology'],
            ['id' => 'cell-biology', 'name' => 'Cell Biology'],
        ], (new DateTimeImmutable())->setDate(2017, 1, 2));

        $a3 = $this->addArticle('003', 'correction', [], (new DateTimeImmutable())->setDate(2017, 1, 3));

        $a4 = $this->addArticle('004', 'insight', [
            ['id' => 'immunology', 'name' => 'Immunology'],
        ], (new DateTimeImmutable())->setDate(2017, 1, 3));

        $a5 = $this->addArticle('005', 'research-article', [
            ['id' => 'ecology', 'name' => 'Ecology'],
        ], (new DateTimeImmutable())->setDate(2017, 1, 4));

        $a6 = $this->addArticle('006', 'research-article', [
            ['id' => 'cancer-biology', 'name' => 'Cancer Biology'],
        ], (new DateTimeImmutable())->setDate(2017, 1, 5));

        $a7 = $this->addArticle('007', 'feature', [], (new DateTimeImmutable())->setDate(2017, 1, 6));

        $a8 = $this->addArticle('008', 'registered-report', [], (new DateTimeImmutable())->setDate(2017, 1, 7));

        $this->addExternalArticle('008-0');

        $this->relateArticlesByIds('002', ['003', '004']);
        $this->relateArticlesByIds('003', ['002']);
        $this->relateArticlesByIds('004', ['002', '006']);
        $this->relateArticlesByIds('006', ['004']);
        $this->relateArticlesByIds('008', ['008-0']);

        $this->getRulesProcess()->import($a1['model']);
        $this->getRulesProcess()->import($a2['model']);
        $this->getRulesProcess()->import($a3['model']);
        $this->getRulesProcess()->import($a4['model']);
        $this->getRulesProcess()->import($a5['model']);
        $this->getRulesProcess()->import($a6['model']);
        $this->getRulesProcess()->import($a7['model']);
        $this->getRulesProcess()->import($a8['model']);

        $this->addCollection(
            'col1',
            (new DateTimeImmutable())->setDate(2017, 1, 1),
            (new DateTimeImmutable())->setDate(2017, 1, 3),
            [$a5['article'], $a6['article']]
        );

        $this->addCollection(
            'col2',
            (new DateTimeImmutable())->setDate(2017, 1, 2),
            null,
            [$a5['article']]
        );

        $this->addPodcastEpisode(
            1,
            [
                ['number' => 1, 'content' => [$a5['article']]],
            ],
            (new DateTimeImmutable())->setDate(2017, 1, 1)
        );

        $this->addPodcastEpisode(
            2,
            [
                ['number' => 1, 'content' => [$a6['article']]],
                ['number' => 2, 'content' => [$a5['article']]],
            ],
            (new DateTimeImmutable())->setDate(2017, 1, 2)
        );
    }

    public function test_for_an_article_that_has_a_subject_but_no_relations()
    {
        $this->newClient();
        $this->jsonRequest('GET', '/recommendations/article/001');
        $json = $this->getJsonResponse();

        $this->assertEquals(2, $json->total);
        $this->assertEquals('006', $json->items[0]->id);
        $this->assertEquals('002', $json->items[1]->id);

        $this->assertTrue(true);
    }

    public function test_for_an_article_that_has_neither_a_subject_nor_relations()
    {
        $this->newClient();
        $this->jsonRequest('GET', '/recommendations/article/007');
        $json = $this->getJsonResponse();

        $this->assertEquals(1, $json->total);
        $this->assertEquals('006', $json->items[0]->id);

        $this->assertTrue(true);
    }

    public function test_for_a_research_article_the_has_been_corrected_and_has_an_insight()
    {
        $this->newClient();
        $this->jsonRequest('GET', '/recommendations/article/002');
        $json = $this->getJsonResponse();

        $this->assertEquals(4, $json->total);

        if (
            !in_array('003', [$json->items[0]->id, $json->items[1]->id])
        ) {
            $this->fail('Research article 003 was not found');
        }

        if (
            !in_array('004', [$json->items[0]->id, $json->items[1]->id])
        ) {
            $this->fail('Research article 004 was not found');
        }

//        $this->assertEquals('003', $json->items[0]->id);
//        $this->assertEquals('research-article', $json->items[0]->type);
//        $this->assertEquals('004', $json->items[1]->id);
//        $this->assertEquals('insight', $json->items[1]->type);

        $this->assertEquals('006', $json->items[2]->id);
        $this->assertEquals('research-article', $json->items[2]->type);

        $this->assertEquals('001', $json->items[3]->id);
        $this->assertEquals('research-article', $json->items[3]->type);

        $this->assertTrue(true);
    }
    public function test_for_an_article_that_is_in_podcast_episodes_and_a_collection_and_has_a_relation()
    {
        $this->newClient();
        $this->jsonRequest('GET', '/recommendations/article/006');
        $json = $this->getJsonResponse();

        $this->assertEquals(4, $json->total);

        $this->assertEquals('004', $json->items[0]->id);
        $this->assertEquals('insight', $json->items[0]->type);

        $this->assertEquals('col1', $json->items[1]->id);
        $this->assertEquals('collection', $json->items[1]->type);

        $this->assertEquals('podcast-episode-chapter', $json->items[2]->type);
        $this->assertEquals('1', $json->items[2]->chapter->number);
        $this->assertEquals('2', $json->items[2]->episode->number);

        $this->assertEquals('005', $json->items[3]->id);
        $this->assertEquals('research-article', $json->items[3]->type);

        $this->assertTrue(true);
    }

    public function test_for_an_article_that_has_an_external_relation()
    {
        $this->newClient();
        $this->jsonRequest('GET', '/recommendations/article/008');
        $json = $this->getJsonResponse();

        $this->assertEquals(2, $json->total);

        $this->assertEquals('external-article', $json->items[0]->type);
        $this->assertEquals('http://www.example.com/008-0', $json->items[0]->uri);

        $this->assertEquals('006', $json->items[1]->id);
        $this->assertEquals('research-article', $json->items[1]->type);
    }
}
