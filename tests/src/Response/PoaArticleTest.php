<?php

namespace eLife\Tests\Response;

use eLife\ApiSdk\Model\ArticlePoA;
use eLife\Recommendations\Response\PoaArticle;
use PHPUnit_Framework_TestCase;
use test\eLife\ApiSdk\Builder;

final class PoaArticleTest extends PHPUnit_Framework_TestCase
{
    public function test_article_can_be_build_from_model()
    {
        $builder = Builder::for(ArticlePoA::class);
        /** @var ArticlePoA $PoaArticle */
        $PoaArticle = $builder->create(ArticlePoA::class)->__invoke();
        PoaArticle::fromModel($PoaArticle);
    }
}
