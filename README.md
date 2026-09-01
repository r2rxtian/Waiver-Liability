# Carepath Medical Waivers

Internal PHP 8 + Microsoft SQL Server application focused on creating, signing, finalizing, and retaining employee liability waivers.

## Setup

1. Confirm PHP has `pdo_sqlsrv` enabled. The app reuses `../QRS_new/conn/db.php`; credentials are not copied into this project.
2. Review and run `sql/schema.sql` against `LRNPH_OJT`, then run `sql/seed.sql`. Both scripts are idempotent and touch only `dbo.acd_mw_*` tables. They contain no foreign keys.
3. Create the first account from a terminal:

   `php scripts/create-user.php admin "a-strong-password" "System Administrator" "SYSTEM ADMIN"`

4. Add departments and employees to the project-prefixed tables, then open `/Waiver-Liability/`.

The seeded waiver wording is transcribed from the supplied La Rose Noire paper form.

## Revision note

The primary workflow no longer uses clinic visits, assessments, recommendations, or employee-decision records. Existing `dbo.acd_mw_*` clinic tables, if previously created, are intentionally left untouched.

The revised fresh-install `acd_mw_waivers` definition stores waiver details directly. If an older `dbo.acd_mw_waivers` table already exists, do not rerun or alter it automatically. Compare its columns with `sql/schema.sql` and approve a separate migration before using this revision.

For an original project schema whose waiver table contains zero records, review and run `sql/migrate_waiver_first.sql` once. It preserves the old tables, adds the waiver-first columns, and makes the obsolete clinic linkage fields optional. Run `sql/seed.sql` afterward.

Both waiver types use the supplied paper-form wording. Employee name and department placeholders are resolved into the immutable text snapshot when a waiver draft is created.

If `seed.sql` was already run before the paper form was supplied, run `sql/publish_paper_waiver_text.sql` once. It retires the previous active text and publishes version 2.0 without changing historical waiver snapshots.

For pre-production workflow testing, run `sql/sample_test_data.sql`. It creates idempotent sample departments, employees, and role-based test accounts. Every test account uses the temporary password `WaiverTest2026!`; deactivate or remove them before production data is loaded.

## Legacy table audit

The waiver-first runtime no longer references `acd_mw_clinic_visits`, `acd_mw_medical_assessments`, `acd_mw_medical_recommendations`, or `acd_mw_employee_decisions`. Run the read-only `sql/audit_unused_tables.sql` first. After confirming zero rows, no SQL dependencies, and database-owner approval, the guarded `sql/remove_legacy_clinic_tables.sql` removes only those four objects.

The Employees page was removed, but `acd_mw_employees` and `acd_mw_departments` remain required by New Waiver, signed document history, and user-to-employee associations.

## Company folder pattern

The project mirrors `QRS_new`: `api/`, `assets/`, `auth/`, `authz/`, `components/`, `conn/`, `pages/`, `rules/`, `scripts/`, `sql/`, and `styles/`. Root entry files are compatibility shims for the existing localhost URL.
