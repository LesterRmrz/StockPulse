import { ArrowLeft, Pencil, SlidersHorizontal, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useDeleteProduct, useProduct, useProductMovements } from '../../api/products'
import { useAuth } from '../../auth/auth-context'
import { Button } from '../../components/Button'
import { buttonClass } from '../../components/buttonClass'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Select } from '../../components/Field'
import { PageHeader } from '../../components/PageHeader'
import { Pagination } from '../../components/Pagination'
import { EmptyState, ErrorState, Loading } from '../../components/States'
import { InactiveBadge, StatusBadge } from '../../components/StatusBadge'
import { table, TableCard, td, th, tr } from '../../components/Table'
import { formatChange, formatDateTime, formatMargin, formatMoney, formatNumber } from '../../lib/format'
import { MOVEMENT_LABELS } from '../../lib/movements'
import type { MovementType, Product, StockLevel } from '../../lib/types'
import { AdjustStockDialog, type AdjustTarget } from '../inventory/AdjustStockDialog'
import { ReorderSettingsDialog } from '../inventory/ReorderSettingsDialog'

export function ProductDetailPage() {
  // useParams reads :id from the URL /products/:id (always a string)
  const id = Number(useParams().id)
  const { data: product, isPending, isError, error, refetch, isFetching } = useProduct(id)

  if (isPending) return <Loading />
  if (isError) return <ErrorState error={error} onRetry={() => void refetch()} />

  // isFetching here = refreshing after an adjustment, transfer or edit
  return <ProductDetail product={product} refreshing={isFetching} />
}

