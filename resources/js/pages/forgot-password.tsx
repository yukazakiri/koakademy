import { ThemeToggle } from "@/components/theme-toggle";
import { TransitionWrapper } from "@/components/transition-wrapper";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { resolveBranding, type Branding } from "@/lib/branding";
import { Link, useForm, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import { toast } from "sonner";

export default function ForgotPasswordPage() {
    const { props } = usePage<{ branding?: Partial<Branding> | null }>();
    const branding = resolveBranding(props.branding);
    const appName = branding.appName;
    const orgShortName = branding.organizationShortName;

    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    useEffect(() => {
        if (errors && Object.keys(errors).length) {
            Object.values(errors).forEach((m) => toast.error(m));
        }
    }, [errors]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post("/forgot-password", {
            onSuccess: () => toast.success("Password reset link sent if email exists"),
        });
    };

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="relative flex flex-col gap-4 p-6 md:p-10">
                <div className="flex items-center justify-between md:justify-start">
                    <a href="#" className="flex items-center gap-2 font-medium">
                        <div className="flex h-10 w-10 items-center justify-center rounded-md">
                            <img src={branding.logo} alt={`${orgShortName} Logo`} className="h-10 w-10 object-contain" />
                        </div>
                        <span className="text-foreground text-4xl font-extrabold tracking-tight">{appName}</span>
                    </a>
                    <div className="md:absolute md:top-6 md:right-6">
                        <ThemeToggle />
                    </div>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-xs">
                        <TransitionWrapper>
                            <form onSubmit={submit} className="flex flex-col gap-6">
                                <div className="flex flex-col items-center gap-2 text-center">
                                    <h1 className="text-foreground text-xl font-bold">Forgot password</h1>
                                    <p className="text-muted-foreground text-sm text-balance">Enter your email to receive a reset link</p>
                                </div>
                                <div className="grid gap-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            placeholder="m@example.com"
                                            required
                                            value={data.email}
                                            onChange={(e) => setData("email", e.target.value)}
                                            disabled={processing}
                                            className={errors.email ? "border-destructive focus-visible:ring-destructive" : ""}
                                        />
                                    </div>
                                    <Button type="submit" className="w-full" disabled={processing}>
                                        {processing ? "Sending..." : "Send reset link"}
                                    </Button>
                                </div>
                                <div className="text-center text-sm">
                                    <Link href="/login" className="underline underline-offset-4">
                                        Back to login
                                    </Link>
                                </div>
                            </form>
                            <div className="text-muted-foreground hover:[&_a]:text-primary mt-6 text-center text-xs text-balance [&_a]:underline [&_a]:underline-offset-4">
                                By clicking continue, you agree to our <Link href="/terms-of-service">Terms of Service</Link> and{" "}
                                <Link href="/privacy-policy">Privacy Policy</Link>.
                            </div>
                        </TransitionWrapper>
                    </div>
                </div>
            </div>
            <div className="bg-muted relative hidden lg:block">
                <TransitionWrapper className="h-full">
                    <div className="flex h-full items-center justify-center p-8">
                        <div className="mx-auto max-w-md">
                            <div className="text-muted-foreground/40 mb-6 font-serif text-4xl">“</div>

                            <blockquote className="text-foreground mb-8 text-xl leading-relaxed font-medium">
                                <span className="text-muted-foreground">Secure access to your </span>
                                <span className="text-foreground">academic resources</span>
                                <span className="text-muted-foreground"> is our top priority. We're here to help you get back on track.</span>
                            </blockquote>

                            <div className="flex items-center gap-3">
                                <div className="h-8 w-8 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600"></div>
                                <div>
                                    <div className="text-sm font-semibold">IT Support</div>
                                    <div className="text-muted-foreground text-xs">{orgShortName} System Administrator</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </TransitionWrapper>
            </div>
        </div>
    );
}
