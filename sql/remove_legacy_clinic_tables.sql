USE [LRNPH_OJT];
SET NOCOUNT ON;
SET XACT_ABORT ON;

/*
  Approved removal of the obsolete clinic-oriented tables.
  The current waiver-first PHP runtime does not reference these objects.
  This script stops without deleting anything if a table contains data or
  another SQL object depends on it.
*/

DECLARE @Candidates TABLE(object_id INT NULL,table_name SYSNAME NOT NULL);
INSERT @Candidates(object_id,table_name) VALUES
(OBJECT_ID('dbo.acd_mw_medical_assessments','U'),'acd_mw_medical_assessments'),
(OBJECT_ID('dbo.acd_mw_medical_recommendations','U'),'acd_mw_medical_recommendations'),
(OBJECT_ID('dbo.acd_mw_employee_decisions','U'),'acd_mw_employee_decisions'),
(OBJECT_ID('dbo.acd_mw_clinic_visits','U'),'acd_mw_clinic_visits');

IF EXISTS (
    SELECT 1
    FROM @Candidates c
    JOIN sys.partitions p ON p.object_id=c.object_id AND p.index_id IN(0,1)
    GROUP BY c.object_id
    HAVING SUM(p.rows)>0
)
    THROW 51200, 'Removal stopped: at least one legacy table contains data.', 1;

IF EXISTS (
    SELECT 1
    FROM @Candidates c
    JOIN sys.sql_expression_dependencies d ON d.referenced_id=c.object_id
    WHERE c.object_id IS NOT NULL
)
    THROW 51201, 'Removal stopped: a SQL object depends on a legacy table.', 1;

BEGIN TRANSACTION;

IF OBJECT_ID('dbo.acd_mw_medical_assessments','U') IS NOT NULL
    DROP TABLE dbo.acd_mw_medical_assessments;

IF OBJECT_ID('dbo.acd_mw_medical_recommendations','U') IS NOT NULL
    DROP TABLE dbo.acd_mw_medical_recommendations;

IF OBJECT_ID('dbo.acd_mw_employee_decisions','U') IS NOT NULL
    DROP TABLE dbo.acd_mw_employee_decisions;

IF OBJECT_ID('dbo.acd_mw_clinic_visits','U') IS NOT NULL
    DROP TABLE dbo.acd_mw_clinic_visits;

COMMIT TRANSACTION;

SELECT c.table_name,
       CASE WHEN OBJECT_ID('dbo.'+c.table_name,'U') IS NULL THEN 'REMOVED' ELSE 'STILL EXISTS' END removal_status
FROM @Candidates c
ORDER BY c.table_name;
