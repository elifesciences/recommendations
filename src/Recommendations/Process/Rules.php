<?php

namespace eLife\Recommendations\Process;

use Assert\Assertion;
use eLife\Recommendations\Rule;

class Rules
{
    public function __construct(array $rules)
    {
        Assertion::allIsInstanceOf(Rule::class, $rules);
    }
}
