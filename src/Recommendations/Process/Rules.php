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
use LogicException;

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

    public function import(RuleModel $model, bool $upsert = true, bool $prune = false)
    {
        foreach ($this->rules as $rule) {
            if ($this->isSupported($model, $rule) === false) {
                continue;
            }
            $relations = $rule->resolveRelations($model);
            if ($upsert) {
                foreach ($relations as $relation) {
                    $rule->upsert($relation);
                }
                if ($prune) {
                    $rule->prune($model, $relations);
                }
            } elseif ($prune) {
                throw new LogicException('You must upsert first in order to prune.');
            } else {
                return $relations;
            }
        }
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
