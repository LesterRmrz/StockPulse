import { ArrowRight } from 'lucide-react'
import { useWarehouses } from '../../api/catalog'
import { useTransfers } from '../../api/inventory'
import { Select } from '../../components/Field'
import { Pagination } from '../../components/Pagination'
import { EmptyState, ErrorState, Loading } from '../../components/States'
import { table, TableCard, td, th, tr } from '../../components/Table'
import { formatDateTime, formatNumber } from '../../lib/format'
import { useUrlFilters } from '../../lib/useUrlFilters'

export function TransfersPage() {
  const { data: warehouses } = useWarehouses()
  const [filters, setFilters] = useUrlFilters({ warehouse_id: '', page: '1' })
  const { data, isPending, isError, error, refetch, isFetching } = useTransfers({
    warehouse_id: filters.warehouse_id,
    page: Number(filters.page),
  })

  return (
    <>
      <TableCard
        busy={isFetching && !isPending}
        toolbar={
          <Select aria-label="Warehouse" value={filters.warehouse_id} onChange={(e) => setFilters({ warehouse_id: e.target.value })} className="w-auto">
            <option value="">All warehouses</option>
            {warehouses?.map((warehouse) => (
              <option key={warehouse.id} value={warehouse.id}>
                {warehouse.code}
              </option>
            ))}
          </Select>
        }
      >
        {isPending ? (
          <Loading />
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : data.data.length === 0 ? (
          <EmptyState title="No transfers yet">Use “New transfer” to move stock between warehouses.</EmptyState>
        ) : (
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>Transfer</th>
                <th className={th}>Route</th>
                <th className={th}>Products</th>
                <th className={`${th} text-right`}>Units</th>
                <th className={th}>By / note</th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((transfer) => (
                <tr key={transfer.id} className={`${tr} align-top`}>
                  <td className={td}>
                    <div className="font-medium">{transfer.transfer_number}</div>
                    <div className="text-xs whitespace-nowrap text-muted">{formatDateTime(transfer.created_at)}</div>
                  </td>
                  <td className={`${td} whitespace-nowrap`}>
                    <span className="inline-flex items-center gap-1.5">
                      {transfer.from?.code}
                      <ArrowRight size={14} className="text-muted" aria-label="to" />
                      {transfer.to?.code}
                    </span>
                  </td>
                  <td className={td}>
                    <ul className="flex flex-col gap-0.5">
                      {transfer.items?.map((item) => (
                        <li key={item.product.id}>
                          <span className="text-muted">{item.quantity} ×</span> {item.product.name}
                        </li>
                      ))}
                    </ul>
                  </td>
                  <td className={`${td} text-right font-medium`}>{formatNumber(transfer.total_units ?? 0)}</td>
                  <td className={`${td} text-muted`}>{[transfer.user, transfer.note].filter(Boolean).join(' · ') || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </TableCard>
      {data && <Pagination meta={data.meta} onPage={(page) => setFilters({ page: String(page) })} />}
    </>
  )
}
