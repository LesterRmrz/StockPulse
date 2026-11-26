import { Plus, RotateCcw } from 'lucide-react'
import { Link, useNavigate } from 'react-router-dom'
import { useCategories, useSuppliers } from '../../api/catalog'
import { useProducts, useRestoreProduct, type ProductFilters } from '../../api/products'
import { useAuth } from '../../auth/auth-context'
import { Button } from '../../components/Button'
import { buttonClass } from '../../components/buttonClass'
import { FormError, Select } from '../../components/Field'
import { PageHeader } from '../../components/PageHeader'
import { Pagination } from '../../components/Pagination'
import { SearchInput } from '../../components/SearchInput'
import { EmptyState, ErrorState, Loading } from '../../components/States'
import { InactiveBadge } from '../../components/StatusBadge'
import { table, TableCard, td, th, tr, trLink } from '../../components/Table'
import { errorMessage } from '../../lib/api'
import { formatDateTime, formatMoney, formatNumber } from '../../lib/format'
import { useDebouncedValue } from '../../lib/useDebouncedValue'
import { useUrlFilters } from '../../lib/useUrlFilters'

const SORTS = {
  'name:asc': 'Name A–Z',
  'name:desc': 'Name Z–A',
  'total_quantity:asc': 'Lowest stock',
  'total_quantity:desc': 'Highest stock',
  'sell_price:desc': 'Price high–low',
  'sell_price:asc': 'Price low–high',
} as const

