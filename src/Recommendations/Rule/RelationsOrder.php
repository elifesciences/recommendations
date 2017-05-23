<?php

namespace eLife\Recommendations\Rule;

use DomainException;
use eLife\ApiSdk\Model\Article;
use eLife\Recommendations\RuleModel;

class RelationsOrder
{
    private $typeOrder = [
        'retraction',
        'correction',
        'external-article',
        'registered-report',
        'replication-study',
        'research-advance',
        'scientific-correspondence',
        'research-article',
        'tools-resources',
        'feature',
        'insight',
        'editorial',
        'short-report',
        'collection',
        'podcast-episode',
        'podcast-episode-chapter',
    ];

    public function filter($articles)
    {
        usort($articles, function ($former, $latter) {
            $comparison = $this->typeIndex($former) <=> $this->typeIndex($latter);
            if ($comparison === 0) {
                $comparison = (-1) * ($this->dateIndex($former) <=> $this->dateIndex($latter));
            }

            return $comparison;
        });

        return $articles;
    }

    private function typeIndex(RuleModel $article): int
    {
        $index = array_search($article->getType(), $this->typeOrder);
        if ($index === false) {
            throw new DomainException("Not supported type {$article->getType()}");
        }

        return $index;
    }

    private function dateIndex(RuleModel $article)
    {
        return $article->getPublished();
    }
}
