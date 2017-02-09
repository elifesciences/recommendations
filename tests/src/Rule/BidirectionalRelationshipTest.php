<?php

namespace tests\eLife\Rule;

use eLife\ApiSdk\Collection\ArraySequence;
use eLife\ApiSdk\Model\Article;
use eLife\Recommendations\Rule\BidirectionalRelationship;
use eLife\Recommendations\RuleModel;
use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
use test\eLife\ApiSdk\Serializer\ArticlePoANormalizerTest;
use test\eLife\ApiSdk\Serializer\ArticleVoRNormalizerTest;

class BidirectionalRelationshipTest extends PHPUnit_Framework_TestCase
{
    use ValidRelationAssertion;

    /**
     * @dataProvider getArticleData
     */
    public function test(Article $article)
    {
        /** @var BidirectionalRelationship | \PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->createPartialMock(BidirectionalRelationship::class, ['getRelatedArticles']);
        $mock->setLogger(new NullLogger());
        $mock->expects($this->exactly(1))
            ->method('getRelatedArticles')
            ->willReturn(new ArraySequence([$article]));

        $this->assertTrue(in_array($article->getType(), $mock->supports()));

        $relations = $mock->resolveRelations(new RuleModel('2', 'blog-article'));
        foreach ($relations as $relation) {
            $this->assertValidRelation($relation);
        }
    }

    public function getArticleData()
    {
        return array_merge((new ArticlePoANormalizerTest())->normalizeProvider(), (new ArticleVoRNormalizerTest())->normalizeProvider());
    }
}
