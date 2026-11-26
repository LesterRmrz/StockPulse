import type { ButtonHTMLAttributes } from 'react'
import { buttonClass, type ButtonVariant } from './buttonClass'

/**
 * `...props` passes every normal <button> attribute through (onClick,
 * disabled, aria-label...). ButtonHTMLAttributes<HTMLButtonElement> is the
 * TypeScript type that lists them all; `&` adds our own two props to it.
 */
export function Button({
  variant = 'secondary',
  size = 'md',
  className = '',
  type = 'button',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant; size?: 'md' | 'sm' }) {
  return <button type={type} className={`${buttonClass(variant, size)} ${className}`} {...props} />
}
