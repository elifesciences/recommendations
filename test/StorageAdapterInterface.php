<?php

namespace test\eLife\Recommendations;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface StorageAdapterInterface
{
    public function fetch(RequestInterface $request): ?ResponseInterface;

    public function save(RequestInterface $request, ResponseInterface $response): void;
}
