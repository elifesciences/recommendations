<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\ExternalArticle as ExternalArticleModel;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;

class BidirectionalRelationship implements Rule
{
    use PersistRule;
    use RepoRelations;

    private $sdk;
    private $type;
    private $repo;
    private $logger;

    public function __construct(
        ApiSdk $sdk,
        string $type,
        RuleModelRepository $repo,
        LoggerInterface $logger = null
    ) {
        $this->sdk = $sdk;
        $this->type = $type;
        $this->repo = $repo;
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function getArticle(string $id): Article
    {
        return $this->sdk->articles()->get($id)->wait(true);
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
        $this->logger->debug('Starting to resolve relations for article with id '.$input->getId());
        $article = $this->getArticle($input->getId());
        if ($article instanceof ExternalArticleModel) {
            $this->logger->warning('We cannot process external article at this time.');

            return [];
        }
        $related = $article->getRelatedArticles();
        $this->logger->debug('Found related articles ('.$related->count().')');
        $type = $this->type;
        $this->logger->debug('Starting to loop through articles');

        return $related
            ->filter(function ($item) {
                return $item instanceof ArticleVersion;
            })
            ->filter(function (Article $article) use ($type) {
                $this->logger->debug('Found related article id: '.$article->getId().' and type: '.$type);

                return $article->getType() === $type;
            })
            ->map(function (Article $article) use ($input) {
                $this->logger->debug('Mapping to relation '.$input->getId());

                return new ManyToManyRelationship($input, new RuleModel($article->getId(), $article->getType(), $article->getPublishedDate()));
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
