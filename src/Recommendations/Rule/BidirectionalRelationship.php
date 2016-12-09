<?php

namespace eLife\Recommendations\Rule;

use DateTimeImmutable;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use eLife\Sdk\Article;

final class BidirectionalRelationship implements Rule
{
    use PersistRule;

    private $sdk;
    private $type;
    private $repo;

    public function __construct(
        ApiSdk $sdk,
        string $type,
        RuleModelRepository $repo
    ) {
        $this->sdk = $sdk;
        $this->type = $type;
        $this->repo = $repo;
    }

    protected function getArticle(string $id): Article
    {
        return new Article($this->sdk->articles()->get($id)->wait(true));
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
        $article = $this->getArticle($input->getId());
        $related = $article->getRelatedArticles();
        $type = $this->type;

        return $related
            ->filter(function (ArticleVersion $article) use ($type) {
                return $article->getType() === $type;
            })
            ->map(function (ArticleVersion $article) use ($input) {
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
