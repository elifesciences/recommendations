<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\ArticleResponse\Article as ArticleFields;
use eLife\Api\Response\Common\ArticleFromModel;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class VorArticle implements Article, Result
{
    use ArticleFields;
    use ArticleFromModel;

    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $status = 'vor';
}
