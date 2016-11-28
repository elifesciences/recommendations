<?php

namespace eLife\Recommendations\Rule;

class Test
{
    public function testRetractionArticle()
    {
        $this->withRelation('retractions', ['from' => 1, 'to' => 2]);
        $this->withRelation('retractions', ['from' => 1, 'to' => 3]);

        $two = $this->whenIQuery('retractions', ['article', 2]);
        $this->assertEqual([1], $two);

        $one = $this->whenIQuery('retractions', ['article', 1]);
        $this->assertEmpty($one);
    }
}
