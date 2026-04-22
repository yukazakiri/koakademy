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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { router, useForm } from "@inertiajs/react";
import { AlertTriangle, Building2, Calendar, Check, GraduationCap, Loader2, Mail, MapPin, Pencil, Phone, Plus, Save, Trash2 } from "lucide-react";
import { FormEvent, useEffect, useState } from "react";
import { toast } from "sonner";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { School, SystemManagementPageProps } from "./types";

interface CreateSchoolFormData {
    name: string;
    code: string;
    description: string;
    location: string;
    phone: string;
    email: string;
    dean_name: string;
    dean_email: string;
}

interface SchoolDetailsFormData {
    school_id: string;
    name: string;
    code: string;
    description: string;
    location: string;
    phone: string;
    email: string;
}

export default function SystemManagementSchoolPage({
    user,
    active_school,
    schools,
    access,
    system_semester,
    system_school_starting_date,
    system_school_ending_date,
    available_semesters,
}: SystemManagementPageProps) {
    const [isAddSchoolOpen, setIsAddSchoolOpen] = useState(false);
    const [isEditSchoolOpen, setIsEditSchoolOpen] = useState(false);
    const [editingSchool, setEditingSchool] = useState<School | null>(null);
    const [deletingSchool, setDeletingSchool] = useState<School | null>(null);

    const schoolForm = useForm({ school_id: active_school?.id?.toString() || "" });
    const schoolDetailsForm = useForm<SchoolDetailsFormData>({
        school_id: active_school?.id?.toString() || "",
        name: active_school?.name || "",
        code: active_school?.code || "",
        description: active_school?.description || "",
        location: active_school?.location || "",
        phone: active_school?.phone || "",
        email: active_school?.email || "",
    });
    const createSchoolForm = useForm<CreateSchoolFormData>({
        name: "",
        code: "",
        description: "",
        location: "",
        phone: "",
        email: "",
        dean_name: "",
        dean_email: "",
    });
    const editSchoolForm = useForm<CreateSchoolFormData>({
        name: "",
        code: "",
        description: "",
        location: "",
        phone: "",
        email: "",
        dean_name: "",
        dean_email: "",
    });

    const academicCalendarForm = useForm({
        semester: system_semester ?? 1,
        school_starting_date: system_school_starting_date ?? "",
        school_ending_date: system_school_ending_date ?? "",
    });

    useEffect(() => {
        academicCalendarForm.setData({
            semester: system_semester ?? 1,
            school_starting_date: system_school_starting_date ?? "",
            school_ending_date: system_school_ending_date ?? "",
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [system_semester, system_school_starting_date, system_school_ending_date]);

    useEffect(() => {
        if (!active_school) {
            return;
        }

        schoolDetailsForm.setData({
            school_id: active_school.id.toString(),
            name: active_school.name,
            code: active_school.code,
            description: active_school.description || "",
            location: active_school.location || "",
            phone: active_school.phone || "",
            email: active_school.email || "",
        });
    }, [active_school]);

    const handleCreateSchool = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        createSchoolForm.post(route("administrators.system-management.school.store"), {
            onSuccess: () => {
                toast.success("School created successfully.");
                setIsAddSchoolOpen(false);
                createSchoolForm.reset();
            },
            onError: () => toast.error("Failed to create school."),
        });
    };

    const openEditDialog = (school: School): void => {
        setEditingSchool(school);
        editSchoolForm.setData({
            name: school.name,
            code: school.code,
            description: school.description || "",
            location: school.location || "",
            phone: school.phone || "",
            email: school.email || "",
            dean_name: school.dean_name || "",
            dean_email: school.dean_email || "",
        });
        setIsEditSchoolOpen(true);
    };

    const handleUpdateSchool = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (!editingSchool) {
            return;
        }

        editSchoolForm.put(route("administrators.system-management.schools.update", editingSchool.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("School updated successfully.");
                setIsEditSchoolOpen(false);
                setEditingSchool(null);
                editSchoolForm.reset();
            },
            onError: () => toast.error("Failed to update school."),
        });
    };

    const handleToggleSchoolStatus = (school: School): void => {
        router.patch(
            route("administrators.system-management.schools.status.update", school.id),
            { is_active: !school.is_active },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(school.is_active ? "School deactivated successfully." : "School activated successfully.");
                },
                onError: () => toast.error("Failed to update school status."),
            },
        );
    };

    const handleDeleteSchool = (): void => {
        if (!deletingSchool) {
            return;
        }

        router.delete(route("administrators.system-management.schools.destroy", deletingSchool.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("School archived successfully.");
                setDeletingSchool(null);
            },
            onError: () => {
                toast.error("Unable to archive school.");
                setDeletingSchool(null);
            },
        });
    };

    const handleForceDeleteSchool = (): void => {
        if (!deletingSchool) {
            return;
        }

        router.delete(route("administrators.system-management.schools.force-destroy", deletingSchool.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("School permanently deleted with related records.");
                setDeletingSchool(null);
            },
            onError: () => {
                toast.error("Unable to force delete school.");
                setDeletingSchool(null);
            },
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="school"
            heading="School & Campus Config"
            description="Manage your active operating environment and configure the directory of campus profiles."
        >
            <Tabs defaultValue="active" className="w-full space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <TabsList className="grid w-full grid-cols-2 sm:w-[400px]">
                        <TabsTrigger value="active" className="text-sm">
                            Active Environment
                        </TabsTrigger>
                        <TabsTrigger value="directory" className="text-sm">
                            School Directory
                        </TabsTrigger>
                    </TabsList>

                    <Button onClick={() => setIsAddSchoolOpen(true)} className="w-full shrink-0 shadow-sm sm:w-auto">
                        <Plus className="mr-2 h-4 w-4" />
                        Add New Campus
                    </Button>
                </div>

                <TabsContent value="active" className="space-y-6 focus-visible:ring-0 focus-visible:outline-none">
                    <div className="grid gap-6 lg:grid-cols-12">
                        {/* Sidebar: Active School Switcher */}
                        <div className="space-y-6 lg:col-span-4 xl:col-span-3">
                            <Card className="border-muted-foreground/10 flex h-full flex-col shadow-sm">
                                <CardHeader className="bg-muted/30 border-b pb-4">
                                    <div className="mb-1 flex items-center gap-2">
                                        <div className="bg-primary/10 text-primary rounded-md p-1.5">
                                            <Building2 className="h-4 w-4" />
                                        </div>
                                        <CardTitle className="text-base">Current Context</CardTitle>
                                    </div>
                                    <CardDescription>Select the school instance this portal manages right now.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-grow flex-col space-y-4 p-4">
                                    <div className="grid max-h-[300px] gap-2 overflow-y-auto pr-1 sm:max-h-none">
                                        {schools.map((school) => (
                                            <button
                                                key={school.id}
                                                type="button"
                                                onClick={() => schoolForm.setData("school_id", school.id.toString())}
                                                className={cn(
                                                    "hover:bg-accent hover:border-accent-foreground/20 group flex items-center justify-between rounded-lg border p-3 text-left transition-all",
                                                    schoolForm.data.school_id === school.id.toString()
                                                        ? "border-primary bg-primary/5 ring-primary/20 shadow-sm ring-1"
                                                        : "bg-background border-transparent",
                                                )}
                                            >
                                                <div className="flex items-center gap-3 overflow-hidden pr-2">
                                                    <div
                                                        className={cn(
                                                            "flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition-colors",
                                                            schoolForm.data.school_id === school.id.toString()
                                                                ? "bg-primary text-primary-foreground"
                                                                : "bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary",
                                                        )}
                                                    >
                                                        <Building2 className="h-4 w-4" />
                                                    </div>
                                                    <div className="flex min-w-0 flex-col">
                                                        <span className="text-foreground truncate text-sm leading-tight font-medium">
                                                            {school.name}
                                                        </span>
                                                        <span className="text-muted-foreground mt-0.5 truncate text-xs">{school.code}</span>
                                                    </div>
                                                </div>

                                                {schoolForm.data.school_id === school.id.toString() && (
                                                    <div className="bg-primary text-primary-foreground flex h-5 w-5 shrink-0 items-center justify-center rounded-full shadow-sm">
                                                        <Check className="h-3 w-3" />
                                                    </div>
                                                )}
                                            </button>
                                        ))}
                                    </div>

                                    <div className="mt-auto space-y-3 pt-4">
                                        <Button
                                            className="w-full shadow-sm"
                                            onClick={() =>
                                                submitSystemForm({
                                                    form: schoolForm,
                                                    routeName: "administrators.system-management.school.update",
                                                    successMessage: "Active school updated successfully.",
                                                    errorMessage: "Failed to update active school.",
                                                })
                                            }
                                            disabled={schoolForm.processing || schoolForm.data.school_id === active_school?.id?.toString()}
                                        >
                                            {schoolForm.processing ? (
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            ) : (
                                                <Save className="mr-2 h-4 w-4" />
                                            )}
                                            Apply Selection
                                        </Button>

                                        <div className="rounded-md border border-amber-500/20 bg-amber-500/10 p-3">
                                            <div className="flex items-start gap-2.5">
                                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-500" />
                                                <p className="text-xs leading-relaxed font-medium text-amber-800 dark:text-amber-400">
                                                    Changing the active context reloads global configurations.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Main Content: School Details Form */}
                        <div className="lg:col-span-8 xl:col-span-9">
                            <Card className="border-muted-foreground/10 h-full shadow-sm">
                                <CardHeader className="bg-muted/30 relative overflow-hidden border-b pb-0">
                                    <div className="text-primary pointer-events-none absolute top-0 right-0 p-6 opacity-5">
                                        <Building2 className="h-32 w-32" />
                                    </div>
                                    <div className="relative z-10 flex flex-col justify-between gap-4 pb-6 sm:flex-row sm:items-end">
                                        <div className="space-y-1.5">
                                            <CardTitle className="text-xl">Settings & Details</CardTitle>
                                            <CardDescription>Manage the profile configuration for the active school.</CardDescription>
                                        </div>
                                        <Button
                                            onClick={() =>
                                                submitSystemForm({
                                                    form: schoolDetailsForm,
                                                    routeName: "administrators.system-management.school-details.update",
                                                    successMessage: "School details updated successfully.",
                                                    errorMessage: "Failed to update school details.",
                                                })
                                            }
                                            disabled={schoolDetailsForm.processing || !active_school || !schoolDetailsForm.isDirty}
                                            className="shrink-0 shadow-sm"
                                        >
                                            {schoolDetailsForm.processing ? (
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            ) : (
                                                <Save className="mr-2 h-4 w-4" />
                                            )}
                                            Save Settings
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-6">
                                    {active_school ? (
                                        <div className="max-w-3xl space-y-8">
                                            {/* Primary Info */}
                                            <div className="space-y-4">
                                                <div className="flex items-center gap-2 border-b pb-2">
                                                    <Building2 className="text-muted-foreground h-4 w-4" />
                                                    <h3 className="text-foreground text-sm font-medium">Primary Information</h3>
                                                </div>
                                                <div className="grid gap-5 sm:grid-cols-2">
                                                    <div className="space-y-2.5">
                                                        <Label
                                                            htmlFor="school_name"
                                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                        >
                                                            School Name
                                                        </Label>
                                                        <Input
                                                            id="school_name"
                                                            value={schoolDetailsForm.data.name}
                                                            onChange={(event) => schoolDetailsForm.setData("name", event.target.value)}
                                                            className="bg-background"
                                                        />
                                                    </div>
                                                    <div className="space-y-2.5">
                                                        <Label
                                                            htmlFor="school_code"
                                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                        >
                                                            Campus Code
                                                        </Label>
                                                        <Input
                                                            id="school_code"
                                                            value={schoolDetailsForm.data.code}
                                                            onChange={(event) => schoolDetailsForm.setData("code", event.target.value)}
                                                            className="bg-background uppercase"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="space-y-2.5">
                                                    <Label
                                                        htmlFor="school_description"
                                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                    >
                                                        Description
                                                    </Label>
                                                    <Textarea
                                                        id="school_description"
                                                        value={schoolDetailsForm.data.description}
                                                        onChange={(event) => schoolDetailsForm.setData("description", event.target.value)}
                                                        rows={4}
                                                        className="bg-background resize-none leading-relaxed"
                                                    />
                                                </div>
                                            </div>

                                            {/* Contact Info */}
                                            <div className="space-y-4">
                                                <div className="flex items-center gap-2 border-b pb-2">
                                                    <Phone className="text-muted-foreground h-4 w-4" />
                                                    <h3 className="text-foreground text-sm font-medium">Contact Details</h3>
                                                </div>
                                                <div className="grid gap-5 sm:grid-cols-2">
                                                    <div className="space-y-2.5">
                                                        <Label
                                                            htmlFor="school_phone"
                                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                        >
                                                            Phone Number
                                                        </Label>
                                                        <div className="relative">
                                                            <Phone className="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                                                            <Input
                                                                id="school_phone"
                                                                value={schoolDetailsForm.data.phone}
                                                                onChange={(event) => schoolDetailsForm.setData("phone", event.target.value)}
                                                                className="bg-background pl-9"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2.5">
                                                        <Label
                                                            htmlFor="school_email"
                                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                        >
                                                            Email Address
                                                        </Label>
                                                        <div className="relative">
                                                            <Mail className="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                                                            <Input
                                                                id="school_email"
                                                                type="email"
                                                                value={schoolDetailsForm.data.email}
                                                                onChange={(event) => schoolDetailsForm.setData("email", event.target.value)}
                                                                className="bg-background pl-9"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2.5 sm:col-span-2">
                                                        <Label
                                                            htmlFor="school_location"
                                                            className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                                        >
                                                            Location / Address
                                                        </Label>
                                                        <div className="relative">
                                                            <MapPin className="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                                                            <Input
                                                                id="school_location"
                                                                value={schoolDetailsForm.data.location}
                                                                onChange={(event) => schoolDetailsForm.setData("location", event.target.value)}
                                                                className="bg-background pl-9"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center justify-center py-16 text-center">
                                            <div className="bg-muted mb-4 rounded-full p-4">
                                                <Building2 className="text-muted-foreground h-8 w-8" />
                                            </div>
                                            <h3 className="text-lg font-medium">No Active School Selected</h3>
                                            <p className="text-muted-foreground mt-1 max-w-sm text-sm">
                                                Please select a school from the context sidebar to view and edit its details.
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Academic Calendar Defaults */}
                    <Card className="border-muted-foreground/10 shadow-sm">
                        <CardHeader className="bg-muted/30 border-b pb-4">
                            <div className="mb-1 flex items-center gap-2">
                                <div className="bg-primary/10 text-primary rounded-md p-1.5">
                                    <Calendar className="h-4 w-4" />
                                </div>
                                <CardTitle className="text-base">Academic Calendar Defaults</CardTitle>
                            </div>
                            <CardDescription>Configure the system-wide default semester and school year dates.</CardDescription>
                        </CardHeader>
                        <CardContent className="p-6">
                            <div className="grid gap-6 sm:grid-cols-3">
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="system_semester"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Default Semester
                                    </Label>
                                    <Select
                                        value={academicCalendarForm.data.semester.toString()}
                                        onValueChange={(value) => academicCalendarForm.setData("semester", parseInt(value))}
                                    >
                                        <SelectTrigger id="system_semester" className="bg-background">
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(available_semesters ?? {}).map(([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="school_starting_date"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        School Starting Date
                                    </Label>
                                    <div className="relative">
                                        <Calendar className="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                                        <Input
                                            id="school_starting_date"
                                            type="date"
                                            value={academicCalendarForm.data.school_starting_date}
                                            onChange={(event) =>
                                                academicCalendarForm.setData("school_starting_date", event.target.value)
                                            }
                                            className="bg-background pl-9"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2.5">
                                    <Label
                                        htmlFor="school_ending_date"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        School Ending Date
                                    </Label>
                                    <div className="relative">
                                        <Calendar className="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                                        <Input
                                            id="school_ending_date"
                                            type="date"
                                            value={academicCalendarForm.data.school_ending_date}
                                            onChange={(event) =>
                                                academicCalendarForm.setData("school_ending_date", event.target.value)
                                            }
                                            className="bg-background pl-9"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div className="mt-6 flex justify-end">
                                <Button
                                    onClick={() =>
                                        submitSystemForm({
                                            form: academicCalendarForm,
                                            routeName: "administrators.system-management.academic-calendar.update",
                                            successMessage: "Academic calendar defaults updated successfully.",
                                            errorMessage: "Failed to update academic calendar defaults.",
                                        })
                                    }
                                    disabled={
                                        academicCalendarForm.processing ||
                                        (!academicCalendarForm.isDirty &&
                                            academicCalendarForm.data.semester === (system_semester ?? 1) &&
                                            academicCalendarForm.data.school_starting_date === (system_school_starting_date ?? "") &&
                                            academicCalendarForm.data.school_ending_date === (system_school_ending_date ?? ""))
                                    }
                                    className="shadow-sm"
                                >
                                    {academicCalendarForm.processing ? (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    ) : (
                                        <Save className="mr-2 h-4 w-4" />
                                    )}
                                    Save Calendar Defaults
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="directory" className="space-y-6 focus-visible:ring-0 focus-visible:outline-none">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {schools.map((school) => (
                            <Card
                                key={school.id}
                                className="border-muted-foreground/10 hover:border-primary/20 flex flex-col overflow-hidden shadow-sm transition-colors"
                            >
                                <div className={cn("h-1.5 w-full", school.is_active ? "bg-primary" : "bg-muted")} />
                                <CardHeader className="px-5 pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-lg leading-tight">
                                                {school.name}
                                                {active_school?.id === school.id && <Check className="text-primary h-4 w-4" />}
                                            </CardTitle>
                                            <div className="mt-1.5 flex items-center gap-2">
                                                <Badge variant="outline" className="px-1.5 font-mono text-[10px]">
                                                    {school.code}
                                                </Badge>
                                                <Badge variant={school.is_active ? "default" : "secondary"} className="px-1.5 text-[10px]">
                                                    {school.is_active ? "Active" : "Inactive"}
                                                </Badge>
                                                {active_school?.id === school.id && (
                                                    <Badge className="border-blue-200 bg-blue-50 px-1.5 text-[10px] text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                        Operating
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-grow space-y-2.5 px-5 pb-4 text-sm">
                                    <p className="text-muted-foreground line-clamp-2 text-xs leading-relaxed">
                                        {school.description || "No description provided."}
                                    </p>
                                    <div className="mt-3 space-y-1.5 border-t pt-3">
                                        <div className="text-muted-foreground flex items-center gap-2">
                                            <MapPin className="h-3.5 w-3.5 shrink-0" />
                                            <span className="truncate text-xs">{school.location || "No location set"}</span>
                                        </div>
                                        <div className="text-muted-foreground flex items-center gap-2">
                                            <Mail className="h-3.5 w-3.5 shrink-0" />
                                            <span className="truncate text-xs">{school.email || "No email"}</span>
                                        </div>
                                        {school.dean_name && (
                                            <div className="text-muted-foreground flex items-center gap-2">
                                                <GraduationCap className="h-3.5 w-3.5 shrink-0" />
                                                <span className="truncate text-xs">{school.dean_name}</span>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                                <div className="bg-muted/20 flex flex-wrap gap-2 border-t p-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 flex-1 text-xs font-medium"
                                        onClick={() => openEditDialog(school)}
                                    >
                                        <Pencil className="mr-1.5 h-3 w-3" />
                                        Edit
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-8 flex-1 text-xs font-medium"
                                        onClick={() => handleToggleSchoolStatus(school)}
                                    >
                                        {school.is_active ? "Deactivate" : "Activate"}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive h-8 w-8 shrink-0"
                                        onClick={() => setDeletingSchool(school)}
                                        disabled={schools.length <= 1}
                                        title="Archive Campus"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </Card>
                        ))}
                    </div>
                </TabsContent>
            </Tabs>

            {/* Dialogs */}
            <Dialog open={isAddSchoolOpen} onOpenChange={setIsAddSchoolOpen}>
                <DialogContent className="sm:max-w-[520px]">
                    <DialogHeader>
                        <DialogTitle>Create School</DialogTitle>
                        <DialogDescription>Add another campus profile for multi-school setup.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateSchool} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="new_school_name" className="text-muted-foreground text-xs font-semibold uppercase">
                                    School Name
                                </Label>
                                <Input
                                    id="new_school_name"
                                    value={createSchoolForm.data.name}
                                    onChange={(event) => createSchoolForm.setData("name", event.target.value)}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new_school_code" className="text-muted-foreground text-xs font-semibold uppercase">
                                    School Code
                                </Label>
                                <Input
                                    id="new_school_code"
                                    value={createSchoolForm.data.code}
                                    onChange={(event) => createSchoolForm.setData("code", event.target.value)}
                                    required
                                    className="uppercase"
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="new_school_description" className="text-muted-foreground text-xs font-semibold uppercase">
                                Description
                            </Label>
                            <Textarea
                                id="new_school_description"
                                value={createSchoolForm.data.description}
                                onChange={(event) => createSchoolForm.setData("description", event.target.value)}
                                className="resize-none"
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="new_school_phone" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Phone
                                </Label>
                                <Input
                                    id="new_school_phone"
                                    value={createSchoolForm.data.phone}
                                    onChange={(event) => createSchoolForm.setData("phone", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new_school_email" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Email
                                </Label>
                                <Input
                                    id="new_school_email"
                                    type="email"
                                    value={createSchoolForm.data.email}
                                    onChange={(event) => createSchoolForm.setData("email", event.target.value)}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="new_school_location" className="text-muted-foreground text-xs font-semibold uppercase">
                                Location
                            </Label>
                            <Input
                                id="new_school_location"
                                value={createSchoolForm.data.location}
                                onChange={(event) => createSchoolForm.setData("location", event.target.value)}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="new_school_dean_name" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Dean Name (optional)
                                </Label>
                                <Input
                                    id="new_school_dean_name"
                                    value={createSchoolForm.data.dean_name}
                                    onChange={(event) => createSchoolForm.setData("dean_name", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="new_school_dean_email" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Dean Email (optional)
                                </Label>
                                <Input
                                    id="new_school_dean_email"
                                    type="email"
                                    value={createSchoolForm.data.dean_email}
                                    onChange={(event) => createSchoolForm.setData("email", event.target.value)}
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsAddSchoolOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createSchoolForm.processing}>
                                {createSchoolForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                                Create
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isEditSchoolOpen} onOpenChange={setIsEditSchoolOpen}>
                <DialogContent className="sm:max-w-[620px]">
                    <DialogHeader>
                        <DialogTitle>Edit School Profile</DialogTitle>
                        <DialogDescription>Update the details of the selected campus.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUpdateSchool} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="edit_school_name" className="text-muted-foreground text-xs font-semibold uppercase">
                                    School Name
                                </Label>
                                <Input
                                    id="edit_school_name"
                                    value={editSchoolForm.data.name}
                                    onChange={(event) => editSchoolForm.setData("name", event.target.value)}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit_school_code" className="text-muted-foreground text-xs font-semibold uppercase">
                                    School Code
                                </Label>
                                <Input
                                    id="edit_school_code"
                                    value={editSchoolForm.data.code}
                                    onChange={(event) => editSchoolForm.setData("code", event.target.value)}
                                    required
                                    className="uppercase"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit_school_description" className="text-muted-foreground text-xs font-semibold uppercase">
                                Description
                            </Label>
                            <Textarea
                                id="edit_school_description"
                                value={editSchoolForm.data.description}
                                onChange={(event) => editSchoolForm.setData("description", event.target.value)}
                                rows={3}
                                className="resize-none"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="edit_school_location" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Location
                                </Label>
                                <Input
                                    id="edit_school_location"
                                    value={editSchoolForm.data.location}
                                    onChange={(event) => editSchoolForm.setData("location", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit_school_phone" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Phone
                                </Label>
                                <Input
                                    id="edit_school_phone"
                                    value={editSchoolForm.data.phone}
                                    onChange={(event) => editSchoolForm.setData("phone", event.target.value)}
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="edit_school_email" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Email
                                </Label>
                                <Input
                                    id="edit_school_email"
                                    type="email"
                                    value={editSchoolForm.data.email}
                                    onChange={(event) => editSchoolForm.setData("email", event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit_school_dean_name" className="text-muted-foreground text-xs font-semibold uppercase">
                                    Dean Name
                                </Label>
                                <Input
                                    id="edit_school_dean_name"
                                    value={editSchoolForm.data.dean_name}
                                    onChange={(event) => editSchoolForm.setData("dean_name", event.target.value)}
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit_school_dean_email" className="text-muted-foreground text-xs font-semibold uppercase">
                                Dean Email
                            </Label>
                            <Input
                                id="edit_school_dean_email"
                                type="email"
                                value={editSchoolForm.data.dean_email}
                                onChange={(event) => editSchoolForm.setData("dean_email", event.target.value)}
                            />
                        </div>

                        <DialogFooter className="mt-4 border-t pt-2">
                            <Button type="button" variant="outline" onClick={() => setIsEditSchoolOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editSchoolForm.processing}>
                                {editSchoolForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                Update Profile
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={deletingSchool !== null} onOpenChange={(open) => !open && setDeletingSchool(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete School?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Archive will soft-delete <strong>{deletingSchool?.name}</strong>. Force delete will permanently remove it and purge
                            related school records.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={handleForceDeleteSchool}
                        >
                            Force Delete
                        </AlertDialogAction>
                        <AlertDialogAction onClick={handleDeleteSchool}>Archive Setting</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </SystemManagementLayout>
    );
}
