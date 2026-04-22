import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { Building2, CheckCircle2, Edit, Plus, Search, Trash2, XCircle } from "lucide-react";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";

declare const route: any;

interface SchoolOption {
    id: number;
    name: string;
}

interface DepartmentItem {
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
    school: SchoolOption | null;
    users_count: number;
    courses_count: number;
    created_at: string | null;
}

interface Props {
    user: User;
    departments: DepartmentItem[];
    stats: {
        total: number;
        active: number;
        inactive: number;
    };
    schools: SchoolOption[];
    filters: {
        search?: string | null;
        status?: string | null;
    };
    flash?: {
        type: string;
        message: string;
    };
}

const defaultFormData = {
    school_id: "",
    name: "",
    code: "",
    description: "",
    head_name: "",
    head_email: "",
    location: "",
    phone: "",
    email: "",
    is_active: true,
};

export default function DepartmentsIndex({ user, departments, stats, schools, filters, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState<DepartmentItem | null>(null);
    const [deletingDepartment, setDeletingDepartment] = useState<DepartmentItem | null>(null);

    const createForm = useForm({ ...defaultFormData });
    const editForm = useForm({ ...defaultFormData });

    useEffect(() => {
        if (!flash?.message) return;
        if (flash.type === "success") {
            toast.success(flash.message);
        } else if (flash.type === "error") {
            toast.error(flash.message);
        } else {
            toast.message(flash.message);
        }
    }, [flash]);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(
            route("administrators.departments.index"),
            { search: term || null, status: filters.status },
            { preserveState: true, replace: true },
        );
    }, 300);

    const openCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const openEdit = (department: DepartmentItem) => {
        editForm.setData({
            school_id: String(department.school_id ?? ""),
            name: department.name,
            code: department.code,
            description: department.description ?? "",
            head_name: department.head_name ?? "",
            head_email: department.head_email ?? "",
            location: department.location ?? "",
            phone: department.phone ?? "",
            email: department.email ?? "",
            is_active: department.is_active,
        });
        editForm.clearErrors();
        setEditingDepartment(department);
    };

    const handleCreateSubmit = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route("administrators.departments.store"), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEditSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!editingDepartment) return;
        editForm.put(route("administrators.departments.update", editingDepartment.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingDepartment(null);
                editForm.reset();
            },
        });
    };

    const handleDelete = () => {
        if (!deletingDepartment) return;
        router.delete(route("administrators.departments.destroy", deletingDepartment.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setDeletingDepartment(null),
        });
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            route("administrators.departments.index"),
            { search: search.trim() || null, status: value === "all" ? null : value },
            { preserveState: true, replace: true },
        );
    };

    const statCards = useMemo(
        () => [
            { label: "Total Departments", value: stats.total, hint: "All departments in the system." },
            { label: "Active", value: stats.active, hint: "Currently active departments." },
            { label: "Inactive", value: stats.inactive, hint: "Temporarily disabled departments." },
        ],
        [stats],
    );

    const DepartmentFormFields = ({ form, isEdit }: { form: typeof createForm; isEdit?: boolean }) => (
        <div className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-name" : "create-name"}>Department Name</Label>
                    <Input
                        id={isEdit ? "edit-name" : "create-name"}
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        placeholder="e.g. College of Engineering"
                    />
                    {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-code" : "create-code"}>Code</Label>
                    <Input
                        id={isEdit ? "edit-code" : "create-code"}
                        value={form.data.code}
                        onChange={(e) => form.setData("code", e.target.value.toUpperCase())}
                        placeholder="e.g. COE"
                    />
                    {form.errors.code && <p className="text-destructive text-xs">{form.errors.code}</p>}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor={isEdit ? "edit-school" : "create-school"}>School</Label>
                <Select
                    value={form.data.school_id}
                    onValueChange={(value) => form.setData("school_id", value)}
                >
                    <SelectTrigger id={isEdit ? "edit-school" : "create-school"}>
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

            <div className="space-y-2">
                <Label htmlFor={isEdit ? "edit-description" : "create-description"}>Description</Label>
                <Textarea
                    id={isEdit ? "edit-description" : "create-description"}
                    value={form.data.description}
                    onChange={(e) => form.setData("description", e.target.value)}
                    placeholder="Optional description"
                    rows={3}
                />
                {form.errors.description && <p className="text-destructive text-xs">{form.errors.description}</p>}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-head-name" : "create-head-name"}>Head Name</Label>
                    <Input
                        id={isEdit ? "edit-head-name" : "create-head-name"}
                        value={form.data.head_name}
                        onChange={(e) => form.setData("head_name", e.target.value)}
                        placeholder="Optional"
                    />
                    {form.errors.head_name && <p className="text-destructive text-xs">{form.errors.head_name}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-head-email" : "create-head-email"}>Head Email</Label>
                    <Input
                        id={isEdit ? "edit-head-email" : "create-head-email"}
                        type="email"
                        value={form.data.head_email}
                        onChange={(e) => form.setData("head_email", e.target.value)}
                        placeholder="Optional"
                    />
                    {form.errors.head_email && <p className="text-destructive text-xs">{form.errors.head_email}</p>}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-location" : "create-location"}>Location</Label>
                    <Input
                        id={isEdit ? "edit-location" : "create-location"}
                        value={form.data.location}
                        onChange={(e) => form.setData("location", e.target.value)}
                        placeholder="Optional"
                    />
                    {form.errors.location && <p className="text-destructive text-xs">{form.errors.location}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-phone" : "create-phone"}>Phone</Label>
                    <Input
                        id={isEdit ? "edit-phone" : "create-phone"}
                        value={form.data.phone}
                        onChange={(e) => form.setData("phone", e.target.value)}
                        placeholder="Optional"
                    />
                    {form.errors.phone && <p className="text-destructive text-xs">{form.errors.phone}</p>}
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor={isEdit ? "edit-email" : "create-email"}>Department Email</Label>
                <Input
                    id={isEdit ? "edit-email" : "create-email"}
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData("email", e.target.value)}
                    placeholder="Optional"
                />
                {form.errors.email && <p className="text-destructive text-xs">{form.errors.email}</p>}
            </div>

            <div className="bg-muted/30 border-border/50 flex items-center gap-3 rounded-lg border p-3">
                <Checkbox
                    id={isEdit ? "edit-is-active" : "create-is-active"}
                    checked={form.data.is_active}
                    onCheckedChange={(checked) => form.setData("is_active", Boolean(checked))}
                />
                <Label htmlFor={isEdit ? "edit-is-active" : "create-is-active"} className="cursor-pointer select-none">
                    Department is active
                </Label>
            </div>
            {form.errors.is_active && <p className="text-destructive text-xs">{form.errors.is_active}</p>}
        </div>
    );

    return (
        <AdminLayout user={user} title="Departments">
            <Head title="Administrators • Departments" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-blue-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>Department Management</CardTitle>
                                <CardDescription>Organize academic and administrative departments.</CardDescription>
                            </div>
                        </div>
                        <Button onClick={openCreate} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add Department
                        </Button>
                    </CardHeader>
                </Card>

                <div className="grid gap-3 md:grid-cols-3">
                    {statCards.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">{stat.label}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1">
                                <div className="text-2xl font-semibold tracking-tight">{stat.value}</div>
                                <div className="text-muted-foreground text-xs">{stat.hint}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Departments</CardTitle>
                            <CardDescription>Manage departments, assign schools, and set heads.</CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="relative w-full md:w-72">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search departments..."
                                    value={search}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setSearch(value);
                                        handleSearch(value);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={filters.status ?? "all"} onValueChange={handleStatusFilter}>
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>School</TableHead>
                                    <TableHead>Head</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Users</TableHead>
                                    <TableHead>Courses</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {departments.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="text-muted-foreground h-24 text-center text-sm">
                                            No departments found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    departments.map((department) => (
                                        <TableRow key={department.id}>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium">{department.name}</span>
                                                    {department.description && (
                                                        <span className="text-muted-foreground max-w-xs truncate text-xs">
                                                            {department.description}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="font-mono text-xs">
                                                    {department.code}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {department.school?.name ?? "—"}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {department.head_name ?? "—"}
                                            </TableCell>
                                            <TableCell>
                                                {department.is_active ? (
                                                    <Badge variant="default" className="gap-1">
                                                        <CheckCircle2 className="h-3 w-3" /> Active
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="gap-1">
                                                        <XCircle className="h-3 w-3" /> Inactive
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="text-xs">
                                                    {department.users_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="text-xs">
                                                    {department.courses_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-primary hover:bg-primary/10 h-8 w-8"
                                                        onClick={() => openEdit(department)}
                                                        title="Edit"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:bg-destructive/10 h-8 w-8"
                                                        onClick={() => setDeletingDepartment(department)}
                                                        title="Delete"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Create Modal */}
            <Dialog open={isCreateOpen} onOpenChange={(open) => !open && setIsCreateOpen(false)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Add Department</DialogTitle>
                        <DialogDescription>Create a new department and assign it to a school.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <DepartmentFormFields form={createForm} />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing ? "Creating..." : "Create Department"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog open={!!editingDepartment} onOpenChange={(open) => !open && setEditingDepartment(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Edit Department</DialogTitle>
                        <DialogDescription>Update department details and assignments.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <DepartmentFormFields form={editForm} isEdit />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingDepartment(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={!!deletingDepartment} onOpenChange={(open) => !open && setDeletingDepartment(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete department?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will remove "{deletingDepartment?.name}" and unassign all linked users and courses. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