export function ProductsPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const { data: categories } = useCategories()
  const { data: suppliers } = useSuppliers({ per_page: 100 })
  const restore = useRestoreProduct()
  const [filters, setFilters] = useUrlFilters({ search: '', category_id: '', supplier_id: '', stock: '', sort: 'name:asc', deleted: '', page: '1' })

  // The URL updates on every keystroke, but the request waits until typing pauses
  const search = useDebouncedValue(filters.search)
  const [sort, direction] = filters.sort.split(':') as [ProductFilters['sort'], ProductFilters['direction']]
  // Only admins and managers may see deleted products (the API would answer 403 to anyone else)
  const showDeleted = !!user?.can.manage_catalog && filters.deleted === '1'

  const { data, isPending, isError, error, refetch, isFetching } = useProducts({
    search,
    category_id: filters.category_id,
    supplier_id: filters.supplier_id,
    stock: filters.stock as ProductFilters['stock'],
    sort,
    direction,
    deleted: showDeleted ? '1' : '',
    page: Number(filters.page),
  })

  return (
    <>
      <PageHeader
        title="Products"
        subtitle="Every SKU in the catalog, with stock across all warehouses."
        actions={
          user?.can.manage_catalog && (
            <Link to="/products/new" className={buttonClass('primary')}>
              <Plus size={16} aria-hidden="true" />
              New product
            </Link>
          )
        }
      />

      {/* After a restore: say so, and offer the way back to the product */}
      {restore.isSuccess && (
        <p role="status" className="mb-4 rounded-lg border border-success/40 bg-success/10 px-3.5 py-2.5 text-sm text-success">
          {restore.data.name} is back in the catalog.{' '}
          <Link to={`/products/${restore.data.id}`} className="font-semibold underline">
            Open it
          </Link>
        </p>
      )}
      {restore.isError && (
        <div className="mb-4">
          <FormError message={errorMessage(restore.error)} />
        </div>
      )}

      <TableCard
        busy={isFetching && !isPending}
        toolbar={
          <>
            <SearchInput value={filters.search} onChange={(value) => setFilters({ search: value })} placeholder="Search name or SKU…" />
            <Select aria-label="Category" value={filters.category_id} onChange={(e) => setFilters({ category_id: e.target.value })} className="w-auto">
              <option value="">All categories</option>
              {categories?.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.parent ? `${category.parent} › ` : ''}
                  {category.name}
                </option>
              ))}
            </Select>

            <Select aria-label="Supplier" value={filters.supplier_id} onChange={(e) => setFilters({ supplier_id: e.target.value })} className="w-auto">
              <option value="">All suppliers</option>
              {suppliers?.data.map((supplier) => (
                <option key={supplier.id} value={supplier.id}>
                  {supplier.name}
                </option>
              ))}
            </Select>

            <Select aria-label="Stock" value={filters.stock} onChange={(e) => setFilters({ stock: e.target.value })} className="w-auto">
              <option value="">Any stock level</option>
              <option value="attention">Needs reordering</option>
              <option value="out">Out of stock everywhere</option>
            </Select>
            <Select aria-label="Sort" value={filters.sort} onChange={(e) => setFilters({ sort: e.target.value })} className="w-auto">
              {Object.entries(SORTS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
            {user?.can.manage_catalog && (
              <Select aria-label="Show" value={showDeleted ? '1' : ''} onChange={(e) => setFilters({ deleted: e.target.value })} className="w-auto">
                <option value="">Catalog</option>
                <option value="1">Deleted products</option>
              </Select>
            )}
          </>
        }
      >
        {isPending ? (
          <Loading />
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : data.data.length === 0 ? (
          showDeleted ? (
            <EmptyState title="No deleted products">Products you delete show up here, so you can bring them back.</EmptyState>
          ) : (
            <EmptyState title="No products found">Try a different search or clear the filters.</EmptyState>
          )
        ) : showDeleted ? (
          // Deleted products have no page to open (it's a 404), so these rows aren't links
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>Product</th>
                <th className={th}>Category</th>
                <th className={th}>Deleted</th>
                <th className={th}>
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((product) => {
                const restoring = restore.isPending && restore.variables === product.id

                return (
                  <tr key={product.id} className={tr}>
                    <td className={td}>
                      <div className="font-medium">{product.name}</div>
                      <div className="text-xs text-muted">{product.sku}</div>
                    </td>
                    <td className={`${td} text-muted`}>{product.category?.name}</td>
                    <td className={`${td} text-muted`}>{product.deleted_at && formatDateTime(product.deleted_at)}</td>
                    <td className={`${td} text-right`}>
                      <Button size="sm" disabled={restore.isPending} onClick={() => restore.mutate(product.id)}>
                        <RotateCcw size={14} aria-hidden="true" />
                        {restoring ? 'Restoring…' : 'Restore'}
                      </Button>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        ) : (
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>Product</th>
                <th className={th}>Category</th>
                <th className={`${th} text-right`}>Price</th>
                <th className={`${th} text-right`}>In stock</th>
                <th className={th}>Reorder</th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((product) => (
                // Inactive rows are faded, like inactive warehouses
                <tr key={product.id} className={`${trLink} ${product.is_active ? '' : 'opacity-60'}`} onClick={() => navigate(`/products/${product.id}`)}>
                  <td className={td}>
                    <div className="flex items-center gap-2">
                      {/* A real link too, so keyboard users and "open in new tab" work */}
                      <Link to={`/products/${product.id}`} className="font-medium hover:text-accent" onClick={(e) => e.stopPropagation()}>
                        {product.name}
                      </Link>
                      {!product.is_active && <InactiveBadge />}
                    </div>
                    <div className="text-xs text-muted">{product.sku}</div>
                  </td>
                  <td className={`${td} text-muted`}>{product.category?.name}</td>
                  <td className={`${td} text-right`}>{formatMoney(product.sell_price)}</td>
                  <td className={`${td} text-right font-medium`}>{formatNumber(product.total_quantity ?? 0)}</td>
                  <td className={td}>
                    {product.total_quantity === 0 ? (
                      <span className="text-xs font-semibold text-danger">Out everywhere</span>
                    ) : product.attention_count ? (
                      <span className="text-xs font-semibold text-warning">
                        {product.attention_count} warehouse{product.attention_count > 1 ? 's' : ''}
                      </span>
                    ) : (
                      <span className="text-xs text-muted">—</span>
                    )}
                  </td>
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
