import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { compare } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { ArrowLeftRight, ExternalLink, Loader2, Search, Users } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { route } from "ziggy-js";
import { deliveryModeLabel, type DeliveryMode } from "../lib/class-defaults";

interface StudentEntry {
    id: number;
    student_id: string | number | null;
    name: string;
    course: string | null;
    academic_year: string | number | null;
    student_enrollment_id: number | null;
}

interface ClassInfo {
    id: number;
    record_title: string;
    subject_code: string;
    section: string;
    delivery_mode?: DeliveryMode;
    total_students: number;
}

interface ComparisonData {
    class_a: ClassInfo;
    class_b: ClassInfo;
    comparison: {
        in_both_ids: number[];
        only_in_a: StudentEntry[];
        only_in_b: StudentEntry[];
        all_a: StudentEntry[];
        all_b: StudentEntry[];
    };
}

interface ClassCompareDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classes: Pick<ClassInfo, "id" | "record_title" | "subject_code" | "section" | "delivery_mode">[];
    initialClassIds?: [number, number];
}

function StudentRow({ student, isShared, searchTerm }: { student: StudentEntry; isShared: boolean; searchTerm: string }) {
    const highlight = (text: string) => {
        if (!searchTerm) return text;
        const idx = text.toLowerCase().indexOf(searchTerm.toLowerCase());
        if (idx === -1) return text;
        return (
            <>
                {text.slice(0, idx)}
                <mark className="rounded-sm bg-amber-200 px-0.5 text-amber-900 dark:bg-amber-800 dark:text-amber-100">
                    {text.slice(idx, idx + searchTerm.length)}
                </mark>
                {text.slice(idx + searchTerm.length)}
            </>
        );
    };

    return (
        <div
            className={cn(
                "group flex items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors",
                isShared ? "border border-emerald-200 bg-emerald-50 dark:border-emerald-800/50 dark:bg-emerald-950/30" : "bg-muted/40",
            )}
        >
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5">
                    {isShared && <span className="shrink-0 text-[10px] text-emerald-500">◆</span>}
                    <span className="truncate font-semibold">{highlight(student.name)}</span>
                </div>
                <span className="text-muted-foreground ml-4 font-mono text-xs">{student.student_id ?? "N/A"}</span>
            </div>
            <div className="text-muted-foreground ml-2 flex shrink-0 items-center gap-2 text-xs">
                {student.course && (
                    <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                        {highlight(student.course)}
                    </Badge>
                )}
                {student.academic_year && <span className="font-mono">Yr {student.academic_year}</span>}

                {student.student_enrollment_id && (
                    <Tooltip>
                        <TooltipTrigger
                            render={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    nativeButton={false}
                                    render={<Link href={route("administrators.enrollments.show", student.student_enrollment_id)} />}
                                    className="h-6 w-6"
                                />
                            }
                        >
                            <ExternalLink className="h-3.5 w-3.5" />
                            <span className="sr-only">View Enrollment</span>
                        </TooltipTrigger>
                        <TooltipContent side="left">View Enrollment</TooltipContent>
                    </Tooltip>
                )}
            </div>
        </div>
    );
}

