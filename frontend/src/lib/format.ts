// Display helpers. Intl formatters are built once and reused (creating them is slow).

const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' })
const number = new Intl.NumberFormat('en-PH')
const dateTime = new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' })

/** "1234.5" -> "₱1,234.50". Accepts the API's money strings. */
export function formatMoney(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '—' : money.format(Number(value))
}

export function formatNumber(value: number): string {
  return number.format(value)
}

/** +5 / -3 / 0, for ledger quantity changes */
export function formatChange(value: number): string {
  return value > 0 ? `+${number.format(value)}` : number.format(value)
}

export function formatDateTime(iso: string): string {
  return dateTime.format(new Date(iso))
}

/** Gross margin as a percentage of the sell price, e.g. "48%" */
export function formatMargin(cost: string, sell: string): string {
  const price = Number(sell)
  if (price <= 0) return '—'
  return `${Math.round(((price - Number(cost)) / price) * 100)}%`
}
