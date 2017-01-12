<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\Rule;

interface CompoundRule extends Rule
{
    public function getRules(): array;
}
