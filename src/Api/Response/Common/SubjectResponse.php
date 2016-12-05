<?php

namespace eLife\Api\Response\Common;

use eLife\ApiSdk\Model\Subject;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class SubjectResponse
{
    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $id;

    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $name;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public static function fromModel(Subject $subject)
    {
        return new static(
            $subject->getId(),
            $subject->getName()
        );
    }
}
