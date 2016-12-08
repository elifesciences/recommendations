<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\Relationship;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use LogicException;

trait PersistRule
{
    abstract protected function getRepository() : RuleModelRepository;

    public function upsert(Relationship $relationship)
    {
        $repo = $this->getRepository();

        switch (true) {
            case $relationship instanceof ManyToManyRelationship:
                $repo->addRelation($relationship);
                break;

            default:
                throw new LogicException('There is nothing set up to handle '.get_class($relationship).' yet.');
        }
    }

    public function prune(RuleModel $input, array $relationships = null)
    {
        // TODO: Implement prune() method.
    }
}
