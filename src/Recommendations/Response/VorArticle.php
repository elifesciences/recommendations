<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\ArticleResponse\Article as ArticleFields;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class VorArticle implements Article, Response
{
    use ArticleFields;

    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $status = 'vor';
}
