<?php

namespace eLife\Tests\Process;

use eLife\Recommendations\Process\Rules;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use eLife\Recommendations\Rule;
use eLife\Recommendations\RuleModel;
use LogicException;
use Mockery;
use PHPUnit_Framework_TestCase;

final class RulesTest extends PHPUnit_Framework_TestCase
{
    private $rule1;
    private $rule2;
    private $rule_empty;

    public function setUp()
    {
        $this->rule1 = Mockery::mock(Rule::class);
        $this->rule1
            ->shouldReceive('addRelations')
            ->andReturnUsing(function ($_, $in) {
                return array_merge($in, [['type' => 'podcast-episode', 'id' => 1]]);
            });
        $this->rule1
            ->shouldReceive('supports')
            ->andReturn(['podcast-episode']);
        $this->rule2 = Mockery::mock(Rule::class);
        $this->rule2
            ->shouldReceive('supports')
            ->andReturn(['article']);
        $this->rule2
            ->shouldReceive('addRelations')
            ->andReturnUsing(function ($_, $in) {
                return array_merge($in, [['type' => 'article', 'id' => 2]]);
            });
        $this->rule_empty = Mockery::mock(Rule::class);
    }

    public function testAggregatingAddRelations()
    {
        $rules = new Rules($this->rule1, $this->rule2);
        $actual = $rules->getRecommendations(new RuleModel('1', 'article'));
        $this->assertEquals([
            [
                'type' => 'podcast-episode',
                'id' => 1,
            ],
            [
                'type' => 'article',
                'id' => 2,
            ],
        ], $actual);
    }

    public function testImport()
    {
        $this->rule2
            ->shouldReceive('resolveRelations')
            ->andReturn([new ManyToManyRelationship(new RuleModel('2', 'article'), new RuleModel('1', 'article'))])
            ->once();
        $this->rule2
            ->shouldReceive('upsert')
            ->andReturn(null)
            ->once();
        $rules = new Rules($this->rule2);
        $rules->import(new RuleModel('1', 'article'), true);
    }

    public function testEmpty()
    {
        $this->rule_empty
            ->shouldReceive('supports')
            ->andReturn([]);
        $this->rule_empty
            ->shouldReceive('resolveRelations')
            ->never();
        $this->rule_empty
            ->shouldReceive('upsert')
            ->never();
        $rules = new Rules($this->rule_empty);
        $rules->import(new RuleModel('1', 'not-article'), true);
    }

    public function testImportWithoutUpsert()
    {
        $this->rule2
            ->shouldReceive('resolveRelations')
            ->andReturn([new ManyToManyRelationship(new RuleModel('2', 'article'), new RuleModel('1', 'article'))])
            ->once();
        $this->rule2
            ->shouldReceive('upsert')
            ->never();
        $rules = new Rules($this->rule2);
        $rules->import(new RuleModel('1', 'article'), false);
    }

    public function testImportWithoutUpsertAndPruneWillFail()
    {
        $this->rule2
            ->shouldReceive('supports')
            ->andReturn(['article']);
        $this->expectException('PHPUnit_Framework_Error_Warning');
        $rules = new Rules($this->rule2);
        $rules->import(new RuleModel('1', 'article'), false, true);
    }
}
