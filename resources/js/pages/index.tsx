import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Link, usePage } from "@inertiajs/react";

interface Branding {
    appName: string;
}

export default function IndexPage() {
    const { props } = usePage<{ branding?: Branding }>();
    const appName = props.branding?.appName || "School Portal";

    return (
        <div className="bg-muted flex min-h-screen items-center justify-center p-6">
            <div className="w-full max-w-4xl">
                <div className="mb-12 text-center">
                    <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">{appName}</h1>
                    <p className="text-muted-foreground mt-6 text-lg leading-8">Welcome to the {appName}</p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Admin Dashboard</CardTitle>
                            <CardDescription>Access the Filament admin panel</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild className="w-full">
                                <Link href="/admin">Go to Admin Panel</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Login</CardTitle>
                            <CardDescription>Login to your account</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline" className="w-full">
                                <Link href="/login">Login</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
