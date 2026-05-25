import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Combobox, type ComboboxOption } from "@/components/ui/combobox";
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DndContext, type DragEndEvent, PointerSensor, useDraggable, useSensor, useSensors } from "@dnd-kit/core";
import { useForm } from "@inertiajs/react";
import {
    ArrowRight,
    BarChart3,
    BookOpenCheck,
    CheckCircle2,
    CircleDollarSign,
    GraduationCap,
    GripVertical,
    Loader2,
    LockKeyhole,
    MousePointerClick,
    Pencil,
    Plus,
    Save,
    Scissors,
    Settings2,
    Sparkles,
    Target,
    Trash2,
    Users,
    Workflow,
} from "lucide-react";
import { type PointerEvent as ReactPointerEvent, useCallback, useMemo, useRef, useState } from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type {
    EnrollmentPipelineActionType,
    EnrollmentPipelineNodeAction,
    EnrollmentPipelineNodeActionType,
    EnrollmentPipelineNodeCondition,
    EnrollmentPipelineStep,
    EnrollmentPipelineStepAction,
    EnrollmentStatMetric,
    EnrollmentStatsCard,
    SystemManagementPageProps,
} from "./types";

interface EnrollmentPipelineFormData {
    schema_version: number;
    submitted_label: string;
    enrollment_courses: number[];
    entry_step_key: string;
    completion_step_key: string;
    steps: EnrollmentPipelineStep[];
    automation: {
        auto_create_student_enrollment: boolean;
        auto_assign_subjects: boolean;
        default_new_applicant_to_first_year: boolean;
    };
    enrollment_stats: {
        cards: EnrollmentStatsCard[];
    };
}

const colorOptions = ["yellow", "blue", "green", "emerald", "teal", "gray", "amber", "red", "indigo", "orange"] as const;

const colorHex: Record<(typeof colorOptions)[number], string> = {
    yellow: "#facc15",
    blue: "#3b82f6",
    green: "#22c55e",
    emerald: "#10b981",
    teal: "#14b8a6",
    gray: "#6b7280",
    amber: "#f59e0b",
    red: "#ef4444",
    indigo: "#6366f1",
    orange: "#f97316",
};

const actionOptions: Array<{
    value: EnrollmentPipelineActionType;
    label: string;
    description: string;
    icon: typeof CheckCircle2;
}> = [
    {
        value: "standard",
        label: "Move to this status",
        description: "Use this for review steps that only need approval and a status change.",
        icon: CheckCircle2,
    },
    {
        value: "department_verification",
        label: "Run department verification",
        description: "Executes the department verification flow, including the existing status update and notifications.",
        icon: BookOpenCheck,
    },
    {
        value: "cashier_verification",
        label: "Require cashier/payment verification",
        description: "Routes this step through the payment verification flow instead of a simple approval button.",
        icon: CircleDollarSign,
    },
];

const nodeActionOptions: Array<{ value: EnrollmentPipelineNodeActionType; label: string; description: string }> = [
    { value: "change_status", label: "Change status", description: "Move the enrollment into this node status." },
    { value: "send_email", label: "Send email", description: "Send a simple email to the student." },
    { value: "send_notification", label: "Send notification", description: "Notify super admins and optionally the student." },
    { value: "department_verification", label: "Department verification", description: "Run the existing department verification flow." },
    { value: "cashier_verification", label: "Cashier verification", description: "Run the existing cashier payment verification flow." },
    { value: "sync_student_enrolled_status", label: "Sync enrolled status", description: "Mark the student status as enrolled." },
    { value: "auto_enroll_classes", label: "Auto-enroll classes", description: "Enroll the student in available classes." },
    { value: "calculate_tuition", label: "Calculate tuition", description: "Create tuition from configured or submitted tuition data." },
    { value: "update_tuition", label: "Update tuition", description: "Update existing tuition attributes from action config." },
    { value: "create_payment_transactions", label: "Create transactions", description: "Reserved for cashier verification payment details." },
];

const profileFieldOptions = [
    { value: "first_name", label: "First name" },
    { value: "last_name", label: "Last name" },
    { value: "email", label: "Email" },
    { value: "phone", label: "Phone" },
    { value: "birth_date", label: "Birth date" },
    { value: "gender", label: "Gender" },
    { value: "address", label: "Address" },
    { value: "course_id", label: "Course" },
];

const statsMetricOptions: Array<{ value: EnrollmentStatMetric; label: string }> = [
    { value: "total_records", label: "Total Records" },
    { value: "active_records", label: "Active Records" },
    { value: "trashed_records", label: "Deleted Records" },
    { value: "status_count", label: "Status Count" },
    { value: "paid_count", label: "Fully Paid Count" },
];

const slugify = (value: string, fallback: string): string => {
    const slug = value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "");

    return slug.length > 0 ? slug : fallback;
};

const actionsForActionType = (actionType: EnrollmentPipelineActionType): EnrollmentPipelineStepAction[] => {
    if (actionType === "department_verification") {
        return ["department_verification"];
    }

    if (actionType === "cashier_verification") {
        return ["cashier_verification"];
    }

    return ["advance_status"];
};

const nodeActionsForActionType = (actionType: EnrollmentPipelineActionType): EnrollmentPipelineNodeAction[] => {
    const type = actionType === "department_verification" || actionType === "cashier_verification" ? actionType : "change_status";

    return [
        {
            key: type === "change_status" ? "advance_status" : type,
            type,
            enabled: true,
            order: 1,
            config: {},
            halt_on_failure: true,
        },
    ];
};

const normalizeNodeActions = (step: EnrollmentPipelineStep, actionType: EnrollmentPipelineActionType): EnrollmentPipelineNodeAction[] => {
    if (step.node_actions?.length) {
        return [...step.node_actions].sort((left, right) => left.order - right.order);
    }

    return nodeActionsForActionType(actionType);
};

const normalizeNodeConditions = (step: EnrollmentPipelineStep): EnrollmentPipelineNodeCondition[] =>
    step.node_conditions?.length ? [...step.node_conditions].sort((left, right) => left.order - right.order) : [];

const normalizeActionType = (step: EnrollmentPipelineStep): EnrollmentPipelineActionType => {
    if (step.actions?.includes("cashier_verification")) {
        return "cashier_verification";
    }

    if (step.actions?.includes("department_verification")) {
        return "department_verification";
    }

    return step.action_type || "standard";
};

const createDefaultStep = (index: number): EnrollmentPipelineStep => ({
    key: `step_${index}`,
    status: `step_${index}`,
    label: `Step ${index}`,
    color: "indigo",
    allowed_roles: [],
    action_type: "standard",
    actions: ["advance_status"],
    node_actions: nodeActionsForActionType("standard"),
    node_conditions: [],
    next_step_key: null,
});

interface CanvasNodePosition {
    x: number;
    y: number;
}

const canvasNodeWidth = 360;
const canvasNodeHeight = 112;

const getCanvasNodeId = (step: EnrollmentPipelineStep, index: number): string => `${step.key || "step"}-${index}`;

const defaultCanvasPosition = (index: number): CanvasNodePosition => ({
    x: 80 + (index % 2) * 420,
    y: 72 + Math.floor(index / 2) * 180,
});

