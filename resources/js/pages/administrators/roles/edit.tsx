import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Head, router } from "@inertiajs/react";
import { ArrowLeft, Save, Shield } from "lucide-react";
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

interface PageProps {
    role: {
        id: number;
        name: string;
        guard_name: string;
        permissions: string[];
    };
    permissions: PermissionCategory[];
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

export default function RolesEdit({ role, permissions: permissionCategories, user }: PageProps) {
    const [formData, setFormData] = useState({
        name: role.name,
        permissions: role.permissions,
    });

    const [isSaving, setIsSaving] = useState(false);

    const handleTogglePermission = (permissionName: string) => {
        setFormData((prev) => ({
            ...prev,
            permissions: prev.permissions.includes(permissionName)
                ? prev.permissions.filter((p) => p !== permissionName)
                : [...prev.permissions, permissionName],
        }));
    };

    const handleSelectAllInCategory = (categoryPermissions: Permission[]) => {
        const newPermissions = [...formData.permissions];
        categoryPermissions.forEach((perm) => {
            if (!newPermissions.includes(perm.name)) {
                newPermissions.push(perm.name);
            }
        });
        setFormData((prev) => ({
            ...prev,
            permissions: newPermissions,
        }));
    };

    const handleDeselectAllInCategory = (categoryPermissions: Permission[]) => {
        setFormData((prev) => ({
            ...prev,
            permissions: prev.permissions.filter((p) => !categoryPermissions.some((cp) => cp.name === p)),
        }));
    };

    const handleSelectAll = () => {
        const allPermissions = permissionCategories.flatMap((cat) => cat.permissions.map((p) => p.name));
        setFormData((prev) => ({
            ...prev,
            permissions: allPermissions,
        }));
    };

    const handleDeselectAll = () => {
        setFormData((prev) => ({
            ...prev,
            permissions: [],
        }));
    };

    const handleSubmit = () => {
        setIsSaving(true);
        router.put(route("administrators.roles.update", role.id), formData, {
            onFinish: () => {
                setIsSaving(false);
            },
        });
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
        <AdminLayout user={user} title={`Edit Role: ${role.name}`}>
            <Head title={`Edit Role: ${role.name}`} />

            <div className="container mx-auto space-y-6 py-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" onClick={() => router.get(route("administrators.roles.index"))}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-3xl font-bold tracking-tight">
                                <Shield className="h-6 w-6" />
                                Edit Role
                            </h1>
                            <p className="text-muted-foreground">Update role details and manage permissions</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => router.get(route("administrators.roles.index"))}>
                            Cancel
                        </Button>
                        <Button onClick={handleSubmit} disabled={isSaving}>
                            <Save className="mr-2 h-4 w-4" />
                            {isSaving ? "Saving..." : "Save Changes"}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>Role Details</CardTitle>
                                <CardDescription>Basic information about the role</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Role Name</label>
                                    <input
                                        type="text"
                                        value={formData.name}
                                        onChange={(e) =>
                                            setFormData((prev) => ({
                                                ...prev,
                                                name: e.target.value,
                                            }))
                                        }
                                        className="w-full rounded-md border px-3 py-2"
                                        placeholder="e.g., custom_role"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Guard Name</label>
                                    <input type="text" value={role.guard_name} disabled className="bg-muted w-full rounded-md border px-3 py-2" />
                                    <p className="text-muted-foreground text-xs">The guard this role belongs to</p>
                                </div>
                                <div className="border-t pt-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Selected Permissions</span>
                                        <span className="text-muted-foreground text-sm">
                                            {formData.permissions.length} /{" "}
                                            {permissionCategories.reduce((acc, cat) => acc + cat.permissions.length, 0)}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex gap-2">
                                        <Button variant="outline" size="sm" onClick={handleSelectAll} className="flex-1">
                                            Select All
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={handleDeselectAll} className="flex-1">
                                            Deselect All
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Permissions</CardTitle>
                                <CardDescription>Manage which permissions this role has access to</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-6 md:grid-cols-2">
                                    {permissionCategories.map((category) => (
                                        <div key={category.category} className="rounded-lg border p-4">
                                            <div className="mb-3 flex items-center justify-between">
                                                <h3 className="font-semibold">{getCategoryLabel(category.category)}</h3>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleSelectAllInCategory(category.permissions)}
                                                        className="h-6 px-2 text-xs"
                                                    >
                                                        All
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDeselectAllInCategory(category.permissions)}
                                                        className="h-6 px-2 text-xs"
                                                    >
                                                        None
                                                    </Button>
                                                </div>
                                            </div>
                                            <ScrollArea className="h-64">
                                                <div className="space-y-2 pr-4">
                                                    {category.permissions.map((permission) => (
                                                        <div key={permission.id} className="flex items-start gap-2">
                                                            <Checkbox
                                                                id={permission.name}
                                                                checked={formData.permissions.includes(permission.name)}
                                                                onCheckedChange={() => handleTogglePermission(permission.name)}
                                                            />
                                                            <label
                                                                htmlFor={permission.name}
                                                                className="flex-1 cursor-pointer text-sm"
                                                                title={permission.name}
                                                            >
                                                                <div>{permission.action}</div>
                                                                {permission.description && (
                                                                    <div className="text-muted-foreground text-xs font-normal">
                                                                        {permission.description}
                                                                    </div>
                                                                )}
                                                            </label>
                                                        </div>
                                                    ))}
                                                </div>
                                            </ScrollArea>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
