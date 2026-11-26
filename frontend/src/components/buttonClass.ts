export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost'

const variants: Record<ButtonVariant, string> = {
  // Yellow is reserved for the one main action on a screen (docs/PLAN.md design rules)
  primary: 'bg-accent text-accent-ink font-semibold hover:opacity-90',
  secondary: 'border border-line-strong text-ink hover:bg-surface-2',
  danger: 'border border-danger/50 text-danger hover:bg-danger/10',
  ghost: 'text-muted hover:bg-surface-2 hover:text-ink',
}

/**
 * The button look as a class string, so a react-router <Link> can look like a button:
 *   <Link to="/x" className={buttonClass('primary')}>
 *
 * (Kept in its own file: Vite's hot reload works best when component files
 * export only components.)
 */
export function buttonClass(variant: ButtonVariant = 'secondary', size: 'md' | 'sm' = 'md'): string {
  const sizing = size === 'sm' ? 'h-9 px-3 text-sm' : 'h-11 px-4 text-sm'
  return `inline-flex items-center justify-center gap-2 rounded-lg font-medium transition disabled:pointer-events-none disabled:opacity-50 ${sizing} ${variants[variant]}`
}
