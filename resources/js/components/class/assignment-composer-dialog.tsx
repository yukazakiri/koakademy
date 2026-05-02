import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { FilePreview } from "@/components/class/file-preview";
import { AssignmentRubricCriterion, AssignmentRubricLevel, StudentEntry } from "@/types/class-detail-types";
import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import {
    IconChevronDown,
    IconPaperclip,
    IconPlus,
    IconSearch,
    IconTrash,
    IconUsers,
} from "@tabler/icons-react";
import { ChangeEvent, FormEvent, useMemo, useRef, useState } from "react";
import { toast } from "sonner";

import { store as storeClassPost } from "@/routes/faculty/classes/posts";

interface AssignmentComposerDialogProps {
    classId: number;
    classCode: string;
    classSection: string;
    currentFacultyId: string | null;
    students: StudentEntry[];
    mode?: "create" | "edit";
    post?: {
        id: number;
        title: string;
        content?: string | null;
        instruction?: string | null;
        status?: string | null;
        priority?: string | null;
        start_date?: string | null;
        due_date?: string | null;
        progress_percent?: number | null;
        total_points?: number | null;
        assigned_faculty_id?: string | null;
        audience_mode?: string | null;
        assigned_student_ids?: number[] | null;
        rubric?: AssignmentRubricCriterion[] | null;
        attachments?: { name: string; url: string; kind: "link" | "file" }[] | null;
    } | null;
    trigger?: React.ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}

interface AssignmentComposerForm {
    title: string;
    content: string;
    instruction: string;
    type: "assignment";
    status: "backlog" | "in_progress" | "review" | "done" | "blocked";
    priority: "low" | "medium" | "high";
    start_date: string;
    due_date: string;
    progress_percent: number;
    total_points: number;
    assigned_faculty_id: string | null;
    audience_mode: "all_students" | "specific_students";
    assigned_student_ids: number[];
    rubric: AssignmentRubricCriterion[];
    attachments: { name: string; url: string; kind: "link" | "file" }[];
    files: File[];
}

const MAX_FILE_SIZE_MB = 50;
const MAX_FILE_SIZE = MAX_FILE_SIZE_MB * 1024 * 1024;

const createRubricLevel = (): AssignmentRubricLevel => ({
    title: "",
    description: "",
});

const createRubricCriterion = (): AssignmentRubricCriterion => ({
    title: "",
    description: "",
    points: 0,
    levels: [createRubricLevel()],
});

const formatDateForInput = (value: Date): string => {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, "0");
    const day = String(value.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
};

const formatAttachmentName = (name: string): string => {
    if (name.length <= 38) {
        return name;
    }

    const extensionIndex = name.lastIndexOf(".");
    const extension = extensionIndex > 0 ? name.slice(extensionIndex) : "";
    const baseName = extensionIndex > 0 ? name.slice(0, extensionIndex) : name;

    return `${baseName.slice(0, 28)}…${extension}`;
};

