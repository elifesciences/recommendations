<?php

namespace eLife\Recommendations\Command;

use eLife\Api\Command\PopulateCommand;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;

final class PopulateRulesCommand extends PopulateCommand
{
    private $repo;
    private $rules;

    public function __construct(ApiSdk $sdk, RuleModelRepository $repo, Rules $rules)
    {
        $this->repo = $repo;
        $this->rules = $rules;
        parent::__construct($sdk, false);
    }

    protected static function getSupportedModels()
    {
        return ['all', 'PodcastEpisodes', 'Collections', 'ResearchArticles'];
    }

    protected function getCommandHelp(): string
    {
        return 'Populates the database with relationships based on the rules configured.';
    }

    public function processModel(string $type, $model, LoggerInterface $logger)
    {
        if ($model instanceof PodcastEpisode) {
            // Import podcast.
            $ruleModel = new RuleModel($model->getNumber(), $type);
            $logger->debug("We got $type with {$model->getNumber()}");
        } elseif (method_exists($model, 'getId')) {
            // Import et al.
            $ruleModel = new RuleModel($model->getId(), $type);
            $logger->debug("We got $type with {$model->getId()}");
        } else {
            // Not good, not et al.
            $logger->alert('Unknown model type', ['model' => $model, 'type' => $type]);

            return;
        }
        // Import.
        $this->rules->import($ruleModel);
    }
}
