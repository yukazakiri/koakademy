import { useForm } from "@inertiajs/react";
import { CheckCircle, UserCheck } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

interface Props {
    email?: string;
    errors?: Record<string, string>;
    status?: string;
    warning?: string;
}

export default function FacultyVerifyPage({ email = "", errors = {}, status, warning }: Props) {
    const { data, setData, post, processing } = useForm({
        email: email,
        faculty_id_number: "",
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post("/faculty-verify");
    };

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-md">
                <Card className="border-none shadow-none">
                    <CardHeader className="space-y-1 text-center">
                        <div className="mb-4 flex justify-center">
                            <div className="bg-primary/10 flex size-16 items-center justify-center rounded-full">
                                <UserCheck className="text-primary size-8" />
                            </div>
                        </div>
                        <CardTitle className="text-2xl font-bold">Faculty ID Verification</CardTitle>
                        <CardDescription>Please verify your faculty ID number to access the system</CardDescription>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            {warning && (
                                <Alert className="border-yellow-200 bg-yellow-50">
                                    <AlertDescription className="text-yellow-800">{warning}</AlertDescription>
                                </Alert>
                            )}

                            {status && (
                                <Alert>
                                    <CheckCircle className="h-4 w-4" />
                                    <AlertDescription>{status}</AlertDescription>
                                </Alert>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="email">Email Address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="john.doe@example.com"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData("email", e.target.value)}
                                    className={errors.email && "border-destructive"}
                                    disabled
                                />
                                {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="faculty_id_number">Faculty ID Number</Label>
                                <Input
                                    id="faculty_id_number"
                                    type="text"
                                    placeholder="e.g., FAC-2024-001"
                                    required
                                    value={data.faculty_id_number}
                                    onChange={(e) => setData("faculty_id_number", e.target.value)}
                                    className={errors.faculty_id_number && "border-destructive"}
                                />
                                {errors.faculty_id_number && <p className="text-destructive text-sm">{errors.faculty_id_number}</p>}
                                <p className="text-muted-foreground text-xs">Enter the faculty ID number provided by the institution</p>
                            </div>

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing ? "Verifying..." : "Verify & Continue"}
                            </Button>
                        </form>
                    </CardContent>

                    <CardFooter className="flex flex-col gap-4">
                        <div className="text-muted-foreground text-center text-xs">
                            <p>Need help? Contact the IT Department or visit the Registrar's Office</p>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>
    );
}
