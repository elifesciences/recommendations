<?php

namespace eLife\Tests\Response;

use DateTimeImmutable;
use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\ApiSdk\Model\Image;
use eLife\ApiSdk\Model\ImageSize;
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
                $image = new Image('alt', [new ImageSize('2:1', [
                    900 => 'https://placehold.it/900x450',
                    1800 => 'https://placehold.it/900x450',
                ])])
            )
            ->withThumbnail(
                $image = new Image('', [
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

        Collection::fromModel($model);
    }
}
