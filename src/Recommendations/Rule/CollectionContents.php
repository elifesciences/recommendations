<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\Model\Article;
use eLife\ApiSdk\Model\ArticleVersion;
use eLife\ApiSdk\Model\Collection;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\Rule\Common\MicroSdk;
use eLife\Recommendations\Rule\Common\PersistRule;
use eLife\Recommendations\Rule\Common\RepoRelations;
use eLife\Recommendations\RuleModel;
use eLife\Recommendations\RuleModelRepository;

final class CollectionContents implements Rule
{
    use PersistRule;
    use RepoRelations;
    use RuleModelLogger;

    private $sdk;
    private $repo;

    public function __construct(MicroSdk $sdk, RuleModelRepository $repo)
    {
        $this->sdk = $sdk;
        $this->repo = $repo;
    }

    /** @return Collection */
    public function getCollection(string $id)
    {
        return $this->sdk->get('collection', $id);
    }

    public function resolveRelations(RuleModel $input): array
    {
        if ($input->getType() !== 'collection') {
            $this->error($input, 'Invalid rule model, should never happen.');

            return [];
        }
        $collection = $this->getCollection($input->getId());
        $contents = $collection->getContent();
        $this->debug($input, sprintf('Found (%s) piece(s) of content in collection', $contents->count()));

        return $contents
            ->filter(function ($item) use ($input) {
                $isArticle = $item instanceof Article;
                if (!$isArticle) {
                    $this->debug($input, sprintf('Skipping non-article of type %s', get_class($item)));
                }

                return $isArticle;
            })
            ->map(function (Article $article) use ($input) {
                $id = $article->getId();
                $type = $article->getType();
                $date = $article instanceof ArticleVersion ? $article->getPublishedDate() : null;
                $relationship = new ManyToManyRelationship(new RuleModel($id, $type, $date), $input);
                $this->debug($input, sprintf('Found article in content %s<%s>', $type, $id), [
                    'relationship' => $relationship,
                    'article' => $article,
                ]);

                return $relationship;
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
