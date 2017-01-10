<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\Journal;
use eLife\ApiSdk\Model\ExternalArticle as ExternalArticleModel;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function __construct(string $articleTitle, string $authorLine, string $uri, Journal $journal)
    {
        $this->articleTitle = $articleTitle;
        $this->authorLine = $authorLine;
        $this->uri = $uri;
        $this->journal = $journal;
    }

    public static function fromModel(ExternalArticleModel $externalArticle)
    {
        return new static(
            $externalArticle->getArticleTitle(),
            $externalArticle->getAuthorLine(),
            $externalArticle->getUri(),
            Journal::fromModel($externalArticle->getJournal())
        );
    }
}
