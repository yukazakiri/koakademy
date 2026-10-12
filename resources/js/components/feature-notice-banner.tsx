import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Button } from "@/components/ui/button";
import { usePage } from "@inertiajs/react";
import { Info, X } from "lucide-react";
import { useEffect, useState } from "react";

interface FeatureNotice {
    title: string;
    message: string;
    feature: string;
}

/**
 * Explains a redirect caused by feature policy, for example a stable rollout
 * that has not reached this user yet.
 *
 * Rendered inline rather than as a toast on purpose: a toast auto-dismisses,
 * and this notice is the entire answer to "why was I sent back?". The prop is
 * namespaced to feature policy so it cannot collide with the generic `flash`
 * prop that individual pages already consume.
 */
export function FeatureNoticeBanner() {
    const notice = usePage<{ featureNotice?: FeatureNotice }>().props.featureNotice;
    const [dismissed, setDismissed] = useState<string | null>(null);

    useEffect(() => {
        setDismissed(null);
    }, [notice?.message]);

    if (!notice || dismissed === notice.message) {
        return null;
    }

    return (
        <div className="mx-4 mt-3 lg:mx-6">
            <Alert variant="info" role="status">
                <Info aria-hidden="true" />
                <AlertTitle>{notice.title}</AlertTitle>
                <AlertDescription>{notice.message}</AlertDescription>
                <AlertAction>
                    <Button size="icon-sm" variant="ghost" aria-label="Dismiss notice" onClick={() => setDismissed(notice.message)}>
                        <X />
                    </Button>
                </AlertAction>
            </Alert>
        </div>
    );
}
