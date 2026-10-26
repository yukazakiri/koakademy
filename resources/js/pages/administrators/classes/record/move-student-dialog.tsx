import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { moveStudent } from "@/routes/administrators/classes";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";
import type { EnrollmentRow, TransferableClass } from "./types";

export function MoveStudentDialog({
    student,
    transferableClasses,
    currentClassId,
    onClose,
}: {
    student: EnrollmentRow;
    transferableClasses: TransferableClass[];
    currentClassId: number;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        student_id: student.student?.id ?? null,
        target_class_id: "",
        notify_student: true,
    });

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) onClose();
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move student to another section</DialogTitle>
                    <DialogDescription>
                        Transfer {student.student?.name || "this student"} to an available class section. The transfer runs in the background.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(moveStudent.url(currentClassId), {
                            onSuccess: () => {
                                toast.success("Student transfer queued successfully");
                                onClose();
                            },
                            onError: () => toast.error("The transfer could not be queued. Review the details and try again."),
                        });
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="transfer-target">Target class</Label>
                        <Select value={data.target_class_id} onValueChange={(value) => setData("target_class_id", value)}>
                            <SelectTrigger
                                id="transfer-target"
                                aria-invalid={Boolean(errors.target_class_id)}
                                aria-describedby={errors.target_class_id ? "transfer-target-error" : undefined}
                                className="w-full"
                            >
                                <SelectValue placeholder="Choose a section" />
                            </SelectTrigger>
                            <SelectContent>
                                {transferableClasses.length ? (
                                    transferableClasses.map((classItem) => (
                                        <SelectItem key={classItem.id} value={String(classItem.id)} disabled={classItem.is_full}>
                                            {classItem.label}
                                            {classItem.is_full ? " · Full" : ""}
                                        </SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="none" disabled>
                                        No other sections available
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        {errors.target_class_id && (
                            <p id="transfer-target-error" className="text-destructive text-sm">
                                {errors.target_class_id}
                            </p>
                        )}
                    </div>
                    {errors.student_id && (
                        <p role="alert" className="text-destructive text-sm">
                            {errors.student_id}
                        </p>
                    )}
                    <div className="flex items-center gap-3">
                        <input
                            id="transfer-notify"
                            type="checkbox"
                            checked={data.notify_student}
                            onChange={(event) => setData("notify_student", event.target.checked)}
                            className="accent-primary focus-visible:outline-ring size-4 focus-visible:outline-2"
                        />
                        <Label htmlFor="transfer-notify" className="text-sm font-normal">
                            Notify student by email
                        </Label>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" disabled={processing} onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !data.target_class_id || !data.student_id}>
                            {processing ? "Queuing transfer…" : "Move student"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
