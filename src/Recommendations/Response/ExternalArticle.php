<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\Journal;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class ExternalArticle implements Article, Result
{
    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $type = 'external-article';

    /**
     * @Type("string")
     * @Since(version="1")
     * @SerializedName("articleTitle")
     */
    public $articleTitle;

    /**
     * @Type(Journal::class)
     */
    public $journal;

    /**
     * @Type("string")
     * @Since(version="1")
     * @SerializedName("authorLine")
     */
    public $authorLine;

    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $uri;
}
