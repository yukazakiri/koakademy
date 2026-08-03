import { newsletter } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Link } from "@inertiajs/react";
import { ArrowRight, MailCheck, Newspaper, ShieldCheck } from "lucide-react";

export function NewsletterSettingsCard() {
    return (
        <Card className="border-primary/20 overflow-hidden">
            <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                    <div className="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-xl">
                        <Newspaper className="size-5" aria-hidden="true" />
                    </div>
                    <div className="min-w-0 space-y-1">
                        <CardTitle>Newsletter marketing</CardTitle>
                        <CardDescription>Manage the consent prompt and your Sequenzy, Brevo, or Mailchimp provider.</CardDescription>
                    </div>
                </div>
                <Badge variant="outline" className="w-fit shrink-0 gap-1.5">
                    <MailCheck className="size-3.5" aria-hidden="true" />
                    Marketing only
                </Badge>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="bg-muted/40 text-muted-foreground flex items-start gap-3 rounded-xl border p-4 text-sm leading-6">
                    <ShieldCheck className="text-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>Newsletter contacts stay separate from SMTP, password resets, receipts, and other transactional messages.</p>
                </div>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-sm">Configure credentials, test the connection, then enable future signups.</p>
                    <Button asChild className="shrink-0">
                        <Link href={newsletter.url()} prefetch>
                            Configure newsletter
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
