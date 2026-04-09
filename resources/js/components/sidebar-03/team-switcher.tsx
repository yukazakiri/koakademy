"use client";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Link, router, usePage } from "@inertiajs/react";
import { Building2, Check, ChevronsUpDown, LogOut, Plus, Settings, Shield } from "lucide-react";
import * as React from "react";

type Team = {
    id: string;
    name: string;
    logo: React.ElementType;
    plan: string;
};

type Organization = {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
};

interface PageProps {
    currentOrganization?: Organization | null;
    organizations?: Organization[];
    canSwitchOrganization?: boolean;
    [key: string]: unknown;
}

export function TeamSwitcher({ teams, user }: { teams: Team[]; user: User }) {
    const { isMobile } = useSidebar();
    const [activeTeam, setActiveTeam] = React.useState(teams[0]);
    const [showLogoutDialog, setShowLogoutDialog] = React.useState(false);
    const [showCreateOrgDialog, setShowCreateOrgDialog] = React.useState(false);
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [newOrgData, setNewOrgData] = React.useState({
        name: "",
        code: "",
        description: "",
    });

    const { props } = usePage<PageProps>();
    const currentOrganization = props.currentOrganization;
    const organizations = props.organizations || [];
    const canSwitchOrganization = props.canSwitchOrganization || false;

    // Check if user can create organizations (super admin)
    const canCreateOrganization = ["admin", "super_admin", "developer"].includes(user.role);

    if (!activeTeam) return null;

    const Logo = activeTeam.logo;

    const isStudent = ["student", "shs_student", "graduate_student"].includes(user.role);
    const isFaculty = ["professor", "instructor", "associate_professor", "assistant_professor", "part_time_faculty"].includes(user.role);
    const isAdmin = [
        "admin",
        "super_admin",
        "developer",
        "president",
        "vice_president",
        "dean",
        "associate_dean",
        "department_head",
        "program_chair",
        "registrar",
        "assistant_registrar",
        "cashier",
        "hr_manager",
        "student_affairs_officer",
        "guidance_counselor",
        "librarian",
    ].includes(user.role);

    let profileLink = "/profile";
    if (isStudent) profileLink = "/student/profile";
    else if (isFaculty) profileLink = "/faculty/profile";
    else if (isAdmin) profileLink = "/administrators/settings";

    const handleSwitchOrganization = async (organizationId: number) => {
        try {
            const response = await fetch("/api/organizations/switch", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                body: JSON.stringify({ organization_id: organizationId }),
            });

            if (response.ok) {
                // Reload the page to reflect the new organization context
                window.location.reload();
            } else {
                console.error("Failed to switch organization");
            }
        } catch (error) {
            console.error("Error switching organization:", error);
        }
    };

    const handleCreateOrganization = async () => {
        if (!newOrgData.name || !newOrgData.code) return;

        setIsSubmitting(true);
        try {
            const response = await fetch("/api/organizations", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                body: JSON.stringify(newOrgData),
            });

            if (response.ok) {
                const data = await response.json();
                setShowCreateOrgDialog(false);
                setNewOrgData({ name: "", code: "", description: "" });
                // Switch to the newly created organization
                if (data.organization?.id) {
                    await handleSwitchOrganization(data.organization.id);
                } else {
                    window.location.reload();
                }
            } else {
                const errorData = await response.json();
                console.error("Failed to create organization:", errorData);
            }
        } catch (error) {
            console.error("Error creating organization:", error);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            >
                                <div className="bg-background text-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                                    <Logo className="size-4" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="text-foreground truncate font-semibold">{activeTeam.name}</span>
                                    <span className="text-muted-foreground truncate text-xs">{activeTeam.plan}</span>
                                </div>
                                <ChevronsUpDown className="text-muted-foreground ml-auto" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            className="mb-4 w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                            align="start"
                            side={isMobile ? "bottom" : "right"}
                            sideOffset={4}
                        >
                            <DropdownMenuLabel className="text-muted-foreground text-xs">Account</DropdownMenuLabel>
                            <div className="flex items-center gap-3 px-2 py-1.5">
                                <Avatar className="h-9 w-9">
                                    <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                                    <AvatarFallback>{user.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                                </Avatar>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <p className="text-foreground truncate text-sm font-medium">{user.name}</p>
                                    <p className="text-muted-foreground truncate text-xs">{user.email}</p>
                                </div>
                            </div>

                            {/* Organization Switcher Section */}
                            {(organizations.length > 0 || canCreateOrganization) && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <Building2 className="size-3" />
                                        Organizations
                                    </DropdownMenuLabel>

                                    {organizations.map((org) => (
                                        <DropdownMenuItem
                                            key={org.id}
                                            onClick={() => handleSwitchOrganization(org.id)}
                                            className={cn(
                                                "text-foreground cursor-pointer gap-2 p-2",
                                                currentOrganization?.id === org.id && "bg-accent",
                                            )}
                                        >
                                            <div className="flex size-6 items-center justify-center rounded-sm border">
                                                <Building2 className="size-4 shrink-0" />
                                            </div>
                                            <span className="flex-1">{org.name}</span>
                                            {currentOrganization?.id === org.id && <Check className="text-primary size-4" />}
                                        </DropdownMenuItem>
                                    ))}

                                    {canCreateOrganization && (
                                        <DropdownMenuItem
                                            onClick={() => setShowCreateOrgDialog(true)}
                                            className="text-foreground cursor-pointer gap-2 p-2"
                                        >
                                            <div className="flex size-6 items-center justify-center rounded-sm border border-dashed">
                                                <Plus className="size-4 shrink-0" />
                                            </div>
                                            <span>Create Organization</span>
                                        </DropdownMenuItem>
                                    )}
                                </>
                            )}

                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-muted-foreground text-xs">Quick Actions</DropdownMenuLabel>
                            {teams.map((team, index) => (
                                <DropdownMenuItem key={team.id} onClick={() => setActiveTeam(team)} className="text-foreground gap-2 p-2">
                                    <div className="flex size-6 items-center justify-center rounded-sm border">
                                        <team.logo className="size-4 shrink-0" />
                                    </div>
                                    {team.name}
                                    <DropdownMenuShortcut>⌘{index + 1}</DropdownMenuShortcut>
                                </DropdownMenuItem>
                            ))}
                            <DropdownMenuSeparator />
                            {isAdmin && (
                                <DropdownMenuItem asChild className="text-foreground">
                                    <Link href="/administrators/system-management" className="flex items-center gap-2">
                                        <Shield className="size-4" />
                                        <span>System Management</span>
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem asChild className="text-foreground">
                                <Link href={profileLink} className="flex items-center gap-2">
                                    <Settings className="size-4" />
                                    <span>Settings</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem className="cursor-pointer text-red-600 focus:text-red-600" onClick={() => setShowLogoutDialog(true)}>
                                <LogOut className="mr-2 size-4" />
                                <span>Log out</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>

            {/* Logout Confirmation Dialog */}
            <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure you want to log out?</AlertDialogTitle>
                        <AlertDialogDescription>You will be redirected to the login page. Any unsaved changes may be lost.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction className="bg-red-600 text-white hover:bg-red-700" onClick={() => router.post("/logout")}>
                            Log out
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Create Organization Dialog */}
            <Dialog open={showCreateOrgDialog} onOpenChange={setShowCreateOrgDialog}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Create New Organization</DialogTitle>
                        <DialogDescription>Create a new school or organization. This will be available for you to switch to.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="org-name">Organization Name</Label>
                            <Input
                                id="org-name"
                                value={newOrgData.name}
                                onChange={(e) => setNewOrgData((prev) => ({ ...prev, name: e.target.value }))}
                                placeholder="e.g., School of Engineering"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="org-code">Organization Code</Label>
                            <Input
                                id="org-code"
                                value={newOrgData.code}
                                onChange={(e) => setNewOrgData((prev) => ({ ...prev, code: e.target.value.toUpperCase() }))}
                                placeholder="e.g., SOE"
                                maxLength={20}
                            />
                            <p className="text-muted-foreground text-xs">A short unique code for this organization (max 20 characters)</p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="org-description">Description (Optional)</Label>
                            <Textarea
                                id="org-description"
                                value={newOrgData.description}
                                onChange={(e) => setNewOrgData((prev) => ({ ...prev, description: e.target.value }))}
                                placeholder="Brief description of the organization"
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowCreateOrgDialog(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreateOrganization} disabled={isSubmitting || !newOrgData.name || !newOrgData.code}>
                            {isSubmitting ? "Creating..." : "Create Organization"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
