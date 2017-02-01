<?php

namespace eLife\Recommendations\Command;

use eLife\Api\Command\PopulateCommand;
use eLife\ApiSdk\ApiSdk;
use eLife\Bus\Queue\InternalSqsMessage;
use eLife\Bus\Queue\WatchableQueue;
use eLife\Logging\Monitoring;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\LoggerInterface;

final class PopulateRulesCommand extends PopulateCommand
{
    private $repo;
    private $rules;
    private $queue;

    public function __construct(
        ApiSdk $sdk,
        RuleModelRepository $repo,
        WatchableQueue $queue,
        Rules $rules,
        LoggerInterface $logger,
        Monitoring $monitoring,
        callable $limit
    ) {
        $this->repo = $repo;
        $this->rules = $rules;
        $this->limit = $limit;
        $this->queue = $queue;
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
        $this->logger->debug("{$this->getName()} Importing model from SDK", [
            'type' => $type,
        ]);
        $ruleModel = $this->rules->getRuleModelFromSdk($model, $type);
        if ($ruleModel) {
            // Add to SQS
            $this->queue->enqueue(new InternalSqsMessage($ruleModel->getType(), $ruleModel->getId()));
        }
    }
}
