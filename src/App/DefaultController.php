<?php

namespace eLife\App;

use eLife\ApiClient\MediaType;
use eLife\Recommendations\Process\Hydration;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RecommendationsResponse;
use eLife\Recommendations\RuleModel;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Throwable;

final class DefaultController
{
    private $rules;

    const MEDIA_TYPE = 'application/vnd.elife.recommendations+json';
    const CURRENT_VERSION = 1;
    const MAX_VERSION = 1;

    public function __construct(Rules $rules, Hydration $hydrator, Serializer $serializer)
    {
        $this->rules = $rules;
        $this->hydrator = $hydrator;
        $this->serializer = $serializer;
        $this->context = new SerializationContext();
    }

    public function acceptableResponse(string $contentType)
    {
        if ($contentType === 'application/json') {
            $mediaType = new MediaType(self::MEDIA_TYPE, self::CURRENT_VERSION);
        } else {
            try {
                $mediaType = MediaType::fromString($contentType);
            } catch (Throwable $e) {
                throw new NotAcceptableHttpException('Not acceptable');
            }
            if ($mediaType->getType() !== self::MEDIA_TYPE || $mediaType->getVersion() > self::MAX_VERSION) {
                throw new NotAcceptableHttpException('Not acceptable');
            }
        }

        return $mediaType;
    }

    public function indexAction(Request $request, string $type, string $id)
    {
        $mediaType = $this->acceptableResponse($request->headers->get('Accept'));
        $version = $mediaType->getVersion() || self::CURRENT_VERSION;
        $recommendations = $this->rules->getRecommendations(new RuleModel($id, $type));
        $items = $this->hydrator->hydrateAll($recommendations);
        $this->context->setVersion($version);
        $json = $this->serializer->serialize(RecommendationsResponse::fromModels($items, count($items)), 'json', $this->context);

        return new Response($json, 200, [
            'Content-Type' => (string) (new MediaType(self::MEDIA_TYPE, $version)),
        ]);
    }
}
