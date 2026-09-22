import { ClassData } from "@/components/data-table";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useGradingConfig } from "@/hooks/use-grading-config";
import { computeGwa, formatGwa, gradeScaleLabel, gwaToneClass, type GwaResult } from "@/lib/gwa";
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
import { useEffect, useMemo, useRef, useState, type CSSProperties, type ReactNode } from "react";
import { useReactToPrint } from "react-to-print";

// --- Types ---

interface CurriculumSubject {
    id: number;
    code: string;
    title: string;
    units: number;
    status: "pending" | "ongoing" | "completed" | "failed" | "dropped";
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

interface SchoolBranding {
    name: string;
    address: string;
    logo: string;
    tagline: string;
    email: string;
    phone: string;
}

interface StudentClassesProps {
    user: UserType;
    student_name: string;
    student_number: string;
    course_name: string;
    school: SchoolBranding;
    progress: ProgressSummary;
    curriculum: Curriculum;
    faculty_data: {
        classes: ClassData[];
        stats: unknown[];
    };
    rooms: { id: number; name: string }[];
}

const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

// --- Components ---

const GwaChip = ({ label, result, size = "md", className }: { label: string; result: GwaResult; size?: "sm" | "md"; className?: string }) => {
    const gradingConfig = useGradingConfig();
    const scaleLabel = gradeScaleLabel(result.scale, gradingConfig);
    const valueSize = size === "sm" ? "text-sm" : "text-base";
    return (
        <div
            className={cn(
                "border-border/60 bg-background/55 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-full border px-3 py-1.5 text-xs",
                className,
            )}
        >
            <span className="font-semibold">{label}</span>
            <span className="flex items-baseline gap-1">
                <span className="text-muted-foreground uppercase">GWA</span>
                <span className={cn("font-mono font-bold", valueSize, gwaToneClass(result, gradingConfig))}>{formatGwa(result, gradingConfig)}</span>
                {scaleLabel && <span className="text-muted-foreground">({scaleLabel})</span>}
            </span>
            <span className="text-muted-foreground">
                {result.gradedCount}/{result.itemCount} • {result.gradedUnits}/{result.totalUnits}u
                {result.excludedCount > 0 && <span className="ml-1 text-amber-600">• {result.excludedCount} excl</span>}
            </span>
        </div>
    );
};

const yearLabel = (year: number): string => {
    if (year === 1) return "1st Year";
    if (year === 2) return "2nd Year";
    if (year === 3) return "3rd Year";
    if (year === 4) return "4th Year";
    return `Year ${year}`;
};

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
        case "dropped":
            return (
                <Badge
                    variant="outline"
                    className="bg-destructive/10 text-destructive border-destructive/20 hover:bg-destructive/20 gap-1 transition-colors"
                >
                    <XCircle className="h-3 w-3" />
                    Dropped
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
                "group hover:bg-muted/35 data-[state=selected]:bg-muted transition-colors",
                subject.status === "failed" || subject.status === "dropped"
                    ? "bg-red-50/40 hover:bg-red-50/70 dark:bg-red-950/10 dark:hover:bg-red-950/20"
                    : subject.status === "ongoing"
                      ? "bg-blue-50/40 hover:bg-blue-50/70 dark:bg-blue-950/10 dark:hover:bg-blue-950/20"
                      : subject.status === "completed"
                        ? "bg-emerald-50/25 hover:bg-emerald-50/50 dark:bg-emerald-950/5 dark:hover:bg-emerald-950/10"
                        : "",
            )}
        >
            <TableCell className="text-muted-foreground w-[100px] py-3 font-mono text-xs font-medium">{subject.code}</TableCell>
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
            <TableCell className="w-[80px] py-3 text-center">{subject.units}.0</TableCell>
            <TableCell className="w-[100px] py-3 text-center">
                {subject.grade ? (
                    <span className={cn("font-bold", subject.grade <= 3.0 ? "text-emerald-600 dark:text-emerald-400" : "text-destructive")}>
                        {subject.grade}
                    </span>
                ) : (
                    <span className="text-muted-foreground/30">—</span>
                )}
            </TableCell>
            <TableCell className="w-[140px] py-3 text-right">
                <StatusBadge status={subject.status} grade={null} />
            </TableCell>
        </TableRow>
    );
};

