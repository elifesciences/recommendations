<?php


namespace eLife\Recommendations;

use Doctrine\DBAL\Connection;
use eLife\Recommendations\Relationships\ManyToManyRelationship;

class RuleModelRepository
{
    private $db;

    public function __construct(Connection $conn)
    {
        $this->db = $conn;
    }

    public function upsert(RuleModel $ruleModel) : RuleModel
    {

    }

    public function addRelation(ManyToManyRelationship $relationship) {
        $on = $this->upsert($relationship->getOn());
        $subject = $this->upsert($relationship->getSubject());

        $this->db->insert('Relations', [

        ]);
    }

}
