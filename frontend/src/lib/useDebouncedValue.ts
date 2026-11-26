import { useEffect, useState } from 'react'

/**
 * Returns `value`, but only after it has stopped changing for `delay` ms.
 * Used for search boxes: typing "cable" sends one request, not five.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    // Cleanup: a new keystroke cancels the previous timer
    return () => clearTimeout(timer)
  }, [value, delay])

  return debounced
}
