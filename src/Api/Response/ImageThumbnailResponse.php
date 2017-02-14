<?php

namespace eLife\Api\Response;

use Assert\Assertion;
use eLife\ApiSdk\Model\Image as ImageModel;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class ImageThumbnailResponse implements ImageVariant
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
        Assertion::allInArray(array_flip($images), [250, 500, 70, 140], 'You need to provide all available sizes for this image ['.implode(array_diff(array_flip($images), [250, 500, 70, 140])).']');

        $this->alt = $alt;
        $this->sizes = [
            '16:9' => [
                250 => $images[250],
                500 => $images[500],
            ],
            '1:1' => [
                70 => $images[70],
                140 => $images[140],
            ],
        ];
    }

    public static function fromModel(ImageModel $image)
    {
        $images = [];
        foreach ($image->getSizes() as $resolution => $size) {
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
