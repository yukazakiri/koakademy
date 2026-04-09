import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { useForm } from "@inertiajs/react";
import axios from "axios";
import { Loader2, Mail, Save } from "lucide-react";
import { FormEvent, useState } from "react";
import { toast } from "sonner";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface MailFormData {
    email_from_address: string;
    email_from_name: string;
    driver: string;
    host: string;
    port: number;
    username: string;
    password: string;
    encryption: string;
}

export default function SystemManagementMailPage({ user, general_settings, mail_config, access }: SystemManagementPageProps) {
    const [testEmail, setTestEmail] = useState("");
    const [sendingTest, setSendingTest] = useState(false);

    const mailForm = useForm<MailFormData>({
        email_from_address: general_settings?.email_from_address || "",
        email_from_name: general_settings?.email_from_name || "",
        driver: mail_config?.driver || "smtp",
        host: mail_config?.host || "",
        port: mail_config?.port || 587,
        username: mail_config?.username || "",
        password: mail_config?.password || "",
        encryption: mail_config?.encryption || "tls",
    });

    const handleTestEmail = async (event: FormEvent<HTMLButtonElement>) => {
        event.preventDefault();
        if (!testEmail) {
            toast.error("Please enter a recipient email.");
            return;
        }

        setSendingTest(true);

        try {
            const response = await axios.post(route("administrators.system-management.mail.test"), { to: testEmail });
            toast.success(response.data.message || "Test email sent successfully.");
            setTestEmail("");
        } catch (error: unknown) {
            const responseData =
                axios.isAxiosError(error) && typeof error.response?.data === "object" && error.response?.data !== null
                    ? (error.response.data as { message?: string; exception?: string })
                    : null;
            const errorMessage = responseData?.message || (error instanceof Error ? error.message : "Unexpected mail error.");
            const errorDetail = responseData?.exception || "";

            toast.error("Failed to send test email.", {
                description: errorDetail ? `${errorMessage}: ${errorDetail}` : errorMessage,
            });
        } finally {
            setSendingTest(false);
        }
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="mail"
            heading="Mail Server"
            description="Configure SMTP transport and validate outbound delivery."
        >
            <Card>
                <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle>Mail Configuration</CardTitle>
                            <CardDescription>Settings are written to database and synchronized to environment values.</CardDescription>
                        </div>
                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form: mailForm,
                                    routeName: "administrators.system-management.mail.update",
                                    successMessage: "Mail settings updated successfully.",
                                    errorMessage: "Failed to update mail settings.",
                                })
                            }
                            disabled={mailForm.processing}
                        >
                            {mailForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="email_from_name">From Name</Label>
                            <Input
                                id="email_from_name"
                                value={mailForm.data.email_from_name}
                                onChange={(event) => mailForm.setData("email_from_name", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="email_from_address">From Address</Label>
                            <Input
                                id="email_from_address"
                                type="email"
                                value={mailForm.data.email_from_address}
                                onChange={(event) => mailForm.setData("email_from_address", event.target.value)}
                            />
                        </div>
                    </div>

                    <Separator />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="driver">Driver</Label>
                            <Select value={mailForm.data.driver} onValueChange={(value) => mailForm.setData("driver", value)}>
                                <SelectTrigger id="driver">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="smtp">SMTP</SelectItem>
                                    <SelectItem value="mailgun">Mailgun</SelectItem>
                                    <SelectItem value="ses">Amazon SES</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="encryption">Encryption</Label>
                            <Select value={mailForm.data.encryption || "tls"} onValueChange={(value) => mailForm.setData("encryption", value)}>
                                <SelectTrigger id="encryption">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tls">TLS</SelectItem>
                                    <SelectItem value="ssl">SSL</SelectItem>
                                    <SelectItem value="none">None</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="host">Host</Label>
                            <Input id="host" value={mailForm.data.host} onChange={(event) => mailForm.setData("host", event.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="port">Port</Label>
                            <Input
                                id="port"
                                type="number"
                                value={mailForm.data.port}
                                onChange={(event) => mailForm.setData("port", Number(event.target.value) || 587)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="username">Username</Label>
                            <Input
                                id="username"
                                value={mailForm.data.username}
                                onChange={(event) => mailForm.setData("username", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={mailForm.data.password}
                                onChange={(event) => mailForm.setData("password", event.target.value)}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Send Test Email</CardTitle>
                    <CardDescription>Use this after saving to confirm delivery works.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-3 sm:flex-row">
                    <Input
                        type="email"
                        placeholder="recipient@example.com"
                        value={testEmail}
                        onChange={(event) => setTestEmail(event.target.value)}
                        className="sm:max-w-sm"
                    />
                    <Button variant="secondary" onClick={handleTestEmail} disabled={sendingTest || testEmail.trim() === ""}>
                        {sendingTest ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Mail className="mr-2 h-4 w-4" />}
                        Send Test
                    </Button>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}
