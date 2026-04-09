import { useMemo } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Link } from "@inertiajs/react";

import { getTimetableColorTokens } from "@/components/dashboard/timetable-colors";

import type { WeeklyScheduleDay } from "@/components/dashboard/weekly-timetable";

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

interface SubjectMatrixProps {
    schedule: WeeklyScheduleDay[];
}

interface SubjectMatrixRow {
    key: string;
    subject_code: string;
    subject_title: string;
    section: string;
    entries: Record<string, WeeklyScheduleDay["entries"]>;
}

export function SubjectMatrix({ schedule }: SubjectMatrixProps) {
    const rows = useMemo<SubjectMatrixRow[]>(() => {
        const grouped = new Map<string, SubjectMatrixRow>();

        schedule.forEach((day) => {
            day.entries.forEach((entry) => {
                const key = `${entry.subject_code}-${entry.section}`;
                const existing = grouped.get(key);

                if (existing) {
                    existing.entries[day.day] = [...(existing.entries[day.day] ?? []), entry];
                } else {
                    grouped.set(key, {
                        key,
                        subject_code: entry.subject_code,
                        subject_title: entry.subject_title,
                        section: entry.section,
                        entries: {
                            [day.day]: [entry],
                        },
                    });
                }
            });
        });

        return Array.from(grouped.values()).sort((a, b) => a.subject_code.localeCompare(b.subject_code));
    }, [schedule]);

    if (rows.length === 0) {
        return (
            <div className="text-muted-foreground flex flex-col items-center justify-center py-12 text-center">
                <p>No subjects found in your schedule.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="space-y-3 sm:hidden">
                {rows.map((row) => (
                    <div key={row.key} className="bg-card rounded-xl border p-4">
                        <div className="space-y-1">
                            <div className="leading-tight font-semibold">{row.subject_title}</div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline" className="text-[10px]">
                                    {row.subject_code}
                                </Badge>
                                <span className="text-muted-foreground text-xs">Sec {row.section}</span>
                            </div>
                        </div>

                        <div className="mt-3 space-y-3">
                            {DAYS.map((day) => {
                                const entries = row.entries[day] ?? [];
                                if (!entries.length) {
                                    return null;
                                }

                                return (
                                    <div key={`${row.key}-${day}`} className="space-y-2">
                                        <div className="text-muted-foreground text-xs font-medium">{day}</div>
                                        <div className="space-y-2">
                                            {entries.map((entry) => (
                                                <MatrixCard key={`${day}-${entry.id}`} entry={entry} />
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>

            <div className="hidden h-[560px] w-full touch-pan-x overflow-auto rounded-md border sm:block">
                <div className="min-w-[860px]">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="bg-muted/50 sticky top-0 left-0 z-30 w-[240px] backdrop-blur">Subject</TableHead>
                                {DAYS.map((day) => (
                                    <TableHead key={day} className="bg-muted/50 sticky top-0 z-20 min-w-[140px] text-center backdrop-blur">
                                        {day}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.key}>
                                    <TableCell className="bg-background/80 sticky left-0 z-10 font-medium backdrop-blur">
                                        <div className="flex flex-col gap-1">
                                            <span className="leading-tight">{row.subject_title}</span>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="text-[10px]">
                                                    {row.subject_code}
                                                </Badge>
                                                <span className="text-muted-foreground text-xs">Sec {row.section}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    {DAYS.map((day) => (
                                        <TableCell key={day} className="p-2 align-top">
                                            <CellEntries entries={row.entries[day] ?? []} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    );
}

interface CellEntriesProps {
    entries: WeeklyScheduleDay["entries"];
}

function CellEntries({ entries }: CellEntriesProps) {
    if (!entries.length) {
        return <div className="text-muted-foreground/20 flex h-full items-center justify-center text-xl">·</div>;
    }

    return (
        <div className="flex flex-col gap-2">
            {entries.map((entry) => (
                <MatrixCard key={entry.id} entry={entry} />
            ))}
        </div>
    );
}

function MatrixCard({ entry }: { entry: WeeklyScheduleDay["entries"][number] }) {
    const colors = getTimetableColorTokens(`${entry.subject_code}-${entry.section}-${entry.room}`);

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="focus-visible:ring-ring w-full rounded-md border px-2 py-1.5 text-left text-xs shadow-sm transition-all hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
                    style={{
                        backgroundColor: colors.fill,
                        borderColor: colors.border,
                        color: "hsl(var(--foreground))",
                    }}
                >
                    <div className="text-foreground font-medium">
                        {entry.start_time} – {entry.end_time}
                    </div>
                    <div className="text-muted-foreground mt-0.5 truncate text-[10px]">{entry.room}</div>
                </button>
            </PopoverTrigger>

            <PopoverContent side="right" align="start" className="w-80 overflow-hidden p-0">
                <div className="border-b px-3 py-2" style={{ backgroundColor: colors.fill }}>
                    <div className="text-sm leading-tight font-semibold">{entry.subject_title}</div>
                    <div className="text-muted-foreground mt-0.5 text-xs">
                        {entry.subject_code} • Sec {entry.section}
                    </div>
                </div>

                <div className="space-y-2 p-3 text-xs">
                    <div className="text-muted-foreground text-xs">
                        {entry.start_time} – {entry.end_time}
                    </div>
                    <div className="text-muted-foreground text-xs">Room: {entry.room}</div>

                    <Separator />

                    <div className="flex gap-2">
                        <Button asChild size="sm" className="flex-1">
                            <Link href={entry.class_id ? `/faculty/classes/${entry.class_id}` : "/faculty/classes"}>Open class</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href="/faculty/classes">All</Link>
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
