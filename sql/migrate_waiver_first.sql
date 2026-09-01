USE [LRNPH_OJT];
SET NOCOUNT ON;
SET XACT_ABORT ON;

/*
  One-time migration from the original clinic-oriented project schema to the
  waiver-first schema. This script preserves every table and does not touch
  unrelated database objects.

  Safety rule: the migration stops if any waiver records exist.
*/

IF OBJECT_ID('dbo.acd_mw_waivers', 'U') IS NULL
    THROW 51000, 'dbo.acd_mw_waivers does not exist. Run schema.sql instead.', 1;

IF EXISTS (SELECT 1 FROM dbo.acd_mw_waivers)
    THROW 51001, 'Migration stopped: dbo.acd_mw_waivers contains records.', 1;

BEGIN TRANSACTION;

IF COL_LENGTH('dbo.acd_mw_waivers', 'waiver_type_id') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD waiver_type_id INT NOT NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'waiver_date') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD waiver_date DATE NOT NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'waiver_time') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD waiver_time TIME NOT NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'medical_personnel_name') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD medical_personnel_name NVARCHAR(150) NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'recommendation') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD recommendation NVARCHAR(1000) NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'transportation_offered') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD transportation_offered VARCHAR(100) NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'remarks') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD remarks NVARCHAR(1000) NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'created_by') IS NULL
    ALTER TABLE dbo.acd_mw_waivers ADD created_by INT NOT NULL;

/* Old clinic workflow fields remain for compatibility but are no longer required. */
IF COL_LENGTH('dbo.acd_mw_waivers', 'visit_id') IS NOT NULL
    ALTER TABLE dbo.acd_mw_waivers ALTER COLUMN visit_id INT NULL;

IF COL_LENGTH('dbo.acd_mw_waivers', 'employee_decision') IS NOT NULL
    ALTER TABLE dbo.acd_mw_waivers ALTER COLUMN employee_decision VARCHAR(80) NULL;

IF OBJECT_ID('dbo.acd_mw_waiver_acknowledgments', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.acd_mw_waiver_acknowledgments', 'display_order') IS NULL
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.acd_mw_waiver_acknowledgments)
        THROW 51002, 'Migration stopped: acknowledgment records require backfilling.', 1;

    ALTER TABLE dbo.acd_mw_waiver_acknowledgments
        ADD display_order INT NOT NULL;
END;

IF OBJECT_ID('dbo.acd_mw_waiver_signatures', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.acd_mw_waiver_signatures', 'signer_user_id') IS NULL
BEGIN
    ALTER TABLE dbo.acd_mw_waiver_signatures
        ADD signer_user_id INT NULL;
END;

COMMIT TRANSACTION;

SELECT
    c.column_id,
    c.name AS column_name,
    TYPE_NAME(c.user_type_id) AS data_type,
    c.is_nullable
FROM sys.columns c
WHERE c.object_id = OBJECT_ID('dbo.acd_mw_waivers')
ORDER BY c.column_id;
