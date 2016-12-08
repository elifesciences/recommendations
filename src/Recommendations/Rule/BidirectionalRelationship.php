<?php

namespace eLife\Recommendations\Rule;

use DateTimeImmutable;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\Recommendations\Relationship;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Sdk\Article;

final class BidirectionalRelationship implements Rule
{
    private $sdk;
    private $type;

    public function __construct(
        ApiSdk $sdk,
        string $type
    ) {
        $this->sdk = $sdk;
        $this->type = $type;
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
     * Upsert relations.
     *
     * Given an `input` and an `on` it will persist this relationship for retrieval
     * in recommendation results.
     */
    public function upsert(Relationship $relationship)
    {
        // Add this
        $subject = $relationship->getSubject();
        // To this.
        $on = $relationship->getOn();
        // So when I ask for...
        $result = $this->addRelations($on, []);
        // I get...
        $result = [$subject/* ... */];
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

    /**
     * Prune relations.
     *
     * Given an `input` this will go through the persistence layer and remove old non-existent relation ships
     * for this given `input`. Its possible some logic will be shared with resolve relations, but this is up
     * to each implementation.
     *
     * Optionally gets passed the relationships returned from resolve relationships
     */
    public function prune(RuleModel $input, array $relationships = null)
    {
        // TODO: Implement prune() method.
    }
}
