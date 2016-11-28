<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use eLife\Recommendations\Relationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Sdk\Article;

final class RetractionArticles implements Rule
{
    use ArticleTypeRule;

    private $sdk;

    public function __construct(
        ApiSdk $sdk
    ) {
        $this->sdk = $sdk;
    }

    protected function getArticle(string $id) : Article
    {
        return new Article($this->sdk->articles()->get($id));
    }

    protected function getArticleType() : string
    {
        return 'retraction';
    }

    /**
     * Upsert relations.
     *
     * Given an `input` and an `on` it will persist this relationship for
     * retrieval in recommendation results.
     */
    public function upsert(Relationship $relationship)
    {
        // This will be done in a trait
    }

    /**
     * Prune relations.
     *
     * Given an `input` this will go through the persistence layer and remove old
     * non-existent relation ships for this given `input`. Its possible some
     * logic will be shared with resolve relations, but this is up to each
     * implementation.
     */
    public function prune(RuleModel $input)
    {
        // Will  probably be done in a trait too.
    }

    /**
     * Add relations for model to list.
     *
     * This will be what is used when constructing the recommendations. Given a
     * model (id, type) we return an array of [type, id]'s that will be hydrated
     * into results by the application. The aim is for this function to be as
     * fast as possible given its executed at run-time.
     */
    public function addRelations(RuleModel $model, array $list) : array
    {
        // @todo.
        return [];
    }
}
