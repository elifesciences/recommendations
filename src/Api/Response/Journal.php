<?php

namespace eLife\Api\Response;

use eLife\ApiSdk\Model\Place;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

class Journal
{
    /**
     * @Type("array<string>")
     * @Since(version="1")
     */
    public $name;

    public function __construct(array $name)
    {
        $this->name = $name;
    }

    public static function fromModel(Place $model)
    {
        return new static(
            $model->getName()
        );
    }
}
