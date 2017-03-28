<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ArticleVersion;
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

class BidirectionalRelationship implements Rule
{
    use PersistRule;
    use RepoRelations;

    private $sdk;
    private $repo;
    private $logger;

    public function __construct(
        MicroSdk $sdk,
        RuleModelRepository $repo,
        LoggerInterface $logger = null
    ) {
        $this->sdk = $sdk;
        $this->repo = $repo;
        $this->logger = $logger;
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
     * Return is an array of tuples containing an input and an on where `input`
     * is the model to be added and `on` is the target node. In plain english
     * given a podcast containing articles it would return an array where the
     * podcast is every `input` and each article is the `output`.
     */
    public function resolveRelations(RuleModel $input): array
    {
        $this->logger->debug('Looking for articles related to Article<'.$input->getId().'>');
        try {
            $related = $this->getRelatedArticles($input->getId());

            if ($related->count() === 0) {
                return [];
            }
        } catch (Throwable $e) {
            $this->logger->error('Article<'.$input->getId().'> threw exception when requesting related articles', [
                'exception' => $e,
            ]);

            return [];
        }
        $this->logger->debug('Found related articles ('.$related->count().')');

        return $related
            ->filter(function ($item) {
                return $item instanceof Article;
            })
            ->map(function (Article $article) use ($input) {
                $type = $article instanceof ExternalArticleModel ? 'external-article' : $article->getType();
                $date = $article instanceof ArticleVersion ? $article->getPublishedDate() : null;
                // Link this podcast TO the related item.
                $this->logger->debug('Mapping to relation '.$input->getId());

                return new ManyToManyRelationship($input, new RuleModel($article->getId(), $type, $date));
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
            'research-exchange',
            'retraction',
            'registered-report',
            'replication-study',
            'short-report',
            'tools-resources',
        ];
    }
}
