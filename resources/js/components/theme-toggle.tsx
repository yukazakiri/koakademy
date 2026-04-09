import { IconMoon, IconSun } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { useTheme } from "@/hooks/use-theme";

export function ThemeToggle() {
    const { setThemeWithViewTransition, actualTheme } = useTheme();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="hover:bg-accent hover:text-accent-foreground h-9 w-9 px-0 transition-all duration-200">
                    <IconSun className="text-foreground h-[1.2rem] w-[1.2rem] scale-100 rotate-0 transition-all duration-300 dark:scale-0 dark:-rotate-90" />
                    <IconMoon className="text-foreground absolute h-[1.2rem] w-[1.2rem] scale-0 rotate-90 transition-all duration-300 dark:scale-100 dark:rotate-0" />
                    <span className="sr-only">Toggle theme</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="transition-all duration-200">
                <DropdownMenuItem onClick={(e) => setThemeWithViewTransition("light", e)} className="cursor-pointer transition-colors duration-200">
                    <IconSun className="text-foreground mr-2 h-4 w-4" />
                    <span className="text-foreground">Light</span>
                </DropdownMenuItem>
                <DropdownMenuItem onClick={(e) => setThemeWithViewTransition("dark", e)} className="cursor-pointer transition-colors duration-200">
                    <IconMoon className="text-foreground mr-2 h-4 w-4" />
                    <span className="text-foreground">Dark</span>
                </DropdownMenuItem>
                <DropdownMenuItem onClick={(e) => setThemeWithViewTransition("system", e)} className="cursor-pointer transition-colors duration-200">
                    <svg
                        className="text-foreground mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>
                    <span className="text-foreground">System</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
