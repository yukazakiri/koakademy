import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { BookOpen, CheckCircle, ExternalLink, FileText, Trash2 } from "lucide-react";
import type { FormEvent } from "react";
import type { ChecklistHistoryRecord, ChecklistSubject, StudentOptions, SubjectEnrollmentFormData } from "../types";

interface SubjectEnrollmentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedSubject: ChecklistSubject | null;
    selectedEnrollmentId: number | "new" | null;
    setSelectedEnrollmentId: (value: number | "new" | null) => void;
    isStandaloneNonCredited: boolean;
    options: StudentOptions;
    data: SubjectEnrollmentFormData;
    setData: (key: keyof SubjectEnrollmentFormData, value: string | number | boolean | null) => void;
    errors: Partial<Record<keyof SubjectEnrollmentFormData, string>>;
    processing: boolean;
    onSubmit: (event: FormEvent) => void;
    onDelete?: () => void;
}

export function SubjectEnrollmentDialog({
    open,
    onOpenChange,
    selectedSubject,
    selectedEnrollmentId,
    setSelectedEnrollmentId,
    isStandaloneNonCredited,
    options,
    data,
    setData,
    errors,
    processing,
    onSubmit,
    onDelete,
}: SubjectEnrollmentDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] gap-0 overflow-y-auto p-0 sm:max-w-[700px]">
                <DialogHeader className="border-b p-6 pb-4">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
                            <BookOpen className="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle>Update Subject Enrollment</DialogTitle>
                            <DialogDescription className="mt-1">
                                {isStandaloneNonCredited ? (
                                    <span>Adding a standalone non-credited subject record for this student.</span>
                                ) : (
                                    <span>
                                        Managing enrollment for <span className="text-foreground font-bold">{selectedSubject?.code}</span> -{" "}
                                        {selectedSubject?.title}
                                    </span>
                                )}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                {!isStandaloneNonCredited && selectedSubject?.history && selectedSubject.history.length > 0 && (
                    <div className="bg-muted/30 flex items-center justify-between border-b p-4 px-6">
                        <div className="space-y-1">
                            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Enrollment Record</Label>
                            <Select
                                value={String(selectedEnrollmentId)}
                                onValueChange={(value) => setSelectedEnrollmentId(value === "new" ? "new" : Number(value))}
                            >
                                <SelectTrigger className="bg-background h-9 w-full sm:w-[350px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedSubject.history.map((history: ChecklistHistoryRecord, index: number) => (
                                        <SelectItem key={history.id} value={String(history.id)}>
                                            Take {selectedSubject.history.length - index}: SY {history.school_year} (Sem {history.semester}){" "}
                                            {history.grade ? `- Grade: ${history.grade}` : "- In Progress"}
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="new" className="text-primary font-medium">
                                        + Add Historical Record
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                )}
                {!isStandaloneNonCredited && selectedSubject && (!selectedSubject.history || selectedSubject.history.length === 0) && (
                    <div className="bg-muted/30 flex items-center justify-between border-b p-4 px-6">
                        <div className="space-y-1">
                            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Enrollment Record</Label>
                            <Select
                                value={String(selectedEnrollmentId)}
                                onValueChange={(value) => setSelectedEnrollmentId(value === "new" ? "new" : null)}
                            >
                                <SelectTrigger className="bg-background h-9 w-full sm:w-[350px]">
                                    <SelectValue placeholder="No existing records" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedEnrollmentId !== "new" && <SelectItem value="null">Current Enrollment</SelectItem>}
                                    <SelectItem value="new" className="text-primary font-medium">
                                        + Add Historical Record
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                )}

                <form onSubmit={onSubmit} className="flex flex-col">
                    <div className="space-y-6 p-6">
                        <div className="space-y-3">
                            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Classification</Label>
                            {isStandaloneNonCredited ? (
                                <div className="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-sm font-medium text-amber-800">
                                    Non Credited
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    {options.classifications.map((type) => (
                                        <div
                                            key={type}
                                            className={`relative flex cursor-pointer items-center gap-2 rounded-lg border p-3 transition-all ${
                                                data.classification === type
                                                    ? "border-primary bg-primary/5 ring-primary ring-1"
                                                    : "hover:border-primary/50 hover:bg-accent"
                                            } `}
                                            onClick={() => setData("classification", type)}
                                        >
                                            <div
                                                className={`flex h-4 w-4 items-center justify-center rounded-full border ${
                                                    data.classification === type ? "border-primary bg-primary" : "border-muted-foreground"
                                                } `}
                                            >
                                                {data.classification === type && <div className="h-2 w-2 rounded-full bg-white" />}
                                            </div>
                                            <span className="text-sm font-medium capitalize">{type.replace("_", " ")}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {(data.classification === "non_credited" || isStandaloneNonCredited) && (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                    This will create a standalone non-credited subject record and will not be linked to the clicked curriculum
                                    subject.
                                </div>
                            )}
                        </div>

                        {data.classification !== "internal" && (
                            <div className="bg-muted/30 animate-in fade-in slide-in-from-top-2 space-y-4 rounded-xl border p-4 duration-300">
                                <div className="border-muted-foreground/20 flex items-center gap-2 border-b border-dashed pb-2">
                                    <ExternalLink className="text-muted-foreground h-4 w-4" />
                                    <h4 className="text-sm font-semibold">External School Details</h4>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="school_name">School Name</Label>
                                        <Input
                                            id="school_name"
                                            list="school_names_list"
                                            value={data.school_name}
                                            onChange={(event) => setData("school_name", event.target.value)}
                                            placeholder="Enter or select school name..."
                                            className="bg-background"
                                        />
                                        <datalist id="school_names_list">
                                            {options.school_names.map((school) => (
                                                <option key={school} value={school} />
                                            ))}
                                        </datalist>
                                        {errors.school_name && <span className="text-destructive text-xs">{errors.school_name}</span>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="external_subject_code">External Subject Code</Label>
                                        <Input
                                            id="external_subject_code"
                                            value={data.external_subject_code}
                                            onChange={(event) => setData("external_subject_code", event.target.value)}
                                            placeholder="e.g. MATH 101"
                                            className="bg-background"
                                        />
                                        {errors.external_subject_code && (
                                            <span className="text-destructive text-xs">{errors.external_subject_code}</span>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="external_subject_units">External Units</Label>
                                        <Input
                                            id="external_subject_units"
                                            type="number"
                                            value={data.external_subject_units}
                                            onChange={(event) => setData("external_subject_units", event.target.value)}
                                            placeholder="e.g. 3"
                                            className="bg-background"
                                        />
                                        {errors.external_subject_units && (
                                            <span className="text-destructive text-xs">{errors.external_subject_units}</span>
                                        )}
                                    </div>

                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="external_subject_title">External Subject Title</Label>
                                        <Input
                                            id="external_subject_title"
                                            value={data.external_subject_title}
                                            onChange={(event) => setData("external_subject_title", event.target.value)}
                                            placeholder="e.g. Introduction to Mathematics"
                                            className="bg-background"
                                        />
                                        {errors.external_subject_title && (
                                            <span className="text-destructive text-xs">{errors.external_subject_title}</span>
                                        )}
                                    </div>
                                </div>

                                {data.classification === "credited" && (
                                    <div className="pt-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="credited_subject_id">Credited As (Curriculum Subject)</Label>
                                            <div className="relative">
                                                <Input
                                                    value={`${selectedSubject?.code} - ${selectedSubject?.title}`}
                                                    disabled
                                                    className="bg-muted text-muted-foreground pr-10"
                                                />
                                                <div className="text-muted-foreground pointer-events-none absolute top-2.5 right-3">
                                                    <CheckCircle className="h-4 w-4" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="space-y-3">
                            <Label className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Academic Record</Label>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="grade">Grade</Label>
                                    <div className="relative">
                                        <Input
                                            id="grade"
                                            type="number"
                                            step="0.01"
                                            min="1"
                                            max="100"
                                            value={data.grade}
                                            onChange={(event) => setData("grade", event.target.value)}
                                            placeholder="--"
                                            className={cn(
                                                "pl-9 font-mono font-bold",
                                                data.grade && (Number(data.grade) <= 3.0 || Number(data.grade) >= 75)
                                                    ? "border-green-200 text-green-600 focus-visible:ring-green-500"
                                                    : "",
                                            )}
                                        />
                                        <div className="text-muted-foreground pointer-events-none absolute top-2.5 left-3">
                                            <FileText className="h-4 w-4" />
                                        </div>
                                    </div>
                                    {errors.grade && <span className="text-destructive text-xs">{errors.grade}</span>}
                                </div>

                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="school_year">School Year</Label>
                                    <Select value={data.school_year} onValueChange={(value) => setData("school_year", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select SY" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-[200px]">
                                            {options.school_years.map((schoolYear) => (
                                                <SelectItem key={schoolYear.value} value={schoolYear.value}>
                                                    {schoolYear.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.school_year && <span className="text-destructive text-xs">{errors.school_year}</span>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="academic_year">Year Level</Label>
                                    <Select value={String(data.academic_year)} onValueChange={(value) => setData("academic_year", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Year" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Year</SelectItem>
                                            <SelectItem value="2">2nd Year</SelectItem>
                                            <SelectItem value="3">3rd Year</SelectItem>
                                            <SelectItem value="4">4th Year</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.academic_year && <span className="text-destructive text-xs">{errors.academic_year}</span>}
                                </div>

                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="semester">Semester</Label>
                                    <Select value={String(data.semester)} onValueChange={(value) => setData("semester", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester</SelectItem>
                                            <SelectItem value="2">2nd Semester</SelectItem>
                                            <SelectItem value="3">Summer</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.semester && <span className="text-destructive text-xs">{errors.semester}</span>}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="remarks">Remarks</Label>
                            <Textarea
                                id="remarks"
                                value={data.remarks}
                                onChange={(event) => setData("remarks", event.target.value)}
                                placeholder="Optional remarks..."
                                className="resize-none"
                                rows={2}
                            />
                            {errors.remarks && <span className="text-destructive text-xs">{errors.remarks}</span>}
                        </div>
                    </div>

                    <DialogFooter className="bg-muted/10 border-t p-6 pt-2">
                        <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                            <div>
                                {onDelete && selectedEnrollmentId !== "new" && selectedEnrollmentId !== null && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="text-destructive hover:bg-destructive/10 border-destructive/50"
                                        onClick={onDelete}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Record
                                    </Button>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing} className="gap-2">
                                    <CheckCircle className="h-4 w-4" />
                                    Save Changes
                                </Button>
                            </div>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
