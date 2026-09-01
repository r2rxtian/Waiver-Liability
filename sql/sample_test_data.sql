USE [LRNPH_OJT];
SET NOCOUNT ON;
SET XACT_ABORT ON;

/*
  Non-production test data for the Employee Waiver of Liability system.
  Safe to rerun: departments, employees, and users are checked before insert.

  Temporary password for every test account: WaiverTest2026!
  Remove or deactivate these accounts before loading production user data.
*/

IF OBJECT_ID('dbo.acd_mw_departments','U') IS NULL
   OR OBJECT_ID('dbo.acd_mw_employees','U') IS NULL
   OR OBJECT_ID('dbo.acd_mw_roles','U') IS NULL
   OR OBJECT_ID('dbo.acd_mw_users','U') IS NULL
    THROW 51100, 'Required project tables are missing. Run schema/migration and seed scripts first.', 1;

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='SYSTEM ADMIN')
   OR NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='CLINIC NURSE')
   OR NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='SUPERVISOR')
   OR NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='HR / CLINIC ADMIN')
   OR NOT EXISTS(SELECT 1 FROM dbo.acd_mw_roles WHERE role_name='EMPLOYEE')
    THROW 51101, 'Required roles are missing. Run sql/seed.sql first.', 1;

BEGIN TRANSACTION;

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_departments WHERE department_code='PROD')
    INSERT dbo.acd_mw_departments(department_code,department_name,is_active,created_at) VALUES('PROD',N'Production',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_departments WHERE department_code='QA')
    INSERT dbo.acd_mw_departments(department_code,department_name,is_active,created_at) VALUES('QA',N'Quality Assurance',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_departments WHERE department_code='WH')
    INSERT dbo.acd_mw_departments(department_code,department_name,is_active,created_at) VALUES('WH',N'Warehouse',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_departments WHERE department_code='LOG')
    INSERT dbo.acd_mw_departments(department_code,department_name,is_active,created_at) VALUES('LOG',N'Logistics',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_departments WHERE department_code='MNT')
    INSERT dbo.acd_mw_departments(department_code,department_name,is_active,created_at) VALUES('MNT',N'Maintenance',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_departments WHERE department_code='HR')
    INSERT dbo.acd_mw_departments(department_code,department_name,is_active,created_at) VALUES('HR',N'Human Resources',1,SYSDATETIME());

DECLARE @prod INT=(SELECT department_id FROM dbo.acd_mw_departments WHERE department_code='PROD');
DECLARE @qa INT=(SELECT department_id FROM dbo.acd_mw_departments WHERE department_code='QA');
DECLARE @warehouse INT=(SELECT department_id FROM dbo.acd_mw_departments WHERE department_code='WH');
DECLARE @logistics INT=(SELECT department_id FROM dbo.acd_mw_departments WHERE department_code='LOG');
DECLARE @maintenance INT=(SELECT department_id FROM dbo.acd_mw_departments WHERE department_code='MNT');
DECLARE @hr INT=(SELECT department_id FROM dbo.acd_mw_departments WHERE department_code='HR');

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00218')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00218',N'Juan Dela Cruz',@prod,N'Machine Operator','juan.delacruz.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00241')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00241',N'Maria Angela Santos',@hr,N'HR Coordinator','maria.santos.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00307')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00307',N'Carlo Miguel Reyes',@prod,N'Line Leader','carlo.reyes.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00319')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00319',N'Anne Patricia Lim',@qa,N'Quality Inspector','anne.lim.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00342')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00342',N'Ramon Bautista',@warehouse,N'Warehouse Associate','ramon.bautista.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00365')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00365',N'Joanna Mae Villanueva',@logistics,N'Dispatch Coordinator','joanna.villanueva.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00388')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00388',N'Enrico Mendoza',@maintenance,N'Maintenance Technician','enrico.mendoza.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00402')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00402',N'Camille Garcia',@qa,N'Food Safety Analyst','camille.garcia.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00426')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00426',N'Paolo Navarro',@prod,N'Packaging Operator','paolo.navarro.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00451')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00451',N'Lea Dominique Flores',@warehouse,N'Inventory Clerk','lea.flores.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00473')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00473',N'Noel Castillo',@logistics,N'Shuttle Driver','noel.castillo.test@la-rose-noire.com',1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_employees WHERE employee_number='EMP-00490')
 INSERT dbo.acd_mw_employees(employee_number,full_name,department_id,position,email,is_active,created_at) VALUES('EMP-00490',N'Kristine Aquino',@prod,N'Production Associate','kristine.aquino.test@la-rose-noire.com',0,SYSDATETIME());

