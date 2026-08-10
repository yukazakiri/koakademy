import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Mail, ServerCog } from "lucide-react";

import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

export default function SystemManagementMailPage({ user, mail_config, access }: SystemManagementPageProps) {
    const isLogTransport = mail_config.delivery_mode === "log";

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="mail"
            heading="Deployment Mail"
            description="View the runtime mail transport. Credentials are managed outside the application."
        >
            <Card>
                <CardHeader>
                    <div className="flex items-start gap-3">
                        <div className="rounded-md bg-muted p-2 text-muted-foreground">
                            <ServerCog className="size-5" />
                        </div>
                        <div className="space-y-1">
                            <CardTitle>Deployment-managed transport</CardTitle>
                            <CardDescription>
                                KoAkademy never stores SMTP or provider credentials in the application database.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    <dl className="grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt className="text-muted-foreground">Mailer</dt>
                            <dd className="mt-1 font-medium capitalize">{mail_config.driver}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">From address</dt>
                            <dd className="mt-1 font-medium">{mail_config.email_from_address}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">From name</dt>
                            <dd className="mt-1 font-medium">{mail_config.email_from_name}</dd>
                        </div>
                    </dl>

                    <div className="rounded-lg border bg-muted/30 p-4">
                        <div className="flex gap-3">
                            <Mail className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <div className="space-y-2 text-sm">
                                <p className="font-medium">
                                    {isLogTransport ? "Email is currently written to application logs." : "An external mail provider is active."}
                                </p>
                                <p className="text-muted-foreground">
                                    Configure SMTP or Sequenzy from the server, where credentials are rotated as Docker Secrets and
                                    FrankenPHP is restarted safely.
                                </p>
                                <code className="block w-fit rounded bg-background px-2 py-1 text-xs">
                                    sudo koakademy configure mail {isLogTransport ? "smtp" : "log"}
                                </code>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}
