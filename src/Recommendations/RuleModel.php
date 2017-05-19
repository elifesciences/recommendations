<?php
/**
 * Rule Model.
 *
 * General purpose DTO representing items prior to hydration. Optional date time for sorting and
 * synthetic to indicate that it cannot be hydrated further.
 */

namespace eLife\Recommendations;

use DateTimeImmutable;
use JsonSerializable;

final class RuleModel implements JsonSerializable
{
    private $rule_id;
    private $id;
    private $type;
    private $isSynthetic;
    private $published;

    public static function synthetic(string $id, string $type)
    {
        return new self($id, $type, $published = null, $isSynthetic = true);
    }

    public function __construct(string $id, string $type, DateTimeImmutable $published = null, bool $isSynthetic = false, string $rule_id = null)
    {
        $this->id = $id;
        $this->type = $type;
        $this->isSynthetic = $isSynthetic;
        $this->published = $published;
        $this->rule_id = $rule_id;
    }

    public function setType(string $type)
    {
        $this->type = $type;
    }

    public function setRuleId(string $id)
    {
        $this->rule_id = $id;
    }

    public function isFromDatabase(): bool
    {
        return (bool) $this->rule_id;
    }

    public function getRuleId(): string
    {
        return $this->rule_id;
    }

    /**
     * Synthetic check.
     *
     * This will return whether or not the item is retrievable from
     * the locally cached API SDK, as it came through from the bus
     * and was retrieved in background.
     *
     * If it is synthetic, the data will have to be retrieved from:
     * - its own API SDK, but it will be a cache miss initially (e.g. subjects)
     * - the API SDK of another type (e.g. podcast episode chapters or external articles)
     */
    public function isSynthetic(): bool
    {
        return $this->isSynthetic;
    }

    /**
     * Returns the ID or Number of item.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the type of item.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function getPublished()
    {
        return $this->published;
    }

    public function __toString()
    {
        return json_encode($this);
    }

    public function equalTo(RuleModel $model)
    {
        return $model->getId() === $this->getId() && $model->getType() === $this->getType();
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'published' => $this->published,
            'isSynthetic' => $this->isSynthetic,
        ];
    }

    public function setPublished(DateTimeImmutable $published)
    {
        $this->published = $published;
    }
}
