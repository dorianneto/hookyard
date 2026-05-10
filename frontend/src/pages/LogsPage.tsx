import { useEffect, useState } from 'react'
import {
  useReactTable,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
} from '@tanstack/react-table'
import { cn } from '@/lib/utils'
import { apiFetch } from '@/lib/apiFetch'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

interface LogEntry {
  eventId: string
  eventReceivedAt: string
  sourceName: string
  sourceId: string
  endpointId: string
  endpointUrl: string
  deliveryStatus: 'pending' | 'delivered' | 'failed'
  attemptCount: number
  latestAttemptAt: string | null
  latestAttemptStatusCode: number | null
}

interface LogsResponse {
  data: LogEntry[]
  total: number
  page: number
  perPage: number
}

interface Source {
  id: string
  name: string
}

interface EndpointOption {
  id: string
  url: string
  sourceId: string
}

const PER_PAGE = 20

const statusVariant = (status: LogEntry['deliveryStatus']) => {
  if (status === 'delivered') return 'default'
  if (status === 'failed') return 'destructive'
  return 'secondary'
}

const columns: ColumnDef<LogEntry>[] = [
  {
    accessorKey: 'eventReceivedAt',
    header: 'Received At',
    cell: ({ getValue }) => new Date(getValue<string>()).toLocaleString(),
  },
  {
    accessorKey: 'sourceName',
    header: 'Source',
  },
  {
    accessorKey: 'endpointUrl',
    header: 'Endpoint URL',
    cell: ({ getValue }) => {
      const url = getValue<string>()
      return url.length > 40 ? url.slice(0, 40) + '…' : url
    },
  },
  {
    accessorKey: 'deliveryStatus',
    header: 'Status',
    cell: ({ getValue }) => {
      const status = getValue<LogEntry['deliveryStatus']>()
      return <Badge variant={statusVariant(status)}>{status}</Badge>
    },
  },
  {
    accessorKey: 'attemptCount',
    header: 'Attempts',
  },
  {
    accessorKey: 'latestAttemptAt',
    header: 'Last Attempt',
    cell: ({ getValue }) => {
      const v = getValue<string | null>()
      return v ? new Date(v).toLocaleString() : '—'
    },
  },
  {
    accessorKey: 'latestAttemptStatusCode',
    header: 'HTTP Code',
    cell: ({ getValue }) => {
      const v = getValue<number | null>()
      return v !== null ? v : '—'
    },
  },
]

