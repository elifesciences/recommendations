<?php

namespace eLife\Recommendations\Command;

use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\Bus\Command\QueueCommand;
use eLife\Bus\Queue\QueueItem;
use eLife\Bus\Queue\QueueItemTransformer;
use eLife\Bus\Queue\WatchableQueue;
use eLife\Logging\Monitoring;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RuleModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;

final class MysqlRepoQueueCommand extends QueueCommand
{
    private $rules;

    public function __construct(
        Rules $rules,
        LoggerInterface $logger,
        WatchableQueue $queue,
        QueueItemTransformer $transformer,
        Monitoring $monitoring,
        callable $limit
    ) {
        $this->rules = $rules;

        parent::__construct($logger, $queue, $transformer, $monitoring, $limit);
    }

    protected function process(InputInterface $input, QueueItem $model)
    {
        if ($model instanceof PodcastEpisode) {
            // Import podcast.
            $ruleModel = new RuleModel($model->getNumber(), $type, $model->getPublishedDate());
            $this->logger->debug("We got $type with {$model->getNumber()}");
        } elseif (method_exists($model, 'getId')) {
            $published = method_exists($model, 'getPublishedDate') ? $model->getPublishedDate() : null;
            // Import et al.
            $ruleModel = new RuleModel($model->getId(), $model->getType(), $published);
            $this->logger->debug("We got {$model->getType()} with {$model->getId()}");
        } else {
            // Not good, not et al.
            $this->logger->alert('Unknown model type', ['model' => $model, 'type' => $model->getType()]);

            return;
        }
        // Import.
        $this->rules->import($ruleModel);
    }
}
