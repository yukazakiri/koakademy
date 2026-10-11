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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
    canonicalEmailTemplateKey,
    configurationFromDraft,
    createEmailDraft,
    emailDraftHistoryReducer,
    insertEmailCopyToken,
    interpolateEmailCopy,
    introFromParagraphs,
    isSamePortalEmailPath,
    moveEmailParagraph,
    replaceEmailParagraphs,
    type EmailCopyTarget,
    type EmailEditorSelection,
    type EmailTemplateConfiguration,
    type EmailTemplateDefinition,
    type EmailTemplateDraft,
    type EmailTemplateEditorData,
} from "@/lib/email-template-canvas";
import { cn } from "@/lib/utils";
import {
    closestCenter,
    defaultKeyboardCoordinateGetter,
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    pointerWithin,
    rectIntersection,
    useSensor,
    useSensors,
    type CollisionDetection,
    type DragEndEvent,
    type DragStartEvent,
    type KeyboardCoordinateGetter,
} from "@dnd-kit/core";
import { sortableKeyboardCoordinates } from "@dnd-kit/sortable";
import {
    ArrowLeft,
    Check,
    ChevronRight,
    CircleAlert,
    Eye,
    GripVertical,
    Laptop,
    LayoutTemplate,
    LoaderCircle,
    LockKeyhole,
    Mail,
    Redo2,
    RotateCcw,
    Save,
    ShieldCheck,
    Smartphone,
    Sparkles,
    TextCursorInput,
    Undo2,
} from "lucide-react";
import { useEffect, useId, useMemo, useReducer, useRef, useState } from "react";
import { CopyDropTarget, ParagraphPaletteItem, TokenPaletteItem } from "./drag-items";
import { EmailCanvas } from "./email-canvas";
import { EmailProperties } from "./email-properties";
import { useEmailTemplatePreview } from "./use-email-template-preview";

interface EmailTemplateEditorProps {
    configuration: Record<string, unknown>;
    data: EmailTemplateEditorData;
    stepKey: string;
    stepLabel: string;
    terminal: boolean;
    policyName: string;
    previewUrl: string;
    onSave: (configuration: EmailTemplateConfiguration) => void;
    onCancel: () => void;
    saving?: boolean;
    readOnly?: boolean;
    errors?: Record<string, string>;
}

export function EmailTemplateEditor(props: EmailTemplateEditorProps) {
    const initialKey =
        canonicalEmailTemplateKey(props.configuration.notification) ??
        canonicalEmailTemplateKey(props.stepKey) ??
        (props.terminal ? "assessment" : "status_update");
    const initialDefinition = props.data.definitions.find((definition) => definition.key === initialKey) ?? props.data.definitions[0];

    return initialDefinition ? (
        <EmailTemplateWorkspace {...props} initialDefinition={initialDefinition} />
    ) : (
        <p className="text-destructive p-6" role="alert">
            The email catalog could not be loaded. Reload this page before editing.
        </p>
    );
}

