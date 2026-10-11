export type EmailTemplateKey = "status_update" | "academic_verified" | "assessment" | "custom";

export interface EmailTemplateConfiguration {
    notification: EmailTemplateKey;
    subject: string;
    headline: string;
    custom_body: string;
    button_label: string;
    button_url: string;
    include_details: boolean;
    include_subjects: boolean;
    include_tuition: boolean;
}

export interface EmailTemplateDefinition {
    key: EmailTemplateKey;
    label: string;
    description: string;
    defaults: EmailTemplateConfiguration;
    capabilities: {
        headline: boolean;
        button_label: boolean;
        button_url: boolean;
        sections: boolean;
    };
}

export interface EmailTemplateEditorData {
    definitions: EmailTemplateDefinition[];
    sample_tokens: Record<string, string>;
    branding: {
        organization_name: string;
        logo_url: string | null;
        tagline: string | null;
        theme_color: string;
        support_email: string | null;
        support_phone: string | null;
    };
}

export interface EmailParagraph {
    id: string;
    text: string;
}

export interface EmailTemplateDraft extends Omit<EmailTemplateConfiguration, "custom_body"> {
    paragraphs: EmailParagraph[];
    /** Showing catalog copy must not silently turn it into an authored override. */
    introCustomized: boolean;
}

export type EmailCopyTarget = "subject" | "headline" | `paragraph:${string}`;
export type EmailEditorSelection = EmailCopyTarget | "button";

export interface EmailDraftHistory {
    past: EmailTemplateDraft[];
    present: EmailTemplateDraft;
    future: EmailTemplateDraft[];
}

export type EmailDraftHistoryAction =
    { type: "change"; update: (draft: EmailTemplateDraft) => EmailTemplateDraft } | { type: "undo" } | { type: "redo" };

/** Enrollment mail renders its greeting in code, outside the editable intro. */
export function stripEmailSalutation(body: string): string {
    return body
        .replace(/^\s*(?:hello|hi|dear|greetings)\s*\{\{\s*student_name\s*\}\}\s*,?\s*/i, "")
        .replace(/^\s*congratulations\s*\{\{\s*student_name\s*\}\}\s*!?,?\s*/i, "")
        .trimStart();
}

export function paragraphsFromIntro(intro: string): EmailParagraph[] {
    const normalized = stripEmailSalutation(intro.replace(/\r\n?/g, "\n")).trim();

    return normalized === "" ? [] : normalized.split(/\n{2,}/).map((text, index) => ({ id: `paragraph-${index + 1}`, text }));
}

/** Blocks are an editing aid, not a new storage format or an editable HTML layout. */
export function introFromParagraphs(paragraphs: EmailParagraph[]): string {
    return stripEmailSalutation(
        paragraphs
            .map((paragraph) => paragraph.text.trim())
            .filter(Boolean)
            .join("\n\n"),
    ).trim();
}

export function canonicalEmailTemplateKey(value: unknown): EmailTemplateKey | null {
    if (value === "official_enrollment") return "assessment";
    return value === "status_update" || value === "academic_verified" || value === "assessment" || value === "custom" ? value : null;
}

export function createEmailDraft(configuration: Record<string, unknown>, definition: EmailTemplateDefinition): EmailTemplateDraft {
    const text = (key: keyof EmailTemplateConfiguration) =>
        typeof configuration[key] === "string" && configuration[key].trim() !== "" ? configuration[key] : String(definition.defaults[key]);
    const flag = (key: "include_details" | "include_subjects" | "include_tuition") =>
        typeof configuration[key] === "boolean" ? configuration[key] : definition.defaults[key];
    const storedIntro = typeof configuration.custom_body === "string" ? stripEmailSalutation(configuration.custom_body).trim() : "";

    return {
        notification: definition.key,
        subject: text("subject"),
        headline: text("headline"),
        paragraphs: paragraphsFromIntro(text("custom_body")),
        introCustomized: storedIntro !== "",
        button_label: text("button_label"),
        button_url: typeof configuration.button_url === "string" ? configuration.button_url : definition.defaults.button_url,
        include_details: flag("include_details"),
        include_subjects: flag("include_subjects"),
        include_tuition: flag("include_tuition"),
    };
}

