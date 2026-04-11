import { cn } from "@/lib/utils";
import { useBranding } from "@/lib/branding";
import { useEffect, useState } from "react";

interface Quote {
    id: string;
    text: string;
    category: "efficiency" | "student-tracking" | "workflow" | "time-saving";
    emphasis?: string[];
}

const facultyQuotes: Quote[] = [
    {
        id: "1",
        text: "Streamline your course management with automated attendance tracking and grade calculation",
        category: "efficiency",
        emphasis: ["automated", "grade calculation"],
    },
    {
        id: "2",
        text: "Monitor student progress in real-time with comprehensive analytics and performance dashboards",
        category: "student-tracking",
        emphasis: ["real-time", "comprehensive analytics"],
    },
    {
        id: "3",
        text: "Reduce administrative workload by 40% with digital workflows and automated notifications",
        category: "time-saving",
        emphasis: ["40%", "automated notifications"],
    },
    {
        id: "4",
        text: "Access complete student profiles including academic history, attendance, and performance metrics",
        category: "student-tracking",
        emphasis: ["complete", "performance metrics"],
    },
    {
        id: "5",
        text: "Collaborate seamlessly with department colleagues through shared resources and communication tools",
        category: "workflow",
        emphasis: ["seamlessly", "shared resources"],
    },
    {
        id: "6",
        text: "Save hours each week with bulk grade entry and automated report generation",
        category: "time-saving",
        emphasis: ["hours", "automated"],
    },
    {
        id: "7",
        text: "Stay organized with centralized course materials, assignments, and student communications",
        category: "workflow",
        emphasis: ["centralized", "organized"],
    },
    {
        id: "8",
        text: "Make data-driven decisions with insights into class performance and engagement trends",
        category: "student-tracking",
        emphasis: ["data-driven", "insights"],
    },
];

interface OnboardingPanelProps {
    className?: string;
}

export function OnboardingPanel({ className }: OnboardingPanelProps) {
    const { organizationShortName: orgShortName, themeColor } = useBranding();

    const [currentIndex, setCurrentIndex] = useState(0);
    const [isAnimating, setIsAnimating] = useState(false);

    const currentQuote = facultyQuotes[currentIndex];

    useEffect(() => {
        setIsAnimating(false);

        setCurrentIndex(Math.floor(Math.random() * facultyQuotes.length));
    }, []);

    // Minimal: no navigation handlers

    const renderQuoteText = (text: string, emphasis?: string[]) => {
        if (!emphasis || emphasis.length === 0) return text;

        let result = text;
        emphasis.forEach((word) => {
            const regex = new RegExp(`\\b${word}\\b`, "gi");
            result = result.replace(regex, `<span class=\"text-foreground\">${word}</span>`);
        });

        return <span className="text-muted-foreground" dangerouslySetInnerHTML={{ __html: result }} />;
    };

    // Minimal design: no icons/colors map needed
    // Clean, minimal Supabase-like aesthetic
    return (
        <div className={cn("relative flex h-full w-full items-center justify-center p-8", className)}>
            <div className="mx-auto max-w-md">
                <div className="text-muted-foreground/40 mb-6 font-serif text-4xl">“</div>

                <blockquote className="text-foreground mb-8 text-xl leading-relaxed font-medium">
                    {renderQuoteText(currentQuote.text, currentQuote.emphasis)}
                </blockquote>

                <div className="flex items-center gap-3">
                    <div className="h-8 w-8 rounded-full" style={{ background: `linear-gradient(135deg, ${themeColor}, #ffffff)` }}></div>
                    <div>
                        <div className="text-sm font-semibold">Faculty Member</div>
                        <div className="text-muted-foreground text-xs">{orgShortName} Faculty</div>
                    </div>
                </div>
            </div>
        </div>
    );
}
