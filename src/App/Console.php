<?php

namespace eLife\App;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Schema\Schema;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;

final class Console
{
    /**
     * These commands map to [name]Command so when the command "hello" is configured
     * it will call helloCommand() on this class with InputInterface and OutputInterface
     * as parameters.
     *
     * This will hopefully cover most things.
     */
    public static $quick_commands = [
        'hello' => ['description' => 'This is a quick hello world command'],
        'echo' => ['description' => 'Example of asking a question'],
        'cache:clear' => ['description' => 'Clears cache'],
        'debug:params' => ['description' => 'Lists current parameters'],
        'generatedatabase' => ['description' => 'Generates Database'],
    ];

    /** @var Connection */
    private $db;

    public function __construct(Application $console, Kernel $app)
    {
        $this->console = $console;
        $this->app = $app;
        $this->root = __DIR__.'/../..';
        $this->setDb($app->get('db'));

        $this->console->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'));
    }

    private function setDb(Connection $connection)
    {
        $this->db = $connection;
    }

    private function path($path = '')
    {
        return $this->root.$path;
    }

    public function debugParamsCommand(InputInterface $input, OutputInterface $output, LoggerInterface $logger)
    {
        foreach ($this->app->get('config') as $key => $config) {
            if (is_array($config)) {
                $logger->warning($key);
                $logger->info(json_encode($config, JSON_PRETTY_PRINT));
                $logger->debug(' ');
            } elseif (is_bool($config)) {
                $logger->warning($key);
                $logger->info($config ? 'true' : 'false');
                $logger->debug(' ');
            } else {
                $logger->warning($key);
                $logger->info($config);
                $logger->debug(' ');
            }
        }
    }

    public function cacheClearCommand(InputInterface $input, OutputInterface $output, LoggerInterface $logger)
    {
        $logger->warning('Clearing cache...');
        try {
            exec('rm -rf '.$this->root.'/cache/*');
        } catch (Exception $e) {
            $logger->error($e);
        }
        $logger->info('Cache cleared successfully.');
    }

    public function echoCommand(InputInterface $input, OutputInterface $output, LoggerInterface $logger)
    {
        $question = new Question('<question>Are we there yet?</question> ');
        $helper = new QuestionHelper();
        while (true) {
            $name = $helper->ask($input, $output, $question);
            if ($name === 'yes') {
                break;
            }
            $logger->error($name);
        }
    }

    public function helloCommand(InputInterface $input, OutputInterface $output, LoggerInterface $logger)
    {
        $logger->info('Hello from the outside (of the global scope)');
        $logger->debug('This is working');
    }

    public function generatedatabaseCommand(InputInterface $input, OutputInterface $output, LoggerInterface $logger)
    {
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

        $drops = $schema->toDropSql(new MySQL57Platform());
        $arrayOfSqlQueries = array_merge($drops, $schema->toSql(new MySQL57Platform()));

        foreach ($arrayOfSqlQueries as $query) {
            try {
                $this->db->exec($query);
            } catch (Throwable $e) {
                $logger->error($e->getMessage(), ['exception' => $e]);

                return;
            }
        }
        $logger->debug('Database created successfully.');
    }

    public function run()
    {
        foreach (self::$quick_commands as $name => $cmd) {
            if (strpos($name, ':')) {
                $pieces = explode(':', $name);
                $first = array_shift($pieces);
                $pieces = array_map('ucfirst', $pieces);
                array_unshift($pieces, $first);
                $fn = implode('', $pieces);
            } else {
                $fn = $name;
            }
            if (!method_exists($this, $fn.'Command')) {
                throw new LogicException('Your command does not exist: '.$fn.'Command');
            }
            // Hello
            $command = $this->console
                ->register($name)
                ->setDescription($cmd['description'] ?? $name.' command')
                ->setCode(Closure::bind(function (InputInterface $input, OutputInterface $output) use ($fn, $name) {
                    $logger = new CliLogger($input, $output);
                    $this->{$fn.'Command'}($input, $output, $logger);
                }, $this));

            if (isset($cmd['args'])) {
                foreach ($cmd['args'] as $arg) {
                    $command->addArgument($arg['name'], $arg['mode'] ?? null, $arg['description'] ?? '', $arg['default'] ?? null);
                }
            }
        }
        $this->console->run();
    }
}
