<?php

namespace eLife\Sdk;

use LogicException;

trait Decorator
{
    public function __call($name, $arguments)
    {
        if (method_exists($this->getSubject(), $name)) {
            return $this->getSubject()->{$name}(...$arguments);
        }
        throw new LogicException('Method does not exist.');
    }

    abstract protected function getSubject();
}
