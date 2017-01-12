<?php

namespace eLife\Recommendations;

use eLife\ApiSdk\Model\ArticleVersion;
use eLife\Recommendations\Response\Article;
use eLife\Recommendations\Response\Result;
use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;

final class RecommendationResultDiscriminator implements EventSubscriberInterface
{
    public static $articleTypes = [
        'correction',
        'editorial',
        'feature',
        'insight',
        'research-advance',
        'research-article',
        'research-exchange',
        'retraction',
        'registered-report',
        'replication-study',
        'short-report',
        'tools-resources',
    ];

    public static function getSubscribedEvents()
    {
        return [
            ['event' => Events::PRE_SERIALIZE, 'method' => 'onPreSerialize'],
        ];
    }

    public function onPreSerialize(PreSerializeEvent $event)
    {
        $object = $event->getObject();
        if (is_object($object) && $object instanceof Result) {
            /* @noinspection PhpUndefinedFieldInspection */
            $object->internal_type = $object->getType();
            if ($object instanceof ArticleVersion) {
                /* @noinspection PhpUndefinedFieldInspection */
                $object->internal_type .= '--'.$object->status;
            }
        }
    }
}
