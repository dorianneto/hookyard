import { useState, useEffect } from "react";
import type { FormEvent } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { IconTrack } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

interface Plan {
  id: string;
  name: string;
  monthly_request_limit: number;
}

export default function RegisterPage() {
  const [plans, setPlans] = useState<Plan[]>([]);
  const [selectedPlanId, setSelectedPlanId] = useState<string | null>(null);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [plansLoading, setPlansLoading] = useState(true);

  useEffect(() => {
    fetch("/api/v1/plans")
      .then((res) => res.json())
      .then((data: { data: Plan[] }) => setPlans(data.data))
      .catch(() => setError("Failed to load plans. Please refresh the page."))
      .finally(() => setPlansLoading(false));
  }, []);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);

    if (password !== confirmPassword) {
      setError("Passwords do not match.");
      return;
    }

    setLoading(true);

    try {
      const res = await fetch("/api/v1/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password, plan_id: selectedPlanId }),
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        throw new Error(
          (data as { error?: string }).error ?? "Registration failed.",
        );
      }

      window.location.href = (data as { checkout_url: string }).checkout_url;
    } catch (err) {
      setError(err instanceof Error ? err.message : "Registration failed.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
      <div className="flex w-full max-w-sm flex-col gap-6">
        <a href="#" className="flex items-center gap-2 self-center font-medium">
          <div className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
            <IconTrack className="size-4" />
          </div>
          HookYard
        </a>
        <Card className="w-full max-w-sm">
          <CardHeader>
            <CardTitle>Create account</CardTitle>
            <CardDescription>
              Sign up to start receiving webhooks.
            </CardDescription>
          </CardHeader>
          <form onSubmit={handleSubmit}>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label>Choose a plan</Label>
                {plansLoading ? (
                  <div className="grid gap-2">
                    {[1, 2].map((i) => (
                      <div
                        key={i}
                        className="h-16 rounded-lg border bg-muted animate-pulse"
                      />
                    ))}
                  </div>
                ) : (
                  <div className="grid gap-2">
                    {plans.map((plan) => (
                      <div
                        key={plan.id}
                        onClick={() => setSelectedPlanId(plan.id)}
                        className={cn(
                          "cursor-pointer rounded-lg border p-4 transition-colors",
                          selectedPlanId === plan.id
                            ? "border-primary bg-primary/5"
                            : "border-border hover:border-primary/50",
                        )}
                      >
                        <p className="font-semibold">{plan.name}</p>
                        <p className="text-sm text-muted-foreground">
                          {plan.monthly_request_limit.toLocaleString()} requests/month
                        </p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoComplete="email"
                  placeholder="you@example.com"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  autoComplete="new-password"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="confirm-password">Confirm password</Label>
                <Input
                  id="confirm-password"
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                  autoComplete="new-password"
                />
              </div>
              {error && (
                <Alert variant="destructive">
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}
            </CardContent>
            <CardFooter className="flex flex-col gap-3 mt-4">
              <Button
                type="submit"
                className="w-full"
                disabled={loading || selectedPlanId === null}
              >
                {loading ? "Creating account…" : "Create account"}
              </Button>
              <p className="text-sm text-muted-foreground text-center">
                Already have an account?{" "}
                <Link
                  to="/login"
                  className="text-primary underline-offset-4 hover:underline"
                >
                  Sign in
                </Link>
              </p>
            </CardFooter>
          </form>
        </Card>
      </div>
    </div>
  );
}
