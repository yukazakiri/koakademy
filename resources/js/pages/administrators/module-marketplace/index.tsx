import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { disable, enable } from "@/routes/administrators/module-marketplace";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import { CheckCircle2, ExternalLink, PackageCheck, PackageOpen, Power, RefreshCw, XCircle } from "lucide-react";
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

    function changeStatus(module: MarketplaceModule): void {
        const nextEnabled = !module.enabled;
        setProcessingModule(module.name);

        router.post((nextEnabled ? enable : disable).url(module.name), {}, {
            preserveScroll: true,
            onFinish: () => setProcessingModule(null),
        });
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
                        <Badge variant={marketplace.enabled ? "default" : "outline"} className="w-fit">
                            {marketplace.enabled ? "Marketplace enabled" : "Marketplace disabled"}
                        </Badge>
                    </div>
                </header>

                {marketplace.registry_error && (
                    <div className="border-destructive/30 bg-destructive/10 text-destructive rounded-xl border px-4 py-3 text-sm" role="alert">
                        {marketplace.registry_error}
                    </div>
                )}

                <div className="border-border/70 bg-muted/30 text-muted-foreground rounded-xl border px-4 py-3 text-sm leading-6">
                    New Composer packages are installed during an image build. The marketplace does not run Composer in a live container, so a
                    package marked as not installed needs a new image before it can be enabled here. After changing a module state, restart or
                    redeploy the application so every long-running worker loads the new providers and routes.
                </div>

                {marketplace.modules.length > 0 ? (
                    <section aria-label="Available modules" className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {marketplace.modules.map((module) => {
                            const isProcessing = processingModule === module.name;

                            return (
                                <Card key={module.name} className="border-border/70 flex h-full flex-col">
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <CardTitle className="flex items-center gap-2">
                                                    {module.name}
                                                    {module.enabled && <CheckCircle2 className="text-emerald-600 size-4" aria-label="Enabled" />}
                                                </CardTitle>
                                                <CardDescription className="mt-1">{module.composer_package ?? module.alias}</CardDescription>
                                            </div>
                                            <Badge variant={module.installed ? (module.enabled ? "default" : "outline") : "secondary"}>
                                                {module.installed ? (module.enabled ? "Enabled" : "Disabled") : "Image required"}
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
                                                        <span className="text-emerald-600 inline-flex items-center gap-1">
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
                                                <PackageCheck className="size-3.5" /> Rebuild image to install
                                            </span>
                                        )}
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
                            {marketplace.registry_url ? "Refresh the catalog or check the registry configuration." : "Configure a signed module registry to publish modules here."}
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
            </div>
        </AdminLayout>
    );
}
