<?php

namespace test\eLife\Recommendations;

use ComposerLocator;
use Csa\Bundle\GuzzleBundle\Cache\StorageAdapterInterface;
use Csa\Bundle\GuzzleBundle\GuzzleHttp\Middleware\MockMiddleware;
use DateTimeImmutable;
use eLife\ApiClient\ApiClient\ArticlesClient;
use eLife\ApiClient\ApiClient\CollectionsClient;
use eLife\ApiClient\ApiClient\PodcastClient;
use eLife\ApiClient\ApiClient\SearchClient;
use eLife\ApiClient\MediaType;
use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\Collection;
use eLife\ApiSdk\Model\HasIdentifier;
use eLife\ApiSdk\Model\Identifier;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\ApiValidator\MessageValidator;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PathBasedSchemaFinder;
use eLife\Recommendations\AppKernel;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use function GuzzleHttp\json_encode;

abstract class ApplicationTestCase extends ApiTestCase
{
    /** @var AppKernel */
    private $app;

    /**
     * @before
     */
    final public function setUpApp()
    {
        $this->app = new AppKernel('test');
    }

    final protected function getApp() : AppKernel
    {
        return $this->app;
    }

    final protected function getApiSdk() : ApiSdk
    {
        return $this->app->getContainer()->get('elife.api_sdk');
    }

    final protected function getMockStorage() : StorageAdapterInterface
    {
        return $this->app->getContainer()->get('elife.guzzle_client.mock.storage');
    }

    final protected function getValidator() : MessageValidator
    {
        return $this->app->getContainer()->get('elife.json_message_validator');
    }

    final protected function assertJsonStringEqualsJson(array $expectedJson, string $actualJson, $message = '')
    {
        $this->assertJsonStringEqualsJsonString(json_encode($expectedJson), $actualJson, $message);
    }
}
