import AdminLayout from "@/components/administrators/admin-layout";
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
import { buttonVariants } from "@/components/ui/button";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { FileText, Trash2 } from "lucide-react";
import React, { useEffect, useState } from "react";
import { toast } from "sonner";

import { AcademicScheduleDashboard } from "./components/academic-schedule-dashboard";
import { PrintScheduleDialog } from "./components/print-schedule-dialog";
import { StudentActionMenu } from "./components/student-action-menu";
import { StudentChecklistSection } from "./components/student-checklist-section";
import { StudentDeleteDialogs } from "./components/student-delete-dialogs";
import { StudentDetailsCard } from "./components/student-details-card";
import { StudentSidebar } from "./components/student-sidebar";
import { StudentTabs } from "./components/student-tabs";
import { SubjectEnrollmentDialog } from "./components/subject-enrollment-dialog";
import type { ChecklistHistoryRecord, ChecklistSubject, ChecklistYearGroup } from "./types";

import {
    ChangeCourseDialog,
    ManageClearanceDialog,
    RetryEnrollmentDialog,
    UndoIdDialog,
    UpdateIdDialog,
    UpdateStatusDialog,
    UpdateTuitionDialog,
} from "./components/student-action-dialogs";
import type { Branding, PrintOption, StudentShowProps, SubjectEnrollmentFormData } from "./types";

declare let route: any;

function classificationBadgeVariant(classification: string | null | undefined): "info-light" | "success-light" | "warning-light" {
    if (classification === "credited") {
        return "success-light";
    }

    if (classification === "non_credited") {
        return "warning-light";
    }

    return "info-light";
}

