# Waiver Desk

Internal PHP 8 + Microsoft SQL Server application focused on creating, signing, finalizing, and retaining employee liability waivers.

## Setup

1. Confirm PHP has `pdo_sqlsrv` enabled. The app reuses `../QRS_new/conn/config.php`; credentials are not copied into this project.
2. Review and run `sql/schema.sql` against `LRNPH_OJT`, then run `sql/seed.sql`. Both scripts are idempotent and touch only `dbo.acd_mw_*` tables. They contain no foreign keys.
3. For an existing installation, run `sql/add_signing_workflow.sql` once. It creates only `dbo.acd_mw_signing_tokens` when missing, adds no foreign keys, and refuses to alter an incompatible existing token table.
4. Create the first account from a terminal:

   `php scripts/create-user.php admin "a-strong-password" "System Administrator" "SYSTEM ADMIN"`

5. Add departments and employees to the project-prefixed tables, then open `/Waiver-Liability/`.

The seeded waiver wording is transcribed from the supplied La Rose Noire paper form.

## Secure mobile signing

Authorized staff review a draft and select **Request Employee Signature**. The system stores only the SHA-256 hash of a random one-time token and displays the raw token only inside the temporary mobile URL/QR. The default lifetime is 10 minutes. Active requests can be cancelled, and expired or used QR links cannot be reused.

In **Settings**, set `APP_BASE_URL` to an HTTP or HTTPS address phones can reach on the company network, for example `http://10.2.0.25/Waiver-Liability`. Do not use `localhost` for cross-device testing. The employee verifies with their `employee_number` (shown as Biometrics Number) and the password of the active user account linked to that same `employee_id`. This verification is scoped to the signing token and does not grant access to the admin application.

After the employee confirms, the desktop waiting panel polls the authenticated status endpoint every 2.5 seconds and updates automatically. A supervisor then signs through normal application authentication. Finalization and both signature saves are transactional, completed records are read-only, and PDF export is available only after both signatures and the document hash exist.

The local QR renderer is the MIT-licensed `GlobusStudio/phpQRcode`; formal A4 exports use the PHP-only FPDF library. Neither workflow requires Node.js or an external form service.

Run `php scripts/signing-schema-audit.php` after the migration to list the live project tables, columns, row counts, foreign keys, and employee-linked signing accounts. The audit is read-only and never prints password hashes or raw signing tokens.

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
