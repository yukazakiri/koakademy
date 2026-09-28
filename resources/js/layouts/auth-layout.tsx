import { Head, Link, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { ArrowLeft } from "lucide-react";
import { useState, type ComponentProps, type ReactNode } from "react";

import { AnnouncementBanner } from "@/components/announcement-banner";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { ThemeToggle } from "@/components/theme-toggle";
import { TransitionWrapper } from "@/components/transition-wrapper";
import { resolveBranding, type Branding } from "@/lib/branding";
import { cn } from "@/lib/utils";

export interface AuthLayoutProps {
    children: ReactNode;
    title?: ReactNode;
    description?: ReactNode;
    badge?: ReactNode;
    icon?: ReactNode;
    announcements?: ComponentProps<typeof AnnouncementBanner>["announcements"];
    metaTitle?: string;
    metaDescription?: string;
    maxWidth?: "sm" | "md" | "lg";
    showBackToLogin?: boolean;
    showFooter?: boolean;
    customRightPanel?: ReactNode;
}

const TILE_POSITIONS = [
    { top: "12%", left: "18%", delay: 0 },
    { top: "24%", left: "74%", delay: 1.2 },
    { top: "42%", left: "32%", delay: 2.4 },
    { top: "58%", left: "82%", delay: 0.7 },
    { top: "76%", left: "20%", delay: 3.1 },
    { top: "18%", left: "88%", delay: 1.8 },
    { top: "82%", left: "64%", delay: 2.6 },
    { top: "64%", left: "14%", delay: 1.5 },
    { top: "35%", left: "60%", delay: 3.5 },
];

function AnimatedGridTiles() {
    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
            {TILE_POSITIONS.map((pos, idx) => (
                <motion.div
                    key={idx}
                    className="absolute size-9 rounded-md border border-primary/20 bg-primary/10 backdrop-blur-xs"
                    style={{ top: pos.top, left: pos.left }}
                    animate={{
                        opacity: [0.15, 0.7, 0.15],
                        scale: [1, 1.05, 1],
                    }}
                    transition={{
                        duration: 4.5,
                        repeat: Infinity,
                        ease: "easeInOut",
                        delay: pos.delay,
                    }}
                />
            ))}
        </div>
    );
}