export default function LogsPage() {
  const [logs, setLogs] = useState<LogEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [selectedEndpointIds, setSelectedEndpointIds] = useState<string[]>([])
  const [selectedSourceIds, setSelectedSourceIds] = useState<string[]>([])
  const [selectedStatus, setSelectedStatus] = useState<string>('')
  const [sources, setSources] = useState<Source[]>([])
  const [endpointOptions, setEndpointOptions] = useState<EndpointOption[]>([])

  useEffect(() => {
    apiFetch('/api/v1/sources')
      .then((res) => res.json() as Promise<Source[]>)
      .then((data) => {
        setSources(data)
        return Promise.all(
          data.map((src) =>
            apiFetch(`/api/v1/sources/${src.id}/endpoints`)
              .then((res) => res.json() as Promise<{ id: string; url: string }[]>)
              .then((eps) => eps.map((ep) => ({ id: ep.id, url: ep.url, sourceId: src.id })))
          )
        )
      })
      .then((nested) => setEndpointOptions(nested.flat()))
      .catch(() => {})
  }, [])

  useEffect(() => {
    setLoading(true)
    setError(null)

    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(PER_PAGE))
    selectedSourceIds.forEach((id) => params.append('source_ids[]', id))
    selectedEndpointIds.forEach((id) => params.append('endpoint_ids[]', id))
    if (selectedStatus) params.set('status', selectedStatus)

    apiFetch(`/api/v1/logs?${params.toString()}`)
      .then((res) => {
        if (!res.ok) throw new Error('Failed to load logs.')
        return res.json() as Promise<LogsResponse>
      })
      .then((data) => {
        setLogs(data.data)
        setTotal(data.total)
      })
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load logs.'))
      .finally(() => setLoading(false))
  }, [page, selectedEndpointIds, selectedSourceIds, selectedStatus])

  const table = useReactTable({
    data: logs,
    columns,
    getCoreRowModel: getCoreRowModel(),
  })

  const hasFilters =
    selectedSourceIds.length > 0 || selectedEndpointIds.length > 0 || selectedStatus !== ''

  const clearFilters = () => {
    setSelectedSourceIds([])
    setSelectedEndpointIds([])
    setSelectedStatus('')
    setPage(1)
  }

  const toggleSource = (id: string) => {
    setSelectedSourceIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    )
    setPage(1)
  }

  const toggleEndpoint = (id: string) => {
    setSelectedEndpointIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    )
    setPage(1)
  }

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-semibold">Logs</h1>

      <Card>
        <CardContent className="pt-4">
          <div className="flex gap-2 flex-wrap">
            <Popover>
              <PopoverTrigger className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}>
                Sources
                {selectedSourceIds.length > 0 && (
                  <Badge variant="secondary">
                    {selectedSourceIds.length}
                  </Badge>
                )}
              </PopoverTrigger>
              <PopoverContent className="w-56 p-0">
                <Command>
                  <CommandInput placeholder="Search sources…" />
                  <CommandList>
                    <CommandEmpty>No sources found.</CommandEmpty>
                    <CommandGroup>
                      {sources.map((src) => (
                        <CommandItem
                          key={src.id}
                          value={src.id}
                          onSelect={() => toggleSource(src.id)}
                          data-checked={selectedSourceIds.includes(src.id)}
                        >
                          {src.name}
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  </CommandList>
                </Command>
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}>
                Endpoints
                {selectedEndpointIds.length > 0 && (
                  <Badge variant="secondary">
                    {selectedEndpointIds.length}
                  </Badge>
                )}
              </PopoverTrigger>
              <PopoverContent className="w-72 p-0">
                <Command>
                  <CommandInput placeholder="Search endpoints…" />
                  <CommandList>
                    <CommandEmpty>No endpoints found.</CommandEmpty>
                    <CommandGroup>
                      {endpointOptions.map((ep) => (
                        <CommandItem
                          key={ep.id}
                          value={ep.id}
                          onSelect={() => toggleEndpoint(ep.id)}
                          data-checked={selectedEndpointIds.includes(ep.id)}
                        >
                          {ep.url.length > 40 ? ep.url.slice(0, 40) + '…' : ep.url}
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  </CommandList>
                </Command>
              </PopoverContent>
            </Popover>

            <Select
              value={selectedStatus}
              onValueChange={(val) => {
                setSelectedStatus(val === 'all' ? '' : val)
                setPage(1)
              }}
            >
              <SelectTrigger size="sm" className="w-32">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="delivered">Delivered</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
              </SelectContent>
            </Select>

            {hasFilters && (
              <Button variant="ghost" size="sm" onClick={clearFilters}>
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
          {table.getHeaderGroups().map((hg) => (
            <TableRow key={hg.id}>
              {hg.headers.map((header) => (
                <TableHead key={header.id}>
                  {flexRender(header.column.columnDef.header, header.getContext())}
                </TableHead>
              ))}
            </TableRow>
          ))}
        </TableHeader>
        <TableBody>
          {loading ? (
            Array.from({ length: 5 }).map((_, i) => (
              <TableRow key={i}>
                {Array.from({ length: 7 }).map((__, j) => (
                  <TableCell key={j}>
                    <Skeleton className="h-4 w-full" />
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : !error && logs.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7}>
                <p className="text-center text-muted-foreground py-8">No delivery logs found.</p>
              </TableCell>
            </TableRow>
          ) : (
            table.getRowModel().rows.map((row) => (
              <TableRow key={row.id}>
                {row.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </TableCell>
                ))}
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
          <span className="text-sm px-2">Page {page}</span>
          <Button
            variant="outline"
            size="sm"
            disabled={page * PER_PAGE >= total}
            onClick={() => setPage((p) => p + 1)}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  )
}
