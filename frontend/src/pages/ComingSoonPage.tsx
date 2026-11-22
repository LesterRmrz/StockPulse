import { Construction } from 'lucide-react'
import { PageHeader } from '../components/PageHeader'
import type { NavItem } from '../navigation'

/** Placeholder for pages that later milestones will build. */
export function ComingSoonPage({ item }: { item: NavItem }) {
  return (
    <>
      <PageHeader title={item.label} />
      <section className="flex flex-col items-start gap-3 rounded-2xl border border-dashed border-line-strong bg-surface p-8">
        <Construction className="text-accent" size={28} aria-hidden="true" />
        <h2 className="text-lg font-semibold">Not built yet</h2>
        <p className="max-w-prose text-sm text-muted">
          This page is part of milestone <span className="font-semibold text-ink">{item.milestone}</span> in docs/PLAN.md.
          The database tables and demo data behind it already exist.
        </p>
      </section>
    </>
  )
}
