import { useEffect, useState } from "react";
import { apiFetch } from "@/lib/apiFetch";
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { Link } from "react-router-dom";
import { EventsByDayChart, type DayEventCount } from "@/components/EventsByDayChart";
import { QuotaByDayChart, type DayQuota } from "@/components/QuotaByDayChart";

interface DashboardStats {
  totalSources: number;
  totalEndpoints: number;
  totalEventsReceived: number;
  deliveredEventsCount: number;
  pendingEventsCount: number;
  failedEventsCount: number;
  lastEventReceivedAt: string | null;
  quotaUsed: number;
  quotaLimit: number;
}

interface ChartsData {
  eventsByDay: DayEventCount[];
  quotaByDay: DayQuota[];
}

export default function DashboardPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [charts, setCharts] = useState<ChartsData | null>(null);
  const [chartsLoading, setChartsLoading] = useState(true);
  const [chartsError, setChartsError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch("/api/v1/dashboard")
      .then((res) => {
        if (!res.ok) throw new Error("Failed to load dashboard.");
        return res.json() as Promise<DashboardStats>;
      })
      .then(setStats)
      .catch((err: unknown) =>
        setError(
          err instanceof Error ? err.message : "Failed to load dashboard.",
        ),
      )
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    apiFetch("/api/v1/dashboard/charts")
      .then((res) => {
        if (!res.ok) throw new Error("Failed to load chart data.");
        return res.json() as Promise<ChartsData>;
      })
      .then(setCharts)
      .catch((err: unknown) =>
        setChartsError(
          err instanceof Error ? err.message : "Failed to load chart data.",
        ),
      )
      .finally(() => setChartsLoading(false));
  }, []);

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-semibold">Dashboard</h1>
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <Card key={i}>
              <CardHeader>
                <Skeleton className="h-4 w-24" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-8 w-16" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  if (!stats) return null;

  const quotaPct =
    stats.quotaLimit > 0
      ? Math.min(100, Math.round((stats.quotaUsed / stats.quotaLimit) * 100))
      : 0;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Dashboard</h1>
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card>
          <CardHeader>
            <CardAction>
              <Link to={"/sources"}>View sources</Link>
            </CardAction>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Total Sources
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">{stats.totalSources}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Total Endpoints
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">{stats.totalEndpoints}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Total Events
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">
              {stats.totalEventsReceived}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Last Event Received
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-lg font-semibold">
              {stats.lastEventReceivedAt
                ? new Date(stats.lastEventReceivedAt).toLocaleString()
                : "Never"}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Delivered
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">
              {stats.deliveredEventsCount}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Pending
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">{stats.pendingEventsCount}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Failed
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">{stats.failedEventsCount}</p>
          </CardContent>
        </Card>

        {stats.quotaLimit > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Request Quota (30d)
              </CardTitle>
              <CardDescription>
                {stats.quotaUsed.toLocaleString()} /{" "}
                {stats.quotaLimit.toLocaleString()}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-2 rounded-full bg-muted overflow-hidden">
                <div
                  className="h-full bg-primary transition-all"
                  style={{ width: `${quotaPct}%` }}
                />
              </div>
            </CardContent>
          </Card>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {chartsLoading ? (
          <>
            <Card>
              <CardHeader>
                <Skeleton className="h-4 w-48" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-52 w-full" />
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <Skeleton className="h-4 w-48" />
              </CardHeader>
              <CardContent>
                <Skeleton className="h-52 w-full" />
              </CardContent>
            </Card>
          </>
        ) : chartsError ? (
          <Alert variant="destructive" className="col-span-full">
            <AlertDescription>{chartsError}</AlertDescription>
          </Alert>
        ) : charts ? (
          <>
            <EventsByDayChart data={charts.eventsByDay} />
            {charts.quotaByDay.length > 0 && (
              <QuotaByDayChart data={charts.quotaByDay} />
            )}
          </>
        ) : null}
      </div>
    </div>
  );
}
