import AdminLayout from "@/components/administrators/admin-layout";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from "@/components/ui/alert-dialog";
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
import { Tabs, TabsContent, TabsList, TabsTab } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { ColumnDef, ColumnFiltersState, SortingState, flexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from "@tanstack/react-table";
import { ArrowUpDown, BookOpen, ChevronLeft, Edit, FilePenLine, GraduationCap, Plus, Search, Settings, Trash2 } from "lucide-react";
import { useMemo, useState, type FormEvent } from "react";
import { route } from "ziggy-js";

type DepartmentOption = { id: number; name: string; code: string };
type ProgramPayload = { id: number; code: string; title: string; description: string | null; department_id: number | null; department_name: string | null; department_code: string | null; course_type_id: number | null; course_type_name: string | null; lec_per_unit: string | number | null; remarks: string | null; curriculum_year: string | null; miscelaneous: string | number | null };
type SubjectPayload = { id: number; code: string; title: string; classification: string | null; units: number | null; lecture: number | null; laboratory: number | null; academic_year: number | null; semester: number | null; group: string | null; is_credited: boolean; pre_riquisite: number[] };
type SubjectOption = { id: number; code: string; title: string };
type ClassificationOption = { value: string; label: string };
type SubjectFormData = { code: string; title: string; classification: string; units: string; lecture: string; laboratory: string; academic_year: string; semester: string; group: string; is_credited: boolean; pre_riquisite: number[] };

interface Props {
    user: User;
    program: ProgramPayload;
    stats: { subjects: number; credited_subjects: number; academic_years: number; subjects_with_requisites: number; total_units: number };
    subjects: SubjectPayload[];
    subject_options: SubjectOption[];
    classification_options: ClassificationOption[];
    departments: DepartmentOption[];
    course_types: { id: number; name: string }[];
}

const yearOptions = [{ value: "1", label: "1st Year" }, { value: "2", label: "2nd Year" }, { value: "3", label: "3rd Year" }, { value: "4", label: "4th Year" }];
const semesterOptions = [{ value: "1", label: "1st Semester" }, { value: "2", label: "2nd Semester" }, { value: "3", label: "Summer" }];
const fmt = (v: string | number | null): string => (v === null || v === undefined ? "" : String(v));
const FieldError = ({ message }: { message?: string }) => (message ? <p className="text-destructive mt-1 text-xs font-medium">{message}</p> : null);

export default function CurriculumProgramShow({ user, program, stats, subjects, subject_options, classification_options, departments, course_types }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editSubject, setEditSubject] = useState<SubjectPayload | null>(null);
    const [deleteSubject, setDeleteSubject] = useState<SubjectPayload | null>(null);
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = useState("");

    const subjectOptionMap = useMemo(() => new Map(subject_options.map((o) => [o.id, o])), [subject_options]);
    const classificationMap = useMemo(() => new Map(classification_options.map((o) => [o.value, o.label])), [classification_options]);

    const programForm = useForm({
        code: program.code,
        title: program.title,
        description: program.description ?? "",
        department_id: program.department_id ? String(program.department_id) : "",
        course_type_id: program.course_type_id ? String(program.course_type_id) : "",
        lec_per_unit: fmt(program.lec_per_unit),
        remarks: program.remarks ?? "",
        curriculum_year: program.curriculum_year ?? "",
        miscelaneous: fmt(program.miscelaneous),
    });

    const defaultSubject: SubjectFormData = { code: "", title: "", classification: classification_options[0]?.value ?? "credited", units: "", lecture: "", laboratory: "", academic_year: "", semester: "", group: "", is_credited: true, pre_riquisite: [] };
    const createForm = useForm<SubjectFormData>({ ...defaultSubject });
    const editForm = useForm<SubjectFormData>({ ...defaultSubject });

    const handleProgramSubmit = (e: FormEvent) => { e.preventDefault(); programForm.put(route("administrators.curriculum.programs.update", program.id), { preserveScroll: true }); };
    const handleCreateSubject = (e: FormEvent) => { e.preventDefault(); createForm.post(route("administrators.curriculum.programs.subjects.store", program.id), { preserveScroll: true, onSuccess: () => { setIsCreateOpen(false); createForm.reset(); createForm.clearErrors(); } }); };
    const handleEditSubject = (e: FormEvent) => { e.preventDefault(); if (!editSubject) return; editForm.put(route("administrators.curriculum.programs.subjects.update", { course: program.id, subject: editSubject.id }), { preserveScroll: true, onSuccess: () => { setEditSubject(null); editForm.reset(); editForm.clearErrors(); } }); };
    const openEditSubject = (s: SubjectPayload) => { editForm.setData({ code: s.code, title: s.title, classification: s.classification ?? defaultSubject.classification, units: fmt(s.units), lecture: fmt(s.lecture), laboratory: fmt(s.laboratory), academic_year: s.academic_year?.toString() ?? "", semester: s.semester?.toString() ?? "", group: s.group ?? "", is_credited: s.is_credited, pre_riquisite: s.pre_riquisite ?? [] }); editForm.clearErrors(); setEditSubject(s); };
    const handleDeleteSubject = () => { if (!deleteSubject) return; router.delete(route("administrators.curriculum.programs.subjects.destroy", { course: program.id, subject: deleteSubject.id }), { preserveScroll: true, onSuccess: () => setDeleteSubject(null) }); };

    const renderPrereqs = (s: SubjectPayload) => {
        if (!s.pre_riquisite?.length) return <span className="text-muted-foreground text-xs italic">None</span>;
        return (<div className="flex flex-wrap gap-1">{s.pre_riquisite.map((id) => { const o = subjectOptionMap.get(id); return <Badge key={id} variant="outline" className="bg-muted/50 font-mono text-[10px]" title={o ? o.title : `#${id}`}>{o ? o.code : `#${id}`}</Badge>; })}</div>);
    };

    const columns: ColumnDef<SubjectPayload>[] = useMemo(() => [
        { accessorKey: "code", header: ({ column }) => (<Button variant="ghost" className="-ml-4 h-8" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}>Subject<ArrowUpDown className="ml-2 h-4 w-4" /></Button>),
            cell: ({ row }) => { const s = row.original; return (<div className="flex flex-col gap-1"><span className="text-foreground font-semibold tracking-tight">{s.code}</span><span className="text-muted-foreground max-w-xs truncate text-xs">{s.title}</span><div className="mt-1 flex flex-wrap gap-1"><Badge variant="secondary" className="h-4 px-1.5 text-[10px] font-medium">{s.classification ? (classificationMap.get(s.classification) ?? s.classification) : "Unassigned"}</Badge>{s.is_credited && <Badge variant="outline" className="h-4 border-emerald-200 bg-emerald-50 px-1.5 text-[10px] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400">Credited</Badge>}</div></div>); },
            filterFn: (row, _id, value) => `${row.original.code} ${row.original.title}`.toLowerCase().includes((value as string).toLowerCase()) },
        { accessorKey: "academic_year", header: "Year & Sem", cell: ({ row }) => (<div className="flex flex-col"><span className="text-foreground text-sm font-medium">Year {row.original.academic_year ?? "-"}</span><span className="text-muted-foreground text-xs">Sem {row.original.semester ?? "-"}</span></div>) },
        { accessorKey: "units", header: "Units", cell: ({ row }) => (<div className="flex flex-col"><span className="text-foreground text-sm font-medium">{row.original.units ?? 0} Units</span><span className="text-muted-foreground text-xs">Lec: {row.original.lecture ?? 0} | Lab: {row.original.laboratory ?? 0}</span></div>) },
        { id: "prerequisites", header: "Prerequisites", cell: ({ row }) => renderPrereqs(row.original) },
        { id: "actions", cell: ({ row }) => (<div className="flex justify-end gap-1"><Button variant="ghost" size="icon" className="text-primary hover:bg-primary/10 h-8 w-8" onClick={() => openEditSubject(row.original)} title="Edit"><Edit className="h-4 w-4" /></Button><Button variant="ghost" size="icon" className="text-destructive hover:bg-destructive/10 h-8 w-8" onClick={() => setDeleteSubject(row.original)} title="Delete"><Trash2 className="h-4 w-4" /></Button></div>) },
    ], [classificationMap, subjectOptionMap]);

    const table = useReactTable({ data: subjects, columns, onSortingChange: setSorting, onColumnFiltersChange: setColumnFilters, getCoreRowModel: getCoreRowModel(), getPaginationRowModel: getPaginationRowModel(), getSortedRowModel: getSortedRowModel(), getFilteredRowModel: getFilteredRowModel(), globalFilterFn: "includesString", onGlobalFilterChange: setGlobalFilter, state: { sorting, columnFilters, globalFilter }, initialState: { pagination: { pageSize: 10 } } });

    const SubjectFields = ({ form, currentSubjectId }: { form: typeof createForm; currentSubjectId?: number }) => {
        const prereqOptions = subject_options.filter((o) => o.id !== currentSubjectId).map((o) => ({ label: `${o.code} - ${o.title}`, value: String(o.id) }));
        return (
            <div className="grid gap-5">
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Subject Code</Label><Input placeholder="e.g. CS101" value={form.data.code} onChange={(e) => form.setData("code", e.target.value)} /><FieldError message={form.errors.code} /></div>
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Subject Title</Label><Input placeholder="e.g. Intro to CS" value={form.data.title} onChange={(e) => form.setData("title", e.target.value)} /><FieldError message={form.errors.title} /></div>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Classification</Label><Select value={form.data.classification} onValueChange={(v) => form.setData("classification", v)}><SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger><SelectContent>{classification_options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent></Select><FieldError message={form.errors.classification} /></div>
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Group</Label><Input placeholder="e.g. Core" value={form.data.group} onChange={(e) => form.setData("group", e.target.value)} /><FieldError message={form.errors.group} /></div>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Units</Label><Input type="number" min="0" value={form.data.units} onChange={(e) => form.setData("units", e.target.value)} /><FieldError message={form.errors.units} /></div>
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Lecture Hours</Label><Input type="number" min="0" value={form.data.lecture} onChange={(e) => form.setData("lecture", e.target.value)} /><FieldError message={form.errors.lecture} /></div>
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Lab Hours</Label><Input type="number" min="0" value={form.data.laboratory} onChange={(e) => form.setData("laboratory", e.target.value)} /><FieldError message={form.errors.laboratory} /></div>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Academic Year</Label><Select value={form.data.academic_year} onValueChange={(v) => form.setData("academic_year", v)}><SelectTrigger><SelectValue placeholder="Select year" /></SelectTrigger><SelectContent>{yearOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent></Select><FieldError message={form.errors.academic_year} /></div>
                    <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Semester</Label><Select value={form.data.semester} onValueChange={(v) => form.setData("semester", v)}><SelectTrigger><SelectValue placeholder="Select semester" /></SelectTrigger><SelectContent>{semesterOptions.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent></Select><FieldError message={form.errors.semester} /></div>
                </div>
                <div className="grid gap-2"><Label className="text-foreground/80 font-semibold">Prerequisites</Label><MultiSelect options={prereqOptions} selected={form.data.pre_riquisite.map(String)} onChange={(sel: string[]) => form.setData("pre_riquisite", sel.map(Number))} placeholder="Select prerequisites..." searchPlaceholder="Search subjects..." className="bg-background w-full" /><FieldError message={form.errors.pre_riquisite as string | undefined} /></div>
                <div className="bg-muted/30 border-border/50 flex items-center gap-2 rounded-lg border p-3"><Checkbox id="is-credited" checked={form.data.is_credited} onCheckedChange={(c) => form.setData("is_credited", Boolean(c))} /><Label htmlFor="is-credited" className="text-foreground/80 cursor-pointer font-semibold select-none">Mark as credited subject</Label></div>
            </div>
        );
    };

    return (
        <AdminLayout user={user} title={`Program: ${program.code}`}>
            <Head title={`Program: ${program.code}`} />
            <div className="flex flex-col gap-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="bg-primary/10 rounded-xl p-3"><GraduationCap className="text-primary size-6" /></div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{program.code}</h1>
                            <p className="text-muted-foreground text-sm">{program.title}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary" className="px-2.5 py-0.5 text-xs">{stats.subjects} subjects</Badge>
                        <Badge variant="outline" className="bg-background px-2.5 py-0.5 text-xs">{stats.total_units} units</Badge>
                        <Badge variant="outline" className="bg-background px-2.5 py-0.5 text-xs">{stats.credited_subjects} credited</Badge>
                        <Button asChild variant="ghost" size="sm"><Link href={route("administrators.curriculum.programs.index")}><ChevronLeft className="mr-1 size-4" />Programs</Link></Button>
                    </div>
                </div>

                {/* Tabs */}
                <Tabs defaultValue="subjects">
                    <TabsList variant="underline">
                        <TabsTab value="subjects"><BookOpen className="mr-1.5 size-4" />Subjects ({stats.subjects})</TabsTab>
                        <TabsTab value="settings"><Settings className="mr-1.5 size-4" />Program Settings</TabsTab>
                    </TabsList>

                    {/* Subjects Tab */}
                    <TabsContent value="subjects">
                        <Card className="border shadow-sm">
                            <CardHeader className="bg-muted/20 border-b pb-4">
                                <div className="flex flex-col justify-between gap-4 sm:flex-row">
                                    <div><CardTitle className="text-lg">Curriculum Subjects</CardTitle><CardDescription>Manage subjects for this program.</CardDescription></div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div className="relative w-full sm:w-64"><Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" /><Input id="subject-search" placeholder="Search subjects..." value={globalFilter ?? ""} onChange={(e) => setGlobalFilter(e.target.value)} className="bg-background h-9 w-full pl-9" /></div>
                                        <Button size="sm" onClick={() => setIsCreateOpen(true)} className="h-9"><Plus className="mr-2 size-4" />Add Subject</Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader className="bg-muted/30">{table.getHeaderGroups().map((hg) => (<TableRow key={hg.id} className="hover:bg-transparent">{hg.headers.map((h) => (<TableHead key={h.id} className="py-3">{h.isPlaceholder ? null : flexRender(h.column.columnDef.header, h.getContext())}</TableHead>))}</TableRow>))}</TableHeader>
                                    <TableBody>{table.getRowModel().rows?.length ? table.getRowModel().rows.map((row) => (<TableRow key={row.id} className="group hover:bg-muted/20">{row.getVisibleCells().map((cell) => (<TableCell key={cell.id} className="py-3">{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>))}</TableRow>)) : (<TableRow><TableCell colSpan={columns.length} className="text-muted-foreground h-32 text-center">{subjects.length === 0 ? "No subjects yet. Add the first subject." : "No subjects match your search."}</TableCell></TableRow>)}</TableBody>
                                </Table>
                            </CardContent>
                            {table.getPageCount() > 1 && (<div className="flex items-center justify-end space-x-2 border-t p-4"><Button variant="outline" size="sm" onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}>Previous</Button><span className="text-muted-foreground text-sm">Page {table.getState().pagination.pageIndex + 1} of {table.getPageCount()}</span><Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>Next</Button></div>)}
                        </Card>
                    </TabsContent>

                    {/* Settings Tab */}
                    <TabsContent value="settings">
                        <Card className="border shadow-sm">
                            <CardHeader><CardTitle className="text-lg">Program Settings</CardTitle><CardDescription>Update program details, department assignment, and fee configuration.</CardDescription></CardHeader>
                            <CardContent>
                                <form className="grid gap-6" onSubmit={handleProgramSubmit}>
                                    <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                                        <div className="grid gap-2 lg:col-span-1"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Program Code</Label><Input value={programForm.data.code} className="font-medium" onChange={(e) => programForm.setData("code", e.target.value)} /><FieldError message={programForm.errors.code} /></div>
                                        <div className="grid gap-2 lg:col-span-3"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Program Title</Label><Input value={programForm.data.title} className="font-medium" onChange={(e) => programForm.setData("title", e.target.value)} /><FieldError message={programForm.errors.title} /></div>
                                        <div className="grid gap-2 lg:col-span-2">
                                            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Department</Label>
                                            <Select value={programForm.data.department_id} onValueChange={(v) => programForm.setData("department_id", v)}>
                                                <SelectTrigger><SelectValue placeholder="Select department" /></SelectTrigger>
                                                <SelectContent>{departments.map((d) => <SelectItem key={d.id} value={String(d.id)}>{d.code} — {d.name}</SelectItem>)}</SelectContent>
                                            </Select>
                                            <FieldError message={programForm.errors.department_id} />
                                        </div>
                                        <div className="grid gap-2 lg:col-span-2">
                                            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Course Type</Label>
                                            <Select value={programForm.data.course_type_id} onValueChange={(v) => programForm.setData("course_type_id", v)}>
                                                <SelectTrigger><SelectValue placeholder="Select type" /></SelectTrigger>
                                                <SelectContent>{course_types.map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.name}</SelectItem>)}</SelectContent>
                                            </Select>
                                            <FieldError message={programForm.errors.course_type_id} />
                                        </div>
                                        <div className="grid gap-2 lg:col-span-2"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Curriculum Year</Label><Input value={programForm.data.curriculum_year} onChange={(e) => programForm.setData("curriculum_year", e.target.value)} /><FieldError message={programForm.errors.curriculum_year} /></div>
                                        <div className="grid gap-2"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Lec per Unit</Label><Input type="number" value={programForm.data.lec_per_unit} onChange={(e) => programForm.setData("lec_per_unit", e.target.value)} /><FieldError message={programForm.errors.lec_per_unit} /></div>
                                        <div className="grid gap-2 lg:col-span-2"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Misc Fee</Label><Input type="number" value={programForm.data.miscelaneous} onChange={(e) => programForm.setData("miscelaneous", e.target.value)} /><FieldError message={programForm.errors.miscelaneous} /></div>
                                    </div>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <div className="grid gap-2"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Description</Label><Textarea className="min-h-[100px] resize-none" value={programForm.data.description} onChange={(e) => programForm.setData("description", e.target.value)} /><FieldError message={programForm.errors.description} /></div>
                                        <div className="grid gap-2"><Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Remarks</Label><Textarea className="min-h-[100px] resize-none" value={programForm.data.remarks} onChange={(e) => programForm.setData("remarks", e.target.value)} /><FieldError message={programForm.errors.remarks} /></div>
                                    </div>
                                    <div className="flex justify-end pt-2"><Button type="submit" disabled={programForm.processing}><FilePenLine className="mr-2 size-4" />Save Program Details</Button></div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Create Subject Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={(o) => { setIsCreateOpen(o); if (!o) { createForm.reset(); createForm.clearErrors(); } }}>
                <DialogContent className="bg-card border shadow-lg sm:max-w-2xl">
                    <DialogHeader className="mb-4 border-b pb-4"><DialogTitle className="text-xl">Add Subject</DialogTitle><DialogDescription>Add a new subject under {program.code}.</DialogDescription></DialogHeader>
                    <form className="grid gap-6" onSubmit={handleCreateSubject}><SubjectFields form={createForm} /><DialogFooter className="mt-2 border-t pt-4"><Button type="button" variant="ghost" onClick={() => setIsCreateOpen(false)}>Cancel</Button><Button type="submit" disabled={createForm.processing}>Save Subject</Button></DialogFooter></form>
                </DialogContent>
            </Dialog>

            {/* Edit Subject Dialog */}
            <Dialog open={!!editSubject} onOpenChange={(o) => { if (!o) { setEditSubject(null); editForm.reset(); editForm.clearErrors(); } }}>
                <DialogContent className="bg-card border shadow-lg sm:max-w-2xl">
                    <DialogHeader className="mb-4 border-b pb-4"><DialogTitle className="text-xl">Edit Subject</DialogTitle><DialogDescription>Update {editSubject?.code} details.</DialogDescription></DialogHeader>
                    <form className="grid gap-6" onSubmit={handleEditSubject}><SubjectFields form={editForm} currentSubjectId={editSubject?.id} /><DialogFooter className="mt-2 border-t pt-4"><Button type="button" variant="ghost" onClick={() => setEditSubject(null)}>Cancel</Button><Button type="submit" disabled={editForm.processing}>Save Changes</Button></DialogFooter></form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={!!deleteSubject} onOpenChange={(o) => !o && setDeleteSubject(null)}>
                <AlertDialogContent><AlertDialogHeader><AlertDialogTitle>Remove subject?</AlertDialogTitle><AlertDialogDescription>This will permanently remove <strong className="text-foreground">{deleteSubject?.code}</strong> from the program.</AlertDialogDescription></AlertDialogHeader><AlertDialogFooter><AlertDialogCancel>Cancel</AlertDialogCancel><AlertDialogAction onClick={handleDeleteSubject} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">Remove subject</AlertDialogAction></AlertDialogFooter></AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
