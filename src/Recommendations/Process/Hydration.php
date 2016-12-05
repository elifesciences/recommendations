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

final class Hydration
{
    private $sdk;

    public function __construct(ApiSdk $sdk)
    {
        $this->sdk = $sdk;
    }

    private function hydrateOne(RuleModel $model)
    {
        // @todo implementation.
        // Returns one of our request models.
        return $model;
    }

    /**
     * @param array $rules
     *
     * @return array
     */
    public function hydrateAll(array $rules) : array
    {
        Assertion::allIsInstanceOf(RuleModel::class, $rules);
        // @todo implementation
        return array_map([$this, 'hydrateOne'], $rules);
    }
}
