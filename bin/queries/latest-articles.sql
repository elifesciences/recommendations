SELECT *
FROM Rules
WHERE Rules.type IN (
    'research-advance',
    'research-article',
    'replication-study',
    'scientific-correspondence',
    'short-report',
    'tools-resources'
)
AND Rules.published IS NOT NULL
ORDER BY Rules.published DESC
