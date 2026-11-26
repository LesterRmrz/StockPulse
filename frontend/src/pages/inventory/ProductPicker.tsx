import { useId, useState } from 'react'
import { useStockLevels } from '../../api/inventory'
import { Spinner } from '../../components/Busy'
import { controlClass } from '../../components/Field'
import { formatNumber } from '../../lib/format'
import type { StockLevel } from '../../lib/types'
import { useDebouncedValue } from '../../lib/useDebouncedValue'

/**
 * Search box that lists products *in one warehouse*, with how many are
 * available there. Searching stock levels (not products) gives us the
 * available quantity for free.
 */
export function ProductPicker({
  warehouseId,
  exclude,
  onPick,
}: {
  warehouseId: number
  exclude: number[]
  onPick: (level: StockLevel) => void
}) {
  const [term, setTerm] = useState('')
  const [open, setOpen] = useState(false)
  const search = useDebouncedValue(term)
  const listId = useId() // a unique id to connect the input to its list (for screen readers)

  const { data, isFetching } = useStockLevels({ warehouse_id: String(warehouseId), search, sort: 'name', per_page: 8 })
  const options = (data?.data ?? []).filter((level) => !exclude.includes(level.product_id))
  const searching = isFetching || term !== search

  return (
    <div className="relative">
      <input
        role="combobox"
        aria-expanded={open}
        aria-controls={listId}
        aria-label="Add a product"
        value={term}
        onChange={(e) => {
          setTerm(e.target.value)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        // Delay closing so a click on an option still lands
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        placeholder="Search a product to add…"
        className={`${controlClass} w-full`}
      />
      {open && (
        <ul id={listId} role="listbox" className="absolute z-10 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-line-strong bg-surface-2 py-1 shadow-xl">
          {/* Still typing (the debounce hasn't fired yet) or waiting for the API */}
          {searching && (
            <li className="flex items-center gap-2 px-3.5 py-2 text-xs text-muted" role="status">
              <Spinner className="size-3" />
              Searching…
            </li>
          )}
          {options.length === 0 ? (
            !searching && <li className="px-3.5 py-2.5 text-sm text-muted">No matching products</li>
          ) : (
            options.map((level) => (
              <li key={level.id} role="option" aria-selected={false}>
                <button
                  type="button"
                  disabled={level.quantity === 0}
                  onClick={() => {
                    onPick(level)
                    setTerm('')
                    setOpen(false)
                  }}
                  className="flex w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-sm hover:bg-surface disabled:opacity-50"
                >
                  <span className="min-w-0">
                    <span className="block truncate">{level.product?.name}</span>
                    <span className="text-xs text-muted">{level.product?.sku}</span>
                  </span>
                  <span className="shrink-0 text-xs text-muted">{formatNumber(level.quantity)} available</span>
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  )
}
