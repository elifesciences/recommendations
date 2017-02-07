SELECT *
FROM Rules
WHERE Rules.type != 'subject' AND Rules.type != 'collection' AND Rules.type != 'podcast-episode'
ORDER BY Rules.published DESC
