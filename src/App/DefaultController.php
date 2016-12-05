<?php

namespace eLife\App;

use eLife\Recommendations\Process\Hydration;
use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\RuleModel;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;

final class DefaultController
{
    private $rules;

    public function __construct(Rules $rules, Hydration $hydrator, Serializer $serializer)
    {
        $this->rules = $rules;
        $this->hydrator = $hydrator;
        $this->serializer = $serializer;
    }

    public function indexAction(Request $request)
    {
        $id = $request->get('id');
        $recommedations = $this->rules->getRecommendations(new RuleModel($id, 'research-article'));
        $items = $this->hydrator->hydrateAll($recommedations);
    }
}
