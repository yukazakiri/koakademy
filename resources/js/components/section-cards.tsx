import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { IconMinus, IconTrendingDown, IconTrendingUp } from "@tabler/icons-react";

export interface Stat {
    label: string;
    value: string | number;
    icon?: string;
    trend?: string;
    trendDirection?: "up" | "down" | "neutral";
}

interface SectionCardsProps {
    stats?: Stat[];
}

export function SectionCards({ stats = [] }: SectionCardsProps) {
    if (!stats.length) return null;

    return (
        <>
            {stats.map((stat, index) => (
                <Card key={index} className="border-l-primary/60 overflow-hidden border-l-4 shadow-sm transition-all hover:shadow-md">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">{stat.label}</CardTitle>
                        {stat.trend && (
                            <Badge
                                variant={stat.trendDirection === "up" ? "default" : stat.trendDirection === "down" ? "destructive" : "secondary"}
                                className="h-5 gap-0.5 px-1.5 text-[10px]"
                            >
                                {stat.trendDirection === "up" ? (
                                    <IconTrendingUp className="size-3" />
                                ) : stat.trendDirection === "down" ? (
                                    <IconTrendingDown className="size-3" />
                                ) : (
                                    <IconMinus className="size-3" />
                                )}
                                {stat.trend}
                            </Badge>
                        )}
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold">{stat.value}</div>
                        <p className="text-muted-foreground mt-1 text-xs">
                            {stat.trendDirection === "up" ? "Increased" : stat.trendDirection === "down" ? "Decreased" : "No change"} from last month
                        </p>
                    </CardContent>
                </Card>
            ))}
        </>
    );
}
