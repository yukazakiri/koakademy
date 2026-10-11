import type { EmailTemplateConfiguration, EmailTemplateEditorData, EmailTemplateKey } from "@/lib/email-template-canvas";
import { EmailTemplateEditor } from "@/pages/administrators/system-management/components/email-template-canvas/email-template-editor";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";

declare global {
    interface Window {
        emailCanvasSaved: EmailTemplateConfiguration[];
        emailCanvasDiscarded: number;
    }
}

const parameters = new URLSearchParams(window.location.search);
const template = (parameters.get("template") ?? "status_update") as EmailTemplateKey;
const keys: EmailTemplateKey[] = ["status_update", "academic_verified", "assessment", "custom"];
const data: EmailTemplateEditorData = {
    definitions: keys.map((key) => ({
        key,
        label: key.replaceAll("_", " "),
        description: "Representative enrollment email with protected student data.",
        defaults: {
            notification: key,
            subject: "Application: {{ status }}",
            headline: key === "academic_verified" ? "Enrollment Verified" : key === "assessment" ? "Welcome to Test Academy!" : "Application update",
            custom_body: "First paragraph for {{ student_name }}.\n\nSecond paragraph with next steps.",
            button_label: key === "assessment" ? "Access Student Portal" : "Visit Student Portal",
            button_url: "",
            include_details: true,
            include_subjects: key === "academic_verified",
            include_tuition: key === "academic_verified",
        },
        capabilities: {
            headline: key === "status_update" || key === "custom",
            button_label: key !== "assessment",
            button_url: key === "status_update" || key === "custom",
            sections: key === "status_update" || key === "custom",
        },
    })),
    sample_tokens: {
        student_name: "Santos, Maria",
        status: "Interview Scheduled",
        step_name: "Interview",
        school_year: "2026-2027",
        semester: "1",
        program: "BSCS",
        portal_url: "http://portal.example.test",
        organization_name: "Test Academy",
    },
    branding: {
        organization_name: "Test Academy",
        logo_url: null,
        tagline: "Student services",
        theme_color: "#0f172a",
        support_email: "support@example.test",
        support_phone: null,
    },
};

window.emailCanvasSaved = [];
window.emailCanvasDiscarded = 0;
if (parameters.get("theme") === "dark") document.documentElement.classList.add("dark");

createRoot(document.getElementById("root")!).render(
    <div className="h-svh">
        <EmailTemplateEditor
            configuration={{
                notification: template,
                ...(parameters.has("builtin")
                    ? {}
                    : {
                          custom_body: "Hello {{ student_name }},\n\nFirst paragraph for {{ student_name }}.\n\nSecond paragraph with next steps.",
                      }),
            }}
            data={data}
            stepKey="interview"
            stepLabel="Interview milestone"
            terminal={template === "assessment"}
            policyName="Canvas browser fixture"
            previewUrl="/email-canvas-preview-fixture"
            onSave={(configuration) => {
                window.emailCanvasSaved.push(configuration);
            }}
            onCancel={() => {
                window.emailCanvasDiscarded++;
            }}
            readOnly={parameters.has("readonly")}
        />
    </div>,
);
