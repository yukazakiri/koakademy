import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { themes, type ColorTheme } from "@/conf/themes";
import { useTheme } from "@/hooks/use-theme";
import { Check, Laptop, LayoutGrid, Monitor, Moon, Paintbrush, Sun } from "lucide-react";

export function PersonalizationTab() {
    const { theme, setThemeWithViewTransition, colorTheme, setColorTheme } = useTheme();

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Monitor className="h-5 w-5" />
                        Display Mode
                    </CardTitle>
                    <CardDescription>Choose your preferred interface appearance</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {[
                            { value: "light", label: "Light", icon: Sun },
                            { value: "dark", label: "Dark", icon: Moon },
                            { value: "system", label: "System", icon: Laptop },
                        ].map((mode) => (
                            <button
                                key={mode.value}
                                type="button"
                                onClick={(e) => setThemeWithViewTransition(mode.value as any, e)}
                                className={`relative flex items-center justify-center gap-3 rounded-xl border-2 p-4 transition-all duration-200 ${
                                    theme === mode.value
                                        ? "border-primary bg-primary/5 ring-primary/20 ring-1"
                                        : "border-muted hover:border-primary/50 hover:bg-muted/50"
                                } `}
                            >
                                <mode.icon className={`h-5 w-5 ${theme === mode.value ? "text-primary" : "text-muted-foreground"}`} />
                                <span className={`font-medium ${theme === mode.value ? "text-foreground" : "text-muted-foreground"}`}>
                                    {mode.label}
                                </span>
                                {theme === mode.value && (
                                    <div className="absolute top-3 right-3">
                                        <div className="bg-primary h-2 w-2 rounded-full" />
                                    </div>
                                )}
                            </button>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Paintbrush className="h-5 w-5" />
                        Color Theme
                    </CardTitle>
                    <CardDescription>Select a color palette that suits your style</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="border-primary/20 bg-background relative overflow-hidden rounded-xl border-2 p-6 shadow-sm transition-all">
                        <div className="from-primary/5 to-accent/5 absolute inset-0 bg-gradient-to-br via-transparent" />
                        <div className="relative flex flex-col items-center justify-between gap-6 sm:flex-row">
                            <div className="space-y-1.5 text-center sm:text-left">
                                <div className="flex items-center justify-center gap-2 sm:justify-start">
                                    <h3 className="text-foreground text-xl font-bold tracking-tight">
                                        {themes.find((t) => t.id === colorTheme)?.name}
                                    </h3>
                                    <Badge variant="secondary" className="text-[10px] font-bold tracking-wider uppercase">
                                        Active
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground max-w-md text-sm">{themes.find((t) => t.id === colorTheme)?.description}</p>
                            </div>

                            <div className="flex items-center gap-3">
                                <div className="mr-1 hidden flex-col items-end gap-0.5 border-r pr-4 sm:flex">
                                    <span className="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Typography</span>
                                    <span className="text-foreground text-xl leading-none" style={{ fontFamily: "var(--font-sans)" }}>
                                        Aa
                                    </span>
                                    <span className="text-muted-foreground text-[10px]" style={{ fontFamily: "var(--font-sans)" }}>
                                        123
                                    </span>
                                </div>

                                <div className="flex flex-col items-center gap-1">
                                    <div
                                        className="ring-border h-12 w-12 rounded-xl shadow-md ring-1 transition-transform hover:scale-110"
                                        style={{ backgroundColor: themes.find((t) => t.id === colorTheme)?.colors.primary }}
                                    />
                                    <span className="text-muted-foreground text-[10px] font-medium uppercase">Pri</span>
                                </div>
                                <div className="flex flex-col items-center gap-1">
                                    <div
                                        className="ring-border h-12 w-12 rounded-xl shadow-md ring-1 transition-transform hover:scale-110"
                                        style={{ backgroundColor: themes.find((t) => t.id === colorTheme)?.colors.secondary }}
                                    />
                                    <span className="text-muted-foreground text-[10px] font-medium uppercase">Sec</span>
                                </div>
                                <div className="flex flex-col items-center gap-1">
                                    <div
                                        className="ring-border h-12 w-12 rounded-xl shadow-md ring-1 transition-transform hover:scale-110"
                                        style={{ backgroundColor: themes.find((t) => t.id === colorTheme)?.colors.accent }}
                                    />
                                    <span className="text-muted-foreground text-[10px] font-medium uppercase">Acc</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <Label className="text-muted-foreground mb-3 block text-xs font-medium tracking-wider uppercase">Available Themes</Label>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                            {themes.map((t) => {
                                return (
                                    <button
                                        key={t.id}
                                        type="button"
                                        onClick={() => setColorTheme(t.id as ColorTheme)}
                                        className={`group relative flex items-center justify-between rounded-xl border p-3 transition-all duration-200 ${
                                            colorTheme === t.id
                                                ? "border-primary bg-primary/5 ring-primary/20 shadow-md ring-1"
                                                : "border-muted bg-card hover:border-primary/30 hover:bg-accent/5 hover:shadow-sm"
                                        } `}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex -space-x-2">
                                                <div
                                                    className="border-background h-8 w-8 rounded-full border-2 shadow-sm"
                                                    style={{ backgroundColor: t.colors.primary }}
                                                />
                                                <div
                                                    className="border-background h-8 w-8 rounded-full border-2 shadow-sm"
                                                    style={{ backgroundColor: t.colors.secondary }}
                                                />
                                            </div>
                                            <div className="space-y-0.5 text-left">
                                                <span
                                                    className={`block text-sm font-semibold transition-colors ${colorTheme === t.id ? "text-primary" : "text-foreground"}`}
                                                >
                                                    {t.name}
                                                </span>
                                                <span className="text-muted-foreground block text-[10px]">{t.font}</span>
                                            </div>
                                        </div>

                                        {colorTheme === t.id && (
                                            <div className="bg-primary text-primary-foreground animate-in zoom-in flex h-5 w-5 items-center justify-center rounded-full shadow-sm">
                                                <Check className="h-3 w-3" />
                                            </div>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <LayoutGrid className="h-5 w-5" />
                        Dashboard Layout
                    </CardTitle>
                    <CardDescription>Customize your workspace density and behavior</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="bg-muted/20 flex items-center justify-between space-x-2 rounded-lg border p-4">
                        <Label htmlFor="compact-mode" className="flex cursor-pointer flex-col space-y-1">
                            <span className="font-medium">Compact Mode</span>
                            <span className="text-muted-foreground text-xs font-normal">
                                Reduce whitespace in lists and tables for higher density
                            </span>
                        </Label>
                        <Switch id="compact-mode" />
                    </div>
                    <div className="bg-muted/20 flex items-center justify-between space-x-2 rounded-lg border p-4">
                        <Label htmlFor="sidebar-collapsed" className="flex cursor-pointer flex-col space-y-1">
                            <span className="font-medium">Collapse Sidebar by Default</span>
                            <span className="text-muted-foreground text-xs font-normal">
                                Automatically collapse the navigation sidebar on page load
                            </span>
                        </Label>
                        <Switch id="sidebar-collapsed" />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
