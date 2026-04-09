import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import axios from "axios";
import { AlertTriangle, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface IdCardTabProps {
    idCard: {
        card_data: IdCardData;
        photo_url: string | null;
        qr_code: string;
        is_valid: boolean;
    } | null;
    isFaculty: boolean;
}

export function IdCardTab({ idCard, isFaculty }: IdCardTabProps) {
    const [qrCode, setQrCode] = useState(idCard?.qr_code ?? "");
    const [isRefreshingQr, setIsRefreshingQr] = useState(false);

    if (!idCard) {
        return null;
    }

    const handleRefreshQr = async () => {
        setIsRefreshingQr(true);
        try {
            const endpoint = isFaculty ? "/faculty/id-card/refresh" : "/student/id-card/refresh";
            const response = await axios.post(endpoint);
            if (response.data) {
                setQrCode(response.data.qr_code);
                toast.success("QR code refreshed successfully");
            }
        } catch (error) {
            console.error("Failed to refresh QR code:", error);
            toast.error("Failed to refresh QR code");
        } finally {
            setIsRefreshingQr(false);
        }
    };

    return (
        <div className="flex flex-col items-center gap-8">
            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold tracking-tight">Digital ID Card</h2>
                <p className="text-muted-foreground">Your official digital identification</p>
            </div>

            <div className="w-full max-w-md">
                <DigitalIdCard
                    cardData={idCard.card_data}
                    photoUrl={idCard.photo_url}
                    qrCode={qrCode}
                    isValid={idCard.is_valid}
                    onRefresh={handleRefreshQr}
                    isRefreshing={isRefreshingQr}
                />

                <div className="mt-8 grid gap-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ShieldCheck className="text-primary h-4 w-4" />
                                Verification Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Status</span>
                                <Badge variant={idCard.is_valid ? "default" : "destructive"}>
                                    {idCard.is_valid ? "Active & Verified" : "Inactive / Expired"}
                                </Badge>
                            </div>
                            <Separator className="my-3" />
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground text-sm">Last Updated</span>
                                <span className="font-mono text-sm">{new Date().toLocaleDateString()}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="bg-muted/50 rounded-lg border p-4">
                        <div className="flex gap-3">
                            <div className="mt-0.5">
                                <AlertTriangle className="text-muted-foreground h-5 w-5" />
                            </div>
                            <div className="text-muted-foreground text-sm">
                                <p className="text-foreground mb-1 font-medium">Security Notice</p>
                                <p>Do not share your QR code screenshot online. This ID is for your personal use within the campus premises only.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
