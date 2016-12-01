<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\Common\Image;
use eLife\Api\Response\Common\SnippetFields;
use eLife\Api\Response\Common\Subjects;
use eLife\Api\Response\Snippet;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class Collection implements Snippet, Result
{
    use SnippetFields;
    use Subjects;
    use Image;

    /**
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
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
}
