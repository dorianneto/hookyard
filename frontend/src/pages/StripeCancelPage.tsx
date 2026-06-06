import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { IconTrack } from "@tabler/icons-react";

export default function StripeCancelPage() {
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
            <CardTitle>Payment cancelled</CardTitle>
            <CardDescription>
              No charge was made. You can try again whenever you're ready.
            </CardDescription>
          </CardHeader>
          <CardContent />
          <CardFooter>
            <Button asChild variant="outline" className="w-full">
              <Link to="/register">Back to registration</Link>
            </Button>
          </CardFooter>
        </Card>
      </div>
    </div>
  );
}
