import type { ReactNode } from "react";

/**
 * Split a label into the parts that match the search terms and the parts that do not.
 *
 * Used to highlight why a result matched. Terms are matched case-insensitively and
 * every occurrence is highlighted, not just the first.
 */
export type HighlightedPart = { text: string; matched: boolean };

export function splitBySearchTerms(label: string, query: string): HighlightedPart[] {
    const terms = query
        .trim()
        .split(/[^\p{L}\p{N}]+/u)
        .filter((term) => term.length > 0);

    if (label === "" || terms.length === 0) {
        return [{ text: label, matched: false }];
    }

    const pattern = new RegExp(`(${terms.map((term) => escapeRegExp(term)).join("|")})`, "giu");
    const parts = label.split(pattern);

    return parts
        .filter((part) => part !== "")
        .map((part) => ({ text: part, matched: terms.some((term) => term.toLowerCase() === part.toLowerCase()) }));
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/**
 * Render a label with the searched terms emphasised.
 */
export function highlightSearchTerms(label: string, query: string): ReactNode[] {
    const parts = splitBySearchTerms(label, query);

    return parts.map((part, index) =>
        part.matched ? (
            <mark key={`${part.text}-${index}`} className="bg-primary/20 text-foreground rounded-[2px] px-0.5">
                {part.text}
            </mark>
        ) : (
            <span key={`${part.text}-${index}`}>{part.text}</span>
        ),
    );
}
