<?php

namespace test\eLife\Recommendations;

use Csa\Bundle\GuzzleBundle\Cache\StorageAdapterInterface;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiValidator\MessageValidator;
use eLife\Recommendations\AppKernel;
use function GuzzleHttp\json_encode;

abstract class ApplicationTestCase extends ApiTestCase
{
    /** @var AppKernel */
    private $app;

    /**
     * @before
     */
    final public function setUpApp()
    {
        $this->app = new AppKernel('test');
    }

    final protected function getApp() : AppKernel
    {
        return $this->app;
    }

    final protected function getApiSdk() : ApiSdk
    {
        return $this->app->getContainer()->get('elife.api_sdk');
    }

    final protected function getMockStorage() : StorageAdapterInterface
    {
        return $this->app->getContainer()->get('elife.guzzle_client.mock.storage');
    }

    final protected function getValidator() : MessageValidator
    {
        return $this->app->getContainer()->get('elife.json_message_validator');
    }

    final protected function assertJsonStringEqualsJson(array $expectedJson, string $actualJson, $message = '')
    {
        $this->assertJsonStringEqualsJsonString(json_encode($expectedJson), $actualJson, $message);
    }
}
