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
import { ScrollArea } from "@/components/ui/scroll-area";
import { Head, router } from "@inertiajs/react";
import { Headset, Plus, Shield, ShieldCheck, Trash2, Users } from "lucide-react";
import { useState } from "react";

interface Permission {
    id: number;
    name: string;
    action: string;
    description: string | null;
    guard_name: string;
}

interface PermissionCategory {
    category: string;
    permissions: Permission[];
}

interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: string[];
    permissions_count: number;
    users_count: number;
}

interface UserWithRole {
    id: number;
    name: string;
    email: string;
    role: string | null;
    role_label: string | null;
}

interface PageProps {
    roles: Role[];
    permissions: PermissionCategory[];
    available_roles: { value: string; label: string }[];
    users_with_roles: UserWithRole[];
    flash?: {
        type: string;
        message: string;
    };
    user: {
        name: string;
        email: string;
        avatar: string | null;
        role: string;
        role_label: string | null;
        permissions: string[];
    };
}

declare const route: any;

export default function RolesIndex({ roles, permissions, available_roles, users_with_roles, flash, user }: PageProps) {
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isAssignDialogOpen, setIsAssignDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);
    const [selectedUser, setSelectedUser] = useState<UserWithRole | null>(null);

    const [formData, setFormData] = useState({
        name: "",
        permissions: [] as string[],
    });

    const openCreateDialog = () => {
        setFormData({ name: "", permissions: [] });
        setIsCreateDialogOpen(true);
    };

    const openEditDialog = (role: Role) => {
        setSelectedRole(role);
        setFormData({
            name: role.name,
            permissions: role.permissions,
        });
        setIsEditDialogOpen(true);
    };

    const openAssignDialog = () => {
        setSelectedUser(null);
        setIsAssignDialogOpen(true);
    };

    const openDeleteDialog = (role: Role) => {
        setSelectedRole(role);
        setIsDeleteDialogOpen(true);
    };

    const handleCreateRole = () => {
        router.post(route("administrators.roles.store"), formData, {
            onFinish: () => {
                setIsCreateDialogOpen(false);
                setFormData({ name: "", permissions: [] });
            },
        });
    };

    const handleUpdateRole = () => {
        if (!selectedRole) return;

        router.put(route("administrators.roles.update", selectedRole.id), formData, {
            onFinish: () => {
                setIsEditDialogOpen(false);
                setSelectedRole(null);
                setFormData({ name: "", permissions: [] });
            },
        });
    };

    const handleAssignRole = () => {
        if (!selectedUser) return;

        router.post(
            route("administrators.roles.assign"),
            {
                user_id: selectedUser.id,
                role_name: selectedRole?.name || "",
            },
            {
                onFinish: () => {
                    setIsAssignDialogOpen(false);
                    setSelectedUser(null);
                },
            },
        );
    };

    const handleDeleteRole = () => {
        if (!selectedRole) return;

        router.delete(route("administrators.roles.destroy", selectedRole.id), {
            onFinish: () => {
                setIsDeleteDialogOpen(false);
                setSelectedRole(null);
            },
        });
    };

    const togglePermission = (permissionName: string) => {
        setFormData((prev) => ({
            ...prev,
            permissions: prev.permissions.includes(permissionName)
                ? prev.permissions.filter((p) => p !== permissionName)
                : [...prev.permissions, permissionName],
        }));
    };

    const selectAllPermissions = () => {
        const allPermissions = permissions.flatMap((cat) => cat.permissions.map((p) => p.name));
        setFormData((prev) => ({
            ...prev,
            permissions: allPermissions,
        }));
    };

    const deselectAllPermissions = () => {
        setFormData((prev) => ({
            ...prev,
            permissions: [],
        }));
    };

    const getCategoryLabel = (category: string): string => {
        const labels: Record<string, string> = {
            // Model-based permissions
            User: "Users",
            Student: "Students",
            Faculty: "Faculty",
            Course: "Courses",
            Subject: "Subjects",
            Class: "Classes",
            Enrollment: "Enrollments",
            Department: "Departments",
            School: "Schools",
            Announcement: "Announcements",
            Event: "Events",
            Transaction: "Transactions",
            Invoice: "Invoices",
            Payment: "Payments",
            Clearance: "Clearances",
            InventoryProduct: "Inventory Products",
            InventoryBorrowing: "Inventory Borrowing",
            Book: "Books",
            Author: "Authors",
            Category: "Categories",
            BorrowRecord: "Borrow Records",
            HelpTicket: "Help Tickets",
            GeneralSetting: "General Settings",
            SanityContent: "Sanity Content",
            OnboardingFeature: "Onboarding Features",
            Role: "Roles",
            Permission: "Permissions",
            Room: "Rooms",
            Schedule: "Schedules",
            MedicalRecord: "Medical Records",
            AuditLog: "Audit Logs",
            // Custom permissions
            view_dashboard: "Dashboard",
            view_audit_logs: "Audit Logs",
            manage_settings: "Settings",
            manage_school: "School",
            manage_enrollments: "Enrollments",
            quick_enroll: "Quick Enroll",
            view_tuition_fees: "Tuition Fees",
            manage_tuition_fees: "Manage Tuition Fees",
            process_payments: "Process Payments",
            view_payments: "View Payments",
            manage_clearance: "Clearance",
            view_clearance: "View Clearance",
            generate_reports: "Reports",
            export_data: "Export",
            import_data: "Import",
            manage_inventory: "Inventory",
            borrow_inventory: "Borrow Inventory",
            approve_borrowing: "Approve Borrowing",
            manage_mail: "Mail",
            view_mail: "View Mail",
            send_mail: "Send Mail",
            manage_announcements: "Announcements",
            view_announcements: "View Announcements",
            manage_events: "Events",
            view_events: "View Events",
            manage_class_schedules: "Class Schedules",
            view_class_schedules: "View Schedules",
            manage_subjects: "Subjects",
            view_subjects: "View Subjects",
            manage_courses: "Courses",
            view_courses: "View Courses",
            manage_faculty: "Faculty",
            view_faculty: "View Faculty",
            manage_departments: "Departments",
            view_departments: "View Departments",
            manage_rooms: "Rooms",
            view_rooms: "View Rooms",
            manage_account: "Account",
            view_account: "View Account",
            view_id_card: "ID Card",
            manage_id_card: "Manage ID Card",
            verify_id_card: "Verify ID Card",
            view_onboarding: "Onboarding",
            manage_onboarding: "Manage Onboarding",
            manage_tokens: "Tokens",
            view_tokens: "View Tokens",
            manage_sanity_content: "Sanity Content",
            view_sanity_content: "View Sanity Content",
            other: "Other",
        };

        return labels[category] || category;
    };

    return (
        <AdminLayout user={user} title="Roles & Permissions">
            <Head title="Roles & Permissions" />

            <div className="container mx-auto space-y-6 py-6">
                {flash && (
                    <div
                        className={`rounded-md p-4 ${
                            flash.type === "success"
                                ? "border border-green-200 bg-green-50 text-green-800"
                                : "border border-red-200 bg-red-50 text-red-800"
                        }`}
                    >
                        {flash.message}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Roles & Permissions</h1>
                        <p className="text-muted-foreground">Manage user roles and their permissions</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={openAssignDialog}>
                            <Users className="mr-2 h-4 w-4" />
                            Assign Role
                        </Button>
                        <Button onClick={openCreateDialog}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Role
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Roles</CardTitle>
                            <Shield className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{roles.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Permissions</CardTitle>
                            <ShieldCheck className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{permissions.reduce((acc, cat) => acc + cat.permissions.length, 0)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">System Roles</CardTitle>
                            <Headset className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {roles.filter((r) => ["developer", "admin", "super_admin"].includes(r.name)).length}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                        <CardDescription>View and manage all roles in the system</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="rounded-md border">
                            <table className="w-full">
                                <thead>
                                    <tr className="bg-muted/50 border-b">
                                        <th className="px-4 py-3 text-left text-sm font-medium">Role</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium">Permissions</th>
                                        <th className="px-4 py-3 text-left text-sm font-medium">Users</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.map((role) => (
                                        <tr key={role.id} className="border-b">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Shield className="text-primary h-4 w-4" />
                                                    <span className="font-medium">{role.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {role.permissions.slice(0, 5).map((perm) => (
                                                        <Badge key={perm} variant="secondary" className="text-xs">
                                                            {perm.split(":")[1] || perm}
                                                        </Badge>
                                                    ))}
                                                    {role.permissions_count > 5 && (
                                                        <Badge variant="outline" className="text-xs">
                                                            +{role.permissions_count - 5} more
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant="outline">{role.users_count} user(s)</Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => router.get(route("administrators.roles.edit", role.id))}
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600 hover:text-red-700"
                                                        onClick={() => openDeleteDialog(role)}
                                                        disabled={role.users_count > 0}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Permissions by Category</CardTitle>
                        <CardDescription>View all available permissions grouped by category</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {permissions.map((category) => (
                                <div key={category.category}>
                                    <h3 className="mb-2 text-sm font-semibold">{getCategoryLabel(category.category)}</h3>
                                    <div className="flex flex-wrap gap-2">
                                        {category.permissions.map((permission) => (
                                            <Badge key={permission.id} variant="outline" className="text-xs">
                                                {permission.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-h-[80vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Create New Role</DialogTitle>
                        <DialogDescription>Create a new role and assign permissions</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Role Name</label>
                            <input
                                type="text"
                                value={formData.name}
                                onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                                className="w-full rounded-md border px-3 py-2"
                                placeholder="e.g., custom_role"
                            />
                        </div>
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-sm font-medium">Permissions</label>
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={selectAllPermissions}>
                                        Select All
                                    </Button>
                                    <Button type="button" variant="outline" size="sm" onClick={deselectAllPermissions}>
                                        Deselect All
                                    </Button>
                                </div>
                            </div>
                            <ScrollArea className="h-64">
                                <div className="space-y-4 pr-4">
                                    {permissions.map((category) => (
                                        <div key={category.category}>
                                            <h4 className="text-muted-foreground mb-2 text-xs font-semibold">
                                                {getCategoryLabel(category.category)}
                                            </h4>
                                            <div className="space-y-2">
                                                {category.permissions.map((permission) => (
                                                    <div key={permission.id} className="flex items-center gap-2">
                                                        <Checkbox
                                                            id={permission.name}
                                                            checked={formData.permissions.includes(permission.name)}
                                                            onCheckedChange={() => togglePermission(permission.name)}
                                                        />
                                                        <label htmlFor={permission.name} className="cursor-pointer text-sm">
                                                            {permission.name}
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreateRole} disabled={!formData.name}>
                            Create Role
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-h-[80vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Role</DialogTitle>
                        <DialogDescription>Update role name and permissions</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Role Name</label>
                            <input
                                type="text"
                                value={formData.name}
                                onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                                className="w-full rounded-md border px-3 py-2"
                            />
                        </div>
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-sm font-medium">Permissions</label>
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={selectAllPermissions}>
                                        Select All
                                    </Button>
                                    <Button type="button" variant="outline" size="sm" onClick={deselectAllPermissions}>
                                        Deselect All
                                    </Button>
                                </div>
                            </div>
                            <ScrollArea className="h-64">
                                <div className="space-y-4 pr-4">
                                    {permissions.map((category) => (
                                        <div key={category.category}>
                                            <h4 className="text-muted-foreground mb-2 text-xs font-semibold">
                                                {getCategoryLabel(category.category)}
                                            </h4>
                                            <div className="space-y-2">
                                                {category.permissions.map((permission) => (
                                                    <div key={permission.id} className="flex items-center gap-2">
                                                        <Checkbox
                                                            id={`edit-${permission.name}`}
                                                            checked={formData.permissions.includes(permission.name)}
                                                            onCheckedChange={() => togglePermission(permission.name)}
                                                        />
                                                        <label htmlFor={`edit-${permission.name}`} className="cursor-pointer text-sm">
                                                            {permission.name}
                                                        </label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </ScrollArea>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleUpdateRole} disabled={!formData.name}>
                            Save Changes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isAssignDialogOpen} onOpenChange={setIsAssignDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Assign Role to User</DialogTitle>
                        <DialogDescription>Select a user and role to assign</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Select User</label>
                            <select
                                value={selectedUser?.id || ""}
                                onChange={(e) => {
                                    const user = users_with_roles.find((u) => u.id === parseInt(e.target.value));
                                    setSelectedUser(user || null);
                                }}
                                className="w-full rounded-md border px-3 py-2"
                            >
                                <option value="">Select a user...</option>
                                {users_with_roles.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name} ({user.email})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Select Role</label>
                            <select
                                value={selectedRole?.name || ""}
                                onChange={(e) => {
                                    const role = roles.find((r) => r.name === e.target.value);
                                    setSelectedRole(role || null);
                                }}
                                className="w-full rounded-md border px-3 py-2"
                            >
                                <option value="">Select a role...</option>
                                {roles.map((role) => (
                                    <option key={role.id} value={role.name}>
                                        {role.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {selectedUser && selectedRole && (
                            <div className="bg-muted rounded-md p-3">
                                <p className="text-sm">
                                    <span className="font-medium">{selectedUser.name}</span> will be assigned the role of{" "}
                                    <span className="font-medium">{selectedRole.name}</span>
                                </p>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsAssignDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleAssignRole} disabled={!selectedUser || !selectedRole}>
                            Assign Role
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Role</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete the role "{selectedRole?.name}"? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeleteRole} className="bg-red-600 hover:bg-red-700">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
