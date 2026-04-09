import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Empty } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    BookOpen,
    Calendar,
    Clock,
    ExternalLink,
    GraduationCap,
    IdCard,
    Mail,
    MapPin,
    Phone,
    Plus,
    School,
    Search,
    Trash2,
    User as UserIcon,
} from "lucide-react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";

type Schedule = {
    start_time: string;
    end_time: string;
    time_range: string;
    room: { id: number; name: string };
};

type WeeklySchedule = Record<string, Schedule[]>;

type ClassRow = {
    id: number;
    subject_code: string;
    subject_title: string | null;
    section: string;
    school_year: string;
    semester: number;
    classification: string | null;
    schedule?: WeeklySchedule;
};

type FacultyDetail = {
    id: string;
    faculty_id_number: string | null;
    name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    email: string;
    phone_number: string | null;
    department: string | null;
    office_hours: string | null;
    birth_date: string | null;
    address_line1: string | null;
    biography: string | null;
    education: string | null;
    courses_taught: string | null;
    avatar_url: string | null;
    status: string | null;
    gender: string | null;
    age: number | null;
    classes: ClassRow[];
    current_classes: ClassRow[];
    filament: {
        view_url: string;
        edit_url: string;
    };
};

type UnassignedClassOption = {
    id: number;
    subject_code: string;
    subject_title: string | null;
    section: string;
    schedule: WeeklySchedule;
    label: string;
    units: number;
};

type Option = { value: string; label: string };

interface FacultyShowProps {
    user: User;
    faculty: FacultyDetail;
    options: {
        unassigned_classes: UnassignedClassOption[];
        statuses: Option[];
    };
}

function statusLabel(status: string | null | undefined): string {
    if (!status) return "Unknown";
    if (status === "active") return "Active";
    if (status === "inactive") return "Inactive";
    if (status === "on_leave") return "On Leave";
    return status;
}

function statusBadgeVariant(status: string | null | undefined): "default" | "secondary" | "outline" {
    if (status === "active") return "default";
    if (status === "inactive") return "secondary";
    if (status === "on_leave") return "outline";
    return "outline";
}

function formatSchedule(schedule: WeeklySchedule | undefined) {
    if (!schedule) return "TBA";

    const days = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
    const entries: { day: string; time: string; room: string }[] = [];

    days.forEach((day) => {
        if (schedule[day] && schedule[day].length > 0) {
            schedule[day].forEach((slot) => {
                entries.push({
                    day: day.slice(0, 3).toUpperCase(),
                    time: slot.time_range,
                    room: slot.room.name,
                });
            });
        }
    });

    if (entries.length === 0) return "TBA";

    // Group by time/room to combine days (e.g. MWF 9-10)
    // Simple grouping logic for visualization
    return (
        <div className="flex flex-col gap-1">
            {entries.map((entry, idx) => (
                <div key={idx} className="flex items-center gap-2 text-xs">
                    <Badge variant="outline" className="h-5 px-1 py-0 font-mono text-[10px]">
                        {entry.day}
                    </Badge>
                    <span className="text-muted-foreground">{entry.time}</span>
                    <span className="text-muted-foreground">• {entry.room}</span>
                </div>
            ))}
        </div>
    );
}

