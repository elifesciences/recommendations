<?php

namespace eLife\Api\Response;

use eLife\Api\Response\Common\NamedResponse;
use eLife\ApiSdk\Model\Person;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class SelectedCuratorResponse extends NamedResponse
{
    /**
     * @Type("boolean")
     * @Since(version="1")
     * @SerializedName("etAl")
     */
    public $etAl = false;

    public function __construct(string $id, array $name, ImageResponse $image, bool $etAl = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->image = $image;
        $this->etAl = $etAl;
    }

    public static function fromModel(Person $person, int $count = 1)
    {
        return new static(
            $person->getId(),
            [
                'preferred' => $person->getDetails()->getPreferredName(),
                'index' => $person->getDetails()->getIndexName(),
            ],
            ImageResponse::fromModels(null, $person->getImage()),
            $count > 1
        );
    }
}
