<?php

namespace eLife\Recommendations;

use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\ArticleVoR;
use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\Recommendations\Response\Collection;
use eLife\Recommendations\Response\PoaArticle;
use eLife\Recommendations\Response\PodcastEpisode;
use eLife\Recommendations\Response\VorArticle;
use JMS\Serializer\Annotation\Since;
use JMS\Serializer\Annotation\Type;

final class RecommendationsResponse
{
    /**
     * @Type("array<eLife\Recommendations\Response\Result>")
     * @Since(version="1")
     */
    public $items = [];

    /**
     * @Type("integer");
     * @Since(version="1")
     */
    public $total;

    public function __construct(array $items, int $total)
    {
        $this->items = $items;
        $this->total = $total;
    }

    public static function fromModels($models, int $total)
    {
        $items = array_filter(array_map(function ($model) {
            switch (true) {
                case $model instanceof ArticlePoA:
                    return PoaArticle::fromModel($model);
                case $model instanceof ArticleVoR:
                    return VorArticle::fromModel($model);
                /*
                case $model instanceof ExternalArticle:
                    return ExternalArticle::fromModel($model);
                */
                case $model instanceof CollectionModel:
                    return Collection::fromModel($model);
                case $model instanceof PodcastEpisodeModel:
                    return PodcastEpisode::fromModel($model);
                default:
                    return null;
            }
        }, $models));

        return new static($items, $total);
    }
}
