<?php

namespace test\eLife\Recommendations;

use eLife\ApiSdk\ApiSdk;
use GuzzleHttp\HandlerStack;
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
        $this->app['api.uri'] = 'http://api.elifesciences.org/';
        $this->app->extend('elife.guzzle_client.handler', function (HandlerStack $stack) {
            $stack->push($this->getMock());

            return $stack;
        });
    }

    final protected function getApp() : Application
    {
        return $this->app;
    }

    final protected function getApiSdk() : ApiSdk
    {
        return $this->app['elife.api_sdk'];
    }

    final protected function assertJsonStringEqualsJson(array $expectedJson, string $actualJson, $message = '')
    {
        $this->assertJsonStringEqualsJsonString(json_encode($expectedJson), $actualJson, $message);
    }
}
