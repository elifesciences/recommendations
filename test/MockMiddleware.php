<?php

namespace test\eLife\Recommendations;

use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class MockMiddleware
{
    private StorageAdapterInterface $storage;

    public function __construct(StorageAdapterInterface $storage, string $mode = 'replay')
    {
        $this->storage = $storage;
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $response = $this->storage->fetch($request);
            if ($response !== null) {
                return new FulfilledPromise($response);
            }
            throw new RuntimeException('No mocked response found for: '.$request->getMethod().' '.$request->getUri());
        };
    }
}
