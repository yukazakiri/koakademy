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
import { MultiSelect } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { ArrowUpDown, BookOpen, Edit, FilePenLine, GraduationCap, Plus, Search, Trash2 } from "lucide-react";
import { useMemo, useState, type FormEvent } from "react";
import { route } from "ziggy-js";

interface CurriculumProgramShowProps {
    user: User;
    program: ProgramPayload;
    stats: {
        subjects: number;
        credited_subjects: number;
        academic_years: number;
        subjects_with_requisites: number;
        total_units: number;
    };
    subjects: SubjectPayload[];
    subject_options: SubjectOption[];
    classification_options: ClassificationOption[];
}

type ProgramPayload = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    department: string | null;
    lec_per_unit: string | number | null;
    lab_per_unit: string | number | null;
    remarks: string | null;
    curriculum_year: string | null;
    miscelaneous: string | number | null;
};

type SubjectPayload = {
    id: number;
    code: string;
    title: string;
    classification: string | null;
    units: number | null;
    lecture: number | null;
    laboratory: number | null;
    academic_year: number | null;
    semester: number | null;
    group: string | null;
    is_credited: boolean;
    pre_riquisite: number[];
};

type SubjectOption = {
    id: number;
    code: string;
    title: string;
};

type ClassificationOption = {
    value: string;
    label: string;
};

type SubjectFormData = {
    code: string;
    title: string;
    classification: string;
    units: string;
    lecture: string;
    laboratory: string;
    academic_year: string;
    semester: string;
    group: string;
    is_credited: boolean;
    pre_riquisite: number[];
};

const yearOptions = [
    { value: "1", label: "1st Year" },
    { value: "2", label: "2nd Year" },
    { value: "3", label: "3rd Year" },
    { value: "4", label: "4th Year" },
];

const semesterOptions = [
    { value: "1", label: "1st Semester" },
    { value: "2", label: "2nd Semester" },
    { value: "3", label: "Summer" },
];

const formatNumberField = (value: string | number | null): string => {
    if (value === null || value === undefined) {
        return "";
    }

    return String(value);
};

const FieldError = ({ message }: { message?: string }) => (message ? <p className="text-destructive mt-1 text-xs font-medium">{message}</p> : null);

