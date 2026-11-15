import { publish, retryNotifications, clearance as updateClearance } from "@/actions/App/Http/Controllers/AdministratorGradeReleaseController";
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { toast } from "sonner";
import type { GradeReleasePeriod, StudentDetail } from "../types";

export function GradeReleaseSection({ student }: { student: StudentDetail }) {
    const [editing, setEditing] = useState<GradeReleasePeriod | null>(null);
    const [publishing, setPublishing] = useState<GradeReleasePeriod | null>(null);
    const clearanceForm = useForm({ school_year: "", semester: 1, is_cleared: false, remarks: "" });
    const publishForm = useForm<{ school_year: string; semester: number; correction_reason: string; period?: string }>({
        school_year: "",
        semester: 1,
        correction_reason: "",
    });
    const retryForm = useForm({ school_year: "", semester: 1 });

    if (!student.can_view_grade_release) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Grade release and clearance</CardTitle>
                    <CardDescription>You do not have permission to view grade-release clearance.</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const edit = (period: GradeReleasePeriod) => {
        clearanceForm.setData({
            school_year: period.school_year,
            semester: period.semester,
            is_cleared: period.clearance?.is_cleared ?? false,
            remarks: period.clearance?.remarks ?? "",
        });
        clearanceForm.clearErrors();
        setEditing(period);
    };

    const confirmPublish = (period: GradeReleasePeriod) => {
        publishForm.setData({ school_year: period.school_year, semester: period.semester, correction_reason: "" });
        publishForm.clearErrors();
        setPublishing(period);
    };

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Grade release and clearance</CardTitle>
                    <CardDescription>
                        Current and future semester clearance is {student.grade_release_clearance_required ? "required" : "not required"} to publish
                        final grades. Previous semesters do not require it.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {student.grade_release_periods.length === 0 && <p className="text-muted-foreground text-sm">No semesters have been recorded.</p>}
                    {student.grade_release_periods.map((period) => {
                        const blocked = period.clearance_required && !period.clearance?.is_cleared;
                        const canPublish = period.total > 0 && period.missing === 0 && !blocked && (!period.published_at || period.has_changes);
                        return (
                            <div
                                key={`${period.school_year}-${period.semester}`}
                                className="flex flex-col gap-3 border-b pb-4 last:border-0 last:pb-0"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {period.school_year} · Semester {period.semester}
                                    </span>
                                    {period.is_current && <Badge variant="outline">Current</Badge>}
                                    <Badge variant={period.clearance?.is_cleared ? "default" : "secondary"}>
                                        {period.clearance ? (period.clearance.is_cleared ? "Cleared" : "Not cleared") : "No clearance record"}
                                    </Badge>
                                    <Badge variant={period.published_at && !period.has_changes ? "default" : "secondary"}>
                                        {period.published_at ? (period.has_changes ? "Unpublished changes" : "Published") : "Draft"}
                                    </Badge>
                                </div>
                                <div className="text-muted-foreground flex flex-col gap-1 text-sm">
                                    <span>
                                        {period.total - period.missing}/{period.total} subject and class records have a final result.{" "}
                                        {period.total > 0 && "Records are counted separately where both exist."}
                                    </span>
                                    {period.missing_items.length > 0 && <span>Missing results: {period.missing_items.join(", ")}</span>}
                                    {period.clearance?.cleared_by && (
                                        <span>
                                            Cleared by {period.clearance.cleared_by}
                                            {period.clearance.cleared_at ? ` on ${new Date(period.clearance.cleared_at).toLocaleString()}` : ""}
                                        </span>
                                    )}
                                    {period.clearance?.remarks && <span>Remarks: {period.clearance.remarks}</span>}
                                    {period.published_at && (
                                        <span>
                                            Last published {new Date(period.published_at).toLocaleString()} · Revision {period.revision}
                                        </span>
                                    )}
                                    {period.notification_status && (
                                        <span>
                                            Notification: email {period.notification_status.mail}, in-app {period.notification_status.database}.
                                        </span>
                                    )}
                                    {blocked && <span>Clearance is required before publishing this semester.</span>}
                                    {period.notification_status?.warnings?.map((warning) => (
                                        <span key={warning}>{warning}</span>
                                    ))}
                                </div>
                                {period.events.length > 0 && (
                                    <details className="text-sm">
                                        <summary className="cursor-pointer font-medium">Recent activity</summary>
                                        <ul className="text-muted-foreground mt-2 flex flex-col gap-1">
                                            {period.events.map((event) => (
                                                <li key={event.id}>
                                                    {event.event === "published"
                                                        ? `Grades published (revision ${event.revision})`
                                                        : event.event === "clearance_granted"
                                                          ? "Clearance granted"
                                                          : "Clearance revoked"}
                                                    {event.actor ? ` by ${event.actor}` : ""}
                                                    {event.at ? ` · ${new Date(event.at).toLocaleString()}` : ""}
                                                    {event.reason ? ` — ${event.reason}` : ""}
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {student.can_manage_grade_clearance && (
                                        <Button type="button" variant="outline" size="sm" onClick={() => edit(period)}>
                                            Manage clearance
                                        </Button>
                                    )}
                                    {student.can_publish_grades && (
                                        <Button type="button" size="sm" disabled={!canPublish} onClick={() => confirmPublish(period)}>
                                            {period.published_at ? "Publish changes" : "Publish grades"}
                                        </Button>
                                    )}
                                    {student.can_publish_grades &&
                                        period.published_at &&
                                        period.notification_status &&
                                        [period.notification_status.mail, period.notification_status.database].some(
                                            (status) => status === "failed" || status === "unavailable",
                                        ) && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={retryForm.processing}
                                                onClick={() => {
                                                    retryForm.transform(() => ({ school_year: period.school_year, semester: period.semester }));
                                                    retryForm.post(retryNotifications.url(student.id), {
                                                        preserveScroll: true,
                                                        onSuccess: () => toast.success("Notification retry processed."),
                                                        onError: () => toast.error("Notification retry failed."),
                                                    });
                                                }}
                                            >
                                                Retry notification
                                            </Button>
                                        )}
                                </div>
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Manage grade-release clearance</DialogTitle>
                        <DialogDescription>
                            {editing?.school_year} · Semester {editing?.semester}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            clearanceForm.post(updateClearance.url(student.id), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setEditing(null);
                                    toast.success("Grade-release clearance updated.");
                                },
                                onError: () => toast.error("Clearance could not be updated."),
                            });
                        }}
                    >
                        <div className="flex items-center gap-3">
                            <Switch
                                id="grade-cleared"
                                checked={clearanceForm.data.is_cleared}
                                onCheckedChange={(value) => clearanceForm.setData("is_cleared", value)}
                            />
                            <Label htmlFor="grade-cleared">Student is cleared</Label>
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="grade-clearance-remarks">Remarks</Label>
                            <Textarea
                                id="grade-clearance-remarks"
                                value={clearanceForm.data.remarks}
                                onChange={(event) => clearanceForm.setData("remarks", event.target.value)}
                            />
                            {clearanceForm.errors.remarks && <p className="text-destructive text-sm">{clearanceForm.errors.remarks}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditing(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={clearanceForm.processing}>
                                Save clearance
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={publishing !== null} onOpenChange={(open) => !open && setPublishing(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Publish these grades?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Grades for {publishing?.school_year}, semester {publishing?.semester}, will become visible to the student. A notification
                            will be sent through supported configured channels.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {(publishing?.needs_correction_reason || Boolean(publishForm.errors.correction_reason)) && (
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="grade-correction-reason">Reason for correction</Label>
                            <Textarea
                                id="grade-correction-reason"
                                value={publishForm.data.correction_reason}
                                onChange={(event) => publishForm.setData("correction_reason", event.target.value)}
                            />
                            {publishForm.errors.correction_reason && (
                                <p className="text-destructive text-sm" role="alert">
                                    {publishForm.errors.correction_reason}
                                </p>
                            )}
                        </div>
                    )}
                    {publishForm.errors.period && <p className="text-destructive text-sm">{publishForm.errors.period}</p>}
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={publishForm.processing}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={publishForm.processing}
                            onClick={(event) => {
                                event.preventDefault();
                                publishForm.post(publish.url(student.id), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setPublishing(null);
                                        toast.success("Grades published.");
                                    },
                                    onError: () => toast.error("Grades could not be published."),
                                });
                            }}
                        >
                            Publish grades
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
