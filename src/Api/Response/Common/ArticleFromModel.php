<?php

namespace eLife\Api\Response\Common;

use DateTime;
use eLife\Api\Response\ImageResponse;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\ArticleVoR;
use eLife\ApiSdk\Model\Subject;

trait ArticleFromModel
{
    public function __construct(
        string $id,
        string $title,
        string $type,
        string $impactStatement,
        string $titlePrefix,
        string $authorLine,
        DateTime $statusDate,
        int $volume,
        int $version,
        int $issue,
        string $elocationId,
        string $doi,
        string $pdf,
        array $subjects,
        ImageResponse $image
    ) {
        $this->title = $title;
        $this->titlePrefix = $titlePrefix;
        $this->authorLine = $authorLine;
        $this->id = $id;
        $this->type = $type;
        $this->impactStatement = $impactStatement;
        $this->statusDate = $statusDate;
        $this->volume = $volume;
        $this->version = $version;
        $this->issue = $issue;
        $this->elocationId = $elocationId;
        $this->doi = $doi;
        $this->pdf = $pdf;
        $this->subjects = $subjects;
        $this->image = $image;
    }

    public static function fromModel(ArticleVersion $article)
    {
        return new static(
            $article->getId(),
            $article->getTitle(),
            $article->getType(),
            $article instanceof ArticleVoR ? $article->getImpactStatement() : null,
            $article->getTitlePrefix(),
            $article->getAuthorLine(),
            $article->getStatusDate(),
            $article->getVolume(),
            $article->getVersion(),
            $article->getIssue(),
            $article->getElocationId(),
            $article->getDoi(),
            $article->getPdf(),
            array_map(function (Subject $subject) {
                return SubjectResponse::fromModel($subject);
            }, $article->getSubjects()),
            $article instanceof ArticleVoR ? ImageResponse::fromModels($article->getBanner(), $article->getThumbnail()) : null
        );
    }
}
