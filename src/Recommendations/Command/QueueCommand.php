<?php
/**
 * Queue command.
 *
 * For listening to SQS.
 */

namespace eLife\Recommendations\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueCommand extends Command
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('queue:watch')
            ->setDescription('Watches SQS for changes to articles, ')
            ->addOption('drop', 'd', InputOption::VALUE_NONE)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('this is awesome');
    }
}
