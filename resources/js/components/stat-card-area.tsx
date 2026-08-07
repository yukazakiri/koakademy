"use client";

import {
  Area,
  AreaChart,
  ChartStatFlow,
  LinearGradient,
} from "@/components/charts";
import { curveCardinal } from "@visx/curve";
import { useState } from "react";
import {
  Card,
  CardAction,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  StatCardChart,
  statCardLabelClassName,
  statCardValueClassName,
} from "./stat-card-chart";
import {
  formatStatCardMonth,
  StatCardHoverBridge,
  type StatCardHoverState,
} from "./stat-card-hover-bridge";
import { TrendBadge } from "./trend-badge";

export type StatCardAreaPoint = {
  date: string;
  value: number;
};

type StatCardFormatOptions = Intl.NumberFormatOptions & {
  currency?: string;
};

export function StatCardArea({
  chartColor = "var(--chart-1)",
  data,
  description,
  formatOptions,
  label = "Current",
  prefix,
  suffix,
  title,
  trend,
  value,
}: {
  chartColor?: string;
  data: StatCardAreaPoint[];
  description?: string;
  formatOptions?: StatCardFormatOptions;
  label?: string;
  prefix?: string;
  suffix?: string;
  title: string;
  trend: number;
  value: number;
}) {
  const [hover, setHover] = useState<StatCardHoverState>({
    value: null,
    label: null,
    trend: null,
  });
  const displayValue = hover.value ?? value;
  const displayLabel = hover.label ?? label;
  const displayTrend = hover.trend ?? trend;
  const gradientId = `stat-card-area-fill-${title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "")}`;

  return (
    <Card className="w-full gap-0 py-0">
      <CardHeader className="px-4 py-3">
        <CardTitle>{title}</CardTitle>
        <CardAction>
          <TrendBadge value={displayTrend} />
        </CardAction>
      </CardHeader>

      <CardContent className="flex flex-col gap-3 px-4 pt-2 pb-3">
        <ChartStatFlow
          formatOptions={{
            maximumFractionDigits: 0,
            ...formatOptions,
          }}
          label={displayLabel}
          labelClassName={statCardLabelClassName}
          prefix={prefix}
          suffix={suffix}
          value={displayValue}
          valueClassName={statCardValueClassName}
        />

        {description ? (
          <p className="text-muted-foreground min-h-8 text-xs leading-5">
            {description}
          </p>
        ) : null}

        <StatCardChart size="sm">
          <AreaChart
            aspectRatio="2.5 / 1"
            className="w-full"
            data={data}
            margin={{ top: 0, right: 0, bottom: 0, left: 0 }}
          >
            <StatCardHoverBridge
              dataKey="value"
              formatLabel={formatStatCardMonth}
              onHoverChange={setHover}
            />
            <LinearGradient
              from={chartColor}
              fromOpacity={0.45}
              id={gradientId}
              to={chartColor}
              toOpacity={0}
            />
            <Area
              curve={curveCardinal.tension(0.65)}
              dataKey="value"
              fill={`url(#${gradientId})`}
              fillOpacity={1}
              gradientToOpacity={0}
              showHighlight
              stroke={chartColor}
              strokeWidth={2}
            />
          </AreaChart>
        </StatCardChart>
      </CardContent>
    </Card>
  );
}
