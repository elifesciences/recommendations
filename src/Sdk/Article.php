<?php

namespace eLife\Sdk;

use eLife\ApiSdk\Collection\ArraySequence;
use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\ArticleVersion;

class Article
{
    use Decorator;

    private $article;

    public function __construct(ArticleVersion $article)
    {
        $this->article = $article;
    }

//    public function getRelatedArticles() : Sequence
//    {
//        return new ArraySequence([]);
//    }

    protected function getSubject()
    {
        return $this->article;
    }
}
