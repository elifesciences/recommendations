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

    /**
     * @Type("array")
     * @Since(version="1")
     */
    public $type;

    public function __construct(string $id, array $name, array $type, bool $etAl = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
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
            [
                'id' => $person->getType(),
                'label' => $person->getTypeLabel(),
            ],
            $count > 1
        );
    }
}
