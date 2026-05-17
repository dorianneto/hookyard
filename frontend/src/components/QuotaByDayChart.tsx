import { Line, LineChart, CartesianGrid, XAxis, YAxis } from "recharts";
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export interface DayQuota {
  date: string;
  count: number;
}

interface Props {
  data: DayQuota[];
}

const chartConfig = {
  count: { label: "Requests", color: "var(--chart-1)" },
};

function fillDays(data: DayQuota[]): DayQuota[] {
  const map = new Map(data.map((d) => [d.date, d]));
  const result: DayQuota[] = [];
  for (let i = 29; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    result.push(map.get(key) ?? { date: key, count: 0 });
  }
  return result;
}

export function QuotaByDayChart({ data }: Props) {
  const filled = fillDays(data);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium text-muted-foreground">
          Daily Request Usage (last 30 days)
        </CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="h-52 w-full">
          <LineChart data={filled}>
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
            <Line
              dataKey="count"
              stroke="var(--color-count)"
              dot={false}
              strokeWidth={2}
            />
          </LineChart>
        </ChartContainer>
      </CardContent>
    </Card>
  );
}
