<?php

namespace eLife\Recommendations\Rule;

use DateTimeImmutable;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ExternalArticle as ExternalArticleModel;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;

class BidirectionalRelationship implements Rule
{
    use PersistRule;

    private $sdk;
    private $type;
    private $repo;
    public $logger;

    public function __construct(
        ApiSdk $sdk,
        string $type,
        RuleModelRepository $repo,
        LoggerInterface $logger
    ) {
        $this->sdk = $sdk;
        $this->type = $type;
        $this->repo = $repo;
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
        $this->logger->debug('Starting to resolve relations for article with id ' . $input->getId());
        $article = $this->getArticle($input->getId());
        if ($article instanceof ExternalArticleModel) {
            return [];
        }
        $related = $article->getRelatedArticles();
        $this->logger->debug('Found related articles (' . $related->count() . ')');
        $type = $this->type;
        $this->logger->debug('Starting to loop through articles');
        return $related
            ->filter(function (Article $article) use ($type) {
                if (method_exists($article, 'getId')) {
                    $this->logger->debug('Found related article id: ' . $article->getId() . ' and type: ' . $type);
                }
                if ($article instanceof ExternalArticleModel) {
//                    return $type === 'external-article';
                    return false;
                }

                return $article->getType() === $type;
            })
            ->map(function (Article $article) use ($input) {
                $this->logger->debug('Mapping to relation ' . $input->getId());
                return new ManyToManyRelationship($input, new RuleModel($article->getId(), $article->getType(), $article->getPublishedDate()));
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
        $type = $model->getType(); // Convert this into database name
        $id = $model->getId(); // Query that database with the type + ID
        // Get the results and make some new RuleModels
        $list[] = new RuleModel('12445', 'research-article', new DateTimeImmutable());

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
