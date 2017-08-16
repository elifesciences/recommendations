<?php

namespace test\eLife\Recommendations;

use PHPUnit\Framework\TestCase;
use Silex\Application;

abstract class ApplicationTestCase extends TestCase
{
    private $app;

    /**
     * @before
     */
    final public function setUpApp()
    {
        $this->app = require __DIR__.'/../src/bootstrap.php';
    }

    final protected function getApp() : Application
    {
        return $this->app;
    }
}
