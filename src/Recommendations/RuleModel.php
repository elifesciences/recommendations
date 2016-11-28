<?php

namespace eLife\Recommendations;

class RuleModel
{
    private $id;
    private $type;
    private $isSynthetic;

    public function __construct(string $id, string $type, bool $isSynthetic = false)
    {
        $this->id = $id;
        $this->type = $type;
        $this->isSynthetic = $isSynthetic;
    }

    /**
     * Synthetic check.
     *
     * This will return whether or not the item is retrievable from
     * the API SDK. If it is synthetic, the data will have to be
     * retrieved from another, local, data source.
     */
    public function isSynthetic() : bool
    {
        return $this->isSynthetic;
    }

    /**
     * Returns the ID or Number of item.
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Returns the type of item.
     */
    public function getType() : string
    {
        return $this->type;
    }
}
