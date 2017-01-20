<?php
/**
 * The one Rule to ring them all.
 *
 * This class will take in a set of rules, run through them and return a
 * list of RuleModels.
 *
 * The result will be passed to "Hydration".
 */

namespace eLife\Recommendations\Process;

use BadMethodCallException;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use Psr\Log\LoggerInterface;

final class Rules
{
    private $rules;
    private $logger;

    public function __construct(LoggerInterface $logger, Rule ...$rules)
    {
        $this->rules = $rules;
        $this->logger = $logger;
    }

    public function isSupported(RuleModel $model, Rule $rule)
    {
        return in_array($model->getType(), $rule->supports());
    }

    public function importFromSdk(Model $model, string $type = null)
    {
        if ($type === null) {
            $type = method_exists($model, 'getType') ? $model->getType() : null;
        }
        $ruleModel = null;
        if ($model instanceof PodcastEpisode) {
            // Import podcast.
            $ruleModel = new RuleModel($model->getNumber(), 'podcast-episode', $model->getPublishedDate());
            $this->logger->debug("We got $type with {$model->getNumber()}");
        } elseif (method_exists($model, 'getId') && $type) {
            $published = method_exists($model, 'getPublishedDate') ? $model->getPublishedDate() : null;
            // Import et al.
            $ruleModel = new RuleModel($model->getId(), $type, $published);
            $this->logger->debug("We got {$type} with {$model->getId()}");
        } else {
            // Not good, not et al.
            $this->logger->alert('Unknown model type', [
                'model' => $model,
                'type' => $type,
            ]);
        }
        if ($ruleModel) {
            // Import.
            $this->import($ruleModel);
        }
    }

    public function import(RuleModel $model, bool $upsert = true, bool $prune = false): array
    {
        if ($upsert === false && $prune === true) {
            throw new BadMethodCallException('You must upsert in order to prune.');
        }
        $all = [];
        foreach ($this->rules as $rule) {
            if ($this->isSupported($model, $rule) === false) {
                continue;
            }
            $relations = $rule->resolveRelations($model);
            if ($upsert) {
                array_map([$rule, 'upsert'], $relations);
            }
            if ($prune) {
                $rule->prune($model, $relations);
            }
            $all = array_merge($all, $relations);
        }

        return $all;
    }

    public function getRecommendations(RuleModel $model)
    {
        $next = [];
        foreach ($this->rules as $rule) {
            $prev = $next;
            $next = $rule->addRelations($model, $prev);
        }

        return $next;
    }
}
