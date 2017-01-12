<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\Collection;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;

final class CollectionContents implements Rule
{
    use PersistRule;
    use RepoRelations;

    private $sdk;
    private $repo;

    public function __construct(ApiSdk $sdk, RuleModelRepository $repo)
    {
        $this->sdk = $sdk;
        $this->repo = $repo;
    }

    public function getCollection(string $id)
    {
        return $this->sdk->collections()->get($id)->wait(true);
    }

    public function resolveRelations(RuleModel $input): array
    {
        if ($input->getType() !== 'collection') {
            return [];
        }
        $collection = $this->getCollection($input->getId());

        return $collection->getContent()
            ->filter(function ($item) {
                return $item instanceof ArticleVersion;
            })
            ->map(function (ArticleVersion $article) use ($input) {
                // Add collection TO article.
                return new ManyToManyRelationship(new RuleModel($article->getId(), $article->getType(), $article->getPublishedDate()), $input);
            })
            ->toArray();
    }

    public function supports(): array
    {
        return [
            'collection',
        ];
    }
}