export default function CurriculumProgramShow({
    user,
    program,
    stats,
    subjects,
    subject_options,
    classification_options,
}: CurriculumProgramShowProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editSubject, setEditSubject] = useState<SubjectPayload | null>(null);
    const [deleteSubject, setDeleteSubject] = useState<SubjectPayload | null>(null);

    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = useState("");

    const subjectOptionMap = useMemo(() => {
        return new Map(subject_options.map((option) => [option.id, option]));
    }, [subject_options]);

    const classificationMap = useMemo(() => {
        return new Map(classification_options.map((option) => [option.value, option.label]));
    }, [classification_options]);

    const programForm = useForm({
        code: program.code,
        title: program.title,
        description: program.description ?? "",
        department: program.department ?? "",
        lec_per_unit: formatNumberField(program.lec_per_unit),
        lab_per_unit: formatNumberField(program.lab_per_unit),
        remarks: program.remarks ?? "",
        curriculum_year: program.curriculum_year ?? "",
        miscelaneous: formatNumberField(program.miscelaneous),
    });

    const defaultSubject: SubjectFormData = {
        code: "",
        title: "",
        classification: classification_options[0]?.value ?? "credited",
        units: "",
        lecture: "",
        laboratory: "",
        academic_year: "",
        semester: "",
        group: "",
        is_credited: true,
        pre_riquisite: [],
    };

    const createForm = useForm<SubjectFormData>({ ...defaultSubject });
    const editForm = useForm<SubjectFormData>({ ...defaultSubject });

    const handleProgramSubmit = (event: FormEvent) => {
        event.preventDefault();

        programForm.put(route("administrators.curriculum.programs.update", program.id), {
            preserveScroll: true,
        });
    };

    const handleCreateSubject = (event: FormEvent) => {
        event.preventDefault();

        createForm.post(route("administrators.curriculum.programs.subjects.store", program.id), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                createForm.clearErrors();
            },
        });
    };

    const handleEditSubject = (event: FormEvent) => {
        event.preventDefault();

        if (!editSubject) return;

        editForm.put(
            route("administrators.curriculum.programs.subjects.update", {
                course: program.id,
                subject: editSubject.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditSubject(null);
                    editForm.reset();
                    editForm.clearErrors();
                },
            },
        );
    };

    const openEditSubject = (subject: SubjectPayload) => {
        editForm.setData({
            code: subject.code,
            title: subject.title,
            classification: subject.classification ?? defaultSubject.classification,
            units: formatNumberField(subject.units),
            lecture: formatNumberField(subject.lecture),
            laboratory: formatNumberField(subject.laboratory),
            academic_year: subject.academic_year?.toString() ?? "",
            semester: subject.semester?.toString() ?? "",
            group: subject.group ?? "",
            is_credited: subject.is_credited,
            pre_riquisite: subject.pre_riquisite ?? [],
        });
        editForm.clearErrors();
        setEditSubject(subject);
    };

    const handleDeleteSubject = () => {
        if (!deleteSubject) return;

        router.delete(
            route("administrators.curriculum.programs.subjects.destroy", {
                course: program.id,
                subject: deleteSubject.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => setDeleteSubject(null),
            },
        );
    };

    const renderPrerequisiteLabels = (subject: SubjectPayload) => {
        if (!subject.pre_riquisite?.length) {
            return <span className="text-muted-foreground text-xs italic">None</span>;
        }

        return (
            <div className="flex flex-wrap gap-1">
                {subject.pre_riquisite.map((id) => {
                    const option = subjectOptionMap.get(id);
                    return (
                        <Badge
                            key={id}
                            variant="outline"
                            className="bg-muted/50 font-mono text-[10px]"
                            title={option ? option.title : `Subject #${id}`}
                        >
                            {option ? option.code : `#${id}`}
                        </Badge>
                    );
                })}
            </div>
        );
    };

    const columns: ColumnDef<SubjectPayload>[] = useMemo(
        () => [
            {
                accessorKey: "code",
                header: ({ column }) => {
                    return (
                        <Button
                            variant="ghost"
                            className="data-[state=open]:bg-accent -ml-4 h-8 hover:bg-transparent"
                            onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        >
                            Subject Details
                            <ArrowUpDown className="ml-2 h-4 w-4" />
                        </Button>
                    );
                },
                cell: ({ row }) => {
                    const subject = row.original;
                    return (
                        <div className="flex flex-col gap-1">
                            <span className="text-foreground font-semibold tracking-tight">{subject.code}</span>
                            <span className="text-muted-foreground max-w-xs truncate text-xs" title={subject.title}>
                                {subject.title}
                            </span>
                            <div className="mt-1 flex flex-wrap gap-1">
                                <Badge variant="secondary" className="h-4 px-1.5 text-[10px] font-medium">
                                    {subject.classification
                                        ? (classificationMap.get(subject.classification) ?? subject.classification)
                                        : "Unassigned"}
                                </Badge>
                                {subject.is_credited && (
                                    <Badge
                                        variant="outline"
                                        className="h-4 border-emerald-200 bg-emerald-50 px-1.5 text-[10px] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400"
                                    >
                                        Credited
                                    </Badge>
                                )}
                            </div>
                        </div>
                    );
                },
                filterFn: (row, id, value) => {
                    const rowValue = `${row.original.code} ${row.original.title}`.toLowerCase();
                    return rowValue.includes((value as string).toLowerCase());
                },
            },
            {
                accessorKey: "academic_year",
                header: "Year & Sem",
                cell: ({ row }) => {
                    const subject = row.original;
                    return (
                        <div className="flex flex-col">
                            <span className="text-foreground text-sm font-medium">Year {subject.academic_year ?? "-"}</span>
                            <span className="text-muted-foreground text-xs">Sem {subject.semester ?? "-"}</span>
                        </div>
                    );
                },
            },
            {
                accessorKey: "units",
                header: "Units & Hours",
                cell: ({ row }) => {
                    const subject = row.original;
                    return (
                        <div className="flex flex-col">
                            <span className="text-foreground text-sm font-medium">{subject.units ?? 0} Units</span>
                            <span className="text-muted-foreground text-xs">
                                Lec: {subject.lecture ?? 0} | Lab: {subject.laboratory ?? 0}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: "prerequisites",
                header: "Prerequisites",
                cell: ({ row }) => {
                    return renderPrerequisiteLabels(row.original);
                },
            },
            {
                id: "actions",
                cell: ({ row }) => {
                    const subject = row.original;
                    return (
                        <div className="flex justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-primary hover:text-primary hover:bg-primary/10 h-8 w-8"
                                onClick={() => openEditSubject(subject)}
                                title="Edit subject"
                            >
                                <Edit className="h-4 w-4" />
                                <span className="sr-only">Edit</span>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 w-8"
                                onClick={() => setDeleteSubject(subject)}
                                title="Remove subject"
                            >
                                <Trash2 className="h-4 w-4" />
                                <span className="sr-only">Delete</span>
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [classificationMap, subjectOptionMap],
    );

    const table = useReactTable({
        data: subjects,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        globalFilterFn: "includesString",
        onGlobalFilterChange: setGlobalFilter,
        state: {
            sorting,
            columnFilters,
            globalFilter,
        },
        initialState: {
            pagination: { pageSize: 10 },
        },
    });

    const SubjectDialogFields = ({ form, currentSubjectId }: { form: typeof createForm; currentSubjectId?: number }) => {
        const prerequisiteOptions = subject_options
            .filter((option) => option.id !== currentSubjectId)
            .map((option) => ({
                label: `${option.code} - ${option.title}`,
                value: String(option.id),
            }));

        return (
            <div className="grid gap-5">
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="subject-code" className="text-foreground/80 font-semibold">
                            Subject Code
                        </Label>
                        <Input
                            id="subject-code"
                            placeholder="e.g. CS101"
                            value={form.data.code}
                            onChange={(event) => form.setData("code", event.target.value)}
                        />
                        <FieldError message={form.errors.code} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="subject-title" className="text-foreground/80 font-semibold">
                            Subject Title
                        </Label>
                        <Input
                            id="subject-title"
                            placeholder="e.g. Intro to Computer Science"
                            value={form.data.title}
                            onChange={(event) => form.setData("title", event.target.value)}
                        />
                        <FieldError message={form.errors.title} />
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label className="text-foreground/80 font-semibold">Classification</Label>
                        <Select value={form.data.classification} onValueChange={(value) => form.setData("classification", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select classification" />
                            </SelectTrigger>
                            <SelectContent>
                                {classification_options.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError message={form.errors.classification} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="subject-group" className="text-foreground/80 font-semibold">
                            Group
                        </Label>
                        <Input
                            id="subject-group"
                            placeholder="e.g. Core"
                            value={form.data.group}
                            onChange={(event) => form.setData("group", event.target.value)}
                        />
                        <FieldError message={form.errors.group} />
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="subject-units" className="text-foreground/80 font-semibold">
                            Units
                        </Label>
                        <Input
                            id="subject-units"
                            type="number"
                            min="0"
                            value={form.data.units}
                            onChange={(event) => form.setData("units", event.target.value)}
                        />
                        <FieldError message={form.errors.units} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="subject-lecture" className="text-foreground/80 font-semibold">
                            Lecture Hours
                        </Label>
                        <Input
                            id="subject-lecture"
                            type="number"
                            min="0"
                            value={form.data.lecture}
                            onChange={(event) => form.setData("lecture", event.target.value)}
                        />
                        <FieldError message={form.errors.lecture} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="subject-lab" className="text-foreground/80 font-semibold">
                            Lab Hours
                        </Label>
                        <Input
                            id="subject-lab"
                            type="number"
                            min="0"
                            value={form.data.laboratory}
                            onChange={(event) => form.setData("laboratory", event.target.value)}
                        />
                        <FieldError message={form.errors.laboratory} />
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label className="text-foreground/80 font-semibold">Academic Year</Label>
                        <Select value={form.data.academic_year} onValueChange={(value) => form.setData("academic_year", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select year" />
                            </SelectTrigger>
                            <SelectContent>
                                {yearOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError message={form.errors.academic_year} />
                    </div>
                    <div className="grid gap-2">
                        <Label className="text-foreground/80 font-semibold">Semester</Label>
                        <Select value={form.data.semester} onValueChange={(value) => form.setData("semester", value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {semesterOptions.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError message={form.errors.semester} />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label className="text-foreground/80 font-semibold">Prerequisites</Label>
                    <MultiSelect
                        options={prerequisiteOptions}
                        selected={form.data.pre_riquisite.map(String)}
                        onChange={(selected: string[]) =>
                            form.setData(
                                "pre_riquisite",
                                selected.map((value: string) => Number(value)),
                            )
                        }
                        placeholder="Select prerequisites..."
                        searchPlaceholder="Search subjects..."
                        className="bg-background w-full"
                    />
                    <p className="text-muted-foreground mt-1 text-xs">Choose subjects that must be completed before this course.</p>
                    <FieldError message={form.errors.pre_riquisite as string | undefined} />
                </div>
                <div className="bg-muted/30 border-border/50 mt-2 flex items-center gap-2 rounded-lg border p-3">
                    <Checkbox
                        id="is-credited-checkbox"
                        checked={form.data.is_credited}
                        onCheckedChange={(checked) => form.setData("is_credited", Boolean(checked))}
                    />
                    <Label htmlFor="is-credited-checkbox" className="text-foreground/80 cursor-pointer font-semibold select-none">
                        Mark as credited subject
                    </Label>
                    <FieldError message={form.errors.is_credited} />
                </div>
            </div>
        );
    };

    return (
        <AdminLayout user={user} title={`Program: ${program.code}`}>
            <Head title={`Program: ${program.code}`} />
            <div className="flex flex-col gap-6">
                <Card className="border-none bg-gradient-to-br from-slate-50 via-white to-slate-50 shadow-sm dark:from-slate-900 dark:via-slate-900 dark:to-slate-950">
                    <CardHeader className="flex flex-col gap-4 pb-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="bg-primary/10 rounded-xl p-3">
                                <GraduationCap className="text-primary size-6" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle className="text-2xl">{program.code} Curriculum</CardTitle>
                                <CardDescription className="max-w-xl text-sm">{program.title}</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary" className="px-2.5 py-0.5 text-xs font-medium">
                                {stats.subjects} subjects
                            </Badge>
                            <Badge variant="outline" className="bg-background px-2.5 py-0.5 text-xs font-medium">
                                {stats.total_units} units total
                            </Badge>
                            <Badge variant="outline" className="bg-background px-2.5 py-0.5 text-xs font-medium">
                                {stats.credited_subjects} credited
                            </Badge>
                            <Badge variant="outline" className="bg-background px-2.5 py-0.5 text-xs font-medium">
                                {stats.academic_years} year levels
                            </Badge>
                            <Button asChild variant="ghost" size="sm" className="h-8">
                                <Link href="/administrators/curriculum/programs">Back to programs</Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form className="bg-card/50 grid gap-6 rounded-xl border p-4 shadow-sm" onSubmit={handleProgramSubmit}>
                            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                                <div className="grid gap-2 lg:col-span-1">
                                    <Label htmlFor="program-code" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Program Code
                                    </Label>
                                    <Input
                                        id="program-code"
                                        value={programForm.data.code}
                                        className="font-medium"
                                        onChange={(event) => programForm.setData("code", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.code} />
                                </div>
                                <div className="grid gap-2 lg:col-span-3">
                                    <Label htmlFor="program-title" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Program Title
                                    </Label>
                                    <Input
                                        id="program-title"
                                        value={programForm.data.title}
                                        className="font-medium"
                                        onChange={(event) => programForm.setData("title", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.title} />
                                </div>
                                <div className="grid gap-2 lg:col-span-2">
                                    <Label
                                        htmlFor="program-department"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Department
                                    </Label>
                                    <Input
                                        id="program-department"
                                        value={programForm.data.department}
                                        onChange={(event) => programForm.setData("department", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.department} />
                                </div>
                                <div className="grid gap-2 lg:col-span-2">
                                    <Label
                                        htmlFor="program-curriculum"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Curriculum Year
                                    </Label>
                                    <Input
                                        id="program-curriculum"
                                        value={programForm.data.curriculum_year}
                                        onChange={(event) => programForm.setData("curriculum_year", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.curriculum_year} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="program-lec" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Lec per Unit
                                    </Label>
                                    <Input
                                        id="program-lec"
                                        type="number"
                                        value={programForm.data.lec_per_unit}
                                        onChange={(event) => programForm.setData("lec_per_unit", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.lec_per_unit} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="program-lab" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Lab per Unit
                                    </Label>
                                    <Input
                                        id="program-lab"
                                        type="number"
                                        value={programForm.data.lab_per_unit}
                                        onChange={(event) => programForm.setData("lab_per_unit", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.lab_per_unit} />
                                </div>
                                <div className="grid gap-2 lg:col-span-2">
                                    <Label htmlFor="program-misc" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Misc Fee
                                    </Label>
                                    <Input
                                        id="program-misc"
                                        type="number"
                                        value={programForm.data.miscelaneous}
                                        onChange={(event) => programForm.setData("miscelaneous", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.miscelaneous} />
                                </div>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="program-description"
                                        className="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >
                                        Description
                                    </Label>
                                    <Textarea
                                        id="program-description"
                                        className="min-h-[100px] resize-none"
                                        value={programForm.data.description}
                                        onChange={(event) => programForm.setData("description", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.description} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="program-remarks" className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                        Remarks
                                    </Label>
                                    <Textarea
                                        id="program-remarks"
                                        className="min-h-[100px] resize-none"
                                        value={programForm.data.remarks}
                                        onChange={(event) => programForm.setData("remarks", event.target.value)}
                                    />
                                    <FieldError message={programForm.errors.remarks} />
                                </div>
                            </div>

                            <div className="flex justify-end pt-2">
                                <Button type="submit" disabled={programForm.processing}>
                                    <FilePenLine className="mr-2 size-4" />
                                    Save Program Details
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="border shadow-sm">
                    <CardHeader className="bg-muted/20 border-b pb-4">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <BookOpen className="text-muted-foreground size-5" />
                                    Program Subjects
                                </CardTitle>
                                <CardDescription>Search and manage the curriculum subjects datagrid.</CardDescription>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="relative w-full sm:w-64">
                                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                    <Input
                                        placeholder="Search subjects..."
                                        value={globalFilter ?? ""}
                                        onChange={(event) => setGlobalFilter(event.target.value)}
                                        className="bg-background h-9 w-full pl-9"
                                    />
                                </div>
                                <Button size="sm" type="button" onClick={() => setIsCreateOpen(true)} className="h-9">
                                    <Plus className="mr-2 size-4" />
                                    Add Subject
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id} className="hover:bg-transparent">
                                        {headerGroup.headers.map((header) => {
                                            return (
                                                <TableHead key={header.id} className="py-3">
                                                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                                </TableHead>
                                            );
                                        })}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow key={row.id} data-state={row.getIsSelected() && "selected"} className="group hover:bg-muted/20">
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id} className="py-3">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={columns.length} className="text-muted-foreground h-32 text-center">
                                            {subjects.length === 0
                                                ? "No subjects assigned yet. Add the first subject to get started."
                                                : "No subjects found matching your criteria."}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                    {table.getPageCount() > 1 && (
                        <div className="flex items-center justify-end space-x-2 border-t p-4">
                            <Button variant="outline" size="sm" onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}>
                                Previous
                            </Button>
                            <span className="text-muted-foreground text-sm">
                                Page {table.getState().pagination.pageIndex + 1} of {table.getPageCount()}
                            </span>
                            <Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>
                                Next
                            </Button>
                        </div>
                    )}
                </Card>
            </div>

            {/* Dialogs */}
            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => {
                    setIsCreateOpen(open);
                    if (!open) {
                        createForm.reset();
                        createForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="bg-card border shadow-lg sm:max-w-2xl">
                    <DialogHeader className="mb-4 border-b pb-4">
                        <DialogTitle className="text-xl">Add Subject</DialogTitle>
                        <DialogDescription>Add a new subject under {program.code} and define its curriculum placement.</DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-6" onSubmit={handleCreateSubject}>
                        <SubjectDialogFields form={createForm} />
                        <DialogFooter className="mt-2 border-t pt-4">
                            <Button type="button" variant="ghost" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                Save Subject
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={!!editSubject}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditSubject(null);
                        editForm.reset();
                        editForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="bg-card border shadow-lg sm:max-w-2xl">
                    <DialogHeader className="mb-4 border-b pb-4">
                        <DialogTitle className="text-xl">Edit Subject</DialogTitle>
                        <DialogDescription>Update {editSubject?.code} details and prerequisite relationships.</DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-6" onSubmit={handleEditSubject}>
                        <SubjectDialogFields form={editForm} currentSubjectId={editSubject?.id} />
                        <DialogFooter className="mt-2 border-t pt-4">
                            <Button type="button" variant="ghost" onClick={() => setEditSubject(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={!!deleteSubject} onOpenChange={(open) => !open && setDeleteSubject(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove subject?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently remove <strong className="text-foreground">{deleteSubject?.code}</strong> from the program
                            curriculum. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDeleteSubject}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Remove subject
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
