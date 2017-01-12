<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\PodcastEpisode;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;

class PodcastEpisodeContents implements Rule
{
    use GetSdk;
    use PersistRule;

    /**
     * @var ApiSdk
     */
    private $sdk;
    /**
     * @var RuleModelRepository
     */
    private $repo;

    public function __construct(ApiSdk $sdk, RuleModelRepository $repo)
    {
        $this->sdk = $sdk;
        $this->repo = $repo;
    }

    /**
     * Resolve Relations.
     *
     * Given a model (type + id) from SQS, calculate which entities need relations added
     * for the specific domain rule.
     *
     * Return is an array of tuples containing an input and an on where `input` is the model to be
     * added and `on` is the target node. In plain english given a podcast containing articles it would
     * return an array where the podcast is every `input` and each article is the `output`.
     */
    public function resolveRelations(RuleModel $input): array
    {
        /** @var PodcastEpisode $model Added to stop IDE complaining @todo create hasSubjects interface. */
        $model = $this->getFromSdk($input->getType(), $input->getId());
        $relations = [];
        foreach ($model->getChapters() as $chapter) {
            $content = $chapter->getContent();
            $relations[] = $content->filter(function ($content) {
                // Only article for now in this rule.
                return $content instanceof ArticleVersion;
            })->map(function (ArticleVersion $article) use ($input) {
                // Link this podcast TO the related item.
                return new ManyToManyRelationship(new RuleModel($article->getId(), $article->getType(), $article->getPublishedDate()), $input);
            })->toArray();
        }

        return array_reduce($relations, 'array_merge', []);
    }

    /**
     * Add relations for model to list.
     *
     * This will be what is used when constructing the recommendations. Given a model (id, type) we return an array
     * of [type, id]'s that will be hydrated into results by the application. The aim is for this function to be
     * as fast as possible given its executed at run-time.
     */
    public function addRelations(RuleModel $model, array $list): array
    {
        return $list;
    }

    protected function getRepository(): RuleModelRepository
    {
        return $this->repo;
    }

    /**
     * Returns item types that are supported by rule.
     */
    public function supports(): array
    {
        return [
            'podcast-episode',
        ];
    }
}
