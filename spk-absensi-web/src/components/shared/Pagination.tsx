import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/lib/utils'

interface PaginationMeta {
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  total: number
}

interface PaginationProps {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  /** Tampilkan selector jumlah data per halaman (opsional) */
  perPage?: number
  onPerPageChange?: (perPage: number) => void
  perPageOptions?: number[]
  className?: string
}

/**
 * Komponen pagination reusable.
 * Menampilkan: info entri · selector per-page (opsional) · tombol prev/halaman/next.
 */
export function Pagination({
  meta,
  onPageChange,
  perPage,
  onPerPageChange,
  perPageOptions = [10, 15, 25, 50],
  className,
}: PaginationProps) {
  const { current_page: page, last_page, from, to, total } = meta

  /** Buat daftar halaman dengan ellipsis */
  function buildPages(): (number | 'ellipsis')[] {
    const pages: (number | 'ellipsis')[] = []
    if (last_page <= 7) {
      for (let i = 1; i <= last_page; i++) pages.push(i)
    } else {
      pages.push(1)
      if (page > 3) pages.push('ellipsis')
      const start = Math.max(2, page - 1)
      const end = Math.min(last_page - 1, page + 1)
      for (let i = start; i <= end; i++) pages.push(i)
      if (page < last_page - 2) pages.push('ellipsis')
      pages.push(last_page)
    }
    return pages
  }

  return (
    <div
      className={cn(
        'px-6 py-4 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3',
        className
      )}
    >
      {/* Kiri: Info entri + per-page selector */}
      <div className="flex items-center gap-3 text-sm text-muted-foreground">
        <span>
          {total === 0
            ? 'Tidak ada data'
            : `Menampilkan ${from ?? 0}–${to ?? 0} dari ${total} entri`}
        </span>

        {onPerPageChange && perPage !== undefined && (
          <select
            value={perPage}
            onChange={(e) => {
              onPerPageChange(Number(e.target.value))
            }}
            className="px-2 py-1 text-xs bg-background border border-border rounded-lg"
          >
            {perPageOptions.map((n) => (
              <option key={n} value={n}>
                {n} / hal
              </option>
            ))}
          </select>
        )}
      </div>

      {/* Kanan: tombol halaman */}
      {total > 0 && (
        <div className="flex items-center gap-1">
          {/* Prev */}
          <button
            onClick={() => onPageChange(page - 1)}
            disabled={page <= 1}
            className="flex items-center gap-1 px-2.5 py-1.5 text-sm border border-border rounded-lg hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronLeft size={14} /> Prev
          </button>

          {/* Nomor halaman */}
          {buildPages().map((p, idx) =>
            p === 'ellipsis' ? (
              <span
                key={`e-${idx}`}
                className="px-1.5 py-1.5 text-muted-foreground text-sm"
              >
                …
              </span>
            ) : (
              <button
                key={p}
                onClick={() => onPageChange(p)}
                className={cn(
                  'min-w-[32px] h-8 px-2 text-sm rounded-lg border transition-colors',
                  p === page
                    ? 'bg-primary text-primary-foreground border-primary font-semibold'
                    : 'border-border hover:bg-muted'
                )}
              >
                {p}
              </button>
            )
          )}

          {/* Next */}
          <button
            onClick={() => onPageChange(page + 1)}
            disabled={page >= last_page}
            className="flex items-center gap-1 px-2.5 py-1.5 text-sm border border-border rounded-lg hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            Next <ChevronRight size={14} />
          </button>
        </div>
      )}
    </div>
  )
}
