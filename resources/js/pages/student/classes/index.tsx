import { ClassData } from "@/components/data-table";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { User as UserType } from "@/types/user";
import { Head, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    CheckCircle2,
    Clock,
    FileText,
    GraduationCap,
    LayoutGrid as LayoutGridIcon,
    Printer,
    Search,
    Sparkles,
    Trophy,
    User as UserIcon,
    XCircle,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState, type CSSProperties } from "react";
import { useReactToPrint } from "react-to-print";

// --- Types ---

interface CurriculumSubject {
    id: number;
    code: string;
    title: string;
    units: number;
    status: "pending" | "ongoing" | "completed" | "failed";
    grade: number | null;
    remarks: string | null;
}

interface Curriculum {
    [year: number]: {
        [sem: number]: CurriculumSubject[];
    };
}

interface ProgressSummary {
    earned: number;
    total: number;
    percentage: number;
}

interface StudentClassesProps {
    user: UserType;
    student_name: string;
    course_name: string;
    progress: ProgressSummary;
    curriculum: Curriculum;
    faculty_data: {
        classes: ClassData[];
        stats: unknown[];
    };
    rooms: { id: number; name: string }[];
}

// --- Components ---

const StatusBadge = ({ status, grade }: { status: CurriculumSubject["status"]; grade: number | null }) => {
    switch (status) {
        case "completed":
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 transition-colors hover:bg-emerald-500/20"
                >
                    <CheckCircle2 className="h-3 w-3" />
                    {grade ? `Grade: ${grade}` : "Completed"}
                </Badge>
            );
        case "failed":
            return (
                <Badge
                    variant="outline"
                    className="bg-destructive/10 text-destructive border-destructive/20 hover:bg-destructive/20 gap-1 transition-colors"
                >
                    <XCircle className="h-3 w-3" />
                    {grade ? `Grade: ${grade}` : "Failed"}
                </Badge>
            );
        case "ongoing":
            return (
                <Badge
                    variant="outline"
                    className="animate-pulse gap-1 border-blue-500/20 bg-blue-500/10 text-blue-600 transition-colors hover:bg-blue-500/20"
                >
                    <Clock className="h-3 w-3" />
                    In Progress
                </Badge>
            );
        default:
            return (
                <Badge variant="secondary" className="text-muted-foreground bg-muted/50 gap-1">
                    <Clock className="h-3 w-3" />
                    Pending
                </Badge>
            );
    }
};

// New Component: InteractiveSubjectRow
const InteractiveSubjectRow = ({ subject, activeClass }: { subject: CurriculumSubject; activeClass?: ClassData }) => {
    return (
        <TableRow
            className={cn(
                "group hover:bg-muted/50 data-[state=selected]:bg-muted transition-colors",
                subject.status === "failed"
                    ? "bg-red-50/50 hover:bg-red-50 dark:bg-red-950/10 dark:hover:bg-red-950/20"
                    : subject.status === "ongoing"
                      ? "bg-blue-50/50 hover:bg-blue-50 dark:bg-blue-950/10 dark:hover:bg-blue-950/20"
                      : subject.status === "completed"
                        ? "bg-emerald-50/30 hover:bg-emerald-50/50 dark:bg-emerald-950/5 dark:hover:bg-emerald-950/10"
                        : "",
            )}
        >
            <TableCell className="text-muted-foreground w-[100px] font-mono text-xs font-medium">{subject.code}</TableCell>
            <TableCell>
                <div className="flex flex-col">
                    <span className="text-foreground group-hover:text-primary text-sm font-medium transition-colors">{subject.title}</span>
                    {activeClass && (
                        <div className="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                            <span className="flex items-center gap-1 text-blue-600 dark:text-blue-400">
                                <UserIcon className="h-3 w-3" /> {activeClass.faculty_name}
                            </span>
                            <span>•</span>
                            <span>{activeClass.schedule}</span>
                            <span>•</span>
                            <span>{activeClass.room}</span>
                        </div>
                    )}
                </div>
            </TableCell>
            <TableCell className="w-[80px] text-center">{subject.units}.0</TableCell>
            <TableCell className="w-[100px] text-center">
                {subject.grade ? (
                    <span className={cn("font-bold", subject.grade <= 3.0 ? "text-emerald-600 dark:text-emerald-400" : "text-destructive")}>
                        {subject.grade}
                    </span>
                ) : (
                    <span className="text-muted-foreground/30">—</span>
                )}
            </TableCell>
            <TableCell className="w-[140px] text-right">
                <StatusBadge status={subject.status} grade={null} />
            </TableCell>
        </TableRow>
    );
};

