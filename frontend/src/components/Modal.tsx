import { X } from 'lucide-react'
import { useEffect, useRef, type ReactNode } from 'react'

/**
 * A dialog built on the browser's own <dialog> element. showModal() gives us
 * for free: a backdrop, focus kept inside the dialog, and Esc to close.
 *
 * The parent decides whether it's shown (`open`) and gets told when the user
 * wants to close it (`onClose`), the usual "controlled component" pattern.
 */
export function Modal({
  open,
  onClose,
  title,
  children,
  footer,
}: {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
  footer?: ReactNode
}) {
  // useRef gives direct access to the DOM element, to call showModal()/close()
  const ref = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    if (open && !dialog.open) {
      dialog.showModal()
      // showModal() focuses the first button (the ×). Move focus to the first
      // form field instead, so you can start typing right away.
      dialog.querySelector<HTMLElement>('input:not([type=radio]), select, textarea')?.focus()
    }
    if (!open && dialog.open) dialog.close()
  }, [open])

  return (
    <dialog
      ref={ref}
      onClose={onClose} // fires on Esc
      onClick={(e) => e.target === ref.current && onClose()} // click on the backdrop
      className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-2xl border border-line bg-surface p-0 text-ink backdrop:bg-black/70"
    >
      {open && (
        <div className="flex max-h-[85dvh] flex-col">
          <header className="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <h2 className="font-display text-lg font-semibold">{title}</h2>
            <button type="button" onClick={onClose} className="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2" aria-label="Close">
              <X size={18} aria-hidden="true" />
            </button>
          </header>
          <div className="overflow-y-auto px-5 py-4">{children}</div>
          {footer && <footer className="flex justify-end gap-2 border-t border-line px-5 py-4">{footer}</footer>}
        </div>
      )}
    </dialog>
  )
}
