# Financial foreign-key recovery after migration

The `2026_10_26_000001_restrict_financial_and_audit_foreign_keys` migration protects financial references against new invalid writes and destructive parent deletion. Older installations may already contain references to missing records.

On PostgreSQL, a relationship with existing orphan rows is installed as **`NOT VALID`**. Historical rows remain unchanged; new or changed foreign-key references are checked, and protected parent deletes are restricted. The migration can therefore finish without fabricating enrollments, nulling references, or deleting financial history. The constraint remains unvalidated until a separate reconciliation is completed.

On other supported databases, the migration stops if it finds orphans because their engines do not offer the same safe deferred-validation behavior. It also stops for an unexpected target, composite foreign key, or missing required schema. Do not remove rows to make the migration pass.

## Review after deployment

1. Confirm a restorable backup. Work from a production-like copy where possible; coordinate findings with authorized finance and registrar staff. Do not expose student-level financial records in public issue trackers or logs.
2. List unvalidated foreign keys in the current PostgreSQL schema (names and tables only):

    ```sql
    SELECT r.relname AS source_table, c.conname AS constraint_name,
           t.relname AS referenced_table, pg_get_constraintdef(c.oid) AS definition
    FROM pg_constraint c
    JOIN pg_class r ON r.oid = c.conrelid
    JOIN pg_namespace n ON n.oid = r.relnamespace
    JOIN pg_class t ON t.oid = c.confrelid
    WHERE c.contype = 'f' AND NOT c.convalidated
      AND n.nspname = current_schema()
    ORDER BY r.relname, c.conname;
    ```

3. For the reported relationship, count affected rows and distinct missing identifiers without altering data:

    ```sql
    SELECT count(*) AS orphaned_tuition_rows,
           count(DISTINCT st.enrollment_id) AS missing_enrollment_ids
    FROM student_tuition st
    WHERE st.enrollment_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM student_enrollment se WHERE se.id = st.enrollment_id);
    ```

4. Investigate and approve a **record-specific** repair separately. An orphan is not proof that its financial record is invalid or that an enrollment can safely be reconstructed. Preserve audit history and avoid bulk updates or deletes.
5. After the approved repair, confirm the orphan count is zero and validate the specific existing constraint in a planned maintenance window. Use the actual identifier returned by step 2; the standard constraint name is shown here only as an example:

    ```sql
    ALTER TABLE student_tuition VALIDATE CONSTRAINT student_tuition_enrollment_id_foreign;
    ```

    Validation checks historical rows and may take time on large tables. If it fails, the constraint remains enforced for new or changed references; investigate before trying again.

The migration is safe to rerun after an interrupted attempt: a matching constraint with the intended delete action is retained whether validated or not. Modifying unrelated fields on an orphaned tuition row remains possible; changing its foreign-key value must satisfy the constraint.