export function AssignmentComposerDialog({
    classId,
    classCode,
    classSection,
    currentFacultyId,
    students,
    mode = "create",
    post = null,
    trigger,
    open: controlledOpen,
    onOpenChange,
}: AssignmentComposerDialogProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlled = controlledOpen !== undefined && onOpenChange !== undefined;
    const open = isControlled ? controlledOpen : internalOpen;
    const setOpen = isControlled ? onOpenChange : setInternalOpen;

    const [rubricOpen, setRubricOpen] = useState(false);
    const [studentSearch, setStudentSearch] = useState("");
    const fileInputRef = useRef<HTMLInputElement>(null);

    const defaultDate = useMemo(() => formatDateForInput(new Date()), []);

    const getInitialForm = (): AssignmentComposerForm => {
        if (mode === "edit" && post) {
            return {
                title: post.title ?? "",
                content: post.content ?? "",
                instruction: post.instruction ?? "",
                type: "assignment",
                status: (post.status as AssignmentComposerForm["status"]) ?? "backlog",
                priority: (post.priority as AssignmentComposerForm["priority"]) ?? "medium",
                start_date: post.start_date ?? defaultDate,
                due_date: post.due_date ?? "",
                progress_percent: post.progress_percent ?? 0,
                total_points: post.total_points ?? 100,
                assigned_faculty_id: post.assigned_faculty_id ?? currentFacultyId,
                audience_mode: (post.audience_mode as AssignmentComposerForm["audience_mode"]) ?? "all_students",
                assigned_student_ids: post.assigned_student_ids ?? [],
                rubric: post.rubric ?? [],
                attachments: post.attachments ?? [],
                files: [],
            };
        }

        return {
            title: "",
            content: "",
            instruction: "",
            type: "assignment",
            status: "backlog",
            priority: "medium",
            start_date: defaultDate,
            due_date: "",
            progress_percent: 0,
            total_points: 100,
            assigned_faculty_id: currentFacultyId,
            audience_mode: "all_students",
            assigned_student_ids: [],
            rubric: [],
            attachments: [],
            files: [],
        };
    };

    const form = useForm<AssignmentComposerForm>(getInitialForm());

    const filteredStudents = useMemo(() => {
        const query = studentSearch.trim().toLowerCase();
        if (query === "") {
            return students;
        }

        return students.filter((student) => {
            return student.name.toLowerCase().includes(query) || student.student_id.toLowerCase().includes(query);
        });
    }, [students, studentSearch]);

    const selectedStudentCount = form.data.audience_mode === "all_students" ? students.length : form.data.assigned_student_ids.length;

    const canSubmit = form.data.title.trim() !== "" && form.data.instruction.trim() !== "" && form.data.total_points > 0;

    const handleFilesSelected = (event: ChangeEvent<HTMLInputElement>) => {
        if (!event.target.files) {
            return;
        }

        const incomingFiles = Array.from(event.target.files);
        const oversizedFiles = incomingFiles.filter((file) => file.size > MAX_FILE_SIZE);
        const validFiles = incomingFiles.filter((file) => file.size <= MAX_FILE_SIZE);

        if (oversizedFiles.length > 0) {
            const names = oversizedFiles.map((file) => file.name).join(", ");
            toast.error(`These files exceed ${MAX_FILE_SIZE_MB}MB: ${names}`);
        }

        if (validFiles.length > 0) {
            form.setData("files", [...form.data.files, ...validFiles]);
        }

        event.target.value = "";
    };

    const handleRemoveFile = (index: number) => {
        form.setData(
            "files",
            form.data.files.filter((_, fileIndex) => fileIndex !== index),
        );
    };

    const handleRemoveAttachment = (index: number) => {
        form.setData(
            "attachments",
            form.data.attachments.filter((_, attachmentIndex) => attachmentIndex !== index),
        );
    };

    const handleToggleSpecificStudent = (enrollmentId: number, checked: boolean) => {
        if (checked) {
            form.setData("assigned_student_ids", [...form.data.assigned_student_ids, enrollmentId]);
            return;
        }

        form.setData(
            "assigned_student_ids",
            form.data.assigned_student_ids.filter((studentId) => studentId !== enrollmentId),
        );
    };

    const handleSelectAllVisible = () => {
        const visibleIds = filteredStudents.map((student) => Number(student.id));

        form.setData("assigned_student_ids", Array.from(new Set([...form.data.assigned_student_ids, ...visibleIds])));
    };

    const handleClearSpecificStudents = () => {
        form.setData("assigned_student_ids", []);
    };

    const handleUpdateCriterion = (index: number, patch: Partial<AssignmentRubricCriterion>) => {
        form.setData(
            "rubric",
            form.data.rubric.map((criterion, criterionIndex) => (criterionIndex === index ? { ...criterion, ...patch } : criterion)),
        );
    };

    const handleRemoveCriterion = (index: number) => {
        const nextRubric = form.data.rubric.filter((_, criterionIndex) => criterionIndex !== index);
        form.setData("rubric", nextRubric);

        if (nextRubric.length === 0) {
            setRubricOpen(false);
        }
    };

    const handleAddCriterion = () => {
        if (!rubricOpen) {
            setRubricOpen(true);
        }
        form.setData("rubric", [...form.data.rubric, createRubricCriterion()]);
    };

    const handleUpdateLevel = (criterionIndex: number, levelIndex: number, patch: Partial<AssignmentRubricLevel>) => {
        const nextRubric = form.data.rubric.map((criterion, currentCriterionIndex) => {
            if (currentCriterionIndex !== criterionIndex) {
                return criterion;
            }

            return {
                ...criterion,
                levels: criterion.levels.map((level, currentLevelIndex) =>
                    currentLevelIndex === levelIndex ? { ...level, ...patch } : level,
                ),
            };
        });

        form.setData("rubric", nextRubric);
    };

    const handleAddLevel = (criterionIndex: number) => {
        const nextRubric = form.data.rubric.map((criterion, currentCriterionIndex) => {
            if (currentCriterionIndex !== criterionIndex) {
                return criterion;
            }

            return {
                ...criterion,
                levels: [...criterion.levels, createRubricLevel()],
            };
        });

        form.setData("rubric", nextRubric);
    };

    const handleRemoveLevel = (criterionIndex: number, levelIndex: number) => {
        const nextRubric = form.data.rubric.map((criterion, currentCriterionIndex) => {
            if (currentCriterionIndex !== criterionIndex) {
                return criterion;
            }

            return {
                ...criterion,
                levels: criterion.levels.filter((_, currentLevelIndex) => currentLevelIndex !== levelIndex),
            };
        });

        form.setData("rubric", nextRubric);
    };

    const handleResetComposer = () => {
        form.reset();
        form.clearErrors();
        const initial = getInitialForm();
        Object.entries(initial).forEach(([key, value]) => {
            form.setData(key as keyof AssignmentComposerForm, value);
        });
        setRubricOpen((post?.rubric?.length ?? 0) > 0);
        setStudentSearch("");
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.data.audience_mode === "specific_students" && form.data.assigned_student_ids.length === 0) {
            toast.error("Select at least one student or assign to all students.");
            return;
        }

        if (form.data.total_points <= 0) {
            toast.error("Total points must be greater than zero.");
            return;
        }

        const totalUploadSize = form.data.files.reduce((size, file) => size + file.size, 0);
        if (totalUploadSize > MAX_FILE_SIZE) {
            toast.error(`Total attachment size must be ${MAX_FILE_SIZE_MB}MB or less.`);
            return;
        }

        const formData = new FormData();
        formData.append("title", form.data.title);
        formData.append("content", form.data.content);
        formData.append("instruction", form.data.instruction);
        formData.append("type", "assignment");
        formData.append("status", form.data.status);
        formData.append("priority", form.data.priority);
        formData.append("start_date", form.data.start_date);
        if (form.data.due_date) {
            formData.append("due_date", form.data.due_date);
        }
        formData.append("progress_percent", String(form.data.progress_percent));
        formData.append("total_points", String(form.data.total_points));
        formData.append("assigned_faculty_id", form.data.assigned_faculty_id ?? "");
        formData.append("audience_mode", form.data.audience_mode);

        form.data.assigned_student_ids.forEach((id, index) => {
            formData.append(`assigned_student_ids[${index}]`, String(id));
        });

        form.data.rubric.forEach((criterion, criterionIndex) => {
            formData.append(`rubric[${criterionIndex}][title]`, criterion.title);
            formData.append(`rubric[${criterionIndex}][description]`, criterion.description ?? "");
            formData.append(`rubric[${criterionIndex}][points]`, String(criterion.points));

            criterion.levels.forEach((level, levelIndex) => {
                formData.append(`rubric[${criterionIndex}][levels][${levelIndex}][title]`, level.title);
                formData.append(`rubric[${criterionIndex}][levels][${levelIndex}][description]`, level.description ?? "");
            });
        });

        form.data.attachments.forEach((attachment, index) => {
            formData.append(`attachments[${index}][name]`, attachment.name);
            formData.append(`attachments[${index}][url]`, attachment.url);
            formData.append(`attachments[${index}][kind]`, attachment.kind);
        });

        form.data.files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        if (mode === "edit" && post) {
            form.put(route("faculty.classes.posts.update", { class: classId, post: post.id }), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Assignment updated.");
                    setOpen(false);
                },
                onError: () => {
                    toast.error("Please review the assignment form and try again.");
                },
            });
        } else {
            form.post(storeClassPost.url({ class: classId }), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Assignment posted to class.");
                    handleResetComposer();
                    setOpen(false);
                },
                onError: () => {
                    toast.error("Please review the assignment form and try again.");
                },
            });
        }
    };

    return (
        <div className={mode === "create" ? "flex justify-end" : undefined}>
            <Dialog open={open} onOpenChange={setOpen}>
                {mode === "create" ? (
                    <DialogTrigger asChild>
                        <Button className="rounded-full">
                            <IconPlus className="mr-2 size-4" />
                            Create assignment
                        </Button>
                    </DialogTrigger>
                ) : (
                    trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>
                )}

                <DialogContent className="max-w-[95vw] gap-0 overflow-hidden p-0 sm:max-w-4xl">
                    <DialogHeader className="space-y-2 px-6 pt-6 pb-5 sm:px-8">
                        <Badge variant="secondary" className="w-fit text-xs font-medium">
                            Assignment Composer
                        </Badge>
                        <DialogTitle className="text-xl font-semibold tracking-tight">
                            {mode === "edit" ? "Edit assignment" : "Create assignment"}
                        </DialogTitle>
                        <p className="text-muted-foreground text-sm">
                            {classCode} · Section {classSection}
                        </p>
                    </DialogHeader>

                    <Separator />

                    <form onSubmit={handleSubmit} className="flex flex-col">
                        <ScrollArea className="max-h-[68vh]">
                            <div className="space-y-6 px-6 py-6 sm:px-8">
                                <section className="space-y-4 rounded-2xl border p-4 sm:p-5">
                                    <p className="text-sm font-semibold">1. Assignment details</p>

                                    <div className="space-y-2">
                                        <Label htmlFor="assignment-title" className="text-sm font-medium">
                                            Title
                                        </Label>
                                        <Input
                                            id="assignment-title"
                                            value={form.data.title}
                                            onChange={(event) => form.setData("title", event.target.value)}
                                            placeholder="e.g., Unit 3 Problem Set"
                                            className="h-11"
                                            required
                                        />
                                        {form.errors.title && <p className="text-destructive text-xs">{form.errors.title}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="assignment-instruction" className="text-sm font-medium">
                                                Instructions
                                            </Label>
                                            <span className="text-muted-foreground text-xs">Max {MAX_FILE_SIZE_MB}MB per file</span>
                                        </div>

                                        <div className="overflow-hidden rounded-xl border">
                                            <Textarea
                                                id="assignment-instruction"
                                                value={form.data.instruction}
                                                onChange={(event) => form.setData("instruction", event.target.value)}
                                                placeholder="Describe what students should do, include any requirements, resources, or steps to follow..."
                                                rows={6}
                                                className="min-h-[160px] resize-none border-0 text-sm shadow-none focus-visible:ring-0"
                                            />

                                            {(form.data.attachments.length > 0 || form.data.files.length > 0) && (
                                                <div className="space-y-2 overflow-x-hidden px-3 pb-3">
                                                    {/* Existing attachments */}
                                                    {form.data.attachments.map((attachment, index) => (
                                                        <FilePreview
                                                            key={`attachment-${attachment.url}-${index}`}
                                                            name={attachment.name}
                                                            url={attachment.url}
                                                            kind={attachment.kind}
                                                            onRemove={() => handleRemoveAttachment(index)}
                                                        />
                                                    ))}

                                                    {/* New files */}
                                                    {form.data.files.map((file, index) => (
                                                        <FilePreview
                                                            key={`file-${file.name}-${index}`}
                                                            name={file.name}
                                                            size={file.size}
                                                            kind="file"
                                                            file={file}
                                                            onRemove={() => handleRemoveFile(index)}
                                                        />
                                                    ))}
                                                </div>
                                            )}

                                            <div className="bg-muted/20 flex min-w-0 items-center justify-between gap-2 rounded-b-xl border-t px-3 py-2">
                                                <Button type="button" variant="ghost" size="sm" onClick={() => fileInputRef.current?.click()}>
                                                    <IconPaperclip className="mr-2 size-4" />
                                                    {form.data.attachments.length > 0 || form.data.files.length > 0 ? "Add attachment" : "Attach file"}
                                                </Button>
                                                {(form.data.attachments.length > 0 || form.data.files.length > 0) && (
                                                    <Badge variant="secondary" className="shrink-0 text-xs font-normal">
                                                        {form.data.attachments.length + form.data.files.length} item
                                                        {form.data.attachments.length + form.data.files.length > 1 ? "s" : ""}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>

                                        {form.errors.instruction && <p className="text-destructive text-xs">{form.errors.instruction}</p>}
                                        <input ref={fileInputRef} type="file" multiple className="hidden" onChange={handleFilesSelected} />
                                    </div>
                                </section>

                                <section className="grid gap-4 rounded-2xl border p-4 sm:grid-cols-2 sm:p-5">
                                    <p className="col-span-full text-sm font-semibold">2. Points and due date</p>

                                    <div className="space-y-2">
                                        <Label htmlFor="assignment-points" className="text-sm font-medium">
                                            Points
                                        </Label>
                                        <Input
                                            id="assignment-points"
                                            type="number"
                                            min={1}
                                            value={form.data.total_points}
                                            onChange={(event) => {
                                                const points = Number(event.target.value);
                                                form.setData("total_points", Number.isNaN(points) ? 0 : points);
                                            }}
                                            className="h-11"
                                            required
                                        />
                                        {form.errors.total_points && <p className="text-destructive text-xs">{form.errors.total_points}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="assignment-due-date" className="text-sm font-medium">
                                            Due date
                                        </Label>
                                        <Input
                                            id="assignment-due-date"
                                            type="date"
                                            value={form.data.due_date}
                                            onChange={(event) => form.setData("due_date", event.target.value)}
                                            className="h-11"
                                        />
                                        {form.errors.due_date && <p className="text-destructive text-xs">{form.errors.due_date}</p>}
                                    </div>
                                </section>

                                <section className="space-y-4 rounded-2xl border p-4 sm:p-5">
                                    <div className="flex items-center gap-2">
                                        <IconUsers className="text-muted-foreground size-4" />
                                        <p className="text-sm font-semibold">3. Assign to</p>
                                        <Badge variant="secondary" className="ml-auto text-xs font-normal">
                                            {selectedStudentCount} students
                                        </Badge>
                                    </div>

                                    <RadioGroup
                                        value={form.data.audience_mode}
                                        onValueChange={(value) => {
                                            const nextMode = value as "all_students" | "specific_students";
                                            form.setData("audience_mode", nextMode);
                                            if (nextMode === "all_students") {
                                                form.setData("assigned_student_ids", []);
                                            }
                                        }}
                                        className="gap-3"
                                    >
                                        <label className="hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors has-[[data-state=checked]]:border-primary/50 has-[[data-state=checked]]:bg-primary/5">
                                            <RadioGroupItem value="all_students" id="audience-all" className="mt-0.5" />
                                            <div className="space-y-0.5">
                                                <p className="text-sm font-medium">All students</p>
                                                <p className="text-muted-foreground text-xs">Everyone enrolled in this class</p>
                                            </div>
                                        </label>

                                        <label className="hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors has-[[data-state=checked]]:border-primary/50 has-[[data-state=checked]]:bg-primary/5">
                                            <RadioGroupItem value="specific_students" id="audience-specific" className="mt-0.5" />
                                            <div className="space-y-0.5">
                                                <p className="text-sm font-medium">Specific students</p>
                                                <p className="text-muted-foreground text-xs">Select individual students from the roster</p>
                                            </div>
                                        </label>
                                    </RadioGroup>

                                    {form.data.audience_mode === "specific_students" && (
                                        <div className="space-y-3 rounded-xl border p-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <div className="relative min-w-0 flex-1">
                                                    <IconSearch className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                                    <Input
                                                        value={studentSearch}
                                                        onChange={(event) => setStudentSearch(event.target.value)}
                                                        placeholder="Search by name or student ID"
                                                        className="pl-9"
                                                    />
                                                </div>
                                                <Button type="button" variant="outline" size="sm" onClick={handleSelectAllVisible}>
                                                    Select visible
                                                </Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={handleClearSpecificStudents}>
                                                    Clear
                                                </Button>
                                            </div>

                                            <div className="max-h-[240px] overflow-y-auto rounded-lg border">
                                                {filteredStudents.length === 0 ? (
                                                    <p className="text-muted-foreground px-3 py-4 text-sm">No students match your search.</p>
                                                ) : (
                                                    filteredStudents.map((student) => {
                                                        const enrollmentId = Number(student.id);
                                                        const checked = form.data.assigned_student_ids.includes(enrollmentId);

                                                        return (
                                                            <label
                                                                key={student.id}
                                                                className="hover:bg-muted/40 flex cursor-pointer items-center gap-3 px-3 py-2.5 transition-colors"
                                                            >
                                                                <Checkbox
                                                                    checked={checked}
                                                                    onCheckedChange={(value) => handleToggleSpecificStudent(enrollmentId, Boolean(value))}
                                                                />
                                                                <div className="min-w-0">
                                                                    <p className="text-sm font-medium">{student.name}</p>
                                                                    <p className="text-muted-foreground text-xs">{student.student_id}</p>
                                                                </div>
                                                            </label>
                                                        );
                                                    })
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {form.errors.assigned_student_ids && <p className="text-destructive text-xs">{form.errors.assigned_student_ids}</p>}
                                </section>

                                <section className="space-y-3 rounded-2xl border p-4 sm:p-5">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-semibold">4. Grading rubric</p>
                                            <Badge variant="outline" className="text-xs font-normal">
                                                Optional
                                            </Badge>
                                        </div>
                                        <Collapsible open={rubricOpen} onOpenChange={setRubricOpen}>
                                            <CollapsibleTrigger asChild>
                                                <Button type="button" variant="ghost" size="sm" className="gap-1">
                                                    {form.data.rubric.length === 0 ? "Add rubric" : rubricOpen ? "Collapse" : "Expand"}
                                                    <IconChevronDown className={`size-4 transition-transform ${rubricOpen ? "rotate-180" : ""}`} />
                                                </Button>
                                            </CollapsibleTrigger>

                                            <CollapsibleContent className="mt-3 space-y-4">
                                                {form.data.rubric.length === 0 && (
                                                    <button
                                                        type="button"
                                                        onClick={handleAddCriterion}
                                                        className="border-border/60 hover:border-primary/40 hover:bg-muted/50 flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed py-6 transition-colors"
                                                    >
                                                        <div className="bg-primary/10 flex size-10 items-center justify-center rounded-full">
                                                            <IconPlus className="text-primary size-5" />
                                                        </div>
                                                        <span className="text-muted-foreground text-sm">Add your first rubric criterion</span>
                                                    </button>
                                                )}

                                                {form.data.rubric.map((criterion, criterionIndex) => (
                                                    <div key={`criterion-${criterionIndex}`} className="space-y-4 rounded-xl border p-4">
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="grid flex-1 gap-3 sm:grid-cols-[1fr_auto]">
                                                                <Input
                                                                    value={criterion.title}
                                                                    onChange={(event) =>
                                                                        handleUpdateCriterion(criterionIndex, {
                                                                            title: event.target.value,
                                                                        })
                                                                    }
                                                                    placeholder="Criteria title"
                                                                />
                                                                <Input
                                                                    type="number"
                                                                    min={0}
                                                                    value={criterion.points}
                                                                    onChange={(event) => {
                                                                        const points = Number(event.target.value);
                                                                        handleUpdateCriterion(criterionIndex, {
                                                                            points: Number.isNaN(points) ? 0 : points,
                                                                        });
                                                                    }}
                                                                    className="w-24"
                                                                />
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 shrink-0"
                                                                onClick={() => handleRemoveCriterion(criterionIndex)}
                                                            >
                                                                <IconTrash className="size-4" />
                                                            </Button>
                                                        </div>

                                                        <Textarea
                                                            value={criterion.description ?? ""}
                                                            onChange={(event) =>
                                                                handleUpdateCriterion(criterionIndex, {
                                                                    description: event.target.value,
                                                                })
                                                            }
                                                            rows={2}
                                                            placeholder="Criterion description"
                                                            className="resize-none text-sm"
                                                        />

                                                        <Separator />

                                                        <div className="space-y-2">
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-muted-foreground text-xs font-medium uppercase tracking-wider">
                                                                    Levels
                                                                </span>
                                                                <Button type="button" size="sm" variant="ghost" onClick={() => handleAddLevel(criterionIndex)}>
                                                                    <IconPlus className="mr-1 size-3.5" />
                                                                    Add level
                                                                </Button>
                                                            </div>

                                                            {criterion.levels.map((level, levelIndex) => (
                                                                <div key={`criterion-${criterionIndex}-level-${levelIndex}`} className="rounded-lg border p-3">
                                                                    <div className="flex items-start gap-3">
                                                                        <div className="grid min-w-0 flex-1 gap-2">
                                                                            <Input
                                                                                value={level.title}
                                                                                onChange={(event) =>
                                                                                    handleUpdateLevel(criterionIndex, levelIndex, {
                                                                                        title: event.target.value,
                                                                                    })
                                                                                }
                                                                                placeholder="Level title"
                                                                                className="h-9"
                                                                            />
                                                                            <Textarea
                                                                                value={level.description ?? ""}
                                                                                onChange={(event) =>
                                                                                    handleUpdateLevel(criterionIndex, levelIndex, {
                                                                                        description: event.target.value,
                                                                                    })
                                                                                }
                                                                                rows={2}
                                                                                placeholder="Level description"
                                                                                className="resize-none text-sm"
                                                                            />
                                                                        </div>
                                                                        <Button
                                                                            type="button"
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            className="size-8 shrink-0"
                                                                            disabled={criterion.levels.length === 1}
                                                                            onClick={() => handleRemoveLevel(criterionIndex, levelIndex)}
                                                                        >
                                                                            <IconX className="size-4" />
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                ))}

                                                {form.data.rubric.length > 0 && (
                                                    <Button type="button" variant="outline" size="sm" onClick={handleAddCriterion}>
                                                        <IconPlus className="mr-2 size-4" />
                                                        Add another criterion
                                                    </Button>
                                                )}
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </div>
                                </section>

                                <div className="space-y-2 rounded-2xl border p-4">
                                    <Label htmlFor="assignment-content" className="text-sm font-medium">
                                        Additional notes (optional)
                                    </Label>
                                    <Textarea
                                        id="assignment-content"
                                        value={form.data.content}
                                        onChange={(event) => form.setData("content", event.target.value)}
                                        placeholder="Optional context, reminders, or references"
                                        rows={3}
                                        className="resize-none text-sm"
                                    />
                                </div>
                            </div>
                        </ScrollArea>

                        <Separator />

                        <DialogFooter className="bg-background/95 sticky bottom-0 px-6 py-4 backdrop-blur sm:px-8">
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing || !canSubmit} size="lg" className="rounded-full px-8">
                                {form.processing ? "Posting..." : "Post assignment"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
