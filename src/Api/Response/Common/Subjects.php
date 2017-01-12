<?php

namespace eLife\Api\Response\Common;

use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

trait Subjects
{
    /**
     * @Type("array<eLife\Api\Response\Common\SubjectResponse>")
     * @Accessor(setter="setSubjects")
     * @Since(version="1")
     */
    public $subjects;

    public function setSubjects($subjects)
    {
        $this->subjects = empty($subjects) ? null : $subjects;
    }
}
