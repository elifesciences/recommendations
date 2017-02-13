<?php

namespace eLife\Recommendations\Command;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Schema\Schema;
use eLife\Logging\Monitoring;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class GenerateDatabaseCommand extends Command
{
    private $db;
    private $logger;
    private $monitoring;

    public function __construct(
        Connection $db,
        LoggerInterface $logger,
        Monitoring $monitoring
    ) {
        $this->db = $db;
        $this->logger = $logger;
        $this->monitoring = $monitoring;

        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('generate:database')
            ->setDescription('Creates schema for recommendations database.')
            ->addOption('drop', 'd', InputOption::VALUE_NONE);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->monitoring->nameTransaction('Scheme creation');
        $this->monitoring->startTransaction();
        $schema = new Schema();
        $rules = $schema->createTable('Rules');
        $rules->addColumn('rule_id', 'guid');
        $rules->addColumn('id', 'string', ['length' => 64]);
        $rules->addColumn('type', 'string', ['length' => 64]);
        $rules->addColumn('published', 'datetime', ['notnull' => false]); // Nullable.
        $rules->addColumn('isSynthetic', 'boolean', ['default' => false]);
        $rules->setPrimaryKey(['rule_id']);

        $references = $schema->createTable('References');
        $references->addColumn('on_id', 'guid');
        $references->addColumn('subject_id', 'guid');
        $references->setPrimaryKey(['on_id', 'subject_id']);
        $references->addForeignKeyConstraint($rules, ['on_id'], ['rule_id'], ['onUpdate' => 'CASCADE']);
        $references->addForeignKeyConstraint($rules, ['subject_id'], ['rule_id'], ['onUpdate' => 'CASCADE']);
        // Create table drop statements.
        $drops = [];
        if ($input->getOption('drop')) {
            $drops = $schema->toDropSql(new MySQL57Platform());
            $this->logger->debug('Dropping with sql:', [
                'sql' => implode("\n", $drops),
            ]);
        }
        // Create table insert statements.
        $inserts = $schema->toSql(new MySQL57Platform());
        $this->logger->debug('Populating with sql:', [
            'sql' => implode("\n", $inserts),
        ]);
        $arrayOfSqlQueries = array_merge($drops, $inserts);
        // Loop through and execute statements.
        foreach ($arrayOfSqlQueries as $query) {
            try {
                $this->db->exec($query);
            } catch (Throwable $e) {
                $this->monitoring->recordException($e, 'Problem creating database schema.');
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                throw $e;
            } finally {
                $this->monitoring->endTransaction();
            }
        }
        $this->logger->info('Database created successfully.');
    }
}
