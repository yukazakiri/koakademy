# Student configuration and numbering

Student preferences belong to each school, not to the application source code. A new self-hosted installation starts with neutral preferences. Existing installations are migrated to an explicit Philippine legacy configuration so upgrading does not silently change their data-entry rules.

## Where to configure

- **System Management → Student Configuration**: enabled supported categories, category labels, year/grade options, default nationality/citizenship/gender, supported field visibility/requiredness, Philippine reporting controls, and an external identifier.
- **System Management → Student & Staff IDs**: student-number label, assignment mode, character set, prefixes, padding, validation, and counters.
- Initial setup offers **Neutral international defaults** and **Philippine legacy preferences**. Presets load a starting configuration; they are not reapplied on upgrades. The setup school level selects the initial neutral category for higher education, senior high, or technical training.

Core identity and academic placement remain mandatory. The existing supported student categories can be enabled and relabeled; creating entirely new workflow categories is not currently supported. Regional reporting is an explicit opt-in; disabling a field does not delete its existing value on edit.

### Institution-specific fields

Under **Student information**, add up to 30 institution-specific fields with a label and a text, number, date, or single-choice answer. Their stable internal keys are generated automatically. Active fields appear on the administrator student create, edit, and profile views; values are stored under `students.profile_details.custom_fields` without adding database columns. Do not put secrets, passwords, or credentials in these fields. Access follows existing student-profile permissions.

Custom responses are intentionally excluded from the create form's persistent browser draft. Staff must re-enter them when restoring a draft.

Existing field types cannot be changed after saving. Archive a field instead of deleting it: its values stay in the student record and reappear if the field is reactivated. Retired choice values remain valid for students who already have them, but cannot be assigned to new records. Existing student records are not forced to acquire newly required values on edit, while a required field must be supplied on new records and cannot be cleared once filled.

Custom fields are currently collected only by the administrator student form, not the public form, Filament, AI/MCP tools, or API. If any active custom field is required, those secondary creation channels reject new students and public enrollment is closed with instructions to contact the school office. Existing records may still be edited through supported channels without clearing these fields. Optional custom fields do not block other creation channels. Exports and bulk imports do not yet include custom values.

Some secondary entry points cannot collect every configurable field. When a school marks an unsupported field required, those entry points reject creation with an explanation and direct staff to the administrator form instead of silently creating an incomplete record.

## Identifier contract

- `students.id` remains the internal integer record key. Foreign keys, enrollments, payments, and URLs continue to use it.
- `students.student_id` is the public **string** student number. Preserve it as text in API clients, spreadsheets, and integrations. `000042`, `42`, and `STU-000042` are different identifiers.
- Student numbers are unique **within a school**, including soft-deleted records. A migration-added unique claim protects concurrent writes without rewriting pre-existing duplicate legacy rows. Unchanged legacy numbers may still be edited; new duplicates cannot be assigned.
- **Automatic** allocates a number inside the record-creation transaction. A preview is not a reservation and is ignored during automatic submission.
- **Manual** requires a supplied number. **Automatic with manual override** offers both modes explicitly. Failed record creation rolls back the sequence advance.
- Padding applies to the counter only. Length rules count the complete formatted identifier, including its prefix.
- The year prefix uses the application's configured timezone and does not reset the counter annually.
- Shared counters are the default. Separate category counters require distinct category prefixes. They retain their positions when toggling modes. Stale counter edits are rejected instead of overwriting a number issued by another operator.
- Staff/faculty numbering remains installation-wide because its existing signup/validation contract requires globally unique staff numbers.

### External identifiers

The external-reference field is stored in the existing `lrn` column for compatibility. It can be relabeled, restricted to supported categories, optional/required, and validated as text or digits with configured lengths. It is **not** generated by this application.

Normally it is separate from the school number. **Use external value as student number** preserves legacy SHS LRN-as-number behavior when deliberately enabled. Changing this preference never bulk-renumbers existing students. An existing separate student number stays separate on unrelated edits; an existing external-backed number is changed through its external-reference field.

Public enrollment supports automatic school numbering. Categories that require manual assignment or external-as-student-number entry must use the school office workflow. Signup resolves the number field using the matched student's school, not an unauthenticated caller's ambient school. Returning-student lookup compares complete strings without stripping prefixes or leading zeros.

Several existing read-only MCP endpoints accept `student_id` as an integer for backward compatibility. For formatted numbers, first find the record using a text-capable search and pass the returned **internal record ID** to those endpoints; those integer fields cannot carry leading zeros or letters.

## Upgrading safely

1. Back up the database and test the upgrade on a copy, using the same database engine/version as production.
2. Deploy the code and run `php artisan migrate --force` during a maintenance window.
3. Rebuild frontend assets and regenerate Wayfinder routes through the normal build pipeline.
4. Review each school's Student Configuration and identifier previews before admitting new records.
5. Leave **Preserve legacy numeric formatting** enabled if the installation relies on numeric coercion or the former non-repeating prefix behavior. Disable it explicitly for new text formats; existing numbers are not reformatted.

The migrations snapshot existing numbering counters and expand implicit type-prefix fallbacks before introducing neutral defaults. They do not reset counters or renumber students. New schools receive their own configuration; editing form preferences never resets an initialized identifier sequence.

The old `students:fix-college-ids` maintenance command implements an institution-specific six-digit rewrite and is disabled unless explicitly acknowledged. Do not use it for configurable or nonnumeric identifiers. Use the audited student-number workflow for an individual correction instead.

The identifier schema migration intentionally refuses rollback: converting arbitrary text numbers back to integers or merging independently advanced school counters is lossy. Use a tested forward migration or restore the pre-upgrade backup. Never roll older code onto a database that already contains formatted identifiers.

## Verification

Relevant regression coverage is in `tests/Feature/StudentConfigurationTest.php`, `tests/Feature/ConfigurableIdGenerationTest.php`, and the existing student-management, signup, enrollment, import, API authorization, and setup tests. Migration fixtures cover fresh and upgraded SQLite databases. Production-engine migration and concurrency checks should also be performed before rollout.

For isolated frontend review without any real account or school data:

```bash
node tests/Browser/student-configuration-preview.mjs
```

Open `http://127.0.0.1:52971/student-policy-configuration`, `/student-policy-identifiers`, or `/student-policy-create`. Use `/student-policy-create-legacy?legacy=1` for the legacy form. This preview uses synthetic props and a mocked ID preview; it does not test saving to Laravel.
