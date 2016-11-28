<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\Model\ArticleVersion;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\RuleModel;
use eLife\Sdk\Article;

trait ArticleTypeRule
{
    abstract protected function getArticle(string $id) : Article;

    abstract protected function getArticleType() : string;

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
    public function resolveRelations(RuleModel $input) : array
    {
        $article = $this->getArticle($input->getId());
        $related = $article->getRelatedArticles();

        return $related
            ->filter(function (ArticleVersion $article) {
                return $article->getType() === 'retraction';
            })
            ->map(function (ArticleVersion $article) use ($input) {
                return new ManyToManyRelationship($input, new RuleModel($article->getId(), $article->getType()));
            });
    }
}
