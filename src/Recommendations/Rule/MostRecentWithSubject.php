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

    /**
     * Add relations for model to list.
     *
     * This will be what is used when constructing the recommendations. Given a model (id, type) we return an array
     * of [type, id]'s that will be hydrated into results by the application. The aim is for this function to be
     * as fast as possible given its executed at run-time.
     */
    public function addRelations(RuleModel $model, array $list): array
    {
        return $list;
    }

    protected function getRepository(): RuleModelRepository
    {
        return $this->repo;
    }

    /**
     * Returns item types that are supported by rule.
     */
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
