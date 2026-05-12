<?php

namespace test\eLife\Recommendations;

use GuzzleHttp\HandlerStack;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

abstract class WebTestCase extends ApplicationTestCase
{
    final protected function createClient(): KernelBrowser
    {
        static::bootKernel();
        static::getContainer()->get(HandlerStack::class)->push($this->getMock());

        return new KernelBrowser(static::$kernel);
    }
}
