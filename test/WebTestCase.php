<?php

namespace test\eLife\Recommendations;

use Symfony\Component\HttpKernel\HttpKernelBrowser;

abstract class WebTestCase extends ApplicationTestCase
{
    final protected function createClient() : HttpKernelBrowser
    {
        return new HttpKernelBrowser($this->getApp());
    }
}
