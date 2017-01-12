<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;

class NormalizedPersistence implements CompoundRule
{
    use PersistRule;

    private $rules;
    private $repository;

    public function __construct(RuleModelRepository $repository, Rule ...$rules)
    {
        $this->rules = $rules;
        $this->repository = $repository;
    }

    public function getRules() : array
    {
        return $this->rules;
    }

    public function isSupported(RuleModel $model, Rule $rule)
    {
        return in_array($model->getType(), $rule->supports());
    }

    /**
     * Resolve Relations.
     *
     * Given a model (type + id) from SQS, calculate which entities need relations added
     * for the specific domain rule.
     *
     * Return is an array of tuples containing an input and an on where `input` is the model to be
     * added and `on` is the target node. In plain english given a podcast containing articles it would
     * return an array where the podcast is every `input` and each article is the `output`.
     */
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

    /**
     * Add relations for model to list.
     *
     * This will be what is used when constructing the recommendations. Given a model (id, type) we return an array
     * of [type, id]'s that will be hydrated into results by the application. The aim is for this function to be
     * as fast as possible given its executed at run-time.
     */
    public function addRelations(RuleModel $model, array $list): array
    {
        return array_merge($list, $this->repository->getAll($model));
    }

    /**
     * Returns item types that are supported by rule.
     */
    public function supports(): array
    {
        $supports = [];
        foreach ($this->rules as $rule) {
            $supports = array_merge($supports, $rule->supports());
        }

        return array_unique($supports);
    }

    protected function getRepository(): RuleModelRepository
    {
        return $this->repository;
    }
}
