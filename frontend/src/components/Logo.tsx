/** The yellow pulse mark plus the wordmark. */
export function Logo({ size = 32 }: { size?: number }) {
  return (
    <div className="flex items-center gap-2.5">
      <span
        className="flex items-center justify-center rounded-lg bg-accent"
        style={{ width: size, height: size }}
        aria-hidden="true"
      >
        <svg width={size * 0.62} height={size * 0.62} viewBox="0 0 24 24" fill="none" stroke="#0A0A0B" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 12h4l3-8 4 16 3-8h4" />
        </svg>
      </span>
      <span className="font-display text-xl font-bold tracking-tight">StockPulse</span>
    </div>
  )
}
