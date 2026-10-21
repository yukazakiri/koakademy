import type { BetaAccess } from "@/components/profile/beta-access-panel";
import { BetaAccessPanel } from "@/components/profile/beta-access-panel";
import { FeatureInspector } from "@/pages/administrators/feature-toggles/inspector";
import type { ManagedFeature } from "@/pages/administrators/feature-toggles/types";
import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";

function Fixture({ beta, feature, inspector }: { beta: BetaAccess; feature: ManagedFeature; inspector: boolean }) {
    return (
        <main className="mx-auto flex max-w-4xl min-w-0 flex-col gap-6 p-4">
            {inspector ? (
                <FeatureInspector feature={feature} roles={[{ value: "student", label: "Student" }]} schools={[]} groups={[]} />
            ) : (
                <BetaAccessPanel beta={beta} />
            )}
        </main>
    );
}

await createInertiaApp({
    page: JSON.parse(document.getElementById("page-data")!.textContent!),
    resolve: () => Fixture,
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
});
