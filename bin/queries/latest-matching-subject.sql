SELECT
  onRule.type,
  onRule.id,
  onRule.published,
  subjectRule.id,
  subjectRule.type
FROM `References` AS Ref
  JOIN `Rules` onRule ON Ref.on_id = onRule.rule_id
  JOIN `Rules` subjectRule ON Ref.subject_id = subjectRule.rule_id
WHERE subjectRule.type = 'subject' AND subjectRule.id = 'REPLACE_WITH_SUBJECT_ID (e.g. epidemiology-global-health)'
ORDER BY onRule.published DESC
