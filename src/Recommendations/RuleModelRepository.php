<?php

namespace eLife\Recommendations;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use eLife\Recommendations\Relationships\ManyToManyRelationship;
use PDO;
use Rhumsaa\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RuleModelRepository
{
    private $db;

    public function __construct(Connection $conn, string $rulesTableName, string $referencesTableName)
    {
        $this->db = $conn;
        $this->ruleTableName = $rulesTableName;
        $this->referencesTableName = $referencesTableName;
    }

    public function mapAll(array $items)
    {
        return array_map([$this, 'map'], $items);
    }

    public function map($item)
    {
        return new RuleModel(
            $item['id'],
            $item['type'],
            new DateTimeImmutable($item['published']),
            $item['isSynthetic'],
            $item['rule_id']
        );
    }

    public function hydrateOne(RuleModel $ruleModel)
    {
        $item = $this->get($ruleModel);
        $ruleModel->setType($item['type']);
        $ruleModel->setRuleId($item['rule_id']);
        $ruleModel->setPublished(new DateTimeImmutable($item['published']));

        return $ruleModel;
    }

    public function getLatestArticle()
    {
        // Research Advance, Research Article, Scientific Correspondence, Short Report, Tools and Resources or Replication Study article
        $prepared = $this->db->prepare('
          SELECT * FROM '.$this->ruleTableName.' as Ru 
          WHERE Ru.type IN (
            "research-advance",
            "research-article",
            "scientific-correspondence",
            "short-report",
            "tools-resources",
            "replication-study"
          )
          ORDER BY Ru.published DESC
          LIMIT 40
        ');
        $prepared->execute();

        foreach ($prepared->fetchAll() as $item) {
            yield $this->map($item);
        }
    }
    public function getLatestArticleWithSubject(string $subject)
    {
        $prepared = $this->db->prepare('
          SELECT onRule.rule_id, onRule.id, onRule.type, onRule.published, onRule.isSynthetic 
          FROM '.$this->referencesTableName.' as Ref 
          JOIN '.$this->ruleTableName.' onRule on Ref.on_id = onRule.rule_id 
          JOIN '.$this->ruleTableName.' subjectRule on Ref.subject_id = subjectRule.rule_id 
          WHERE subjectRule.type=\'subject\' 
          AND subjectRule.id=? 
          ORDER BY onRule.published DESC
        ');
        $prepared->bindValue(1, $subject, PDO::PARAM_STR);
        $prepared->execute();

        foreach ($prepared->fetchAll() as $item) {
            if ($item) {
                yield $this->map($item);
            }
        }
    }

    public function slice(int $offset, int $count)
    {
        $prepared = $this->db->prepare('
          SELECT Ru.rule_id, Ru.id, Ru.type, Ru.published, Ru.isSynthetic 
          FROM '.$this->ruleTableName.' as Ru
          WHERE Ru.type != \'subject\'
          ORDER BY R.published DESC
          LIMIT ? 
          OFFSET ?;
        ');
        $prepared->bindValue(1, $count, PDO::PARAM_INT);
        $prepared->bindValue(2, $offset, PDO::PARAM_INT);
        $prepared->execute();

        return $this->mapAll($prepared->fetchAll());
    }

    public function getAll(RuleModel $ruleModel)
    {
        $model = $this->get($ruleModel);
        $prepared = $this->db->prepare('
          SELECT Ru.rule_id, Ru.id, Ru.type, Ru.published, Ru.isSynthetic 
          FROM '.$this->ruleTableName.' as Ru
          LEFT JOIN  '.$this->referencesTableName.' AS Re ON Ru.rule_id = Re.subject_id
          WHERE Re.on_id = ?
          AND Ru.type != "subject"
          ORDER BY Ru.published DESC;
        ');
        $prepared->bindValue(1, $model['rule_id']);
        $prepared->execute();

        return $this->mapAll($prepared->fetchAll());
    }

    public function getOne(string $id, string $type)
    {
        return $this->map($this->get(new RuleModel($id, $type)));
    }

    public function get(RuleModel $ruleModel)
    {
        if ($ruleModel->getType() === 'article') {
            $prepared = $this->db->prepare('
              SELECT Ru.rule_id, Ru.type, Ru.id, Ru.published, Ru.isSynthetic
              FROM '.$this->ruleTableName.' as Ru 
              WHERE Ru.id = ? 
              AND Ru.type IN (
                "correction",
                "editorial",
                "feature",
                "insight",
                "research-advance",
                "research-article",
                "retraction",
                "registered-report",
                "replication-study",
                "scientific-correspondence",
                "short-report",
                "tools-resources"
              )
              LIMIT 1;
            ');
            $prepared->bindValue(1, $ruleModel->getId());
        } else {
            $prepared = $this->db->prepare('
              SELECT Ru.rule_id, Ru.type, Ru.id, Ru.published, Ru.isSynthetic
              FROM '.$this->ruleTableName.' as Ru 
              WHERE Ru.id = ? 
              AND Ru.type = ? 
              LIMIT 1;
            ');
            $prepared->bindValue(1, $ruleModel->getId());
            $prepared->bindValue(2, $ruleModel->getType());
        }
        $prepared->execute();

        $result = $prepared->fetch();
        if (!$result) {
            throw new NotFoundHttpException('Rule model not found.');
        }

        return $result;
    }

    public function insert(RuleModel $ruleModel)
    {
        $ruleModel->setRuleId(Uuid::uuid4());
        $this->db->insert($this->ruleTableName, [
            'rule_id' => $ruleModel->getRuleId(),
            'id' => $ruleModel->getId(),
            'type' => $ruleModel->getType(),
            'published' => $ruleModel->getPublished() ?? null,
            'isSynthetic' => $ruleModel->isSynthetic() ? 1 : 0,
        ], [
            PDO::PARAM_STR,
            PDO::PARAM_STR,
            PDO::PARAM_STR,
            'datetime',
            'boolean',
        ]);

        return $ruleModel;
    }

    public function upsert(RuleModel $ruleModel): RuleModel
    {
        if ($ruleModel->isFromDatabase()) {
            return $ruleModel;
        }
        try {
            $this->hydrateOne($ruleModel);
        } catch (NotFoundHttpException $e) {
            $this->insert($ruleModel);
        }

        return $ruleModel;
    }

    public function hasRelation(ManyToManyRelationship $relationship)
    {
        $prepared = $this->db->prepare('
              SELECT on_id, subject_id 
              FROM '.$this->referencesTableName.' 
              WHERE on_id = ? 
              AND subject_id = ? 
              LIMIT 1;
        ');
        $prepared->bindValue(1, $relationship->getOn()->getRuleId());
        $prepared->bindValue(2, $relationship->getSubject()->getRuleId());
        $prepared->execute();

        return (bool) $prepared->fetch();
    }

    public function addRelation(ManyToManyRelationship $relationship)
    {
        $on = $this->upsert($relationship->getOn());
        $subject = $this->upsert($relationship->getSubject());
        if (!$this->hasRelation($relationship)) {
            $this->db->insert($this->referencesTableName, [
                'on_id' => $on->getRuleId(),
                'subject_id' => $subject->getRuleId(),
            ], [
                PDO::PARAM_STR,
                PDO::PARAM_STR,
            ]);
        }
    }
}
