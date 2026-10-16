import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { BookOpen, CircleHelp, ExternalLink, Lightbulb, ShieldCheck } from "lucide-react";
import type { HelpTopic } from "../types";

export function HelpButton({ onClick, label = "What this does" }: { onClick: () => void; label?: string }) {
    return (
        <Button type="button" variant="ghost" size="sm" onClick={onClick} className="h-10 gap-2 px-3 text-xs">
            <CircleHelp className="size-4" />
            {label}
        </Button>
    );
}

export function PolicyHelpDrawer({
    topic,
    open,
    onOpenChange,
    documentationUrl,
}: {
    topic: HelpTopic | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentationUrl: string;
}) {
    const docsUrl = topic?.docsAnchor ? `${documentationUrl.replace(/\/$/, "")}/${topic.docsAnchor.replace(/^\//, "")}/` : documentationUrl;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-md">
                <div className="bg-primary/8 dark:bg-primary/10 border-b px-6 py-8">
                    <div className="bg-primary text-primary-foreground mb-5 flex size-11 items-center justify-center rounded-xl shadow-sm">
                        <BookOpen className="size-5" />
                    </div>
                    <SheetHeader className="text-left">
                        <SheetTitle className="text-xl text-balance">{topic?.title ?? "Enrollment policy help"}</SheetTitle>
                        <SheetDescription className="leading-6 text-pretty">
                            Plain-language guidance for the setting you are working on.
                        </SheetDescription>
                    </SheetHeader>
                </div>

                <div className="space-y-6 px-6 py-6">
                    <section className="space-y-2">
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <CircleHelp className="text-primary size-4" /> What this does
                        </div>
                        <p className="text-muted-foreground text-sm leading-6 text-pretty">{topic?.whatItDoes}</p>
                    </section>

                    {topic?.impact ? (
                        <section className="bg-muted/60 rounded-xl p-4 shadow-[0_0_0_1px_rgba(0,0,0,0.05)] dark:shadow-[0_0_0_1px_rgba(255,255,255,0.08)]">
                            <div className="mb-2 flex items-center gap-2 text-sm font-semibold">
                                <ShieldCheck className="text-primary size-4" /> What changes
                            </div>
                            <p className="text-muted-foreground text-sm leading-6 text-pretty">{topic.impact}</p>
                        </section>
                    ) : null}

                    {topic?.example ? (
                        <section className="rounded-xl bg-amber-500/8 p-4 shadow-[0_0_0_1px_rgba(245,158,11,0.2)]">
                            <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-200">
                                <Lightbulb className="size-4" /> Example
                            </div>
                            <p className="text-sm leading-6 text-pretty text-amber-900/80 dark:text-amber-100/80">{topic.example}</p>
                        </section>
                    ) : null}

                    <Button
                        nativeButton={false}
                        render={<a href={docsUrl} target="_blank" rel="noopener noreferrer" />}
                        variant="outline"
                        className="w-full justify-between"
                    >
                        Read the full operator guide <ExternalLink />
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
