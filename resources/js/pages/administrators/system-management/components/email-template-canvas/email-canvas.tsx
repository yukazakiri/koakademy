import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import {
    interpolateEmailCopy,
    introFromParagraphs,
    type EmailCopyTarget,
    type EmailEditorSelection,
    type EmailParagraph,
    type EmailTemplateDefinition,
    type EmailTemplateDraft,
    type EmailTemplateEditorData,
} from "@/lib/email-template-canvas";
import { cn } from "@/lib/utils";
import { useDroppable } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { ArrowDown, ArrowUp, Copy, GripVertical, LockKeyhole, Paperclip, Plus, Trash2 } from "lucide-react";
import { useReducedMotion } from "motion/react";
import { useEffect, useRef, type CSSProperties, type ReactNode } from "react";
import { CopyDropTarget } from "./drag-items";

interface EmailCanvasProps {
    draft: EmailTemplateDraft;
    definition: EmailTemplateDefinition;
    data: EmailTemplateEditorData;
    selection: EmailEditorSelection;
    device: "desktop" | "mobile";
    disabled: boolean;
    draggingKind: string | null;
    onSelect: (selection: EmailEditorSelection) => void;
    onAdd: () => void;
    onChangeParagraph: (id: string, text: string) => void;
    onMove: (id: string, direction: number) => void;
    onDuplicate: (id: string) => void;
    onRemove: (id: string) => void;
    onCopyFocus: (target: EmailCopyTarget, input: HTMLInputElement | HTMLTextAreaElement) => void;
}

