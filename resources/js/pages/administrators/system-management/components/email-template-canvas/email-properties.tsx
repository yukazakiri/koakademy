import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import {
    isSamePortalEmailPath,
    type EmailCopyTarget,
    type EmailEditorSelection,
    type EmailTemplateDefinition,
    type EmailTemplateDraft,
} from "@/lib/email-template-canvas";
import { cn } from "@/lib/utils";
import { LockKeyhole, Plus, SlidersHorizontal, TextCursorInput } from "lucide-react";
import { useId } from "react";
import { CopyDropTarget } from "./drag-items";

export function EmailProperties({
    draft,
    definition,
    selection,
    disabled,
    errors,
    onChange,
    onSelect,
    onChangeParagraph,
    onAdd,
    onCopyFocus,
}: {
    draft: EmailTemplateDraft;
    definition: EmailTemplateDefinition;
    selection: EmailEditorSelection;
    disabled: boolean;
    errors: Record<string, string>;
    onChange: (update: (draft: EmailTemplateDraft) => EmailTemplateDraft) => void;
    onSelect: (selection: EmailEditorSelection) => void;
    onChangeParagraph: (id: string, text: string) => void;
    onAdd: () => void;
    onCopyFocus: (target: EmailCopyTarget, input: HTMLInputElement | HTMLTextAreaElement) => void;
}) {
    const id = useId();
    const paragraphId = selection.startsWith("paragraph:") ? selection.slice("paragraph:".length) : null;
    const paragraphIndex = draft.paragraphs.findIndex((paragraph) => paragraph.id === paragraphId);
    const paragraph = draft.paragraphs[paragraphIndex];
    const urlError =
        errors["configuration.button_url"] ||
        (!isSamePortalEmailPath(draft.button_url) ? "Use a same-portal path such as /student. External URLs are not allowed." : "");

    return (
        <aside
            className="border-border bg-card min-w-0 border-t p-4 @[42rem]/emailstudio:col-span-2 @[64rem]/emailstudio:col-span-1 @[64rem]/emailstudio:overflow-y-auto @[64rem]/emailstudio:border-t-0 @[64rem]/emailstudio:border-l"
            aria-label="Email content properties"
        >
            <div className="mb-5 flex items-center gap-2">
                <SlidersHorizontal className="text-muted-foreground size-4" aria-hidden="true" />
                <h3 className="text-sm font-semibold">Content properties</h3>
            </div>

            <div className="space-y-5">
                <CopyDropTarget target="subject" disabled={disabled} className="space-y-2">
                    <Label htmlFor={`${id}-subject`} className="text-xs font-semibold">
                        Subject line
                    </Label>
                    <Input
                        id={`${id}-subject`}
                        value={draft.subject}
                        disabled={disabled}
                        maxLength={255}
                        onChange={(event) => onChange((current) => ({ ...current, subject: event.target.value }))}
                        onFocus={(event) => {
                            onSelect("subject");
                            onCopyFocus("subject", event.currentTarget);
                        }}
                        onSelect={(event) => onCopyFocus("subject", event.currentTarget)}
                        aria-invalid={Boolean(errors["configuration.subject"])}
                        aria-describedby={errors["configuration.subject"] ? `${id}-subject-error` : undefined}
                        className="h-10 text-sm"
                        data-email-copy-target="subject"
                        placeholder={definition.defaults.subject}
                    />
                    <FieldError id={`${id}-subject-error`} message={errors["configuration.subject"]} />
                    <p className="text-muted-foreground text-xs leading-5">Tokens resolve to each student&apos;s information when sent.</p>
                </CopyDropTarget>

                {definition.capabilities.headline && (
                    <CopyDropTarget target="headline" disabled={disabled} className="space-y-2">
                        <Label htmlFor={`${id}-headline`} className="text-xs font-semibold">
                            Email headline
                        </Label>
                        <Input
                            id={`${id}-headline`}
                            value={draft.headline}
                            disabled={disabled}
                            maxLength={255}
                            onChange={(event) => onChange((current) => ({ ...current, headline: event.target.value }))}
                            onFocus={(event) => {
                                onSelect("headline");
                                onCopyFocus("headline", event.currentTarget);
                            }}
                            onSelect={(event) => onCopyFocus("headline", event.currentTarget)}
                            className="h-10 text-sm"
                            data-email-copy-target="headline"
                            placeholder={definition.defaults.headline}
                            aria-invalid={Boolean(errors["configuration.headline"])}
                            aria-describedby={errors["configuration.headline"] ? `${id}-headline-error` : undefined}
                        />
                        <FieldError id={`${id}-headline-error`} message={errors["configuration.headline"]} />
                    </CopyDropTarget>
                )}

                <section className="border-border space-y-3 border-t pt-5" aria-label="Intro paragraph properties">
                    <div className="flex items-center justify-between gap-2">
                        <h4 className="flex items-center gap-1.5 text-xs font-semibold">
                            <TextCursorInput className="size-3.5" aria-hidden="true" /> Introduction
                        </h4>
                        <span className="text-muted-foreground text-xs tabular-nums">{draft.paragraphs.length} blocks</span>
                    </div>
                    {paragraph ? (
                        <CopyDropTarget target={`paragraph:${paragraph.id}`} disabled={disabled} className="space-y-2">
                            <Label htmlFor={`${id}-paragraph`} className="text-xs">
                                Paragraph {paragraphIndex + 1} copy
                            </Label>
                            <Textarea
                                id={`${id}-paragraph`}
                                value={paragraph.text}
                                disabled={disabled}
                                rows={5}
                                onChange={(event) => onChangeParagraph(paragraph.id, event.target.value)}
                                onFocus={(event) => onCopyFocus(`paragraph:${paragraph.id}`, event.currentTarget)}
                                onSelect={(event) => onCopyFocus(`paragraph:${paragraph.id}`, event.currentTarget)}
                                data-email-copy-target={`paragraph:${paragraph.id}`}
                                placeholder="Write your paragraph…"
                                className="resize-y text-sm leading-6"
                            />
                        </CopyDropTarget>
                    ) : (
                        <p className="text-muted-foreground text-xs leading-5">
                            Select a paragraph on the canvas to edit it here, or type directly into the selected block.
                        </p>
                    )}
                    <FieldError id={`${id}-body-error`} message={errors["configuration.custom_body"]} />
                    <div className="space-y-1.5">
                        {draft.paragraphs.map((item, index) => (
                            <button
                                key={item.id}
                                type="button"
                                disabled={disabled}
                                onClick={() => onSelect(`paragraph:${item.id}`)}
                                aria-label={`Select paragraph ${index + 1}`}
                                aria-pressed={selection === `paragraph:${item.id}`}
                                className={cn(
                                    "border-border hover:bg-muted focus-visible:ring-ring flex min-h-10 w-full items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-xs focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60",
                                    selection === `paragraph:${item.id}` && "border-primary/40 bg-primary/5",
                                )}
                            >
                                <span className="text-muted-foreground shrink-0 font-mono tabular-nums">{String(index + 1).padStart(2, "0")}</span>
                                <span className="truncate">{item.text || "Empty paragraph"}</span>
                            </button>
                        ))}
                    </div>
                    <Button type="button" variant="outline" className="h-10 w-full border-dashed text-xs" disabled={disabled} onClick={onAdd}>
                        <Plus className="size-3.5" aria-hidden="true" /> Add paragraph
                    </Button>
                    <p className="text-muted-foreground text-xs leading-5">
                        Plain text only. Paragraphs save as intro prose; HTML and Markdown formatting are not applied. The greeting is added
                        automatically.
                    </p>
                </section>

                {definition.capabilities.button_label && (
                    <section className="border-border space-y-3 border-t pt-5">
                        <h4 className="text-xs font-semibold">Portal action</h4>
                        <div className="space-y-2">
                            <Label htmlFor={`${id}-button-label`} className="text-xs">
                                Button label
                            </Label>
                            <Input
                                id={`${id}-button-label`}
                                value={draft.button_label}
                                disabled={disabled}
                                maxLength={120}
                                onChange={(event) => onChange((current) => ({ ...current, button_label: event.target.value }))}
                                onFocus={() => onSelect("button")}
                                className="h-10 text-sm"
                                placeholder={definition.defaults.button_label}
                                aria-invalid={Boolean(errors["configuration.button_label"])}
                            />
                            <FieldError message={errors["configuration.button_label"]} />
                        </div>
                        {definition.capabilities.button_url ? (
                            <div className="space-y-2">
                                <Label htmlFor={`${id}-button-url`} className="text-xs">
                                    Same-portal path
                                </Label>
                                <Input
                                    id={`${id}-button-url`}
                                    value={draft.button_url}
                                    disabled={disabled}
                                    maxLength={2048}
                                    onChange={(event) => onChange((current) => ({ ...current, button_url: event.target.value }))}
                                    onFocus={() => onSelect("button")}
                                    className="h-10 text-sm"
                                    placeholder="/student"
                                    aria-invalid={Boolean(urlError)}
                                    aria-describedby={`${id}-button-url-hint`}
                                />
                                <p
                                    id={`${id}-button-url-hint`}
                                    className={cn("text-xs leading-5", urlError ? "text-destructive" : "text-muted-foreground")}
                                >
                                    {urlError || "Leave blank to use the configured student portal."}
                                </p>
                            </div>
                        ) : (
                            <p className="text-muted-foreground flex items-start gap-1.5 text-xs leading-5">
                                <LockKeyhole className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" /> The destination is managed by the system.
                            </p>
                        )}
                    </section>
                )}

                {definition.capabilities.sections && (
                    <section className="border-border space-y-3 border-t pt-5">
                        <h4 className="text-xs font-semibold">System-rendered sections</h4>
                        {(
                            [
                                ["include_details", "Application details"],
                                ["include_subjects", "Approved subjects"],
                                ["include_tuition", "Tuition summary"],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key} className="flex min-h-10 items-center justify-between gap-3">
                                <Label htmlFor={`${id}-${key}`} className="text-xs">
                                    {label}
                                </Label>
                                <Switch
                                    id={`${id}-${key}`}
                                    checked={draft[key]}
                                    disabled={disabled}
                                    onCheckedChange={(checked) => onChange((current) => ({ ...current, [key]: checked }))}
                                />
                            </div>
                        ))}
                        <p className="text-muted-foreground text-xs leading-5">These sections use student records, not editable template text.</p>
                    </section>
                )}

                <div className="bg-muted/50 text-muted-foreground flex items-start gap-2 rounded-xl p-3 text-xs leading-5">
                    <LockKeyhole className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        Branding, greetings, student data, account instructions, and attachments stay system-managed. Security emails are not
                        template-editable.
                    </p>
                </div>
            </div>
        </aside>
    );
}

function FieldError({ message, id }: { message?: string; id?: string }) {
    return message ? (
        <p id={id} className="text-destructive text-xs leading-5" role="alert">
            {message}
        </p>
    ) : null;
}