DECLARE @password_hash VARCHAR(255)='$2y$10$Za14RSdvAvE24ombUV1Je.twnszlI2bD.fNB310G8Cp1iJm0tyvCW';
DECLARE @admin_role INT=(SELECT role_id FROM dbo.acd_mw_roles WHERE role_name='SYSTEM ADMIN');
DECLARE @nurse_role INT=(SELECT role_id FROM dbo.acd_mw_roles WHERE role_name='CLINIC NURSE');
DECLARE @supervisor_role INT=(SELECT role_id FROM dbo.acd_mw_roles WHERE role_name='SUPERVISOR');
DECLARE @hr_role INT=(SELECT role_id FROM dbo.acd_mw_roles WHERE role_name='HR / CLINIC ADMIN');
DECLARE @employee_role INT=(SELECT role_id FROM dbo.acd_mw_roles WHERE role_name='EMPLOYEE');

IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_users WHERE username='test.admin')
 INSERT dbo.acd_mw_users(username,password_hash,full_name,email,role_id,employee_id,is_active,created_at) VALUES('test.admin',@password_hash,N'Test System Administrator','test.admin@la-rose-noire.com',@admin_role,NULL,1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_users WHERE username='test.nurse')
 INSERT dbo.acd_mw_users(username,password_hash,full_name,email,role_id,employee_id,is_active,created_at) VALUES('test.nurse',@password_hash,N'Nurse Maria Santos','test.nurse@la-rose-noire.com',@nurse_role,(SELECT employee_id FROM dbo.acd_mw_employees WHERE employee_number='EMP-00241'),1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_users WHERE username='test.hr')
 INSERT dbo.acd_mw_users(username,password_hash,full_name,email,role_id,employee_id,is_active,created_at) VALUES('test.hr',@password_hash,N'Maria Angela Santos','test.hr@la-rose-noire.com',@hr_role,(SELECT employee_id FROM dbo.acd_mw_employees WHERE employee_number='EMP-00241'),1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_users WHERE username='test.supervisor')
 INSERT dbo.acd_mw_users(username,password_hash,full_name,email,role_id,employee_id,is_active,created_at) VALUES('test.supervisor',@password_hash,N'Carlo Miguel Reyes','test.supervisor@la-rose-noire.com',@supervisor_role,(SELECT employee_id FROM dbo.acd_mw_employees WHERE employee_number='EMP-00307'),1,SYSDATETIME());
IF NOT EXISTS(SELECT 1 FROM dbo.acd_mw_users WHERE username='test.employee')
 INSERT dbo.acd_mw_users(username,password_hash,full_name,email,role_id,employee_id,is_active,created_at) VALUES('test.employee',@password_hash,N'Juan Dela Cruz','test.employee@la-rose-noire.com',@employee_role,(SELECT employee_id FROM dbo.acd_mw_employees WHERE employee_number='EMP-00218'),1,SYSDATETIME());

COMMIT TRANSACTION;

SELECT employee_number,full_name,position,is_active FROM dbo.acd_mw_employees WHERE employee_number LIKE 'EMP-00%' ORDER BY employee_number;
SELECT username,full_name,is_active FROM dbo.acd_mw_users WHERE username LIKE 'test.%' ORDER BY username;
