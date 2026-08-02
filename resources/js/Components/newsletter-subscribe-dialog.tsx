import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { router, usePage } from "@inertiajs/react";
import { BellRing, CalendarClock, Loader2, MailCheck, Newspaper } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

type NewsletterFeedback = {
    type: "success" | "error";
    message: string;
} | null;

interface NewsletterSharedProps {
    enabled: boolean;
    shouldPrompt: boolean;
    feedback?: NewsletterFeedback;
}

const SNOOZE_STORAGE_KEY = "koakademy.newsletter-prompt-snoozed";
const PROMPT_DELAY_MS = 1500;

/**
 * One-time opt-in prompt asking students and faculty to subscribe to the
 * school newsletter. Visibility is driven entirely by the shared `newsletter`
 * Inertia prop: the server hides it for users who already responded or who
 * already exist as Sequenzy subscribers.
 */
export function NewsletterSubscribeDialog() {
    const { newsletter } = usePage().props as unknown as {
        newsletter?: NewsletterSharedProps;
    };
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState<"subscribe" | "decline" | null>(null);

    // Open the prompt once per session while the server says we should ask.
    useEffect(() => {
        if (!newsletter?.shouldPrompt) {
            setOpen(false);
            return;
        }

        if (sessionStorage.getItem(SNOOZE_STORAGE_KEY) === "1") {
            return;
        }

        const timer = window.setTimeout(() => setOpen(true), PROMPT_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [newsletter?.shouldPrompt]);

    // Surface server feedback (flashed after subscribe attempts) as toasts.
    useEffect(() => {
        const feedback = newsletter?.feedback;

        if (!feedback?.message) {
            return;
        }

        if (feedback.type === "success") {
            toast.success(feedback.message);
        } else {
            toast.error(feedback.message);
        }
    }, [newsletter?.feedback]);

    const handleSubscribe = () => {
        setSubmitting("subscribe");

        router.post(
            route("newsletter.subscribe"),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSubmitting(null),
            },
        );
    };

    const handleNotNow = () => {
        sessionStorage.setItem(SNOOZE_STORAGE_KEY, "1");
        setOpen(false);
    };

    const handleDecline = () => {
        setSubmitting("decline");

        router.post(
            route("newsletter.decline"),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSubmitting(null),
            },
        );
    };

    if (!newsletter?.enabled) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                // Closing via the X button or overlay counts as "not now".
                if (!nextOpen) {
                    handleNotNow();
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="bg-primary/10 mx-auto mb-1 flex size-12 items-center justify-center rounded-full sm:mx-0">
                        <Newspaper className="text-primary size-6" />
                    </div>
                    <DialogTitle className="text-xl">Stay in the loop!</DialogTitle>
                    <DialogDescription>
                        Subscribe to the school newsletter and get the latest news and announcements delivered straight to your inbox.
                    </DialogDescription>
                </DialogHeader>

                <div className="border-border/60 bg-muted/40 rounded-lg border px-4 py-3">
                    <ul className="text-muted-foreground space-y-2.5 text-sm">
                        <li className="flex items-center gap-2.5">
                            <BellRing className="text-primary size-4 shrink-0" />
                            Important announcements and deadline reminders
                        </li>
                        <li className="flex items-center gap-2.5">
                            <CalendarClock className="text-primary size-4 shrink-0" />
                            School events, activities, and schedule updates
                        </li>
                        <li className="flex items-center gap-2.5">
                            <MailCheck className="text-primary size-4 shrink-0" />
                            No spam — unsubscribe at any time
                        </li>
                    </ul>
                </div>

                <DialogFooter className="flex-col gap-3 sm:flex-col">
                    <Button onClick={handleSubscribe} disabled={submitting !== null} className="w-full">
                        {submitting === "subscribe" ? <Loader2 className="animate-spin" /> : <MailCheck />}
                        {submitting === "subscribe" ? "Subscribing…" : "Yes, subscribe me"}
                    </Button>
                    <div className="flex w-full items-center justify-between">
                        <button
                            type="button"
                            onClick={handleDecline}
                            disabled={submitting !== null}
                            className="text-muted-foreground hover:text-foreground text-xs underline-offset-2 transition-colors hover:underline disabled:opacity-50"
                        >
                            Don&apos;t ask again
                        </button>
                        <Button variant="ghost" size="sm" onClick={handleNotNow} disabled={submitting !== null}>
                            Not now
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default NewsletterSubscribeDialog;
