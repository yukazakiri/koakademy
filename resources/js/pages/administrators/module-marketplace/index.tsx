import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { disable, enable } from "@/routes/administrators/module-marketplace";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import { Check, CheckCircle2, Copy, ExternalLink, PackageCheck, PackageOpen, Power, RefreshCw, XCircle } from "lucide-react";
import { useState } from "react";

interface MarketplaceModule {
    name: string;
    alias: string;
    version: string;
    installed_version: string | null;
    description: string;
    author: string;
    license: string;
    composer_package: string | null;
    repository: string | null;
    homepage: string | null;
    installed: boolean;
    enabled: boolean;
    status: "not_installed" | "disabled" | "enabled" | "restart_required";
    restart_required: boolean;
    update_available: boolean;
    installation_source: "composer" | "source" | null;
    update_command: string | null;
    compatible: boolean;
    compatibility_errors: string[];
    asset_url: string | null;
    released_at: string | null;
}

interface MarketplaceProps {
    enabled: boolean;
    registry_url: string | null;
    registry_error: string | null;
    modules: MarketplaceModule[];
}

interface Props {
    user: User;
    marketplace: MarketplaceProps;
}

export default function ModuleMarketplacePage({ user, marketplace }: Props) {
    const [processingModule, setProcessingModule] = useState<string | null>(null);
    const [selectedUpdate, setSelectedUpdate] = useState<MarketplaceModule | null>(null);
    const [copiedUpdateCommand, setCopiedUpdateCommand] = useState(false);
    const restartRequiredModules = marketplace.modules.filter((module) => module.restart_required);

    function openUpdateDialog(module: MarketplaceModule): void {
        setSelectedUpdate(module);
        setCopiedUpdateCommand(false);
    }

    function closeUpdateDialog(): void {
        setSelectedUpdate(null);
        setCopiedUpdateCommand(false);
    }

    async function copyUpdateCommand(): Promise<void> {
        const command = selectedUpdate?.update_command;

        if (!command || !navigator.clipboard) {
            return;
        }

        try {
            await navigator.clipboard.writeText(command);
            setCopiedUpdateCommand(true);
        } catch {
            setCopiedUpdateCommand(false);
        }
    }

    function changeStatus(module: MarketplaceModule): void {
        const nextEnabled = !module.enabled;
        setProcessingModule(module.name);

        router.post(
            (nextEnabled ? enable : disable).url(module.name),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingModule(null),
            },
        );
    }

    return (
        <AdminLayout user={user} title="Module Marketplace">
            <Head title="Module Marketplace" />

            <div className="mx-auto w-full max-w-[80rem] space-y-6 pb-8">
                <header className="border-border/70 bg-card/85 overflow-hidden rounded-2xl border px-5 py-6 shadow-sm backdrop-blur-xl sm:px-7 sm:py-8">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <span className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl">
                                <PackageOpen className="size-5" aria-hidden="true" />
                            </span>
                            <p className="text-muted-foreground mt-5 text-xs font-semibold tracking-[0.1em] uppercase">Platform extensions</p>
                            <h1 className="text-foreground mt-2 text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">Module Marketplace</h1>
                            <p className="text-muted-foreground mt-3 max-w-2xl text-sm leading-6 sm:text-base">
                                Review the signed public catalog and control modules that are already included in this application image.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={marketplace.enabled ? "default" : "outline"} className="w-fit">
                                {marketplace.enabled ? "Marketplace enabled" : "Marketplace disabled"}
                            </Badge>
                            {marketplace.registry_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <a href="?refresh=1">
                                        <RefreshCw className="size-3.5" /> Refresh catalog
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>
                </header>

                {marketplace.registry_error && (
                    <div className="border-destructive/30 bg-destructive/10 text-destructive rounded-xl border px-4 py-3 text-sm" role="alert">
                        {marketplace.registry_error}
                    </div>
                )}

                {restartRequiredModules.length > 0 && (
                    <div
                        className="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-200"
                        role="status"
                    >
                        Restart or redeploy the application to apply changes to: {restartRequiredModules.map((module) => module.name).join(", ")}.
                    </div>
                )}

                <div className="border-border/70 bg-muted/30 text-muted-foreground rounded-xl border px-4 py-3 text-sm leading-6">
                    New Composer packages are installed during an image build. The marketplace does not run Composer in a live container, so a package
                    marked as not installed needs a new image before it can be enabled here. After changing a module state, restart or redeploy the
                    application so every long-running worker loads the new providers and routes.
                </div>

                {marketplace.modules.length > 0 ? (
                    <section aria-label="Available modules" className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {marketplace.modules.map((module) => {
                            const isProcessing = processingModule === module.name;
                            const statusLabel = {
                                not_installed: "Not installed",
                                disabled: "Disabled",
                                enabled: "Enabled",
                                restart_required: "Restart required",
                            }[module.status];

                            return (
                                <Card key={module.name} className="border-border/70 flex h-full flex-col">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle className="flex items-center gap-2">
                                                    {module.name}
                                                    {module.enabled && <CheckCircle2 className="size-4 text-emerald-600" aria-label="Enabled" />}
                                                </CardTitle>
                                                <CardDescription className="mt-1">{module.composer_package ?? module.alias}</CardDescription>
                                            </div>
                                            <Badge variant={module.status === "enabled" ? "default" : module.installed ? "outline" : "secondary"}>
                                                {statusLabel}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 space-y-4">
                                        <p className="text-muted-foreground text-sm leading-6">{module.description || "No description provided."}</p>
                                        <dl className="text-muted-foreground grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                            <div>
                                                <dt className="font-medium">Catalog version</dt>
                                                <dd className="text-foreground mt-0.5">{module.version}</dd>
                                            </div>
                                            <div>
                                                <dt className="font-medium">Installed version</dt>
                                                <dd className="text-foreground mt-0.5">{module.installed_version ?? "Not installed"}</dd>
                                            </div>
                                            <div>
                                                <dt className="font-medium">Compatibility</dt>
                                                <dd className="mt-0.5">
                                                    {module.compatible ? (
                                                        <span className="inline-flex items-center gap-1 text-emerald-600">
                                                            <CheckCircle2 className="size-3" /> Compatible
                                                        </span>
                                                    ) : (
                                                        <span className="text-destructive inline-flex items-center gap-1">
                                                            <XCircle className="size-3" /> Check requirements
                                                        </span>
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="font-medium">License</dt>
                                                <dd className="text-foreground mt-0.5">{module.license}</dd>
                                            </div>
                                            {module.update_available && (
                                                <div className="col-span-2">
                                                    <span className="text-amber-700 dark:text-amber-300">Update available in the catalog.</span>
                                                </div>
                                            )}
                                        </dl>
                                        {!module.compatible && module.compatibility_errors.length > 0 && (
                                            <ul className="text-destructive list-disc space-y-1 pl-4 text-xs">
                                                {module.compatibility_errors.map((error) => (
                                                    <li key={error}>{error}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </CardContent>
                                    <CardFooter className="flex flex-wrap justify-between gap-2">
                                        <div className="flex flex-wrap gap-2">
                                            {module.repository && (
                                                <Button variant="ghost" size="sm" asChild>
                                                    <a href={module.repository} target="_blank" rel="noreferrer">
                                                        Source <ExternalLink className="size-3.5" />
                                                    </a>
                                                </Button>
                                            )}
                                            {module.asset_url && !module.installed && (
                                                <Button variant="ghost" size="sm" asChild>
                                                    <a href={module.asset_url} target="_blank" rel="noreferrer">
                                                        Release <ExternalLink className="size-3.5" />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            {module.update_available && (
                                                <Button variant="outline" size="sm" onClick={() => openUpdateDialog(module)}>
                                                    <RefreshCw className="size-3.5" /> Update
                                                </Button>
                                            )}
                                            {module.installed ? (
                                                <Button
                                                    variant={module.enabled ? "outline" : "default"}
                                                    size="sm"
                                                    disabled={isProcessing || !marketplace.enabled || (!module.enabled && !module.compatible)}
                                                    onClick={() => changeStatus(module)}
                                                >
                                                    {isProcessing ? <RefreshCw className="size-3.5 animate-spin" /> : <Power className="size-3.5" />}
                                                    {module.enabled ? "Disable" : "Enable"}
                                                </Button>
                                            ) : (
                                                <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                                                    <PackageCheck className="size-3.5" /> Add with Composer, then rebuild
                                                </span>
                                            )}
                                        </div>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </section>
                ) : (
                    <div className="border-border bg-card rounded-2xl border border-dashed px-6 py-12 text-center">
                        <PackageOpen className="text-muted-foreground mx-auto size-8" />
                        <p className="text-foreground mt-3 font-medium">No modules are currently listed.</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {marketplace.registry_url
                                ? "Refresh the catalog or check the registry configuration."
                                : "Configure a signed module registry to publish modules here."}
                        </p>
                        {marketplace.registry_url && (
                            <Button variant="link" size="sm" asChild className="mt-2">
                                <a href={marketplace.registry_url} target="_blank" rel="noreferrer">
                                    Open registry
                                </a>
                            </Button>
                        )}
                    </div>
                )}

                <Dialog open={selectedUpdate !== null} onOpenChange={(open) => !open && closeUpdateDialog()}>
                    <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                        <DialogHeader>
                            <DialogTitle>
                                {selectedUpdate ? `Update ${selectedUpdate.name} to ${selectedUpdate.version}` : "Update module"}
                            </DialogTitle>
                            <DialogDescription>
                                Module code is delivered through the application image. This action prepares the update; it does not change the
                                running container.
                            </DialogDescription>
                        </DialogHeader>

                        {selectedUpdate && (
                            <div className="space-y-5">
                                {selectedUpdate.installation_source === "composer" && selectedUpdate.update_command ? (
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">Run this in the KoAkademy application repository:</p>
                                        <div className="bg-muted flex items-start gap-2 rounded-lg border p-3">
                                            <code className="min-w-0 flex-1 text-xs leading-5 break-all">{selectedUpdate.update_command}</code>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="shrink-0"
                                                onClick={copyUpdateCommand}
                                                aria-label={`Copy update command for ${selectedUpdate.name}`}
                                            >
                                                {copiedUpdateCommand ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                                                {copiedUpdateCommand ? "Copied" : "Copy"}
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="border-border bg-muted/40 space-y-2 rounded-lg border p-4 text-sm leading-6">
                                        <p className="font-medium">This is a source-tree module.</p>
                                        <p>
                                            Update <code>Modules/{selectedUpdate.name}</code> in the KoAkademy repository, or migrate it to its
                                            standalone Composer package before rebuilding the application image. The catalog release cannot update
                                            source files in a running container.
                                        </p>
                                    </div>
                                )}

                                <ol className="text-muted-foreground list-decimal space-y-2 pl-5 text-sm leading-6">
                                    <li>Update the source or run the Composer command, then commit the dependency lockfile.</li>
                                    <li>Build and publish a new KoAkademy image.</li>
                                    <li>Redeploy every HTTP, queue, and scheduler replica.</li>
                                    <li>
                                        After every replica is healthy, acknowledge the rollout with{" "}
                                        <code>php artisan modules:sync-statuses --acknowledge-restart</code>.
                                    </li>
                                </ol>
                            </div>
                        )}

                        <DialogFooter>
                            <Button variant="outline" onClick={closeUpdateDialog}>
                                Close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    );
}
