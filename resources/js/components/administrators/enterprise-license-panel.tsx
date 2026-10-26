import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { update } from "@/routes/administrators/enterprise/capabilities";
import { store } from "@/routes/administrators/enterprise/licenses";
import { Link, router, useForm } from "@inertiajs/react";
import { type FormEvent, useId, useRef, useState } from "react";

export interface EnterpriseMarketplace {
    configured: boolean;
    installation_id: string;
    school: { id: number; name: string; reference: string } | null;
    license_status: "valid" | "invalid" | "not_imported";
    license: {
        id: string;
        revision: number;
        customer: string;
        maintenance_until: string;
        maintenance_active: boolean;
        grants: string[];
    } | null;
    capabilities: {
        key: string;
        name: string;
        description: string;
        module: string;
        enabled: boolean;
        licensed: boolean;
        access: { allowed: boolean; reason: string };
        entry_url: string | null;
    }[];
}

export function EnterpriseLicensePanel({ enterprise }: { enterprise: EnterpriseMarketplace }) {
    const fileId = useId();
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ license: File | null }>({ license: null });
    const [changing, setChanging] = useState<string | null>(null);
    const [capabilityError, setCapabilityError] = useState<string | null>(null);

    function importLicense(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!enterprise.school) return;

        form.post(store.url({ school: enterprise.school.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                if (fileInput.current) fileInput.current.value = "";
            },
        });
    }

    function changeCapability(key: string, enabled: boolean) {
        if (!enterprise.school) return;
        setChanging(key);
        setCapabilityError(null);
        router.put(
            update.url({ school: enterprise.school.id, capability: key }),
            { enabled },
            {
                preserveScroll: true,
                onError: (errors) => setCapabilityError(errors.capability ?? "Could not change this capability. Please try again."),
                onFinish: () => setChanging(null),
            },
        );
    }

    if (!enterprise.school) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>School licenses</CardTitle>
                    <CardDescription>Select an active school with the organization switcher to manage its enterprise capabilities.</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <section aria-label="School licenses and purchased capabilities" className="grid items-start gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle>License for {enterprise.school.name}</CardTitle>
                        <Badge variant={enterprise.license ? "default" : "outline"}>
                            {enterprise.license ? "Activated" : enterprise.license_status === "invalid" ? "Needs attention" : "No license"}
                        </Badge>
                    </div>
                    <CardDescription>Import the license issued for this school. Activation works offline.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {enterprise.license && (
                        <div className="space-y-2 text-sm">
                            <p className="font-medium">{enterprise.license.customer}</p>
                            <p className="text-muted-foreground">
                                Maintenance {enterprise.license.maintenance_active ? "through" : "ended"}{" "}
                                {new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date(enterprise.license.maintenance_until))}.
                                {!enterprise.license.maintenance_active && " Purchased capabilities remain available."}
                            </p>
                        </div>
                    )}
                    {enterprise.license_status === "invalid" && (
                        <p className="text-destructive text-sm" role="alert">
                            The stored license could not be verified. Check the vendor verification key and import a valid license.
                        </p>
                    )}
                    {!enterprise.configured && (
                        <p className="text-muted-foreground text-sm">
                            License verification needs to be configured by your installation operator before a license can be imported.
                        </p>
                    )}
                    <form onSubmit={importLicense} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor={fileId}>License file</Label>
                            <Input
                                ref={fileInput}
                                id={fileId}
                                type="file"
                                accept=".json,.license,application/json"
                                disabled={!enterprise.configured || form.processing}
                                aria-invalid={Boolean(form.errors.license)}
                                aria-describedby={form.errors.license ? `${fileId}-error` : undefined}
                                onChange={(event) => {
                                    form.setData("license", event.target.files?.[0] ?? null);
                                    form.clearErrors("license");
                                }}
                            />
                            {form.errors.license && (
                                <p id={`${fileId}-error`} className="text-destructive text-sm" role="alert">
                                    {form.errors.license}
                                </p>
                            )}
                        </div>
                        <Button type="submit" disabled={!enterprise.configured || !form.data.license || form.processing}>
                            {form.processing ? "Importing license…" : "Import license"}
                        </Button>
                    </form>
                    <details className="text-sm">
                        <summary className="text-muted-foreground cursor-pointer">Details for requesting a license</summary>
                        <dl className="mt-4 space-y-4">
                            <div>
                                <dt className="font-medium">Installation ID</dt>
                                <dd className="text-muted-foreground mt-1 font-mono text-xs break-all">{enterprise.installation_id}</dd>
                            </div>
                            <div>
                                <dt className="font-medium">School reference</dt>
                                <dd className="text-muted-foreground mt-1 font-mono text-xs break-all">{enterprise.school.reference}</dd>
                            </div>
                        </dl>
                    </details>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Enterprise capabilities</CardTitle>
                    <CardDescription>Turn on purchased capabilities for this school. Changes apply to the next request.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {capabilityError && (
                        <p role="alert" className="text-destructive text-sm">
                            {capabilityError}
                        </p>
                    )}
                    {enterprise.capabilities.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            This release has no enterprise packages installed. Install an enterprise release to add capabilities.
                        </p>
                    )}
                    {enterprise.capabilities.map((capability) => (
                        <div key={capability.key} className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold">{capability.name}</h3>
                                <Badge variant={capability.access.allowed ? "default" : "outline"}>
                                    {capability.licensed ? "Included in license" : "License required"}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">{capability.description}</p>
                            <p className="text-muted-foreground text-sm" role="status">
                                {capability.access.reason}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={changing !== null || (!capability.enabled && !capability.licensed)}
                                    onClick={() => changeCapability(capability.key, !capability.enabled)}
                                >
                                    {changing === capability.key ? "Saving…" : capability.enabled ? "Turn off" : "Enable for school"}
                                </Button>
                                {capability.entry_url && capability.access.allowed && (
                                    <Button nativeButton={false} render={<Link href={capability.entry_url} />}>
                                        Open reports
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>
        </section>
    );
}
