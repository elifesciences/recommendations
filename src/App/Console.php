<?php

namespace eLife\App;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Closure;
use Exception;
use LogicException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Console
{
    public static $quick_commands = [
        'cache:clear' => ['description' => 'Clears cache'],
        'debug:params' => ['description' => 'Lists current parameters'],
        'queue:create' => ['description' => 'Lists current parameters'],
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
        // Set up logger.
        $this->logger = $app->get('logger');
        try {
            $this->console->add($app->get('console.populate_rules'));
            $this->console->add($app->get('console.queue'));
        } catch (SqsException $e) {
            $this->logger->warning('Something went wrong while connecting to SQS', ['exception' => $e]);
        }
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
