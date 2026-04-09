# Database Seeders

The seeders in this directory populate KoAkademy with realistic local development and test data.

## What Gets Seeded

- system users and staff roles
- courses, subjects, rooms, and faculty
- students and linked accounts
- classes, schedules, and enrollments
- tuition, payments, and related transactions
- clearance and related administrative records

The exact volume changes over time. Check the seeder classes if you need the current source of truth.

## Run the Seeders

Fresh database with seed data:

```bash
vendor/bin/sail artisan migrate:fresh --seed
```

Seed an existing schema:

```bash
vendor/bin/sail artisan db:seed
```

Run a specific seeder:

```bash
vendor/bin/sail artisan db:seed --class=UserSeeder
vendor/bin/sail artisan db:seed --class=StudentSeeder
```

## Default Accounts

These accounts are defined directly in the current seeders and are safe to rely on for local development:

- `developer@koakademy.edu` / `password`
- `admin@koakademy.edu` / `password`
- `president@koakademy.edu` / `password`
- `registrar@koakademy.edu` / `password`
- `cashier@koakademy.edu` / `password`
- `john.student@student.koakademy.edu` / `password`

Additional staff, faculty, and student accounts are also seeded. The latest list is in [UserSeeder.php](./UserSeeder.php), [FacultySeeder.php](./FacultySeeder.php), and [StudentSeeder.php](./StudentSeeder.php).

## Email Patterns

- staff and faculty accounts use `@koakademy.edu`
- student accounts use `@student.koakademy.edu`

## Typical Use Cases

- demo data for UI work
- integration tests that need realistic relations
- local verification of enrollment, billing, and clearance flows

## Notes

- Do not depend on seeded record counts in feature code.
- Prefer factories for tests unless you explicitly need the seeded graph.
- If you change seeded credentials or identities, update this document in the same change.
