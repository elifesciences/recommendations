<?php

namespace eLife\Tests\Response;

use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\Recommendations\Response\Collection;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Builder;
use test\eLife\ApiSdk\Model\CollectionTest as SdkCollectionTest;

final class CollectionTest extends PHPUnit_Framework_TestCase
{
    public function test_collection_can_be_build_from_model()
    {
        $builder = Builder::for(CollectionModel::class);
        $collection = $builder->create(CollectionModel::class)->__invoke();;
        Collection::fromModel($collection);
    }
}
