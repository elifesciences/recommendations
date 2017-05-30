<?php

namespace eLife\Recommendations\Response;

use eLife\ApiSdk\Model\PodcastEpisodeChapter as ApiSdkPodcastEpisodeChapter;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

class PodcastChapter
{
    /**
     * @Type("integer")
     * @Since(version="1")
     */
    private $number;

    /**
     * @Type("string")
     * @Since(version="1")
     */
    private $title;

    /**
     * @Type("string")
     * @Since(version="1")
     * @SerializedName("longTitle")
     */
    private $longTitle;

    /**
     * @Type("integer")
     * @Since(version="1")
     */
    private $time;

    /**
     * @Type("string")
     * @Since(version="1")
     * @SerializedName("impactStatement")
     */
    private $impactStatement;

    public function __construct(
        int $number,
        string $title,
        string $longTitle = null,
        int $time,
        string $impactStatement = null
    ) {
        $this->number = $number;
        $this->title = $title;
        $this->longTitle = $longTitle;
        $this->time = $time;
        $this->impactStatement = $impactStatement;
    }

    public static function fromModel(ApiSdkPodcastEpisodeChapter $chapter)
    {
        return new static(
            $chapter->getNumber(),
            $chapter->getTitle(),
            $chapter->getLongTitle(),
            $chapter->getTime(),
            $chapter->getImpactStatement()
        );
    }
}
