import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    BookOpen,
    ChevronLeft,
    ChevronRight,
    ClipboardPlus,
    ExternalLink,
    Loader2,
    Pencil,
    Save,
} from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { getInitials, getStatusColor, type Student } from "../columns";
import { StudentPortalAccountCard, type StudentPortalAccountSummary } from "./student-portal-account-card";

interface StudentInspectorDrawerProps {
    student: (Student & { email?: string | null; phone?: string | null; portal_account?: StudentPortalAccountSummary | null }) | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    statusOptions: Array<{ value: string; label: string }>;
    onPrev?: () => void;
    onNext?: () => void;
    hasPrev?: boolean;
    hasNext?: boolean;
}

export function StudentInspectorDrawer({
    student,
    open,
    onOpenChange,
    statusOptions,
    onPrev,
    onNext,
    hasPrev = false,
    hasNext = false,
}: StudentInspectorDrawerProps) {
    const [status, setStatus] = useState(student?.status ?? "enrolled");
    const [yearLevel, setYearLevel] = useState(String(student?.year_level ?? 1));
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!student) return;
        setStatus(student.status ?? "enrolled");
        setYearLevel(String(student.year_level ?? 1));
    }, [student]);

    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            if (target && ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName)) return;
            if (event.key === "[" && hasPrev) {
                event.preventDefault();
                onPrev?.();
            } else if (event.key === "]" && hasNext) {
                event.preventDefault();
                onNext?.();
            }
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [open, hasPrev, hasNext, onPrev, onNext]);

    if (!student) return null;

    const isDirty = status !== (student.status ?? "enrolled") || Number(yearLevel) !== Number(student.year_level ?? 1);

    const handleQuickSave = async () => {
        if (!isDirty || saving) return;
        setSaving(true);
        try {
            await axios.patch(
                `/administrators/students/${student.id}/quick-update`,
                {
                    status,
                    academic_year: Number(yearLevel),
                },
                { headers: { Accept: "application/json" } },
            );
            toast.success(`Updated ${student.name}`);
            router.reload();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to quick-update student.");
        } finally {
            setSaving(false);
        }
    };

    const isShs = student.type === "shs";
    const yearChoices = isShs
        ? [
              { value: "11", label: "Grade 11" },
              { value: "12", label: "Grade 12" },
          ]
        : [
              { value: "1", label: "1st Year" },
              { value: "2", label: "2nd Year" },
              { value: "3", label: "3rd Year" },
              { value: "4", label: "4th Year" },
              { value: "5", label: "5th Year" },
          ];

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-[520px]">
                <SheetHeader className="border-border/70 bg-muted/20 border-b px-5 py-4">
                    <div className="flex items-start justify-between gap-3 pr-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <Avatar className="border-border size-11 border">
                                <AvatarImage src={student.avatar_url ?? undefined} alt={student.name} />
                                <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                                    {getInitials(student.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <SheetTitle className="truncate text-base font-semibold">{student.name}</SheetTitle>
                                <SheetDescription className="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span className="font-mono font-medium tabular-nums">{student.student_id || `#${student.id}`}</span>
                                    <span>·</span>
                                    <span className="uppercase">{student.type || "Student"}</span>
                                </SheetDescription>
                            </div>
                        </div>

                        <div className="flex shrink-0 items-center gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-7"
                                disabled={!hasPrev}
                                onClick={onPrev}
                                title="Previous student ([)"
                                aria-label="Previous student"
                            >
                                <ChevronLeft className="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-7"
                                disabled={!hasNext}
                                onClick={onNext}
                                title="Next student (])"
                                aria-label="Next student"
                            >
                                <ChevronRight className="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    {/* Program & Clearance Summary */}
                    <div className="grid grid-cols-2 gap-2.5">
                        <div className="border-border/70 bg-card rounded-xl border p-3">
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Program</p>
                            <p className="text-foreground mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                <BookOpen className="text-primary size-3.5 shrink-0" />
                                <span className="truncate">{student.course || "Unassigned"}</span>
                            </p>
                            {student.course_title && <p className="text-muted-foreground mt-0.5 truncate text-[11px]">{student.course_title}</p>}
                        </div>
                        <div className="border-border/70 bg-card rounded-xl border p-3">
                            <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Status &amp; Clearance</p>
                            <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline" className={`text-[11px] capitalize ${getStatusColor(student.status)}`}>
                                    {(student.status ?? "unknown").replace("_", " ")}
                                </Badge>
                                <Badge variant="secondary" className="text-[10px] capitalize">
                                    {student.previous_sem_clearance.replace("_", " ")}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    {/* Inline Quick-Edit */}
                    <div className="border-border/70 bg-card space-y-3 rounded-xl border p-4">
                        <div className="flex items-center justify-between">
                            <p className="text-foreground text-xs font-semibold">Inline Quick Edit</p>
                            <span className="text-muted-foreground font-mono text-[10px]">[ / ] cycle rows</span>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="inspector-status" className="text-xs">
                                    Status
                                </Label>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger id="inspector-status" className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statusOptions.map((opt) => (
                                            <SelectItem key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="inspector-year" className="text-xs">
                                    Year Level
                                </Label>
                                <Select value={yearLevel} onValueChange={setYearLevel}>
                                    <SelectTrigger id="inspector-year" className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearChoices.map((choice) => (
                                            <SelectItem key={choice.value} value={choice.value}>
                                                {choice.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                size="sm"
                                disabled={!isDirty || saving}
                                onClick={() => void handleQuickSave()}
                                className="h-8 gap-1.5 text-xs active:scale-[0.97]"
                            >
                                {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                Save Quick Edit
                            </Button>
                        </div>
                    </div>

                    {/* Portal Account Provisioning */}
                    <StudentPortalAccountCard
                        key={student.id}
                        studentId={student.id}
                        studentEmail={student.email}
                        portalAccount={student.portal_account}
                        compact
                        onUpdated={() => router.reload()}
                    />

                    <Separator />

                    {/* Direct Workflow Actions */}
                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            render={<Link href={`/administrators/students/${student.id}`} />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <ExternalLink className="size-3.5" />
                            Full Profile
                        </Button>
                        <Button
                            render={<Link href={`/administrators/students/${student.id}/edit`} />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <Pencil className="size-3.5" />
                            Full Edit
                        </Button>
                        <Button
                            render={<Link href={`/administrators/enrollments/create?student_id=${student.id}`} />}
                            nativeButton={false}
                            size="sm"
                            className="col-span-2 h-9 gap-1.5 text-xs"
                        >
                            <ClipboardPlus className="size-3.5" />
                            Create Enrollment for{" "}
                            {(student.name.includes(",")
                                ? student.name.split(",")[1]?.trim().split(/\s+/)[0]
                                : student.name.split(/\s+/)[0]) || student.name}
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
