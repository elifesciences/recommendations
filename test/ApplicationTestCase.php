<?php

namespace test\eLife\Recommendations;

use eLife\ApiSdk\ApiSdk;
use function GuzzleHttp\json_encode;

abstract class ApplicationTestCase extends ApiTestCase
{
    final protected function getApiSdk(): ApiSdk
    {
        return static::getContainer()->get(ApiSdk::class);
    }

    final protected function assertJsonStringEqualsJson(array $expectedJson, string $actualJson, $message = '')
    {
        $this->assertJsonStringEqualsJsonString(json_encode($expectedJson), $actualJson, $message);
    }
}
