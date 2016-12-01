<?php

namespace eLife\Recommendations\Rule;

use eLife\ApiSdk\ApiSdk;
use JMS\Serializer\Exception\LogicException;

trait GetSdk
{
    public function getFromSdk(string $type, string $id)
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

            case 'research-article':
                return $this->sdk->articles();
                break;

            default:
                throw new LogicException('ApiSDK does not exist for that type.');
        }
    }
}
