<?php

namespace tests\eLife\Rule;

use eLife\ApiSdk\Model\ArticleVersion;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule\MostRecentWithSubject;
use eLife\Recommendations\RuleModel;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Serializer\ArticlePoANormalizerTest;
use test\eLife\ApiSdk\Serializer\ArticleVoRNormalizerTest;

class MostRecentWithSubjectTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getArticleData
     */
    public function test(ArticleVersion $article)
    {
        /** @var MostRecentWithSubject & PHPUnit_Framework_MockObject_MockBuilder $mock */
        $mock = $this->createPartialMock(MostRecentWithSubject::class, ['getFromSdk']);
        $mock->expects($this->exactly(1))
            ->method('getFromSdk')
            ->willReturn($article);

        $this->assertTrue(in_array($article->getType(), $mock->supports()));

        $relations = $mock->resolveRelations(new RuleModel('1', 'article'));
        foreach ($relations as $relation) {
            /* @var ManyToManyRelationship $relation */
            $this->assertInstanceOf(ManyToManyRelationship::class, $relation);
            $this->assertNotNull($relation->getOn());
            $this->assertNotNull($relation->getOn()->getId());
            $this->assertNotNull($relation->getOn()->getType());
            $this->assertNotNull($relation->getSubject());
            $this->assertNotNull($relation->getSubject()->getId());
            $this->assertNotNull($relation->getSubject()->getType());
            $this->assertTrue($relation->getSubject()->isSynthetic());
        }
    }

    public function getArticleData()
    {
        return array_merge((new ArticlePoANormalizerTest())->normalizeProvider(), (new ArticleVoRNormalizerTest())->normalizeProvider());
    }
}
