<?php

namespace tests\eLife\Rule;

use eLife\ApiSdk\Collection\ArraySequence;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ExternalArticle;
use eLife\Recommendations\Rule\BidirectionalRelationship;
use eLife\Recommendations\RuleModel;
use Psr\Log\NullLogger;
use test\eLife\ApiSdk\Serializer\ArticlePoANormalizerTest;
use test\eLife\ApiSdk\Serializer\ArticleVoRNormalizerTest;

class BidirectionalRelationshipTest extends BaseRuleTest
{
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

        $relations = $mock->resolveRelations(new RuleModel('17044', 'research-article'));
        foreach ($relations as $relation) {
            $this->assertValidRelation($relation);
        }
    }

    public function getArticleData()
    {
        return array_merge(
            (new ArticlePoANormalizerTest())->normalizeProvider(),
            (new ArticleVoRNormalizerTest())->normalizeProvider(),
            [
                'external' => [new ExternalArticle(
                    'Discovery and Preclinical Validation of Drug Indications Using Compendia of Public Gene Expression Data',
                    'Science Translational Medicine',
                    'M Sirota at al',
                    'https =>//doi.org/10.1126/scitranslmed.3001318'
                )],
            ]
        );
    }
}
