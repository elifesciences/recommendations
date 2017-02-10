<?php

namespace eLife\App;

use Aws\Sqs\SqsClient;
use Closure;
use Exception;
use LogicException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Console
{
    public static $quick_commands = [
        'cache:clear' => ['description' => 'Clears cache'],
        'debug:params' => ['description' => 'Lists current parameters'],
        'queue:create' => ['description' => 'Lists current parameters'],
        'query:count' => [
            'description' => 'Runs query from bin/queries folder',
            'args' => [
                [
                    'name' => 'file',
                    'mode' => InputArgument::REQUIRED,
                    'description' => 'e.g. easy-read-rules',
                ],
            ],
        ],
    ];

    public function __construct(Application $console, Kernel $app)
    {
        $this->console = $console;
        $this->app = $app;
        $this->root = __DIR__.'/../..';
        $this->config = $this->app->get('config');

        $this->console
            ->getDefinition()
            ->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'));

        // Add custom commands.
        $this->console->add($app->get('console.generate_database'));
        $this->console->add($app->get('console.queue_count'));
        $this->console->add($app->get('console.queue_clean'));
        // Set up logger.
        $this->logger = $app->get('logger');
        $this->console->add($app->get('console.populate_rules'));
        $this->console->add($app->get('console.queue'));
    }

    public function queryCountCommand(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $path = $this->root.'/bin/queries/'.$file.'.sql';

        if (!file_exists($path)) {
            $this->logger->error('Query not found');

            return;
        }

        $query = file_get_contents($path);

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->app->get('db');

        $result = $connection->prepare($query);
        $result->execute();

        $output->writeln($result->rowCount());
    }

    public function queueCreateCommand()
    {
        /** @var SqsClient $queue */
        $queue = $this->app->get('aws.sqs');

        $queue->createQueue([
            'Region' => $this->config['aws']['region'],
            'QueueName' => $this->config['aws']['queue_name'],
        ]);
    }

    public function debugParamsCommand()
    {
        foreach ($this->app->get('config') as $key => $config) {
            if (is_array($config)) {
                $this->logger->warning($key);
                $this->logger->info(json_encode($config, JSON_PRETTY_PRINT));
                $this->logger->debug(' ');
            } elseif (is_bool($config)) {
                $this->logger->warning($key);
                $this->logger->info($config ? 'true' : 'false');
                $this->logger->debug(' ');
            } else {
                $this->logger->warning($key);
                $this->logger->info($config);
                $this->logger->debug(' ');
            }
        }
    }

    public function cacheClearCommand()
    {
        $this->logger->warning('Clearing cache...');
        try {
            exec('rm -rf '.$this->root.'/var/cache/*');
        } catch (Exception $e) {
            $this->logger->error($e);
        }
        $this->logger->info('Cache cleared successfully.');
    }

    public function run($input = null, $output = null)
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
                    $this->{$fn.'Command'}($input, $output);
                }, $this));

            if (isset($cmd['args'])) {
                foreach ($cmd['args'] as $arg) {
                    $command->addArgument($arg['name'], $arg['mode'] ?? null, $arg['description'] ?? '', $arg['default'] ?? null);
                }
            }
        }
        $this->console->run($input, $output);
    }
}