export function replaceEmailParagraphs(draft: EmailTemplateDraft, paragraphs: EmailParagraph[]): EmailTemplateDraft {
    return {
        ...draft,
        paragraphs,
        introCustomized: draft.introCustomized || introFromParagraphs(paragraphs) !== introFromParagraphs(draft.paragraphs),
    };
}

export function configurationFromDraft(draft: EmailTemplateDraft): EmailTemplateConfiguration {
    const { paragraphs, introCustomized, ...configuration } = draft;

    return {
        ...configuration,
        subject: draft.subject.trim(),
        headline: draft.headline.trim(),
        custom_body: introCustomized ? introFromParagraphs(paragraphs) : "",
        button_label: draft.button_label.trim(),
        button_url: draft.button_url.trim(),
    };
}

/** Match the two token spellings supported by EmailTemplateRenderer, without recursive expansion. */
export function interpolateEmailCopy(copy: string, tokens: Record<string, string>): string {
    return copy.replace(/\{\{(?: ([a-zA-Z_]\w*) |([a-zA-Z_]\w*))\}\}/g, (match, spaced: string | undefined, compact: string | undefined) => {
        const key = spaced ?? compact ?? "";
        return Object.hasOwn(tokens, key) ? tokens[key] : match;
    });
}

export function insertEmailCopyToken(copy: string, token: string, start = copy.length, end = start): { text: string; caret: number } {
    const from = Math.max(0, Math.min(start, copy.length));
    const to = Math.max(from, Math.min(end, copy.length));
    const prefix = copy.slice(0, from);
    const suffix = copy.slice(to);
    const leadingSpace = prefix !== "" && !/\s$/.test(prefix) ? " " : "";
    const trailingSpace = suffix !== "" && !/^\s|^[,.;:!?)]/.test(suffix) ? " " : "";
    const insertion = `${leadingSpace}${token}${trailingSpace}`;

    return { text: `${prefix}${insertion}${suffix}`, caret: from + insertion.length };
}

export function isSamePortalEmailPath(path: string): boolean {
    const trimmed = path.trim();
    const hasUnsafeCharacter = Array.from(trimmed).some(
        (character) => character === "\\" || character.charCodeAt(0) <= 0x20 || character.charCodeAt(0) === 0x7f,
    );
    return trimmed === "" || (/^\/(?!\/)/.test(trimmed) && !hasUnsafeCharacter && !/%(?:5c|0[0-9a-f]|1[0-9a-f]|7f)/i.test(trimmed));
}

export function moveEmailParagraph(paragraphs: EmailParagraph[], id: string, destination: number): EmailParagraph[] {
    const source = paragraphs.findIndex((paragraph) => paragraph.id === id);
    if (source === -1 || destination < 0 || destination >= paragraphs.length || source === destination) return paragraphs;
    const next = [...paragraphs];
    const [paragraph] = next.splice(source, 1);
    next.splice(destination, 0, paragraph);
    return next;
}

export function emailDraftHistoryReducer(history: EmailDraftHistory, action: EmailDraftHistoryAction): EmailDraftHistory {
    if (action.type === "undo") {
        const previous = history.past.at(-1);
        return previous ? { past: history.past.slice(0, -1), present: previous, future: [history.present, ...history.future] } : history;
    }

    if (action.type === "redo") {
        const next = history.future[0];
        return next ? { past: [...history.past, history.present], present: next, future: history.future.slice(1) } : history;
    }

    const next = action.update(history.present);
    if (JSON.stringify(next) === JSON.stringify(history.present)) return history;

    return { past: [...history.past.slice(-49), history.present], present: next, future: [] };
}