const AcademicStatCard = ({
    icon: Icon,
    label,
    value,
    detail,
    tone,
    children,
    className,
}: {
    icon: typeof Trophy;
    label: string;
    value: string | number;
    detail: string;
    tone: string;
    children?: ReactNode;
    className?: string;
}) => {
    const iconTone = tone.split(" ").find((c) => c.startsWith("text-")) ?? "text-primary";
    const bgTone = tone.split(" ").find((c) => c.startsWith("bg-")) ?? "bg-primary/10";

    return (
        <Card
            className={cn(
                "border-border/40 bg-card/60 hover:border-primary/40 hover:bg-card relative overflow-hidden rounded-xl shadow-sm transition-all duration-300",
                className,
            )}
        >
            <CardContent className="p-3 sm:p-5">
                <div className="flex items-start justify-between">
                    <div className="min-w-0 flex-1">
                        <p className="text-foreground/50 text-[9px] font-bold tracking-wider uppercase sm:text-xs">{label}</p>
                        <p className="text-foreground mt-0.5 truncate text-lg leading-none font-bold tracking-tight sm:text-2xl">{value}</p>
                    </div>
                    <div className={cn("flex h-8 w-8 shrink-0 items-center justify-center rounded-lg sm:h-12 sm:w-12", bgTone)}>
                        <Icon className={cn("h-4 w-4 sm:h-7 sm:w-7", iconTone)} strokeWidth={2} />
                    </div>
                </div>
                <p className="text-foreground/45 mt-2 line-clamp-1 text-[9px] font-medium sm:text-xs">{detail}</p>
                {children}
            </CardContent>
        </Card>
    );
};

