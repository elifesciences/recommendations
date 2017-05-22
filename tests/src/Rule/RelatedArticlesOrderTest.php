<?php

namespace eLife\Recommendations\Rule;

use DateTimeImmutable;
use eLife\Recommendations\RuleModel;

class RelatedArticlesOrderTest extends \PHPUnit_Framework_TestCase
{
    private $order;

    public function setUp()
    {
        $this->order = new RelatedArticlesOrder();
    }

    public function test_orders_by_type()
    {
        $retraction = new RuleModel('00001', 'retraction');
        $correction = new RuleModel('00002', 'correction');
        $external = new RuleModel('00003-0', 'external-article');

        $this->assertEquals(
            [
                $retraction,
                $correction,
                $external,
            ],
            $this->order->filter([
                $external,
                $retraction,
                $correction,
            ])
        );
    }

    public function test_orders_by_date_articles_of_the_same_type()
    {
        $firstInsight = new RuleModel('00001', 'insight', new DateTimeImmutable('2017-01-01'));
        $secondInsight = new RuleModel('00002', 'insight', new DateTimeImmutable('2017-02-01'));

        $firstExternal = new RuleModel('00003', 'external-article');
        $secondExternal = new RuleModel('00004', 'external-article');

        $this->assertEquals(
            [
                $firstExternal,
                $secondExternal,
                $secondInsight,
                $firstInsight,
            ],
            $this->order->filter([
                $firstInsight,
                $secondInsight,
                $firstExternal,
                $secondExternal,
            ])
        );
    }
}
