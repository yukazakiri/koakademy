import { SignupStepper } from "@/components/signup-stepper";
import { ThemeToggle } from "@/components/theme-toggle";
import { TransitionWrapper } from "@/components/transition-wrapper";
import { resolveBranding, type Branding } from "@/lib/branding";
import { usePage } from "@inertiajs/react";

export default function SignupPage() {
    const { branding } = usePage<{
        branding?: Partial<Branding> | null;
    }>().props;

    const resolvedBranding = resolveBranding(branding);
    const appName = resolvedBranding.appName;
    const organizationShortName = resolvedBranding.organizationShortName;

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div className="w-full max-w-sm md:max-w-[400px]">
                <div className="mb-4 flex justify-end">
                    <ThemeToggle />
                </div>
                <div className="mb-8 flex flex-col items-center text-center">
                    <a href="#" className="mb-2 flex items-center gap-2 font-medium">
                        <div className="flex h-8 w-8 items-center justify-center rounded-md">
                            <img src={resolvedBranding.logo} alt={`${organizationShortName} Logo`} className="h-8 w-8 object-contain" />
                        </div>
                        <span className="text-foreground text-xl font-bold tracking-tight">{appName}</span>
                    </a>
                </div>
                <TransitionWrapper>
                    <SignupStepper />
                </TransitionWrapper>
            </div>
        </div>
    );
}
