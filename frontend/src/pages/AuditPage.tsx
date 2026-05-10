import { useEffect, useState } from 'react'
import { apiFetch } from '@/lib/apiFetch'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

interface AuditEntry {
  id: string
  action: 'create' | 'delete' | 'edit'
  resource: string
  resource_id: string | null
  metadata: Record<string, unknown>
  created_at: string
}

interface AuditResponse {
  data: AuditEntry[]
  total: number
  page: number
  per_page: number
}

const ACTION_OPTIONS = ['create', 'delete', 'edit'] as const

const actionVariant = (action: AuditEntry['action']) => {
  if (action === 'create') return 'default' as const
  if (action === 'delete') return 'destructive' as const
  return 'secondary' as const
}

const PER_PAGE = 20

export default function AuditPage() {
  const [entries, setEntries] = useState<AuditEntry[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedActions, setSelectedActions] = useState<string[]>([])
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  useEffect(() => {
    setLoading(true)
    setError(null)

    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(PER_PAGE))
    selectedActions.forEach((a) => params.append('action[]', a))
    if (dateFrom) params.set('date_from', `${dateFrom}T00:00:00+00:00`)
    if (dateTo) params.set('date_to', `${dateTo}T23:59:59+00:00`)

    apiFetch(`/api/v1/audit?${params.toString()}`)
      .then((res) => {
        if (!res.ok) throw new Error('Failed to load audit logs.')
        return res.json() as Promise<AuditResponse>
      })
      .then((data) => {
        setEntries(data.data)
        setTotal(data.total)
      })
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load audit logs.'))
      .finally(() => setLoading(false))
  }, [page, selectedActions, dateFrom, dateTo])

  const totalPages = Math.max(1, Math.ceil(total / PER_PAGE))

  const toggleAction = (action: string) => {
    setSelectedActions((prev) =>
      prev.includes(action) ? prev.filter((a) => a !== action) : [...prev, action]
    )
    setPage(1)
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">Audit</h1>

      <Card>
        <CardContent className="pt-4">
          <div className="flex gap-3 flex-wrap items-center">
            <div className="flex gap-2 items-center">
              <span className="text-sm font-medium">Action:</span>
              <div className="flex gap-1">
                {ACTION_OPTIONS.map((action) => (
                  <Button
                    key={action}
                    variant={selectedActions.includes(action) ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => toggleAction(action)}
                  >
                    {action}
                  </Button>
                ))}
              </div>
            </div>

            <div className="flex gap-2 items-center">
              <span className="text-sm font-medium">From:</span>
              <input
                type="date"
                value={dateFrom}
                onChange={(e) => { setDateFrom(e.target.value); setPage(1) }}
                className="border rounded px-2 py-1 text-sm"
              />
            </div>

            <div className="flex gap-2 items-center">
              <span className="text-sm font-medium">To:</span>
              <input
                type="date"
                value={dateTo}
                onChange={(e) => { setDateTo(e.target.value); setPage(1) }}
                className="border rounded px-2 py-1 text-sm"
              />
            </div>

            {(selectedActions.length > 0 || dateFrom || dateTo) && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => { setSelectedActions([]); setDateFrom(''); setDateTo(''); setPage(1) }}
              >
                Clear filters
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>When</TableHead>
            <TableHead>Action</TableHead>
            <TableHead>Resource</TableHead>
            <TableHead>Details</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {loading ? (
            Array.from({ length: 5 }).map((_, i) => (
              <TableRow key={i}>
                {Array.from({ length: 4 }).map((__, j) => (
                  <TableCell key={j}>
                    <Skeleton className="h-4 w-full" />
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : entries.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4}>
                <p className="text-center text-muted-foreground py-8">No audit records found.</p>
              </TableCell>
            </TableRow>
          ) : (
            entries.map((entry) => (
              <TableRow key={entry.id}>
                <TableCell>{new Date(entry.created_at).toLocaleString()}</TableCell>
                <TableCell>
                  <Badge variant={actionVariant(entry.action)}>{entry.action}</Badge>
                </TableCell>
                <TableCell className="capitalize">{entry.resource}</TableCell>
                <TableCell className="text-sm text-muted-foreground">
                  {Object.entries(entry.metadata)
                    .map(([k, v]) => `${k}=${Array.isArray(v) ? v.join(',') : String(v)}`)
                    .join(', ')}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <div className="flex items-center justify-between pt-4">
        <span className="text-sm text-muted-foreground">{total} results</span>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={page === 1}
            onClick={() => setPage((p) => p - 1)}
          >
            Previous
          </Button>
          <span className="text-sm px-2">Page {page} of {totalPages}</span>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= totalPages}
            onClick={() => setPage((p) => p + 1)}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  )
}
