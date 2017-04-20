<?php

namespace eLife\Api\Response;

use eLife\ApiSdk\Model\Image;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class ImageResponse
{
    /**
     * @Type(IiifImageResponse::class)
     * @Since(version="1")
     */
    public $banner;

    /**
     * @Type(IiifImageResponse::class)
     * @Since(version="1")
     */
    public $thumbnail;

    public function https()
    {
        $this->banner = $this->banner ? $this->banner->https() : null;
        $this->thumbnail = $this->thumbnail ? $this->thumbnail->https() : null;

        return $this;
    }

    private function __construct(IiifImageResponse $banner = null, IiifImageResponse $thumbnail = null)
    {
        $this->banner = $banner;
        $this->thumbnail = $thumbnail;
    }

    public static function fromModels(Image $banner = null, Image $thumbnail = null)
    {
        if ($banner === null && $thumbnail === null) {
            return null;
        }

        return new static(
            $banner ? IiifImageResponse::fromModel($banner) : null,
            $thumbnail ? IiifImageResponse::fromModel($thumbnail) : null
        );
    }
}
