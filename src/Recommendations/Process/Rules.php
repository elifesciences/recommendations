<?php
/**
 * The one Rule to ring them all.
 *
 * This class will take in a set of rules, run through them and return a
 * list of RuleModels.
 *
 * The result will be passed to "Hydration".
 */

namespace eLife\Recommendations\Process;

use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;

final class Rules
{
    private $rules;

    public function __construct(Rule ...$rules)
    {
        $this->rules = $rules;
    }

    public function isSupported(RuleModel $model, Rule $rule)
    {
        return in_array($model->getType(), $rule->supports());
    }

    public function import(RuleModel $model, bool $upsert = true, bool $prune = false): array
    {
        assert($upsert === true || $prune === false, 'You must upsert in order to prune.');
        $all = [];
        foreach ($this->rules as $rule) {
            if ($this->isSupported($model, $rule) === false) {
                continue;
            }
            $relations = $rule->resolveRelations($model);
            if ($upsert) {
                array_map([$rule, 'upsert'], $relations);
            }
            if ($prune) {
                $rule->prune($model, $relations);
            }
            $all = array_merge($all, $relations);
        }

        return $all;
    }

    public function getRecommendations(RuleModel $model)
    {
        $next = [];
        foreach ($this->rules as $rule) {
            $prev = $next;
            $next = $rule->addRelations($model, $prev);
        }

        return $next;
    }
}
