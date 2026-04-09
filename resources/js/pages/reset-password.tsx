import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Link, useForm } from "@inertiajs/react";
import { useEffect } from "react";
import { toast } from "sonner";

type Props = {
    token: string;
    email: string;
};

export default function ResetPasswordPage(props: Props) {
    const { token, email } = props;
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: "",
        password_confirmation: "",
    });

    useEffect(() => {
        if (errors && Object.keys(errors).length) {
            Object.values(errors).forEach((m) => toast.error(m));
        }
    }, [errors]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post("/reset-password", {
            onSuccess: () => toast.success("Password updated. You can log in now."),
        });
    };

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="flex flex-col gap-6 p-6 md:p-10">
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-xs">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-bold">Reset password</h1>
                                <p className="text-muted-foreground text-sm">Set a new password for your account</p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData("email", e.target.value)} required />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData("password", e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirm password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData("password_confirmation", e.target.value)}
                                    required
                                />
                            </div>
                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing ? "Resetting..." : "Reset password"}
                            </Button>
                            <div className="text-center text-sm">
                                <Link href="/login" className="underline underline-offset-4">
                                    Back to login
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div className="bg-muted relative hidden lg:block">
                <img
                    src="https://ui.shadcn.com/placeholder.svg"
                    alt="Image"
                    className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                />
            </div>
        </div>
    );
}
