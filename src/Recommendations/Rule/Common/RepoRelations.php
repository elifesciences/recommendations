<?php

namespace eLife\Recommendations\Rule\Common;

use BadMethodCallException;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use LogicException;

trait RepoRelations
{
    protected $repository;

    public function addRelations(RuleModel $model, array $list): array
    {
        throw new BadMethodCallException('No default implementation for addRelations()');
    }

    protected function getRepository(): RuleModelRepository
    {
        if (!isset($this->repository) || !$this->repository instanceof RuleModelRepository) {
            throw new LogicException('You must inject repository property to use this trait.');
        }

        return $this->repository;
    }
}
