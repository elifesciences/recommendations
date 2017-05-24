<?php
/**
 * Populate command.
 *
 * For populating the database initially.
 */

namespace eLife\Api\Command;

use eLife\ApiSdk\ApiSdk;
use eLife\Bus\Limit\Limit;
use eLife\Logging\Monitoring;
use Iterator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class PopulateCommand extends Command
{
    private $sdk;
    private $serializer;
    private $output;
    protected $logger;
    /** @var Limit */
    protected $limit;
    private $monitoring;

    public function __construct(
        ApiSdk $sdk,
        LoggerInterface $logger,
        Monitoring $monitoring,
        callable $limit
    ) {
        $this->serializer = $sdk->getSerializer();
        $this->sdk = $sdk;
        $this->logger = $logger;
        $this->monitoring = $monitoring;
        $this->limit = $limit;

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
        return ['all', 'PodcastEpisodes', 'Collections', 'ResearchArticles'];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $entity = $input->getArgument('entity');
        // Only the configured.
        if (!in_array($entity, static::getSupportedModels())) {
            $this->logger->error('Entity with name '.$entity.' not supported.');

            return;
        }
        if ($entity === 'all') {
            foreach (static::getSupportedModels() as $e) {
                if ($e !== 'all') {
                    // Run the item.
                    $this->{'import'.$e}();
                }
            }
        } else {
            // Run the item.
            $this->{'import'.$entity}();
        }
        // Reporting.
        $this->logger->info("\nAll entities queued.");
    }

    public function importPodcastEpisodes()
    {
        $this->logger->info('Importing Podcast Episodes');
        $episodes = $this->sdk->podcastEpisodes();
        $this->iterateSerializeTask($episodes, 'podcast-episode', $episodes->count());
    }

    public function importCollections()
    {
        $this->logger->info('Importing Collections');
        $collections = $this->sdk->collections();
        $this->iterateSerializeTask($collections, 'collection', $collections->count());
    }

    public function importResearchArticles()
    {
        $this->logger->info('Importing Research Articles');
        $articles = $this->sdk->articles();
        $this->iterateSerializeTask($articles, 'article', $articles->count());
    }

    private function iterateSerializeTask(Iterator $items, string $type, int $count)
    {
        $this->logger->info("Importing $count items of type $type");
        $progress = new ProgressBar($this->output, $count);
        $limit = $this->limit;

        $items->rewind();
        while ($items->valid()) {
            if ($limit()) {
                throw new RuntimeException('Command cannot complete because: '.implode(', ', $limit->getReasons()));
            }
            $progress->advance();
            try {
                $item = $items->current();
                if ($item === null) {
                    $items->next();
                    continue;
                }
                $this->processModel($type, $item);
            } catch (Throwable $e) {
                $item = $item ?? null;
                $this->logger->error('Skipping import on a '.get_class($item), ['exception' => $e]);
                $this->monitoring->recordException($e, 'Skipping import on a '.get_class($item));
            }
            $items->next();
        }
        $progress->finish();
        $progress->clear();
    }

    abstract public function processModel(string $type, $model);
}
