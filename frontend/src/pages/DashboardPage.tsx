import { useAuth } from '../auth/auth-context'
import { PageHeader } from '../components/PageHeader'

export function DashboardPage() {
  const { user } = useAuth()

  return (
    <>
      <PageHeader
        title="Dashboard"
        subtitle={
          <span className="inline-flex items-center gap-2">
            <span className="size-2 rounded-full bg-accent" aria-hidden="true" />
            Welcome back, {user?.name}
          </span>
        }
      />
      <section className="rounded-2xl border border-dashed border-line-strong bg-surface p-8">
        <h2 className="text-lg font-semibold">Analytics arrive in M5</h2>
        <p className="mt-2 max-w-prose text-sm text-muted">
          KPI cards, the revenue chart, the live activity feed and the low-stock table come in milestone M5. The 90 days
          of seeded orders and stock movements they will read from are already in the database.
        </p>
      </section>
    </>
  )
}
