import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldSeparator, FieldSet } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from "@/components/ui/input-group";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    BriefcaseBusiness,
    Building2,
    CircleCheckBig,
    IdCard,
    KeyRound,
    Mail,
    Save,
    ShieldCheck,
    Sparkles,
    UserCog,
    Users2,
} from "lucide-react";
import type { FormEventHandler } from "react";

interface ExtendedUser {
    id: number;
    name: string;
    email: string;
    role: string;
    school_id: number | null;
    department_id: number | null;
    faculty_id_number: string | null;
    record_id: string | null;
    avatar_url?: string | null;
    roles: { id: number; name: string }[];
}

interface PageProps {
    user: ExtendedUser;
    roles: Record<string, string>;
    schools: { id: number; name: string }[];
    departments: { id: number; name: string; school_id: number }[];
    permissions: { id: number; name: string }[];
    auth_user: any;
}

const BEGINNER_GUIDE = [
    "Pick the main system role first. It should explain most of the user's job.",
    "Only attach extra access bundles when the default role is not enough.",
    "Leave password fields blank unless you intentionally want to rotate credentials.",
];

function initialsFor(name: string): string {
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? "")
        .join("");
}

export default function UserEdit({ user, roles, schools, departments, permissions, auth_user }: PageProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: user.name || "",
        email: user.email || "",
        password: "",
        password_confirmation: "",
        role: user.role || "",
        school_id: user.school_id?.toString() || "",
        department_id: user.department_id?.toString() || "",
        faculty_id_number: user.faculty_id_number || "",
        record_id: user.record_id || "",
        roles: user.roles ? user.roles.map((role) => role.id) : ([] as number[]),
    });

    const filteredDepartments = departments.filter((department) => department.school_id.toString() === data.school_id?.toString());
    const selectedRoleLabel = roles[data.role] ?? "No system role selected";
    const selectedSchoolName = schools.find((school) => school.id.toString() === data.school_id)?.name ?? "Not assigned";
    const selectedDepartmentName = filteredDepartments.find((department) => department.id.toString() === data.department_id)?.name ?? "Not assigned";
    const completionScore = [data.name, data.email, data.role].filter((value) => value.trim() !== "").length;
    const completionPercentage = Math.round((completionScore / 3) * 100);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        put(route("administrators.users.update", user.id));
    };

    const toggleRole = (roleId: number): void => {
        if (data.roles.includes(roleId)) {
            setData(
                "roles",
                data.roles.filter((id) => id !== roleId),
            );

            return;
        }

        setData("roles", [...data.roles, roleId]);
    };

    return (
        <AdminLayout user={auth_user} title="Edit User">
            <Head title={`Edit User • ${user.name}`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-6">
                <Card>
                    <CardHeader className="gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div className="space-y-4">
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.users.index")}>
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back to users
                                </Link>
                            </Button>

                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">
                                    <Sparkles className="mr-1 h-3.5 w-3.5" />
                                    Beginner-friendly editor
                                </Badge>
                                <Badge variant="outline">{selectedRoleLabel}</Badge>
                                <Badge variant="outline">
                                    {data.roles.length} access bundle{data.roles.length === 1 ? "" : "s"}
                                </Badge>
                            </div>

                            <div className="space-y-2">
                                <CardTitle className="text-3xl tracking-tight">Edit {user.name}</CardTitle>
                                <CardDescription className="max-w-3xl text-sm leading-6">
                                    This screen is organized for faster admin work: account identity first, organizational placement next, and direct
                                    access bundles last.
                                </CardDescription>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3 lg:w-[420px]">
                            <div className="rounded-xl border bg-muted/40 p-4">
                                <p className="text-muted-foreground text-xs uppercase tracking-[0.18em]">Required</p>
                                <p className="mt-2 text-2xl font-semibold">{completionScore}/3</p>
                                <p className="text-muted-foreground mt-1 text-sm">Name, email, and role.</p>
                            </div>
                            <div className="rounded-xl border bg-muted/40 p-4">
                                <p className="text-muted-foreground text-xs uppercase tracking-[0.18em]">School</p>
                                <p className="mt-2 line-clamp-2 text-sm font-medium">{selectedSchoolName}</p>
                                <p className="text-muted-foreground mt-1 text-sm">{selectedDepartmentName}</p>
                            </div>
                            <div className="rounded-xl border bg-muted/40 p-4">
                                <p className="text-muted-foreground text-xs uppercase tracking-[0.18em]">Save state</p>
                                <p className="mt-2 text-sm font-medium">{isDirty ? "Unsaved changes" : "Everything saved"}</p>
                                <p className="text-muted-foreground mt-1 text-sm">Review the side panel before saving.</p>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
                        <Card>
                            <CardHeader className="space-y-4">
                                <div className="flex items-center gap-4">
                                    <Avatar className="h-16 w-16 border">
                                        <AvatarImage src={user.avatar_url ?? undefined} alt={user.name} />
                                        <AvatarFallback className="text-base font-semibold">{initialsFor(user.name)}</AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 space-y-1">
                                        <CardTitle className="truncate text-xl">{data.name || user.name}</CardTitle>
                                        <CardDescription className="truncate">{data.email || "No email set"}</CardDescription>
                                    </div>
                                </div>

                                <div className="space-y-3 rounded-xl border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-muted-foreground text-sm">Primary role</span>
                                        <Badge variant="outline">{selectedRoleLabel}</Badge>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-muted-foreground text-sm">School</span>
                                        <span className="text-right text-sm font-medium">{selectedSchoolName}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-muted-foreground text-sm">Department</span>
                                        <span className="text-right text-sm font-medium">{selectedDepartmentName}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-muted-foreground text-sm">Access bundles</span>
                                        <span className="text-sm font-medium">{data.roles.length}</span>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CircleCheckBig className="text-primary h-4 w-4" />
                                    Save checklist
                                </CardTitle>
                                <CardDescription>Use this to sanity-check the most important fields before submitting.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Required completion</span>
                                        <span className="font-medium">{completionPercentage}%</span>
                                    </div>
                                    <Progress value={completionPercentage} />
                                </div>

                                <div className="space-y-3">
                                    {[
                                        { label: "Name added", complete: data.name.trim() !== "" },
                                        { label: "Email added", complete: data.email.trim() !== "" },
                                        { label: "System role chosen", complete: data.role.trim() !== "" },
                                    ].map((item) => (
                                        <div key={item.label} className="flex items-center gap-3 text-sm">
                                            <CircleCheckBig className={item.complete ? "text-primary h-4 w-4" : "text-muted-foreground h-4 w-4"} />
                                            <span className={item.complete ? "font-medium" : "text-muted-foreground"}>{item.label}</span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Alert>
                            <Sparkles className="h-4 w-4" />
                            <AlertTitle>Beginner notes</AlertTitle>
                            <AlertDescription className="space-y-2">
                                {BEGINNER_GUIDE.map((item) => (
                                    <p key={item}>{item}</p>
                                ))}
                            </AlertDescription>
                        </Alert>
                    </aside>

                    <form onSubmit={submit} className="space-y-6">
                        <Tabs defaultValue="profile" className="space-y-6">
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="profile">Profile</TabsTrigger>
                                <TabsTrigger value="organization">Organization</TabsTrigger>
                                <TabsTrigger value="access">Access</TabsTrigger>
                            </TabsList>

                            <TabsContent value="profile" className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-start gap-3">
                                            <div className="bg-primary/10 text-primary rounded-lg p-2">
                                                <UserCog className="h-4 w-4" />
                                            </div>
                                            <div>
                                                <CardTitle>Identity and sign-in</CardTitle>
                                                <CardDescription>
                                                    Update the visible account details and optionally rotate credentials.
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <FieldSet>
                                            <FieldGroup className="grid gap-6 md:grid-cols-2">
                                                <Field>
                                                    <FieldLabel htmlFor="name">Full name</FieldLabel>
                                                    <Input
                                                        id="name"
                                                        value={data.name}
                                                        onChange={(event) => setData("name", event.target.value)}
                                                        placeholder="e.g. Juan Dela Cruz"
                                                        required
                                                    />
                                                    <FieldDescription>Used in tables, notifications, and audit logs.</FieldDescription>
                                                    <FieldError>{errors.name}</FieldError>
                                                </Field>

                                                <Field>
                                                    <FieldLabel htmlFor="email">Email address</FieldLabel>
                                                    <InputGroup>
                                                        <InputGroupAddon align="inline-start">
                                                            <InputGroupText>
                                                                <Mail className="h-4 w-4" />
                                                            </InputGroupText>
                                                        </InputGroupAddon>
                                                        <InputGroupInput
                                                            id="email"
                                                            type="email"
                                                            value={data.email}
                                                            onChange={(event) => setData("email", event.target.value)}
                                                            placeholder="user@koakademy.edu"
                                                            required
                                                        />
                                                    </InputGroup>
                                                    <FieldDescription>The user signs in and receives password resets with this address.</FieldDescription>
                                                    <FieldError>{errors.email}</FieldError>
                                                </Field>
                                            </FieldGroup>

                                            <FieldSeparator>Password rotation</FieldSeparator>

                                            <Alert>
                                                <KeyRound className="h-4 w-4" />
                                                <AlertTitle>Password changes are optional</AlertTitle>
                                                <AlertDescription>
                                                    Leave both password fields empty if you only want to update profile or access settings.
                                                </AlertDescription>
                                            </Alert>

                                            <FieldGroup className="grid gap-6 md:grid-cols-2">
                                                <Field>
                                                    <FieldLabel htmlFor="password">New password</FieldLabel>
                                                    <Input
                                                        id="password"
                                                        type="password"
                                                        value={data.password}
                                                        onChange={(event) => setData("password", event.target.value)}
                                                        placeholder="Only fill this when rotating credentials"
                                                    />
                                                    <FieldDescription>Manual reset for cases where email reset is not the right workflow.</FieldDescription>
                                                    <FieldError>{errors.password}</FieldError>
                                                </Field>

                                                <Field>
                                                    <FieldLabel htmlFor="password_confirmation">Confirm new password</FieldLabel>
                                                    <Input
                                                        id="password_confirmation"
                                                        type="password"
                                                        value={data.password_confirmation}
                                                        onChange={(event) => setData("password_confirmation", event.target.value)}
                                                        placeholder="Repeat the new password"
                                                    />
                                                    <FieldDescription>Only needed when the password field is filled.</FieldDescription>
                                                </Field>
                                            </FieldGroup>
                                        </FieldSet>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="organization" className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-start gap-3">
                                            <div className="bg-primary/10 text-primary rounded-lg p-2">
                                                <BriefcaseBusiness className="h-4 w-4" />
                                            </div>
                                            <div>
                                                <CardTitle>Role and organizational placement</CardTitle>
                                                <CardDescription>
                                                    Choose the user's main responsibility, then place them inside the correct school and department.
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <FieldSet>
                                            <Field>
                                                <FieldLabel htmlFor="role">System role</FieldLabel>
                                                <Select onValueChange={(value) => setData("role", value)} value={data.role}>
                                                    <SelectTrigger id="role">
                                                        <SelectValue placeholder="Choose the user's main role" />
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
                                                    Start here. The enum role should explain most of what this user is allowed to do.
                                                </FieldDescription>
                                                <FieldError>{errors.role}</FieldError>
                                            </Field>

                                            <FieldGroup className="grid gap-6 md:grid-cols-2">
                                                <Field>
                                                    <FieldLabel htmlFor="school">School or college</FieldLabel>
                                                    <Select
                                                        onValueChange={(value) => {
                                                            setData((previous) => ({ ...previous, school_id: value, department_id: "" }));
                                                        }}
                                                        value={data.school_id}
                                                    >
                                                        <SelectTrigger id="school">
                                                            <SelectValue placeholder="Assign a school" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {schools.map((school) => (
                                                                <SelectItem key={school.id} value={school.id.toString()}>
                                                                    {school.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FieldDescription>Best used for staff tied to a specific academic unit.</FieldDescription>
                                                    <FieldError>{errors.school_id}</FieldError>
                                                </Field>

                                                <Field>
                                                    <FieldLabel htmlFor="department">Department</FieldLabel>
                                                    <Select
                                                        onValueChange={(value) => setData("department_id", value)}
                                                        value={data.department_id}
                                                        disabled={!data.school_id}
                                                    >
                                                        <SelectTrigger id="department">
                                                            <SelectValue placeholder={data.school_id ? "Assign a department" : "Choose a school first"} />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {filteredDepartments.map((department) => (
                                                                <SelectItem key={department.id} value={department.id.toString()}>
                                                                    {department.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    <FieldDescription>Departments are filtered by school to keep assignments valid.</FieldDescription>
                                                    <FieldError>{errors.department_id}</FieldError>
                                                </Field>
                                            </FieldGroup>

                                            <Separator />

                                            <FieldGroup className="grid gap-6 md:grid-cols-2">
                                                <Field>
                                                    <FieldLabel htmlFor="faculty_id_number">Faculty ID number</FieldLabel>
                                                    <InputGroup>
                                                        <InputGroupAddon align="inline-start">
                                                            <InputGroupText>
                                                                <IdCard className="h-4 w-4" />
                                                            </InputGroupText>
                                                        </InputGroupAddon>
                                                        <InputGroupInput
                                                            id="faculty_id_number"
                                                            value={data.faculty_id_number}
                                                            onChange={(event) => setData("faculty_id_number", event.target.value)}
                                                            placeholder="Optional internal staff ID"
                                                        />
                                                    </InputGroup>
                                                    <FieldDescription>Useful for faculty and other staff records.</FieldDescription>
                                                    <FieldError>{errors.faculty_id_number}</FieldError>
                                                </Field>

                                                <Field>
                                                    <FieldLabel htmlFor="record_id">External record ID</FieldLabel>
                                                    <InputGroup>
                                                        <InputGroupAddon align="inline-start">
                                                            <InputGroupText>
                                                                <Building2 className="h-4 w-4" />
                                                            </InputGroupText>
                                                        </InputGroupAddon>
                                                        <InputGroupInput
                                                            id="record_id"
                                                            value={data.record_id}
                                                            onChange={(event) => setData("record_id", event.target.value)}
                                                            placeholder="Optional ID from another system"
                                                        />
                                                    </InputGroup>
                                                    <FieldDescription>Helpful when this account is linked to HR, SIS, or another legacy source.</FieldDescription>
                                                    <FieldError>{errors.record_id}</FieldError>
                                                </Field>
                                            </FieldGroup>
                                        </FieldSet>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="access" className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-start gap-3">
                                            <div className="bg-primary/10 text-primary rounded-lg p-2">
                                                <ShieldCheck className="h-4 w-4" />
                                            </div>
                                            <div>
                                                <CardTitle>Direct access bundles</CardTitle>
                                                <CardDescription>
                                                    These are direct Spatie roles attached to the account. Treat them as exceptions to the main role.
                                                </CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-5">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="secondary">{data.roles.length} selected</Badge>
                                            {user.roles.map((role) => (
                                                <Badge key={role.id} variant="outline">
                                                    {role.name}
                                                </Badge>
                                            ))}
                                        </div>

                                        {permissions.length > 0 ? (
                                            <div className="grid gap-4 md:grid-cols-2">
                                                {permissions.map((roleOption) => {
                                                    const checked = data.roles.includes(roleOption.id);

                                                    return (
                                                        <label
                                                            key={roleOption.id}
                                                            htmlFor={`role-${roleOption.id}`}
                                                            className={`flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors ${
                                                                checked ? "border-primary bg-primary/5" : "bg-card hover:bg-muted/40"
                                                            }`}
                                                        >
                                                            <Checkbox
                                                                id={`role-${roleOption.id}`}
                                                                checked={checked}
                                                                onCheckedChange={() => toggleRole(roleOption.id)}
                                                                className="mt-0.5"
                                                            />
                                                            <div className="min-w-0 space-y-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-medium">{roleOption.name}</span>
                                                                    {checked ? <Badge>Attached</Badge> : null}
                                                                </div>
                                                                <p className="text-muted-foreground text-sm">
                                                                    Add this only when the user's default role still needs a targeted exception.
                                                                </p>
                                                            </div>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        ) : (
                                            <Empty className="border bg-muted/20">
                                                <EmptyHeader>
                                                    <EmptyMedia variant="icon">
                                                        <Users2 className="h-5 w-5" />
                                                    </EmptyMedia>
                                                    <EmptyTitle>No access bundles available</EmptyTitle>
                                                    <EmptyDescription>Create roles first if you want direct account-level bundles here.</EmptyDescription>
                                                </EmptyHeader>
                                            </Empty>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>

                        <Card>
                            <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1">
                                    <p className="font-medium">Ready to save?</p>
                                    <p className="text-muted-foreground text-sm">
                                        The current password stays untouched unless you filled the password section.
                                    </p>
                                </div>

                                <div className="flex flex-col-reverse gap-3 sm:flex-row">
                                    <Button variant="outline" asChild>
                                        <Link href={route("administrators.users.index")}>Cancel</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing || !isDirty} className="min-w-40">
                                        <Save className="mr-2 h-4 w-4" />
                                        {processing ? "Saving..." : "Save changes"}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
