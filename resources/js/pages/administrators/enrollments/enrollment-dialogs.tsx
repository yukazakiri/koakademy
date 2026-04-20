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
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Activity, AlertTriangle, Download, FileText, Loader2, Printer, RotateCcw, X } from "lucide-react";
import type { Dispatch, RefObject, SetStateAction } from "react";
import type { EnrollmentRow } from "./columns";
import { ReportContent } from "./report-content";
import type { ApplicantRow, BulkReportFilters } from "./types";

type BulkReportsDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    filters: BulkReportFilters;
    onFiltersChange: Dispatch<SetStateAction<BulkReportFilters>>;
    isGenerating: boolean;
    onGenerate: () => void;
};

export function BulkReportsDialog({ open, onOpenChange, filters, onFiltersChange, isGenerating, onGenerate }: BulkReportsDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Download className="h-5 w-5" />
                        Bulk Export Assessments
                    </DialogTitle>
                    <DialogDescription>
                        Generate a combined PDF containing all student assessments for the current semester. This will be processed in the background
                        and you'll receive a notification when ready.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    {/* Course Filter */}
                    <div className="space-y-2">
                        <Label htmlFor="course-filter">Filter by Course</Label>
                        <Select
                            value={filters.course_filter}
                            onValueChange={(value) => onFiltersChange((prev) => ({ ...prev, course_filter: value }))}
                        >
                            <SelectTrigger id="course-filter">
                                <SelectValue placeholder="Select course" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Courses</SelectItem>
                                <SelectItem value="BSIT">BSIT</SelectItem>
                                <SelectItem value="BSCS">BSCS</SelectItem>
                                <SelectItem value="BSIS">BSIS</SelectItem>
                                <SelectItem value="BSHM">BSHM</SelectItem>
                                <SelectItem value="BSTM">BSTM</SelectItem>
                                <SelectItem value="BSBA">BSBA</SelectItem>
                                <SelectItem value="BSED">BSED</SelectItem>
                                <SelectItem value="BEED">BEED</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Year Level Filter */}
                    <div className="space-y-2">
                        <Label htmlFor="year-level-filter">Filter by Year Level</Label>
                        <Select
                            value={filters.year_level_filter}
                            onValueChange={(value) => onFiltersChange((prev) => ({ ...prev, year_level_filter: value }))}
                        >
                            <SelectTrigger id="year-level-filter">
                                <SelectValue placeholder="Select year level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Year Levels</SelectItem>
                                <SelectItem value="1">1st Year</SelectItem>
                                <SelectItem value="2">2nd Year</SelectItem>
                                <SelectItem value="3">3rd Year</SelectItem>
                                <SelectItem value="4">4th Year</SelectItem>
                                <SelectItem value="5">5th Year</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Student Limit */}
                    <div className="space-y-2">
                        <Label htmlFor="student-limit">Maximum Students</Label>
                        <Select
                            value={filters.student_limit}
                            onValueChange={(value) => onFiltersChange((prev) => ({ ...prev, student_limit: value }))}
                        >
                            <SelectTrigger id="student-limit">
                                <SelectValue placeholder="Select limit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Students</SelectItem>
                                <SelectItem value="10">10 Students</SelectItem>
                                <SelectItem value="25">25 Students</SelectItem>
                                <SelectItem value="50">50 Students</SelectItem>
                                <SelectItem value="100">100 Students</SelectItem>
                                <SelectItem value="250">250 Students</SelectItem>
                                <SelectItem value="500">500 Students</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Include Deleted */}
                    <div className="flex items-center gap-3 rounded-lg border p-3">
                        <input
                            type="checkbox"
                            id="include-deleted"
                            checked={filters.include_deleted}
                            onChange={(e) => onFiltersChange((prev) => ({ ...prev, include_deleted: e.target.checked }))}
                            className="h-4 w-4 rounded border-gray-300"
                        />
                        <div>
                            <Label htmlFor="include-deleted" className="cursor-pointer">
                                Include deleted enrollments
                            </Label>
                            <p className="text-muted-foreground text-xs">Also include soft-deleted enrollment records</p>
                        </div>
                    </div>

                    {/* Info Box */}
                    <div className="rounded-lg border bg-blue-50 p-3 text-sm text-blue-800 dark:bg-blue-950/50 dark:text-blue-400">
                        <p className="flex items-center gap-2 font-medium">
                            <Activity className="h-4 w-4" />
                            Processing Information
                        </p>
                        <p className="mt-1 text-xs">
                            Only verified (enrolled) students will be included. The PDF will be generated in the background and sorted alphabetically
                            by last name. You'll receive a notification with a download link when complete.
                        </p>
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={isGenerating}>
                        Cancel
                    </Button>
                    <Button onClick={onGenerate} disabled={isGenerating}>
                        {isGenerating ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Queuing...
                            </>
                        ) : (
                            <>
                                <Download className="mr-2 h-4 w-4" />
                                Generate Report
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type ReportPreviewDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClose: () => void;
    onPrint: () => void;
    reportData: Record<string, unknown> | null;
    reportPrintRef: RefObject<HTMLDivElement>;
};

