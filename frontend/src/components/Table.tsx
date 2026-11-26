import type { ReactNode } from 'react'
import { BusyOverlay } from './Busy'

/**
 * Card with a horizontally scrollable table inside, so wide tables scroll on
 * phones without making the whole page scroll sideways.
 *
 * `busy`: the rows on screen are being refreshed (pages pass
 * `isFetching && !isPending`: fetching, but not the very first load).
 */
export function TableCard({ children, toolbar, busy = false }: { children: ReactNode; toolbar?: ReactNode; busy?: boolean }) {
  return (
    <section className="rounded-2xl border border-line bg-surface">
      {toolbar && <div className="flex flex-wrap items-end gap-3 border-b border-line p-4">{toolbar}</div>}
      <BusyOverlay busy={busy}>
        <div className="overflow-x-auto">{children}</div>
      </BusyOverlay>
    </section>
  )
}

// Shared cell styles, used as className={th} / className={td}
export const table = 'w-full min-w-[40rem] text-left text-sm'
export const th = 'px-4 py-3 text-xs font-semibold tracking-wide text-muted uppercase whitespace-nowrap'
export const td = 'px-4 py-3 align-middle'
export const tr = 'border-t border-line'
export const trLink = 'border-t border-line cursor-pointer hover:bg-surface-2'
