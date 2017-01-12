<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\Relationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Sdk\Article;

final class MostRecent implements Rule
{
    /**
     * Resolve Relations.
     *
     * @note this particular example does not add any relations!
     */
    public function resolveRelations(RuleModel $input): array
    {
        return [];
    }

    public function upsert(Relationship $relationship)
    {
        // TODO: Implement upsert() method.
    }

    public function prune(RuleModel $input, array $relationships = null)
    {
        // TODO: Implement prune() method.
    }

    public function addRelations(RuleModel $model, array $list): array
    {
        return $list;
    }

    public function supports(): array
    {
        return [
            'correction',
            'editorial',
            'feature',
            'insight',
            'research-advance',
            'research-article',
            'research-exchange',
            'retraction',
            'registered-report',
            'replication-study',
            'short-report',
            'tools-resources',
        ];
    }
}