export function ReportPreviewDialog({ open, onOpenChange, onClose, onPrint, reportData, reportPrintRef }: ReportPreviewDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent showCloseButton={false} className="flex h-[95vh] w-[96vw] max-w-[96vw] flex-col overflow-hidden p-0 sm:max-w-[96vw] lg:max-w-[1280px]">
                <div className="flex items-start justify-between border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
                    <div>
                        <DialogTitle className="flex items-center gap-2 text-base font-semibold tracking-tight text-slate-900">
                            <FileText className="h-4 w-4" />
                            Report Preview
                        </DialogTitle>
                        <DialogDescription className="mt-1 text-xs text-slate-500">
                            Landscape view for easier review. Use Print for final export.
                        </DialogDescription>
                    </div>
                    <div className="flex items-center gap-2 pt-0.5">
                        <Button variant="outline" size="sm" onClick={onPrint} className="border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                            <Printer className="mr-2 h-4 w-4" />
                            Print
                        </Button>
                        <Button variant="ghost" size="sm" onClick={onClose} className="text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                            <X className="mr-1 h-4 w-4" />
                            Close
                        </Button>
                    </div>
                </div>
                <div className="flex-1 overflow-auto bg-slate-100/70 px-3 py-4 sm:px-6 sm:py-6">
                    <div className="mx-auto w-full max-w-[1200px] overflow-x-auto">
                        <div
                            className="mx-auto min-h-[210mm] min-w-[980px] max-w-[297mm] rounded-md border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:p-10"
                            ref={reportPrintRef}
                        >
                            {reportData && <ReportContent data={reportData} />}
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

type ManageApplicantDialogProps = {
    open: boolean;
    applicant: ApplicantRow | null;
    scholarshipType: string;
    onScholarshipChange: Dispatch<SetStateAction<string>>;
    onOpenChange: (open: boolean) => void;
    onSave: () => void;
    isUpdating: boolean;
};

export function ManageApplicantDialog({
    open,
    applicant,
    scholarshipType,
    onScholarshipChange,
    onOpenChange,
    onSave,
    isUpdating,
}: ManageApplicantDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Manage Applicant</DialogTitle>
                    <DialogDescription>
                        Update scholarship configuration for <strong>{applicant?.name}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-6 py-4">
                    <div className="space-y-3">
                        <Label htmlFor="scholarship" className="text-sm font-medium">
                            Scholarship Status
                        </Label>
                        <Select value={scholarshipType} onValueChange={onScholarshipChange}>
                            <SelectTrigger id="scholarship" className="w-full">
                                <SelectValue placeholder="Select scholarship type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-slate-300" />
                                        No Scholarship
                                    </div>
                                </SelectItem>
                                <SelectItem value="tes">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                                        TES (Tertiary Education Subsidy)
                                    </div>
                                </SelectItem>
                                <SelectItem value="tdp">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-blue-500" />
                                        TDP (Tulong Dunong Program)
                                    </div>
                                </SelectItem>
                                <SelectItem value="institutional">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-purple-500" />
                                        Institutional
                                    </div>
                                </SelectItem>
                                <SelectItem value="private">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full bg-orange-500" />
                                        Private
                                    </div>
                                </SelectItem>
                                <SelectItem value="other">Other</SelectItem>
                            </SelectContent>
                        </Select>
                        <div className="bg-muted/50 text-muted-foreground rounded-lg border p-3 text-xs">
                            <p className="text-foreground font-medium">Note:</p>
                            Setting this will flag the student as a scholar in the system. Official verification should be done before confirming
                            enrollment.
                        </div>
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:justify-end">
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={onSave} disabled={isUpdating}>
                        {isUpdating ? "Saving Changes..." : "Save Changes"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type DeleteApplicantDialogProps = {
    open: boolean;
    applicant: ApplicantRow | null;
    isDeleting: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

export function DeleteApplicantDialog({ open, applicant, isDeleting, onOpenChange, onConfirm }: DeleteApplicantDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Applicant</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete <strong>{applicant?.name}</strong>? This action will soft-delete the record. You can restore
                        it later if needed.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isDeleting} className="bg-red-600 text-white hover:bg-red-700">
                        {isDeleting ? "Deleting..." : "Delete"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

type ForceDeleteApplicantDialogProps = {
    open: boolean;
    applicant: ApplicantRow | null;
    isDeleting: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

export function ForceDeleteApplicantDialog({ open, applicant, isDeleting, onOpenChange, onConfirm }: ForceDeleteApplicantDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2 text-red-600">
                        <AlertTriangle className="h-5 w-5" />
                        Permanently Delete Applicant
                    </AlertDialogTitle>
                    <AlertDialogDescription className="space-y-3">
                        <p>
                            Are you sure you want to <strong>permanently delete</strong> <strong>{applicant?.name}</strong>?
                        </p>
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/50 dark:text-red-400">
                            <p className="font-semibold">Warning: This action cannot be undone!</p>
                            <p className="mt-1">
                                All related data including enrollments, subject records, and clearances will be permanently removed from the system.
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isDeleting} className="bg-red-600 text-white hover:bg-red-700">
                        {isDeleting ? "Deleting..." : "Permanently Delete"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

type DeleteEnrollmentDialogProps = {
    open: boolean;
    enrollment: EnrollmentRow | null;
    isDeleting: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

export function DeleteEnrollmentDialog({ open, enrollment, isDeleting, onOpenChange, onConfirm }: DeleteEnrollmentDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Enrollment</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete the enrollment for <strong>{enrollment?.student_name}</strong>? This action will soft-delete
                        the record. You can restore it later if needed.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isDeleting} className="bg-red-600 text-white hover:bg-red-700">
                        {isDeleting ? "Deleting..." : "Delete"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

type ForceDeleteEnrollmentDialogProps = {
    open: boolean;
    enrollment: EnrollmentRow | null;
    isDeleting: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

export function ForceDeleteEnrollmentDialog({ open, enrollment, isDeleting, onOpenChange, onConfirm }: ForceDeleteEnrollmentDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2 text-red-600">
                        <AlertTriangle className="h-5 w-5" />
                        Permanently Delete Enrollment
                    </AlertDialogTitle>
                    <AlertDialogDescription className="space-y-3">
                        <p>
                            Are you sure you want to <strong>permanently delete</strong> the enrollment for{" "}
                            <strong>{enrollment?.student_name}</strong>?
                        </p>
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/50 dark:text-red-400">
                            <p className="font-semibold">Warning: This action cannot be undone!</p>
                            <p className="mt-1">
                                All related data including subject enrollments, additional fees, tuition records, and transactions will be permanently
                                removed from the system.
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isDeleting} className="bg-red-600 text-white hover:bg-red-700">
                        {isDeleting ? "Deleting..." : "Permanently Delete"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

type RestoreEnrollmentDialogProps = {
    open: boolean;
    enrollment: EnrollmentRow | null;
    isDeleting: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

export function RestoreEnrollmentDialog({ open, enrollment, isDeleting, onOpenChange, onConfirm }: RestoreEnrollmentDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2 text-emerald-600">
                        <RotateCcw className="h-5 w-5" />
                        Restore Enrollment
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to restore the enrollment for <strong>{enrollment?.student_name}</strong>? This will make the enrollment
                        active again in the system.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm} disabled={isDeleting} className="bg-emerald-600 text-white hover:bg-emerald-700">
                        {isDeleting ? "Restoring..." : "Restore"}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