function EmailTemplateWorkspace({
    configuration,
    data,
    stepKey,
    stepLabel,
    policyName,
    previewUrl,
    onSave,
    onCancel,
    saving = false,
    readOnly = false,
    errors = {},
    initialDefinition,
}: EmailTemplateEditorProps & { initialDefinition: EmailTemplateDefinition }) {
    const dndId = useId();
    const workspaceRef = useRef<HTMLDivElement>(null);
    const [initialDraft] = useState(() => createEmailDraft(configuration, initialDefinition));
    const [history, dispatch] = useReducer(emailDraftHistoryReducer, { past: [], present: initialDraft, future: [] });
    const draft = history.present;
    const nextParagraphId = useRef(initialDraft.paragraphs.length + 1);
    const lastCopyInput = useRef<{ target: EmailCopyTarget; input: HTMLInputElement | HTMLTextAreaElement } | null>(null);
    const [selection, setSelection] = useState<EmailEditorSelection>("subject");
    const [device, setDevice] = useState<"desktop" | "mobile">("desktop");
    const [mode, setMode] = useState<"canvas" | "preview">("canvas");
    const [libraryOpen, setLibraryOpen] = useState(false);
    const [dragging, setDragging] = useState<{ kind: string; label: string } | null>(null);
    const [announcement, setAnnouncement] = useState("");
    const [discardDialog, setDiscardDialog] = useState(false);
    const definition = data.definitions.find((item) => item.key === draft.notification) ?? initialDefinition;
    const payload = useMemo(() => configurationFromDraft(draft), [draft]);
    const dirty = JSON.stringify(payload) !== JSON.stringify(configurationFromDraft(initialDraft));
    const canEdit = !readOnly && !saving;
    const invalidUrl = definition.capabilities.button_url && !isSamePortalEmailPath(payload.button_url);
    const bodyLength = Array.from(introFromParagraphs(draft.paragraphs) || definition.defaults.custom_body).length;
    const bodyTooLong = bodyLength > 5000;
    const visibleErrors = {
        ...errors,
        ...(bodyTooLong ? { "configuration.custom_body": "Keep the introduction to 5,000 characters or fewer." } : {}),
    };
    const preview = useEmailTemplatePreview(previewUrl, stepKey, payload, mode === "preview" && !invalidUrl && !bodyTooLong);
    const tokens: Record<string, string> = {
        ...data.sample_tokens,
        portal_url:
            definition.capabilities.button_url && payload.button_url !== "" && !invalidUrl
                ? `${data.sample_tokens.portal_url.replace(/\/$/, "")}${payload.button_url}`
                : data.sample_tokens.portal_url,
    };
    const canvasData = { ...data, sample_tokens: tokens };
    const previewSubject = interpolateEmailCopy(payload.subject || definition.defaults.subject, tokens);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: emailKeyboardCoordinates }),
    );

    useEffect(() => {
        if (!dirty || saving || readOnly) return;
        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = "";
        };
        window.addEventListener("beforeunload", beforeUnload);
        return () => window.removeEventListener("beforeunload", beforeUnload);
    }, [dirty, saving, readOnly]);

    const change = (update: (current: EmailTemplateDraft) => EmailTemplateDraft) => {
        if (canEdit) dispatch({ type: "change", update });
    };

    const selectContent = (target: EmailEditorSelection) => {
        setSelection(target);
        if (lastCopyInput.current?.target !== target) lastCopyInput.current = null;
        if (target === "button" || !canEdit) return;
        window.requestAnimationFrame(() => {
            const input = Array.from(
                workspaceRef.current?.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>("[data-email-copy-target]") ?? [],
            ).find((element) => element.dataset.emailCopyTarget === target);
            if (input && document.activeElement !== input) {
                input.focus();
                input.scrollIntoView({ block: "nearest", inline: "nearest" });
            }
        });
    };

    const focusParagraph = (id: string) => {
        selectContent(`paragraph:${id}`);
    };

    const addParagraph = (index = draft.paragraphs.length, text = "") => {
        if (!canEdit) return;
        const paragraph = { id: `paragraph-${nextParagraphId.current++}`, text };
        change((current) => {
            const paragraphs = [...current.paragraphs];
            paragraphs.splice(Math.max(0, Math.min(index, paragraphs.length)), 0, paragraph);
            return replaceEmailParagraphs(current, paragraphs);
        });
        focusParagraph(paragraph.id);
        setAnnouncement("Paragraph added. Type directly into the selected block.");
    };

    const changeParagraph = (id: string, text: string) =>
        change((current) =>
            replaceEmailParagraphs(
                current,
                current.paragraphs.map((paragraph) => (paragraph.id === id ? { ...paragraph, text } : paragraph)),
            ),
        );

    const moveParagraph = (id: string, direction: number) => {
        const index = draft.paragraphs.findIndex((paragraph) => paragraph.id === id);
        change((current) => replaceEmailParagraphs(current, moveEmailParagraph(current.paragraphs, id, index + direction)));
        setAnnouncement(`Paragraph moved to position ${index + direction + 1}.`);
    };

    const removeParagraph = (id: string) => {
        if (!canEdit) return;
        const index = draft.paragraphs.findIndex((paragraph) => paragraph.id === id);
        const next = draft.paragraphs[index + 1] ?? draft.paragraphs[index - 1];
        change((current) =>
            replaceEmailParagraphs(
                current,
                current.paragraphs.filter((paragraph) => paragraph.id !== id),
            ),
        );
        setSelection(next ? `paragraph:${next.id}` : "subject");
        lastCopyInput.current = null;
        setAnnouncement("Paragraph removed. Undo restores it.");
    };

    const insertToken = (token: string, requestedTarget?: EmailCopyTarget) => {
        if (!canEdit) return;
        let target = requestedTarget ?? lastCopyInput.current?.target ?? (selection === "button" ? undefined : selection);
        if (target?.startsWith("paragraph:") && !draft.paragraphs.some((paragraph) => target === `paragraph:${paragraph.id}`)) target = undefined;
        if (target === "headline" && !definition.capabilities.headline) target = undefined;
        if (!target) {
            const paragraph = draft.paragraphs.at(-1);
            if (!paragraph) {
                addParagraph(0, token);
                return;
            }
            target = `paragraph:${paragraph.id}`;
        }

        const selectedTarget = target;
        const text = target.startsWith("paragraph:")
            ? (draft.paragraphs.find((paragraph) => `paragraph:${paragraph.id}` === target)?.text ?? "")
            : draft[target as "subject" | "headline"];
        const input = lastCopyInput.current?.target === target && lastCopyInput.current.input.isConnected ? lastCopyInput.current.input : null;
        const insertion = insertEmailCopyToken(text, token, input?.selectionStart ?? text.length, input?.selectionEnd ?? text.length);
        change((current) =>
            selectedTarget.startsWith("paragraph:")
                ? replaceEmailParagraphs(
                      current,
                      current.paragraphs.map((paragraph) =>
                          `paragraph:${paragraph.id}` === selectedTarget ? { ...paragraph, text: insertion.text } : paragraph,
                      ),
                  )
                : { ...current, [selectedTarget]: insertion.text },
        );
        setSelection(selectedTarget);
        if (input) {
            window.requestAnimationFrame(() => {
                input.focus();
                input.setSelectionRange(insertion.caret, insertion.caret);
            });
        }
        setAnnouncement(`${token} inserted. Its value is supplied when the email is sent.`);
    };

    const applyDefinition = (key: string) => {
        if (!canEdit) return;
        const next = data.definitions.find((item) => item.key === key);
        if (!next) return;
        const presetDraft = createEmailDraft({}, next);
        presetDraft.paragraphs = presetDraft.paragraphs.map((paragraph) => ({ ...paragraph, id: `paragraph-${nextParagraphId.current++}` }));
        change(() => presetDraft);
        setSelection("subject");
        lastCopyInput.current = null;
        setAnnouncement("Built-in defaults loaded. Undo restores your previous copy.");
    };

    const handleDragStart = ({ active }: DragStartEvent) => {
        const metadata = active.data.current;
        const kind = String(metadata?.kind ?? "");
        setDragging({
            kind,
            label: kind === "new-paragraph" ? "New paragraph" : kind === "token" ? String(metadata?.token ?? "") : "Move paragraph",
        });
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        setDragging(null);
        if (!canEdit || !over) return;
        const kind = active.data.current?.kind;
        const destination = over.data.current;
        if (kind === "paragraph" && destination?.kind === "paragraph") {
            if (active.id === over.id) return;
            const index = draft.paragraphs.findIndex((paragraph) => paragraph.id === over.id);
            change((current) => replaceEmailParagraphs(current, moveEmailParagraph(current.paragraphs, String(active.id), index)));
            setSelection(`paragraph:${active.id}`);
            setAnnouncement(`Paragraph moved to position ${index + 1}.`);
        } else if (kind === "new-paragraph" && (destination?.kind === "paragraph" || destination?.kind === "intro")) {
            const targetIndex = draft.paragraphs.findIndex((paragraph) => paragraph.id === over.id);
            const activeRect = active.rect.current.translated;
            const after = activeRect && activeRect.top + activeRect.height / 2 > over.rect.top + over.rect.height / 2;
            addParagraph(targetIndex === -1 ? draft.paragraphs.length : targetIndex + (after ? 1 : 0));
        } else if (kind === "token") {
            const token = String(active.data.current?.token ?? "");
            if (destination?.kind === "copy" || destination?.kind === "paragraph") {
                insertToken(token, destination.target as EmailCopyTarget);
            } else if (destination?.kind === "intro") {
                const lastParagraph = draft.paragraphs.at(-1);
                if (lastParagraph) insertToken(token, `paragraph:${lastParagraph.id}`);
                else addParagraph(0, token);
            }
        }
    };

    const requestCancel = () => {
        if (dirty && !saving) setDiscardDialog(true);
        else onCancel();
    };
    const onCopyFocus = (target: EmailCopyTarget, input: HTMLInputElement | HTMLTextAreaElement) => {
        lastCopyInput.current = { target, input };
    };
    const selectedParagraphIndex = draft.paragraphs.findIndex((paragraph) => selection === `paragraph:${paragraph.id}`);
    const targetLabel =
        selectedParagraphIndex >= 0
            ? `Paragraph ${selectedParagraphIndex + 1}`
            : selection === "headline"
              ? "Headline"
              : selection === "subject"
                ? "Subject"
                : "Introduction";

    return (
        <DndContext
            id={dndId}
            sensors={sensors}
            collisionDetection={emailCollisionDetection}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => {
                setDragging(null);
                setAnnouncement("Drag cancelled. Nothing changed.");
            }}
            accessibility={{
                screenReaderInstructions: {
                    draggable:
                        "Press Space to pick up a block, arrow keys to move, and Space to drop. Press Escape to cancel. Add, insert, and move buttons are also available without dragging.",
                },
            }}
        >
            <div ref={workspaceRef} className="bg-background @container/emailstudio flex h-full min-h-0 flex-col" data-email-template-editor>
                <header className="border-border bg-card flex shrink-0 flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button
                            type="button"
                            variant="ghost"
                            className="size-10 p-0"
                            onClick={requestCancel}
                            disabled={saving}
                            aria-label="Back to email templates"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                        </Button>
                        <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                            <Mail className="size-5" aria-hidden="true" />
                        </span>
                        <div className="min-w-0">
                            <p className="text-muted-foreground flex items-center gap-1 text-[11px]">
                                <span className="max-w-44 truncate">{policyName}</span>
                                <ChevronRight className="size-3 shrink-0" aria-hidden="true" />
                                <span>Policy draft</span>
                            </p>
                            <h1 className="mt-0.5 truncate text-base font-semibold tracking-tight">{stepLabel}</h1>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={draft.notification} onValueChange={applyDefinition} disabled={!canEdit}>
                            <SelectTrigger aria-label="Email template preset" className="h-10 w-[220px] max-w-full text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {data.definitions.map((item) => (
                                    <SelectItem key={item.key} value={item.key}>
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 gap-1.5 text-xs"
                            disabled={!canEdit}
                            onClick={() => applyDefinition(draft.notification)}
                        >
                            <RotateCcw className="size-3.5" aria-hidden="true" /> Reset
                        </Button>
                    </div>
                </header>

                <div className="border-border bg-card/70 flex shrink-0 flex-wrap items-center justify-between gap-2 border-b px-4 py-2 sm:px-5">
                    <div className="flex items-center gap-1">
                        <EditorModeButton active={mode === "canvas"} onClick={() => setMode("canvas")}>
                            <LayoutTemplate className="size-4" aria-hidden="true" /> Design canvas
                        </EditorModeButton>
                        <EditorModeButton active={mode === "preview"} onClick={() => setMode("preview")}>
                            <Eye className="size-4" aria-hidden="true" /> Recipient preview
                        </EditorModeButton>
                    </div>
                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            className="size-10 p-0"
                            aria-label="Undo email edit"
                            title="Undo"
                            disabled={!canEdit || history.past.length === 0}
                            onClick={() => {
                                dispatch({ type: "undo" });
                                lastCopyInput.current = null;
                                setAnnouncement("Edit undone.");
                            }}
                        >
                            <Undo2 className="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="size-10 p-0"
                            aria-label="Redo email edit"
                            title="Redo"
                            disabled={!canEdit || history.future.length === 0}
                            onClick={() => {
                                dispatch({ type: "redo" });
                                lastCopyInput.current = null;
                                setAnnouncement("Edit restored.");
                            }}
                        >
                            <Redo2 className="size-4" aria-hidden="true" />
                        </Button>
                        <span className="bg-border mx-1 h-5 w-px" aria-hidden="true" />
                        <Button
                            type="button"
                            variant={device === "desktop" ? "secondary" : "ghost"}
                            className="size-10 p-0"
                            aria-label="Desktop email preview"
                            aria-pressed={device === "desktop"}
                            title="Desktop"
                            onClick={() => setDevice("desktop")}
                        >
                            <Laptop className="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant={device === "mobile" ? "secondary" : "ghost"}
                            className="size-10 p-0"
                            aria-label="Mobile email preview"
                            aria-pressed={device === "mobile"}
                            title="Mobile"
                            onClick={() => setDevice("mobile")}
                        >
                            <Smartphone className="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </div>

                <div className="grid min-h-0 flex-1 grid-cols-1 overflow-y-auto @[42rem]/emailstudio:grid-cols-[13rem_minmax(0,1fr)] @[64rem]/emailstudio:grid-cols-[13rem_minmax(0,1fr)_17rem] @[64rem]/emailstudio:overflow-hidden">
                    <aside
                        className="border-border bg-card border-b p-4 @[42rem]/emailstudio:border-r @[42rem]/emailstudio:border-b-0 @[64rem]/emailstudio:overflow-y-auto"
                        aria-label="Email block library"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 w-full justify-between text-xs @[42rem]/emailstudio:hidden"
                            onClick={() => setLibraryOpen((current) => !current)}
                            aria-expanded={libraryOpen}
                            aria-controls={`${dndId}-library-content`}
                        >
                            <span className="flex items-center gap-2">
                                <TextCursorInput className="size-4" aria-hidden="true" /> Blocks & personalization
                            </span>
                            <ChevronRight className={cn("size-4", libraryOpen && "rotate-90")} aria-hidden="true" />
                        </Button>
                        <div
                            id={`${dndId}-library-content`}
                            className={cn("space-y-6 pt-4 @[42rem]/emailstudio:pt-0", !libraryOpen && "hidden @[42rem]/emailstudio:block")}
                        >
                            <section className="space-y-3">
                                <div>
                                    <h2 className="text-sm font-semibold">Build your message</h2>
                                    <p className="text-muted-foreground mt-1 text-xs leading-5">Arrange intro paragraphs inside the email.</p>
                                </div>
                                <ParagraphPaletteItem disabled={!canEdit || mode !== "canvas"} onAdd={() => addParagraph()} />
                                <p className="text-muted-foreground text-xs leading-5">
                                    Drag the grip to reorder. Move up / down buttons work without dragging.
                                </p>
                            </section>
                            <section className="border-border space-y-3 border-t pt-5">
                                <h3 className="flex items-center gap-1.5 text-xs font-semibold">
                                    <Sparkles className="text-primary size-3.5" aria-hidden="true" /> Personalization
                                </h3>
                                <p className="text-muted-foreground text-xs leading-5">Drop a token into copy, or click to insert at your cursor.</p>
                                <div className="bg-muted/60 flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px]">
                                    <span className="text-muted-foreground">Insert into</span>
                                    <span className="font-medium">{targetLabel}</span>
                                </div>
                                <div className="space-y-1.5">
                                    {Object.entries(data.sample_tokens).map(([name, sample]) => (
                                        <TokenPaletteItem key={name} name={name} sample={sample} disabled={!canEdit} onInsert={insertToken} />
                                    ))}
                                </div>
                            </section>
                            <section className="border-border space-y-2 border-t pt-5">
                                <h3 className="flex items-center gap-1.5 text-xs font-semibold">
                                    <ShieldCheck className="size-3.5" aria-hidden="true" /> Protected by design
                                </h3>
                                <p className="text-muted-foreground text-xs leading-5">
                                    The brand header, greeting, student data, and attachments are not draggable content.
                                </p>
                                <p className="text-muted-foreground text-xs leading-5">{definition.description}</p>
                            </section>
                        </div>
                    </aside>

                    <main
                        className="flex min-h-[36rem] min-w-0 flex-col bg-[#f2f4f6] @[64rem]/emailstudio:min-h-0 @[64rem]/emailstudio:overflow-hidden"
                        aria-label="Email workspace"
                    >
                        <CopyDropTarget
                            target="subject"
                            disabled={!canEdit || mode !== "canvas"}
                            className="shrink-0 border-b border-slate-200 bg-white"
                        >
                            <button
                                type="button"
                                className="w-full px-4 py-4 text-left outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-inset sm:px-6"
                                disabled={!canEdit || mode !== "canvas"}
                                onClick={() => selectContent("subject")}
                                aria-label="Edit email subject on canvas"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">Inbox</span>
                                    <span className="text-xs text-slate-500">Sample message</span>
                                </div>
                                <h2 className="mt-2 text-base leading-6 font-semibold break-words text-slate-800">
                                    {mode === "preview" && preview.result && !preview.loading ? preview.result.subject : previewSubject}
                                </h2>
                                <p className="mt-2 text-xs text-slate-500">
                                    To {tokens.student_name} <span className="text-slate-400">· student@example.test</span>
                                </p>
                            </button>
                        </CopyDropTarget>
                        <div className="min-h-0 flex-1 overflow-y-auto px-3 py-6 sm:px-6" data-email-canvas-scroll>
                            {mode === "canvas" ? (
                                <EmailCanvas
                                    draft={draft}
                                    definition={definition}
                                    data={canvasData}
                                    selection={selection}
                                    device={device}
                                    disabled={!canEdit}
                                    draggingKind={dragging?.kind ?? null}
                                    onSelect={selectContent}
                                    onAdd={() => addParagraph()}
                                    onChangeParagraph={changeParagraph}
                                    onMove={moveParagraph}
                                    onDuplicate={(id) => {
                                        const index = draft.paragraphs.findIndex((paragraph) => paragraph.id === id);
                                        if (index >= 0) addParagraph(index + 1, draft.paragraphs[index].text);
                                    }}
                                    onRemove={removeParagraph}
                                    onCopyFocus={onCopyFocus}
                                />
                            ) : (
                                <div
                                    className={cn("mx-auto w-full", device === "desktop" ? "max-w-[650px]" : "max-w-[375px]")}
                                    data-email-device={device}
                                >
                                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                                        <span className="flex items-center gap-1.5">
                                            <Eye className="size-3.5" aria-hidden="true" /> Actual email renderer
                                        </span>
                                        <span role="status">
                                            {preview.loading ? "Rendering sample…" : preview.result && !preview.error ? "Preview up to date" : ""}
                                        </span>
                                    </div>
                                    {invalidUrl || bodyTooLong || preview.error ? (
                                        <div
                                            role="alert"
                                            className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800"
                                        >
                                            <CircleAlert className="mt-1 size-4 shrink-0" aria-hidden="true" />
                                            <p>
                                                {invalidUrl
                                                    ? "Fix the action URL before previewing. Only same-portal paths are allowed."
                                                    : bodyTooLong
                                                      ? "Shorten the introduction to 5,000 characters or fewer to render a preview."
                                                      : preview.error}
                                            </p>
                                        </div>
                                    ) : preview.result ? (
                                        <div className="relative">
                                            <iframe
                                                title="Recipient email preview"
                                                sandbox=""
                                                referrerPolicy="no-referrer"
                                                srcDoc={preview.result.html}
                                                className={cn(
                                                    "h-[640px] w-full rounded-lg border-0 bg-white shadow-sm",
                                                    preview.loading && "opacity-50",
                                                )}
                                                aria-busy={preview.loading}
                                            />
                                            {preview.loading && (
                                                <div className="absolute inset-0 flex items-start justify-center rounded-lg bg-white/30 pt-10">
                                                    <span className="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs text-slate-600 shadow-sm">
                                                        <LoaderCircle className="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />{" "}
                                                        Updating preview
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div
                                            className="flex h-80 items-center justify-center gap-2 rounded-lg bg-white text-sm text-slate-500"
                                            role="status"
                                        >
                                            <LoaderCircle className="size-5 animate-spin motion-reduce:animate-none" aria-hidden="true" /> Rendering
                                            the email…
                                        </div>
                                    )}
                                    <p className="mt-4 text-xs leading-5 text-slate-600">
                                        Sample student, subjects, and fees only. Assessment previews show an existing portal account and a
                                        representative PDF-present state; no email or file is sent.
                                    </p>
                                </div>
                            )}
                        </div>
                    </main>

                    <EmailProperties
                        draft={draft}
                        definition={definition}
                        selection={selection}
                        disabled={!canEdit}
                        errors={visibleErrors}
                        onChange={change}
                        onSelect={selectContent}
                        onChangeParagraph={changeParagraph}
                        onAdd={() => addParagraph()}
                        onCopyFocus={onCopyFocus}
                    />
                </div>

                <footer className="border-border bg-card flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-4 py-3 sm:px-5">
                    <div className="min-w-0">
                        <p className="flex items-center gap-1.5 text-xs font-medium">
                            {readOnly ? (
                                <>
                                    <LockKeyhole className="size-3.5" aria-hidden="true" /> Read-only template
                                </>
                            ) : dirty ? (
                                <>
                                    <span className="size-1.5 rounded-full bg-amber-500" aria-hidden="true" /> Unsaved changes
                                </>
                            ) : (
                                <>
                                    <Check className="size-3.5 text-emerald-600" aria-hidden="true" /> No unsaved changes
                                </>
                            )}
                        </p>
                        <p className="text-muted-foreground mt-1 text-[11px]">
                            Saved to this policy&apos;s draft. Publish the policy to use changes for future enrollments.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={cn("mr-1 text-[11px] tabular-nums", bodyTooLong ? "text-destructive" : "text-muted-foreground")}>
                            {bodyLength.toLocaleString()} / 5,000
                        </span>
                        <Button type="button" variant="outline" className="h-10 text-xs" onClick={requestCancel} disabled={saving}>
                            {readOnly ? "Back to templates" : "Discard & back"}
                        </Button>
                        <Button
                            type="button"
                            className="h-10 gap-2 text-xs"
                            onClick={() => onSave(payload)}
                            disabled={!canEdit || invalidUrl || bodyTooLong}
                        >
                            {saving ? (
                                <LoaderCircle className="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
                            ) : (
                                <Save className="size-4" aria-hidden="true" />
                            )}
                            {saving ? "Saving…" : "Save template"}
                        </Button>
                    </div>
                </footer>
                <p className="sr-only" role="status" aria-live="polite">
                    {announcement}
                </p>
            </div>

            <DragOverlay dropAnimation={null}>
                {dragging && (
                    <div
                        data-email-drag-overlay
                        className="border-primary/30 bg-card text-foreground flex max-w-64 items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium shadow-xl"
                    >
                        <GripVertical className="text-primary size-4" aria-hidden="true" />
                        {dragging.label}
                    </div>
                )}
            </DragOverlay>
            <AlertDialog open={discardDialog} onOpenChange={setDiscardDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Discard unsaved email changes?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Your saved policy draft will not change. Leave the editor without saving these changes?
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Keep editing</AlertDialogCancel>
                        <AlertDialogAction onClick={onCancel}>Discard & leave</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </DndContext>
    );
}

const emailKeyboardCoordinates: KeyboardCoordinateGetter = (event, args) => {
    if (args.context.active?.data.current?.kind !== "paragraph") return defaultKeyboardCoordinateGetter(event, args);

    // Nested token targets must not intercept arrow-key paragraph navigation.
    const droppableRects = new Map(
        Array.from(args.context.droppableRects).filter(([id]) => args.context.droppableContainers.get(id)?.data.current?.kind === "paragraph"),
    );

    return sortableKeyboardCoordinates(event, { ...args, context: { ...args.context, droppableRects } });
};

const emailCollisionDetection: CollisionDetection = (args) => {
    const kind = args.active.data.current?.kind;
    const containers = args.droppableContainers.filter((container) => {
        const destination = container.data.current?.kind;
        if (kind === "paragraph") return destination === "paragraph" && (!args.pointerCoordinates || container.id !== args.active.id);
        if (kind === "new-paragraph") return destination === "paragraph" || destination === "intro";
        return destination === "copy" || destination === "paragraph" || destination === "intro";
    });
    const filtered = { ...args, droppableContainers: containers };
    if (!args.pointerCoordinates) return kind === "paragraph" ? closestCenter(filtered) : rectIntersection(filtered);
    const collisions = pointerWithin(filtered);
    const priority = (id: string | number) => {
        const destination = containers.find((container) => container.id === id)?.data.current?.kind;
        return destination === "copy" ? 0 : destination === "paragraph" ? 1 : 2;
    };
    return collisions.sort((a, b) => priority(a.id) - priority(b.id));
};

function EditorModeButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
    return (
        <Button type="button" variant={active ? "secondary" : "ghost"} className="h-10 gap-1.5 text-xs" aria-pressed={active} onClick={onClick}>
            {children}
        </Button>
    );
}
