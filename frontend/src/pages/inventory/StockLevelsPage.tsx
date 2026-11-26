import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useCategories, useWarehouses } from '../../api/catalog'
import { useStockLevels, type StockLevelFilters } from '../../api/inventory'
import { useAuth } from '../../auth/auth-context'
import { Button } from '../../components/Button'
import { Select } from '../../components/Field'
import { Pagination } from '../../components/Pagination'
import { SearchInput } from '../../components/SearchInput'
import { EmptyState, ErrorState, Loading } from '../../components/States'
import { InactiveBadge, StatusBadge } from '../../components/StatusBadge'
import { STATUS_LABELS } from '../../lib/stockStatus'
import { table, TableCard, td, th, tr } from '../../components/Table'
import { formatNumber } from '../../lib/format'
import { useDebouncedValue } from '../../lib/useDebouncedValue'
import { useUrlFilters } from '../../lib/useUrlFilters'
import { AdjustStockDialog, type AdjustTarget } from './AdjustStockDialog'

export function StockLevelsPage() {
  const { user } = useAuth()
  const { data: warehouses } = useWarehouses()
  const { data: categories } = useCategories()
  const [adjusting, setAdjusting] = useState<AdjustTarget | null>(null)
  const [filters, setFilters] = useUrlFilters({ search: '', warehouse_id: '', category_id: '', status: '', sort: 'urgency', page: '1' })
  const search = useDebouncedValue(filters.search)

  const { data, isPending, isError, error, refetch, isFetching } = useStockLevels({
    ...filters,
    search,
    status: filters.status as StockLevelFilters['status'],
    sort: filters.sort as StockLevelFilters['sort'],
    page: Number(filters.page),
  })

  return (
    <>
      <TableCard
        busy={isFetching && !isPending}
        toolbar={
          <>
            <SearchInput value={filters.search} onChange={(value) => setFilters({ search: value })} placeholder="Search name or SKU…" />
            <Select aria-label="Warehouse" value={filters.warehouse_id} onChange={(e) => setFilters({ warehouse_id: e.target.value })} className="w-auto">
              <option value="">All warehouses</option>
              {warehouses?.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.code}
                </option>
              ))}
            </Select>
            <Select aria-label="Category" value={filters.category_id} onChange={(e) => setFilters({ category_id: e.target.value })} className="w-auto">
              <option value="">All categories</option>
              {categories?.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </Select>
            <Select aria-label="Status" value={filters.status} onChange={(e) => setFilters({ status: e.target.value })} className="w-auto">
              <option value="">Any status</option>
              <option value="attention">Needs reordering</option>
              {Object.entries(STATUS_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
            <Select aria-label="Sort" value={filters.sort} onChange={(e) => setFilters({ sort: e.target.value })} className="w-auto">
              <option value="urgency">Most urgent first</option>
              <option value="name">Product name</option>
              <option value="quantity">Lowest quantity</option>
            </Select>
          </>
        }
      >
        {isPending ? (
          <Loading />
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : data.data.length === 0 ? (
          <EmptyState title="Nothing matches">Try a different search or clear the filters.</EmptyState>
        ) : (
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>Product</th>
                <th className={th}>Warehouse</th>
                <th className={`${th} text-right`}>On hand</th>
                <th className={`${th} text-right`}>Reorder point</th>
                <th className={th}>Status</th>
                <th className={th}>
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((level) => (
                <tr key={level.id} className={`${tr} ${level.product?.is_active === false ? 'opacity-60' : ''}`}>
                  <td className={td}>
                    <div className="flex items-center gap-2">
                      <Link to={`/products/${level.product_id}`} className="font-medium hover:text-accent">
                        {level.product?.name}
                      </Link>
                      {level.product?.is_active === false && <InactiveBadge />}
                    </div>
                    <div className="text-xs text-muted">
                      {level.product?.sku} · {level.product?.category}
                    </div>
                  </td>
                  <td className={td}>{level.warehouse?.code}</td>
                  <td className={`${td} text-right font-medium`}>{formatNumber(level.quantity)}</td>
                  <td className={`${td} text-right text-muted`}>{formatNumber(level.reorder_point)}</td>
                  <td className={td}>
                    <StatusBadge status={level.status} />
                  </td>
                  <td className={`${td} text-right`}>
                    {user?.can.adjust_stock && level.warehouse?.is_active !== false && (
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                          setAdjusting({
                            productId: level.product_id,
                            productLabel: `${level.product?.name} (${level.product?.sku})`,
                            warehouseId: level.warehouse_id,
                            warehouseCode: level.warehouse?.code ?? '',
                            quantity: level.quantity,
                          })
                        }
                      >
                        Adjust
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </TableCard>
      {data && <Pagination meta={data.meta} onPage={(page) => setFilters({ page: String(page) })} />}

      {adjusting && <AdjustStockDialog target={adjusting} onClose={() => setAdjusting(null)} />}
    </>
  )
}
