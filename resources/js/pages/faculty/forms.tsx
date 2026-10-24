import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";

interface FormRow {
    id: string; title: string; slug: string; description: string | null;
    access_mode: string; closes_at: string | null; responded: boolean; url: string;
}
interface Props { user: User; forms: FormRow[] }

const accessLabels: Record<string, string> = {
    anonymous: "Anonymous",
    authenticated: "Sign-in required",
    guest_identifier: "Guest",
    invitation: "Invitation only",
};

export default function FormsPage({ user, forms }: Props) {
    const open = (url: string) => router.visit(url);

    return (
        <FacultyLayout user={user}>
            <Head title="Faculty Forms" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Faculty Forms</h1>
                    <p className="text-muted-foreground text-sm">School forms published for faculty: surveys, evaluations, and requests.</p>
                </header>

                <div className="grid gap-4 md:grid-cols-2">
                    {forms.length === 0 && (
                        <Card className="md:col-span-2">
                            <CardContent className="text-muted-foreground p-8 text-center text-sm">No published forms right now.</CardContent>
                        </Card>
                    )}
                    {forms.map((f) => (
                        <Card key={f.id}>
                            <CardContent className="space-y-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold">{f.title}</p>
                                        {f.description && <p className="text-muted-foreground line-clamp-2 text-sm">{f.description}</p>}
                                    </div>
                                    {f.responded ? (
                                        <Badge variant="outline" className="shrink-0 border-emerald-200 bg-emerald-500/10 font-normal text-emerald-600">submitted</Badge>
                                    ) : (
                                        <Badge variant="outline" className="shrink-0 bg-amber-500/10 font-normal text-amber-600">pending</Badge>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <span className="text-muted-foreground">
                                        {accessLabels[f.access_mode] ?? f.access_mode}{f.closes_at ? ` · closes ${f.closes_at}` : ""}
                                    </span>
                                    <Button size="sm" onClick={() => open(f.url)}>{f.responded ? "View form" : "Fill out"}</Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
        </FacultyLayout>
    );
}
