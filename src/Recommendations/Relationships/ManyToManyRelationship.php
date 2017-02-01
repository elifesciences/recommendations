<?php

namespace eLife\Recommendations\Relationships;

use eLife\Recommendations\Relationship;
use eLife\Recommendations\RuleModel;
use JsonSerializable;
use function GuzzleHttp\json_encode;

final class ManyToManyRelationship implements Relationship, JsonSerializable
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

    public function __toString()
    {
        return json_encode($this);
    }

    public function jsonSerialize()
    {
        return [
            'on' => $this->on,
            'subject' => $this->subject,
        ];
    }
}
