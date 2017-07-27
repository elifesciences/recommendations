<?php

namespace eLife\Tests\Response;

use DateTimeImmutable;
use eLife\ApiSdk\Collection\EmptySequence;
use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\ApiSdk\Model\File;
use eLife\ApiSdk\Model\Image;
use eLife\Recommendations\Response\Collection;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Builder;
use tests\eLife\RamlRequirement;

final class CollectionTest extends PHPUnit_Framework_TestCase
{
    use RamlRequirement;

    public function test_collection_can_be_build_from_model()
    {
        $builder = Builder::for(CollectionModel::class);
        /** @var CollectionModel $model */
        $model = $builder
            ->create(CollectionModel::class)
            ->withImpactStatement('Tropical disease impact statement')
            ->__invoke();

        Collection::fromModel($model);
    }

    public function test_collection_can_be_build_from_full_model()
    {
        $builder = Builder::for(CollectionModel::class);
        /** @var CollectionModel $model */
        $model = $builder
            ->create(CollectionModel::class)
            ->withImpactStatement('Tropical disease impact statement')
            ->withPublishedDate($publishedDate = new DateTimeImmutable())
            ->withPromiseOfBanner(
                new Image('', 'https://iiif.elifesciences.org/banner.jpg', new EmptySequence(), new File('image/jpeg', 'https://iiif.elifesciences.org/banner.jpg/full/full/0/default.jpg', 'banner.jpg'), 1800, 900, 50, 50)
            )
            ->withThumbnail(
                new Image('', 'https://iiif.elifesciences.org/thumbnail.jpg', new EmptySequence(), new File('image/jpeg', 'https://iiif.elifesciences.org/thumbnail.jpg/full/full/0/default.jpg', 'thumbnail.jpg'), 140, 140, 50, 50)
            )
            ->__invoke();

        Collection::fromModel($model);
    }
}
