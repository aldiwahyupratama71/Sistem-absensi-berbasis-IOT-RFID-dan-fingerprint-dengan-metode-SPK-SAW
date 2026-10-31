import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'
import { format, parseISO } from 'date-fns'
import { id } from 'date-fns/locale'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(dateString: string | null | undefined, emptyText: string = 'Belum pernah'): string {
  if (!dateString) return emptyText

  try {
    let date: Date
    // Jika hanya date-only (YYYY-MM-DD tanpa waktu/timezone), gunakan
    // konstruktor Date langsung agar tidak terjadi shift UTC → WIB.
    // parseISO dari date-fns menginterpretasi date-only sebagai UTC midnight
    // yang kemudian bergeser jadi hari sebelumnya di timezone +7.
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
      const [year, month, day] = dateString.split('-').map(Number)
      date = new Date(year, month - 1, day) // bulan di JS adalah 0-indexed
    } else {
      date = parseISO(dateString)
    }
    return format(date, 'dd MMM yyyy', { locale: id })
  } catch (error) {
    return 'Invalid Date'
  }
}

export function formatDateTime(dateString: string | null | undefined, emptyText: string = 'Belum pernah'): string {
  if (!dateString) return emptyText

  try {
    const date = parseISO(dateString)
    return format(date, 'dd MMM yyyy HH:mm', { locale: id })
  } catch (error) {
    return 'Invalid Date'
  }
}

export function formatTime(timeString: string | null | undefined, emptyText: string = '-'): string {
  if (!timeString) return emptyText
  
  // Handle case where timeString might just be "HH:mm:ss" without date
  if (timeString.length <= 8) {
    // Return just the HH:mm part
    return timeString.substring(0, 5)
  }

  try {
    const date = parseISO(timeString)
    return format(date, 'HH:mm', { locale: id })
  } catch (error) {
    return 'Invalid Time'
  }
}
