import { useState } from 'react'
import { errorMessage } from '../lib/api'
import { Button } from './Button'
import { FormError } from './Field'
import { Modal } from './Modal'

/**
 * "Are you sure?" for destructive actions. `onConfirm` returns a Promise; if it
 * fails (e.g. the API says "this category still has products"), the message is
 * shown here instead of closing.
 */
export function ConfirmDialog({
  open,
  onClose,
  title,
  message,
  confirmLabel = 'Delete',
  onConfirm,
}: {
  open: boolean
  onClose: () => void
  title: string
  message: string
  confirmLabel?: string
  onConfirm: () => Promise<unknown>
}) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function confirm() {
    setBusy(true)
    setError(null)
    try {
      await onConfirm()
      onClose()
    } catch (e) {
      setError(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  function close() {
    setError(null)
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={close}
      title={title}
      footer={
        <>
          <Button onClick={close}>Cancel</Button>
          <Button variant="danger" onClick={() => void confirm()} disabled={busy}>
            {busy ? 'Working…' : confirmLabel}
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-3">
        <p className="text-sm text-muted">{message}</p>
        <FormError message={error} />
      </div>
    </Modal>
  )
}
