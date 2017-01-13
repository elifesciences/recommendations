<?php

namespace tests\eLife\Rule;

use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule\BidirectionalRelationship;
use eLife\Recommendations\RuleModel;
use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
use test\eLife\ApiSdk\Serializer\ArticlePoANormalizerTest;
use test\eLife\ApiSdk\Serializer\ArticleVoRNormalizerTest;
use eLife\ApiSdk\Model\Article;

class BidirectionalRelationshipTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getArticleData
     */
    public function test(Article $article)
    {
        /** @var BidirectionalRelationship | \PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->createPartialMock(BidirectionalRelationship::class, ['getArticle']);
        $mock->setLogger(new NullLogger());
        $mock->expects($this->exactly(1))
            ->method('getArticle')
            ->willReturn($article);

        $this->assertTrue(in_array($article->getType(), $mock->supports()));

        $relations = $mock->resolveRelations(new RuleModel('1', 'article'));
        foreach ($relations as $relation) {
            /* @var ManyToManyRelationship $relation */
            $this->assertInstanceOf(ManyToManyRelationship::class, $relation);
            $this->assertNotNull($relation->getOn());
            $this->assertNotNull($relation->getOn()->getId());
            $this->assertNotNull($relation->getOn()->getType());
            $this->assertNotNull($relation->getOn()->getPublished());
            $this->assertNotNull($relation->getSubject());
            $this->assertNotNull($relation->getSubject()->getId());
            $this->assertNotNull($relation->getSubject()->getType());
        }
    }

    public function getArticleData()
    {
        return array_merge((new ArticlePoANormalizerTest())->normalizeProvider(), (new ArticleVoRNormalizerTest())->normalizeProvider());
    }
}