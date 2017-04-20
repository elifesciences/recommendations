<?php

namespace eLife\Tests\Response;

use eLife\ApiSdk\Model\File;
use eLife\ApiSdk\Model\Image;
use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\Recommendations\Response\PodcastEpisode;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Builder;
use function GuzzleHttp\Promise\promise_for;

final class PodcastEpisodeTest extends PHPUnit_Framework_TestCase
{
    public function test_podcast_can_be_build_from_model()
    {
        $builder = Builder::for(PodcastEpisodeModel::class);
        /** @var PodcastEpisodeModel $podcast */
        $podcast = $builder
            ->create(PodcastEpisodeModel::class)
            ->withThumbnail(
                new Image('', 'https://iiif.elifesciences.org/thumbnail.jpg', new File('image/jpeg', 'https://iiif.elifesciences.org/thumbnail.jpg/full/full/0/default.jpg', 'thumbnail.jpg'), 140, 140, 50, 50)
            )
            ->__invoke();
        PodcastEpisode::fromModel($podcast);
    }

    public function test_podcast_episode_catches_exception_from_lack_of_thumbnail()
    {
        $builder = Builder::for(PodcastEpisodeModel::class);
        /** @var PodcastEpisodeModel $podcast */
        $podcast = $builder
            ->create(PodcastEpisodeModel::class)
            ->withThumbnail(
                new Image('', 'https://iiif.elifesciences.org/thumbnail.jpg', new File('image/jpeg', 'https://iiif.elifesciences.org/thumbnail.jpg/full/full/0/default.jpg', 'thumbnail.jpg'), 140, 140, 50, 50)
            )
            ->withBanner(
                promise_for(new Image('', 'https://iiif.elifesciences.org/banner.jpg', new File('image/jpeg', 'https://iiif.elifesciences.org/banner.jpg/full/full/0/default.jpg', 'banner.jpg'), 1800, 900, 50, 50))
            )
            ->__invoke();
        PodcastEpisode::fromModel($podcast);
    }
}
