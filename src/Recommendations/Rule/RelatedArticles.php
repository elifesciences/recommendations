<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ExternalArticle as ExternalArticleModel;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\Rule\Common\MicroSdk;
use eLife\Recommendations\Rule\Common\PersistRule;
use eLife\Recommendations\Rule\Common\RepoRelations;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;
use Throwable;

class RelatedArticles implements Rule
{
    use PersistRule;
    use RepoRelations;
    use RuleModelLogger;

    private $sdk;
    private $logger;

    public function __construct(
        MicroSdk $sdk,
        RuleModelRepository $repository,
        RelatedArticlesOrder $order,
        LoggerInterface $logger = null
    ) {
        $this->sdk = $sdk;
        $this->repository = $repository;
        $this->order = $order;
        $this->logger = $logger;
    }

    // extract a RelatedArticleOrder class to test in isolation

    public function addRelations(RuleModel $model, array $list): array
    {
        return array_merge(
            $list,
            $this->order->filter($this->getRepository()->getAll($model))
        );
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function getArticle(string $id): Article
    {
        return $this->sdk->get('article', $id);
    }

    protected function getRelatedArticles(string $id): Sequence
    {
        return $this->sdk->getRelatedArticles($id);
    }

    /**
     * Resolve Relations.
     *
     * Given a model (type + id) from SQS, calculate which entities need
     * relations added for the specific domain rule.
     *
     * Return is an array of tuples containing an `input` and an `on` where
     * `input` * is the model to be added and `on` is the target node.
     * In plain english given an article related to other articles it would
     * return an array * where the first is every `input` and each related
     * article is the `output`.
     */
    public function resolveRelations(RuleModel $input): array
    {
        $this->debug($input, 'Looking for related articles');
        try {
            $related = $this->getRelatedArticles($input->getId());

            if ($related->count() === 0) {
                $this->debug($input, 'No related articles found');

                return [];
            }
        } catch (Throwable $e) {
            $this->error($input, 'Exception thrown getting related articles', [
                'exception' => $e,
            ]);

            return [];
        }
        $this->debug($input, sprintf('Found (%d) related article(s)', $related->count()));

        return $related
            ->filter(function ($item) use ($input) {
                $isArticle = $item instanceof Article;
                if (!$isArticle) {
                    $this->warning($input, sprintf('Found unknown article type: %s', get_class($item)), [
                        'model' => $item,
                    ]);
                }

                return $isArticle;
            })
            ->map(function (Article $article) use ($input) {
                $id = $article->getId();
                $type = $article->getType();
                if ($article instanceof ExternalArticleModel) {
                    $relationship = new ManyToManyRelationship($input, RuleModel::synthetic($input->getId().'-'.$article->getUri(), $type));
                } else {
                    $date = $article->getPublishedDate();
                    $relationship = new ManyToManyRelationship($input, new RuleModel($article->getId(), $type, $date));
                }
                $this->debug($input, sprintf('Found related article %s<%s>', $type, $id), [
                    'relationship' => $relationship,
                    'article' => $article,
                ]);

                return $relationship;
            })
            ->toArray();
    }

    /**
     * Returns item types that are supported by rule.
     */
    public function supports(): array
    {
        return [
            'correction',
            'editorial',
            'feature',
            'insight',
            'research-advance',
            'research-article',
            'retraction',
            'registered-report',
            'replication-study',
            'scientific-correspondence',
            'short-report',
            'tools-resources',
        ];
    }
}
