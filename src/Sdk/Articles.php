<?php

namespace eLife\Sdk;

use eLife\ApiSdk\Client\Articles as ArticleBase;

class Articles
{
    use Decorator;

    private $articles;

    public function __construct(ArticleBase $articles)
    {
        $this->articles = $articles;
    }

    public function getArticleById(string $id) : Article
    {
        return new Article($this->articles->get($id));
    }

    protected function getSubject()
    {
        return $this->articles;
    }
}
