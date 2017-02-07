SELECT
  onRule.type,
  onRule.id,
  subjectRule.id,
  subjectRule.type
FROM `References` AS Ref
  JOIN `Rules` onRule ON Ref.on_id = onRule.rule_id
  JOIN `Rules` subjectRule ON Ref.subject_id = subjectRule.rule_id
WHERE subjectRule.type != 'subject'
