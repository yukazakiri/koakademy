import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { router } from "@inertiajs/react";
import { Plug } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface ExperimentalTabProps {
    experimentalAvailable: string[];
    experimentalFeatures: string[];
    paths: {
        experimental_features: string;
    };
}

export function ExperimentalTab({ experimentalAvailable, experimentalFeatures, paths }: ExperimentalTabProps) {
    const [features, setFeatures] = useState<string[]>(experimentalFeatures);

    const handleToggleExperimentalFeature = (featureKey: string, enabled: boolean) => {
        setFeatures((prev) => {
            const next = enabled ? [...new Set([...prev, featureKey])] : prev.filter((key) => key !== featureKey);

            router.post(
                paths.experimental_features,
                { features: next },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success("Experimental features updated.");
                    },
                    onError: () => {
                        toast.error("Failed to update experimental features.");
                    },
                },
            );

            return next;
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Plug className="h-5 w-5" />
                    Experimental Features
                </CardTitle>
                <CardDescription>Opt in to features that are still in testing.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {experimentalAvailable.map((featureKey) => {
                    const isFacultyFeature = featureKey.includes("faculty-");
                    const isStudentFeature = featureKey.includes("student-");
                    const roleLabel = isFacultyFeature ? "Faculty" : isStudentFeature ? "Student" : null;
                    const isDeveloperMode = featureKey.includes("developer-mode");
                    const featureName = featureKey
                        .replace("onboarding-", "")
                        .replace(/-/g, " ")
                        .replace(/\b\w/g, (letter) => letter.toUpperCase());

                    return (
                        <div key={featureKey} className="flex items-center justify-between rounded-lg border p-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="font-medium">{featureName}</p>
                                    {roleLabel && (
                                        <Badge variant={isFacultyFeature ? "default" : "secondary"} className="text-[10px]">
                                            {roleLabel}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    {isDeveloperMode
                                        ? "Enable API key creation for programmatic portal access. This feature is intended for advanced users."
                                        : `Toggle access to ${featureKey.replace("onboarding-", "").replace(/-/g, " ")}.`}
                                </p>
                                {isDeveloperMode && (
                                    <p className="mt-1 text-xs text-amber-600 dark:text-amber-500">
                                        API keys should be kept secret. Never share them publicly.
                                    </p>
                                )}
                            </div>
                            <Switch
                                checked={features.includes(featureKey)}
                                onCheckedChange={(checked) => handleToggleExperimentalFeature(featureKey, checked)}
                            />
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
