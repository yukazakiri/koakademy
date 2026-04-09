import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

interface PageProps {
    roles: Record<string, string>;
    schools: { id: number; name: string }[];
    departments: { id: number; name: string; school_id: number }[];
    permissions: { id: number; name: string }[];
    user: any;
}

export default function UserCreate({ roles, schools, departments, permissions, user }: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        role: "",
        school_id: "",
        department_id: "",
        faculty_id_number: "",
        record_id: "",
        roles: [] as number[],
    });

    const filteredDepartments = departments.filter((dept) => dept.school_id.toString() === data.school_id?.toString());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route("administrators.users.store"));
    };

    const toggleRole = (roleId: number) => {
        const currentRoles = [...data.roles];
        if (currentRoles.includes(roleId)) {
            setData(
                "roles",
                currentRoles.filter((id) => id !== roleId),
            );
        } else {
            setData("roles", [...currentRoles, roleId]);
        }
    };

    return (
        <AdminLayout user={user} title="Create User">
            <Head title="Create User" />

            <div className="mx-auto flex max-w-4xl flex-col gap-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Create User</h2>
                    <p className="text-muted-foreground">Add a new user to the system.</p>
                </div>

                <form onSubmit={submit}>
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profile Information</CardTitle>
                                <CardDescription>Basic user details and login credentials.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Full Name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        placeholder="e.g. Juan Dela Cruz"
                                        required
                                    />
                                    {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email Address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData("email", e.target.value)}
                                        placeholder="user@koakademy.edu"
                                        required
                                    />
                                    {errors.email && <p className="text-sm text-red-500">{errors.email}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData("password", e.target.value)}
                                        required
                                    />
                                    {errors.password && <p className="text-sm text-red-500">{errors.password}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="password_confirmation">Confirm Password</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData("password_confirmation", e.target.value)}
                                        required
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Organization & Role</CardTitle>
                                <CardDescription>Assign user to a school, department, and system role.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="role">System Role</Label>
                                    <Select onValueChange={(val) => setData("role", val)} value={data.role}>
                                        <SelectTrigger>
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
                                    {errors.role && <p className="text-sm text-red-500">{errors.role}</p>}
                                </div>

                                <Separator className="sm:col-span-2" />

                                <div className="space-y-2">
                                    <Label htmlFor="school">School / College</Label>
                                    <Select
                                        onValueChange={(val) => {
                                            setData((prev) => ({ ...prev, school_id: val, department_id: "" }));
                                        }}
                                        value={data.school_id}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select School" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {schools.map((school) => (
                                                <SelectItem key={school.id} value={school.id.toString()}>
                                                    {school.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.school_id && <p className="text-sm text-red-500">{errors.school_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="department">Department</Label>
                                    <Select
                                        onValueChange={(val) => setData("department_id", val)}
                                        value={data.department_id}
                                        disabled={!data.school_id}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredDepartments.map((dept) => (
                                                <SelectItem key={dept.id} value={dept.id.toString()}>
                                                    {dept.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.department_id && <p className="text-sm text-red-500">{errors.department_id}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="faculty_id">Faculty ID Number</Label>
                                    <Input
                                        id="faculty_id"
                                        value={data.faculty_id_number}
                                        onChange={(e) => setData("faculty_id_number", e.target.value)}
                                        placeholder="Optional"
                                    />
                                    {errors.faculty_id_number && <p className="text-sm text-red-500">{errors.faculty_id_number}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="record_id">External Record ID</Label>
                                    <Input
                                        id="record_id"
                                        value={data.record_id}
                                        onChange={(e) => setData("record_id", e.target.value)}
                                        placeholder="Optional"
                                    />
                                    {errors.record_id && <p className="text-sm text-red-500">{errors.record_id}</p>}
                                </div>
                            </CardContent>
                        </Card>

                        {permissions.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Additional Permissions</CardTitle>
                                    <CardDescription>Grant specific permissions via roles.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                                        {permissions.map((role) => (
                                            <div key={role.id} className="flex items-center space-x-2">
                                                <Checkbox
                                                    id={`perm-${role.id}`}
                                                    checked={data.roles.includes(role.id)}
                                                    onCheckedChange={() => toggleRole(role.id)}
                                                />
                                                <Label htmlFor={`perm-${role.id}`} className="cursor-pointer text-sm font-normal">
                                                    {role.name}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex justify-end gap-4">
                            <Button variant="outline" asChild>
                                <Link href={route("administrators.users.index")}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? "Creating..." : "Create User"}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
