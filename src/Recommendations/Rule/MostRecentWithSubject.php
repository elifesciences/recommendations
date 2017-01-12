<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Collection;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\Subject;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use eLife\Sdk\Article;

class MostRecentWithSubject implements Rule
{
    use GetSdk;
    use PersistRule;

    private $sdk;
    private $repo;

    public function __construct(
        ApiSdk $sdk,
        RuleModelRepository $repo
    ) {
        $this->sdk = $sdk;
        $this->repo = $repo;
    }

    public function resolveRelations(RuleModel $input): array
    {
        /** @var ArticleVersion $model Added to stop IDE complaining @todo create hasSubjects interface. */
        $model = $this->getFromSdk($input->getType(), $input->getId());

        return $model
            ->getSubjects()
            ->map(function (Subject $subject) use ($input) {
                return new ManyToManyRelationship($input, new RuleModel($subject->getId(), 'subject', null, true));
            })
            ->toArray();
    }

    public function addRelations(RuleModel $model, array $list): array
    {
        return $list;
    }

    protected function getRepository(): RuleModelRepository
    {
        return $this->repo;
    }

    public function supports(): array
    {
        return [
            'collection',
            'podcast-episode',
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