export default function AdministratorFacultyShow({ user, faculty, options }: FacultyShowProps) {
    const [idDialogOpen, setIdDialogOpen] = useState(false);
    const [assignOpen, setAssignOpen] = useState(false);

    const [facultyIdNumber, setFacultyIdNumber] = useState(faculty.faculty_id_number ?? "");
    const [assignSearch, setAssignSearch] = useState("");
    const [selectedClassIds, setSelectedClassIds] = useState<Set<number>>(new Set());

    const filteredUnassigned = useMemo(() => {
        const query = assignSearch.trim().toLowerCase();
        if (!query) return options.unassigned_classes;

        return options.unassigned_classes.filter(
            (opt) =>
                opt.label.toLowerCase().includes(query) ||
                opt.subject_code.toLowerCase().includes(query) ||
                (opt.subject_title && opt.subject_title.toLowerCase().includes(query)),
        );
    }, [assignSearch, options.unassigned_classes]);

    const toggleClass = (classId: number) => {
        setSelectedClassIds((prev) => {
            const next = new Set(prev);
            if (next.has(classId)) next.delete(classId);
            else next.add(classId);
            return next;
        });
    };

    const assignClasses = () => {
        const classIds = Array.from(selectedClassIds);
        if (classIds.length === 0) return;

        router.post(
            route("administrators.faculties.assign-classes", faculty.id),
            { class_ids: classIds },
            {
                onSuccess: () => {
                    setAssignSearch("");
                    setSelectedClassIds(new Set());
                    setAssignOpen(false);
                },
            },
        );
    };

    const unassignClass = (classId: number) => {
        if (!confirm("Unassign this class from the faculty?")) return;

        router.delete(
            route("administrators.faculties.classes.unassign", {
                faculty: faculty.id,
                class: classId,
            }),
        );
    };

    const updateFacultyIdNumber = () => {
        router.put(
            route("administrators.faculties.update-id-number", faculty.id),
            { faculty_id_number: facultyIdNumber },
            { onSuccess: () => setIdDialogOpen(false) },
        );
    };

    const deleteFaculty = () => {
        if (!confirm(`Delete ${faculty.name}? This cannot be undone.`)) return;
        router.delete(route("administrators.faculties.destroy", faculty.id));
    };

    return (
        <AdminLayout user={user} title="Faculty Details">
            <Head title={`Faculty • ${faculty.name}`} />

            <div className="space-y-6">
                {/* Header Actions */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="text-muted-foreground flex items-center gap-2">
                        <Link href={route("administrators.faculties.index")} className="hover:text-foreground transition-colors">
                            Faculty Directory
                        </Link>
                        <span>/</span>
                        <span className="text-foreground font-medium">{faculty.name}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm" className="text-foreground">
                            <Link href={route("administrators.faculties.edit", faculty.id)}>Edit Profile</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm" className="text-foreground">
                            <a href={faculty.filament.view_url} target="_blank" rel="noreferrer">
                                <ExternalLink className="mr-2 h-3.5 w-3.5" />
                                Filament
                            </a>
                        </Button>
                        <Button variant="destructive" size="sm" onClick={deleteFaculty}>
                            <Trash2 className="mr-2 h-3.5 w-3.5" />
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Left Column: Profile & Personal Info */}
                    <div className="space-y-6 lg:col-span-4">
                        <Card className="overflow-hidden">
                            <div className="bg-muted/30 relative h-24">
                                <div className="absolute -bottom-12 left-6">
                                    <Avatar className="border-background h-24 w-24 border-4 shadow-sm">
                                        <AvatarImage src={faculty.avatar_url ?? undefined} alt={faculty.name} />
                                        <AvatarFallback className="text-xl">{(faculty.name || "?").slice(0, 2).toUpperCase()}</AvatarFallback>
                                    </Avatar>
                                </div>
                            </div>
                            <CardHeader className="pt-16 pb-4">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <CardTitle className="text-xl">{faculty.name}</CardTitle>
                                        <CardDescription className="mt-1 flex items-center gap-1.5">
                                            <Mail className="h-3.5 w-3.5" /> {faculty.email}
                                        </CardDescription>
                                    </div>
                                    <Badge variant={statusBadgeVariant(faculty.status)} className="capitalize">
                                        {statusLabel(faculty.status)}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3">
                                    <div className="group flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground flex items-center gap-2">
                                            <IdCard className="h-4 w-4" /> Faculty ID
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono">{faculty.faculty_id_number ?? "—"}</span>
                                            <Dialog open={idDialogOpen} onOpenChange={setIdDialogOpen}>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
                                                    >
                                                        <span className="sr-only">Edit ID</span>
                                                        <IdCard className="h-3.5 w-3.5" />
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogHeader>
                                                        <DialogTitle>Update Faculty ID</DialogTitle>
                                                        <DialogDescription>Enter the new ID number for this faculty member.</DialogDescription>
                                                    </DialogHeader>
                                                    <div className="grid gap-2 py-4">
                                                        <Label htmlFor="faculty_id">Faculty ID Number</Label>
                                                        <Input
                                                            id="faculty_id"
                                                            value={facultyIdNumber}
                                                            onChange={(e) => setFacultyIdNumber(e.target.value)}
                                                            placeholder="e.g. 100023"
                                                        />
                                                    </div>
                                                    <DialogFooter>
                                                        <Button variant="outline" onClick={() => setIdDialogOpen(false)}>
                                                            Cancel
                                                        </Button>
                                                        <Button onClick={updateFacultyIdNumber}>Save Changes</Button>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground flex items-center gap-2">
                                            <School className="h-4 w-4" /> Department
                                        </span>
                                        <span>{faculty.department ?? "—"}</span>
                                    </div>

                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground flex items-center gap-2">
                                            <Phone className="h-4 w-4" /> Phone
                                        </span>
                                        <span>{faculty.phone_number ?? "—"}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <UserIcon className="h-4 w-4" /> Personal Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <div className="text-muted-foreground mb-1 text-xs">Gender</div>
                                        <div className="capitalize">{faculty.gender ?? "—"}</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground mb-1 text-xs">Age</div>
                                        <div>{faculty.age ? `${faculty.age} years old` : "—"}</div>
                                    </div>
                                    <div className="col-span-2">
                                        <div className="text-muted-foreground mb-1 flex items-center gap-1 text-xs">
                                            <Calendar className="h-3 w-3" /> Birth Date
                                        </div>
                                        <div>{faculty.birth_date ?? "—"}</div>
                                    </div>
                                    <div className="col-span-2">
                                        <div className="text-muted-foreground mb-1 flex items-center gap-1 text-xs">
                                            <MapPin className="h-3 w-3" /> Address
                                        </div>
                                        <div className="whitespace-pre-line">{faculty.address_line1 ?? "—"}</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Clock className="h-4 w-4" /> Office Hours
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-muted-foreground text-sm whitespace-pre-line">
                                    {faculty.office_hours || "No office hours listed."}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Classes & Academic Info */}
                    <div className="space-y-6 lg:col-span-8">
                        <Tabs defaultValue="current" className="w-full">
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="current">Current Classes</TabsTrigger>
                                <TabsTrigger value="history">Class History</TabsTrigger>
                                <TabsTrigger value="academic">Academic Profile</TabsTrigger>
                            </TabsList>

                            <TabsContent value="current" className="mt-4 space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-medium">Current Assignments</h3>
                                        <p className="text-muted-foreground text-sm">Classes for the active academic period.</p>
                                    </div>

                                    <Sheet open={assignOpen} onOpenChange={setAssignOpen}>
                                        <SheetTrigger asChild>
                                            <Button size="sm">
                                                <Plus className="mr-2 h-4 w-4" /> Assign Class
                                            </Button>
                                        </SheetTrigger>
                                        <SheetContent className="w-full sm:max-w-2xl">
                                            <SheetHeader className="mb-4">
                                                <SheetTitle>Assign Classes to {faculty.first_name}</SheetTitle>
                                                <SheetDescription>Select classes from the unassigned list below.</SheetDescription>
                                                <div className="relative mt-2">
                                                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                                    <Input
                                                        placeholder="Search by subject code, title or ID..."
                                                        value={assignSearch}
                                                        onChange={(e) => setAssignSearch(e.target.value)}
                                                        className="pl-9"
                                                    />
                                                </div>
                                            </SheetHeader>

                                            <div className="flex h-full max-h-[calc(100vh-220px)] flex-col">
                                                {filteredUnassigned.length === 0 ? (
                                                    <div className="text-muted-foreground m-1 flex h-48 flex-col items-center justify-center rounded-lg border-2 border-dashed">
                                                        <GraduationCap className="mb-2 h-8 w-8 opacity-50" />
                                                        <p>No matching classes found.</p>
                                                    </div>
                                                ) : (
                                                    <div className="flex-1 space-y-3 overflow-y-auto pr-2 pb-4">
                                                        {filteredUnassigned.map((opt) => {
                                                            const isSelected = selectedClassIds.has(opt.id);
                                                            return (
                                                                <div
                                                                    key={opt.id}
                                                                    className={cn(
                                                                        "group relative flex cursor-pointer flex-col gap-4 rounded-lg border p-4 transition-all hover:shadow-md sm:flex-row",
                                                                        isSelected
                                                                            ? "bg-primary/5 border-primary ring-primary/20 ring-1"
                                                                            : "bg-card hover:border-primary/50",
                                                                    )}
                                                                    onClick={() => toggleClass(opt.id)}
                                                                >
                                                                    <div className="min-w-0 flex-1">
                                                                        <div className="mb-1 flex items-start justify-between gap-2">
                                                                            <div className="flex items-center gap-2">
                                                                                <Badge variant={isSelected ? "default" : "secondary"}>
                                                                                    {opt.subject_code}
                                                                                </Badge>
                                                                                <span className="text-muted-foreground rounded border px-1.5 py-0.5 text-xs font-medium">
                                                                                    {opt.section}
                                                                                </span>
                                                                            </div>
                                                                            {/* Mobile Checkbox */}
                                                                            <div className="sm:hidden">
                                                                                <Checkbox checked={isSelected} />
                                                                            </div>
                                                                        </div>

                                                                        <h4 className="truncate pr-4 text-sm font-semibold sm:text-base">
                                                                            {opt.subject_title || "No Title"}
                                                                        </h4>

                                                                        <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                                                            <div className="col-span-2">{formatSchedule(opt.schedule)}</div>
                                                                        </div>
                                                                    </div>

                                                                    <div className="hidden flex-col items-end justify-center border-l pl-2 sm:flex">
                                                                        <Button
                                                                            size="sm"
                                                                            variant={isSelected ? "default" : "outline"}
                                                                            className={cn(
                                                                                "w-24 transition-all",
                                                                                isSelected &&
                                                                                    "hover:bg-destructive hover:text-destructive-foreground",
                                                                            )}
                                                                            onClick={(e) => {
                                                                                e.stopPropagation();
                                                                                toggleClass(opt.id);
                                                                            }}
                                                                        >
                                                                            {isSelected ? "Remove" : "Add"}
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>

                                            <SheetFooter className="mt-4 border-t pt-4">
                                                <div className="flex w-full items-center justify-between">
                                                    <div className="text-sm font-medium">{selectedClassIds.size} class(es) selected</div>
                                                    <div className="flex gap-2">
                                                        <Button
                                                            variant="outline"
                                                            onClick={() => {
                                                                setAssignOpen(false);
                                                                setSelectedClassIds(new Set());
                                                                setAssignSearch("");
                                                            }}
                                                        >
                                                            Cancel
                                                        </Button>
                                                        <Button onClick={assignClasses} disabled={selectedClassIds.size === 0}>
                                                            Assign Selected
                                                        </Button>
                                                    </div>
                                                </div>
                                            </SheetFooter>
                                        </SheetContent>
                                    </Sheet>
                                </div>

                                <Card>
                                    <CardContent className="p-0">
                                        {faculty.current_classes.length === 0 ? (
                                            <Empty>
                                                <div className="flex flex-col items-center justify-center p-8 text-center">
                                                    <div className="bg-muted/30 mb-3 rounded-full p-3">
                                                        <GraduationCap className="text-muted-foreground h-6 w-6" />
                                                    </div>
                                                    <h3 className="text-lg font-medium">No Active Classes</h3>
                                                    <p className="text-muted-foreground mt-1 mb-4 max-w-sm text-sm">
                                                        This faculty member doesn't have any classes assigned for the current semester.
                                                    </p>
                                                    <Button variant="outline" onClick={() => setAssignOpen(true)}>
                                                        Assign a Class
                                                    </Button>
                                                </div>
                                            </Empty>
                                        ) : (
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Code</TableHead>
                                                        <TableHead>Subject</TableHead>
                                                        <TableHead>Section</TableHead>
                                                        <TableHead>Schedule</TableHead>
                                                        <TableHead className="text-right">Actions</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {faculty.current_classes.map((cls) => (
                                                        <TableRow key={cls.id}>
                                                            <TableCell className="font-medium">{cls.subject_code}</TableCell>
                                                            <TableCell className="max-w-[200px] truncate" title={cls.subject_title || ""}>
                                                                {cls.subject_title || "—"}
                                                            </TableCell>
                                                            <TableCell>{cls.section}</TableCell>
                                                            <TableCell className="text-muted-foreground text-xs">
                                                                {formatSchedule(cls.schedule)}
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <div className="flex items-center justify-end gap-2">
                                                                    <Button asChild variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                        <Link href={route("administrators.classes.show", cls.id)}>
                                                                            <span className="sr-only">View</span>
                                                                            <ExternalLink className="h-4 w-4" />
                                                                        </Link>
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-destructive hover:text-destructive h-8 w-8 p-0"
                                                                        onClick={() => unassignClass(cls.id)}
                                                                    >
                                                                        <span className="sr-only">Unassign</span>
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="history" className="mt-4 space-y-4">
                                <div className="mb-4">
                                    <h3 className="text-lg font-medium">Class History</h3>
                                    <p className="text-muted-foreground text-sm">All classes previously assigned to this faculty.</p>
                                </div>
                                <Card>
                                    <CardContent className="p-0">
                                        {faculty.classes.length === 0 ? (
                                            <div className="text-muted-foreground p-8 text-center">No class history found.</div>
                                        ) : (
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>SY / Sem</TableHead>
                                                        <TableHead>Code</TableHead>
                                                        <TableHead>Subject</TableHead>
                                                        <TableHead>Section</TableHead>
                                                        <TableHead className="text-right">Actions</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {faculty.classes.map((cls) => (
                                                        <TableRow key={cls.id}>
                                                            <TableCell className="text-muted-foreground text-xs whitespace-nowrap">
                                                                {cls.school_year} • {cls.semester}
                                                            </TableCell>
                                                            <TableCell className="font-medium">{cls.subject_code}</TableCell>
                                                            <TableCell className="max-w-[200px] truncate">{cls.subject_title || "—"}</TableCell>
                                                            <TableCell>{cls.section}</TableCell>
                                                            <TableCell className="text-right">
                                                                <Button asChild variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                    <Link href={route("administrators.classes.show", cls.id)}>
                                                                        <ExternalLink className="h-4 w-4" />
                                                                    </Link>
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="academic" className="mt-4 space-y-6">
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="mb-2 flex items-center gap-2 text-lg font-medium">
                                            <BookOpen className="h-4 w-4" /> Biography
                                        </h3>
                                        <Card>
                                            <CardContent className="pt-6">
                                                {faculty.biography ? (
                                                    <p className="text-sm leading-relaxed whitespace-pre-line">{faculty.biography}</p>
                                                ) : (
                                                    <p className="text-muted-foreground text-sm italic">No biography provided.</p>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <div>
                                        <h3 className="mb-2 flex items-center gap-2 text-lg font-medium">
                                            <GraduationCap className="h-4 w-4" /> Education
                                        </h3>
                                        <Card>
                                            <CardContent className="pt-6">
                                                {faculty.education ? (
                                                    <p className="text-sm leading-relaxed whitespace-pre-line">{faculty.education}</p>
                                                ) : (
                                                    <p className="text-muted-foreground text-sm italic">No education history provided.</p>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </div>

                                    <div>
                                        <h3 className="mb-2 flex items-center gap-2 text-lg font-medium">
                                            <School className="h-4 w-4" /> Courses Taught
                                        </h3>
                                        <Card>
                                            <CardContent className="pt-6">
                                                {faculty.courses_taught ? (
                                                    <p className="text-sm leading-relaxed whitespace-pre-line">{faculty.courses_taught}</p>
                                                ) : (
                                                    <p className="text-muted-foreground text-sm italic">No courses listed.</p>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
