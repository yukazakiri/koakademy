import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTab } from "@/components/ui/tabs";

type TabItem = {
    value: string;
    label: string;
    count: number;
};

type PTabs10Props = {
    value: string;
    onValueChange: (value: string) => void;
    tabs: TabItem[];
};

export default function PTabs10({ value, onValueChange, tabs }: PTabs10Props) {
    return (
        <Tabs value={value} onValueChange={onValueChange} className="w-full">
            <TabsList className="flex h-auto w-full flex-wrap items-center justify-start gap-1 bg-transparent p-0">
                {tabs.map((tab) => (
                    <TabsTab
                        key={tab.value}
                        value={tab.value}
                        className="data-[active]:bg-muted flex h-auto items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-all data-[active]:shadow-none"
                    >
                        {tab.label}
                        <Badge className="not-in-data-active:text-muted-foreground ml-1 rounded-full px-1.5 py-0 text-xs" variant="outline">
                            {tab.count}
                        </Badge>
                    </TabsTab>
                ))}
            </TabsList>
        </Tabs>
    );
}
