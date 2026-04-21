import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { FileText, Plus } from "lucide-react";
import { Fragment, useMemo } from "react";
import { Badge as ReuiBadge } from "@/components/ui/badge";
import { computeGwa, formatGwa, gradeScaleLabel, gwaToneClass, type GwaResult } from "@/lib/gwa";
import type { ChecklistHistoryRecord, ChecklistSemesterGroup, ChecklistSubject, ChecklistYearGroup, StudentDetail } from "../types";

interface StudentChecklistSectionProps {
    student: StudentDetail;
    onPrintTranscript: () => void;
    onAddNonCreditedSubject: () => void;
    onSubjectClick: (subject: ChecklistSubject) => void;
    classificationBadgeVariant: (classification: string | null | undefined) => "info-light" | "success-light" | "warning-light";
}

function getYearLabel(year: number): string {
    if (year === 1) {
        return "1st Year";
    }

    if (year === 2) {
        return "2nd Year";
    }

    if (year === 3) {
        return "3rd Year";
    }

    if (year === 4) {
        return "4th Year";
    }

    return `${year}th Year`;
}

function getSemesterLabel(semester: number): string {
    if (semester === 1) {
        return "1st Semester";
    }

    if (semester === 2) {
        return "2nd Semester";
    }

    if (semester === 3) {
        return "Summer";
    }

    return `Semester ${semester}`;
}

function collectYearSubjects(yearGroup: ChecklistYearGroup): ChecklistSubject[] {
    return yearGroup.semesters.flatMap((sem: ChecklistSemesterGroup) => sem.subjects);
}

interface GwaSummaryProps {
    label: string;
    result: GwaResult;
    className?: string;
}

function GwaSummary({ label, result, className }: GwaSummaryProps) {
    return (
        <div
            className={`flex flex-wrap items-center gap-x-4 gap-y-1 rounded-md border bg-muted/30 px-3 py-2 text-sm ${className ?? ""}`}
        >
            <span className="font-semibold">{label}</span>
            <span className="flex items-baseline gap-1">
                <span className="text-muted-foreground text-xs uppercase">GWA</span>
                <span className={`font-mono text-base font-bold ${gwaToneClass(result)}`}>{formatGwa(result)}</span>
                {gradeScaleLabel(result.scale) && (
                    <span className="text-muted-foreground text-xs">
                        ({gradeScaleLabel(result.scale)})
                    </span>
                )}
            </span>
            <span className="text-muted-foreground text-xs">
                {result.gradedCount}/{result.itemCount} subjects graded
            </span>
            <span className="text-muted-foreground text-xs">
                {result.gradedUnits}/{result.totalUnits} units
            </span>
        </div>
    );
}

