import { AlertTriangle, Inbox } from 'lucide-react'
import type { ReactNode } from 'react'
import { errorMessage } from '../lib/api'
import { Spinner } from './Busy'
import { Button } from './Button'

/** The three states every data screen has besides "here's the data". */

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <div role="status" className="flex items-center gap-3 px-4 py-10 text-sm text-muted">
      <Spinner />
      {label}
    </div>
  )
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  return (
    <div role="alert" className="flex flex-col items-start gap-3 px-4 py-10">
      <div className="flex items-center gap-2 text-sm text-danger">
        <AlertTriangle size={16} aria-hidden="true" />
        {errorMessage(error)}
      </div>
      {onRetry && (
        <Button size="sm" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  )
}

export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-12 text-center">
      <Inbox className="text-muted" size={28} aria-hidden="true" />
      <p className="font-semibold">{title}</p>
      {children && <div className="max-w-sm text-sm text-muted">{children}</div>}
    </div>
  )
}
