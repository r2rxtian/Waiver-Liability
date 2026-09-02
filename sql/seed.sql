USE [LRNPH_OJT];
SET NOCOUNT ON;

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='SYSTEM ADMIN') INSERT dbo.acd_mw_roles(role_name,description) VALUES('SYSTEM ADMIN','Full system administration');
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='CLINIC NURSE') INSERT dbo.acd_mw_roles(role_name,description) VALUES('CLINIC NURSE','Clinical workflow access');
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='SUPERVISOR') INSERT dbo.acd_mw_roles(role_name,description) VALUES('SUPERVISOR','Supervisor acknowledgment access');
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='HR / CLINIC ADMIN') INSERT dbo.acd_mw_roles(role_name,description) VALUES('HR / CLINIC ADMIN','Reporting and template administration');
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='EMPLOYEE') INSERT dbo.acd_mw_roles(role_name,description) VALUES('EMPLOYEE','Own waiver review and signature');

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_waiver_types WHERE type_code='UNFIT_TO_WORK') INSERT dbo.acd_mw_waiver_types(type_code,type_name,description) VALUES('UNFIT_TO_WORK','UNFIT TO WORK','Employee continues working contrary to nurse recommendation');
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_waiver_types WHERE type_code='REFUSED_MEDICAL_TREATMENT') INSERT dbo.acd_mw_waiver_types(type_code,type_name,description) VALUES('REFUSED_MEDICAL_TREATMENT','REFUSED MEDICAL TREATMENT','Employee refuses recommended medical action');

-- Wording transcribed from the supplied company paper waiver form.
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_waiver_versions v JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=v.waiver_type_id WHERE t.type_code='UNFIT_TO_WORK' AND v.status='ACTIVE')
 INSERT dbo.acd_mw_waiver_versions(waiver_type_id,version_number,waiver_text,status,published_at) SELECT waiver_type_id,'1.0',N'In the event that I cannot continue working physically and psychologically with regards to the medical advisement by the company nurse on duty, I, {{employee_name}} of {{department}} Department, do hereby WAIVE, RELEASE and DISCHARGE from any and all liabilities of the nurse and the management of La Rose Noire upon leaving the company debilitated due to my existing ailment.', 'ACTIVE',SYSDATETIME() FROM dbo.acd_mw_waiver_types WHERE type_code='UNFIT_TO_WORK';
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_waiver_versions v JOIN dbo.acd_mw_waiver_types t ON t.waiver_type_id=v.waiver_type_id WHERE t.type_code='REFUSED_MEDICAL_TREATMENT' AND v.status='ACTIVE')
 INSERT dbo.acd_mw_waiver_versions(waiver_type_id,version_number,waiver_text,status,published_at) SELECT waiver_type_id,'1.0',N'I, {{employee_name}}, of {{department}} Department/Area acknowledged and fully understood that I have a medical concern which I may need additional medical attention and that La Rose Noire Shuttle service is available to transport me to the nearest hospital. Instead, I elect to seek medical care and refuse further evaluation and treatment. I do hereby waive, release and discharge from any and all liabilities of the nurse and the management of LA ROSE NOIRE upon leaving the company debilitated due to my existing ailment. Furthermore, I acknowledge that this waiver will be used by the company for further references and that it will govern my actions as I take responsibility to it.', 'ACTIVE',SYSDATETIME() FROM dbo.acd_mw_waiver_types WHERE type_code='REFUSED_MEDICAL_TREATMENT';

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_settings WHERE setting_key='APP_BASE_URL') INSERT dbo.acd_mw_settings(setting_key,setting_value,updated_at) VALUES('APP_BASE_URL',NULL,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_settings WHERE setting_key='SIGNING_TOKEN_MINUTES') INSERT dbo.acd_mw_settings(setting_key,setting_value,updated_at) VALUES('SIGNING_TOKEN_MINUTES','10',SYSDATETIME());

-- Create users with scripts/create-user.php so password_hash() is always used.
