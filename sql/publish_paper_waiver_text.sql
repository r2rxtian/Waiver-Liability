USE [LRNPH_OJT];
SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Publishes the wording supplied from the existing company paper form.
   Historical waiver snapshots are never modified. */
BEGIN TRANSACTION;

DECLARE @unfit_type_id INT = (SELECT waiver_type_id FROM dbo.acd_mw_waiver_types WHERE type_code='UNFIT_TO_WORK');
DECLARE @refused_type_id INT = (SELECT waiver_type_id FROM dbo.acd_mw_waiver_types WHERE type_code='REFUSED_MEDICAL_TREATMENT');

IF @unfit_type_id IS NULL OR @refused_type_id IS NULL
    THROW 51010, 'Waiver types are missing. Run seed.sql first.', 1;

UPDATE dbo.acd_mw_waiver_versions SET status='RETIRED',retired_at=SYSDATETIME()
WHERE waiver_type_id IN (@unfit_type_id,@refused_type_id) AND status='ACTIVE';

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_waiver_versions WHERE waiver_type_id=@unfit_type_id AND version_number='2.0')
INSERT dbo.acd_mw_waiver_versions(waiver_type_id,version_number,waiver_text,status,published_at)
VALUES(@unfit_type_id,'2.0',N'In the event that I cannot continue working physically and psychologically with regards to the medical advisement by the company nurse on duty, I, {{employee_name}} of {{department}} Department, do hereby WAIVE, RELEASE and DISCHARGE from any and all liabilities of the nurse and the management of La Rose Noire upon leaving the company debilitated due to my existing ailment.','ACTIVE',SYSDATETIME());
ELSE
UPDATE dbo.acd_mw_waiver_versions SET status='ACTIVE',published_at=COALESCE(published_at,SYSDATETIME()),retired_at=NULL WHERE waiver_type_id=@unfit_type_id AND version_number='2.0';

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_waiver_versions WHERE waiver_type_id=@refused_type_id AND version_number='2.0')
INSERT dbo.acd_mw_waiver_versions(waiver_type_id,version_number,waiver_text,status,published_at)
VALUES(@refused_type_id,'2.0',N'I, {{employee_name}}, of {{department}} Department/Area acknowledged and fully understood that I have a medical concern which I may need additional medical attention and that La Rose Noire Shuttle service is available to transport me to the nearest hospital. Instead, I elect to seek medical care and refuse further evaluation and treatment. I do hereby waive, release and discharge from any and all liabilities of the nurse and the management of LA ROSE NOIRE upon leaving the company debilitated due to my existing ailment. Furthermore, I acknowledge that this waiver will be used by the company for further references and that it will govern my actions as I take responsibility to it.','ACTIVE',SYSDATETIME());
ELSE
UPDATE dbo.acd_mw_waiver_versions SET status='ACTIVE',published_at=COALESCE(published_at,SYSDATETIME()),retired_at=NULL WHERE waiver_type_id=@refused_type_id AND version_number='2.0';

COMMIT TRANSACTION;

SELECT t.type_code,v.version_number,v.status,v.published_at
FROM dbo.acd_mw_waiver_versions v
JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=v.waiver_type_id
ORDER BY t.type_code,v.waiver_version_id;
