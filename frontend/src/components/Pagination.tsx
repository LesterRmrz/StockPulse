import { ChevronLeft, ChevronRight } from 'lucide-react'
import { formatNumber } from '../lib/format'
import type { Paginated } from '../lib/types'
import { Button } from './Button'

/** "Showing 26–50 of 150" with previous / next buttons. */
export function Pagination({ meta, onPage }: { meta: Paginated<unknown>['meta']; onPage: (page: number) => void }) {
  if (meta.total === 0) return null

  return (
    <nav aria-label="Pagination" className="flex flex-wrap items-center justify-between gap-3 px-1 pt-4 text-sm text-muted">
      <span>
        Showing {formatNumber(meta.from ?? 0)}–{formatNumber(meta.to ?? 0)} of {formatNumber(meta.total)}
      </span>
      <div className="flex items-center gap-2">
        <Button size="sm" onClick={() => onPage(meta.current_page - 1)} disabled={meta.current_page <= 1} aria-label="Previous page">
          <ChevronLeft size={16} aria-hidden="true" />
        </Button>
        <span className="min-w-20 text-center">
          Page {meta.current_page} of {meta.last_page}
        </span>
        <Button size="sm" onClick={() => onPage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page} aria-label="Next page">
          <ChevronRight size={16} aria-hidden="true" />
        </Button>
      </div>
    </nav>
  )
}
