import "@xyflow/react/dist/style.css";
import { useState } from "react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";
import { WorkflowCanvas } from "../../resources/js/pages/administrators/system-management/enrollment-policy/components/workflow-canvas";
import type { WorkflowStep } from "../../resources/js/pages/administrators/system-management/enrollment-policy/types";

const initial: WorkflowStep[] = [
    {
        key: "submitted",
        label: "Application received",
        entry: true,
        terminal: false,
        status: "Pending",
        permission: "Update:StudentEnrollment",
        actions: [
            { key: "subjects", handler: "enrollment.assign_subjects", configuration: {} },
            { key: "tuition", handler: "enrollment.calculate_tuition", configuration: {} },
        ],
        transitions: [{ key: "approve", label: "Verify academics", to: "academic_verified", fallback: true, conditions: [] }],
    },
    {
        key: "academic_verified",
        label: "Academic clearance recorded",
        entry: false,
        terminal: false,
        status: "Verified By Dept Head",
        permission: "Update:StudentEnrollment",
        actions: [{ key: "academic_status", handler: "enrollment.verify_academic", configuration: {} }],
        transitions: [{ key: "payment_review", label: "Verify payment", to: "completed", fallback: true, conditions: [] }],
    },
    {
        key: "completed",
        label: "Enrolled",
        entry: false,
        terminal: true,
        status: "Verified By Cashier",
        outcome: "completed",
        actions: [
            { key: "payment_status", handler: "enrollment.verify_payment", configuration: { receipt_mode: "required" } },
            { key: "sync_student", handler: "enrollment.sync_student", configuration: { value: "enrolled" } },
            { key: "assessment", handler: "enrollment.generate_assessment", configuration: {} },
        ],
        transitions: [],
    },
];
function Fixture() {
    const [steps, setSteps] = useState(initial);
    return (
        <main className="flex min-w-0 flex-col gap-4 p-4">
            <h1 className="text-lg font-semibold">Enrollment workflow</h1>
            <WorkflowCanvas
                steps={steps}
                actions={Object.fromEntries(
                    [
                        ["enrollment.assign_subjects", "Assign subjects"],
                        ["enrollment.calculate_tuition", "Calculate tuition"],
                        ["enrollment.verify_academic", "Record academic verification"],
                        ["enrollment.verify_payment", "Record payment"],
                        ["enrollment.sync_student", "Synchronize student"],
                        ["enrollment.generate_assessment", "Queue assessment PDF"],
                    ].map(([key, label]) => [key, { key, label, operator_configurable: true }]),
                )}
                rules={{}}
                options={{ roles: [] }}
                readOnly={new URLSearchParams(location.search).has("readonly")}
                entryRules={[{ key: "channels", handler: "availability.channel", configuration: { allowed: ["administrator", "public"] } }]}
                onChange={setSteps}
            />
            <output data-workflow-json className="hidden">
                {JSON.stringify(steps)}
            </output>
        </main>
    );
}
createRoot(document.getElementById("root")!).render(<Fixture />);
