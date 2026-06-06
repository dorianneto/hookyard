import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { IconTrack } from "@tabler/icons-react";

export default function RegisterPendingPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [retrying, setRetrying] = useState(false);

  const resumeCheckout = async () => {
    setError(null);
    setRetrying(true);

    try {
      const res = await fetch("/api/v1/register/resume", { method: "POST" });
      const data = await res.json().catch(() => ({}));

      if (res.status === 409) {
        navigate("/");
        return;
      }

      if (res.status === 422) {
        navigate("/register");
        return;
      }

      if (!res.ok) {
        throw new Error((data as { error?: string }).error ?? "Failed to resume checkout.");
      }

      window.location.href = (data as { checkout_url: string }).checkout_url;
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to resume checkout.");
      setRetrying(false);
    }
  };

  useEffect(() => {
    if (user) {
      resumeCheckout();
    }
  }, []);

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
            <CardTitle>Resuming checkout…</CardTitle>
            <CardDescription>
              {error
                ? "Something went wrong. Please try again."
                : "You will be redirected to complete your payment shortly."}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {error && (
              <>
                <Alert variant="destructive">
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
                <Button
                  className="w-full"
                  disabled={retrying}
                  onClick={resumeCheckout}
                >
                  {retrying ? "Resuming…" : "Try again"}
                </Button>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
