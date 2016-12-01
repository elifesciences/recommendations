<?php

namespace eLife\Recommendations;

use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class RecommendationsResponse
{
    /**
     * @Type("array<eLife\Recommendations\Result>")
     * @Since(version="1")
     */
    public $items = [];

    /**
     * @Type("integer");
     * @Since(version="1")
     */
    public $total;
}
