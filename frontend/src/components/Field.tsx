import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

/** The look shared by every input. Width is separate, see `width()` below. */
export const controlClass =
  'h-11 rounded-lg border border-line-strong bg-canvas px-3.5 text-sm font-normal text-ink placeholder:text-zinc-500 disabled:opacity-60 aria-invalid:border-danger'

/**
 * Full width unless the caller chose a width (w-auto, w-28...). If both
 * w-full and w-auto were in the class list, the order of rules in the CSS
 * file would decide which wins, not the order we wrote them in.
 */
function width(className: string): string {
  return /(^|\s)w-/.test(className) ? '' : 'w-full'
}

/** Label + control + optional hint or error message, stacked. */
export function Field({ label, error, hint, children }: { label: string; error?: string; hint?: ReactNode; children: ReactNode }) {
  return (
    <label className="flex flex-col gap-1.5 text-sm font-medium">
      {label}
      {children}
      {error ? (
        <span className="text-xs font-normal text-danger">{error}</span>
      ) : (
        hint && <span className="text-xs font-normal text-muted">{hint}</span>
      )}
    </label>
  )
}

// Thin wrappers that only add the shared styling. aria-invalid turns the border red.

export function Input({ invalid, className = '', ...props }: InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }) {
  return <input aria-invalid={invalid || undefined} className={`${controlClass} ${width(className)} ${className}`} {...props} />
}

export function Select({ invalid, className = '', ...props }: SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
  return <select aria-invalid={invalid || undefined} className={`${controlClass} ${width(className)} ${className}`} {...props} />
}

export function Textarea({ invalid, className = '', ...props }: TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean }) {
  return <textarea aria-invalid={invalid || undefined} className={`${controlClass} ${width(className)} h-auto min-h-24 py-2.5 ${className}`} {...props} />
}

/** A red box for errors that don't belong to one field. */
export function FormError({ message }: { message?: string | null }) {
  if (!message) return null
  return (
    <p role="alert" className="rounded-lg border border-danger/40 bg-danger/10 px-3.5 py-2.5 text-sm text-danger">
      {message}
    </p>
  )
}
