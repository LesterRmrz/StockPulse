import { useState, type FormEvent } from 'react'
import { Check } from 'lucide-react'
import { useAuth } from '../auth/auth-context'
import { Spinner } from '../components/Busy'
import { Logo } from '../components/Logo'
import { errorMessage } from '../lib/api'

const FEATURES = [
  'Live stock levels across 3 warehouses',
  'Full audit trail for every stock movement',
  'Low-stock alerts and one-click purchase orders',
]

// Decorative pulse bars at the bottom of the brand panel (last one is "today").
const BARS = [30, 45, 38, 60, 52, 74, 66, 100]

export function LoginPage() {
  const { login, demoLogin } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [error, setError] = useState<string | null>(null)
  // Which button started the sign-in, so only that one shows "Signing in…"
  const [pending, setPending] = useState<'password' | 'manager' | 'staff' | null>(null)
  const busy = pending !== null

  // After a successful login, <GuestOnly> notices the user is authenticated
  // and redirects into the app, so these handlers don't navigate themselves.
  async function run(source: NonNullable<typeof pending>, action: () => Promise<void>) {
    setPending(source)
    setError(null)
    try {
      await action()
    } catch (e) {
      setError(errorMessage(e))
      setPending(null)
    }
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    void run('password', () => login(email, password, remember))
  }

  return (
    <div className="grid min-h-dvh lg:grid-cols-2">
      {/* Brand panel */}
      <section className="flex flex-col justify-between gap-12 border-b border-line p-8 sm:p-12 lg:border-r lg:border-b-0">
        <Logo size={36} />
        <div className="flex max-w-lg flex-col gap-5">
          <h1 className="font-display text-4xl leading-[1.05] font-semibold tracking-tight sm:text-5xl">
            Real-time inventory across every <span className="text-accent">warehouse.</span>
          </h1>
          <p className="text-base leading-relaxed text-muted">
            Track stock, purchase orders and sales as they happen, with live analytics on one dashboard.
          </p>
          <ul className="mt-2 flex flex-col gap-3 text-sm">
            {FEATURES.map((feature) => (
              <li key={feature} className="flex items-center gap-2.5">
                <span className="grid size-5 place-items-center rounded-md border border-line-strong bg-surface-2">
                  <Check size={12} strokeWidth={3} className="text-accent" aria-hidden="true" />
                </span>
                {feature}
              </li>
            ))}
          </ul>
        </div>
        <div className="hidden h-14 items-end gap-1.5 lg:flex" aria-hidden="true">
          {BARS.map((h, i) => (
            <div
              key={i}
              className={`w-2.5 rounded-sm ${i === BARS.length - 1 ? 'bg-accent' : 'bg-line'}`}
              style={{ height: `${h}%` }}
            />
          ))}
        </div>
      </section>

      {/* Sign-in panel */}
      <section className="flex items-center justify-center bg-[#111113] px-6 py-12">
        <div className="flex w-full max-w-sm flex-col gap-6">
          <div>
            <h2 className="font-display text-3xl font-semibold tracking-tight">Sign in</h2>
            <p className="mt-1 text-sm text-muted">Use your StockPulse account.</p>
          </div>

          {error && (
            <p role="alert" className="rounded-lg border border-danger/40 bg-danger/10 px-3.5 py-2.5 text-sm text-danger">
              {error}
            </p>
          )}

          <form onSubmit={onSubmit} className="flex flex-col gap-4">
            <label className="flex flex-col gap-1.5 text-sm font-medium">
              Email
              <input
                type="email"
                required
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@company.com"
                className="h-11 rounded-lg border border-line-strong bg-canvas px-3.5 font-normal placeholder:text-zinc-500"
              />
            </label>
            <label className="flex flex-col gap-1.5 text-sm font-medium">
              Password
              <input
                type="password"
                required
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="h-11 rounded-lg border border-line-strong bg-canvas px-3.5 font-normal"
              />
            </label>
            <label className="flex min-h-11 items-center gap-2.5 text-sm text-muted">
              <input
                type="checkbox"
                checked={remember}
                onChange={(e) => setRemember(e.target.checked)}
                className="size-4.5 accent-accent"
              />
              Keep me signed in
            </label>
            <button
              type="submit"
              disabled={busy}
              className="flex h-12 items-center justify-center gap-2 rounded-lg bg-accent font-semibold text-accent-ink transition-opacity hover:opacity-90 disabled:opacity-60"
            >
              {/* The ring is dark here: the default yellow ring wouldn't show on a yellow button */}
              {pending === 'password' && <Spinner className="size-4 border-accent-ink/30 border-t-accent-ink" />}
              {pending === 'password' ? 'Signing in…' : 'Sign in'}
            </button>
          </form>

          <div className="flex items-center gap-3 text-xs tracking-wider text-muted uppercase">
            <span className="h-px flex-1 bg-line" />
            or explore the demo
            <span className="h-px flex-1 bg-line" />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <DemoButton title="Manager" subtitle="Full access" disabled={busy} loading={pending === 'manager'} onClick={() => void run('manager', () => demoLogin('manager'))} />
            <DemoButton title="Staff" subtitle="Stock and orders" disabled={busy} loading={pending === 'staff'} onClick={() => void run('staff', () => demoLogin('staff'))} />
          </div>
          <p className="text-center text-xs text-muted">Demo data resets every night.</p>
        </div>
      </section>
    </div>
  )
}

function DemoButton(props: { title: string; subtitle: string; disabled: boolean; loading: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={props.onClick}
      disabled={props.disabled}
      // The clicked button keeps full opacity and a yellow border; the other one fades
      className={`flex min-h-16 flex-col items-start justify-center gap-0.5 rounded-lg border bg-canvas px-3.5 text-left hover:border-accent ${
        props.loading ? 'border-accent' : 'border-line-strong disabled:opacity-60'
      }`}
    >
      <span className="text-sm font-semibold">{props.title}</span>
      <span className="flex items-center gap-1.5 text-xs text-muted">
        {props.loading && <Spinner className="size-3" />}
        {props.loading ? 'Signing in…' : props.subtitle}
      </span>
    </button>
  )
}
