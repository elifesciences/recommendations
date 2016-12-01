<?php

namespace eLife\Recommendations\Response;

use eLife\Api\Response\Snippet;
use JMS\Serializer\Annotation as Serializer;

/**
 * @todo add discrimination fields (see search)
 * @Serializer\Discriminator()
 */
interface Article extends Snippet
{
}