export default function AdministratorStudentShow({ user, student, options }: StudentShowProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const zeroString = currency === "USD" ? "$ 0.00" : "₱ 0.00";

    const [hoveredSubject, setHoveredSubject] = useState<string | null>(null);
    const [selectedSubject, setSelectedSubject] = useState<ChecklistSubject | null>(null);
    const [selectedEnrollmentId, setSelectedEnrollmentId] = useState<number | "new" | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [actionDialog, setActionDialog] = useState<string | null>(null);
    const [isPrintDialogOpen, setIsPrintDialogOpen] = useState(false);
    const [printDialogOption, setPrintDialogOption] = useState<PrintOption>("both");
    const [studentDeleteAction, setStudentDeleteAction] = useState<"softDelete" | "forceDelete" | "restore" | null>(null);
    const fallbackSubject =
        student.checklist.flatMap((yearGroup: ChecklistYearGroup) => yearGroup.semesters.flatMap((semesterGroup) => semesterGroup.subjects))[0] ??
        null;
    const isStandaloneNonCredited = selectedSubject?.isStandaloneNonCredited === true;

    const { data, setData, patch, processing, errors, reset, clearErrors } = useForm<SubjectEnrollmentFormData>({
        enrollment_record_id: null as number | null,
        is_new_record: false,
        grade: "",
        remarks: "",
        classification: "internal",
        school_name: "",
        external_subject_code: "",
        external_subject_title: "",
        external_subject_units: "",
        credited_subject_id: "",
        academic_year: "",
        school_year: "",
        semester: "",
    });

    useEffect(() => {
        if (selectedSubject) {
            if (selectedEnrollmentId === "new") {
                setData({
                    enrollment_record_id: null,
                    is_new_record: true,
                    grade: "",
                    remarks: "",
                    classification: selectedSubject.isStandaloneNonCredited ? "non_credited" : "internal",
                    school_name: "",
                    external_subject_code: "",
                    external_subject_title: "",
                    external_subject_units: "",
                    credited_subject_id: "",
                    academic_year: selectedSubject.academic_year ? String(selectedSubject.academic_year) : "",
                    school_year: student.current_school_year || "",
                    semester: student.current_semester ? String(student.current_semester) : "",
                });
                clearErrors();
            } else {
                let record: ChecklistSubject | ChecklistHistoryRecord = selectedSubject;
                if (selectedEnrollmentId !== null && selectedSubject.history) {
                    record = selectedSubject.history.find((history) => history.id === Number(selectedEnrollmentId)) || selectedSubject;
                }

                setData({
                    enrollment_record_id:
                        record.id !== selectedSubject.id ? Number(record.id) : record.enrollment_id ? Number(record.enrollment_id) : null,
                    is_new_record: false,
                    grade: record.grade === "-" || record.grade === null ? "" : String(record.grade),
                    remarks: record.remarks || "",
                    classification: record.classification || "internal",
                    school_name: record.school_name || "",
                    external_subject_code: record.external_subject_code || "",
                    external_subject_title: record.external_subject_title || "",
                    external_subject_units: record.external_subject_units ? String(record.external_subject_units) : "",
                    credited_subject_id: record.credited_subject_id ? String(record.credited_subject_id) : "",
                    academic_year: record.academic_year ? String(record.academic_year) : "",
                    school_year: record.school_year || student.current_school_year,
                    semester: record.semester ? String(record.semester) : student.current_semester ? String(student.current_semester) : "",
                });
                clearErrors();
            }
        }
    }, [selectedEnrollmentId, selectedSubject]);

    useEffect(() => {
        if (data.classification === "credited" && selectedSubject) {
            setData("credited_subject_id", String(selectedSubject.id));
        }
    }, [data.classification, selectedSubject]);

    const handleSubjectClick = (subject: ChecklistSubject) => {
        setSelectedSubject(subject);
        if (subject.history && subject.history.length > 0) {
            setSelectedEnrollmentId(subject.history[0].id);
        } else {
            setSelectedEnrollmentId("new");
        }
        setIsDialogOpen(true);
    };

    const handleAddNonCreditedSubject = () => {
        if (!fallbackSubject) {
            toast.error("No curriculum subjects are available to initialize this form.");
            return;
        }

        setSelectedSubject({
            ...fallbackSubject,
            routeSubjectId: fallbackSubject.id,
            code: "Standalone",
            title: "Non-Credited Subject",
            history: [],
            isStandaloneNonCredited: true,
        });
        setSelectedEnrollmentId("new");
        setIsDialogOpen(true);
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedSubject) return;

        patch(route("administrators.students.subjects.update-grade", [student.id, selectedSubject.routeSubjectId ?? selectedSubject.id]), {
            preserveScroll: true,
            onSuccess: () => {
                setIsDialogOpen(false);
                setSelectedSubject(null);
                setSelectedEnrollmentId(null);
                reset();
                toast.success("Subject updated successfully");
            },
            onError: () => {
                toast.error("Failed to update subject");
            },
        });
    };

    const handleDelete = () => {
        if (!selectedSubject || selectedEnrollmentId === "new" || selectedEnrollmentId === null) return;

        router.delete(route("administrators.students.subjects.remove", [student.id, selectedEnrollmentId]), {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeleteDialogOpen(false);
                setIsDialogOpen(false);
                setSelectedSubject(null);
                setSelectedEnrollmentId(null);
                toast.success("Subject enrollment deleted successfully");
            },
            onError: () => {
                toast.error("Failed to delete subject enrollment");
            },
        });
    };

    const handleOpenPrintDialog = (option: PrintOption) => {
        setPrintDialogOption(option);
        setIsPrintDialogOpen(true);
    };

    const handleOpenClass = (classId: number) => {
        router.visit(route("administrators.classes.show", classId));
    };

    return (
        <AdminLayout user={user} title="Student">
            <SubjectEnrollmentDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                selectedSubject={selectedSubject}
                selectedEnrollmentId={selectedEnrollmentId}
                setSelectedEnrollmentId={setSelectedEnrollmentId}
                isStandaloneNonCredited={isStandaloneNonCredited}
                options={options}
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                onSubmit={handleSave}
                onDelete={selectedEnrollmentId !== "new" && selectedEnrollmentId !== null ? () => setIsDeleteDialogOpen(true) : undefined}
            />

            <AlertDialog open={isDeleteDialogOpen} onOpenChange={(open) => !open && setIsDeleteDialogOpen(false)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-destructive flex items-center gap-2">
                            <Trash2 className="h-5 w-5" />
                            Delete Subject Enrollment?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently remove the selected enrollment record for{" "}
                            <strong className="text-foreground">{selectedSubject?.code}</strong> - {selectedSubject?.title}. This action cannot be
                            undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {actionDialog === "updateId" && <UpdateIdDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} />}
            {actionDialog === "updateStatus" && (
                <UpdateStatusDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} options={options} />
            )}
            {actionDialog === "undoId" && <UndoIdDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} options={options} />}
            {actionDialog === "changeCourse" && (
                <ChangeCourseDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} options={options} />
            )}
            {actionDialog === "retryEnrollment" && (
                <RetryEnrollmentDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} options={options} />
            )}
            {actionDialog === "updateTuition" && <UpdateTuitionDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} />}
            {actionDialog === "clearance" && <ManageClearanceDialog open={true} onOpenChange={() => setActionDialog(null)} student={student} />}

            <StudentDeleteDialogs student={student} action={studentDeleteAction} onClose={() => setStudentDeleteAction(null)} />

            <PrintScheduleDialog open={isPrintDialogOpen} onOpenChange={setIsPrintDialogOpen} student={student} initialOption={printDialogOption} />

            <Head title={`Administrators • Student • ${student.name}`} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-2xl font-bold tracking-tight">{student.name}</h2>
                            {student.is_trashed && (
                                <Badge variant="destructive" className="text-xs">
                                    <Trash2 className="mr-1 h-3 w-3" /> Trashed
                                </Badge>
                            )}
                        </div>
                        <p className="text-muted-foreground">
                            Student ID: {student.student_id ?? "—"} • {student.academic_year}
                            {student.deleted_at ? ` • Deleted: ${student.deleted_at}` : ""}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Link href={route("administrators.students.index")} className={buttonVariants({ variant: "outline" })}>
                            Back
                        </Link>

                        <StudentActionMenu
                            student={student}
                            options={options}
                            setActionDialog={setActionDialog}
                            setDeleteAction={setStudentDeleteAction}
                        />

                        <Link
                            href={route("administrators.students.documents.index", student.id)}
                            className={buttonVariants({ variant: "secondary", className: "gap-2" })}
                        >
                            <FileText className="h-4 w-4" />
                            Manage Documents
                        </Link>
                        <Link href={route("administrators.students.edit", student.id)} className={buttonVariants()}>
                            Edit Student
                        </Link>
                        <a href={student.filament.view_url} target="_blank" rel="noreferrer" className={buttonVariants({ variant: "outline" })}>
                            Open in Filament
                        </a>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div className="space-y-6 md:col-span-3">
                        <StudentDetailsCard student={student} />

                        <StudentTabs student={student} options={options} onAdjustTuition={() => setActionDialog("updateTuition")} />

                        <AcademicScheduleDashboard
                            student={student}
                            hoveredSubject={hoveredSubject}
                            setHoveredSubject={setHoveredSubject}
                            onOpenClass={handleOpenClass}
                            onPrintTor={() => handleOpenPrintDialog("transcript")}
                            onPrintSchedule={() => handleOpenPrintDialog("both")}
                        />

                        <StudentChecklistSection
                            student={student}
                            onPrintTranscript={() => handleOpenPrintDialog("transcript")}
                            onAddNonCreditedSubject={handleAddNonCreditedSubject}
                            onSubjectClick={handleSubjectClick}
                            classificationBadgeVariant={classificationBadgeVariant}
                        />
                    </div>

                    <StudentSidebar student={student} />
                </div>
            </div>
        </AdminLayout>
    );
}
