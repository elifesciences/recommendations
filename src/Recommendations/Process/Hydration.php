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
use eLife\Recommendations\RuleModel;
use LogicException;
use function GuzzleHttp\Promise\all;

final class Hydration
{
    /** @var ApiSdk */
    private $sdk;

    public function getSdk(RuleModel $item)
    {
        switch ($item->getType()) {
            case 'podcast-episode':
                return $this->sdk->podcastEpisodes();
                break;

            case 'collection':
                return $this->sdk->collections();
                break;

            case 'research-article':
                return $this->sdk->articles();
                break;

            default:
                throw new LogicException('ApiSDK does not exist for that type.');
        }
    }

    public function transform(RuleModel $item)
    {
        $sdk = $this->getSdk($item);
        $entity = $sdk->get($item->getId());

        return $entity->wait(true);
    }

    public function __construct(ApiSdk $sdk)
    {
        $this->sdk = $sdk;
    }

    private function getModel(RuleModel $model, $unwrap = false)
    {
        $sdk = $this->getSdk($model);
        $entity = $sdk->get($model->getId());
        if ($unwrap) {
            return $entity->wait(true);
        }

        return $entity;
    }

    public function hydrateOne(RuleModel $model)
    {
        return $this->getModel($model, true);
    }

    /**
     * @param array $rules
     *
     * @return array
     */
    public function hydrateAll(array $rules) : array
    {
        Assertion::allIsInstanceOf(RuleModel::class, $rules);
        $entities = array_map([$this, 'hydrateOne'], $rules);

        return all($entities)->wait(true);
    }
}
