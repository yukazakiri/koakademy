import { useId, type ReactNode } from "react";

type FormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    action?: ReactNode;
};

export function FormSection({ title, description, children, action }: FormSectionProps): ReactNode {
    const titleId = useId();

    return (
        <section aria-labelledby={titleId} className="bg-card text-card-foreground rounded-xl border p-4 sm:p-6">
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <h3 id={titleId} className="text-base font-semibold">
                        {title}
                    </h3>
                    {description ? <p className="text-muted-foreground max-w-2xl text-sm text-pretty">{description}</p> : null}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
