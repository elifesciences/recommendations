<?php

namespace eLife\Recommendations;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse extends JsonResponse
{
    public function __construct(array $data = [], $status = self::HTTP_OK, $headers = array())
    {
        parent::__construct($data, $status, $headers);

        $this->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=300, stale-if-error=86400');
        $this->headers->set('Vary', 'Accept', false);
    }
}
