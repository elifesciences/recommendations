<?php

namespace eLife\Tests\Response;

use DateTimeImmutable;
use Doctrine\Common\Annotations\AnnotationRegistry;
use eLife\ApiSdk\Model\ArticlePoA;
use eLife\ApiSdk\Model\ArticleVoR;
use eLife\ApiSdk\Model\Collection as CollectionModel;
use eLife\ApiSdk\Model\Image;
use eLife\ApiSdk\Model\ImageSize;
use eLife\ApiSdk\Model\PodcastEpisode as PodcastEpisodeModel;
use eLife\ApiSdk\Model\ExternalArticle as ExternalArticleModel;
use eLife\ApiValidator\MessageValidator\JsonMessageValidator;
use eLife\ApiValidator\SchemaFinder\PuliSchemaFinder;
use eLife\Recommendations\RecommendationResultDiscriminator;
use eLife\Recommendations\RecommendationsResponse;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use PHPUnit_Framework_TestCase;
use StdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\HttpFoundation\Response;
use test\eLife\ApiSdk\Builder;
use test\eLife\ApiSdk\PuliAwareTestCase;
use Webmozart\Json\JsonDecoder;

class RecommendationResponseTest extends PHPUnit_Framework_TestCase
{
    use PuliAwareTestCase;

    private $serializer;
    private $context;
    private $validator;
    private $psr7Bridge;

    public function __construct()
    {
        $this->setUpPuli();
        // Annotations.
        AnnotationRegistry::registerAutoloadNamespace(
            'JMS\Serializer\Annotation', __DIR__.'/../../../vendor/jms/serializer/src'
        );
        // Serializer.
        $this->serializer = SerializerBuilder::create()
            ->configureListeners(function (EventDispatcher $dispatcher) {
                $dispatcher->addSubscriber(new RecommendationResultDiscriminator());
            })
            ->build();
        $this->context = SerializationContext::create();

        // PSR-7 Bridge
        $this->psr7Bridge = new DiactorosFactory();
        // Validator.
        $this->validator = new JsonMessageValidator(
            new PuliSchemaFinder(self::$puli),
            new JsonDecoder()
        );

        parent::__construct();
    }

    public function validate(Response $response)
    {
        /* @noinspection PhpParamsInspection */
        $this->validator->validate($this->psr7Bridge->createResponse($response));
    }

    public function test_recommendations_can_be_build_from_models_with_minimum_fields()
    {
        $builder = Builder::for(PodcastEpisodeModel::class);

        $externalArticle = $builder
            ->create(ExternalArticleModel::class)
            ->__invoke();

        $collection = $builder
            ->create(CollectionModel::class)
            ->withImpactStatement('Tropical disease impact statement')
            ->__invoke();

        $podcast = $builder
            ->create(PodcastEpisodeModel::class)
            ->withThumbnail(
                new Image('alt', [
                    new ImageSize('16:9', [
                        250 => 'https://placehold.it/250x140',
                        500 => 'https://placehold.it/500x280',
                    ]),
                    new ImageSize('1:1', [
                        70 => 'https://placehold.it/70x70',
                        140 => 'https://placehold.it/140x140',
                    ]),
                ])
            )
            ->__invoke();

        $PoaArticle = $builder
            ->create(ArticlePoA::class)
            ->withPublished(new DateTimeImmutable())
            ->__invoke();

        $VoRArticle = $builder
            ->create(ArticleVoR::class)
            ->withPublished(new DateTimeImmutable())
            ->__invoke();

        // Build recommendations.
        $recommendations = RecommendationsResponse::fromModels([$collection, $podcast, $PoaArticle, $VoRArticle, $externalArticle, new StdClass()], 1);
        // Should not throw.
        $json = $this->serializer->serialize($recommendations, 'json', $this->context);
        $response = new Response($json, 200, [
            'Content-Type' => 'application/vnd.elife.recommendations+json;version=1',
        ]);

        $this->validate($response);
    }
}
