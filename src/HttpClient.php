<?php

namespace eLife\Recommendations;

use eLife\ApiClient\HttpClient as ApiHttpClient;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

final class HttpClient implements ApiHttpClient
{
    private $httpClient;
    private $details = [];

    public function __construct(ApiHttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function send(RequestInterface $request) : PromiseInterface
    {
        $this->record($request);
        return $this->httpClient->send($request);
    }

    private function record(RequestInterface $request) : void
    {
        $this->details[$request->getHeaderLine('Accept')][] = implode('?', array_filter([
            $request->getUri()->getPath(),
            $request->getUri()->getQuery(),
        ]));
    }

    public function getDetails() : array
    {
        return $this->details;
    }

    public function count() : int
    {
        return array_reduce($this->details, function ($carry, $detail) {
            return $carry + count($detail);
        }, 0);
    }
}
