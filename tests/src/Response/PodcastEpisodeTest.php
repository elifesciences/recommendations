<?php

namespace eLife\Tests\Response;

use eLife\ApiSdk\Model\Image;
use eLife\ApiSdk\Model\ImageSize;
use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\Recommendations\Response\PodcastEpisode;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Builder;

final class PodcastEpisodeTest extends PHPUnit_Framework_TestCase
{
    public function test_podcast_can_be_build_from_model()
    {
        $builder = Builder::for(PodcastEpisodeModel::class);
        /** @var PodcastEpisodeModel $podcast */
        $podcast = $builder
            ->create(PodcastEpisodeModel::class)
            ->withThumbnail(
                new Image('alt', [
                    new ImageSize('16:9', [
                        250 => 'https://placehold.it/250x140',
                        500 => 'https://placehold.it/500x280',
                    ]),
                    new ImageSize('1:1', [
                        70 => 'https://placehold.it/70x70',
                        140 => 'https://placehold.it/140x140',
                    ]),
                ])
            )
            ->__invoke();
        PodcastEpisode::fromModel($podcast);
    }
}