/** Split out so everything below can assume `product` is loaded (no undefined checks). */
function ProductDetail({ product, refreshing }: { product: Product; refreshing: boolean }) {
  const { user } = useAuth()
  const navigate = useNavigate()
  const remove = useDeleteProduct()
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [adjusting, setAdjusting] = useState<AdjustTarget | null>(null)
  const [editingReorder, setEditingReorder] = useState<StockLevel | null>(null)

  const stock = product.stock ?? []
  const label = `${product.name} (${product.sku})`

  return (
    <>
      <Link to="/products" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink">
        <ArrowLeft size={16} aria-hidden="true" />
        Products
      </Link>

      <PageHeader
        title={product.name}
        subtitle={
          <>
            {product.sku} · {product.category?.name}
            {!product.is_active && (
              <span className="ml-2 align-middle">
                <InactiveBadge />
              </span>
            )}
          </>
        }
        actions={
          user?.can.manage_catalog && (
            <div className="flex gap-2">
              <Link to={`/products/${product.id}/edit`} className={buttonClass('secondary')}>
                <Pencil size={16} aria-hidden="true" />
                Edit
              </Link>
              <Button variant="danger" onClick={() => setConfirmDelete(true)}>
                <Trash2 size={16} aria-hidden="true" />
                Delete
              </Button>
            </div>
          )
        }
      />

      {/* Dimmed while refreshing; the "Updating…" label shows on the stock table below */}
      <dl className={`mb-6 grid grid-cols-2 gap-3 transition-opacity lg:grid-cols-5 ${refreshing ? 'opacity-50' : ''}`}>
        <Stat label="In stock" value={formatNumber(product.total_quantity ?? 0)} highlight />
        <Stat label="Sell price" value={formatMoney(product.sell_price)} />
        <Stat label="Cost" value={formatMoney(product.cost_price)} />
        <Stat label="Margin" value={formatMargin(product.cost_price, product.sell_price)} />
        <Stat label="Supplier" value={product.supplier?.name ?? '—'} small />
      </dl>

      {product.description && <p className="mb-6 max-w-prose text-sm text-muted">{product.description}</p>}

      <h2 className="mb-3 font-display text-xl font-semibold">Stock by warehouse</h2>
      <TableCard busy={refreshing}>
        <table className={table}>
          <thead>
            <tr>
              <th className={th}>Warehouse</th>
              <th className={`${th} text-right`}>On hand</th>
              <th className={`${th} text-right`}>Reorder point</th>
              <th className={`${th} text-right`}>Reorder qty</th>
              <th className={th}>Status</th>
              <th className={th}>
                <span className="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {stock.map((level) => (
              <tr key={level.id} className={tr}>
                <td className={td}>
                  <span className="font-medium">{level.warehouse?.code}</span>
                  <span className="ml-2 text-muted">{level.warehouse?.name}</span>
                </td>
                <td className={`${td} text-right font-medium`}>{formatNumber(level.quantity)}</td>
                <td className={`${td} text-right text-muted`}>{formatNumber(level.reorder_point)}</td>
                <td className={`${td} text-right text-muted`}>{formatNumber(level.reorder_qty)}</td>
                <td className={td}>
                  <StatusBadge status={level.status} />
                </td>
                <td className={`${td} text-right whitespace-nowrap`}>
                  {user?.can.adjust_stock && level.warehouse?.is_active !== false && (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() =>
                        setAdjusting({
                          productId: product.id,
                          productLabel: label,
                          warehouseId: level.warehouse_id,
                          warehouseCode: level.warehouse?.code ?? '',
                          quantity: level.quantity,
                        })
                      }
                    >
                      Adjust
                    </Button>
                  )}
                  {user?.can.edit_reorder_settings && (
                    <Button size="sm" variant="ghost" onClick={() => setEditingReorder(level)} aria-label={`Reorder settings for ${level.warehouse?.code}`}>
                      <SlidersHorizontal size={16} aria-hidden="true" />
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </TableCard>

      <MovementHistory product={product} />

      {/* Dialogs are only rendered while open, so their form state resets each time */}
      {adjusting && <AdjustStockDialog target={adjusting} onClose={() => setAdjusting(null)} />}
      {editingReorder && (
        <ReorderSettingsDialog level={editingReorder} label={`${label} at ${editingReorder.warehouse?.code}`} onClose={() => setEditingReorder(null)} />
      )}
      <ConfirmDialog
        open={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        title="Delete product?"
        message={`${label} will be removed from the catalog. Its history stays in the ledger. This only works once its stock is zero everywhere.`}
        onConfirm={() => remove.mutateAsync(product.id).then(() => navigate('/products'))}
      />
    </>
  )
}

function Stat({ label, value, highlight, small }: { label: string; value: string; highlight?: boolean; small?: boolean }) {
  return (
    <div className="rounded-xl border border-line bg-surface p-4">
      <dt className="text-xs tracking-wide text-muted uppercase">{label}</dt>
      {/* title shows the full text on hover when a long name is cut off */}
      <dd title={value} className={`mt-1 truncate font-display font-semibold ${small ? 'text-base' : 'text-2xl'} ${highlight ? 'text-accent' : ''}`}>
        {value}
      </dd>
    </div>
  )
}

function MovementHistory({ product }: { product: Product }) {
  // Local state (not the URL): this is a section of the page, not the page itself
  const [filters, setFilters] = useState({ page: 1, warehouse_id: '', type: '' as '' | MovementType })
  const { data, isPending, isError, error, refetch, isFetching } = useProductMovements(product.id, filters)

  return (
    <>
      <h2 className="mt-8 mb-3 font-display text-xl font-semibold">Movement history</h2>
      <TableCard
        busy={isFetching && !isPending}
        toolbar={
          <>
            <Select aria-label="Warehouse" value={filters.warehouse_id} onChange={(e) => setFilters({ ...filters, warehouse_id: e.target.value, page: 1 })} className="w-auto">
              <option value="">All warehouses</option>
              {product.stock?.map((level) => (
                <option key={level.warehouse_id} value={level.warehouse_id}>
                  {level.warehouse?.code}
                </option>
              ))}
            </Select>
            <Select aria-label="Type" value={filters.type} onChange={(e) => setFilters({ ...filters, type: e.target.value as '' | MovementType, page: 1 })} className="w-auto">
              <option value="">All types</option>
              {Object.entries(MOVEMENT_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
          </>
        }
      >
        {isPending ? (
          <Loading />
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : data.data.length === 0 ? (
          <EmptyState title="No movements" />
        ) : (
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>When</th>
                <th className={th}>Type</th>
                <th className={th}>Warehouse</th>
                <th className={`${th} text-right`}>Change</th>
                <th className={`${th} text-right`}>After</th>
                <th className={th}>Reference</th>
                <th className={th}>By / note</th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((movement) => (
                <tr key={movement.id} className={tr}>
                  <td className={`${td} whitespace-nowrap text-muted`}>{formatDateTime(movement.created_at)}</td>
                  <td className={td}>{MOVEMENT_LABELS[movement.type]}</td>
                  <td className={td}>{movement.warehouse?.code}</td>
                  <td className={`${td} text-right font-medium ${movement.quantity_change > 0 ? 'text-success' : 'text-danger'}`}>
                    {formatChange(movement.quantity_change)}
                  </td>
                  <td className={`${td} text-right`}>{formatNumber(movement.quantity_after)}</td>
                  <td className={`${td} text-muted`}>{movement.reference?.label ?? '—'}</td>
                  <td className={`${td} max-w-64 truncate text-muted`} title={movement.note ?? undefined}>
                    {[movement.user, movement.note].filter(Boolean).join(' · ') || '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </TableCard>
      {data && <Pagination meta={data.meta} onPage={(page) => setFilters({ ...filters, page })} />}
    </>
  )
}
