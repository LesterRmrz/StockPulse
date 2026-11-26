import type { ReactNode } from 'react'

/** A small spinning ring. Size it with a class, e.g. <Spinner className="size-3" />. */
export function Spinner({ className = 'size-4' }: { className?: string }) {
  return <span className={`inline-block shrink-0 animate-spin rounded-full border-2 border-line-strong border-t-accent ${className}`} aria-hidden="true" />
}

/**
 * Wraps content that is on screen but being refreshed: after changing a
 * filter, or after a save while the new numbers load in the background.
 * Shows a moving bar along the top, an "Updating…" label and dims the content,
 * so it's clear the old values are about to change.
 */
export function BusyOverlay({ busy, children }: { busy: boolean; children: ReactNode }) {
  return (
    // aria-busy tells screen readers this part is being updated
    <div className="relative" aria-busy={busy}>
      {busy && (
        <>
          <div className="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden" aria-hidden="true">
            <div className="h-full w-1/3 bg-accent motion-safe:animate-progress" />
          </div>
          {/* role="status" makes screen readers announce it */}
          <div role="status" className="absolute top-3 left-1/2 z-10 flex -translate-x-1/2 items-center gap-2 rounded-full border border-line-strong bg-surface-2 px-3 py-1.5 text-xs font-medium shadow-lg">
            <Spinner className="size-3" />
            Updating…
          </div>
        </>
      )}
      <div className={`transition-opacity ${busy ? 'opacity-50' : ''}`}>{children}</div>
    </div>
  )
}
