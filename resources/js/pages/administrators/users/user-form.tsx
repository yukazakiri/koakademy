import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldSet } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Link } from "@inertiajs/react";
import { Building2, KeyRound, Save, ShieldCheck, UserRound } from "lucide-react";
import type { FormEventHandler } from "react";

declare const route: (name: string, ...parameters: unknown[]) => string;

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
    mode: "create" | "edit";
    processing: boolean;
    isDirty?: boolean;
    onSubmit: FormEventHandler;
}

function FieldErrorMessage({ error }: { error?: string }) {
    return error ? <FieldError>{error}</FieldError> : null;
}

export function UserForm({
    data,
    setData,
    errors,
    roles,
    schools,
    departments,
    permissions,
    mode,
    processing,
    isDirty = true,
    onSubmit,
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
        <form onSubmit={onSubmit} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <div className="min-w-0 space-y-4">
                <Card size="sm">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2">
                            <UserRound aria-hidden="true" className="text-primary size-4" />
                            Account details
                        </CardTitle>
                        <CardDescription>Name, sign-in email, and credentials.</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <FieldSet>
                            <FieldGroup className="grid gap-4 md:grid-cols-2">
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
                            </FieldGroup>

                            <Separator />

                            <div className="flex items-center gap-2">
                                <KeyRound aria-hidden="true" className="text-muted-foreground size-4" />
                                <div>
                                    <p className="text-sm font-medium">{mode === "create" ? "Set a password" : "Rotate password"}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {mode === "create"
                                            ? "The user will use this password to sign in."
                                            : "Leave both fields blank to keep the current password."}
                                    </p>
                                </div>
                            </div>

                            <FieldGroup className="grid gap-4 md:grid-cols-2">
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
                            </FieldGroup>
                        </FieldSet>
                    </CardContent>
                </Card>

                <Card size="sm">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2">
                            <Building2 aria-hidden="true" className="text-primary size-4" />
                            Organization
                        </CardTitle>
                        <CardDescription>Set the account&apos;s primary role and academic placement.</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <FieldSet>
                            <Field>
                                <FieldLabel htmlFor="role">System role</FieldLabel>
                                <Select value={data.role} onValueChange={(value) => setData("role", value)}>
                                    <SelectTrigger id="role" aria-invalid={Boolean(errors.role)}>
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(roles).map(([value, label]) => (
                                            <SelectItem key={value} value={value}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldDescription>The primary role should cover most of the user&apos;s responsibilities.</FieldDescription>
                                <FieldErrorMessage error={errors.role} />
                            </Field>

                            <FieldGroup className="grid gap-4 md:grid-cols-2">
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
                                    <Select
                                        value={data.department_id}
                                        onValueChange={(value) => setData("department_id", value)}
                                        disabled={!data.school_id}
                                    >
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
                            </FieldGroup>

                            <FieldGroup className="grid gap-4 md:grid-cols-2">
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
                            </FieldGroup>
                        </FieldSet>
                    </CardContent>
                </Card>

                <Card size="sm">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck aria-hidden="true" className="text-primary size-4" />
                            Additional access
                        </CardTitle>
                        <CardDescription>Attach direct roles only when the primary role needs an exception.</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        {permissions.length > 0 ? (
                            <div className="grid gap-2 md:grid-cols-2">
                                {permissions.map((permission) => {
                                    const checked = data.roles.includes(permission.id);

                                    return (
                                        <label
                                            key={permission.id}
                                            htmlFor={`permission-${permission.id}`}
                                            className={`flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 transition-colors ${
                                                checked ? "border-primary/40 bg-primary/5" : "hover:bg-muted/50"
                                            }`}
                                        >
                                            <Checkbox
                                                id={`permission-${permission.id}`}
                                                checked={checked}
                                                onCheckedChange={() => toggleRole(permission.id)}
                                            />
                                            <span className="text-sm font-medium">{permission.name}</span>
                                            {checked ? <Badge className="ml-auto rounded-md text-[11px]">Selected</Badge> : null}
                                        </label>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">No additional roles are available.</p>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-end">
                    <Button variant="outline" asChild>
                        <Link href={route("administrators.users.index")}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={!canSave}>
                        <Save aria-hidden="true" />
                        {processing ? "Saving..." : mode === "create" ? "Create user" : "Save changes"}
                    </Button>
                </div>
            </div>

            <aside className="order-first space-y-4 lg:sticky lg:top-20 lg:order-last lg:self-start">
                <Card size="sm">
                    <CardHeader className="border-b">
                        <CardTitle className="text-sm">Account summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-xs">Primary role</p>
                            <p className="text-sm font-medium">{selectedRoleLabel}</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-xs">Organization</p>
                            <p className="text-sm font-medium">{selectedSchoolName}</p>
                            <p className="text-muted-foreground text-xs">{selectedDepartmentName}</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-muted-foreground text-xs">Additional access</p>
                            <p className="text-sm font-medium tabular-nums">{data.roles.length} selected</p>
                        </div>
                        {mode === "edit" ? (
                            <div className="border-t pt-3">
                                <p className={`text-sm font-medium ${isDirty ? "text-amber-700 dark:text-amber-400" : "text-muted-foreground"}`}>
                                    {isDirty ? "Unsaved changes" : "No unsaved changes"}
                                </p>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </aside>
        </form>
    );
}
