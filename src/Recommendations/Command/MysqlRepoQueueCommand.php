<?php

namespace eLife\Recommendations\Command;

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
        $this->rules->import(new RuleModel($model->getId(), $model->getType()));
    }
}
