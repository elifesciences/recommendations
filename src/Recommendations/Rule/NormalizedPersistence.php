<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\Rule;
use eLife\Recommendations\Rule\Common\CompoundRule;
use eLife\Recommendations\Rule\Common\PersistRule;
use eLife\Recommendations\Rule\Common\RepoRelations;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;

class NormalizedPersistence implements CompoundRule
{
    use PersistRule;
    use RepoRelations;

    private $rules;
    private $order;

    public function __construct(RuleModelRepository $repository, RelatedArticlesOrder $order, Rule ...$rules)
    {
        $this->rules = $rules;
        $this->order = $order;
        $this->repository = $repository;
    }

    public function addRelations(RuleModel $model, array $list): array
    {
        return $this->order->filter(array_merge(
            $list,
            $this->getRepository()->getAll($model)
        ));
    }

    public function isSupported(RuleModel $model, Rule $rule)
    {
        return in_array($model->getType(), $rule->supports());
    }

    public function resolveRelations(RuleModel $model): array
    {
        $all = [];
        foreach ($this->rules as $rule) {
            if ($this->isSupported($model, $rule) === false) {
                continue;
            }
            $relations = $rule->resolveRelations($model);
            $all = array_merge($all, $relations);
        }

        return $all;
    }

    public function getRules() : array
    {
        return $this->rules;
    }

    public function supports(): array
    {
        $supports = [];
        foreach ($this->rules as $rule) {
            $supports = array_merge($supports, $rule->supports());
        }

        return array_unique($supports);
    }
}
