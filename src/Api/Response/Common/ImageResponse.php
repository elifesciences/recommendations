<?php

namespace eLife\Api\Response;

use eLife\ApiSdk\Model\Image;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class ImageResponse
{
    /**
     * @Type(ImageBannerResponse::class)
     * @Since(version="1")
     */
    public $banner;

    /**
     * @Type(ImageThumbnailResponse::class)
     * @Since(version="1")
     */
    public $thumbnail;

    public function https()
    {
        $this->banner = $this->banner ? $this->banner->https() : null;
        $this->thumbnail = $this->thumbnail ? $this->thumbnail->https() : null;

        return $this;
    }

    private function __construct(ImageBannerResponse $banner = null, ImageThumbnailResponse $thumbnail = null)
    {
        $this->banner = $banner;
        $this->thumbnail = $thumbnail;
    }

    public static function fromModels(Image $banner = null, Image $thumbnail = null)
    {
        return new static(
            $banner ? ImageBannerResponse::fromModel($banner) : null,
            $thumbnail ? ImageThumbnailResponse::fromModel($thumbnail) : null
        );
    }
}
