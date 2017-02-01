<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\HasSubjects;
use eLife\ApiSdk\Model\Subject;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\Rule\Common\GetSdk;
use eLife\Recommendations\Rule\Common\PersistRule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;

class MostRecentWithSubject implements Rule
{
    use GetSdk;
    use PersistRule;

    private $sdk;
    private $repo;
    private $logger;

    public function __construct(
        ApiSdk $sdk,
        RuleModelRepository $repo,
        LoggerInterface $logger
    ) {
        $this->sdk = $sdk;
        $this->repo = $repo;
        $this->logger = $logger;
    }

    public function resolveRelations(RuleModel $input): array
    {
        /** @var HasSubjects $model Added to stop IDE complaining. */
        $model = $this->getFromSdk($input->getType(), $input->getId());
        if (!$model instanceof HasSubjects) {
            return [];
        }

        return $model
            ->getSubjects()
            ->map(function (Subject $subject) use ($input) {
                return new ManyToManyRelationship($input, new RuleModel($subject->getId(), 'subject', null, true));
            })
            ->toArray();
    }

    public function addRelations(RuleModel $model, array $list): array
    {
        /** @var ArticleVersion $article */
        $article = $this->getFromSdk($model->getType(), $model->getId());
        $subjects = $article->getSubjects();
        /** @var Subject $subject */
        $subject = $subjects[0] ?? null;
        // Nope out early.
        if (!$subject) {
            return $list;
        }
        foreach ($this->repo->getLatestArticleWithSubject($subject->getId()) as $item) {
            /* @var RuleModel $item */
            $intersection = array_filter($list, function (RuleModel $listItem) use ($item) {
                return $listItem->equalTo($item);
            });
            if (
                empty($intersection) &&
                $item->equalTo($model) === false
            ) {
                array_push($list, $item);

                return $list;
            }
        }
        $this->logger->error('Latest article with subject `'.$subject->getId().'` could not be found', [
            'model' => $model,
        ]);

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
