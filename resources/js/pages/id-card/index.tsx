import { DigitalIdCard, type IdCardData } from "@/components/digital-id-card";
import { Button } from "@/components/ui/button";
import { Head, router } from "@inertiajs/react";
import { motion } from "framer-motion";
import { AlertCircle, ArrowLeft, Printer, Share2 } from "lucide-react";
import { useCallback, useState } from "react";

interface IdCardPageProps {
    id_card: {
        card_data: IdCardData;
        photo_url: string | null;
        qr_code: string;
        is_valid: boolean;
    } | null;
    user: {
        id: number;
        name: string;
        email: string;
        avatar_url: string | null;
        role: string;
    } | null;
}

export default function IdCardIndexPage({ id_card, user }: IdCardPageProps) {
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [qrCode, setQrCode] = useState(id_card?.qr_code ?? "");

    const handleRefresh = useCallback(async () => {
        if (!user) return;

        setIsRefreshing(true);
        try {
            // Determine the correct endpoint based on user role
            const endpoint = user.role === "faculty" ? "/faculty/id-card/refresh" : "/student/id-card/refresh";

            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
                },
            });

            if (response.ok) {
                const data = await response.json();
                setQrCode(data.qr_code);
            }
        } catch (error) {
            console.error("Failed to refresh QR code:", error);
        } finally {
            setIsRefreshing(false);
        }
    }, [user]);

    const handlePrint = useCallback(() => {
        window.print();
    }, []);

    const handleShare = useCallback(async () => {
        if (navigator.share && id_card) {
            try {
                await navigator.share({
                    title: "My Digital ID Card",
                    text: `${id_card.card_data.name} - ${id_card.card_data.type === "student" ? "Student" : "Faculty"} ID: ${id_card.card_data.id}`,
                    url: window.location.href,
                });
            } catch (error) {
                // User cancelled or share failed
            }
        }
    }, [id_card]);

    const handleBack = useCallback(() => {
        const backUrl = user?.role === "faculty" ? "/faculty/dashboard" : "/student/dashboard";
        router.visit(backUrl);
    }, [user]);

    return (
        <>
            <Head title="Digital ID Card" />

            <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
                {/* Background effects */}
                <div className="pointer-events-none fixed inset-0 overflow-hidden">
                    <div className="bg-primary/10 absolute top-0 left-1/4 h-96 w-96 animate-pulse rounded-full blur-3xl" />
                    <div className="absolute right-1/4 bottom-0 h-96 w-96 animate-pulse rounded-full bg-purple-500/10 blur-3xl" />
                    <div className="absolute top-1/2 right-0 h-64 w-64 rounded-full bg-pink-500/5 blur-3xl" />
                </div>

                {/* Header */}
                <header className="relative z-10 border-b border-white/10 bg-slate-900/50 backdrop-blur-xl print:hidden">
                    <div className="container mx-auto flex items-center justify-between px-4 py-4">
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="icon" onClick={handleBack} className="text-white/70 hover:bg-white/10 hover:text-white">
                                <ArrowLeft className="h-5 w-5" />
                            </Button>
                            <div>
                                <h1 className="text-lg font-bold text-white">Digital ID Card</h1>
                                <p className="text-xs text-white/50">
                                    {id_card?.card_data.type === "student" ? "Student Identification" : "Faculty Identification"}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {typeof navigator !== "undefined" && "share" in navigator && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={handleShare}
                                    className="text-white/70 hover:bg-white/10 hover:text-white"
                                >
                                    <Share2 className="h-4 w-4" />
                                </Button>
                            )}
                            <Button variant="ghost" size="icon" onClick={handlePrint} className="text-white/70 hover:bg-white/10 hover:text-white">
                                <Printer className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </header>

                {/* Main Content */}
                <main className="relative z-10 container mx-auto flex min-h-[calc(100vh-80px)] flex-col items-center justify-center px-4 py-8">
                    {id_card ? (
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.5 }}
                            className="w-full max-w-md"
                        >
                            <DigitalIdCard
                                cardData={id_card.card_data}
                                photoUrl={id_card.photo_url}
                                qrCode={qrCode}
                                isValid={id_card.is_valid}
                                onRefresh={handleRefresh}
                                isRefreshing={isRefreshing}
                            />

                            {/* Instructions */}
                            <motion.div
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                transition={{ delay: 0.3 }}
                                className="mt-6 rounded-xl border border-white/10 bg-white/5 p-4 print:hidden"
                            >
                                <h3 className="mb-2 text-sm font-semibold text-white">How to use your Digital ID</h3>
                                <ul className="space-y-2 text-xs text-white/60">
                                    <li className="flex items-start gap-2">
                                        <span className="bg-primary/20 text-primary flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                            1
                                        </span>
                                        Show your QR code at library entrance for borrowing privileges
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="bg-primary/20 text-primary flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                            2
                                        </span>
                                        Present at campus facilities for access verification
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="bg-primary/20 text-primary flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                            3
                                        </span>
                                        Refresh your QR code periodically for security
                                    </li>
                                </ul>
                            </motion.div>

                            {/* Print-only styles */}
                            <style>{`
                                @media print {
                                    body {
                                        background: white !important;
                                    }
                                    .print\\:hidden {
                                        display: none !important;
                                    }
                                }
                            `}</style>
                        </motion.div>
                    ) : (
                        <NoIdCardMessage user={user} />
                    )}
                </main>
            </div>
        </>
    );
}

function NoIdCardMessage({ user }: { user: IdCardPageProps["user"] }) {
    const handleBack = useCallback(() => {
        const backUrl = user?.role === "faculty" ? "/faculty/dashboard" : "/student/dashboard";
        router.visit(backUrl);
    }, [user]);

    return (
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md text-center">
            <div className="rounded-2xl border border-white/10 bg-slate-800/50 p-8 backdrop-blur-xl">
                <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/20 ring-4 ring-amber-500/30">
                    <AlertCircle className="h-8 w-8 text-amber-400" />
                </div>
                <h2 className="mb-2 text-xl font-bold text-white">No ID Card Available</h2>
                <p className="mb-6 text-sm text-white/60">
                    Your digital ID card is not available. This could be because your account is not linked to a student or faculty record.
                </p>
                <Button onClick={handleBack} className="gap-2">
                    <ArrowLeft className="h-4 w-4" />
                    Back to Dashboard
                </Button>
            </div>
        </motion.div>
    );
}
