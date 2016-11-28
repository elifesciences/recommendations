<?php

namespace eLife\Recommendations;

/**
 * Relationship.
 *
 * Example:
 * Podcast episodes that contain articles.
 *      - Podcast episode: Subject
 *      - Article: Owner (on)
 */
interface Relationship
{
    /**
     * Returns the owning side of the relationship.
     */
    public function getOn() : RuleModel;

    /**
     * Returns the target of the relationship to be placed on the owning side.
     */
    public function getSubject() : RuleModel;
}
