<?php
/**
 * Populate command.
 *
 * For populating the database initially.
 */

namespace eLife\Api\Command;

use eLife\ApiSdk\ApiSdk;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Traversable;

abstract class PopulateCommand extends Command
{
    private $sdk;
    private $serializer;
    private $output;
    private $showProgressBar;

    public function __construct(
        ApiSdk $sdk,
        bool $showProgressBar = true
    ) {
        $this->serializer = $sdk->getSerializer();
        $this->sdk = $sdk;
        $this->showProgressBar = $showProgressBar;
        parent::__construct(null);
    }

    protected function configure()
    {
        $this
            ->setName('api:import')
            ->setDescription('Import items from API.')
            ->setHelp($this->getCommandHelp())
            ->addArgument('entity', InputArgument::REQUIRED, 'Must be one of the following <comment>['.implode(', ', self::getSupportedModels()).']</comment>');
    }

    abstract protected function getCommandHelp() : string;

    protected static function getSupportedModels()
    {
        return ['all', 'BlogArticles', 'Events', 'Interviews', 'LabsExperiments', 'PodcastEpisodes', 'Collections', 'ResearchArticles'];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new CliLogger($input, $output);
        $this->output = $output;
        $entity = $input->getArgument('entity');
        // Only the configured.
        if (!in_array($entity, static::getSupportedModels())) {
            $logger->error('Entity with name '.$entity.' not supported.');

            return;
        }
        if ($entity === 'all') {
            foreach (static::getSupportedModels() as $e) {
                if ($e !== 'all') {
                    // Run the item.
                    $this->{'import'.$e}($logger);
                }
            }
        } else {
            // Run the item.
            $this->{'import'.$entity}($logger);
        }
        // Reporting.
        $logger->info("\nAll entities queued.");
    }

    public function importPodcastEpisodes(LoggerInterface $logger)
    {
        $logger->info('Importing Podcast Episodes');
        $episodes = $this->sdk->podcastEpisodes();
        $this->iterateSerializeTask($episodes, $logger, 'podcast-episode');
    }

    public function importCollections(LoggerInterface $logger)
    {
        $logger->info('Importing Collections');
        $collections = $this->sdk->collections();
        $this->iterateSerializeTask($collections, $logger, 'collection', $collections->count());
    }

    public function importLabsExperiments(LoggerInterface $logger)
    {
        $logger->info('Importing Labs Experiments');
        $events = $this->sdk->labsExperiments();
        $this->iterateSerializeTask($events, $logger, 'labs-experiment', $events->count());
    }

    public function importResearchArticles(LoggerInterface $logger)
    {
        $logger->info('Importing Research Articles');
        $events = $this->sdk->articles();
        $this->iterateSerializeTask($events, $logger, 'research-article', $events->count());
    }

    public function importInterviews(LoggerInterface $logger)
    {
        $logger->info('Importing Interviews');
        $events = $this->sdk->interviews();
        $this->iterateSerializeTask($events, $logger, 'interview', $events->count());
    }

    public function importEvents(LoggerInterface $logger)
    {
        $logger->info('Importing Events');
        $events = $this->sdk->events();
        $this->iterateSerializeTask($events, $logger, 'event', $events->count());
    }

    public function importBlogArticles(LoggerInterface $logger)
    {
        $logger->info('Importing Blog Articles');
        $articles = $this->sdk->blogArticles();
        $this->iterateSerializeTask($articles, $logger, 'blog-article', $articles->count());
    }

    private function iterateSerializeTask(Traversable $items, LoggerInterface $logger, string $type, int $count = 0)
    {
        $progress = null;
        if ($this->showProgressBar) {
            $progress = new ProgressBar($this->output, $count);
        }
        foreach ($items as $item) {
            if ($progress) {
                $progress->advance();
            }
            try {
                $this->processModel($type, $item, $logger);
            } catch (Throwable $e) {
                $logger->alert($e->getMessage());
                $logger->warning('Skipping import on a '.get_class($item), ['exception' => $e]);
                continue;
            }
        }
        if ($progress) {
            $progress->finish();
            $progress->clear();
        }
    }

    abstract public function processModel(string $type, $model, LoggerInterface $logger);
}
