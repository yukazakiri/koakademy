import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { IconAdjustmentsHorizontal, IconGridDots, IconListDetails, IconSchool, IconSearch } from "@tabler/icons-react";
import { DAYS } from "../hooks/use-class-schedule";

interface ClassesToolbarProps {
    viewMode: "board" | "gallery" | "list";
    setViewMode: (mode: "board" | "gallery" | "list") => void;
    search: string;
    setSearch: (val: string) => void;
    filterClassification: string;
    setFilterClassification: (val: string) => void;
    filterRoom: string;
    setFilterRoom: (val: string) => void;
    filterDay: string;
    setFilterDay: (val: string) => void;
    rooms: { id: number; name: string }[];
    resetFilters: () => void;
    hasActiveFilters: boolean;
}

export function ClassesToolbar({
    viewMode,
    setViewMode,
    search,
    setSearch,
    filterClassification,
    setFilterClassification,
    filterRoom,
    setFilterRoom,
    filterDay,
    setFilterDay,
    rooms,
    resetFilters,
    hasActiveFilters,
}: ClassesToolbarProps) {
    const activeFilterCount = [filterClassification !== "all", filterRoom !== "all", filterDay !== "all"].filter(Boolean).length;

    return (
        <div className="bg-card flex flex-col items-center justify-between gap-3 rounded-lg border p-1 shadow-sm sm:flex-row">
            {/* Search */}
            <div className="relative w-full sm:max-w-xs">
                <IconSearch className="text-muted-foreground absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2" />
                <Input
                    placeholder="Search classes..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="placeholder:text-muted-foreground/70 h-9 border-0 bg-transparent pl-9 focus-visible:ring-0"
                />
            </div>

            <div className="flex w-full items-center gap-2 px-1 sm:w-auto">
                {/* Filters Popover */}
                <Popover>
                    <PopoverTrigger asChild>
                        <Button variant={hasActiveFilters ? "secondary" : "ghost"} size="sm" className="h-8 gap-2 border border-transparent">
                            <IconAdjustmentsHorizontal className="h-4 w-4" />
                            <span>Filters</span>
                            {activeFilterCount > 0 && (
                                <Badge variant="secondary" className="bg-background/50 ml-0.5 h-5 px-1.5 text-[10px]">
                                    {activeFilterCount}
                                </Badge>
                            )}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-80 p-4" align="end">
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h4 className="leading-none font-medium">Filter Classes</h4>
                                {hasActiveFilters && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={resetFilters}
                                        className="text-muted-foreground hover:text-foreground h-auto p-0 px-2 text-xs"
                                    >
                                        Reset all
                                    </Button>
                                )}
                            </div>
                            <div className="space-y-3">
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Classification</Label>
                                    <Select value={filterClassification} onValueChange={setFilterClassification}>
                                        <SelectTrigger className="h-8">
                                            <SelectValue placeholder="All types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All types</SelectItem>
                                            <SelectItem value="shs">Senior High</SelectItem>
                                            <SelectItem value="college">College</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Room</Label>
                                    <Select value={filterRoom} onValueChange={setFilterRoom}>
                                        <SelectTrigger className="h-8">
                                            <SelectValue placeholder="All rooms" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Everywhere</SelectItem>
                                            {rooms.map((room) => (
                                                <SelectItem key={room.id} value={String(room.id)}>
                                                    {room.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-muted-foreground text-xs">Day</Label>
                                    <Select value={filterDay} onValueChange={setFilterDay}>
                                        <SelectTrigger className="h-8">
                                            <SelectValue placeholder="All days" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Any day</SelectItem>
                                            {DAYS.map((day) => (
                                                <SelectItem key={day} value={day}>
                                                    {day}
                                                </SelectItem>
                                            ))}
                                            <SelectItem value="Sunday">Sunday</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>

                <div className="bg-border mx-1 hidden h-4 w-px sm:block" />

                {/* View Switcher */}
                <Tabs value={viewMode} onValueChange={(v) => setViewMode(v as any)}>
                    <TabsList className="bg-muted/50 h-8 p-0.5">
                        <TabsTrigger value="board" className="data-[state=active]:bg-background h-7 px-2.5 text-xs data-[state=active]:shadow-sm">
                            <IconGridDots className="mr-1.5 h-3.5 w-3.5" />
                            Board
                        </TabsTrigger>
                        <TabsTrigger value="gallery" className="data-[state=active]:bg-background h-7 px-2.5 text-xs data-[state=active]:shadow-sm">
                            <IconSchool className="mr-1.5 h-3.5 w-3.5" />
                            Cards
                        </TabsTrigger>
                        <TabsTrigger value="list" className="data-[state=active]:bg-background h-7 px-2.5 text-xs data-[state=active]:shadow-sm">
                            <IconListDetails className="mr-1.5 h-3.5 w-3.5" />
                            List
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>
        </div>
    );
}
