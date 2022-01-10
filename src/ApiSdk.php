<?php

namespace eLife\Recommendations;

use eLife\ApiClient\HttpClient;
use eLife\ApiSdk\ApiSdk as eLifeApiSdk;

final class ApiSdk
{
    private $sdk;
    private $callCount = 0;

    public function __construct(HttpClient $httpClient)
    {
        $this->sdk = new eLifeApiSdk($httpClient);
    }

    public function __call($name, $arguments)
    {
        $this->callCount++;
        return call_user_func_array([$this->sdk, $name], $arguments);
    }

    public function callCount()
    {
        return $this->callCount;
    }
}
