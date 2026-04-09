# Student Medical Records Module

This module adds student clinic record management for KoAkademy.

## Purpose

The module provides:

- structured medical visit records per student
- follow-up scheduling and status tracking
- privacy controls for confidential entries
- Filament resource screens for administrative workflows

## Main Components

- `app/Models/MedicalRecord.php`: Eloquent model for persisted records.
- `app/Enums/`: domain enums for type, status, and priority.
- `app/Policies/MedicalRecordPolicy.php`: authorization rules.
- `app/Filament/Resources/MedicalRecords/`: list, create, edit pages plus form and table schema.
- `app/Filament/Widgets/MedicalStatsWidget.php`: dashboard stats card.
- `routes/web.php` and `routes/api.php`: module routes.
- `database/migrations/`: schema creation and updates.

## Data Model Notes

Medical records are linked to students and include clinical metadata such as:

- record type
- visit date
- description, diagnosis, treatment, and prescription fields
- optional measurements (height, weight, temperature, blood pressure)
- status and priority
- follow-up date
- confidentiality flags

## Access and Security

- Access is controlled by `MedicalRecordPolicy`.
- Confidential records should be visible only to authorized roles.
- Any policy changes should be covered by feature tests.

## Developer Workflow

Run module-related checks through Sail:

```bash
vendor/bin/sail artisan test --compact Modules/StudentMedicalRecords/tests
vendor/bin/sail artisan migrate
```

If UI schema changes are made under Filament resources, verify list, create, and edit flows in the admin panel.

## Extending the Module

When adding fields or behavior:

1. Add or update the migration.
2. Update the model fillable/casts as needed.
3. Update Filament form and table schemas.
4. Update policy logic if access rules change.
5. Add or update tests in `Modules/StudentMedicalRecords/tests`.

## Maintenance Notes

- Keep record statuses and priorities enum-backed to avoid string drift.
- Avoid hardcoded brand/domain values in module UI text.
- Reuse shared settings/config services when exposing org metadata.
