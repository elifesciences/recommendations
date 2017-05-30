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
use Symfony\Component\Console\Input\InputArgument;
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

        parent::__construct($logger, $queue, $transformer, $monitoring, $limit, false);
    }

    protected function configure()
    {
        $this
            ->setName('queue:watch')
            ->setDescription('Create queue watcher')
            ->setHelp('Creates process that will watch for incoming items on a queue')
            ->addArgument('id', InputArgument::OPTIONAL, 'Identifier to distinguish workers from each other');
    }

    protected function process(InputInterface $input, QueueItem $model, $entity = null)
    {
        $type = method_exists($entity, 'getType') ? $entity->getType() : $model->getType();
        $published = method_exists($entity, 'getPublishedDate') ? $entity->getPublishedDate() : null;
        $this->logger->debug("{$this->getName()} Adding model to queue", [
            'type' => $type,
            'id' => $model->getId(),
            'published' => $published,
        ]);

        $this->rules->import(new RuleModel($model->getId(), $type, $published));
    }
}
