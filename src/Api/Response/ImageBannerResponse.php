<?php

namespace eLife\Api\Response;

use Assert\Assertion;
use eLife\ApiSdk\Model\Image;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class ImageBannerResponse implements ImageVariant
{
    /**
     * @Type("string")
     * @Since(version="1")
     */
    public $alt;

    /**
     * @Type("array<string, array<string,string>>")
     * @Since(version="1")
     */
    public $sizes;

    public function https()
    {
        $sizes = self::makeHttps($this->sizes);

        return new static(
            $this->alt, $sizes
        );
    }

    private static function makeHttps($urls)
    {
        $sizes = [];
        foreach ($urls as $url) {
            foreach ($url as $k => $size) {
                $sizes[$k] = str_replace(['http:/', 'internal_elife_dummy_api'], ['https:/', 'dummyapi.com'], $size);
            }
        }

        return $sizes;
    }

    public function __construct(string $alt, array $images)
    {
        Assertion::allInArray(array_flip($images), [900, 1800], 'You need to provide all available sizes for this image');

        $this->alt = $alt;
        $this->sizes = [
            '2:1' => [
                900 => $images[900],
                1800 => $images[1800],
            ],
        ];
    }

    public static function fromModel(Image $image)
    {
        $images = [];
        foreach ($image->getSizes() as $resolution => $size) {
            // @todo make sure API SDK does not do this anymore and remove.
            if (is_string($size)) {
                $images[$resolution] = $size;
            } else {
                foreach ($size->getImages() as $res => $url) {
                    $images[$res] = $url;
                }
            }
        }

        return (new static(
            $image->getAltText(),
            $images
        ))->https();
    }
}