// Updated Document View
const CurriculumPrintView = ({
    curriculum,
    student_name,
    student_number,
    course_name,
    progress,
    school,
}: {
    curriculum: Curriculum;
    student_name: string;
    student_number: string;
    course_name: string;
    progress: ProgressSummary;
    school: SchoolBranding;
}) => {
    const printFrameRef = useRef<HTMLDivElement>(null);
    const printRootRef = useRef<HTMLDivElement>(null);
    const [printScale, setPrintScale] = useState(1);
    const generatedOn = useMemo(
        () =>
            new Intl.DateTimeFormat("en-PH", {
                year: "numeric",
                month: "long",
                day: "numeric",
            }).format(new Date()),
        [],
    );

    // Group by Year
    const yearGroups = useMemo(() => {
        const groups: Record<number, { [sem: number]: CurriculumSubject[] }> = {};

        Object.entries(curriculum).forEach(([yearStr, sems]) => {
            const year = parseInt(yearStr);
            groups[year] = {};

            Object.entries(sems).forEach(([semStr, subs]) => {
                const sem = parseInt(semStr);
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
            const preferredScale = 1;

            if (!availableHeight || !contentHeight) {
                setPrintScale(preferredScale);
                return;
            }

            const availableWidth = frame.clientWidth;
            const contentWidth = root.scrollWidth;
            const maxScaleByHeight = (availableHeight / contentHeight) * 0.996;
            const maxScaleByWidth = contentWidth > 0 ? (availableWidth / contentWidth) * 0.996 : preferredScale;
            const nextScale = Math.min(preferredScale, maxScaleByHeight, maxScaleByWidth);
            setPrintScale(Math.max(0.78, Number(nextScale.toFixed(3))));
        };

        const frameId = window.requestAnimationFrame(calculateScale);
        window.addEventListener("resize", calculateScale);

        return () => {
            window.cancelAnimationFrame(frameId);
            window.removeEventListener("resize", calculateScale);
        };
    }, [curriculum, course_name, progress.earned, progress.percentage, progress.total, school.name, student_name, student_number]);

    const statusLabel = (status: CurriculumSubject["status"]): string => {
        if (status === "completed") return "C";
        if (status === "ongoing") return "IP";
        if (status === "failed") return "F";
        return "P";
    };

    const SemesterTable = ({ title, subjects }: { title: string; subjects: CurriculumSubject[] }) => (
        <section className="checklist-semester w-full">
            <h3 className="border-x border-t border-[#536274] bg-[#e7ebef] px-1.5 py-[2px] text-center text-[7.5px] font-bold tracking-[0.12em] text-[#172b46] uppercase">
                {title}
            </h3>
            <table className="w-full border-collapse text-[7.5px] leading-[1.15]">
                <thead>
                    <tr className="bg-[#f5f6f7] text-[6.5px] tracking-[0.08em] text-[#4a5563] uppercase">
                        <th className="w-[16%] border border-[#7a8795] px-1.5 py-[2px] text-left">Code</th>
                        <th className="border border-[#7a8795] px-1.5 py-[2px] text-left">Course description</th>
                        <th className="w-[6%] border border-[#7a8795] px-1 py-[2px] text-center">Units</th>
                        <th className="w-[7%] border border-[#7a8795] px-1 py-[2px] text-center">Grade</th>
                        <th className="w-[6%] border border-[#7a8795] px-1 py-[2px] text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {subjects?.length ? (
                        subjects.map((sub) => (
                            <tr key={sub.id} className="even:bg-[#f8f9fa]">
                                <td className="border border-[#a7afb8] px-1.5 py-[2px] align-top font-mono font-semibold whitespace-nowrap">
                                    {sub.code}
                                </td>
                                <td className="border border-[#a7afb8] px-1.5 py-[2px] align-top">{sub.title}</td>
                                <td className="border border-[#a7afb8] px-1 py-[2px] text-center align-top tabular-nums">{sub.units}</td>
                                <td className="border border-[#a7afb8] px-1 py-[2px] text-center align-top font-semibold tabular-nums">
                                    {sub.grade ?? "—"}
                                </td>
                                <td className="border border-[#a7afb8] px-1 py-[2px] text-center align-top font-bold">{statusLabel(sub.status)}</td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={5} className="border border-[#a7afb8] py-1 text-center text-gray-500 italic">
                                No subjects
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </section>
    );

    return (
        <div className="print-container bg-white text-black">
            <div className="print-frame h-[210mm] w-[297mm] overflow-hidden bg-white px-[8mm] py-[6mm]" ref={printFrameRef}>
                <div
                    className="print-root flex h-full flex-col font-sans text-[8px] leading-tight antialiased"
                    ref={printRootRef}
                    style={{ "--print-scale": printScale } as CSSProperties}
                >
                    <header className="border-b-[2.5px] border-[#172b46] pb-2">
                        <div className="grid grid-cols-[58px_1fr_220px] items-center gap-3">
                            <img src={school.logo} alt={`${school.name} logo`} className="h-[52px] w-[52px] object-contain" />
                            <div>
                                <p className="text-[6.5px] font-semibold tracking-[0.2em] text-[#9a6b1f] uppercase">Office of the Registrar</p>
                                <h1 className="mt-0.5 font-serif text-[15px] leading-none font-bold tracking-[0.06em] text-[#172b46] uppercase">
                                    {school.name}
                                </h1>
                                <p className="mt-1 text-[7px] text-[#566170]">{school.address}</p>
                                {(school.email || school.phone) && (
                                    <p className="mt-0.5 text-[6.5px] text-[#6b7280]">{[school.email, school.phone].filter(Boolean).join("  •  ")}</p>
                                )}
                            </div>
                            <div className="border-l border-[#a7afb8] pl-4 text-right">
                                <p className="font-serif text-[16px] leading-none font-bold tracking-[0.08em] text-[#172b46] uppercase">
                                    Academic Checklist
                                </p>
                                <p className="mt-1 text-[6.5px] font-semibold tracking-[0.16em] text-[#9a6b1f] uppercase">
                                    Curriculum Progress Record
                                </p>
                                <p className="mt-1.5 text-[6.5px] text-[#6b7280]">Generated {generatedOn}</p>
                            </div>
                        </div>
                    </header>

                    <section className="mt-2 grid grid-cols-[1fr_1.4fr_92px_92px] border border-[#657383] bg-white">
                        <DocumentField label="Student number" value={student_number} />
                        <DocumentField label="Student name" value={student_name} />
                        <DocumentField label="Units earned" value={`${progress.earned} / ${progress.total}`} align="center" />
                        <DocumentField label="Completion" value={`${progress.percentage}%`} align="center" last />
                        <div className="col-span-4 border-t border-[#a7afb8] px-2 py-1">
                            <span className="mr-2 text-[6px] font-bold tracking-[0.12em] text-[#6b7280] uppercase">Program</span>
                            <span className="font-semibold text-[#172b46]">{course_name}</span>
                        </div>
                    </section>

                    <main className="mt-2 flex-1 space-y-1.5">
                        {sortedYears.map((year) => (
                            <section key={year} className="checklist-year break-inside-avoid">
                                <div className="flex items-center justify-between bg-[#172b46] px-2 py-[2.5px] text-white">
                                    <h2 className="text-[7px] font-bold tracking-[0.16em] uppercase">{yearLabel(year)}</h2>
                                    <span className="text-[6px] tracking-[0.12em] text-white/75 uppercase">Curriculum sequence</span>
                                </div>
                                <div className="grid grid-cols-2 gap-1">
                                    <SemesterTable title="1st Semester" subjects={yearGroups[year][1]} />
                                    <div className="space-y-1">
                                        <SemesterTable title="2nd Semester" subjects={yearGroups[year][2]} />
                                        {yearGroups[year][3] && yearGroups[year][3].length > 0 && (
                                            <SemesterTable title="Summer" subjects={yearGroups[year][3]} />
                                        )}
                                    </div>
                                </div>
                            </section>
                        ))}
                    </main>

                    <footer className="mt-2 grid grid-cols-[1fr_auto_1fr] items-end gap-5 border-t border-[#657383] pt-1.5 text-[6.5px] text-[#566170]">
                        <div>
                            <p className="font-bold tracking-[0.08em] text-[#172b46] uppercase">Status legend</p>
                            <p className="mt-0.5">C — Completed&nbsp;&nbsp; IP — In progress&nbsp;&nbsp; F — Failed&nbsp;&nbsp; P — Pending</p>
                        </div>
                        <div className="text-center">
                            <p className="font-semibold text-[#172b46]">System-generated academic checklist</p>
                            <p className="mt-0.5">For academic advising and student reference • Page 1 of 1</p>
                        </div>
                        <div className="grid grid-cols-2 gap-5 text-center">
                            <div>
                                <div className="h-4 border-b border-[#657383]" />
                                <p className="mt-1 font-semibold uppercase">Academic adviser</p>
                            </div>
                            <div>
                                <div className="h-4 border-b border-[#657383]" />
                                <p className="mt-1 font-semibold uppercase">Registrar</p>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>

            <style>{`
                .print-root {
                    transform: scale(var(--print-scale));
                    transform-origin: top left;
                    width: calc(100% / var(--print-scale));
                }

                @media print {
                    @page { margin: 0; size: A4 landscape; }
                    html, body {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                        background: #fff !important;
                    }
                    .print-container, .print-frame {
                        width: 297mm !important;
                        height: 210mm !important;
                        margin: 0 !important;
                    }
                    .checklist-year, .checklist-semester { break-inside: avoid; page-break-inside: avoid; }
                }
            `}</style>
        </div>
    );
};

const DocumentField = ({
    label,
    value,
    align = "left",
    last = false,
}: {
    label: string;
    value: string;
    align?: "left" | "center";
    last?: boolean;
}) => (
    <div className={cn("px-2 py-1", !last && "border-r border-[#a7afb8]", align === "center" && "text-center")}>
        <p className="text-[6px] font-bold tracking-[0.1em] text-[#6b7280] uppercase">{label}</p>
        <p className="mt-0.5 truncate font-semibold text-[#172b46] tabular-nums">{value}</p>
    </div>
);

export default function StudentClasses({
    user,
    student_name,
    student_number,
    course_name,
    school,
    progress,
    curriculum,
    faculty_data,
}: StudentClassesProps) {
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

    const gradingConfig = useGradingConfig();

    // GWA computations (overall, per year, per year+semester)
    const { overallGwa, yearGwaMap, semesterGwaMap } = useMemo(() => {
        const yearMap = new Map<number, GwaResult>();
        const semesterMap = new Map<string, GwaResult>();
        const all: CurriculumSubject[] = [];

        Object.entries(curriculum).forEach(([yearStr, sems]) => {
            const year = parseInt(yearStr);
            const yearSubjects: CurriculumSubject[] = [];

            Object.entries(sems).forEach(([semStr, subs]) => {
                const semester = parseInt(semStr);
                const subjects = subs as CurriculumSubject[];
                semesterMap.set(`${year}-${semester}`, computeGwa(subjects, { config: gradingConfig }));
                yearSubjects.push(...subjects);
            });

            yearMap.set(year, computeGwa(yearSubjects, { config: gradingConfig }));
            all.push(...yearSubjects);
        });

        return {
            overallGwa: computeGwa(all, { config: gradingConfig }),
            yearGwaMap: yearMap,
            semesterGwaMap: semesterMap,
        };
    }, [curriculum, gradingConfig]);

    const activeYearGwa = selectedYear === "all" ? null : (yearGwaMap.get(selectedYear) ?? null);

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

            {/* Mobile Header Background */}
            <div className="bg-primary/10 relative h-[110px] w-full overflow-hidden px-4 pt-5 md:hidden">
                <div className="bg-primary/20 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                <div className="bg-primary/10 absolute -bottom-12 -left-12 h-40 w-40 rounded-full blur-2xl" />
                <div className="relative">
                    <p className="text-foreground/60 text-[9px] font-bold tracking-wider uppercase">My Academics</p>
                    <h1 className="text-foreground mt-0.5 text-xl font-bold tracking-tight">
                        Academic <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">Journey</span>
                    </h1>
                </div>
            </div>

            <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: "easeOut" }}
                className={cn("mx-auto flex w-full max-w-7xl flex-col gap-2.5 p-3.5 pb-20 md:gap-6 md:p-6", "relative z-20 -mt-10 md:mt-0")}
            >
                {/* Hero Section */}
                <Card className={cn(dashboardPanelClass, "relative overflow-hidden")}>
                    {/* Decorative Glass Elements */}
                    <div className="bg-primary/5 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                    <div className="bg-primary/10 absolute -bottom-12 -left-12 h-48 w-48 rounded-full blur-2xl" />

                    <CardContent className="relative z-10 space-y-3 p-3 sm:p-5 md:space-y-6 md:p-6">
                        <div className="flex flex-col items-start justify-between gap-3 md:flex-row md:items-end">
                            <div className="hidden space-y-1 sm:space-y-2 md:block">
                                <Badge
                                    variant="outline"
                                    className="border-border/60 bg-background/60 w-fit rounded-full px-2 py-0.5 text-[9px] sm:px-3 sm:py-1 sm:text-xs"
                                >
                                    <Sparkles className="text-primary mr-1.5 h-3 w-3" />
                                    My Academics
                                </Badge>
                                <div>
                                    <h1 className="text-foreground text-xl leading-tight font-bold tracking-tight sm:text-3xl md:text-4xl">
                                        Academic{" "}
                                        <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">Journey</span>
                                    </h1>
                                    <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-[11px] sm:text-sm">
                                        <GraduationCap className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                        {course_name}
                                    </p>
                                </div>
                            </div>

                            <div className="block w-full md:hidden">
                                <p className="text-foreground/50 text-[10px] font-bold tracking-wider uppercase">Enrolled Course</p>
                                <p className="text-foreground mt-1 text-sm leading-tight font-bold">{course_name}</p>
                            </div>

                            {/* View Switcher Controls */}
                            <div className="border-border/60 bg-muted/70 grid w-full grid-cols-2 gap-1 rounded-xl border p-1 shadow-sm sm:w-auto">
                                <Button
                                    variant={viewMode === "interactive" ? "default" : "ghost"}
                                    size="sm"
                                    onClick={() => setViewMode("interactive")}
                                    className="h-8 gap-2 rounded-md px-3 text-xs sm:h-9 sm:px-4 sm:text-sm"
                                >
                                    <LayoutGridIcon className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    <span>Interactive</span>
                                </Button>
                                <Button
                                    variant={viewMode === "document" ? "default" : "ghost"}
                                    size="sm"
                                    onClick={() => setViewMode("document")}
                                    className="h-8 gap-2 rounded-md px-3 text-xs sm:h-9 sm:px-4 sm:text-sm"
                                >
                                    <FileText className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                    <span>Document</span>
                                </Button>
                            </div>
                        </div>

                        {/* Progress Stats (Only show in Interactive Mode) */}
                        {viewMode === "interactive" && (
                            <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="grid gap-3 md:grid-cols-2 md:gap-4">
                                <Card className="border-border/40 bg-card/60 hover:border-primary/40 hover:bg-card relative overflow-hidden rounded-2xl shadow-sm transition-all duration-300">
                                    <CardContent className="p-4 sm:p-5">
                                        <div className="flex items-start justify-between">
                                            <div className="min-w-0 flex-1">
                                                <h3 className="text-foreground/50 text-[10px] font-bold tracking-wider uppercase sm:text-xs">
                                                    Course Completion
                                                </h3>
                                                <div className="mt-1 flex items-baseline gap-2 sm:mt-2">
                                                    <span className="text-foreground text-xl leading-none font-bold tracking-tight sm:text-2xl md:text-3xl">
                                                        {progress.percentage}%
                                                    </span>
                                                    <span className="text-foreground/45 text-[10px] font-medium sm:text-xs">Complete</span>
                                                </div>
                                            </div>
                                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 sm:h-14 sm:w-14">
                                                <Trophy className="h-5 w-5 sm:h-8 sm:w-8" strokeWidth={2} />
                                            </div>
                                        </div>

                                        <div className="mt-4 space-y-2 sm:mt-6">
                                            <div className="text-foreground/45 flex justify-between text-[10px] font-bold sm:text-xs">
                                                <span>{progress.earned} Units Earned</span>
                                                <span>{progress.total} Total Units</span>
                                            </div>
                                            <div className="bg-muted h-2 w-full overflow-hidden rounded-full p-[1.5px]">
                                                <motion.div
                                                    initial={{ width: 0 }}
                                                    animate={{ width: `${progress.percentage}%` }}
                                                    transition={{ duration: 1, delay: 0.2, ease: "easeOut" }}
                                                    className="h-full rounded-full bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.3)]"
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-border/40 bg-card/60 hover:border-primary/40 hover:bg-card relative overflow-hidden rounded-2xl shadow-sm transition-all duration-300">
                                    <CardContent className="p-4 sm:p-5">
                                        <div className="flex items-start justify-between">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-foreground/50 text-[10px] font-bold tracking-wider uppercase sm:text-xs">
                                                    Overall GWA
                                                </p>
                                                <div
                                                    className={cn(
                                                        "mt-1 font-mono text-xl leading-none font-bold tracking-tight sm:mt-2 sm:text-2xl md:text-3xl",
                                                        gwaToneClass(overallGwa, gradingConfig),
                                                    )}
                                                >
                                                    {formatGwa(overallGwa, gradingConfig)}
                                                </div>
                                            </div>
                                            <div
                                                className={cn(
                                                    "flex h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:h-14 sm:w-14",
                                                    gwaToneClass(overallGwa, gradingConfig).replace("text-", "bg-").replace("500", "500/10"),
                                                )}
                                            >
                                                <Sparkles
                                                    className={cn("h-5 w-5 sm:h-8 sm:w-8", gwaToneClass(overallGwa, gradingConfig))}
                                                    strokeWidth={2}
                                                />
                                            </div>
                                        </div>
                                        <p className="text-foreground/45 mt-4 line-clamp-1 text-[10px] font-medium sm:mt-6 sm:text-xs">
                                            {overallGwa.gradedCount}/{overallGwa.itemCount} subjects • {overallGwa.gradedUnits}/
                                            {overallGwa.totalUnits} units
                                            {gradeScaleLabel(overallGwa.scale, gradingConfig) &&
                                                ` • ${gradeScaleLabel(overallGwa.scale, gradingConfig)}`}
                                        </p>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        )}
                    </CardContent>
                </Card>

                {/* Main Content Area */}
                <div>
                    <AnimatePresence mode="wait">
                        {viewMode === "interactive" ? (
                            <motion.div
                                key="interactive"
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -20 }}
                                transition={{ duration: 0.3 }}
                            >
                                <Card className={dashboardPanelClass}>
                                    <CardHeader className="px-4 pt-4 pb-0 md:px-5 md:pt-5">
                                        <div className="mb-5 flex flex-col items-stretch justify-between gap-4 md:flex-row md:items-center">
                                            {/* Pill Navigation (Mobile: Select, Desktop: Pills) */}
                                            <div className="w-full md:w-auto">
                                                {/* Mobile Select */}
                                                <div className="md:hidden">
                                                    <Select
                                                        value={String(selectedYear)}
                                                        onValueChange={(val) => setSelectedYear(val === "all" ? "all" : parseInt(val))}
                                                    >
                                                        <SelectTrigger className="border-border/60 bg-background/70 w-full rounded-lg shadow-sm">
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
                                                <div className="border-border/60 bg-muted/70 hidden items-center gap-1 rounded-lg border p-1 md:flex">
                                                    <button
                                                        onClick={() => setSelectedYear("all")}
                                                        className={cn(
                                                            "hover:bg-muted relative rounded-md px-4 py-2 text-sm font-medium transition-colors",
                                                            selectedYear === "all" ? "text-primary-foreground" : "text-muted-foreground",
                                                        )}
                                                    >
                                                        {selectedYear === "all" && (
                                                            <motion.div
                                                                layoutId="activePill"
                                                                className="bg-primary absolute inset-0 rounded-md shadow-sm"
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
                                                                "hover:bg-muted relative rounded-md px-4 py-2 text-sm font-medium transition-colors",
                                                                selectedYear === year ? "text-primary-foreground" : "text-muted-foreground",
                                                            )}
                                                        >
                                                            {selectedYear === year && (
                                                                <motion.div
                                                                    layoutId="activePill"
                                                                    className="bg-primary absolute inset-0 rounded-md shadow-sm"
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
                                                    className="border-border/60 bg-background/65 rounded-lg pl-9 focus-visible:ring-1"
                                                />
                                            </div>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="min-h-[460px] p-4 pt-1 md:p-5 md:pt-1">
                                        <motion.div layout className="space-y-6">
                                            {activeYearGwa && (
                                                <div className="flex justify-end">
                                                    <GwaChip label={`${yearLabel(selectedYear as number)} GWA`} result={activeYearGwa} />
                                                </div>
                                            )}
                                            {filteredContent.length > 0 ? (
                                                filteredContent.map((section, idx) => {
                                                    const semesterResult = semesterGwaMap.get(`${section.year}-${section.semester}`);
                                                    return (
                                                        <motion.div
                                                            key={`${section.year}-${section.semester}`}
                                                            initial={{ opacity: 0, y: 20 }}
                                                            animate={{ opacity: 1, y: 0 }}
                                                            transition={{ delay: idx * 0.05 }}
                                                            className="space-y-3"
                                                        >
                                                            <div className="flex flex-wrap items-center gap-3">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-border/60 bg-background/60 rounded-full text-xs font-semibold tracking-wide uppercase"
                                                                >
                                                                    Year {section.year} •{" "}
                                                                    {section.semester === 1
                                                                        ? "1st Semester"
                                                                        : section.semester === 2
                                                                          ? "2nd Semester"
                                                                          : "Summer"}
                                                                </Badge>
                                                                <div className="bg-border/50 h-px flex-1" />
                                                                {semesterResult && <GwaChip label="Sem GWA" result={semesterResult} size="sm" />}
                                                            </div>

                                                            <div className="border-border/60 overflow-x-auto rounded-lg border">
                                                                <Table>
                                                                    <TableHeader className="bg-muted/55">
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
                                                                                (c) =>
                                                                                    c.subject_code === subject.code && subject.status === "ongoing",
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
                                                    );
                                                })
                                            ) : (
                                                <div className="text-muted-foreground flex min-h-[360px] flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
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
                                <Card className={`${dashboardPanelClass} overflow-hidden`}>
                                    <div className="bg-muted/35 flex flex-col justify-between gap-3 border-b p-4 sm:flex-row sm:items-center print:hidden">
                                        <div>
                                            <div className="text-foreground flex items-center gap-2 text-sm font-semibold">
                                                <FileText className="text-primary h-4 w-4" />
                                                Official-style document preview
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                A4 landscape • System-generated student reference copy
                                            </p>
                                        </div>
                                        <Button
                                            onClick={() => handlePrint()}
                                            className="min-h-10 gap-2 shadow-sm transition-transform active:scale-[0.96]"
                                        >
                                            <Printer className="h-4 w-4" />
                                            Print / Save as PDF
                                        </Button>
                                    </div>
                                    <div className="bg-muted/20 flex justify-start overflow-auto p-4 sm:justify-center md:p-8">
                                        <div
                                            className="h-[210mm] w-[297mm] max-w-none shrink-0 bg-white shadow-[0_24px_70px_rgba(15,23,42,0.18),0_0_0_1px_rgba(15,23,42,0.08)] print:h-auto print:w-auto print:shadow-none"
                                            ref={componentRef}
                                        >
                                            <CurriculumPrintView
                                                curriculum={curriculum}
                                                student_name={student_name}
                                                student_number={student_number}
                                                course_name={course_name}
                                                progress={progress}
                                                school={school}
                                            />
                                        </div>
                                    </div>
                                </Card>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </motion.div>
        </StudentLayout>
    );
}
