<?php

namespace eLife\Recommendations\Response;

use DateTimeImmutable;
use eLife\Api\Response\Common\Image;
use eLife\Api\Response\Common\Published;
use eLife\Api\Response\Common\SnippetFields;
use eLife\Api\Response\Common\SubjectResponse;
use eLife\Api\Response\Common\Subjects;
use eLife\Api\Response\ImageResponse;
use eLife\Api\Response\SelectedCuratorResponse;
use eLife\Api\Response\Snippet;
use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\ApiSdk\Model\Subject;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class Collection implements Snippet, Result
{
    use SnippetFields;
    use Subjects;
    use Image;
    use Published;

    /**
     * @Type("DateTimeImmutable<'Y-m-d\TH:i:s\Z'>")
     * @Since(version="1")
     */
    public $updated;

    /**
     * @Type(SelectedCuratorResponse::class)
     * @Since(version="1")
     * @SerializedName("selectedCurator")
     */
    public $selectedCurator;

    /**
     * @Type("string")
     * @Since(version="1")
     * @Accessor(getter="getType")
     */
    public $type = 'collection';

    private function __construct(
        string $id,
        string $title,
        string $impactStatement = null,
        DateTimeImmutable $updated = null,
        DateTimeImmutable $published,
        ImageResponse $image,
        $selectedCurator,
        array $subjects
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->impactStatement = $impactStatement;
        $this->updated = $updated;
        $this->published = $published;
        $this->image = $image;
        $this->selectedCurator = $selectedCurator;
        $this->subjects = $subjects;
    }

    public static function fromModel(CollectionModel $collection)
    {
        return new static(
            $collection->getId(),
            $collection->getTitle(),
            $collection->getImpactStatement(),
            $collection->getUpdatedDate(),
            $collection->getPublishedDate(),
            ImageResponse::fromModels($collection->getBanner(), $collection->getThumbnail()),
            SelectedCuratorResponse::fromModel($collection->getSelectedCurator(), $collection->getCurators()->count()),
            $collection->getSubjects()->map(function (Subject $subject) {
                SubjectResponse::fromModel($subject);
            })->toArray()
        );
    }
}
