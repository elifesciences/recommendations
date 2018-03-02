<?php

namespace eLife\Recommendations;

use eLife\ApiSdk\Collection\Sequence;
use eLife\ApiSdk\Model\ExternalArticle;
use eLife\ApiSdk\Model\Model;
use eLife\ApiSdk\Model\PodcastEpisodeChapterModel;

function append_if_not_exists(Sequence $sequence, Sequence $toInsert, int $max = null) : Sequence
{
    $appended = 0;

    foreach ($toInsert as $newItem) {
        foreach ($sequence as $item) {
            if (equal_models($newItem, $item)) {
                continue 2;
            }
        }

        ++$appended;
        $sequence = $sequence->append($newItem);

        if ($max && $max === $appended) {
            break;
        }
    }

    return $sequence;
}

function equal_models(Model $a, Model $b) : bool
{
    return
        get_class($a) === get_class($b)
        &&
        (
            ($a instanceof ExternalArticle && $a->getId() === $b->getId())
            ||
            ($a instanceof PodcastEpisodeChapterModel && $a->getEpisode()->getNumber() === $b->getEpisode()->getNumber() && $a->getChapter()->getNumber() === $b->getChapter()->getNumber())
            ||
            $a->getIdentifier() == $b->getIdentifier()
        );
}
