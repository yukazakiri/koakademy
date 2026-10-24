import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { CalendarRange, CheckCircle2, FileDown, Plus, RefreshCw, Sparkles } from "lucide-react";

type TermContext = {
    school_year: string;
    semester: string;
    semester_label: string;
    available_school_years: string[];
    available_semesters: Array<{ value: string; label: string }>;
};

type SchedulingHeaderProps = {
    termContext?: TermContext;
    onTermChange?: (schoolYear: string, semester: string) => void;
    onGenerateCohort?: () => void;
    onCreateClass: () => void;
    onPublishTerm?: () => void;
    draftCount?: number;
    conflictCount?: number;
    isPublishing?: boolean;
    onExport: () => void;
    onSync: () => void;
};

export default function SchedulingHeader({
    termContext,
    onTermChange,
    onGenerateCohort,
    onCreateClass,
    onPublishTerm,
    draftCount = 0,
    conflictCount = 0,
    isPublishing = false,
    onExport,
    onSync,
}: SchedulingHeaderProps) {
    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2.5">
                    <h1 className="text-2xl font-bold tracking-tight">Schedule Planner</h1>
                    {termContext && (
                        <Badge variant="outline" className="gap-1.5 border-primary/30 bg-primary/5 px-2.5 py-0.5 text-xs font-medium text-primary">
                            <CalendarRange className="h-3.5 w-3.5" />
                            AY {termContext.school_year} · {termContext.semester_label}
                        </Badge>
                    )}
                    {draftCount > 0 ? (
                        <Badge variant="secondary" className="bg-amber-500/15 text-amber-700 dark:text-amber-300 text-xs">
                            {draftCount} Draft Block{draftCount === 1 ? "" : "s"}
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="gap-1 border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs">
                            <CheckCircle2 className="h-3 w-3" /> Published
                        </Badge>
                    )}
                </div>
                <p className="text-muted-foreground text-sm">
                    Generate cohort offerings from curriculum, place recurring blocks by section, and resolve room or faculty conflicts.
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {termContext && onTermChange && (
                    <div className="bg-muted/40 flex items-center gap-1.5 rounded-lg border p-1">
                        <Select
                            value={termContext.school_year}
                            onValueChange={(sy) => onTermChange(sy, termContext.semester)}
                        >
                            <SelectTrigger className="h-8 w-[140px] border-0 bg-transparent text-xs font-medium shadow-none">
                                <SelectValue placeholder="School Year" />
                            </SelectTrigger>
                            <SelectContent>
                                {termContext.available_school_years.map((sy) => (
                                    <SelectItem key={sy} value={sy}>
                                        AY {sy}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={termContext.semester}
                            onValueChange={(sem) => onTermChange(termContext.school_year, sem)}
                        >
                            <SelectTrigger className="h-8 w-[135px] border-0 bg-transparent text-xs font-medium shadow-none">
                                <SelectValue placeholder="Semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {termContext.available_semesters.map((sem) => (
                                    <SelectItem key={sem.value} value={sem.value}>
                                        {sem.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {onGenerateCohort && (
                    <Button variant="secondary" size="sm" onClick={onGenerateCohort}>
                        <Sparkles className="mr-1.5 h-3.5 w-3.5" /> Generate Cohort
                    </Button>
                )}

                <Button variant="default" size="sm" onClick={onCreateClass}>
                    <Plus className="mr-1.5 h-3.5 w-3.5" /> Create Class
                </Button>

                {onPublishTerm && draftCount > 0 && (
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={isPublishing || conflictCount > 0}
                        onClick={onPublishTerm}
                        className="border-emerald-500/40 text-emerald-700 hover:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <CheckCircle2 className="mr-1.5 h-3.5 w-3.5" />
                        {isPublishing ? "Publishing..." : `Publish (${draftCount})`}
                    </Button>
                )}

                <Button variant="outline" size="sm" onClick={onExport}>
                    <FileDown className="mr-1.5 h-3.5 w-3.5" /> Export PDF
                </Button>

                <Button variant="outline" size="sm" onClick={onSync}>
                    <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Refresh
                </Button>
            </div>
        </div>
    );
}
