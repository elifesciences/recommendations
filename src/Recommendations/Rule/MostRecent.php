<?php

namespace eLife\Recommendations\Rule;

use eLife\Recommendations\Relationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use eLife\Sdk\Article;
use Psr\Log\LoggerInterface;

final class MostRecent implements Rule
{
    private $repo;
    private $logger;

    public function __construct(RuleModelRepository $repo, LoggerInterface $logger)
    {
        $this->repo = $repo;
        $this->logger = $logger;
    }

    public function resolveRelations(RuleModel $input): array
    {
        // this particular example does not add any relations.
        return [

        ];
    }

    public function upsert(Relationship $relationship)
    {
        // this particular example does not add any relations.
        return;
    }

    public function prune(RuleModel $input, array $relationships = null)
    {
        // this particular example does not add any relations.
    }

    public function addRelations(RuleModel $model, array $list): array
    {
        foreach ($this->repo->getLatestArticle() as $item) {
            /* @var RuleModel $item */
            $intersection = array_filter($list, function (RuleModel $listItem) use ($item) {
                return $listItem->equalTo($item);
            });
            if (
                empty($intersection) &&
                $item->equalTo($model) === false &&
                in_array($item->getType(), $this->supports())
            ) {
                array_push($list, $item);

                return $list;
            }
        }
        $this->logger->error('Latest article could not be found', [
            'model' => $model,
        ]);

        return $list;
    }

    public function supports(): array
    {
        return [
            'research-advance',
            'research-article',
            'replication-study',
            'scientific-correspondence',
            'short-report',
            'tools-resources',
        ];
    }
}