// Updated Document View
const CurriculumPrintView = ({
    curriculum,
    student_name,
    course_name,
    progress,
}: {
    curriculum: Curriculum;
    student_name: string;
    course_name: string;
    progress: ProgressSummary;
}) => {
    const printFrameRef = useRef<HTMLDivElement>(null);
    const printRootRef = useRef<HTMLDivElement>(null);
    const [printScale, setPrintScale] = useState(1.12);

    // Group by Year
    const yearGroups = useMemo(() => {
        const groups: Record<number, { [sem: number]: CurriculumSubject[] }> = {};

        Object.entries(curriculum).forEach(([yearStr, sems]) => {
            const year = parseInt(yearStr);
            groups[year] = {};

            Object.entries(sems).forEach(([semStr, subs]) => {
                const sem = parseInt(semStr);
                // Filter out subjects if needed, or keep all
                groups[year][sem] = subs as CurriculumSubject[];
            });
        });
        return groups;
    }, [curriculum]);

    const sortedYears = Object.keys(yearGroups).map(Number).sort();

    useEffect(() => {
        const calculateScale = () => {
            const frame = printFrameRef.current;
            const root = printRootRef.current;

            if (!frame || !root) {
                return;
            }

            const availableHeight = frame.clientHeight;
            const contentHeight = root.scrollHeight;
            const preferredScale = 1.12;

            if (!availableHeight || !contentHeight) {
                setPrintScale(preferredScale);
                return;
            }

            const availableWidth = frame.clientWidth;
            const contentWidth = root.scrollWidth;
            const maxScaleByHeight = (availableHeight / contentHeight) * 0.992;
            const maxScaleByWidth = contentWidth > 0 ? (availableWidth / contentWidth) * 0.992 : preferredScale;
            const nextScale = Math.min(preferredScale, maxScaleByHeight, maxScaleByWidth);
            setPrintScale(Math.max(0.82, Number(nextScale.toFixed(3))));
        };

        const frameId = window.requestAnimationFrame(calculateScale);
        window.addEventListener("resize", calculateScale);

        return () => {
            window.cancelAnimationFrame(frameId);
            window.removeEventListener("resize", calculateScale);
        };
    }, [curriculum, course_name, progress.earned, progress.percentage, progress.total, student_name]);

    const SemesterTable = ({ title, subjects }: { title: string; subjects: CurriculumSubject[] }) => (
        <div className="w-full">
            <h3 className="mb-0.5 border-y border-black bg-gray-100 px-1 py-0 text-center text-[7px] font-bold uppercase">{title}</h3>
            <table className="w-full border-collapse text-[8px] leading-tight">
                <thead>
                    <tr className="border-b border-black">
                        <th className="w-14 px-1 py-0 text-left">Code</th>
                        <th className="px-1 py-0 text-left">Description</th>
                        <th className="w-7 px-1 py-0 text-center">U</th>
                        <th className="w-7 px-1 py-0 text-center">G</th>
                    </tr>
                </thead>
                <tbody>
                    {subjects?.map((sub) => (
                        <tr key={sub.id} className="border-b border-gray-200 last:border-0">
                            <td className="px-1 py-0 align-top font-mono">{sub.code}</td>
                            <td className="px-1 py-0 align-top">{sub.title}</td>
                            <td className="px-1 py-0 text-center align-top">{sub.units}</td>
                            <td
                                className={`px-1 py-0 text-center align-top font-bold ${
                                    sub.grade !== null ? "bg-emerald-100/90 text-emerald-800" : ""
                                }`}
                            >
                                {sub.grade || "—"}
                            </td>
                        </tr>
                    )) || (
                        <tr>
                            <td colSpan={4} className="py-0.5 text-center text-gray-400 italic">
                                No subjects
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );

    return (
        <div className="print-container bg-white text-black">
            <div className="print-frame h-[210mm] w-[297mm] overflow-hidden bg-white p-2.5" ref={printFrameRef}>
                <div
                    className="print-root h-full font-serif text-[10px] leading-tight"
                    ref={printRootRef}
                    style={{ "--print-scale": printScale } as CSSProperties}
                >
                    {/* Header */}
                    <div className="mb-1 border-b border-black pb-0.5 text-center">
                        <h1 className="text-[16px] font-bold tracking-wide uppercase">Academic Checklist</h1>
                        <h2 className="text-[10px] font-semibold uppercase">{course_name}</h2>
                        <div className="mt-0.5 flex items-end justify-between text-[7px]">
                            <div className="text-left">
                                <p>
                                    <span className="font-bold">Student:</span> {student_name}
                                </p>
                                <p>
                                    <span className="font-bold">Date:</span> {new Date().toLocaleDateString()}
                                </p>
                            </div>
                            <div className="text-right">
                                <p>
                                    <span className="font-bold">Units:</span> {progress.earned} / {progress.total}
                                </p>
                                <p>
                                    <span className="font-bold">Completion:</span> {progress.percentage}%
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="space-y-1">
                        {sortedYears.map((year) => (
                            <div key={year} className="break-inside-avoid border border-black px-1 pt-0.5 pb-0.5">
                                <h2 className="mb-0.5 border-b border-black pb-0 text-[7px] font-bold uppercase">Year Level {year}</h2>

                                <div className="grid grid-cols-2 gap-0.5">
                                    <SemesterTable title="1st Semester" subjects={yearGroups[year][1]} />
                                    <div className="space-y-0.5">
                                        <SemesterTable title="2nd Semester" subjects={yearGroups[year][2]} />
                                        {yearGroups[year][3] && yearGroups[year][3].length > 0 && (
                                            <SemesterTable title="Summer" subjects={yearGroups[year][3]} />
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Footer */}
                    <div className="mt-1 flex items-center justify-between border-t border-black pt-0.5 text-[6px] text-gray-500">
                        <p>System Generated Report</p>
                        <p>Page 1 of 1</p>
                    </div>
                </div>
            </div>

            <style>{`
                .print-root {
                    transform: scale(var(--print-scale));
                    transform-origin: top left;
                    width: calc(100% / var(--print-scale));
                }

                @media print {
                    @page { margin: 6mm 8mm; size: landscape; }
                    html, body {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                        background: #fff !important;
                    }
                    .print-container, .print-frame {
                        width: 100% !important;
                        height: auto !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                }
            `}</style>
        </div>
    );
};

export default function StudentClasses({ user, student_name, course_name, progress, curriculum, faculty_data }: StudentClassesProps) {
    const { url } = usePage();
    const [selectedYear, setSelectedYear] = useState<number | "all">("all");
    const [searchQuery, setSearchQuery] = useState(() => {
        const [, queryString = ""] = url.split("?");
        const searchParams = new URLSearchParams(queryString);

        return searchParams.get("search") ?? "";
    });
    const [viewMode, setViewMode] = useState<"interactive" | "document">("interactive");

    // Print functionality
    const componentRef = useRef<HTMLDivElement>(null);
    const handlePrint = useReactToPrint({
        contentRef: componentRef,
        documentTitle: `Academic_Checklist_${student_name.replace(/\s+/g, "_")}`,
    });

    // Get unique years
    const years = useMemo(
        () =>
            Object.keys(curriculum)
                .map((y) => parseInt(y))
                .sort(),
        [curriculum],
    );

    useEffect(() => {
        const [, queryString = ""] = url.split("?");
        const searchParams = new URLSearchParams(queryString);
        setSearchQuery(searchParams.get("search") ?? "");
    }, [url]);

    // Filter logic for Interactive View
    const filteredContent = useMemo(() => {
        const content: { year: number; semester: number; subjects: CurriculumSubject[] }[] = [];

        Object.entries(curriculum).forEach(([yearStr, semesters]) => {
            const year = parseInt(yearStr);
            if (selectedYear !== "all" && year !== selectedYear) return;

            Object.entries(semesters).forEach(([semStr, subjects]) => {
                const semester = parseInt(semStr);
                const semesterSubjects = subjects as CurriculumSubject[];
                const filteredSubjects = semesterSubjects.filter(
                    (sub) =>
                        sub.title.toLowerCase().includes(searchQuery.toLowerCase()) || sub.code.toLowerCase().includes(searchQuery.toLowerCase()),
                );

                if (filteredSubjects.length > 0) {
                    content.push({ year, semester, subjects: filteredSubjects });
                }
            });
        });

        return content.sort((a, b) => {
            if (a.year !== b.year) return a.year - b.year;
            return a.semester - b.semester;
        });
    }, [curriculum, selectedYear, searchQuery]);

    return (
        <StudentLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="My Academics" />

            <div className="bg-muted/5 min-h-screen pb-20">
                {/* Hero Section */}
                <div className="from-primary/5 to-background relative border-b bg-gradient-to-b px-4 pt-8 pb-12 md:px-6">
                    <div className="mx-auto max-w-7xl space-y-6">
                        <div className="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">Academic Journey</h1>
                                <p className="text-muted-foreground mt-1 flex items-center gap-2">
                                    <GraduationCap className="h-4 w-4" />
                                    {course_name}
                                </p>
                            </div>

                            {/* View Switcher Controls */}
                            <div className="bg-muted/50 flex items-center gap-2 rounded-lg border p-1">
                                <Button
                                    variant={viewMode === "interactive" ? "default" : "ghost"}
                                    size="sm"
                                    onClick={() => setViewMode("interactive")}
                                    className="gap-2"
                                >
                                    <LayoutGridIcon className="h-4 w-4" />
                                    <span className="hidden sm:inline">Interactive</span>
                                </Button>
                                <Button
                                    variant={viewMode === "document" ? "default" : "ghost"}
                                    size="sm"
                                    onClick={() => setViewMode("document")}
                                    className="gap-2"
                                >
                                    <FileText className="h-4 w-4" />
                                    <span className="hidden sm:inline">Document</span>
                                </Button>
                            </div>
                        </div>

                        {/* Progress Stats (Only show in Interactive Mode) */}
                        {viewMode === "interactive" && (
                            <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="mt-6 grid gap-4 md:grid-cols-3">
                                <Card className="from-primary to-primary/80 text-primary-foreground relative overflow-hidden border-none bg-gradient-to-br shadow-lg md:col-span-2">
                                    <div className="absolute top-0 right-0 p-12 opacity-10">
                                        <Trophy className="h-40 w-40" />
                                    </div>
                                    <CardContent className="relative z-10 flex h-full min-h-[160px] flex-col justify-between p-6">
                                        <div>
                                            <h3 className="text-lg font-medium opacity-90">Course Completion</h3>
                                            <div className="mt-2 flex items-baseline gap-2">
                                                <span className="text-5xl font-bold">{progress.percentage}%</span>
                                                <span className="opacity-75">Complete</span>
                                            </div>
                                        </div>
                                        <div className="mt-6 space-y-2">
                                            <div className="flex justify-between text-sm opacity-80">
                                                <span>{progress.earned} Units Earned</span>
                                                <span>{progress.total} Total Units</span>
                                            </div>
                                            <Progress value={progress.percentage} className="bg-primary-foreground/20 h-2" />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="bg-card flex flex-col justify-center border-none shadow-md">
                                    <CardContent className="space-y-3 p-6 text-center">
                                        <div className="bg-primary/10 text-primary mx-auto flex h-12 w-12 items-center justify-center rounded-full">
                                            <Sparkles className="h-6 w-6" />
                                        </div>
                                        <div>
                                            <div className="text-2xl font-bold">Good Standing</div>
                                            <p className="text-muted-foreground text-sm">Current Status</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        )}
                    </div>
                </div>

                {/* Main Content Area */}
                <div className="relative z-20 mx-auto -mt-6 max-w-7xl px-4 md:px-6">
                    <AnimatePresence mode="wait">
                        {viewMode === "interactive" ? (
                            <motion.div
                                key="interactive"
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -20 }}
                                transition={{ duration: 0.3 }}
                            >
                                <Card className="border shadow-sm">
                                    <CardHeader className="px-6 pt-6 pb-0">
                                        <div className="mb-6 flex flex-col items-center justify-between gap-4 md:flex-row">
                                            {/* Pill Navigation (Mobile: Select, Desktop: Pills) */}
                                            <div className="w-full md:w-auto">
                                                {/* Mobile Select */}
                                                <div className="md:hidden">
                                                    <Select
                                                        value={String(selectedYear)}
                                                        onValueChange={(val) => setSelectedYear(val === "all" ? "all" : parseInt(val))}
                                                    >
                                                        <SelectTrigger className="bg-background border-input w-full shadow-sm">
                                                            <SelectValue placeholder="Select Year Level" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">Overview</SelectItem>
                                                            {years.map((year) => (
                                                                <SelectItem key={year} value={String(year)}>
                                                                    {year === 1
                                                                        ? "1st Year"
                                                                        : year === 2
                                                                          ? "2nd Year"
                                                                          : year === 3
                                                                            ? "3rd Year"
                                                                            : year === 4
                                                                              ? "4th Year"
                                                                              : `Year ${year}`}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                {/* Desktop Pills */}
                                                <div className="hidden items-center gap-2 md:flex">
                                                    <button
                                                        onClick={() => setSelectedYear("all")}
                                                        className={cn(
                                                            "hover:bg-muted relative rounded-full px-4 py-2 text-sm font-medium transition-colors",
                                                            selectedYear === "all" ? "text-primary-foreground" : "text-muted-foreground",
                                                        )}
                                                    >
                                                        {selectedYear === "all" && (
                                                            <motion.div
                                                                layoutId="activePill"
                                                                className="bg-primary absolute inset-0 rounded-full shadow-sm"
                                                                transition={{ type: "spring", bounce: 0.2, duration: 0.6 }}
                                                            />
                                                        )}
                                                        <span className="relative z-10">Overview</span>
                                                    </button>

                                                    {years.map((year) => (
                                                        <button
                                                            key={year}
                                                            onClick={() => setSelectedYear(year)}
                                                            className={cn(
                                                                "hover:bg-muted relative rounded-full px-4 py-2 text-sm font-medium transition-colors",
                                                                selectedYear === year ? "text-primary-foreground" : "text-muted-foreground",
                                                            )}
                                                        >
                                                            {selectedYear === year && (
                                                                <motion.div
                                                                    layoutId="activePill"
                                                                    className="bg-primary absolute inset-0 rounded-full shadow-sm"
                                                                    transition={{ type: "spring", bounce: 0.2, duration: 0.6 }}
                                                                />
                                                            )}
                                                            <span className="relative z-10">
                                                                {year === 1
                                                                    ? "1st Year"
                                                                    : year === 2
                                                                      ? "2nd Year"
                                                                      : year === 3
                                                                        ? "3rd Year"
                                                                        : year === 4
                                                                          ? "4th Year"
                                                                          : `Year ${year}`}
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Search */}
                                            <div className="relative w-full md:w-64">
                                                <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                                <Input
                                                    placeholder="Find subject..."
                                                    value={searchQuery}
                                                    onChange={(e) => setSearchQuery(e.target.value)}
                                                    className="bg-muted/30 border-none pl-9 focus-visible:ring-1"
                                                />
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="bg-muted/5 min-h-[500px] p-6">
                                        <motion.div layout className="space-y-8">
                                            {filteredContent.length > 0 ? (
                                                filteredContent.map((section, idx) => (
                                                    <motion.div
                                                        key={`${section.year}-${section.semester}`}
                                                        initial={{ opacity: 0, y: 20 }}
                                                        animate={{ opacity: 1, y: 0 }}
                                                        transition={{ delay: idx * 0.05 }}
                                                        className="space-y-3"
                                                    >
                                                        <div className="flex items-center gap-3 px-2">
                                                            <Badge
                                                                variant="outline"
                                                                className="bg-muted/50 text-sm font-bold tracking-wider uppercase"
                                                            >
                                                                Year {section.year} •{" "}
                                                                {section.semester === 1
                                                                    ? "1st Semester"
                                                                    : section.semester === 2
                                                                      ? "2nd Semester"
                                                                      : "Summer"}
                                                            </Badge>
                                                            <div className="bg-border/50 h-px flex-1" />
                                                        </div>

                                                        <div className="overflow-x-auto rounded-md border">
                                                            <Table>
                                                                <TableHeader className="bg-muted/50">
                                                                    <TableRow>
                                                                        <TableHead className="w-[100px]">Code</TableHead>
                                                                        <TableHead>Description</TableHead>
                                                                        <TableHead className="w-[80px] text-center">Units</TableHead>
                                                                        <TableHead className="w-[100px] text-center">Grade</TableHead>
                                                                        <TableHead className="w-[140px] text-right">Status</TableHead>
                                                                    </TableRow>
                                                                </TableHeader>
                                                                <TableBody>
                                                                    {section.subjects.map((subject) => {
                                                                        const activeClass = faculty_data.classes.find(
                                                                            (c) => c.subject_code === subject.code && subject.status === "ongoing",
                                                                        );

                                                                        return (
                                                                            <InteractiveSubjectRow
                                                                                key={subject.id}
                                                                                subject={subject}
                                                                                activeClass={activeClass}
                                                                            />
                                                                        );
                                                                    })}
                                                                </TableBody>
                                                            </Table>
                                                        </div>
                                                    </motion.div>
                                                ))
                                            ) : (
                                                <div className="text-muted-foreground flex flex-col items-center justify-center py-20">
                                                    <Search className="mb-4 h-12 w-12 opacity-20" />
                                                    <p>No subjects found matching your search.</p>
                                                </div>
                                            )}
                                        </motion.div>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ) : (
                            <motion.div
                                key="document"
                                initial={{ opacity: 0, scale: 0.95 }}
                                animate={{ opacity: 1, scale: 1 }}
                                exit={{ opacity: 0, scale: 0.95 }}
                                transition={{ duration: 0.3 }}
                            >
                                <Card className="overflow-hidden border shadow-lg">
                                    <div className="bg-muted/30 flex items-center justify-between border-b p-4 print:hidden">
                                        <div className="text-muted-foreground text-sm">
                                            <span className="text-foreground font-semibold">Print Preview</span> • Scaled to fit A4/Letter size
                                        </div>
                                        <Button onClick={() => handlePrint()} className="gap-2 shadow-sm">
                                            <Printer className="h-4 w-4" />
                                            Print / Save as PDF
                                        </Button>
                                    </div>
                                    <div className="flex justify-center overflow-auto bg-gray-50/50 p-8">
                                        <div
                                            className="h-[210mm] w-[297mm] max-w-none bg-white shadow-xl print:h-auto print:w-auto print:shadow-none"
                                            ref={componentRef}
                                        >
                                            <CurriculumPrintView
                                                curriculum={curriculum}
                                                student_name={student_name}
                                                course_name={course_name}
                                                progress={progress}
                                            />
                                        </div>
                                    </div>
                                </Card>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </StudentLayout>
    );
}
