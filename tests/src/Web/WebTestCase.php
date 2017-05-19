<?php

namespace tests\eLife\Web;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use eLife\ApiSdk\Collection\ArraySequence;
use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\Collection;
use eLife\ApiSdk\Model\ExternalArticle;
use eLife\ApiSdk\Model\File;
use eLife\ApiSdk\Model\Image;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiSdk\Model\PodcastEpisodeChapter;
use eLife\ApiSdk\Model\Subject;
use eLife\App\Console;
use eLife\App\Kernel;
use eLife\Bus\Queue\SingleItemRepository;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule\Common\MicroSdk;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use Psr\Log\NullLogger;
use Silex\WebTestCase as SilexWebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use test\eLife\ApiSdk\Builder;

abstract class WebTestCase extends SilexWebTestCase
{
    protected $isLocal;
    protected $console;
    /** @var Kernel */
    protected $kernel;
    /** @var Client */
    protected $api;
    private $itemMocks = [];
    private $transformer;
    protected $sdkMock;
    private $logs = [];
    private $relatedArticles;

    public function getAllMocks()
    {
        return $this->itemMocks;
    }

    public function addPodcastEpisode(int $number, array $chapters, DateTimeImmutable $published)
    {
        $builder = Builder::for(PodcastEpisode::class);
        $podcastEpisode = $builder->create(PodcastEpisode::class)
            ->withNumber($number)
            ->withThumbnail(
                new Image('', 'https://iiif.elifesciences.org/thumbnail.jpg', new File('image/jpeg', 'https://iiif.elifesciences.org/thumbnail.jpg/full/full/0/default.jpg', 'thumbnail.jpg'), 140, 140, 50, 50)
            )
            ->withChapters(
                new ArraySequence(
                    array_map(function ($chapter) {
                        return new PodcastEpisodeChapter(
                            $chapter['number'],
                            'title '.$chapter['number'],
                            100,
                            null,
                            new ArraySequence($chapter['content'] ?? [])
                        );
                    }, $chapters)
                )
            )
            ->__invoke();
        $this->addDocument('podcast-episode', $number, $podcastEpisode);
        $model = new RuleModel($number, 'podcast-episode', $podcastEpisode->getPublishedDate());
        $this->getRulesProcess()->import($model);

        return $podcastEpisode;
    }

    public function addCollection(string $id, DateTimeImmutable $published, DateTimeImmutable $updated = null, array $articles)
    {
        $builder = Builder::for(Collection::class);
        $collection = $builder->create(Collection::class)
            ->withId($id)
            ->withPublishedDate($published)
            ->withUpdatedDate($updated)
            ->withContent(
                new ArraySequence($articles)
            )
            ->__invoke();
        $this->addDocument('collection', $id, $collection);
        $model = new RuleModel($id, 'collection', $collection->getPublishedDate());
        $this->getRulesProcess()->import($model);

        return $collection;
    }

    public function addArticle(string $id, string $type, array $subjects, DateTimeImmutable $published)
    {
        $builder = Builder::for(ArticlePoA::class);
        $article = $builder->create(ArticlePoA::class)
            ->withId($id)
            ->withType($type)
            ->withSubjects(
                new ArraySequence(
                    array_map(function ($subject) {
                        return Builder::for(Subject::class)
                            ->create(Subject::class)
                            ->withId($subject['id'])
                            ->withName($subject['name'])
                            ->__invoke();
                    }, $subjects)
                )
            )
            ->withPublished($published);

        $article = $article->__invoke();
        $this->addDocument('article', $id, $article);
        $model = new RuleModel($id, $type, $article->getPublishedDate());

        return [
            'article' => $article,
            'model' => $model,
        ];
    }

    public function addExternalArticle(string $compositeId)
    {
        $this->assertTrue((bool) preg_match('/^(\d+)-(.+)$/', $compositeId, $matches));
        $originalArticleId = $matches[1];
        $uri = $matches[2];
        $builder = Builder::for(ExternalArticle::class);
        $article = $builder->create(ExternalArticle::class)
            ->withUri($uri);

        $article = $article->__invoke();
        $this->addDocument('external-article', $compositeId, $article);
    }

    public function relateArticlesByIds($id, array $ids)
    {
        $articles = array_map(function ($id) {
            if (!empty($this->itemMocks['external-article'][$id])) {
                return $this->itemMocks['external-article'][$id];
            }

            return $this->itemMocks['article'][$id];
        }, $ids);

        $this->setRelatedArticles($id, $articles);
    }

    public function addArticlePoAWithId($id, $date = null, $insert = true)
    {
        $builder = Builder::for(ArticlePoA::class);
        /** @var ArticlePoA $PoaArticle */
        $PoaArticle = $builder->create(ArticlePoA::class)
            ->withId($id);
        if ($date) {
            $PoaArticle = $PoaArticle->withPublished($date);
        }
        $PoaArticle = $PoaArticle->__invoke();

        $this->addDocument('article', $id, $PoaArticle);

        $model = new RuleModel($id, 'research-article', $PoaArticle->getPublishedDate());
        if ($insert) {
            $this->getRulesRepo()->upsert($model);
        }

        return $model;
    }

