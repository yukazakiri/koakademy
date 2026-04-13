import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Head } from "@inertiajs/react";
import { GraduationCap, XCircle } from "lucide-react";

interface EnrollmentClosedProps {
    message: string;
}

export default function EnrollmentClosed({ message }: EnrollmentClosedProps) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
            <Head title="Enrollment Unavailable" />

            <Card className="w-full max-w-md border-amber-200 bg-white shadow-lg dark:border-amber-900 dark:bg-slate-900">
                <CardContent className="flex flex-col items-center gap-6 p-8 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                        <XCircle className="h-8 w-8 text-amber-600 dark:text-amber-400" />
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-center gap-2">
                            <GraduationCap className="text-primary h-5 w-5" />
                            <h1 className="text-xl font-bold">Enrollment Unavailable</h1>
                        </div>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            {message}
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 w-full pt-2">
                        <Button variant="outline" asChild className="w-full">
                            <a href="/">Back to Homepage</a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
