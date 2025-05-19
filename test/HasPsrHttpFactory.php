<?php

namespace test\eLife\Recommendations;

use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Nyholm\Psr7;

trait HasPsrHttpFactory
{
    private function getPsrHttpFactory() : PsrHttpFactory
    {
        $nyholmPsrFactory = new Psr7\Factory\Psr17Factory();
        return new PsrHttpFactory(
            $nyholmPsrFactory,
            $nyholmPsrFactory,
            $nyholmPsrFactory,
            $nyholmPsrFactory,
            $nyholmPsrFactory,
        );
    }
}