export function StudentChecklistSection({
    student,
    onPrintTranscript,
    onAddNonCreditedSubject,
    onSubjectClick,
    classificationBadgeVariant,
}: StudentChecklistSectionProps) {
    const overallGwa = useMemo(
        () => computeGwa(student.checklist.flatMap(collectYearSubjects)),
        [student.checklist],
    );

    const yearGwaMap = useMemo(() => {
        const map = new Map<number, GwaResult>();
        for (const yearGroup of student.checklist) {
            map.set(yearGroup.year, computeGwa(collectYearSubjects(yearGroup)));
        }
        return map;
    }, [student.checklist]);

    const semesterGwaMap = useMemo(() => {
        const map = new Map<string, GwaResult>();
        for (const yearGroup of student.checklist) {
            for (const semesterGroup of yearGroup.semesters) {
                map.set(`${yearGroup.year}-${semesterGroup.semester}`, computeGwa(semesterGroup.subjects));
            }
        }
        return map;
    }, [student.checklist]);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-lg font-bold">Checklist</h3>
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" className="gap-2" onClick={onPrintTranscript}>
                        <FileText className="h-4 w-4" />
                        Transcript of Records
                    </Button>
                    <Button type="button" variant="outline" className="gap-2" onClick={onAddNonCreditedSubject}>
                        <Plus className="h-4 w-4" />
                        Add Non-Credited Subject
                    </Button>
                </div>
            </div>

            <GwaSummary label="Overall GWA" result={overallGwa} />

            <Tabs defaultValue={String(student.checklist[0]?.year)} className="w-full">
                <TabsList className="flex h-auto w-full flex-wrap justify-start">
                    {student.checklist.map((yearGroup) => (
                        <TabsTrigger key={yearGroup.year} value={String(yearGroup.year)}>
                            {getYearLabel(yearGroup.year)}
                        </TabsTrigger>
                    ))}
                </TabsList>

                {student.checklist.map((yearGroup) => {
                    const yearResult = yearGwaMap.get(yearGroup.year);
                    return (
                    <TabsContent key={yearGroup.year} value={String(yearGroup.year)}>
                        <div className="space-y-6">
                            {yearResult && (
                                <GwaSummary label={`${getYearLabel(yearGroup.year)} GWA`} result={yearResult} />
                            )}
                            {yearGroup.semesters.map((semesterGroup) => {
                                const semesterResult = semesterGwaMap.get(`${yearGroup.year}-${semesterGroup.semester}`);
                                return (
                                <Card key={semesterGroup.semester}>
                                    <CardHeader className="bg-muted/50 py-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <CardTitle className="text-base">{getSemesterLabel(semesterGroup.semester)}</CardTitle>
                                            {semesterResult && (
                                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                                    <span className="flex items-baseline gap-1">
                                                        <span className="text-muted-foreground text-xs uppercase">GWA</span>
                                                        <span className={`font-mono text-sm font-bold ${gwaToneClass(semesterResult)}`}>
                                                            {formatGwa(semesterResult)}
                                                        </span>
                                                        {gradeScaleLabel(semesterResult.scale) && (
                                                            <span className="text-muted-foreground text-xs">
                                                                ({gradeScaleLabel(semesterResult.scale)})
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground text-xs">
                                                        {semesterResult.gradedCount}/{semesterResult.itemCount} subjects
                                                    </span>
                                                    <span className="text-muted-foreground text-xs">
                                                        {semesterResult.gradedUnits}/{semesterResult.totalUnits} units
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Code</TableHead>
                                                    <TableHead>Title</TableHead>
                                                    <TableHead className="text-right">Units</TableHead>
                                                    <TableHead>Classification</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead>Grade</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {semesterGroup.subjects.map((subject, subjectIndex) => {
                                                    const hasHistory = subject.history && subject.history.length > 1;

                                                    return (
                                                        <Fragment key={`${subject.id}-${subjectIndex}`}>
                                                            <TableRow
                                                                className="hover:bg-muted/50 cursor-pointer transition-colors"
                                                                onClick={() => onSubjectClick(subject)}
                                                            >
                                                                <TableCell className="font-medium">{subject.code}</TableCell>
                                                                <TableCell>{subject.title}</TableCell>
                                                                <TableCell className="text-right">{subject.units}</TableCell>
                                                                <TableCell>
                                                                    <ReuiBadge
                                                                        variant={classificationBadgeVariant(subject.classification)}
                                                                        size="sm"
                                                                        radius="full"
                                                                        className="capitalize"
                                                                    >
                                                                        {(subject.classification || "internal").replace("_", " ")}
                                                                    </ReuiBadge>
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Badge
                                                                        variant={
                                                                            subject.status === "Completed"
                                                                                ? "default"
                                                                                : subject.status === "In Progress"
                                                                                  ? "secondary"
                                                                                  : "outline"
                                                                        }
                                                                        className={
                                                                            subject.status === "Completed" ? "bg-green-600 hover:bg-green-700" : ""
                                                                        }
                                                                    >
                                                                        {subject.status === "Completed" ? "Passed" : subject.status}
                                                                    </Badge>
                                                                    {hasHistory && (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="text-muted-foreground ml-2 text-[10px] uppercase"
                                                                        >
                                                                            {subject.history.length - 1} Retakes
                                                                        </Badge>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {subject.grade && subject.grade !== "-" && (
                                                                        <span
                                                                            className={`font-mono font-bold ${
                                                                                Number(subject.grade) <= 3.0 || Number(subject.grade) >= 75
                                                                                    ? "text-green-600"
                                                                                    : "text-destructive"
                                                                            }`}
                                                                        >
                                                                            {subject.grade}
                                                                        </span>
                                                                    )}
                                                                </TableCell>
                                                            </TableRow>

                                                            {hasHistory &&
                                                                subject.history.map((history: ChecklistHistoryRecord, historyIndex) => {
                                                                    if (history.id === subject.enrollment_id) {
                                                                        return null;
                                                                    }

                                                                    const isPassed =
                                                                        history.grade &&
                                                                        history.grade !== "-" &&
                                                                        (Number(history.grade) <= 3.0 || Number(history.grade) >= 75);

                                                                    return (
                                                                        <TableRow
                                                                            key={`history-${subjectIndex}-${historyIndex}`}
                                                                            className="bg-muted/10 hover:bg-muted/30 cursor-pointer border-t-0"
                                                                            onClick={(event) => {
                                                                                event.stopPropagation();
                                                                                onSubjectClick(subject);
                                                                            }}
                                                                        >
                                                                            <TableCell className="text-muted-foreground flex items-center gap-2 pl-6">
                                                                                <div className="bg-border h-1.5 w-1.5 rounded-full" />
                                                                                {subject.code}
                                                                            </TableCell>
                                                                            <TableCell className="text-muted-foreground text-sm italic">
                                                                                <span className="bg-background mr-2 rounded border px-1.5 py-0.5 text-xs font-semibold">
                                                                                    Take {subject.history.length - historyIndex}
                                                                                </span>
                                                                                {subject.title} - SY {history.school_year} (Sem {history.semester})
                                                                            </TableCell>
                                                                            <TableCell className="text-muted-foreground text-right">
                                                                                {subject.units}
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                <ReuiBadge
                                                                                    variant={classificationBadgeVariant(history.classification)}
                                                                                    size="xs"
                                                                                    radius="full"
                                                                                    className="capitalize"
                                                                                >
                                                                                    {(history.classification || "internal").replace("_", " ")}
                                                                                </ReuiBadge>
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                <span className="text-muted-foreground text-xs underline decoration-dotted underline-offset-2">
                                                                                    {history.remarks || "No remarks"}
                                                                                </span>
                                                                            </TableCell>
                                                                            <TableCell>
                                                                                {history.grade && history.grade !== "-" ? (
                                                                                    <span
                                                                                        className={`font-mono text-sm ${
                                                                                            isPassed ? "text-green-600/70" : "text-destructive/70"
                                                                                        }`}
                                                                                    >
                                                                                        {history.grade}
                                                                                    </span>
                                                                                ) : (
                                                                                    <span className="text-muted-foreground">-</span>
                                                                                )}
                                                                            </TableCell>
                                                                        </TableRow>
                                                                    );
                                                                })}
                                                        </Fragment>
                                                    );
                                                })}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                                );
                            })}
                        </div>
                    </TabsContent>
                    );
                })}
            </Tabs>
        </div>
    );
}
