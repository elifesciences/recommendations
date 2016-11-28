<?php

namespace eLife\Recommendations\Relationships;

use eLife\Recommendations\Relationship;
use eLife\Recommendations\RuleModel;

final class ManyToManyRelationship implements Relationship
{
    private $on;
    private $subject;

    public function __construct(RuleModel $on, RuleModel $subject)
    {
        $this->on = $on;
        $this->subject = $subject;
    }

    /**
     * Returns the owning side of the relationship.
     */
    public function getOn() : RuleModel
    {
        return $this->on;
    }

    /**
     * Returns the target of the relationship to be placed on the owning side.
     */
    public function getSubject() : RuleModel
    {
        return $this->subject;
    }
}
