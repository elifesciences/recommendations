SELECT
  onRule.rule_id,
  onRule.type,
  onRule.id,
  subjectRule.id,
  subjectRule.type
FROM `Relations` AS Ref
  JOIN `Rules` onRule ON Ref.on_id = onRule.rule_id
  JOIN `Rules` subjectRule ON Ref.subject_id = subjectRule.rule_id