    public function addDocument(string $type, string $id, Model $content)
    {
        $this->itemMocks[$type][$id] = $content;
    }

    public function addRelation(ManyToManyRelationship $ruleModel)
    {
        $this->getRulesRepo()->addRelation($ruleModel);
    }

    public function getJsonResponse()
    {
        /** @var Response $response */
        $response = $this->getResponse();
        if (!$response->isOk()) {
            $this->fail('Response returned was not 200: '.$response->getContent());
        }
        $json = json_decode($response->getContent());

        return $json;
    }

    public function newClient()
    {
        return $this->api = static::createClient();
    }

    public function getResponse()
    {
        return $this->api->getResponse();
    }

    public function createConfiguration()
    {
        if (file_exists(__DIR__.'/../../../config/local.php')) {
            $this->isLocal = true;
            $config = include __DIR__.'/../../../config/local.php';
        } else {
            $this->isLocal = false;
            $config = include __DIR__.'/../../../config/ci.php';
        }

        $config['api_url'] = 'http://api.com/';

        $config['tables']['rules'] = 'Rules__test';
        $config['tables']['references'] = 'References__test';

        return $this->modifyConfiguration($config);
    }

    public function getDatabase(): Connection
    {
        return $this->kernel->getApp()['db'];
    }

    public function getRulesProcess(): Rules
    {
        return $this->kernel->getApp()['rules.process'];
    }

    public function getRulesRepo(): RuleModelRepository
    {
        return $this->kernel->getApp()['rules.repository'];
    }

    public function modifyConfiguration($config)
    {
        return $config;
    }

    protected function mapHeaders($headers)
    {
        $httpHeaders = [];
        foreach ($headers as $key => $header) {
            $httpHeaders['HTTP_'.$key] = $headers[$key];
        }

        return $httpHeaders;
    }

    protected function jsonRequest(string $verb, string $endpoint, array $params = array(), array $headers = array())
    {
        $server = array_merge(array(
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ), $this->mapHeaders($headers));

        return $this->api->request(
            $verb,
            $endpoint,
            $params,
            [],
            $server
        );
    }

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $this->kernel = new Kernel($this->createConfiguration(), false);

        return $this->kernel->getApp();
    }

    public function setUp()
    {
        parent::setUp();
        $lines = $this->runCommand('generate:database');
//        $this->assertStringStartsWith('Database created successfully.', $lines[0], 'Failed to run test during set up');
    }

    public function tearDown()
    {
        $this->getDatabase()->query(sprintf('SET FOREIGN_KEY_CHECKS=%s', (int) false));
        // Delete database.
        $this->getDatabase()->getSchemaManager()->dropTable('References__test');
        $this->getDatabase()->getSchemaManager()->dropTable('Rules__test');
        $this->getDatabase()->query(sprintf('SET FOREIGN_KEY_CHECKS=%s', (int) true));
        parent::tearDown();
    }

    public function setRelatedArticles($id, array $list)
    {
        $this->relatedArticles[$id] = new ArraySequence($list);
    }

    public function getRelatedArticles($id)
    {
        return $this->relatedArticles[$id] ?? new ArraySequence([]);
    }

    public function addLog($log)
    {
        $this->logs[] = $log;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    public function runCommand(string $command)
    {
        $log = $this->returnCallback(function ($message) use (&$logs) {
            $this->addLog($message);
            $logs[] = $message;
        });
        $logs = [];
        $logger = $this->createMock(NullLogger::class);

        $transformerCallback = $this->returnCallback(function ($type, $id) {
            return $this->itemMocks[$type][$id] ?? null;
        });
        $transformer = $this->createMock(SingleItemRepository::class);
        $transformer
            ->expects($this->any())
            ->method('get')
            ->will($transformerCallback);
        $this->transformer = $transformer;

        $sdkMock = $this->createMock(MicroSdk::class);
        $sdkMock
            ->expects($this->any())
            ->method('get')
            ->will($transformerCallback);
        $sdkMock
            ->expects($this->any())
            ->method('getRelatedArticles')
            ->will(
                $this->returnCallback(
                    function ($id) {
                        return $this->getRelatedArticles($id);
                    }
                )
            );
        $this->sdkMock = $sdkMock;

        foreach (['debug', 'info', 'warning', 'critical', 'emergency', 'alert', 'log', 'notice', 'error'] as $level) {
            $logger
                ->expects($this->any())
                ->method($level)
                ->will($log);
        }
        $app = new Application();
        $this->kernel->withApp(function ($app) use ($logger, $transformer, $sdkMock) {
            unset($app['rules.micro_sdk']);
            $app['rules.micro_sdk'] = function () use ($sdkMock) {
                return $sdkMock;
            };
            unset($app['hydration.single_item_repository']);
            $app['hydration.single_item_repository'] = function () use ($transformer) {
                return $transformer;
            };
        });
        $this->kernel->setupFlow();
        $app->setAutoExit(false);
        $application = new Console($app, $this->kernel);
        $application->logger = $logger;

        $fp = tmpfile();
        $input = new StringInput($command);
        $output = new StreamOutput($fp);

        $application->run($input, $output);

        return $logs;
    }
}
