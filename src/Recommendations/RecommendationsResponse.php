<?php

namespace eLife\Recommendations;

use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\ArticleVoR;
use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\ApiSdk\Model\ExternalArticle as ExternalArticleModel;
use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\Recommendations\Response\Collection;
use eLife\Recommendations\Response\ExternalArticle;
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
            switch (get_class($model)) {
                case ArticlePoA::class:
                    return PoaArticle::fromModel($model);
                case ArticleVoR::class:
                    return VorArticle::fromModel($model);
                case ExternalArticleModel::class:
                    return ExternalArticle::fromModel($model);
                case CollectionModel::class:
                    return Collection::fromModel($model);
                case PodcastEpisodeModel::class:
                    return PodcastEpisode::fromModel($model);
                default:
                    return;
            }
        }, $models));

        return new static($items, $total);
    }
}