export function AuthLayout({
    children,
    title,
    description,
    badge,
    icon,
    announcements,
    metaTitle,
    metaDescription,
    maxWidth = "sm",
    showBackToLogin = false,
    showFooter = true,
    customRightPanel,
}: AuthLayoutProps) {
    const page = usePage<{
        branding?: Partial<Branding> | null;
        announcements?: ComponentProps<typeof AnnouncementBanner>["announcements"];
    }>();

    const branding = resolveBranding(page.props.branding);
    const appName = branding.appName;
    const organizationName = branding.organizationName;
    const organizationShortName = branding.organizationShortName;
    const authLayout = branding.authLayout;
    const isSplitLayout = authLayout === "split";

    const [headerLogoError, setHeaderLogoError] = useState(false);
    const [centerLogoError, setCenterLogoError] = useState(false);

    const effectiveAnnouncements = announcements ?? page.props.announcements;
    const hasAnnouncements = Boolean(
        Array.isArray(effectiveAnnouncements)
            ? effectiveAnnouncements.length > 0
            : effectiveAnnouncements && Array.isArray((effectiveAnnouncements as { data?: unknown[] }).data) && (effectiveAnnouncements as { data: unknown[] }).data.length > 0
    );

    const maxWidthClass = {
        sm: "max-w-[380px]",
        md: "max-w-md",
        lg: "max-w-lg",
    }[maxWidth];

    const currentYear = new Date().getFullYear();

    return (
        <div className="relative min-h-svh w-full overflow-x-hidden bg-background text-foreground selection:bg-primary selection:text-primary-foreground flex flex-col lg:flex-row">
            <Head title={metaTitle ? `${metaTitle} - ${appName}` : `${appName} - Academic Management Portal`}>
                <meta
                    name="description"
                    content={
                        metaDescription ??
                        (branding.tagline
                            ? `${organizationName} - ${branding.tagline}`
                            : `The official academic portal for ${organizationName}. Access student records, grades, schedules, and faculty resources.`)
                    }
                />
            </Head>

            {/* Left Column / Form Section */}
            <div className={cn(
                "relative z-10 flex min-h-svh w-full flex-col justify-between p-4 sm:p-8 lg:p-12 xl:p-14",
                isSplitLayout ? "lg:w-1/2" : "max-w-2xl mx-auto"
            )}>
                {/* Animated Grid & Ambient Accent Backdrop on the Form side */}
                <div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
                    <div
                        className="absolute inset-0 bg-[linear-gradient(to_right,color-mix(in_oklch,var(--color-border)_45%,transparent)_1px,transparent_1px),linear-gradient(to_bottom,color-mix(in_oklch,var(--color-border)_45%,transparent)_1px,transparent_1px)] bg-[size:36px_36px]"
                        style={{
                            maskImage: "radial-gradient(ellipse 65% 55% at 50% 50%, #000 60%, transparent 100%)",
                            WebkitMaskImage: "radial-gradient(ellipse 65% 55% at 50% 50%, #000 60%, transparent 100%)",
                        }}
                    />
                    {/* Theme-based ambient glow */}
                    <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 size-96 rounded-full bg-primary/10 blur-3xl pointer-events-none" />
                    <div className="absolute bottom-1/4 left-1/3 size-80 rounded-full bg-accent/20 blur-3xl pointer-events-none" />
                    <AnimatedGridTiles />
                </div>

                {/* Header Navbar */}
                <header className="relative z-10 flex items-center justify-between gap-4">
                    <div>
                        {showBackToLogin ? (
                            <Link
                                href="/login"
                                className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card/70 px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:bg-accent hover:text-accent-foreground"
                            >
                                <ArrowLeft className="size-3.5" />
                                <span>Sign in</span>
                            </Link>
                        ) : (
                            <Link href="/" className="group flex items-center gap-2.5 transition-opacity hover:opacity-90">
                                <div className="flex size-8 items-center justify-center rounded-xl border border-border bg-card shadow-xs ring-1 ring-primary/20 overflow-hidden">
                                    {branding.logo && !headerLogoError ? (
                                        <img
                                            src={branding.logo}
                                            alt={`${organizationShortName} Logo`}
                                            className="size-5 object-contain"
                                            onError={() => setHeaderLogoError(true)}
                                        />
                                    ) : (
                                        <span className="text-xs font-bold text-foreground">
                                            {organizationShortName.slice(0, 2).toUpperCase()}
                                        </span>
                                    )}
                                </div>
                                <span className="text-sm font-semibold tracking-tight text-foreground group-hover:text-primary transition-colors">
                                    {appName}
                                </span>
                            </Link>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <ThemeToggle />
                    </div>
                </header>

                {/* Center Form Area */}
                <main className="relative z-10 my-auto flex w-full flex-1 flex-col items-center justify-center py-8">
                    <div className={cn("w-full", maxWidthClass)}>
                        {/* System Announcements */}
                        {hasAnnouncements && effectiveAnnouncements && (
                            <div className="mb-6 w-full">
                                <AnnouncementBanner announcements={effectiveAnnouncements} />
                            </div>
                        )}

                        {/* Card vs Split/Minimal layout */}
                        {authLayout === "card" ? (
                            <Frame variant="default" className="shadow-2xl backdrop-blur-md [--frame-radius:var(--radius-2xl)] border-border/80">
                                <FramePanel className="border-border bg-card/85 p-6 sm:p-8">
                                    {/* Brand Logo Squircle */}
                                    <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl border border-border bg-card shadow-md ring-1 ring-primary/25 backdrop-blur-xs overflow-hidden">
                                        {icon ?? (
                                            branding.logo && !centerLogoError ? (
                                                <img
                                                    src={branding.logo}
                                                    alt={`${organizationShortName} Logo`}
                                                    className="size-6 object-contain drop-shadow-xs"
                                                    onError={() => setCenterLogoError(true)}
                                                />
                                            ) : (
                                                <span className="text-sm font-bold tracking-wider text-foreground">
                                                    {organizationShortName.slice(0, 3).toUpperCase()}
                                                </span>
                                            )
                                        )}
                                    </div>

                                    {(title || description || badge) && (
                                        <div className="mb-6 space-y-1.5 text-center">
                                            {badge && (
                                                <div className="mb-2 flex justify-center">
                                                    {typeof badge === "string" ? (
                                                        <Badge variant="primary-light" size="sm" className="font-semibold">
                                                            {badge}
                                                        </Badge>
                                                    ) : (
                                                        badge
                                                    )}
                                                </div>
                                            )}
                                            {title && (
                                                <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-[26px]">
                                                    {title}
                                                </h1>
                                            )}
                                            {description && (
                                                <p className="text-sm text-muted-foreground text-pretty">
                                                    {description}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    <TransitionWrapper delay={0.05}>{children}</TransitionWrapper>
                                </FramePanel>
                            </Frame>
                        ) : (
                            <div className="w-full">
                                {/* Centered Brand Logo Squircle */}
                                <motion.div
                                    initial={{ opacity: 0, scale: 0.95 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    transition={{ duration: 0.2 }}
                                    className="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl border border-border bg-card shadow-md ring-1 ring-primary/25 backdrop-blur-xs overflow-hidden"
                                >
                                    {icon ?? (
                                        branding.logo && !centerLogoError ? (
                                            <img
                                                src={branding.logo}
                                                alt={`${organizationShortName} Logo`}
                                                className="size-6 object-contain drop-shadow-xs"
                                                onError={() => setCenterLogoError(true)}
                                            />
                                        ) : (
                                            <span className="text-sm font-bold tracking-wider text-foreground">
                                                {organizationShortName.slice(0, 3).toUpperCase()}
                                            </span>
                                        )
                                    )}
                                </motion.div>

                                {(title || description || badge) && (
                                    <div className="mb-6 space-y-1.5 text-center">
                                        {badge && (
                                            <div className="mb-2 flex justify-center">
                                                {typeof badge === "string" ? (
                                                    <span className="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                                        {badge}
                                                    </span>
                                                ) : (
                                                    badge
                                                )}
                                            </div>
                                        )}
                                        {title && (
                                            <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-[26px]">
                                                {title}
                                            </h1>
                                        )}
                                        {description && (
                                            <p className="text-sm text-muted-foreground text-pretty">
                                                {description}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <TransitionWrapper delay={0.05}>{children}</TransitionWrapper>
                            </div>
                        )}
                    </div>
                </main>

                {/* Footer */}
                {showFooter && (
                    <footer className="relative z-10 pt-6 text-center text-xs text-muted-foreground">
                        <div className="space-y-1">
                            <p className="hover:[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_a]:transition-colors">
                                By continuing, you agree to our{" "}
                                <Link href="/terms-of-service">Terms of Service</Link> and{" "}
                                <Link href="/privacy-policy">Privacy Policy</Link>.
                            </p>
                            <p className="text-[11px] text-muted-foreground/80">
                                {branding.copyrightText
                                    ? (branding.copyrightText.includes("©")
                                        ? branding.copyrightText
                                        : `© ${branding.copyrightText}`)
                                    : `© ${currentYear} ${organizationName}. All rights reserved.`}
                            </p>
                        </div>
                    </footer>
                )}
            </div>

            {/* Right Column / Inset Editorial Fluid Wave Showcase */}
            {isSplitLayout && (
                <div className="hidden lg:flex lg:w-1/2 lg:min-h-svh p-3 sm:p-4 lg:p-6 flex-col">
                    <div className="relative h-full w-full overflow-hidden rounded-[28px] border border-border/80 bg-card shadow-2xl flex flex-col justify-between">
                        {/* High-Resolution Fluid Wave Abstract Artwork */}
                        <img
                            src="/images/auth-editorial-wave.webp"
                            alt="Editorial Showcase"
                            className="absolute inset-0 h-full w-full object-cover select-none pointer-events-none"
                        />

                        {/* Subtle theme-aware luxury ambient depth layer */}
                        <div className="absolute inset-0 bg-gradient-to-t from-background/90 via-background/25 to-background/40 pointer-events-none" />
                        <div className="absolute inset-0 bg-primary/10 mix-blend-color pointer-events-none" />

                        {/* Inset content if customRightPanel is provided */}
                        {customRightPanel ? (
                            <div className="relative z-10 flex h-full flex-col p-8">
                                {customRightPanel}
                            </div>
                        ) : (
                            <div className="relative z-10 flex h-full flex-col justify-between p-8 xl:p-10 pointer-events-none">
                                <div className="flex items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-background/80 px-3 py-1 text-xs font-medium text-foreground backdrop-blur-md shadow-xs">
                                        <span className="size-1.5 rounded-full bg-primary animate-pulse" />
                                        {organizationShortName} Academic System
                                    </span>
                                </div>
                                <div className="space-y-1.5 max-w-md">
                                    <div className="text-lg font-semibold tracking-tight text-foreground drop-shadow-md">
                                        {organizationName}
                                    </div>
                                    <p className="text-xs text-muted-foreground leading-relaxed drop-shadow">
                                        {branding.tagline || "Unified student information, faculty grading, schedule management, and curriculum verification."}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export default AuthLayout;
