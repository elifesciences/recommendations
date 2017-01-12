<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use LogicException;

trait RepoRelations
{
    protected $rules = [];
    protected $repository;

    public function addRelations(RuleModel $model, array $list): array
    {
        return array_merge($list, $this->getRepository()->getAll($model));
    }

    protected function getRepository(): RuleModelRepository
    {
        if (!isset($this->repository) || !$this->repository instanceof RuleModelRepository) {
            throw new LogicException('You must inject repository property to use this trait.');
        }

        return $this->repository;
    }
}
