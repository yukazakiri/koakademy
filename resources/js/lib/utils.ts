import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function escapeHtml(value: unknown): string {
    if (value === null || value === undefined) {
        return "";
    }

    return String(value).replace(/[&<>'"]/g, (character) => {
        const entities: Record<string, string> = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "'": "&#039;",
            '"': "&quot;",
        };

        return entities[character];
    });
}
