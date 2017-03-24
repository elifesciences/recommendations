<?php

namespace eLife\App;

use eLife\ApiClient\MediaType;
use eLife\Recommendations\Process\Hydration;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RecommendationsResponse;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Throwable;

final class DefaultController
{
    private $rules;
    private $logger;

    const MEDIA_TYPE = 'application/vnd.elife.recommendations+json';
    const CURRENT_VERSION = 1;
    const MAX_VERSION = 1;

    public function __construct(
        Rules $rules,
        Hydration $hydrator,
        Serializer $serializer,
        RuleModelRepository $repo,
        LoggerInterface $logger
    ) {
        $this->rules = $rules;
        $this->hydrator = $hydrator;
        $this->serializer = $serializer;
        $this->context = new SerializationContext();
        $this->repo = $repo;
        $this->logger = $logger;
    }

    public function acceptableRequest(Request $request)
    {
        $contentType = $request->headers->get('Accept');
        if ($contentType === 'application/json' || $contentType === '*/*') {
            $mediaType = new MediaType(self::MEDIA_TYPE, self::CURRENT_VERSION);
        } else {
            try {
                $mediaType = MediaType::fromString($contentType);
            } catch (Throwable $e) {
                $this->logger->notice('User provided malformed content type', [
                    'request' => $request,
                    'content-type' => $contentType,
                ]);
                throw new NotAcceptableHttpException('Not acceptable');
            }
            if ($mediaType->getType() !== self::MEDIA_TYPE || $mediaType->getVersion() > self::MAX_VERSION) {
                $this->logger->notice('User requested unknown media type or too high a version', [
                    'request' => $request,
                    'version' => $mediaType->getVersion(),
                    'content-type' => $mediaType->getType(),
                ]);
                throw new NotAcceptableHttpException('Not acceptable');
            }
        }

        return $mediaType;
    }

    public function allAction(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per-page', 100);
        $offset = ($page - 1) * $perPage;

        $mediaType = $this->acceptableRequest($request);
        $version = $mediaType->getVersion() || self::CURRENT_VERSION;
        $recommendations = $this->repo->slice($offset, $perPage);
        $items = $this->hydrator->hydrateAll($recommendations);
        $this->context->setVersion($version);
        $json = $this->serializer->serialize(RecommendationsResponse::fromModels($items, count($items)), 'json', $this->context);

        return new Response($json, 200, [
            'Content-Type' => (string) (new MediaType(self::MEDIA_TYPE, $version)),
        ]);
    }

    public function indexAction(Request $request, string $type, string $id)
    {
        $requestModel = new RuleModel($id, $type);
        $mediaType = $this->acceptableRequest($request);
        $version = $mediaType->getVersion() || self::CURRENT_VERSION;
        // This is meant to be an optimisation, but due to lack of cache elsewhere it currently slows it down.
        //$this->hydrator->extractRelatedFrom($requestModel);
        $recommendations = $this->rules->getRecommendations($requestModel);
        $items = $this->hydrator->hydrateAll($recommendations);
        $this->context->setVersion($version);
        $json = $this->serializer->serialize(RecommendationsResponse::fromModels($items, count($items)), 'json', $this->context);

        return new Response($json, 200, [
            'Content-Type' => (string) (new MediaType(self::MEDIA_TYPE, $version)),
        ]);
    }

    public function pingAction()
    {
        return new Response(
            'pong',
            200,
            [
                'Cache-Control' => 'must-revalidate, no-cache, no-store, private',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]
        );
    }
}
