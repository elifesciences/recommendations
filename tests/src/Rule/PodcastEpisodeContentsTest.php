<?php

namespace tests\eLife\Rule;

use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule\PodcastEpisodeContents;
use eLife\Recommendations\RuleModel;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Serializer\PodcastEpisodeNormalizerTest;

class PodcastEpisodeContentsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getPodcastData
     */
    public function test(PodcastEpisodeModel $podcastEpisode)
    {
        /** @var PodcastEpisodeContents & PHPUnit_Framework_MockObject_MockBuilder $mock */
        $mock = $this->createPartialMock(PodcastEpisodeContents::class, ['getFromSdk']);
        $mock->expects($this->exactly(1))
            ->method('getFromSdk')
            ->willReturn($podcastEpisode);

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

    public function getPodcastData()
    {
        return (new PodcastEpisodeNormalizerTest())->normalizeProvider();
    }
}
