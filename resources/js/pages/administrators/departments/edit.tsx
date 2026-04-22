import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { Building2, Save } from "lucide-react";
import type { FormEvent } from "react";
import { route } from "ziggy-js";

declare const route: any;

interface SchoolOption {
    id: number;
    name: string;
}

interface DepartmentRecord {
    id: number;
    school_id: number;
    name: string;
    code: string;
    description: string | null;
    head_name: string | null;
    head_email: string | null;
    location: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
}

interface Props {
    user: User;
    department: DepartmentRecord | null;
    schools: SchoolOption[];
}

export default function DepartmentEdit({ user, department, schools }: Props) {
    const form = useForm({
        school_id: String(department?.school_id ?? ""),
        name: department?.name ?? "",
        code: department?.code ?? "",
        description: department?.description ?? "",
        head_name: department?.head_name ?? "",
        head_email: department?.head_email ?? "",
        location: department?.location ?? "",
        phone: department?.phone ?? "",
        email: department?.email ?? "",
        is_active: department?.is_active ?? true,
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (department) {
            form.put(route("administrators.departments.update", department.id), {
                preserveScroll: true,
            });
            return;
        }

        form.post(route("administrators.departments.store"), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout user={user} title={department ? "Edit Department" : "Add Department"}>
            <Head title={`Administrators • ${department ? "Edit" : "Add"} Department`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-blue-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>{department ? "Update Department" : "Create Department"}</CardTitle>
                                <CardDescription>Manage department details and school assignment.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.departments.index")}>Back to departments</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {department ? "Save changes" : "Create department"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Department Details</CardTitle>
                        <CardDescription>Fill in the department information below.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Department Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData("name", e.target.value)}
                                placeholder="e.g. College of Engineering"
                            />
                            {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData("code", e.target.value.toUpperCase())}
                                placeholder="e.g. COE"
                            />
                            {form.errors.code && <p className="text-destructive text-xs">{form.errors.code}</p>}
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="school">School</Label>
                            <Select
                                value={form.data.school_id}
                                onValueChange={(value) => form.setData("school_id", value)}
                            >
                                <SelectTrigger id="school">
                                    <SelectValue placeholder="Select a school" />
                                </SelectTrigger>
                                <SelectContent>
                                    {schools.map((school) => (
                                        <SelectItem key={school.id} value={String(school.id)}>
                                            {school.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.school_id && <p className="text-destructive text-xs">{form.errors.school_id}</p>}
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(e) => form.setData("description", e.target.value)}
                                placeholder="Optional description"
                                rows={3}
                            />
                            {form.errors.description && <p className="text-destructive text-xs">{form.errors.description}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="head_name">Head Name</Label>
                            <Input
                                id="head_name"
                                value={form.data.head_name}
                                onChange={(e) => form.setData("head_name", e.target.value)}
                                placeholder="Optional"
                            />
                            {form.errors.head_name && <p className="text-destructive text-xs">{form.errors.head_name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="head_email">Head Email</Label>
                            <Input
                                id="head_email"
                                type="email"
                                value={form.data.head_email}
                                onChange={(e) => form.setData("head_email", e.target.value)}
                                placeholder="Optional"
                            />
                            {form.errors.head_email && <p className="text-destructive text-xs">{form.errors.head_email}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="location">Location</Label>
                            <Input
                                id="location"
                                value={form.data.location}
                                onChange={(e) => form.setData("location", e.target.value)}
                                placeholder="Optional"
                            />
                            {form.errors.location && <p className="text-destructive text-xs">{form.errors.location}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                value={form.data.phone}
                                onChange={(e) => form.setData("phone", e.target.value)}
                                placeholder="Optional"
                            />
                            {form.errors.phone && <p className="text-destructive text-xs">{form.errors.phone}</p>}
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="email">Department Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData("email", e.target.value)}
                                placeholder="Optional"
                            />
                            {form.errors.email && <p className="text-destructive text-xs">{form.errors.email}</p>}
                        </div>

                        <div className="bg-muted/30 border-border/50 flex items-center gap-3 rounded-lg border p-3 sm:col-span-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => form.setData("is_active", Boolean(checked))}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer select-none">
                                Department is active
                            </Label>
                        </div>
                        {form.errors.is_active && <p className="text-destructive text-xs sm:col-span-2">{form.errors.is_active}</p>}
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route("administrators.departments.index")}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        <Save className="mr-2 h-4 w-4" />
                        {form.processing ? "Saving..." : department ? "Save Changes" : "Create Department"}
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
