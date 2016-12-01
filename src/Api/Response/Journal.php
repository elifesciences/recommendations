<?php

namespace eLife\Api\Response;

use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

class Journal
{
    /**
     * @Type("array<string>")
     * @Since(version="1")
     */
    public $name;
}
