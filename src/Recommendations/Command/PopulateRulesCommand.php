<?php

namespace eLife\Recommendations\Command;

use eLife\Api\Command\PopulateCommand;
use eLife\ApiSdk\ApiSdk;
use eLife\Logging\Monitoring;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;

final class PopulateRulesCommand extends PopulateCommand
{
    private $repo;
    private $rules;

    public function __construct(
        ApiSdk $sdk,
        RuleModelRepository $repo,
        Rules $rules,
        LoggerInterface $logger,
        Monitoring $monitoring,
        callable $limit
    ) {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->limit = $limit;
        parent::__construct($sdk, $logger, $monitoring, $limit);
    }

    protected static function getSupportedModels()
    {
        return ['all', 'PodcastEpisodes', 'Collections', 'ResearchArticles'];
    }

    protected function getCommandHelp(): string
    {
        return 'Populates the database with relationships based on the rules configured.';
    }

    public function processModel(string $type, $model)
    {
        $this->rules->importFromSdk($model, $type);
    }
}
