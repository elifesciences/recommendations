<?php
/**
 * Rule Model.
 *
 * General purpose DTO representing items prior to hydration. Optional date time for sorting and
 * synthetic to indicate that it cannot be hydrated further.
 */

namespace eLife\Recommendations;

use DateTimeImmutable;

class RuleModel
{
    private $id;
    private $type;
    private $isSynthetic;
    private $published;

    public function __construct(string $id, string $type, DateTimeImmutable $published = null, bool $isSynthetic = false)
    {
        $this->id = $id;
        $this->type = $type;
        $this->isSynthetic = $isSynthetic;
        $this->published = $published;
    }

    /**
     * Synthetic check.
     *
     * This will return whether or not the item is retrievable from
     * the API SDK. If it is synthetic, the data will have to be
     * retrieved from another, local, data source.
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

    public function getPublished(): DateTimeImmutable
    {
        return $this->published;
    }
}
