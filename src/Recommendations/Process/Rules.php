<?php
/**
 * Rule processing.
 *
 * This class will take in a set of rules, run through them and return a
 * list of RuleModels.
 *
 * The result will be passed to "Hydration".
 */

namespace eLife\Recommendations\Process;

use Assert\Assertion;
use eLife\Recommendations\Rule;

final class Rules
{
    public function __construct(array $rules)
    {
        Assertion::allIsInstanceOf(Rule::class, $rules);
    }
}
