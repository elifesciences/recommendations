<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\Common\Image;
use eLife\Api\Response\Common\Published;
use eLife\Api\Response\Common\SnippetFields;
use eLife\Api\Response\Common\Subjects;
use eLife\Api\Response\Snippet;
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
     * @Type("array<eLife\Search\Api\Response\SourcesResponse>")
     * @Since(version="1")
     */
    public $sources;

    /**
     * @Type("string")
     * @Since(version="1")
     * @Accessor(getter="getType")
     */
    public $type = 'podcast-episode';
}