export function ClassCompareDialog({ open, onOpenChange, classes, initialClassIds }: ClassCompareDialogProps) {
    const [classAId, setClassAId] = useState<string>("");
    const [classBId, setClassBId] = useState<string>("");
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<ComparisonData | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [searchA, setSearchA] = useState("");
    const [searchB, setSearchB] = useState("");

    const classOptions = useMemo(
        () =>
            classes.map((c) => ({
                value: String(c.id),
                label: `${c.subject_code} · Sec ${c.section} · ${deliveryModeLabel(c.delivery_mode)}`,
            })),
        [classes],
    );

    const fetchComparison = async (aId: string, bId: string) => {
        if (!aId || !bId) {
            setData(null);
            return;
        }

        setLoading(true);
        setError(null);
        setSearchA("");
        setSearchB("");

        try {
            const url = compare.url({ query: { class_a: aId, class_b: bId } });
            const response = await fetch(url, {
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            });
            const json = await response.json();

            if (!response.ok) {
                setError(json.error ?? "Failed to compare classes.");
                setData(null);
                return;
            }

            // Build normalized data with shared ID set and merged lists
            const inBothIds = new Set(json.comparison.in_both.map((s: StudentEntry) => s.id));
            const enriched: ComparisonData = {
                ...json,
                class_a: {
                    ...json.class_a,
                    delivery_mode: json.class_a.delivery_mode ?? classes.find((item) => item.id === json.class_a.id)?.delivery_mode,
                },
                class_b: {
                    ...json.class_b,
                    delivery_mode: json.class_b.delivery_mode ?? classes.find((item) => item.id === json.class_b.id)?.delivery_mode,
                },
                comparison: {
                    in_both_ids: [...inBothIds],
                    only_in_a: json.comparison.only_in_a,
                    only_in_b: json.comparison.only_in_b,
                    all_a: [...json.comparison.in_both, ...json.comparison.only_in_a],
                    all_b: [...json.comparison.in_both, ...json.comparison.only_in_b],
                },
            };
            setData(enriched);
        } catch {
            setError("Unable to connect. Please check your network and try again.");
            setData(null);
        } finally {
            setLoading(false);
        }
    };

    const handleClassAChange = (val: string) => {
        setClassAId(val);
        if (classBId && val) {
            void fetchComparison(val, classBId);
        } else {
            setData(null);
        }
    };

    useEffect(() => {
        if (!open) return;

        const [first, second] = initialClassIds?.map(String) ?? ["", ""];
        setClassAId(first);
        setClassBId(second);
        setData(null);
        setError(null);
        if (first && second) void fetchComparison(first, second);
    }, [open, initialClassIds]);

    const handleClassBChange = (val: string) => {
        setClassBId(val);
        if (classAId && val) {
            void fetchComparison(classAId, val);
        } else {
            setData(null);
        }
    };

    const handleClose = () => {
        setClassAId("");
        setClassBId("");
        setData(null);
        setError(null);
        setSearchA("");
        setSearchB("");
        onOpenChange(false);
    };

    const filterStudents = (students: StudentEntry[], search: string) => {
        if (!search.trim()) return students;
        const q = search.toLowerCase();
        return students.filter(
            (s) =>
                s.name.toLowerCase().includes(q) ||
                String(s.student_id ?? "")
                    .toLowerCase()
                    .includes(q) ||
                (s.course ?? "").toLowerCase().includes(q),
        );
    };

    const sharedIds = data ? new Set(data.comparison.in_both_ids) : new Set<number>();
    const filteredA = data ? filterStudents(data.comparison.all_a, searchA) : [];
    const filteredB = data ? filterStudents(data.comparison.all_b, searchB) : [];

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-[92vw] xl:max-w-7xl">
                <TooltipProvider>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <ArrowLeftRight className="h-5 w-5" />
                            Compare Students
                        </DialogTitle>
                        <DialogDescription>Select two class sections to compare their enrolled students side by side.</DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-2 gap-3">
                        {/* Class A Selector */}
                        <div className="space-y-1.5">
                            <label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Class A</label>
                            <Select value={classAId} onValueChange={handleClassAChange}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Search & select a class..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-[280px]">
                                    {classOptions.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value} disabled={opt.value === classBId}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Class B Selector */}
                        <div className="space-y-1.5">
                            <label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Class B</label>
                            <Select value={classBId} onValueChange={handleClassBChange}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Search & select a class..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-[280px]">
                                    {classOptions.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value} disabled={opt.value === classAId}>
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {error && (
                        <div className="border-destructive/20 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">{error}</div>
                    )}

                    {loading && (
                        <div className="text-muted-foreground flex items-center justify-center gap-2 py-12 text-sm">
                            <Loader2 className="h-5 w-5 animate-spin" />
                            Loading comparison...
                        </div>
                    )}

                    {data && !loading && (
                        <>
                            {/* Stats Bar */}
                            <div className="bg-muted/50 grid grid-cols-3 items-center gap-3 rounded-lg p-3 text-center text-xs">
                                <div>
                                    <div className="font-mono text-lg font-bold text-emerald-600">{data.comparison.in_both_ids.length}</div>
                                    <div className="text-muted-foreground font-medium">Shared students</div>
                                </div>
                                <div>
                                    <div className="font-mono text-lg font-bold text-amber-600">{data.comparison.only_in_a.length}</div>
                                    <div className="text-muted-foreground font-medium">Only in A</div>
                                </div>
                                <div>
                                    <div className="font-mono text-lg font-bold text-blue-600">{data.comparison.only_in_b.length}</div>
                                    <div className="text-muted-foreground font-medium">Only in B</div>
                                </div>
                            </div>

                            {/* Side-by-side columns */}
                            <div className="grid grid-cols-2 gap-4">
                                {/* Class A Column */}
                                <div className="flex flex-col gap-2">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="text-sm font-semibold">
                                                {data.class_a.subject_code} - Sec {data.class_a.section}
                                            </div>
                                            <Badge variant="secondary">{deliveryModeLabel(data.class_a.delivery_mode)}</Badge>
                                            <div className="text-muted-foreground text-[11px]">
                                                {data.class_a.total_students} student
                                                {data.class_a.total_students !== 1 ? "s" : ""}
                                            </div>
                                        </div>
                                        <Badge variant="outline" className="text-[10px]">
                                            {data.comparison.in_both_ids.length} shared
                                        </Badge>
                                    </div>

                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                                        <Input
                                            placeholder="Search students..."
                                            className="h-8 pl-8 text-xs"
                                            value={searchA}
                                            onChange={(e) => setSearchA(e.target.value)}
                                        />
                                    </div>

                                    <ScrollArea className="h-[320px] rounded-md border">
                                        <div className="space-y-1 p-2">
                                            {filteredA.length === 0 ? (
                                                <div className="text-muted-foreground flex flex-col items-center justify-center py-12 text-center text-sm">
                                                    <Users className="mb-2 h-6 w-6 opacity-30" />
                                                    {searchA ? "No matching students." : "No students to display."}
                                                </div>
                                            ) : (
                                                filteredA.map((student) => (
                                                    <StudentRow
                                                        key={student.id}
                                                        student={student}
                                                        isShared={sharedIds.has(student.id)}
                                                        searchTerm={searchA}
                                                    />
                                                ))
                                            )}
                                        </div>
                                    </ScrollArea>
                                </div>

                                {/* Class B Column */}
                                <div className="flex flex-col gap-2">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="text-sm font-semibold">
                                                {data.class_b.subject_code} - Sec {data.class_b.section}
                                            </div>
                                            <Badge variant="secondary">{deliveryModeLabel(data.class_b.delivery_mode)}</Badge>
                                            <div className="text-muted-foreground text-[11px]">
                                                {data.class_b.total_students} student
                                                {data.class_b.total_students !== 1 ? "s" : ""}
                                            </div>
                                        </div>
                                        <Badge variant="outline" className="text-[10px]">
                                            {data.comparison.in_both_ids.length} shared
                                        </Badge>
                                    </div>

                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                                        <Input
                                            placeholder="Search students..."
                                            className="h-8 pl-8 text-xs"
                                            value={searchB}
                                            onChange={(e) => setSearchB(e.target.value)}
                                        />
                                    </div>

                                    <ScrollArea className="h-[320px] rounded-md border">
                                        <div className="space-y-1 p-2">
                                            {filteredB.length === 0 ? (
                                                <div className="text-muted-foreground flex flex-col items-center justify-center py-12 text-center text-sm">
                                                    <Users className="mb-2 h-6 w-6 opacity-30" />
                                                    {searchB ? "No matching students." : "No students to display."}
                                                </div>
                                            ) : (
                                                filteredB.map((student) => (
                                                    <StudentRow
                                                        key={student.id}
                                                        student={student}
                                                        isShared={sharedIds.has(student.id)}
                                                        searchTerm={searchB}
                                                    />
                                                ))
                                            )}
                                        </div>
                                    </ScrollArea>
                                </div>
                            </div>

                            <div className="bg-muted/30 text-muted-foreground flex items-center gap-4 rounded-md px-3 py-2 text-xs">
                                <span className="flex items-center gap-1">
                                    <span className="text-emerald-500">◆</span> = In both classes
                                </span>
                                <span className="flex items-center gap-1">
                                    <span className="bg-muted/40 h-3 w-3 rounded"></span> = Unique to this class
                                </span>
                            </div>
                        </>
                    )}

                    {!data && !loading && !error && (
                        <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 py-12 text-center text-sm">
                            <ArrowLeftRight className="h-8 w-8 opacity-20" />
                            <p>Select two classes above to compare their student rosters.</p>
                        </div>
                    )}
                </TooltipProvider>
            </DialogContent>
        </Dialog>
    );
}
