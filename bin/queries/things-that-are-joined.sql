SELECT
  Rules.rule_id,
  Rules.id,
  Rules.type,
  Rules.published,
  Rules.isSynthetic,
  R.on_id
FROM Rules
  LEFT JOIN `References` AS R ON Rules.rule_id = R.subject_id
ORDER BY Rules.published;