export function EmailCanvas({
    draft,
    definition,
    data,
    selection,
    device,
    disabled,
    draggingKind,
    onSelect,
    onAdd,
    onChangeParagraph,
    onMove,
    onDuplicate,
    onRemove,
    onCopyFocus,
}: EmailCanvasProps) {
    const { setNodeRef, isOver } = useDroppable({ id: "email-intro", data: { kind: "intro" }, disabled });
    const { branding, sample_tokens: tokens } = data;
    const isStatus = draft.notification === "status_update" || draft.notification === "custom";
    const isAcademic = draft.notification === "academic_verified";
    const isAssessment = draft.notification === "assessment";
    const headline = definition.capabilities.headline ? draft.headline.trim() || definition.defaults.headline : definition.defaults.headline;
    const buttonLabel = definition.capabilities.button_label
        ? draft.button_label.trim() || definition.defaults.button_label
        : definition.defaults.button_label;
    const hasIntroCopy = introFromParagraphs(draft.paragraphs) !== "";
    const themeStyle = { "--email-brand": branding.theme_color || "#0f172a" } as CSSProperties;

    return (
        <div className="mx-auto w-full max-w-[650px]" data-email-device={device}>
            <div className="mb-5 text-center text-sm font-semibold text-slate-600">{branding.organization_name}</div>
            <article
                aria-label="Editable email canvas"
                style={themeStyle}
                className={cn(
                    "mx-auto w-full bg-white [font-family:Arial,Helvetica,sans-serif] text-[#3d4852] shadow-[0_4px_24px_rgba(15,23,42,0.08)]",
                    device === "desktop" ? "max-w-[570px]" : "max-w-[375px]",
                )}
            >
                <header className={cn("relative border-b-2 border-[var(--email-brand)] px-6 py-6 text-center", isAssessment && "bg-slate-50")}>
                    <span className="absolute top-2 right-2 inline-flex items-center gap-1 text-[10px] text-slate-500">
                        <LockKeyhole className="size-3" aria-hidden="true" /> Brand
                    </span>
                    {branding.logo_url && <img src={branding.logo_url} alt="" className="mx-auto mb-3 h-14 max-w-[180px] object-contain" />}
                    <h2 className="text-xl font-bold text-[var(--email-brand)]">{branding.organization_name}</h2>
                    {branding.tagline && <p className="mt-1 text-xs text-slate-500">{branding.tagline}</p>}
                </header>

                <div className="space-y-5 px-6 py-7 sm:px-8">
                    {isAssessment && <p className="text-xs font-semibold tracking-[0.12em] text-slate-500 uppercase">Enrollment confirmation</p>}
                    <CopyDropTarget target="headline" disabled={disabled || !definition.capabilities.headline}>
                        {definition.capabilities.headline ? (
                            <button
                                type="button"
                                onClick={() => onSelect("headline")}
                                aria-label="Edit email headline"
                                aria-pressed={selection === "headline"}
                                disabled={disabled}
                                className={cn(
                                    "w-full rounded-md text-left text-xl font-bold text-slate-800 transition-colors outline-none hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-blue-500 disabled:opacity-100",
                                    selection === "headline" && !disabled && "bg-blue-50 ring-2 ring-blue-500 ring-offset-4",
                                )}
                            >
                                {interpolateEmailCopy(headline, tokens)}
                            </button>
                        ) : (
                            <div className="flex items-start justify-between gap-3">
                                <h3 className="text-xl font-bold text-slate-800">{interpolateEmailCopy(headline, tokens)}</h3>
                                <LockKeyhole className="mt-1 size-3.5 shrink-0 text-slate-400" aria-label="System-managed heading" />
                            </div>
                        )}
                    </CopyDropTarget>

                    <p className="text-sm leading-6">
                        Dear {tokens.student_name}, <LockKeyhole className="ml-1 inline size-3 text-slate-400" aria-label="System-managed greeting" />
                    </p>

                    <section
                        ref={setNodeRef}
                        aria-label="Editable introduction paragraphs"
                        data-email-intro
                        className={cn(
                            "relative min-h-12 rounded-lg",
                            isOver && draggingKind === "new-paragraph" && "bg-blue-50 ring-2 ring-blue-500 ring-offset-4",
                        )}
                    >
                        <SortableContext items={draft.paragraphs.map((paragraph) => paragraph.id)} strategy={verticalListSortingStrategy}>
                            <div className="space-y-2">
                                {draft.paragraphs.map((paragraph, index) => (
                                    <CanvasParagraph
                                        key={paragraph.id}
                                        paragraph={paragraph}
                                        index={index}
                                        count={draft.paragraphs.length}
                                        tokens={tokens}
                                        selected={selection === `paragraph:${paragraph.id}`}
                                        disabled={disabled}
                                        onSelect={() => onSelect(`paragraph:${paragraph.id}`)}
                                        onChange={(text) => onChangeParagraph(paragraph.id, text)}
                                        onMove={(direction) => onMove(paragraph.id, direction)}
                                        onDuplicate={() => onDuplicate(paragraph.id)}
                                        onRemove={() => onRemove(paragraph.id)}
                                        onCopyFocus={onCopyFocus}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                        {!hasIntroCopy && (
                            <div className="space-y-2 py-2">
                                <p className="text-sm leading-6 whitespace-pre-line">
                                    {interpolateEmailCopy(definition.defaults.custom_body, tokens)}
                                </p>
                                <p className="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                    No intro override. The built-in copy will be sent.
                                </p>
                            </div>
                        )}
                        {hasIntroCopy && !draft.introCustomized && (
                            <p className="mt-2 text-xs leading-5 text-slate-500">
                                Built-in intro. Editing the copy creates a policy-specific override.
                            </p>
                        )}
                        {!disabled && (
                            <button
                                type="button"
                                onClick={onAdd}
                                className={cn(
                                    "mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500 transition-colors hover:border-blue-500 hover:bg-blue-50 hover:text-blue-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none",
                                    draggingKind === "new-paragraph" && "border-blue-400 bg-blue-50 text-blue-700",
                                )}
                            >
                                <Plus className="size-3.5" aria-hidden="true" />
                                {draggingKind === "new-paragraph" ? "Drop your paragraph here" : "Add a paragraph"}
                            </button>
                        )}
                    </section>

                    {isStatus && (
                        <SystemSection label="Status & stage">
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                                <p className="font-semibold">{tokens.status}</p>
                                <p className="mt-1 text-slate-500">Stage: {tokens.step_name}</p>
                            </div>
                        </SystemSection>
                    )}

                    {isStatus && draft.include_details && (
                        <SystemSection label="Application details">
                            <SampleTable
                                rows={[
                                    ["Status", tokens.status],
                                    ["Stage", tokens.step_name],
                                    ["School year", tokens.school_year],
                                    ["Semester", tokens.semester],
                                    ["Program", tokens.program],
                                ]}
                            />
                        </SystemSection>
                    )}

                    {(isAcademic || (isStatus && draft.include_subjects)) && (
                        <SystemSection label="Approved subjects">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-200 text-slate-500">
                                        <th className="py-2 pr-2 font-semibold" scope="col">
                                            Code
                                        </th>
                                        <th className="py-2 pr-2 font-semibold" scope="col">
                                            Title
                                        </th>
                                        <th className="py-2 text-right font-semibold" scope="col">
                                            Units
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {[
                                        ["CS101", "Introduction to Computing", "3"],
                                        ["MATH101", "College Algebra", "3"],
                                    ].map(([code, name, units]) => (
                                        <tr key={code} className="border-b border-slate-100">
                                            <td className="py-2 pr-2">{code}</td>
                                            <td className="py-2 pr-2">{name}</td>
                                            <td className="py-2 text-right tabular-nums">{units}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </SystemSection>
                    )}

                    {(isAcademic || (isStatus && draft.include_tuition)) && (
                        <SystemSection label="Tuition & payment">
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <SampleTable
                                    rows={[
                                        ["Total tuition", "₱15,000.00"],
                                        ["Downpayment", "₱3,000.00"],
                                    ]}
                                />
                                <p className="mt-3 text-xs leading-5 text-slate-500">
                                    Amounts and payment instructions are generated from the student&apos;s assessment.
                                </p>
                            </div>
                        </SystemSection>
                    )}

                    {isStatus && (!draft.introCustomized || !hasIntroCopy) && (
                        <SystemSection label="What this means for you">
                            <p className="text-sm leading-6 text-slate-500">
                                The system includes next-step instructions for {tokens.status}. Recipient preview shows the exact wording.
                            </p>
                        </SystemSection>
                    )}

                    {isAssessment && (
                        <>
                            <SystemSection label="Academic term">
                                <SampleTable
                                    rows={[
                                        ["School year", tokens.school_year],
                                        ["Semester", tokens.semester],
                                    ]}
                                />
                            </SystemSection>
                            <SystemSection label="Next steps & student account">
                                <div className="space-y-3 text-sm leading-6">
                                    <p>Your enrollment is confirmed. Review your Certificate of Matriculation and keep a copy for your records.</p>
                                    <p className="text-slate-500">
                                        This sample shows a student who already has a portal account. Account instructions and PDF availability are
                                        determined when the email is sent.
                                    </p>
                                </div>
                            </SystemSection>
                        </>
                    )}

                    <div className="py-2 text-center">
                        <button
                            type="button"
                            onClick={() => onSelect("button")}
                            disabled={disabled || !definition.capabilities.button_label}
                            aria-label={definition.capabilities.button_label ? "Edit action button" : "System-managed portal action"}
                            aria-pressed={definition.capabilities.button_label && selection === "button"}
                            className={cn(
                                "inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-[#2d3748] px-6 py-3 text-sm font-semibold text-white outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-4 disabled:opacity-100",
                                selection === "button" && !disabled && definition.capabilities.button_label && "ring-2 ring-blue-500 ring-offset-4",
                            )}
                        >
                            {buttonLabel}
                            {!definition.capabilities.button_label && <LockKeyhole className="size-3.5" aria-hidden="true" />}
                        </button>
                    </div>

                    <div className="space-y-1 text-sm leading-6">
                        <p>Regards,</p>
                        <p>{branding.organization_name}</p>
                    </div>
                    {(branding.support_email || branding.support_phone) && (
                        <p className="border-t border-slate-200 pt-4 text-xs leading-5 text-slate-500">
                            Need help? {[branding.support_email, branding.support_phone].filter(Boolean).join(" · ")}
                        </p>
                    )}
                </div>
            </article>

            {isAssessment && (
                <div className="mx-auto mt-4 flex max-w-[570px] items-center gap-3 rounded-lg border border-slate-300 bg-white p-3 text-slate-700">
                    <Paperclip className="size-5 shrink-0 text-slate-500" aria-hidden="true" />
                    <div>
                        <p className="text-xs font-semibold">Certificate of Matriculation.pdf</p>
                        <p className="mt-0.5 text-xs text-slate-500">Representative attachment · generated by the system</p>
                    </div>
                    <LockKeyhole className="ml-auto size-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                </div>
            )}
            <p className="mt-5 text-center text-xs leading-5 text-slate-500">
                Canvas guide with sample data. Use Recipient preview for the actual email rendering.
            </p>
        </div>
    );
}

function CanvasParagraph({
    paragraph,
    index,
    count,
    tokens,
    selected,
    disabled,
    onSelect,
    onChange,
    onMove,
    onDuplicate,
    onRemove,
    onCopyFocus,
}: {
    paragraph: EmailParagraph;
    index: number;
    count: number;
    tokens: Record<string, string>;
    selected: boolean;
    disabled: boolean;
    onSelect: () => void;
    onChange: (text: string) => void;
    onMove: (direction: number) => void;
    onDuplicate: () => void;
    onRemove: () => void;
    onCopyFocus: EmailCanvasProps["onCopyFocus"];
}) {
    const inputRef = useRef<HTMLTextAreaElement>(null);
    const reducedMotion = useReducedMotion();
    const target: EmailCopyTarget = `paragraph:${paragraph.id}`;
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging, isOver, active } = useSortable({
        id: paragraph.id,
        data: { kind: "paragraph", target },
        disabled,
    });
    const style = { transform: CSS.Transform.toString(transform), transition: reducedMotion ? "none" : transition };

    useEffect(() => {
        const input = inputRef.current;
        if (!input) return;
        input.style.height = "0px";
        input.style.height = `${Math.max(input.scrollHeight, 56)}px`;
    }, [paragraph.text, selected]);

    return (
        <article
            ref={setNodeRef}
            style={style}
            data-email-paragraph={paragraph.id}
            className={cn(
                "group/paragraph relative rounded-lg border border-transparent",
                !disabled && "hover:border-blue-200",
                selected && !disabled && "border-blue-500 bg-blue-50/40 ring-1 ring-blue-500",
                isDragging && "opacity-30",
                isOver && active?.data.current?.kind === "token" && "bg-blue-50 ring-2 ring-blue-500",
            )}
        >
            {!disabled && (
                <div className={cn(selected ? "flex flex-wrap items-center gap-0.5 border-b border-blue-100 px-1 py-1" : "absolute top-0 -left-8")}>
                    <button
                        ref={setActivatorNodeRef}
                        type="button"
                        {...attributes}
                        {...listeners}
                        aria-label={`Drag paragraph ${index + 1} to reorder`}
                        className={cn(
                            "flex touch-none items-center justify-center rounded-md text-slate-500 outline-none hover:bg-blue-100 focus-visible:ring-2 focus-visible:ring-blue-500",
                            selected ? "size-10" : "h-10 w-8",
                        )}
                    >
                        <GripVertical className="size-4" aria-hidden="true" />
                    </button>
                    {selected && (
                        <>
                            <span className="mr-auto text-xs font-medium text-blue-800">Paragraph {index + 1}</span>
                            <ParagraphAction label={`Move paragraph ${index + 1} up`} disabled={index === 0} onClick={() => onMove(-1)}>
                                <ArrowUp className="size-4" />
                            </ParagraphAction>
                            <ParagraphAction label={`Move paragraph ${index + 1} down`} disabled={index === count - 1} onClick={() => onMove(1)}>
                                <ArrowDown className="size-4" />
                            </ParagraphAction>
                            <ParagraphAction label={`Duplicate paragraph ${index + 1}`} onClick={onDuplicate}>
                                <Copy className="size-4" />
                            </ParagraphAction>
                            <ParagraphAction label={`Remove paragraph ${index + 1}`} onClick={onRemove}>
                                <Trash2 className="size-4" />
                            </ParagraphAction>
                        </>
                    )}
                </div>
            )}
            {selected && !disabled ? (
                <Textarea
                    ref={inputRef}
                    value={paragraph.text}
                    placeholder="Write your paragraph…"
                    aria-label={`Paragraph ${index + 1} text`}
                    data-email-copy-target={target}
                    onChange={(event) => onChange(event.target.value)}
                    onFocus={(event) => onCopyFocus(target, event.currentTarget)}
                    onSelect={(event) => onCopyFocus(target, event.currentTarget)}
                    rows={2}
                    className="min-h-14 resize-none rounded-none border-0 bg-transparent p-3 text-sm leading-6 text-slate-700 shadow-none focus-visible:ring-0 md:text-sm dark:bg-transparent"
                />
            ) : (
                <button
                    type="button"
                    onClick={onSelect}
                    disabled={disabled}
                    aria-label={`Edit paragraph ${index + 1}`}
                    aria-pressed={selected}
                    className="min-h-12 w-full rounded-md p-2 text-left text-sm leading-6 whitespace-pre-line outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:opacity-100"
                >
                    {interpolateEmailCopy(paragraph.text, tokens) || <span className="text-slate-400 italic">Empty paragraph — click to write</span>}
                </button>
            )}
        </article>
    );
}

function ParagraphAction({
    label,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            className="size-10 p-0 text-slate-500 hover:bg-blue-100 hover:text-slate-800"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function SystemSection({ label, children }: { label: string; children: ReactNode }) {
    return (
        <section className="space-y-2" aria-label={`${label}, system-managed`}>
            <div className="flex items-center justify-between gap-2">
                <h4 className="text-xs font-semibold text-slate-700">{label}</h4>
                <span className="inline-flex items-center gap-1 text-[10px] text-slate-500">
                    <LockKeyhole className="size-3" aria-hidden="true" /> System-managed
                </span>
            </div>
            {children}
        </section>
    );
}

function SampleTable({ rows }: { rows: string[][] }) {
    return (
        <table className="w-full text-left text-xs">
            <tbody>
                {rows.map(([label, value]) => (
                    <tr key={label} className="border-b border-slate-200">
                        <th scope="row" className="w-1/3 py-2.5 pr-3 font-semibold text-slate-500">
                            {label}
                        </th>
                        <td className="py-2.5 text-slate-700 tabular-nums">{value}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
