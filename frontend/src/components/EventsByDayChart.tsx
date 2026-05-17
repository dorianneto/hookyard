import {
  Bar,
  BarChart,
  CartesianGrid,
  XAxis,
  YAxis,
} from "recharts";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  ChartLegend,
  ChartLegendContent,
} from "@/components/ui/chart";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export interface DayEventCount {
  date: string;
  total: number;
  delivered: number;
  pending: number;
  failed: number;
}

interface Props {
  data: DayEventCount[];
}

const chartConfig = {
  delivered: { label: "Delivered", color: "var(--chart-1)" },
  pending: { label: "Pending", color: "var(--chart-2)" },
  failed: { label: "Failed", color: "var(--chart-3)" },
};

function fillDays(data: DayEventCount[]): DayEventCount[] {
  const map = new Map(data.map((d) => [d.date, d]));
  const result: DayEventCount[] = [];
  for (let i = 29; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    result.push(
      map.get(key) ?? { date: key, total: 0, delivered: 0, pending: 0, failed: 0 },
    );
  }
  return result;
}

export function EventsByDayChart({ data }: Props) {
  const filled = fillDays(data);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium text-muted-foreground">
          Events per Day (last 30 days)
        </CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="h-52 w-full">
          <BarChart data={filled}>
            <CartesianGrid vertical={false} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickFormatter={(v: string) => v.slice(5)}
              interval="preserveStartEnd"
            />
            <YAxis tickLine={false} axisLine={false} allowDecimals={false} />
            <ChartTooltip content={<ChartTooltipContent />} />
            <ChartLegend content={<ChartLegendContent />} />
            <Bar
              dataKey="delivered"
              stackId="a"
              fill="var(--color-delivered)"
            />
            <Bar dataKey="pending" stackId="a" fill="var(--color-pending)" />
            <Bar
              dataKey="failed"
              stackId="a"
              fill="var(--color-failed)"
              radius={[4, 4, 0, 0]}
            />
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}
