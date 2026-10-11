import type { EmailCopyTarget } from "@/lib/email-template-canvas";
import { cn } from "@/lib/utils";
import { useDraggable, useDroppable } from "@dnd-kit/core";
import { GripVertical, Plus, TextCursorInput } from "lucide-react";
import { useId, type ReactNode } from "react";

export const EMAIL_TOKEN_LABELS: Record<string, string> = {
    student_name: "Student name",
    status: "Enrollment status",
    step_name: "Workflow stage",
    school_year: "School year",
    semester: "Semester",
    program: "Course / program",
    portal_url: "Student portal",
    organization_name: "Organization",
};

export function ParagraphPaletteItem({ disabled, onAdd }: { disabled: boolean; onAdd: () => void }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: "new-email-paragraph",
        data: { kind: "new-paragraph" },
        disabled,
    });

    return (
        <button
            ref={setNodeRef}
            type="button"
            disabled={disabled}
            onClick={onAdd}
            {...attributes}
            {...listeners}
            aria-label="Add a paragraph. Drag into the message or click to add."
            className={cn(
                "border-border bg-background hover:border-primary/60 focus-visible:ring-ring flex w-full touch-none items-center gap-3 rounded-xl border p-3 text-left shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50",
                isDragging && "opacity-40",
            )}
        >
            <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg">
                <TextCursorInput className="size-5" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">Paragraph</span>
                <span className="text-muted-foreground mt-0.5 block text-xs">Drag in or click to add</span>
            </span>
            <Plus className="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
        </button>
    );
}

export function TokenPaletteItem({
    name,
    sample,
    disabled,
    onInsert,
}: {
    name: string;
    sample: string;
    disabled: boolean;
    onInsert: (token: string) => void;
}) {
    const token = `{{ ${name} }}`;
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: `email-token-${name}`,
        data: { kind: "token", token, label: EMAIL_TOKEN_LABELS[name] ?? name },
        disabled,
    });

    return (
        <button
            ref={setNodeRef}
            type="button"
            disabled={disabled}
            {...attributes}
            {...listeners}
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => onInsert(token)}
            aria-label={`Insert ${EMAIL_TOKEN_LABELS[name] ?? name} token`}
            title={`Sample: ${sample}`}
            className={cn(
                "border-border/60 bg-background hover:border-primary/50 hover:bg-primary/5 focus-visible:ring-ring flex w-full touch-none items-center gap-2 rounded-lg border px-2.5 py-2 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50",
                isDragging && "opacity-40",
            )}
        >
            <GripVertical className="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />
            <span className="min-w-0">
                <span className="block text-xs font-medium">{EMAIL_TOKEN_LABELS[name] ?? name}</span>
                <span className="text-muted-foreground block truncate font-mono text-[11px]">{token}</span>
            </span>
        </button>
    );
}

export function CopyDropTarget({
    target,
    disabled,
    children,
    className,
}: {
    target: EmailCopyTarget;
    disabled: boolean;
    children: ReactNode;
    className?: string;
}) {
    const id = useId();
    const { setNodeRef, isOver, active } = useDroppable({
        id: `email-copy-${id}`,
        data: { kind: "copy", target },
        disabled,
    });

    return (
        <div
            ref={setNodeRef}
            data-email-drop-target={target}
            className={cn(className, isOver && active?.data.current?.kind === "token" && "rounded-lg ring-2 ring-blue-500 ring-offset-2")}
        >
            {children}
        </div>
    );
}
