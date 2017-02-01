<?php
/**
 * Hydration.
 *
 * Given a list of "RuleModels" with Ids, Types and Datetimes it will order them
 * and fetch their full fields from the API SDK (with caching).
 *
 * The result will be passed on to the Serializer.
 */

namespace eLife\Recommendations\Process;

use Assert\Assertion;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\HasSubjects;
use eLife\Recommendations\Rule\Common\GetSdk;
use eLife\Recommendations\RuleModel;
use function GuzzleHttp\Promise\all;

final class Hydration
{
    use GetSdk;

    /** @var ApiSdk */
    private $sdk;
    private $cache = [];

    public function transform(RuleModel $item)
    {
        $sdk = $this->getSdk($item->getType());
        $entity = $sdk->get($item->getId());

        return $entity->wait(true);
    }

    public function __construct(ApiSdk $sdk)
    {
        $this->sdk = $sdk;
    }

    private function getModel(RuleModel $model, $unwrap = false)
    {
        if (isset($this->cache[$model->getType()][$model->getId()])) {
            return $this->cache[$model->getType()][$model->getId()];
        }
        $sdk = $this->getSdk($model->getType());
        $entity = $sdk->get($model->getId());
        if ($unwrap) {
            return $entity->wait(true);
        }

        return $entity->then(function ($model) {
            if (method_exists($model, 'getType')) {
                $this->cache[$model->getType()][$model->getId()] = $model;
                // @todo enable if required.
                // $this->extractRelatedFrom(new RuleModel($model->getId(), $model->getType()));
            }

            return $model;
        });
    }

    public function hydrateOne(RuleModel $model)
    {
        return $this->getModel($model);
    }

    public function extractRelatedFrom(RuleModel $model)
    {
        $model = $this->getModel($model, true);
        if ($model instanceof HasSubjects) {
            $this->cache['subjects'] = $this->cache['subjects'] ?? [];
            /** @var $model ArticleVersion */
            foreach ($model->getSubjects() as $subjected) {
                $this->cache['subjects'][$subjected->getId()] = $subjected;
            }
        }

        if ($model instanceof ArticleVersion) {
            $this->cache['related-article'] = $this->cache['related-article'] ?? [];
            /** @var $model ArticleVersion */
            foreach ($model->getRelatedArticles() as $relatedArticle) {
                $this->cache[$relatedArticle->getType()][$relatedArticle->getId()] = $relatedArticle;
            }
        }
    }

    /**
     * @param array $rules
     *
     * @return array
     */
    public function hydrateAll(array $rules) : array
    {
        Assertion::allIsInstanceOf($rules, RuleModel::class);
        $entities = array_map([$this, 'hydrateOne'], $rules);

        return all($entities)->wait(true);
    }
}
