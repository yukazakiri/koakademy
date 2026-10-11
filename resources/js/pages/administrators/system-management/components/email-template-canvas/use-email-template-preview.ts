import type { EmailTemplateConfiguration } from "@/lib/email-template-canvas";
import axios from "axios";
import { useEffect, useState } from "react";

export interface RecipientEmailPreview {
    subject: string;
    html: string;
}

/** Preview is opt-in, debounced, and abortable; never display an older request as the latest render. */
export function useEmailTemplatePreview(url: string, stepKey: string, configuration: EmailTemplateConfiguration, enabled: boolean) {
    const [result, setResult] = useState<RecipientEmailPreview | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const serialized = JSON.stringify(configuration);

    useEffect(() => {
        if (!enabled) return;
        const controller = new AbortController();
        let cancelled = false;
        setLoading(true);
        setError(null);

        const timeout = window.setTimeout(async () => {
            try {
                const response = await axios.post<RecipientEmailPreview>(
                    url,
                    { step_key: stepKey, configuration: JSON.parse(serialized) },
                    { signal: controller.signal },
                );
                if (!cancelled) {
                    setResult(response.data);
                    setLoading(false);
                }
            } catch (failure) {
                if (cancelled || axios.isCancel(failure)) return;
                let message = "The recipient preview could not be rendered. Your changes have not been lost.";
                if (axios.isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(failure)) {
                    const validationMessage = Object.values(failure.response?.data.errors ?? {}).flat()[0];
                    if (validationMessage) message = validationMessage;
                    else if (failure.response?.status === 419) message = "Your session has expired. Reload the page to preview this email.";
                }
                setError(message);
                setLoading(false);
            }
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [enabled, serialized, stepKey, url]);

    return { result, loading, error };
}
