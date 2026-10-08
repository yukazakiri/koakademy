import { Shdr14 } from "@/components/ui/shdr-14";
import { cn } from "@/lib/utils";
import * as React from "react";

export interface AiCircleLogoProps extends React.SVGProps<SVGSVGElement> {
    size?: number;
    className?: string;
    useOrb?: boolean;
    state?: "idle" | "thinking" | "speaking";
}

/**
 * Universal KoAkademy AI Identity:
 * Mirrors the SHDR-14 dithered plasma dome orb from the AI chat welcome screen.
 *
 * - When useOrb={true}: renders the live interactive WebGL ShaderOrb (FAB, header).
 * - When useOrb={false}: renders a crisp, ultra-lightweight SVG vector replica
 *   matching SHDR-14's tone steps, ink/paper colors, and dither rings for
 *   sidebar navigation, message avatars, and tool indicators.
 */
export function AiCircleLogo({
    size,
    className,
    useOrb = false,
    state = "idle",
    ...props
}: AiCircleLogoProps) {
    if (useOrb) {
        const orbSize = size ?? 32;
        return (
            <div
                className={cn(
                    "inline-flex items-center justify-center shrink-0 overflow-hidden rounded-full select-none",
                    className
                )}
                style={{ width: orbSize, height: orbSize }}
            >
                <Shdr14 size={orbSize} state={state} ariaLabel="AI Copilot" />
            </div>
        );
    }

    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn("size-4 shrink-0 select-none", className)}
            {...props}
        >
            <defs>
                <radialGradient id="aiOrbCore" cx="35%" cy="35%" r="65%">
                    <stop offset="0%" stopColor="#cfe6ff" />
                    <stop offset="40%" stopColor="#818cf8" />
                    <stop offset="75%" stopColor="#4338ca" />
                    <stop offset="100%" stopColor="#0e101f" />
                </radialGradient>
                <radialGradient id="aiOrbRim" cx="50%" cy="50%" r="50%">
                    <stop offset="70%" stopColor="transparent" />
                    <stop offset="100%" stopColor="#cfe6ff" stopOpacity="0.45" />
                </radialGradient>
                <filter id="aiOrbGlow" x="-30%" y="-30%" width="160%" height="160%">
                    <feGaussianBlur stdDeviation="0.6" result="blur" />
                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
            </defs>
            {/* Outer Orb Base */}
            <circle cx="12" cy="12" r="10" fill="url(#aiOrbCore)" />
            {/* SHDR-14 Demoscene Dither Rings / Matrix Dots */}
            <circle
                cx="12"
                cy="12"
                r="7.5"
                stroke="#cfe6ff"
                strokeWidth="0.85"
                strokeDasharray="1.6 2"
                strokeOpacity="0.85"
            />
            <circle
                cx="12"
                cy="12"
                r="5"
                stroke="#a9b9ff"
                strokeWidth="0.85"
                strokeDasharray="1.2 1.6"
                strokeOpacity="0.75"
            />
            <circle
                cx="12"
                cy="12"
                r="2.5"
                stroke="#ffffff"
                strokeWidth="0.75"
                strokeDasharray="0.8 1.2"
                strokeOpacity="0.9"
            />
            {/* Specular Light Source */}
            <circle
                cx="9.5"
                cy="9"
                r="1.4"
                fill="#ffffff"
                fillOpacity="0.95"
                filter="url(#aiOrbGlow)"
            />
            <circle cx="15" cy="14.5" r="0.9" fill="#cfe6ff" fillOpacity="0.5" />
            {/* Outer Fresnel Rim */}
            <circle
                cx="12"
                cy="12"
                r="9.75"
                stroke="url(#aiOrbRim)"
                strokeWidth="0.5"
                fill="none"
            />
        </svg>
    );
}

export default AiCircleLogo;
