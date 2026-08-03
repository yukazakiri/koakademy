import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { decline, subscribe } from "@/routes/newsletter";
import { router, usePage } from "@inertiajs/react";
import { BellOff, Loader2, Mail, MailOpen, ShieldCheck } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

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
 * already responded through the active marketing contact provider.
 */
export function NewsletterSubscribeDialog() {
    const { auth, newsletter } = usePage().props as unknown as {
        auth?: { user?: { id?: number | string } | null };
        newsletter?: NewsletterSharedProps;
    };
    const snoozeStorageKey = `${SNOOZE_STORAGE_KEY}:${auth?.user?.id ?? "guest"}`;
    const [open, setOpen] = useState(false);
    const [submitting, setSubmitting] = useState<"subscribe" | "decline" | null>(null);

    // Open the prompt once per session while the server says we should ask.
    useEffect(() => {
        if (!newsletter?.shouldPrompt) {
            setOpen(false);
            return;
        }

        if (sessionStorage.getItem(snoozeStorageKey) === "1") {
            return;
        }

        const timer = window.setTimeout(() => setOpen(true), PROMPT_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [newsletter?.shouldPrompt, snoozeStorageKey]);

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
            subscribe.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSubmitting(null),
            },
        );
    };

    const handleNotNow = () => {
        sessionStorage.setItem(snoozeStorageKey, "1");
        setOpen(false);
    };

    const handleDecline = () => {
        setSubmitting("decline");

        router.post(
            decline.url(),
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
            <DialogContent className="border-border/70 max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl p-0 shadow-2xl sm:max-w-[900px] [&_[data-slot=dialog-close]]:top-4 [&_[data-slot=dialog-close]]:right-4 [&_[data-slot=dialog-close]]:flex [&_[data-slot=dialog-close]]:size-10 [&_[data-slot=dialog-close]]:items-center [&_[data-slot=dialog-close]]:justify-center [&_[data-slot=dialog-close]]:rounded-xl [&_[data-slot=dialog-close]]:border sm:[&_[data-slot=dialog-close]]:top-7 sm:[&_[data-slot=dialog-close]]:right-7 sm:[&_[data-slot=dialog-close]]:size-12">
                <div className="px-6 pt-9 pb-7 sm:px-16 sm:pt-16 sm:pb-12">
                    <DialogHeader className="items-center gap-0 text-center sm:text-center">
                        <div className="bg-muted text-foreground mb-6 flex size-16 items-center justify-center rounded-full sm:mb-8 sm:size-24">
                            <MailOpen className="size-8 stroke-[1.7] sm:size-12" aria-hidden="true" />
                        </div>
                        <DialogTitle className="text-2xl leading-tight font-semibold tracking-tight sm:text-4xl">
                            Get the important school updates
                        </DialogTitle>
                        <DialogDescription className="mt-4 max-w-[40rem] text-base leading-7 sm:mt-6 sm:text-xl sm:leading-9">
                            Opt in to our newsletter for news, events, and deadline reminders. This is separate from account and transactional
                            messages.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="text-muted-foreground my-7 flex items-center justify-center gap-3 text-sm sm:my-10 sm:text-lg">
                        <ShieldCheck className="size-5 shrink-0 sm:size-6" aria-hidden="true" />
                        <span>Unsubscribe anytime.</span>
                    </div>

                    <DialogFooter className="flex-col gap-3 sm:flex-col">
                        <Button
                            size="lg"
                            onClick={handleSubscribe}
                            disabled={submitting !== null}
                            className="min-h-12 w-full text-base font-semibold sm:min-h-14 sm:text-lg"
                        >
                            {submitting === "subscribe" ? <Loader2 className="animate-spin" /> : <Mail aria-hidden="true" />}
                            {submitting === "subscribe" ? "Subscribing…" : "Subscribe to newsletter"}
                        </Button>
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={handleNotNow}
                            disabled={submitting !== null}
                            className="min-h-12 w-full text-base font-semibold sm:min-h-14 sm:text-lg"
                        >
                            Maybe later
                        </Button>
                    </DialogFooter>
                </div>

                <button
                    type="button"
                    onClick={handleDecline}
                    disabled={submitting !== null}
                    className="group border-border/70 hover:bg-muted/50 focus-visible:ring-ring flex min-h-24 w-full items-center gap-4 border-t px-6 py-5 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset disabled:opacity-50 sm:min-h-32 sm:gap-5 sm:px-16 sm:py-8"
                >
                    {submitting === "decline" ? (
                        <Loader2 className="text-muted-foreground size-5 shrink-0 animate-spin" />
                    ) : (
                        <BellOff
                            className="text-muted-foreground group-hover:text-foreground size-5 shrink-0 transition-colors sm:size-6"
                            aria-hidden="true"
                        />
                    )}
                    <span>
                        <span className="text-foreground block text-sm font-semibold sm:text-lg">Don&apos;t ask me again</span>
                        <span className="text-muted-foreground mt-1 block text-xs leading-5 sm:text-base sm:leading-6">
                            You won&apos;t be shown this again. This setting is permanent.
                        </span>
                    </span>
                </button>
            </DialogContent>
        </Dialog>
    );
}

export default NewsletterSubscribeDialog;
