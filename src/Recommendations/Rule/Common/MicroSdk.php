<?php

namespace eLife\Recommendations\Rule\Common;

use eLife\ApiSdk\ApiSdk;
use eLife\Bus\Queue\SingleItemRepository;
use LogicException;

class MicroSdk implements SingleItemRepository
{
    private $sdk;

    public function __construct(ApiSdk $sdk)
    {
        $this->sdk = $sdk;
    }

    public function getRelatedArticles($id)
    {
        return $this->sdk->articles()->getRelatedArticles($id);
    }

    public function get(string $type, string $id)
    {
        if (!isset($this->sdk) || !$this->sdk instanceof ApiSdk) {
            throw new LogicException('ApiSDK field does not exist on this class: '.get_class($this));
        }

        return $this
            ->getSdk($type)
            ->get($id)
            ->wait(true);
    }

    public function getSdk($type)
    {
        if (!isset($this->sdk) || !$this->sdk instanceof ApiSdk) {
            throw new LogicException('ApiSDK field does not exist on this class: '.get_class($this));
        }
        switch ($type) {
            case 'blog-article':
                return $this->sdk->blogArticles();
                break;

            case 'event':
                return $this->sdk->events();
                break;

            case 'interview':
                return $this->sdk->interviews();
                break;

            case 'labs-experiment':
                return $this->sdk->labsExperiments();
                break;

            case 'podcast-episode':
                return $this->sdk->podcastEpisodes();
                break;

            case 'collection':
                return $this->sdk->collections();
                break;

            // are these needed?
            case 'correction':
            case 'editorial':
            case 'feature':
            case 'insight':
            case 'research-advance':
            case 'research-article':
            case 'retraction':
            case 'registered-report':
            case 'replication-study':
            case 'scientific-correspondence':
            case 'short-report':
            case 'tools-resources':
            // end of -- are these needed?

            case 'article':
                return $this->sdk->articles();
                break;

            default:
                throw new LogicException('ApiSDK does not exist for provided type: '.$type);
        }
    }
}
