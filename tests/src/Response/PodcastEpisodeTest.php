<?php

namespace eLife\Tests\Response;

use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\Recommendations\Response\PodcastEpisode;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Builder;

final class PodcastEpisodeTest extends PHPUnit_Framework_TestCase
{
    public function test_collection_can_be_build_from_model()
    {
        $builder = Builder::for(PodcastEpisodeModel::class);
        /** @var PodcastEpisodeModel $podcast */
        $podcast = $builder->create(PodcastEpisodeModel::class)->__invoke();
        PodcastEpisode::fromModel($podcast);
    }
}