export default function SystemManagementEnrollmentPipelinePage({
    user,
    general_settings,
    enrollment_pipeline,
    enrollment_stats,
    available_roles,
    available_enrollment_courses,
    access,
}: SystemManagementPageProps) {
    const initialSteps = enrollment_pipeline?.steps || [];
    const availableEnrollmentCourseIds = new Set((available_enrollment_courses ?? []).map((course) => course.id));

    const pipelineForm = useForm<EnrollmentPipelineFormData>({
        schema_version: enrollment_pipeline?.schema_version || 2,
        submitted_label: enrollment_pipeline?.submitted_label || "Submitted",
        enrollment_courses: (general_settings.enrollment_courses ?? [])
            .map((courseId) => Number(courseId))
            .filter((courseId) => Number.isInteger(courseId) && courseId > 0)
            .filter((courseId) => availableEnrollmentCourseIds.has(courseId)),
        entry_step_key: enrollment_pipeline?.entry_step_key || initialSteps[0]?.key || "",
        completion_step_key: enrollment_pipeline?.completion_step_key || initialSteps[initialSteps.length - 1]?.key || "",
        steps: initialSteps.map((step, index) => {
            const actionType = normalizeActionType(step);

            return {
                key: step.key || `step_${index + 1}`,
                status: step.status,
                label: step.label,
                color: step.color || "indigo",
                allowed_roles: step.allowed_roles || [],
                action_type: actionType,
                actions: step.actions?.length ? step.actions : actionsForActionType(actionType),
                node_actions: normalizeNodeActions(step, actionType),
                node_conditions: normalizeNodeConditions(step),
                next_step_key: step.next_step_key ?? initialSteps[index + 1]?.key ?? null,
            };
        }),
        automation: {
            auto_create_student_enrollment: enrollment_pipeline?.automation?.auto_create_student_enrollment ?? false,
            auto_assign_subjects: enrollment_pipeline?.automation?.auto_assign_subjects ?? false,
            default_new_applicant_to_first_year: enrollment_pipeline?.automation?.default_new_applicant_to_first_year ?? true,
        },
        enrollment_stats: {
            cards: enrollment_stats?.cards || [],
        },
    });

    const [selectedStepIndex, setSelectedStepIndex] = useState<number | null>(initialSteps.length > 0 ? 0 : null);
    const [selectedRoleByStep, setSelectedRoleByStep] = useState<Record<number, string>>({});
    const [enrollmentCourseSearch, setEnrollmentCourseSearch] = useState("");
    const [nodePositions, setNodePositions] = useState<Record<string, CanvasNodePosition>>(() =>
        Object.fromEntries(initialSteps.map((step, index) => [getCanvasNodeId(step, index), defaultCanvasPosition(index)])),
    );
    const [connectingFromIndex, setConnectingFromIndex] = useState<number | null>(null);
    const [dragConnection, setDragConnection] = useState<{ sourceIndex: number; currentPoint: CanvasNodePosition } | null>(null);
    const canvasRef = useRef<HTMLDivElement | null>(null);

    const formErrors = pipelineForm.errors as Record<string, string | undefined>;
    const selectedStep = selectedStepIndex !== null ? pipelineForm.data.steps[selectedStepIndex] : undefined;
    const selectedNextStep = selectedStep?.next_step_key
        ? pipelineForm.data.steps.find((step) => step.key === selectedStep.next_step_key)
        : undefined;
    const selectedIncomingSteps = selectedStep ? pipelineForm.data.steps.filter((step) => step.next_step_key === selectedStep.key) : [];
    const entryStep = pipelineForm.data.steps.find((step) => step.key === pipelineForm.data.entry_step_key) ?? pipelineForm.data.steps[0];
    const completionStep =
        pipelineForm.data.steps.find((step) => step.key === pipelineForm.data.completion_step_key) ??
        pipelineForm.data.steps[pipelineForm.data.steps.length - 1];
    const canvasNodes = useMemo(
        () =>
            pipelineForm.data.steps.map((step, index) => {
                const id = getCanvasNodeId(step, index);

                return {
                    id,
                    step,
                    index,
                    position: nodePositions[id] ?? defaultCanvasPosition(index),
                };
            }),
        [nodePositions, pipelineForm.data.steps],
    );
    const canvasSensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 4,
            },
        }),
    );
    const getCanvasPoint = useCallback((clientX: number, clientY: number): CanvasNodePosition => {
        const rect = canvasRef.current?.getBoundingClientRect();

        if (!rect) {
            return { x: 0, y: 0 };
        }

        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }, []);

    const roleComboboxOptions: ComboboxOption[] = useMemo(
        () =>
            available_roles.map((roleName) => ({
                value: roleName,
                label: roleName,
                searchText: roleName,
            })),
        [available_roles],
    );

    const filteredEnrollmentCourses = useMemo(() => {
        const search = enrollmentCourseSearch.trim().toLowerCase();

        if (search.length === 0) {
            return available_enrollment_courses ?? [];
        }

        return (available_enrollment_courses ?? []).filter((course) => `${course.code} ${course.title}`.toLowerCase().includes(search));
    }, [available_enrollment_courses, enrollmentCourseSearch]);

    const selectedEnrollmentCourses = useMemo(() => {
        const selectedIds = new Set(pipelineForm.data.enrollment_courses.map((courseId) => Number(courseId)));

        return (available_enrollment_courses ?? []).filter((course) => selectedIds.has(course.id));
    }, [available_enrollment_courses, pipelineForm.data.enrollment_courses]);

    const updatePipelineStep = (index: number, updater: (step: EnrollmentPipelineStep) => EnrollmentPipelineStep) => {
        const currentStep = pipelineForm.data.steps[index];
        if (!currentStep) {
            return;
        }

        const updatedStep = updater(currentStep);
        const steps = pipelineForm.data.steps.map((step, stepIndex) => (stepIndex === index ? updatedStep : step));

        pipelineForm.setData("steps", steps);

        if (updatedStep.key !== currentStep.key) {
            if (pipelineForm.data.entry_step_key === currentStep.key) {
                pipelineForm.setData("entry_step_key", updatedStep.key);
            }

            if (pipelineForm.data.completion_step_key === currentStep.key) {
                pipelineForm.setData("completion_step_key", updatedStep.key);
            }
        }
    };

    const setStepField = (index: number, field: keyof EnrollmentPipelineStep, value: string | string[]) => {
        updatePipelineStep(index, (step) => ({
            ...step,
            [field]: value,
        }));
    };

    const setStepAction = (index: number, actionType: EnrollmentPipelineActionType) => {
        updatePipelineStep(index, (step) => ({
            ...step,
            action_type: actionType,
            actions: actionsForActionType(actionType),
            node_actions: nodeActionsForActionType(actionType),
        }));
    };

    const updateNodeAction = (stepIndex: number, actionIndex: number, updater: (action: EnrollmentPipelineNodeAction) => EnrollmentPipelineNodeAction) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_actions: (step.node_actions || []).map((action, index) => (index === actionIndex ? updater(action) : action)),
        }));
    };

    const addNodeAction = (stepIndex: number) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_actions: [
                ...(step.node_actions || []),
                {
                    key: `action_${(step.node_actions || []).length + 1}`,
                    type: "change_status",
                    enabled: true,
                    order: (step.node_actions || []).length + 1,
                    config: {},
                    halt_on_failure: true,
                },
            ],
        }));
    };

    const removeNodeAction = (stepIndex: number, actionIndex: number) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_actions: (step.node_actions || []).filter((_, index) => index !== actionIndex),
        }));
    };

    const toggleCompleteProfileCondition = (stepIndex: number, enabled: boolean) => {
        updatePipelineStep(stepIndex, (step) => {
            const conditions = step.node_conditions || [];

            if (!enabled) {
                return {
                    ...step,
                    node_conditions: conditions.filter((condition) => condition.type !== "complete_student_profile"),
                };
            }

            if (conditions.some((condition) => condition.type === "complete_student_profile")) {
                return step;
            }

            return {
                ...step,
                node_conditions: [
                    ...conditions,
                    {
                        key: "complete_student_profile",
                        type: "complete_student_profile",
                        enabled: true,
                        order: conditions.length + 1,
                        config: { required_fields: profileFieldOptions.map((field) => field.value) },
                        message: "Complete the student profile before continuing.",
                    },
                ],
            };
        });
    };

    const toggleProfileRequiredField = (stepIndex: number, field: string, checked: boolean) => {
        updatePipelineStep(stepIndex, (step) => ({
            ...step,
            node_conditions: (step.node_conditions || []).map((condition) => {
                if (condition.type !== "complete_student_profile") {
                    return condition;
                }

                const fields = condition.config.required_fields || [];
                const required_fields = checked ? [...new Set([...fields, field])] : fields.filter((currentField) => currentField !== field);

                return {
                    ...condition,
                    config: {
                        ...condition.config,
                        required_fields,
                    },
                };
            }),
        }));
    };

    const addPipelineStep = () => {
        const nextIndex = pipelineForm.data.steps.length + 1;
        const step = createDefaultStep(nextIndex);
        const previousStep = pipelineForm.data.steps[pipelineForm.data.steps.length - 1];
        const steps = previousStep
            ? pipelineForm.data.steps.map((currentStep, index) =>
                  index === pipelineForm.data.steps.length - 1 ? { ...currentStep, next_step_key: step.key } : currentStep,
              )
            : pipelineForm.data.steps;
        const nextSteps = [...steps, step];
        const nodeId = getCanvasNodeId(step, nextSteps.length - 1);

        pipelineForm.setData("steps", nextSteps);
        pipelineForm.setData("completion_step_key", step.key);
        setNodePositions((current) => ({ ...current, [nodeId]: defaultCanvasPosition(nextSteps.length - 1) }));

        if (!pipelineForm.data.entry_step_key) {
            pipelineForm.setData("entry_step_key", step.key);
        }

        setSelectedStepIndex(nextSteps.length - 1);
    };

    const removePipelineStep = (index: number) => {
        const removedStep = pipelineForm.data.steps[index];
        const steps = pipelineForm.data.steps
            .filter((_, stepIndex) => stepIndex !== index)
            .map((step) => (step.next_step_key === removedStep?.key ? { ...step, next_step_key: null } : step));

        pipelineForm.setData("steps", steps);

        if (removedStep?.key === pipelineForm.data.entry_step_key) {
            pipelineForm.setData("entry_step_key", steps[0]?.key || "");
        }

        if (removedStep?.key === pipelineForm.data.completion_step_key) {
            pipelineForm.setData("completion_step_key", steps[steps.length - 1]?.key || "");
        }

        if (selectedStepIndex === index) {
            setSelectedStepIndex(steps.length > 0 ? Math.max(0, index - 1) : null);
        } else if (selectedStepIndex !== null && selectedStepIndex > index) {
            setSelectedStepIndex(selectedStepIndex - 1);
        }
    };

    const movePipelineStep = (index: number, direction: "up" | "down") => {
        const targetIndex = direction === "up" ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= pipelineForm.data.steps.length) {
            return;
        }

        const reordered = [...pipelineForm.data.steps];
        const [moved] = reordered.splice(index, 1);
        reordered.splice(targetIndex, 0, moved);
        pipelineForm.setData("steps", reordered);

        if (selectedStepIndex === index) {
            setSelectedStepIndex(targetIndex);
        } else if (selectedStepIndex === targetIndex) {
            setSelectedStepIndex(index);
        }
    };

    const handleCanvasDragEnd = (event: DragEndEvent) => {
        const { active, delta } = event;
        const id = String(active.id);
        const currentPosition = nodePositions[id] ?? defaultCanvasPosition(canvasNodes.find((node) => node.id === id)?.index ?? 0);

        setNodePositions((current) => ({
            ...current,
            [id]: {
                x: Math.max(16, currentPosition.x + delta.x),
                y: Math.max(16, currentPosition.y + delta.y),
            },
        }));
    };

    const connectStepToNext = (sourceIndex: number, targetIndex: number) => {
        if (sourceIndex === targetIndex) {
            return;
        }

        const targetStep = pipelineForm.data.steps[targetIndex];
        if (!targetStep) {
            return;
        }

        pipelineForm.setData(
            "steps",
            pipelineForm.data.steps.map((step, index) => (index === sourceIndex ? { ...step, next_step_key: targetStep.key } : step)),
        );
        setConnectingFromIndex(null);
    };

    const beginVisualConnection = (sourceIndex: number) => {
        setSelectedStepIndex(sourceIndex);
        setConnectingFromIndex(sourceIndex);
    };

    const completeVisualConnection = (targetIndex: number) => {
        if (connectingFromIndex === null || connectingFromIndex === targetIndex) {
            return;
        }

        connectStepToNext(connectingFromIndex, targetIndex);
    };

    const disconnectStep = (sourceIndex: number) => {
        pipelineForm.setData(
            "steps",
            pipelineForm.data.steps.map((step, index) => (index === sourceIndex ? { ...step, next_step_key: null } : step)),
        );
        setConnectingFromIndex((current) => (current === sourceIndex ? null : current));
    };

    const findConnectionTarget = (point: CanvasNodePosition, sourceIndex: number) => {
        const portHitArea = 28;

        return canvasNodes.find(
            (node) =>
                node.index !== sourceIndex &&
                point.x >= node.position.x - portHitArea &&
                point.x <= node.position.x + canvasNodeWidth + portHitArea &&
                point.y >= node.position.y - portHitArea &&
                point.y <= node.position.y + canvasNodeHeight + portHitArea,
        );
    };

    const startVisualConnectionDrag = (sourceIndex: number, event: ReactPointerEvent<HTMLButtonElement>) => {
        event.stopPropagation();

        const pointerStart = getCanvasPoint(event.clientX, event.clientY);
        const sourceNode = canvasNodes.find((node) => node.index === sourceIndex);
        const sourcePoint = sourceNode
            ? {
                  x: sourceNode.position.x + canvasNodeWidth,
                  y: sourceNode.position.y + canvasNodeHeight / 2,
              }
            : pointerStart;
        let hasMoved = false;

        const handlePointerMove = (pointerEvent: PointerEvent) => {
            const currentPoint = getCanvasPoint(pointerEvent.clientX, pointerEvent.clientY);
            hasMoved = hasMoved || Math.abs(currentPoint.x - pointerStart.x) > 4 || Math.abs(currentPoint.y - pointerStart.y) > 4;

            setDragConnection({
                sourceIndex,
                currentPoint,
            });
        };

        const handlePointerUp = (pointerEvent: PointerEvent) => {
            document.removeEventListener("pointermove", handlePointerMove);
            document.removeEventListener("pointerup", handlePointerUp);

            const targetNode = findConnectionTarget(getCanvasPoint(pointerEvent.clientX, pointerEvent.clientY), sourceIndex);

            if (targetNode) {
                connectStepToNext(sourceIndex, targetNode.index);
            } else if (hasMoved) {
                setConnectingFromIndex(null);
            }

            setDragConnection(null);
        };

        setSelectedStepIndex(sourceIndex);
        setConnectingFromIndex(sourceIndex);
        setDragConnection({
            sourceIndex,
            currentPoint: sourcePoint,
        });

        document.addEventListener("pointermove", handlePointerMove);
        document.addEventListener("pointerup", handlePointerUp);
    };

    const organizeCanvas = () => {
        const visited = new Set<string>();
        const orderedIndexes: number[] = [];
        let currentStep: EnrollmentPipelineStep | undefined =
            pipelineForm.data.steps.find((step) => step.key === pipelineForm.data.entry_step_key) ?? pipelineForm.data.steps[0];

        while (currentStep && !visited.has(currentStep.key)) {
            const currentIndex = pipelineForm.data.steps.findIndex((step) => step.key === currentStep?.key);
            if (currentIndex === -1) {
                break;
            }

            visited.add(currentStep.key);
            orderedIndexes.push(currentIndex);
            currentStep = pipelineForm.data.steps.find((step) => step.key === currentStep?.next_step_key);
        }

        pipelineForm.data.steps.forEach((step, index) => {
            if (!visited.has(step.key)) {
                orderedIndexes.push(index);
            }
        });

        setNodePositions(
            Object.fromEntries(
                orderedIndexes.map((stepIndex, visualIndex) => [
                    getCanvasNodeId(pipelineForm.data.steps[stepIndex], stepIndex),
                    defaultCanvasPosition(visualIndex),
                ]),
            ),
        );
    };

    const duplicatePipelineStep = (index: number) => {
        const step = pipelineForm.data.steps[index];
        if (!step) {
            return;
        }

        const nextIndex = pipelineForm.data.steps.length + 1;
        const duplicatedStep: EnrollmentPipelineStep = {
            ...step,
            key: `${step.key}_copy_${nextIndex}`,
            status: `${step.status}_copy_${nextIndex}`,
            label: `${step.label} Copy`,
            node_actions: (step.node_actions || []).map((action) => ({ ...action, config: { ...action.config } })),
            node_conditions: (step.node_conditions || []).map((condition) => ({
                ...condition,
                config: { ...condition.config, required_fields: [...(condition.config.required_fields || [])] },
            })),
            next_step_key: null,
        };
        const nextSteps = [...pipelineForm.data.steps, duplicatedStep];

        pipelineForm.setData("steps", nextSteps);
        setNodePositions((current) => ({
            ...current,
            [getCanvasNodeId(duplicatedStep, nextSteps.length - 1)]: defaultCanvasPosition(nextSteps.length - 1),
        }));
        setSelectedStepIndex(nextSteps.length - 1);
    };

    const addRoleToStep = (index: number) => {
        const selectedRole = selectedRoleByStep[index];
        if (!selectedRole) {
            return;
        }

        const roles = pipelineForm.data.steps[index]?.allowed_roles || [];
        if (roles.includes(selectedRole)) {
            return;
        }

        setStepField(index, "allowed_roles", [...roles, selectedRole]);
        setSelectedRoleByStep((current) => ({ ...current, [index]: "" }));
    };

    const removeRoleFromStep = (index: number, roleName: string) => {
        const roles = pipelineForm.data.steps[index]?.allowed_roles || [];
        setStepField(
            index,
            "allowed_roles",
            roles.filter((role) => role !== roleName),
        );
    };

    const toggleEnrollmentCourse = (courseId: number, checked: boolean) => {
        const normalizedIds = pipelineForm.data.enrollment_courses.map((id) => Number(id));

        if (checked && !normalizedIds.includes(courseId)) {
            pipelineForm.setData("enrollment_courses", [...normalizedIds, courseId]);

            return;
        }

        if (!checked) {
            pipelineForm.setData(
                "enrollment_courses",
                normalizedIds.filter((selectedId) => selectedId !== courseId),
            );
        }
    };

    const selectAllEnrollmentCourses = () => {
        pipelineForm.setData(
            "enrollment_courses",
            (available_enrollment_courses ?? []).map((course) => course.id),
        );
    };

    const updateStatsCard = (index: number, field: keyof EnrollmentStatsCard, value: string | string[]) => {
        const cards = pipelineForm.data.enrollment_stats.cards.map((card, cardIndex) => {
            if (cardIndex !== index) {
                return card;
            }

            return {
                ...card,
                [field]: value,
            };
        });

        pipelineForm.setData("enrollment_stats", { cards });
    };

    const addStatsCard = () => {
        const nextIndex = pipelineForm.data.enrollment_stats.cards.length + 1;
        pipelineForm.setData("enrollment_stats", {
            cards: [
                ...pipelineForm.data.enrollment_stats.cards,
                {
                    key: `stat_${nextIndex}`,
                    label: `Metric ${nextIndex}`,
                    metric: "total_records",
                    statuses: [],
                    color: "blue",
                },
            ],
        });
    };

    const removeStatsCard = (index: number) => {
        pipelineForm.setData("enrollment_stats", {
            cards: pipelineForm.data.enrollment_stats.cards.filter((_, cardIndex) => cardIndex !== index),
        });
    };

    const toggleStatsCardStatus = (index: number, statusValue: string) => {
        const statuses = pipelineForm.data.enrollment_stats.cards[index]?.statuses || [];
        const nextStatuses = statuses.includes(statusValue) ? statuses.filter((status) => status !== statusValue) : [...statuses, statusValue];

        updateStatsCard(index, "statuses", nextStatuses);
    };

    const submit = () => {
        submitSystemForm({
            form: pipelineForm,
            routeName: "administrators.system-management.enrollment-pipeline.update",
            successMessage: "Enrollment pipeline updated successfully.",
            errorMessage: "Failed to update enrollment pipeline.",
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="pipeline"
            heading="Enrollment Pipeline"
            description="Create the approval path new applicants follow, and choose what each approval step executes."
        >
            <div className="space-y-6">
                <Card className="border-primary/15 bg-primary/5 overflow-hidden shadow-none">
                    <CardContent className="flex flex-col gap-5 p-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="space-y-2">
                            <div className="text-primary flex items-center gap-2 text-sm font-medium">
                                <Sparkles className="h-4 w-4" /> Guided setup
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold tracking-tight">Build a focused enrollment approval workflow</h2>
                                <p className="text-muted-foreground mt-1 max-w-3xl text-sm">
                                    Existing status codes are kept intact. New steps can be added, reordered, and assigned an action without changing
                                    the rest of enrollment management.
                                </p>
                            </div>
                        </div>
                        <Button onClick={submit} disabled={pipelineForm.processing} size="lg" className="shrink-0 shadow-sm">
                            {pipelineForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save Pipeline
                        </Button>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <PipelineSummaryCard icon={Workflow} label="Workflow steps" value={pipelineForm.data.steps.length.toString()} />
                    <PipelineSummaryCard
                        icon={Users}
                        label="Visible courses"
                        value={selectedEnrollmentCourses.length > 0 ? selectedEnrollmentCourses.length.toString() : "All"}
                    />
                    <PipelineSummaryCard icon={CheckCircle2} label="Completion" value={completionStep?.label || "Not set"} />
                </div>

                <Tabs defaultValue="workflow" className="space-y-6">
                    <TabsList className="grid w-full max-w-3xl grid-cols-4">
                        <TabsTrigger value="workflow" className="gap-2">
                            <Workflow className="h-4 w-4" /> Workflow
                        </TabsTrigger>
                        <TabsTrigger value="basics" className="gap-2">
                            <Settings2 className="h-4 w-4" /> Basics
                        </TabsTrigger>
                        <TabsTrigger value="automation" className="gap-2">
                            <GraduationCap className="h-4 w-4" /> Automation
                        </TabsTrigger>
                        <TabsTrigger value="analytics" className="gap-2">
                            <BarChart3 className="h-4 w-4" /> Analytics
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="workflow" className="space-y-6 outline-none">
                        <div className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(380px,0.65fr)]">
                            <div className="grid gap-6 xl:col-span-2 xl:grid-cols-[minmax(0,1.35fr)_minmax(380px,0.65fr)]">
                                <Card className="shadow-none">
                                    <CardHeader>
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <Workflow className="text-primary h-5 w-5" />
                                                    <CardTitle>Approval flow</CardTitle>
                                                </div>
                                                <CardDescription>
                                                    Drag nodes around the canvas, click to edit, and connect the next step from the editor.
                                                </CardDescription>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button type="button" variant="outline" onClick={organizeCanvas} size="sm">
                                                    <Target className="mr-2 h-4 w-4" /> Organize
                                                </Button>
                                                <Button type="button" onClick={addPipelineStep} size="sm">
                                                    <Plus className="mr-2 h-4 w-4" /> Add step
                                                </Button>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="bg-muted/30 text-muted-foreground flex flex-col gap-3 rounded-lg border px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between">
                                            <div className="flex items-center gap-2">
                                                <MousePointerClick className="h-4 w-4" />
                                                Drag nodes, click to inspect, or use node ports to visually connect/disconnect the workflow.
                                            </div>
                                            {connectingFromIndex !== null && (
                                                <div className="bg-background text-foreground flex items-center gap-2 rounded-md border px-2 py-1">
                                                    <span>
                                                        Connecting from{" "}
                                                        {pipelineForm.data.steps[connectingFromIndex]?.label || `Step ${connectingFromIndex + 1}`}
                                                    </span>
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => setConnectingFromIndex(null)}>
                                                        Cancel
                                                    </Button>
                                                </div>
                                            )}
                                        </div>

                                        {pipelineForm.data.steps.length === 0 ? (
                                            <div className="rounded-xl border border-dashed p-8 text-center">
                                                <Workflow className="text-muted-foreground mx-auto mb-3 h-8 w-8" />
                                                <p className="font-medium">No approval steps yet</p>
                                                <p className="text-muted-foreground mt-1 text-sm">Add your first step to start the pipeline.</p>
                                            </div>
                                        ) : (
                                            <div
                                                ref={canvasRef}
                                                className="bg-muted/20 relative min-h-[720px] overflow-hidden rounded-2xl border p-4"
                                            >
                                                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] bg-size-[28px_28px] opacity-60" />
                                                <DndContext sensors={canvasSensors} onDragEnd={handleCanvasDragEnd}>
                                                    <svg className="pointer-events-none absolute inset-0 z-10 h-full w-full">
                                                        {canvasNodes.map((node) => {
                                                            const nextNode = canvasNodes.find(
                                                                (candidate) => candidate.step.key === node.step.next_step_key,
                                                            );
                                                            if (!nextNode) {
                                                                return null;
                                                            }

                                                            const startX = node.position.x + canvasNodeWidth;
                                                            const startY = node.position.y + canvasNodeHeight / 2;
                                                            const endX = nextNode.position.x;
                                                            const endY = nextNode.position.y + canvasNodeHeight / 2;
                                                            const controlOffset = Math.max(80, Math.abs(endX - startX) / 2);

                                                            return (
                                                                <g key={`${node.id}-${nextNode.id}`}>
                                                                    <path
                                                                        d={`M ${startX} ${startY} C ${startX + controlOffset} ${startY}, ${endX - controlOffset} ${endY}, ${endX} ${endY}`}
                                                                        fill="none"
                                                                        stroke="var(--primary)"
                                                                        strokeDasharray="6 6"
                                                                        strokeLinecap="round"
                                                                        strokeWidth="3"
                                                                        opacity="0.85"
                                                                    />
                                                                    <circle cx={endX} cy={endY} r="5" fill="var(--primary)" />
                                                                </g>
                                                            );
                                                        })}
                                                        {dragConnection &&
                                                            canvasNodes
                                                                .filter((node) => node.index === dragConnection.sourceIndex)
                                                                .map((sourceNode) => {
                                                                    const startX = sourceNode.position.x + canvasNodeWidth;
                                                                    const startY = sourceNode.position.y + canvasNodeHeight / 2;
                                                                    const endX = dragConnection.currentPoint.x;
                                                                    const endY = dragConnection.currentPoint.y;
                                                                    const controlOffset = Math.max(80, Math.abs(endX - startX) / 2);

                                                                    return (
                                                                        <path
                                                                            key={`preview-${sourceNode.id}`}
                                                                            d={`M ${startX} ${startY} C ${startX + controlOffset} ${startY}, ${endX - controlOffset} ${endY}, ${endX} ${endY}`}
                                                                            fill="none"
                                                                            stroke="var(--primary)"
                                                                            strokeLinecap="round"
                                                                            strokeWidth="3"
                                                                            opacity="0.95"
                                                                        />
                                                                    );
                                                                })}
                                                    </svg>

                                                    {canvasNodes.map((node) => {
                                                        const stepAction =
                                                            actionOptions.find((option) => option.value === node.step.action_type) ??
                                                            actionOptions[0];

                                                        return (
                                                            <WorkflowCanvasNode
                                                                key={node.id}
                                                                id={node.id}
                                                                step={node.step}
                                                                index={node.index}
                                                                position={node.position}
                                                                actionLabel={stepAction.label}
                                                                isSelected={selectedStepIndex === node.index}
                                                                isEntry={pipelineForm.data.entry_step_key === node.step.key}
                                                                isCompletion={pipelineForm.data.completion_step_key === node.step.key}
                                                                onSelect={() => {
                                                                    if (connectingFromIndex !== null && connectingFromIndex !== node.index) {
                                                                        completeVisualConnection(node.index);
                                                                        return;
                                                                    }

                                                                    setSelectedStepIndex(node.index);
                                                                }}
                                                                onDelete={() => removePipelineStep(node.index)}
                                                                onDuplicate={() => duplicatePipelineStep(node.index)}
                                                                onDisconnect={() => disconnectStep(node.index)}
                                                                onSetEntry={() => pipelineForm.setData("entry_step_key", node.step.key)}
                                                                onSetCompletion={() => pipelineForm.setData("completion_step_key", node.step.key)}
                                                                onConnect={(targetIndex) => connectStepToNext(node.index, targetIndex)}
                                                                onStartConnect={() => beginVisualConnection(node.index)}
                                                                onStartConnectDrag={(event) => startVisualConnectionDrag(node.index, event)}
                                                                onFinishConnect={() => completeVisualConnection(node.index)}
                                                                isConnectingFrom={connectingFromIndex === node.index}
                                                                isConnectionTarget={
                                                                    connectingFromIndex !== null && connectingFromIndex !== node.index
                                                                }
                                                                availableTargets={pipelineForm.data.steps.map((step, index) => ({
                                                                    index,
                                                                    label: step.label || step.status || `Step ${index + 1}`,
                                                                }))}
                                                            />
                                                        );
                                                    })}
                                                </DndContext>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                {selectedStep ? (
                                    <Card className="shadow-none xl:sticky xl:top-6 xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto">
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-4">
                                                <div>
                                                    <CardTitle>Visual inspector</CardTitle>
                                                    <CardDescription>Configure the selected node without leaving the canvas.</CardDescription>
                                                </div>
                                                <div className="flex items-center gap-1 rounded-md border p-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => selectedStepIndex !== null && duplicatePipelineStep(selectedStepIndex)}
                                                    >
                                                        <Plus className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        onClick={() => selectedStepIndex !== null && removePipelineStep(selectedStepIndex)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-5">
                                            <div className="bg-muted/20 rounded-2xl border p-4">
                                                <div className="flex items-start gap-3">
                                                    <div
                                                        className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm font-semibold text-white shadow-sm"
                                                        style={{
                                                            backgroundColor: colorHex[selectedStep.color as keyof typeof colorHex] ?? colorHex.gray,
                                                        }}
                                                    >
                                                        {Number(selectedStepIndex) + 1}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <p className="truncate font-semibold">
                                                                {selectedStep.label || `Step ${Number(selectedStepIndex) + 1}`}
                                                            </p>
                                                            {pipelineForm.data.entry_step_key === selectedStep.key && <Badge>Entry</Badge>}
                                                            {pipelineForm.data.completion_step_key === selectedStep.key && (
                                                                <Badge variant="secondary">Completion</Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-muted-foreground mt-1 truncate font-mono text-xs">{selectedStep.status}</p>
                                                        <div className="mt-3 grid gap-2 text-xs">
                                                            <div className="bg-background rounded-lg border px-3 py-2">
                                                                <span className="text-muted-foreground">Next:</span>{" "}
                                                                <span className="font-medium">{selectedNextStep?.label || "Not connected"}</span>
                                                            </div>
                                                            <div className="bg-background rounded-lg border px-3 py-2">
                                                                <span className="text-muted-foreground">Incoming:</span>{" "}
                                                                <span className="font-medium">
                                                                    {selectedIncomingSteps.length > 0
                                                                        ? selectedIncomingSteps.map((step) => step.label).join(", ")
                                                                        : "None"}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Step name</Label>
                                                    <Input
                                                        value={selectedStep.label}
                                                        onChange={(event) =>
                                                            selectedStepIndex !== null && setStepField(selectedStepIndex, "label", event.target.value)
                                                        }
                                                        placeholder="Department Review"
                                                    />
                                                    <FieldError message={formErrors[`steps.${selectedStepIndex}.label`]} />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Badge color</Label>
                                                    <Select
                                                        value={selectedStep.color}
                                                        onValueChange={(value) =>
                                                            selectedStepIndex !== null && setStepField(selectedStepIndex, "color", value)
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {colorOptions.map((color) => (
                                                                <SelectItem key={color} value={color}>
                                                                    <span className="flex items-center gap-2 capitalize">
                                                                        <span
                                                                            className="h-3 w-3 rounded-full"
                                                                            style={{ backgroundColor: colorHex[color] }}
                                                                        />
                                                                        {color}
                                                                    </span>
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Internal status code</Label>
                                                    <Input
                                                        value={selectedStep.status}
                                                        onChange={(event) =>
                                                            selectedStepIndex !== null &&
                                                            setStepField(selectedStepIndex, "status", slugify(event.target.value, "step_status"))
                                                        }
                                                        className="font-mono"
                                                        placeholder="department_review"
                                                    />
                                                    <p className="text-muted-foreground text-xs">
                                                        Avoid changing this for active statuses unless you are intentionally migrating records.
                                                    </p>
                                                    <FieldError message={formErrors[`steps.${selectedStepIndex}.status`]} />
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                <div>
                                                    <Label className="text-base font-semibold">Legacy action shortcut</Label>
                                                    <p className="text-muted-foreground mt-1 text-sm">
                                                        Pick a preset to quickly replace this node's structured actions.
                                                    </p>
                                                </div>
                                                <div className="grid gap-3">
                                                    {actionOptions.map((option) => {
                                                        const Icon = option.icon;
                                                        const selected = selectedStep.action_type === option.value;

                                                        return (
                                                            <button
                                                                key={option.value}
                                                                type="button"
                                                                onClick={() =>
                                                                    selectedStepIndex !== null && setStepAction(selectedStepIndex, option.value)
                                                                }
                                                                className={`rounded-xl border p-4 text-left transition ${
                                                                    selected
                                                                        ? "border-primary bg-primary/5 ring-primary/20 ring-2"
                                                                        : "hover:border-primary/40 hover:bg-muted/40"
                                                                }`}
                                                            >
                                                                <span className="flex gap-3">
                                                                    <span className="bg-primary/10 text-primary mt-0.5 rounded-lg p-2">
                                                                        <Icon className="h-4 w-4" />
                                                                    </span>
                                                                    <span>
                                                                        <span className="block font-medium">{option.label}</span>
                                                                        <span className="text-muted-foreground mt-1 block text-sm">
                                                                            {option.description}
                                                                        </span>
                                                                    </span>
                                                                </span>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>

                                            <div className="bg-muted/20 space-y-4 rounded-xl border p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <Label className="text-base font-semibold">Node actions</Label>
                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                            These run in order after this node's conditions pass.
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => selectedStepIndex !== null && addNodeAction(selectedStepIndex)}
                                                    >
                                                        <Plus className="mr-2 h-4 w-4" /> Add
                                                    </Button>
                                                </div>

                                                {(selectedStep.node_actions || []).length > 0 ? (
                                                    <div className="space-y-3">
                                                        {(selectedStep.node_actions || []).map((action, actionIndex) => (
                                                            <div key={`${action.type}-${actionIndex}`} className="bg-background space-y-3 rounded-lg border p-3">
                                                                <div className="flex items-start justify-between gap-3">
                                                                    <div className="grid flex-1 gap-3 sm:grid-cols-[1fr_90px]">
                                                                        <div className="space-y-2">
                                                                            <Label>Action</Label>
                                                                            <Select
                                                                                value={action.type}
                                                                                onValueChange={(value) =>
                                                                                    selectedStepIndex !== null &&
                                                                                    updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                        ...currentAction,
                                                                                        key: value,
                                                                                        type: value as EnrollmentPipelineNodeActionType,
                                                                                    }))
                                                                                }
                                                                            >
                                                                                <SelectTrigger>
                                                                                    <SelectValue />
                                                                                </SelectTrigger>
                                                                                <SelectContent>
                                                                                    {nodeActionOptions.map((option) => (
                                                                                        <SelectItem key={option.value} value={option.value}>
                                                                                            {option.label}
                                                                                        </SelectItem>
                                                                                    ))}
                                                                                </SelectContent>
                                                                            </Select>
                                                                        </div>
                                                                        <div className="space-y-2">
                                                                            <Label>Order</Label>
                                                                            <Input
                                                                                type="number"
                                                                                min={1}
                                                                                value={action.order}
                                                                                onChange={(event) =>
                                                                                    selectedStepIndex !== null &&
                                                                                    updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                        ...currentAction,
                                                                                        order: Number(event.target.value) || 1,
                                                                                    }))
                                                                                }
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                        onClick={() => selectedStepIndex !== null && removeNodeAction(selectedStepIndex, actionIndex)}
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </div>
                                                                <p className="text-muted-foreground text-xs">
                                                                    {nodeActionOptions.find((option) => option.value === action.type)?.description}
                                                                </p>
                                                                <div className="grid gap-3 sm:grid-cols-2">
                                                                    <label className="flex items-center gap-2 text-sm">
                                                                        <Switch
                                                                            checked={action.enabled ?? true}
                                                                            onCheckedChange={(enabled) =>
                                                                                selectedStepIndex !== null &&
                                                                                updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                    ...currentAction,
                                                                                    enabled,
                                                                                }))
                                                                            }
                                                                        />
                                                                        Enabled
                                                                    </label>
                                                                    <label className="flex items-center gap-2 text-sm">
                                                                        <Switch
                                                                            checked={action.halt_on_failure ?? true}
                                                                            onCheckedChange={(halt_on_failure) =>
                                                                                selectedStepIndex !== null &&
                                                                                updateNodeAction(selectedStepIndex, actionIndex, (currentAction) => ({
                                                                                    ...currentAction,
                                                                                    halt_on_failure,
                                                                                }))
                                                                            }
                                                                        />
                                                                        Stop if this fails
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-sm">
                                                        No node actions configured. Add one before saving this workflow.
                                                    </p>
                                                )}
                                            </div>

                                            <div className="bg-muted/20 space-y-4 rounded-xl border p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <Label className="text-base font-semibold">Built-in conditions</Label>
                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                            Conditions must pass before the node actions run.
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        checked={(selectedStep.node_conditions || []).some(
                                                            (condition) => condition.type === "complete_student_profile",
                                                        )}
                                                        onCheckedChange={(enabled) =>
                                                            selectedStepIndex !== null && toggleCompleteProfileCondition(selectedStepIndex, enabled)
                                                        }
                                                    />
                                                </div>

                                                {(selectedStep.node_conditions || []).some((condition) => condition.type === "complete_student_profile") ? (
                                                    <div className="bg-background space-y-3 rounded-lg border p-3">
                                                        <div>
                                                            <p className="font-medium">Require complete student profile</p>
                                                            <p className="text-muted-foreground mt-1 text-xs">
                                                                The next node will not proceed until these student fields are filled.
                                                            </p>
                                                        </div>
                                                        <div className="grid gap-2 sm:grid-cols-2">
                                                            {profileFieldOptions.map((field) => {
                                                                const condition = (selectedStep.node_conditions || []).find(
                                                                    (currentCondition) => currentCondition.type === "complete_student_profile",
                                                                );
                                                                const checked = condition?.config.required_fields?.includes(field.value) ?? false;

                                                                return (
                                                                    <label key={field.value} className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                                                        <Checkbox
                                                                            checked={checked}
                                                                            onCheckedChange={(value) =>
                                                                                selectedStepIndex !== null &&
                                                                                toggleProfileRequiredField(selectedStepIndex, field.value, value === true)
                                                                            }
                                                                        />
                                                                        {field.label}
                                                                    </label>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-sm">
                                                        No blocking conditions configured for this node.
                                                    </p>
                                                )}
                                            </div>

                                            <div className="bg-muted/20 space-y-3 rounded-xl border p-4">
                                                <div className="flex items-start gap-2">
                                                    <ArrowRight className="text-primary mt-0.5 h-4 w-4" />
                                                    <div className="flex-1 space-y-3">
                                                        <div>
                                                            <Label className="text-base font-semibold">Connect next node</Label>
                                                            <p className="text-muted-foreground mt-1 text-sm">
                                                                Choose which step comes after this node. This updates the saved approval sequence.
                                                            </p>
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Select
                                                                value=""
                                                                onValueChange={(value) =>
                                                                    selectedStepIndex !== null && connectStepToNext(selectedStepIndex, Number(value))
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Connect to another step..." />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {pipelineForm.data.steps.map((step, index) => {
                                                                        if (index === selectedStepIndex) {
                                                                            return null;
                                                                        }

                                                                        return (
                                                                            <SelectItem key={`${step.key}-${index}`} value={String(index)}>
                                                                                {step.label || step.status || `Step ${index + 1}`}
                                                                            </SelectItem>
                                                                        );
                                                                    })}
                                                                </SelectContent>
                                                            </Select>
                                                            <div className="grid gap-2 sm:grid-cols-2">
                                                                <Button
                                                                    type="button"
                                                                    variant={connectingFromIndex === selectedStepIndex ? "default" : "outline"}
                                                                    onClick={() =>
                                                                        selectedStepIndex !== null && beginVisualConnection(selectedStepIndex)
                                                                    }
                                                                >
                                                                    <ArrowRight className="mr-2 h-4 w-4" /> Draw connection
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    onClick={() => selectedStepIndex !== null && disconnectStep(selectedStepIndex)}
                                                                    disabled={!selectedStep.next_step_key}
                                                                >
                                                                    <Scissors className="mr-2 h-4 w-4" /> Disconnect
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="bg-muted/20 space-y-3 rounded-xl border p-4">
                                                <div className="flex items-start gap-2">
                                                    <LockKeyhole className="text-primary mt-0.5 h-4 w-4" />
                                                    <div>
                                                        <Label className="text-base font-semibold">Approver roles</Label>
                                                        <p className="text-muted-foreground mt-1 text-sm">
                                                            Leave empty to allow any staff member with enrollment access. Super admins always have
                                                            access.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex flex-col gap-2 sm:flex-row">
                                                    <Combobox
                                                        options={roleComboboxOptions}
                                                        value={selectedRoleByStep[Number(selectedStepIndex)] || ""}
                                                        onValueChange={(value) =>
                                                            setSelectedRoleByStep((current) => ({ ...current, [Number(selectedStepIndex)]: value }))
                                                        }
                                                        placeholder="Select role..."
                                                        searchPlaceholder="Search roles..."
                                                        emptyText="No role found."
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        onClick={() => selectedStepIndex !== null && addRoleToStep(selectedStepIndex)}
                                                        disabled={!selectedRoleByStep[Number(selectedStepIndex)]}
                                                    >
                                                        <Plus className="mr-2 h-4 w-4" /> Add role
                                                    </Button>
                                                </div>
                                                {selectedStep.allowed_roles.length > 0 ? (
                                                    <div className="flex flex-wrap gap-2">
                                                        {selectedStep.allowed_roles.map((roleName) => (
                                                            <Badge key={roleName} variant="secondary" className="gap-2">
                                                                {roleName}
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        selectedStepIndex !== null && removeRoleFromStep(selectedStepIndex, roleName)
                                                                    }
                                                                    className="text-muted-foreground hover:text-destructive"
                                                                    aria-label={`Remove ${roleName}`}
                                                                >
                                                                    <Trash2 className="h-3 w-3" />
                                                                </button>
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground bg-background rounded-md border border-dashed p-3 text-sm">
                                                        No role restrictions configured for this step.
                                                    </p>
                                                )}
                                            </div>

                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <Button
                                                    type="button"
                                                    variant={pipelineForm.data.entry_step_key === selectedStep.key ? "default" : "outline"}
                                                    onClick={() => pipelineForm.setData("entry_step_key", selectedStep.key)}
                                                >
                                                    Set as entry
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant={pipelineForm.data.completion_step_key === selectedStep.key ? "default" : "outline"}
                                                    onClick={() => pipelineForm.setData("completion_step_key", selectedStep.key)}
                                                >
                                                    Set as completion
                                                </Button>
                                            </div>

                                            <details className="rounded-lg border p-4">
                                                <summary className="cursor-pointer text-sm font-medium">Advanced: stable step ID</summary>
                                                <div className="mt-3 space-y-2">
                                                    <Label>Step ID</Label>
                                                    <Input
                                                        value={selectedStep.key}
                                                        onChange={(event) =>
                                                            selectedStepIndex !== null &&
                                                            setStepField(selectedStepIndex, "key", slugify(event.target.value, "step"))
                                                        }
                                                        className="font-mono"
                                                    />
                                                    <p className="text-muted-foreground text-xs">
                                                        This links entry/completion markers and legacy aliases. Keep it stable for existing workflows.
                                                    </p>
                                                    <FieldError message={formErrors[`steps.${selectedStepIndex}.key`]} />
                                                </div>
                                            </details>
                                        </CardContent>
                                    </Card>
                                ) : null}
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="basics" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Settings2 className="text-primary h-5 w-5" />
                                    <CardTitle>Basic enrollment settings</CardTitle>
                                </div>
                                <CardDescription>Configure only the applicant-facing entry settings when you need them.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="submitted_label_tab">Initial submitted label</Label>
                                        <Input
                                            id="submitted_label_tab"
                                            value={pipelineForm.data.submitted_label}
                                            onChange={(event) => pipelineForm.setData("submitted_label", event.target.value)}
                                            placeholder="Submitted"
                                        />
                                        <FieldError message={formErrors.submitted_label} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Entry and completion</Label>
                                        <div className="bg-muted/30 rounded-md border px-3 py-2 text-sm">
                                            <span className="font-medium">{entryStep?.label || "No entry step"}</span>
                                            <span className="text-muted-foreground"> → </span>
                                            <span className="font-medium">{completionStep?.label || "No completion step"}</span>
                                        </div>
                                        <p className="text-muted-foreground text-xs">Set these from the selected workflow node.</p>
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-3">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        <div className="space-y-1">
                                            <Label htmlFor="course_search_tab">Courses shown to new applicants</Label>
                                            <p className="text-muted-foreground text-xs">Leave empty to show every active course.</p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button type="button" variant="outline" size="sm" onClick={selectAllEnrollmentCourses}>
                                                Select all
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => pipelineForm.setData("enrollment_courses", [])}
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    </div>
                                    <Input
                                        id="course_search_tab"
                                        value={enrollmentCourseSearch}
                                        onChange={(event) => setEnrollmentCourseSearch(event.target.value)}
                                        placeholder="Search by code or course title..."
                                    />
                                    <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border p-2">
                                        {filteredEnrollmentCourses.length === 0 ? (
                                            <p className="text-muted-foreground py-6 text-center text-sm">No matching courses found.</p>
                                        ) : (
                                            filteredEnrollmentCourses.map((course) => (
                                                <label
                                                    key={course.id}
                                                    className="hover:bg-muted/60 flex cursor-pointer items-center gap-3 rounded-md px-2 py-2"
                                                >
                                                    <Checkbox
                                                        checked={pipelineForm.data.enrollment_courses.includes(course.id)}
                                                        onCheckedChange={(value) => toggleEnrollmentCourse(course.id, value === true)}
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium">{course.title}</span>
                                                        <span className="text-muted-foreground block font-mono text-xs">{course.code}</span>
                                                    </span>
                                                </label>
                                            ))
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="automation" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <GraduationCap className="text-primary h-5 w-5" />
                                    <CardTitle>Applicant automation</CardTitle>
                                </div>
                                <CardDescription>Optional actions that run when a new applicant is submitted.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <AutomationSwitch
                                    id="default_new_applicant_to_first_year_tab"
                                    title="Treat new applicants as 1st year automatically"
                                    description="Required before auto-creating enrollment records or assigning subjects."
                                    checked={pipelineForm.data.automation.default_new_applicant_to_first_year}
                                    onCheckedChange={(enabled) =>
                                        pipelineForm.setData("automation", {
                                            ...pipelineForm.data.automation,
                                            default_new_applicant_to_first_year: enabled,
                                            auto_create_student_enrollment: enabled
                                                ? pipelineForm.data.automation.auto_create_student_enrollment
                                                : false,
                                            auto_assign_subjects: enabled ? pipelineForm.data.automation.auto_assign_subjects : false,
                                        })
                                    }
                                />
                                <AutomationSwitch
                                    id="auto_create_student_enrollment_tab"
                                    title="Auto-create Student Enrollment record"
                                    description="Creates an enrollment record immediately for every new applicant."
                                    checked={pipelineForm.data.automation.auto_create_student_enrollment}
                                    disabled={!pipelineForm.data.automation.default_new_applicant_to_first_year}
                                    onCheckedChange={(enabled) =>
                                        pipelineForm.setData("automation", {
                                            ...pipelineForm.data.automation,
                                            auto_create_student_enrollment: enabled,
                                            auto_assign_subjects: enabled ? pipelineForm.data.automation.auto_assign_subjects : false,
                                        })
                                    }
                                />
                                <AutomationSwitch
                                    id="auto_assign_subjects_tab"
                                    title="Auto-assign available subjects/classes"
                                    description="Uses the applicant course and active academic period to assign available classes."
                                    checked={pipelineForm.data.automation.auto_assign_subjects}
                                    disabled={
                                        !pipelineForm.data.automation.default_new_applicant_to_first_year ||
                                        !pipelineForm.data.automation.auto_create_student_enrollment
                                    }
                                    onCheckedChange={(enabled) =>
                                        pipelineForm.setData("automation", {
                                            ...pipelineForm.data.automation,
                                            auto_assign_subjects: enabled,
                                        })
                                    }
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="analytics" className="space-y-6 outline-none">
                        <Card className="shadow-none">
                            <CardHeader>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle>Enrollment analytics cards</CardTitle>
                                        <CardDescription>Configure the summary cards shown in enrollment dashboards.</CardDescription>
                                    </div>
                                    <Button type="button" onClick={addStatsCard}>
                                        <Plus className="mr-2 h-4 w-4" /> Add card
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {pipelineForm.data.enrollment_stats.cards.length === 0 ? (
                                    <div className="rounded-xl border border-dashed p-10 text-center">
                                        <BarChart3 className="text-muted-foreground mx-auto mb-3 h-9 w-9" />
                                        <p className="font-medium">No analytics cards configured</p>
                                        <p className="text-muted-foreground mt-1 text-sm">Add a card to show totals or counts by pipeline status.</p>
                                    </div>
                                ) : (
                                    <div className="grid gap-4 lg:grid-cols-2">
                                        {pipelineForm.data.enrollment_stats.cards.map((card, index) => (
                                            <Card key={`${card.key}-${index}`} className="shadow-none">
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="flex-1 space-y-2">
                                                            <Input
                                                                value={card.label}
                                                                onChange={(event) => updateStatsCard(index, "label", event.target.value)}
                                                                placeholder="Card title"
                                                                className="text-base font-semibold"
                                                            />
                                                            <FieldError message={formErrors[`enrollment_stats.cards.${index}.label`]} />
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            onClick={() => removeStatsCard(index)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </CardHeader>
                                                <CardContent className="space-y-4">
                                                    <div className="grid gap-4 sm:grid-cols-3">
                                                        <div className="space-y-2 sm:col-span-2">
                                                            <Label>Metric</Label>
                                                            <Select
                                                                value={card.metric}
                                                                onValueChange={(value) => updateStatsCard(index, "metric", value)}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {statsMetricOptions.map((metric) => (
                                                                        <SelectItem key={metric.value} value={metric.value}>
                                                                            {metric.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <Label>Color</Label>
                                                            <Select
                                                                value={card.color}
                                                                onValueChange={(value) => updateStatsCard(index, "color", value)}
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {colorOptions.map((color) => (
                                                                        <SelectItem key={color} value={color} className="capitalize">
                                                                            {color}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Card key</Label>
                                                        <Input
                                                            value={card.key}
                                                            onChange={(event) =>
                                                                updateStatsCard(index, "key", slugify(event.target.value, `stat_${index + 1}`))
                                                            }
                                                            className="font-mono"
                                                        />
                                                    </div>
                                                    {card.metric === "status_count" && (
                                                        <div className="space-y-2">
                                                            <Label>Count these statuses</Label>
                                                            <div className="flex flex-wrap gap-2">
                                                                {pipelineForm.data.steps.map((step, stepIndex) => {
                                                                    const selected = card.statuses.includes(step.status);

                                                                    return (
                                                                        <button
                                                                            key={`${step.status}-${stepIndex}`}
                                                                            type="button"
                                                                            onClick={() => toggleStatsCardStatus(index, step.status)}
                                                                            className={`rounded-md border px-3 py-1.5 text-xs transition ${
                                                                                selected
                                                                                    ? "border-primary bg-primary text-primary-foreground"
                                                                                    : "bg-background hover:bg-muted"
                                                                            }`}
                                                                        >
                                                                            {step.label || step.status}
                                                                        </button>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </SystemManagementLayout>
    );
}

function WorkflowCanvasNode({
    id,
    step,
    index,
    position,
    actionLabel,
    isSelected,
    isEntry,
    isCompletion,
    onSelect,
    onDelete,
    onDuplicate,
    onDisconnect,
    onSetEntry,
    onSetCompletion,
    onConnect,
    onStartConnect,
    onStartConnectDrag,
    onFinishConnect,
    isConnectingFrom,
    isConnectionTarget,
    availableTargets,
}: {
    id: string;
    step: EnrollmentPipelineStep;
    index: number;
    position: CanvasNodePosition;
    actionLabel: string;
    isSelected: boolean;
    isEntry: boolean;
    isCompletion: boolean;
    onSelect: () => void;
    onDelete: () => void;
    onDuplicate: () => void;
    onDisconnect: () => void;
    onSetEntry: () => void;
    onSetCompletion: () => void;
    onConnect: (targetIndex: number) => void;
    onStartConnect: () => void;
    onStartConnectDrag: (event: ReactPointerEvent<HTMLButtonElement>) => void;
    onFinishConnect: () => void;
    isConnectingFrom: boolean;
    isConnectionTarget: boolean;
    availableTargets: Array<{ index: number; label: string }>;
}) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id });
    const style = {
        left: position.x,
        top: position.y,
        width: canvasNodeWidth,
        transform: transform ? `translate3d(${transform.x}px, ${transform.y}px, 0)` : undefined,
        zIndex: isDragging ? 30 : isSelected ? 25 : 20,
    };

    const nodeButton = (
        <div
            role="button"
            tabIndex={0}
            onClick={onSelect}
            onKeyDown={(event) => {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    onSelect();
                }
            }}
            className={`group bg-card relative min-h-28 w-full rounded-2xl border p-4 text-left shadow-sm transition ${
                isDragging
                    ? "border-primary scale-[1.01] opacity-90 shadow-xl"
                    : isSelected
                      ? "border-primary bg-primary/5 ring-primary/20 ring-2"
                      : "hover:border-primary/40 hover:bg-background hover:shadow-md"
            }`}
        >
            <button
                type="button"
                onClick={(event) => {
                    event.stopPropagation();
                    onFinishConnect();
                }}
                disabled={!isConnectionTarget}
                className={`border-background absolute top-1/2 left-0 h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 shadow-sm transition ${
                    isConnectionTarget ? "bg-primary ring-primary/20 ring-4" : "bg-muted-foreground/60"
                }`}
                aria-label="Connect incoming workflow line here"
            />
            <button
                type="button"
                onClick={(event) => {
                    event.stopPropagation();
                    onStartConnect();
                }}
                onPointerDown={onStartConnectDrag}
                className={`border-background absolute top-1/2 right-0 h-6 w-6 translate-x-1/2 -translate-y-1/2 cursor-crosshair rounded-full border-2 shadow-sm transition ${
                    isConnectingFrom ? "bg-primary ring-primary/30 ring-4" : "bg-primary hover:ring-primary/20 hover:ring-4"
                }`}
                aria-label="Start workflow connection from this node"
            />

            <span className="flex items-start gap-3">
                <span
                    className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-semibold text-white shadow-sm"
                    style={{ backgroundColor: colorHex[step.color as keyof typeof colorHex] ?? colorHex.gray }}
                >
                    {index + 1}
                </span>
                <span className="min-w-0 flex-1 space-y-1.5">
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{step.label || `Step ${index + 1}`}</span>
                        {isEntry && <Badge>Entry</Badge>}
                        {isCompletion && <Badge variant="secondary">Completion</Badge>}
                    </span>
                    <span className="text-muted-foreground block truncate font-mono text-xs">{step.status || "missing_status"}</span>
                    <span className="text-muted-foreground block text-xs">{actionLabel}</span>
                </span>
                <span
                    {...attributes}
                    {...listeners}
                    onClick={(event) => event.stopPropagation()}
                    className="bg-background text-muted-foreground hover:text-foreground mt-0.5 inline-flex cursor-grab touch-none rounded-lg border p-2 transition active:cursor-grabbing"
                    aria-label={`Drag ${step.label || `Step ${index + 1}`}`}
                >
                    <GripVertical className="h-4 w-4" />
                </span>
            </span>
        </div>
    );

    return (
        <div ref={setNodeRef} style={style} className="absolute">
            <ContextMenu>
                <ContextMenuTrigger asChild>{nodeButton}</ContextMenuTrigger>
                <ContextMenuContent className="w-56">
                    <ContextMenuLabel>{step.label || `Step ${index + 1}`}</ContextMenuLabel>
                    <ContextMenuSeparator />
                    <ContextMenuItem onSelect={onSelect}>
                        <Pencil className="h-4 w-4" /> Edit logic
                    </ContextMenuItem>
                    <ContextMenuItem onSelect={onDuplicate}>
                        <Plus className="h-4 w-4" /> Duplicate node
                    </ContextMenuItem>
                    <ContextMenuSub>
                        <ContextMenuSubTrigger>
                            <ArrowRight className="h-4 w-4" /> Connect to
                        </ContextMenuSubTrigger>
                        <ContextMenuSubContent className="w-56">
                            {availableTargets
                                .filter((target) => target.index !== index)
                                .map((target) => (
                                    <ContextMenuItem key={target.index} onSelect={() => onConnect(target.index)}>
                                        {target.label}
                                    </ContextMenuItem>
                                ))}
                        </ContextMenuSubContent>
                    </ContextMenuSub>
                    <ContextMenuItem onSelect={onDisconnect} disabled={!step.next_step_key}>
                        <Scissors className="h-4 w-4" /> Disconnect outgoing line
                    </ContextMenuItem>
                    <ContextMenuSeparator />
                    <ContextMenuItem onSelect={onSetEntry}>Set as entry</ContextMenuItem>
                    <ContextMenuItem onSelect={onSetCompletion}>Set as completion</ContextMenuItem>
                    <ContextMenuSeparator />
                    <ContextMenuItem variant="destructive" onSelect={onDelete}>
                        <Trash2 className="h-4 w-4" /> Delete node
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
        </div>
    );
}

function PipelineSummaryCard({ icon: Icon, label, value }: { icon: typeof Workflow; label: string; value: string }) {
    return (
        <Card className="shadow-none">
            <CardContent className="flex items-center gap-3 p-4">
                <div className="bg-primary/10 text-primary rounded-lg p-2">
                    <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</p>
                    <p className="truncate text-lg font-semibold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function AutomationSwitch({
    id,
    title,
    description,
    checked,
    disabled = false,
    onCheckedChange,
}: {
    id: string;
    title: string;
    description: string;
    checked: boolean;
    disabled?: boolean;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border p-3">
            <Switch id={id} checked={checked} disabled={disabled} onCheckedChange={(value) => onCheckedChange(value === true)} />
            <div className="space-y-1">
                <Label htmlFor={id} className="cursor-pointer font-medium">
                    {title}
                </Label>
                <p className="text-muted-foreground text-xs">{description}</p>
            </div>
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="text-destructive text-xs">{message}</p>;
}
