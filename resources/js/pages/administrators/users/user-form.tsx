"use client";

import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldDescription, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Building2, KeyRound, Save, ShieldCheck, UserRound } from "lucide-react";
import type { FormEventHandler } from "react";

export interface UserFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    school_id: string;
    department_id: string;
    faculty_id_number: string;
    record_id: string;
    roles: number[];
}

interface UserFormProps {
    data: UserFormData;
    setData: <Key extends keyof UserFormData>(key: Key, value: UserFormData[Key]) => void;
    errors: Record<string, string>;
    roles: Record<string, string>;
    schools: { id: number; name: string }[];
    departments: { id: number; name: string; school_id: number }[];
    permissions: { id: number; name: string }[];
    canEditAccess?: boolean;
    mode: "create" | "edit";
    processing: boolean;
    isDirty?: boolean;
    onSubmit: FormEventHandler;
    onCancel?: FormEventHandler;
}

function FieldErrorMessage({ error }: { error?: string }) {
    return error ? <FieldError>{error}</FieldError> : null;
}

function initials(name: string) {
    const value = name.trim();
    if (!value) return "—";
    return value
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
}

export function UserForm({
    data,
    setData,
    errors,
    roles,
    schools,
    departments,
    permissions,
    canEditAccess = true,
    mode,
    processing,
    isDirty = true,
    onSubmit,
    onCancel,
}: UserFormProps) {
    const filteredDepartments = departments.filter((department) => department.school_id.toString() === data.school_id);
    const selectedRoleLabel = roles[data.role] ?? "Not assigned";
    const selectedSchoolName = schools.find((school) => school.id.toString() === data.school_id)?.name ?? "Not assigned";
    const selectedDepartmentName = filteredDepartments.find((department) => department.id.toString() === data.department_id)?.name ?? "Not assigned";
    const canSave = !processing && (mode === "create" || isDirty);

    const toggleRole = (roleId: number): void => {
        setData("roles", data.roles.includes(roleId) ? data.roles.filter((id) => id !== roleId) : [...data.roles, roleId]);
    };

    return (
        <form onSubmit={onSubmit} className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <div className="min-w-0 space-y-4">
                <section className="bg-card rounded-xl border shadow-xs">
                    <header className="flex items-center gap-2 border-b px-5 py-3.5">
                        <IconTile variant="soft" size="sm" className="text-primary">
                            <UserRound aria-hidden="true" />
                        </IconTile>
                        <div>
                            <h2 className="font-semibold">Identity</h2>
                            <p className="text-muted-foreground text-xs">How this person appears and signs in.</p>
                        </div>
                    </header>
                    <div className="grid gap-4 p-5 md:grid-cols-2">
                        <Field>
                            <FieldLabel htmlFor="name">Full name</FieldLabel>
                            <Input
                                id="name"
                                name="name"
                                autoComplete="name"
                                value={data.name}
                                onChange={(event) => setData("name", event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                                required
                            />
                            <FieldErrorMessage error={errors.name} />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="email">Email address</FieldLabel>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                inputMode="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) => setData("email", event.target.value)}
                                aria-invalid={Boolean(errors.email)}
                                required
                            />
                            <FieldErrorMessage error={errors.email} />
                        </Field>
                    </div>
                </section>

                <section className="bg-card rounded-xl border shadow-xs">
                    <header className="flex items-center gap-2 border-b px-5 py-3.5">
                        <IconTile variant="soft" size="sm" className="text-amber-600">
                            <KeyRound aria-hidden="true" />
                        </IconTile>
                        <div>
                            <h2 className="font-semibold">Credentials</h2>
                            <p className="text-muted-foreground text-xs">
                                {mode === "create" ? "The user signs in with this password." : "Leave blank to keep the current password."}
                            </p>
                        </div>
                    </header>
                    <div className="grid gap-4 p-5 md:grid-cols-2">
                        <Field>
                            <FieldLabel htmlFor="password">{mode === "create" ? "Password" : "New password"}</FieldLabel>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(event) => setData("password", event.target.value)}
                                aria-invalid={Boolean(errors.password)}
                                required={mode === "create"}
                            />
                            <FieldErrorMessage error={errors.password} />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="password_confirmation">Confirm password</FieldLabel>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(event) => setData("password_confirmation", event.target.value)}
                                aria-invalid={Boolean(errors.password_confirmation)}
                                required={mode === "create"}
                            />
                            <FieldErrorMessage error={errors.password_confirmation} />
                        </Field>
                    </div>
                </section>

                <section className="bg-card rounded-xl border shadow-xs">
                    <header className="flex items-center gap-2 border-b px-5 py-3.5">
                        <IconTile variant="soft" size="sm" className="text-emerald-600">
                            <Building2 aria-hidden="true" />
                        </IconTile>
                        <div>
                            <h2 className="font-semibold">Placement</h2>
                            <p className="text-muted-foreground text-xs">Where this person works and what records they link to.</p>
                        </div>
                    </header>
                    <div className="grid gap-4 p-5 md:grid-cols-2">
                        <Field>
                            <FieldLabel htmlFor="school">School or college</FieldLabel>
                            <Select
                                value={data.school_id}
                                onValueChange={(value) => {
                                    setData("school_id", value);
                                    setData("department_id", "");
                                }}
                            >
                                <SelectTrigger id="school" aria-invalid={Boolean(errors.school_id)}>
                                    <SelectValue placeholder="Select a school" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schools.map((school) => (
                                        <SelectItem key={school.id} value={school.id.toString()}>
                                            {school.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldErrorMessage error={errors.school_id} />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="department">Department</FieldLabel>
                            <Select value={data.department_id} onValueChange={(value) => setData("department_id", value)} disabled={!data.school_id}>
                                <SelectTrigger id="department" aria-invalid={Boolean(errors.department_id)}>
                                    <SelectValue placeholder={data.school_id ? "Select a department" : "Select a school first"} />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredDepartments.map((department) => (
                                        <SelectItem key={department.id} value={department.id.toString()}>
                                            {department.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldErrorMessage error={errors.department_id} />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="faculty_id_number">Faculty ID number</FieldLabel>
                            <Input
                                id="faculty_id_number"
                                name="faculty_id_number"
                                value={data.faculty_id_number}
                                onChange={(event) => setData("faculty_id_number", event.target.value)}
                                aria-invalid={Boolean(errors.faculty_id_number)}
                                placeholder="Optional"
                            />
                            <FieldErrorMessage error={errors.faculty_id_number} />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="record_id">External record ID</FieldLabel>
                            <Input
                                id="record_id"
                                name="record_id"
                                value={data.record_id}
                                onChange={(event) => setData("record_id", event.target.value)}
                                aria-invalid={Boolean(errors.record_id)}
                                placeholder="Optional"
                            />
                            <FieldErrorMessage error={errors.record_id} />
                        </Field>
                    </div>
                </section>

                <section className="bg-card rounded-xl border shadow-xs">
                    <header className="flex items-center gap-2 border-b px-5 py-3.5">
                        <IconTile variant="soft" size="sm" className="text-primary">
                            <ShieldCheck aria-hidden="true" />
                        </IconTile>
                        <div>
                            <h2 className="font-semibold">Access</h2>
                            <p className="text-muted-foreground text-xs">One system role decides authority. Custom bundles add narrow permissions.</p>
                        </div>
                    </header>
                    <div className="flex flex-col gap-5 p-5">
                        <Field>
                            <FieldLabel htmlFor="role">System role</FieldLabel>
                            <Select value={data.role} onValueChange={(value) => setData("role", value)} disabled={!canEditAccess}>
                                <SelectTrigger id="role" aria-invalid={Boolean(errors.role)}>
                                    <SelectValue placeholder="Choose a system role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(roles).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldDescription>
                                {canEditAccess
                                    ? "Determines portal access, hierarchy, and the permissions this role grants."
                                    : "Your system role stays unchanged when editing your own account."}
                            </FieldDescription>
                            <FieldErrorMessage error={errors.role} />
                        </Field>

                        <div>
                            <p className="text-sm font-medium">Additional access</p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Custom permission bundles only. System roles stay in the selector above.
                            </p>
                            {permissions.length > 0 ? (
                                <div className="mt-3 grid gap-2 md:grid-cols-2">
                                    {permissions.map((permission) => {
                                        const checked = data.roles.includes(permission.id);
                                        return (
                                            <label
                                                key={permission.id}
                                                htmlFor={`permission-${permission.id}`}
                                                className="hover:bg-muted/60 flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 text-sm transition-colors"
                                            >
                                                <Checkbox
                                                    id={`permission-${permission.id}`}
                                                    checked={checked}
                                                    disabled={!canEditAccess}
                                                    onCheckedChange={() => toggleRole(permission.id)}
                                                    className="mt-0.5"
                                                />
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium">{permission.name}</span>
                                                    <span className="text-muted-foreground block text-xs">Permission bundle</span>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="text-muted-foreground mt-3 rounded-lg border border-dashed p-4 text-sm">
                                    No custom access bundles are available.
                                </p>
                            )}
                            <FieldErrorMessage error={errors.roles} />
                        </div>
                    </div>
                </section>
            </div>

            <aside className="lg:sticky lg:top-20">
                <div className="bg-card flex flex-col gap-4 rounded-xl border p-5 shadow-xs">
                    <div className="flex items-center gap-3">
                        <span className="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-xl text-sm font-semibold">
                            {initials(data.name)}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-medium">{data.name || "Unnamed account"}</p>
                            <p className="text-muted-foreground truncate text-xs">{data.email || "No email yet"}</p>
                        </div>
                    </div>

                    <dl className="flex flex-col gap-2.5 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground text-xs">System role</dt>
                            <dd>
                                <Badge variant="outline" size="sm" radius="full">
                                    {selectedRoleLabel}
                                </Badge>
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground text-xs">School</dt>
                            <dd className="truncate text-xs font-medium">{selectedSchoolName}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground text-xs">Department</dt>
                            <dd className="truncate text-xs font-medium">{selectedDepartmentName}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground text-xs">Custom bundles</dt>
                            <dd className="text-xs font-medium tabular-nums">{data.roles.length}</dd>
                        </div>
                    </dl>

                    <p className="text-muted-foreground text-xs text-pretty">
                        {mode === "create"
                            ? "The account is created with the selected system role and access bundles."
                            : canEditAccess
                              ? "Saving replaces the system role and keeps the access bundles you keep selected."
                              : "Saving updates your details without changing your roles or access bundles."}
                    </p>

                    <div className="flex flex-col gap-2 border-t pt-4">
                        <Button type="submit" disabled={!canSave} className="w-full">
                            <Save aria-hidden="true" />
                            {processing ? "Saving…" : mode === "create" ? "Create account" : "Save changes"}
                        </Button>
                        {onCancel ? (
                            <Button type="button" variant="outline" disabled={processing} onClick={onCancel} className="w-full">
                                Cancel
                            </Button>
                        ) : null}
                    </div>
                </div>
            </aside>
        </form>
    );
}
