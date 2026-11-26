import { useSearchParams } from 'react-router-dom'

/**
 * List filters stored in the URL query string (?search=usb&page=2) instead of
 * useState. That way the Back button, a page refresh or a shared link all
 * restore exactly the same view.
 *
 * `T extends Record<string, string>` means "an object whose values are all
 * strings": everything in a URL is a string, so numbers are converted by the caller.
 */
export function useUrlFilters<T extends Record<string, string>>(defaults: T) {
  const [params, setParams] = useSearchParams()

  const filters = Object.fromEntries(
    Object.entries(defaults).map(([key, fallback]) => [key, params.get(key) ?? fallback]),
  ) as T

  /** Change some filters. Any change other than the page goes back to page 1. */
  function setFilters(changes: Partial<T>) {
    setParams(
      (previous) => {
        const next = new URLSearchParams(previous)
        for (const [key, value] of Object.entries(changes)) {
          // Leave defaults out of the URL so it stays short
          if (value === undefined || value === defaults[key]) next.delete(key)
          else next.set(key, value)
        }
        if (!('page' in changes)) next.delete('page')
        return next
      },
      // replace: typing in a search box shouldn't add one history entry per letter
      { replace: true },
    )
  }

  return [filters, setFilters] as const
}
