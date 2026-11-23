import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return (
    <div className="grid min-h-[60dvh] place-items-center text-center">
      <div className="flex flex-col items-center gap-3">
        <p className="font-display text-6xl font-semibold text-accent">404</p>
        <h1 className="text-xl font-semibold">Page not found</h1>
        <Link to="/" className="text-sm font-semibold text-accent hover:underline">
          Back to the dashboard
        </Link>
      </div>
    </div>
  )
}
