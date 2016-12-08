<?php

namespace eLife\Recommendations;

use Doctrine\DBAL\Connection;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use Rhumsaa\Uuid\Uuid;

class RuleModelRepository
{
    private $db;

    public function __construct(Connection $conn)
    {
        $this->db = $conn;
    }

    public function get(RuleModel $ruleModel)
    {
        $prepared = $this->db->prepare('SELECT Rules.rule_id FROM Rules WHERE Rules.id = ? AND Rules.type = ? LIMIT 1;');
        $prepared->bindParam(1, $ruleModel->getId());
        $prepared->bindValue(2, $ruleModel->getType());
        $prepared->execute();

        return $prepared->fetch();
    }

    public function insert(RuleModel $ruleModel)
    {
        $ruleModel->setRuleId(Uuid::uuid4());
        $this->db->insert('Rules', [
            'rule_id' => $ruleModel->getRuleId(),
            'id' => $ruleModel->getId(),
            'type' => $ruleModel->getType(),
            'published' => $ruleModel->getPublished() ?? null,
            'isSynthetic' => $ruleModel->isSynthetic() ? 1 : 0,
        ]);

        return $ruleModel;
    }

    public function upsert(RuleModel $ruleModel) : RuleModel
    {
        if ($ruleModel->isFromDatabase()) {
            return $ruleModel;
        }
        $dataModel = $this->get($ruleModel);
        if ($dataModel) {
            $ruleModel->setRuleId($dataModel['rule_id']);
        } else {
            $this->insert($ruleModel);
        }

        return $ruleModel;
    }

    public function hasRelation(ManyToManyRelationship $relationship)
    {
        $prepared = $this->db->prepare('SELECT on_id, subject_id FROM `References` WHERE on_id = ? AND subject_id = ? LIMIT 1;');
        $prepared->bindParam(1, $relationship->getOn()->getRuleId());
        $prepared->bindValue(2, $relationship->getSubject()->getRuleId());
        $prepared->execute();

        return (bool) $prepared->fetch();
    }

    public function addRelation(ManyToManyRelationship $relationship)
    {
        $on = $this->upsert($relationship->getOn());
        $subject = $this->upsert($relationship->getSubject());
        if (!$this->hasRelation($relationship)) {
            $this->db->insert($this->db->quoteIdentifier('References'), [
                'on_id' => $on->getRuleId(),
                'subject_id' => $subject->getRuleId(),
            ]);
        }
    }
}
