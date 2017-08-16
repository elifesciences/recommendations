<?php

namespace test\eLife\Recommendations;

use Silex\Application;
use function GuzzleHttp\json_encode;

abstract class ApplicationTestCase extends ApiTestCase
{
    private $app;

    /**
     * @before
     */
    final public function setUpApp()
    {
        $this->app = require __DIR__.'/../src/bootstrap.php';
        $this->app['elife.api_client'] = $this->getHttpClient();
    }

    final protected function getApp() : Application
    {
        return $this->app;
    }

    final protected function assertJsonStringEqualsJson(array $expectedJson, string $actualJson, $message = '')
    {
        $this->assertJsonStringEqualsJsonString(json_encode($expectedJson), $actualJson, $message);
    }
}
