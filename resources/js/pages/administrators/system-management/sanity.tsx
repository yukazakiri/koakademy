import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { useForm } from "@inertiajs/react";
import { AlertCircle, Loader2, Save } from "lucide-react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps } from "./types";

interface SanityFormData {
    project_id: string;
    dataset: string;
    token: string;
    api_version: string;
    use_cdn: boolean;
}

export default function SystemManagementSanityPage({ user, sanity_config, access }: SystemManagementPageProps) {
    const sanityForm = useForm<SanityFormData>({
        project_id: sanity_config?.project_id || "",
        dataset: sanity_config?.dataset || "",
        token: sanity_config?.token || "",
        api_version: sanity_config?.api_version || "",
        use_cdn: sanity_config?.use_cdn || false,
    });

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="sanity"
            heading="Content (Sanity)"
            description="Set Sanity project credentials used by content synchronization."
        >
            <Card>
                <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle>Sanity Connection</CardTitle>
                            <CardDescription>Configure project-level access to your Sanity dataset.</CardDescription>
                        </div>
                        <Button
                            onClick={() =>
                                submitSystemForm({
                                    form: sanityForm,
                                    routeName: "administrators.system-management.sanity.update",
                                    successMessage: "Sanity settings updated successfully.",
                                    errorMessage: "Failed to update Sanity settings.",
                                })
                            }
                            disabled={sanityForm.processing}
                        >
                            {sanityForm.processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            Save
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="project_id">Project ID</Label>
                            <Input
                                id="project_id"
                                value={sanityForm.data.project_id}
                                onChange={(event) => sanityForm.setData("project_id", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="dataset">Dataset</Label>
                            <Input
                                id="dataset"
                                value={sanityForm.data.dataset}
                                onChange={(event) => sanityForm.setData("dataset", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="api_version">API Version</Label>
                            <Input
                                id="api_version"
                                value={sanityForm.data.api_version}
                                onChange={(event) => sanityForm.setData("api_version", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="token">Token</Label>
                            <Input
                                id="token"
                                type="password"
                                value={sanityForm.data.token}
                                onChange={(event) => sanityForm.setData("token", event.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="space-y-1">
                            <Label className="text-base">Use CDN</Label>
                            <p className="text-muted-foreground text-sm">Speeds up read requests by serving cached responses.</p>
                        </div>
                        <Switch checked={sanityForm.data.use_cdn} onCheckedChange={(checked) => sanityForm.setData("use_cdn", checked)} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Status</CardTitle>
                    <CardDescription>Connection readiness based on required fields.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Project</span>
                        <code className="bg-muted rounded px-2 py-1">{sanityForm.data.project_id || "Not configured"}</code>
                    </div>
                    <Separator />
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Dataset</span>
                        <Badge variant="outline">{sanityForm.data.dataset || "Not configured"}</Badge>
                    </div>
                    <Separator />
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Connection</span>
                        {sanityForm.data.project_id && sanityForm.data.dataset ? (
                            <Badge className="bg-green-600 text-white hover:bg-green-600">Ready</Badge>
                        ) : (
                            <span className="inline-flex items-center gap-1 text-amber-600">
                                <AlertCircle className="h-4 w-4" />
                                Incomplete
                            </span>
                        )}
                    </div>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}
