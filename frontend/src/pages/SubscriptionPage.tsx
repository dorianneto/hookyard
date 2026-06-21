import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { useAuth } from '@/contexts/AuthContext'
import { apiFetch } from '@/lib/apiFetch'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'

interface Plan {
  id: string
  name: string
  monthly_request_limit: number
}

interface SubscriptionData {
  current_plan: Plan | null
  available_plans: Plan[]
  status: string
}

export default function SubscriptionPage() {
  const { user, updateUser } = useAuth()
  const [searchParams] = useSearchParams()

  const [subscription, setSubscription] = useState<SubscriptionData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [switching, setSwitching] = useState<string | null>(null)
  const [cancelling, setCancelling] = useState(false)
  const [cancelDialogOpen, setCancelDialogOpen] = useState(false)

  useEffect(() => {
    apiFetch('/api/v1/subscription')
      .then((res) => {
        if (!res.ok) throw new Error('Failed to load subscription.')
        return res.json() as Promise<SubscriptionData>
      })
      .then((data) => setSubscription(data))
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed to load subscription.'))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (searchParams.get('changed') === '1') {
      toast.success('Plan updated successfully.')
    }
  }, [searchParams])

  const handleSwitch = async (planId: string) => {
    setSwitching(planId)
    try {
      const res = await apiFetch('/api/v1/subscription/change-plan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ plan_id: planId }),
      })
      const data = (await res.json()) as { checkout_url?: string; error?: string }
      if (!res.ok) {
        throw new Error(data.error ?? 'Failed to change plan.')
      }
      window.location.href = data.checkout_url!
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to change plan.')
      setSwitching(null)
    }
  }

  const handleCancel = async () => {
    setCancelling(true)
    try {
      const res = await apiFetch('/api/v1/subscription', { method: 'DELETE' })
      if (!res.ok) {
        const data = (await res.json()) as { error?: string }
        throw new Error(data.error ?? 'Failed to cancel subscription.')
      }
      if (user) {
        updateUser({ ...user, status: 'cancelled' })
      }
      setSubscription((prev) => (prev ? { ...prev, status: 'cancelled', current_plan: null } : prev))
      setCancelDialogOpen(false)
      toast.success('Subscription cancelled.')
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to cancel subscription.')
    } finally {
      setCancelling(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink asChild>
                <Link to="/">Dashboard</Link>
              </BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Subscription</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <p className="text-muted-foreground text-sm">Loading…</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink asChild>
                <Link to="/">Dashboard</Link>
              </BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Subscription</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      </div>
    )
  }

  const isCancelled = subscription?.status === 'cancelled'

  return (
    <div className="space-y-6">
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink asChild>
              <Link to="/">Dashboard</Link>
            </BreadcrumbLink>
          </BreadcrumbItem>
          <BreadcrumbSeparator />
          <BreadcrumbItem>
            <BreadcrumbPage>Subscription</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>

      {isCancelled && (
        <Alert>
          <AlertDescription>
            Your subscription has been cancelled. Select a plan below to reactivate your account.
          </AlertDescription>
        </Alert>
      )}

      {subscription?.current_plan && (
        <Card className="max-w-md">
          <CardHeader>
            <CardTitle>Current Plan</CardTitle>
          </CardHeader>
          <CardContent className="space-y-1">
            <div className="flex items-center gap-2">
              <span className="font-semibold">{subscription.current_plan.name}</span>
              <Badge variant="default">Active</Badge>
            </div>
            <p className="text-muted-foreground text-sm">
              {subscription.current_plan.monthly_request_limit.toLocaleString()} requests / month
            </p>
          </CardContent>
        </Card>
      )}

      <div className="space-y-2">
        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
          Available Plans
        </h2>
        <div className="grid gap-4 sm:grid-cols-3">
          {subscription?.available_plans.map((plan) => {
            const isCurrent = plan.id === subscription.current_plan?.id
            return (
              <Card key={plan.id} className={isCurrent ? 'border-primary' : ''}>
                <CardHeader>
                  <CardTitle className="flex items-center justify-between text-base">
                    {plan.name}
                    {isCurrent && <Badge variant="default">Current</Badge>}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <p className="text-muted-foreground text-sm">
                    {plan.monthly_request_limit.toLocaleString()} requests / month
                  </p>
                  <Button
                    className="w-full"
                    variant={isCurrent ? 'secondary' : 'default'}
                    disabled={isCurrent || switching !== null}
                    onClick={() => handleSwitch(plan.id)}
                  >
                    {switching === plan.id ? 'Redirecting…' : isCurrent ? 'Current Plan' : `Switch to ${plan.name}`}
                  </Button>
                </CardContent>
              </Card>
            )
          })}
        </div>
      </div>

      {!isCancelled && (
        <div className="pt-4 border-t">
          <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
            <DialogTrigger asChild>
              <Button variant="destructive">Cancel Subscription</Button>
            </DialogTrigger>
            <DialogContent showCloseButton={false}>
              <DialogHeader>
                <DialogTitle>Cancel Subscription</DialogTitle>
                <DialogDescription>
                  This will immediately cancel your subscription. You will lose access to your current plan.
                  This action cannot be undone.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button variant="outline" onClick={() => setCancelDialogOpen(false)} disabled={cancelling}>
                  Keep Subscription
                </Button>
                <Button variant="destructive" onClick={handleCancel} disabled={cancelling}>
                  {cancelling ? 'Cancelling…' : 'Yes, Cancel'}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      )}
    </div>
  )
}
