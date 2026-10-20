# One system role, assigned through Spatie

Standard roles such as `student`, `instructor`, `admin`, and `super_admin` are Spatie roles. The user management **System role** selector assigns exactly one recognized system role. **Additional access** accepts only custom roles/permission bundles, not another system role.

## Read behavior

`User::role` resolves recognized assigned web-guard Spatie roles first, including enum values, legacy display labels, and the configured Shield super-admin name. Portal routing, hierarchy checks, model serialization, and model-based role behavior use this resolved role. `users.role` remains a legacy fallback for accounts without assigned Spatie system roles and a compatibility projection for existing SQL reports. It is not a second independent selector.

Existing accounts with several recognized system roles resolve to the highest hierarchy level (enum declaration order breaks equal-level ties). No existing roles are removed automatically. Such accounts need explicit review: retaining several system roles also retains all their Spatie permissions. Editing System role intentionally replaces those recognized roles with one role while preserving custom bundles unless the administrator changes them.

## Before rollout

Run the report before deploying changed role resolution. It reports account IDs, not emails or credentials:

```sh
php artisan users:reconcile-system-roles
```

Default is read-only. Review mismatches and multiple-role conflicts. To apply unambiguous records:

```sh
php artisan users:reconcile-system-roles --apply
```

Apply creates a matching Spatie assignment for legacy-only users and updates the legacy projection from a single assigned Spatie role. Multiple system roles and invalid legacy-only roles are reported and skipped; command returns failure while conflicts remain. Resolve those accounts explicitly in user management. Apply is not a preview and may grant the permissions of an existing role to legacy-only users: review the report and role permission definitions first.

This command is deliberately not an automatic database migration. Rollout has not been applied to the application database by this change.

## Compatibility limits

Direct SQL role filters/aggregations still use `users.role`; reconcile projections before relying on those legacy reports. Direct third-party writes to `users.role` do not override an assigned Spatie system role. Integrations changing a system role must use `UserSystemRoleService::assign()` or explicitly manage Spatie assignments and reconcile the legacy projection. Removing the legacy column and converting every raw SQL report is a later migration, not part of this compatibility release.
