import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Badge as ReUiBadge } from "@/components/reui/badge";
import { Separator } from "@/components/ui/separator";
import { CheckCircle2, Eye, HelpCircle } from "lucide-react";

interface ProfileSidebarProps {
    user: {
        name: string;
    };
    avatarPreview?: string;
    subtitle?: string;
    biographyPreview?: string;
    isFaculty: boolean;
}

export function ProfileSidebar({ user, avatarPreview, subtitle, biographyPreview, isFaculty }: ProfileSidebarProps) {
    return (
        <div className="space-y-4 xl:sticky xl:top-24">
            {/* Public Identity Preview */}
            <Frame spacing="sm" className="border-border/70 bg-card/75 shadow-sm rounded-xl">
                <FrameHeader>
                    <div className="flex items-center justify-between">
                        <FrameTitle className="text-sm font-semibold flex items-center gap-2">
                            <Eye className="h-4 w-4 text-primary" />
                            Directory preview
                        </FrameTitle>
                        <ReUiBadge variant="invert-light" size="xs">
                            Live
                        </ReUiBadge>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        How your profile appears to {isFaculty ? "students and colleagues" : "the campus community"}
                    </p>
                </FrameHeader>
                <FramePanel className="space-y-3.5 bg-muted/20">
                    <div className="flex items-start gap-3">
                        <Avatar className="h-11 w-11 rounded-xl ring-2 ring-background">
                            <AvatarImage src={avatarPreview} alt={user.name} />
                            <AvatarFallback className="bg-primary/10 text-primary font-bold rounded-xl text-sm">
                                {(user.name || "U").slice(0, 2).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex-1 min-w-0 space-y-0.5">
                            <p className="font-semibold text-sm truncate">{user.name}</p>
                            {subtitle && <p className="text-muted-foreground text-xs truncate">{subtitle}</p>}
                        </div>
                    </div>
                    {biographyPreview && (
                        <>
                            <Separator />
                            <p className="text-muted-foreground line-clamp-3 text-xs leading-relaxed italic">
                                &ldquo;{biographyPreview}&rdquo;
                            </p>
                        </>
                    )}
                </FramePanel>
            </Frame>

            {/* Profile Suggestions / Tips */}
            <Frame spacing="sm" className="border-border/70 bg-card/75 shadow-sm rounded-xl">
                <FrameHeader>
                    <FrameTitle className="text-sm font-semibold flex items-center gap-2">
                        <HelpCircle className="h-4 w-4 text-primary" />
                        Best practices
                    </FrameTitle>
                </FrameHeader>
                <FramePanel className="space-y-2.5 text-xs">
                    {isFaculty ? (
                        <>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-success mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <p className="text-muted-foreground">Keep office hours accurate to avoid consultation mix-ups.</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-success mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <p className="text-muted-foreground">List active research interests and course syllabi.</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-success mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <p className="text-muted-foreground">Connect Google Calendar to sync consultation availability.</p>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-success mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <p className="text-muted-foreground">Verify your phone and emergency contact for official alerts.</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-success mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <p className="text-muted-foreground">Review reporting categories for bursary and scholarship eligibility.</p>
                            </div>
                            <div className="flex items-start gap-2">
                                <CheckCircle2 className="text-success mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <p className="text-muted-foreground">Add prior education records for credit accreditation.</p>
                            </div>
                        </>
                    )}
                </FramePanel>
            </Frame>
        </div>
    );
}

