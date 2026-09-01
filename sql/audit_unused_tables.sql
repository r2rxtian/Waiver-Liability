USE [LRNPH_OJT];
SET NOCOUNT ON;

/* Read-only audit. This script does not delete or modify any object. */
DECLARE @Candidates TABLE(table_name SYSNAME,reason NVARCHAR(300));
INSERT @Candidates(table_name,reason) VALUES
('acd_mw_clinic_visits',N'Legacy clinic workflow; no current PHP runtime references'),
('acd_mw_medical_assessments',N'Legacy clinic workflow; no current PHP runtime references'),
('acd_mw_medical_recommendations',N'Legacy clinic workflow; no current PHP runtime references'),
('acd_mw_employee_decisions',N'Legacy clinic workflow; no current PHP runtime references');

SELECT c.table_name,c.reason,
       CASE WHEN t.object_id IS NULL THEN 'DOES_NOT_EXIST' ELSE 'EXISTS' END object_status,
       COALESCE(SUM(p.rows),0) row_count
FROM @Candidates c
LEFT JOIN sys.tables t ON t.name=c.table_name AND t.schema_id=SCHEMA_ID('dbo')
LEFT JOIN sys.partitions p ON p.object_id=t.object_id AND p.index_id IN(0,1)
GROUP BY c.table_name,c.reason,t.object_id
ORDER BY c.table_name;

SELECT c.table_name,
       OBJECT_SCHEMA_NAME(d.referencing_id)+'.'+OBJECT_NAME(d.referencing_id) referencing_object,
       o.type_desc referencing_object_type
FROM @Candidates c
JOIN sys.tables t ON t.name=c.table_name AND t.schema_id=SCHEMA_ID('dbo')
JOIN sys.sql_expression_dependencies d ON d.referenced_id=t.object_id
JOIN sys.objects o ON o.object_id=d.referencing_id
ORDER BY c.table_name,referencing_object;

-- A candidate is eligible for a separately approved DROP only when its row
-- count is zero and the dependency result set contains no rows for it.
