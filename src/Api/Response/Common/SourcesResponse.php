<?php

namespace eLife\Api\Response\Common;

use eLife\ApiSdk\Model\PodcastEpisodeSource;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;

final class SourcesResponse
{
    /**
     * @Type("string")
     * @SerializedName("mediaType")
     */
    public $mediaType;

    /**
     * @Type("string")
     */
    public $uri;

    public function __construct(string $mediaType, string $uri)
    {
        $this->mediaType = $mediaType;
        $this->uri = $uri;
    }

    public static function fromModel(PodcastEpisodeSource $source)
    {
        return new static($source->getMediaType(), $source->getUri());
    }
}
