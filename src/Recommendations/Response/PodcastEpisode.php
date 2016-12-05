<?php

namespace eLife\Recommendations\Response;

use Assert\Assertion;
use DateTime;
use eLife\Api\Response\Common\Image;
use eLife\Api\Response\Common\Published;
use eLife\Api\Response\Common\SnippetFields;
use eLife\Api\Response\Common\SourcesResponse;
use eLife\Api\Response\Common\SubjectResponse;
use eLife\Api\Response\Common\Subjects;
use eLife\Api\Response\ImageResponse;
use eLife\Api\Response\Snippet;
use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\ApiSdk\Model\PodcastEpisodeSource;
use eLife\ApiSdk\Model\Subject;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class PodcastEpisode implements Snippet, Result
{
    use SnippetFields;
    use Subjects;
    use Published;
    use Image;

    /**
     * @Type("integer")
     * @Since(version="1")
     */
    public $number;

    /**
     * @Type("array<eLife\Api\Response\Common\SourcesResponse>")
     * @Since(version="1")
     */
    public $sources;

    /**
     * @Type("string")
     * @Since(version="1")
     * @Accessor(getter="getType")
     */
    public $type = 'podcast-episode';

    private function __construct(
        int $number,
        string $title,
        string $impactStatement,
        array $subjects,
        DateTime $published,
        ImageResponse $image,
        array $sources
    ) {
        Assertion::allIsInstanceOf(SubjectResponse::class, $subjects);
        Assertion::allIsInstanceOf(SourcesResponse::class, $sources);

        $this->sources = $sources;
        $this->number = $number;
        $this->title = $title;
        $this->impactStatement = $impactStatement;
        $this->subjects = $subjects;
        $this->published = $published;
        $this->image = $image;
    }

    public static function fromModel(PodcastEpisodeModel $episode)
    {
        return new static (
            $episode->getNumber(),
            $episode->getTitle(),
            $episode->getImpactStatement(),
            array_map(function (Subject $subject) {
                return SubjectResponse::fromModel($subject);
            }, $episode->getSubjects()),
            DateTime::createFromFormat('Y-m-d\TH:i:sP', $episode->getPublishedDate()->format('Y-m-d\TH:i:sP')),
            ImageResponse::fromModels($episode->getBanner(), $episode->getThumbnail()),
            array_map(function (PodcastEpisodeSource $source) {
                return SourcesResponse::fromModel($source);
            }, $episode->getSources())
        );
    }
}
