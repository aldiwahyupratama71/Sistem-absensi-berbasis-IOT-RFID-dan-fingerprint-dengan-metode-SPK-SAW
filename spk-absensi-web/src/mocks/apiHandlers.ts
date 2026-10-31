import { http, HttpResponse } from 'msw'
import {
  employees,
  devices,
  attendances,
  contracts,
  periods,
  criteria,
  evaluations,
  absence_reasons,
  generateSPKForPeriod
} from './data'

const paginate = <T,>(data: T[], page: number, perPage: number = 10) => {
  const total = data.length
  const last_page = Math.ceil(total / perPage)
  const from = (page - 1) * perPage + 1
  const to = Math.min(from + perPage - 1, total)
  
  return {
    current_page: page,
    data: data.slice(from - 1, to),
    first_page_url: `http://localhost/api?page=1`,
    from: from <= total ? from : null,
    last_page: last_page || 1,
    last_page_url: `http://localhost/api?page=${last_page || 1}`,
    links: [],
    next_page_url: page < last_page ? `http://localhost/api?page=${page + 1}` : null,
    path: `http://localhost/api`,
    per_page: perPage,
    prev_page_url: page > 1 ? `http://localhost/api?page=${page - 1}` : null,
    to: to > 0 ? to : null,
    total
  }
}

export const apiHandlers = [
  // ─── Auth ───────────────────────────────────────────────────────────────────
  http.post('*/api/auth/login', () => HttpResponse.json({ token: 'mock-token', user: { id: 1, name: 'Admin', email: 'admin@spk.test' } })),
  http.post('*/api/auth/logout', () => HttpResponse.json({ message: 'Logout successful' })),
  http.get('*/api/auth/me', () => HttpResponse.json({ id: 1, name: 'Admin', email: 'admin@spk.test' })),

  // ─── Employees ────────────────────────────────────────────────────────────
  http.get('*/api/employees', ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page')) || 1
    const search = url.searchParams.get('search')?.toLowerCase()
    
    let filtered = employees
    if (search) {
      filtered = filtered.filter(e => e.nama.toLowerCase().includes(search) || e.nip.toLowerCase().includes(search))
    }
    
    return HttpResponse.json(paginate(filtered, page))
  }),
  
  http.get('*/api/employees/:id', ({ params }) => {
    const emp = employees.find(e => e.id === Number(params.id))
    if (!emp) return HttpResponse.json({ message: 'Not found' }, { status: 404 })
    return HttpResponse.json(emp)
  }),
  http.post('*/api/employees', async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: 999 })
  }),
  http.put('*/api/employees/:id', async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: Number(params.id) })
  }),
  http.delete('*/api/employees/:id', () => {
    return HttpResponse.json({ message: 'Deleted' })
  }),
  http.get('*/api/employees/:id/cards', () => {
    return HttpResponse.json([{ id: 1, rfid_uid: 'A1:B2:C3:D4', is_active: true }])
  }),
  http.post('*/api/employees/:id/cards', async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 2, rfid_uid: body.rfid_uid, is_active: true })
  }),
  http.patch('*/api/employees/:empId/cards/:cardId/deactivate', () => {
    return HttpResponse.json({ message: 'Card disabled' })
  }),
  http.get('*/api/employees/:id/fingerprints', () => {
    return HttpResponse.json({
      data: [{ id: 1, finger_id: 101, iot_device_id: 2, device_nama: 'Fingerprint Scanner - Lobby', created_at: new Date().toISOString() }]
    })
  }),
  http.delete('*/api/employees/:empId/fingerprints/:fpId', () => {
    return HttpResponse.json({ message: 'Fingerprint deleted' })
  }),

  // ─── Devices ──────────────────────────────────────────────────────────────
  http.get('*/api/devices', ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page')) || 1
    return HttpResponse.json(paginate(devices, page))
  }),
  http.post('*/api/devices', async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: 999, plain_key: 'sk_test_mockedkey123' })
  }),
  http.put('*/api/devices/:id', async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: Number(params.id) })
  }),
  http.delete('*/api/devices/:id', () => {
    return HttpResponse.json({ message: 'Deleted' })
  }),
  http.post('*/api/devices/:id/regenerate-key', () => {
    return HttpResponse.json({ plain_key: 'sk_test_regenerated456' })
  }),

  // ─── Attendance ───────────────────────────────────────────────────────────
  http.get('*/api/attendance', ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page')) || 1
    const startDate = url.searchParams.get('date_from')
    const endDate = url.searchParams.get('date_to')
    const empId = url.searchParams.get('employee_id')
    const status = url.searchParams.get('status')
    
    let enriched = attendances.map(a => ({
      ...a,
      employee: employees.find(e => e.id === a.employee_id),
      absence_reason: absence_reasons.find(r => r.id === a.absence_reason_id)
    }))
    
    if (startDate) enriched = enriched.filter(a => a.tanggal >= startDate)
    if (endDate) enriched = enriched.filter(a => a.tanggal <= endDate)
    if (empId) enriched = enriched.filter(a => a.employee_id === Number(empId))
    if (status) enriched = enriched.filter(a => a.status === status)

    enriched.sort((a, b) => new Date(b.tanggal).getTime() - new Date(a.tanggal).getTime())
    
    return HttpResponse.json(paginate(enriched, page, 15))
  }),

  http.get('*/api/absence-reasons', () => {
    return HttpResponse.json(absence_reasons)
  }),

  http.patch('*/api/attendance/:id/reason', async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const id = Number(params.id)
    
    const attIndex = attendances.findIndex(a => a.id === id)
    if (attIndex !== -1) {
      attendances[attIndex].absence_reason_id = Number(body.absence_reason_id)
    }
    
    return HttpResponse.json({ message: 'Alasan berhasil disimpan' })
  }),

  // ─── Contracts ────────────────────────────────────────────────────────────
  http.get('*/api/contracts', ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page')) || 1
    
    const enriched = contracts.map(c => ({
      ...c,
      employee: employees.find(e => e.id === c.employee_id)
    }))
    
    return HttpResponse.json(paginate(enriched, page))
  }),

  http.post('*/api/contracts', async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: 999 })
  }),
  http.put('*/api/contracts/:id', async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: Number(params.id) })
  }),
  http.delete('*/api/contracts/:id', () => {
    return HttpResponse.json({ message: 'Deleted' })
  }),

  // ─── Evaluation Periods ───────────────────────────────────────────────────
  http.get('*/api/evaluation-periods', ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page')) || 1
    const contractId = url.searchParams.get('contract_id')
    let res = periods
    if (contractId) {
       res = res.filter((p: any) => p.contract_id === Number(contractId))
    }
    return HttpResponse.json(paginate(res, page))
  }),
  http.post('*/api/evaluation-periods', async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...body, id: 999, status_evaluasi: 'belum_dihitung' })
  }),
  http.delete('*/api/evaluation-periods/:id', () => {
    return HttpResponse.json({ message: 'Deleted' })
  }),
  http.get('*/api/evaluation-periods/options', () => {
    return HttpResponse.json(periods)
  }),

  // ─── SPK & Evaluations ────────────────────────────────────────────────────
  http.get('*/api/criteria', () => {
    return HttpResponse.json(criteria)
  }),
  
  http.get('*/api/evaluation-periods/:periodId/results', ({ params, request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page')) || 1
    const periodId = Number(params.periodId)
    
    const evals = (evaluations as any[]).filter(e => e.period_id === periodId).map(e => ({
      ...e,
      employee: employees.find(emp => emp.id === e.employee_id)
    }))
    
    return HttpResponse.json(paginate(evals, page))
  }),
  
  http.post('*/api/evaluation-periods/:periodId/calculate', ({ params }) => {
    const periodId = Number(params.periodId)
    const { newEvals } = generateSPKForPeriod(periodId)
    
    const filtered = (evaluations as any[]).filter(e => e.period_id !== periodId)
    evaluations.length = 0
    evaluations.push(...(filtered as any[]), ...(newEvals as any[]))
    
    return HttpResponse.json({
      message: 'Kalkulasi berhasil',
      summary: {
        jumlah_karyawan: newEvals.length,
        distribusi_kategori: { positif: 1, pertimbangan: 1, perhatian: 1, evaluasi_khusus: 1 },
        warnings: []
      }
    })
  }),

  http.get('*/api/evaluations/:evalId', ({ params }) => {
    const evalId = Number(params.evalId)
    const ev = evaluations.find(e => e.id === evalId)
    if (!ev) return HttpResponse.json({ message: 'Not found' }, { status: 404 })

    return HttpResponse.json({
      id: ev.id,
      employee: employees.find(emp => emp.id === ev.employee_id),
      nilai_akhir: ev.nilai_akhir,
      kategori: ev.kategori,
      rekomendasi: 'Ini adalah rekomendasi mock',
      calculated_at: ev.calculated_at,
      keputusan_hrd: null,
      details: [
        {
          kode: 'C1',
          nama: 'Kehadiran',
          data_mentah: { absent_tanpa_keterangan: 0, absent_sah: 0, daftar_tanggal_pemicu_penalti: [] },
          nilai_mentah: 100,
          rumus_substitusi: "MAX(0, 100 - 0 * 60)",
          nilai_normalisasi: 1.0,
          bobot: 50,
          kontribusi: 50,
          daftar_pemicu_penalti: []
        }
      ]
    })
  }),

  // ─── Dashboard Stats & Data ───────────────────────────────────────────────
  http.get('*/api/dashboard', ({ request }) => {
    const total_karyawan_aktif = employees.length
    const tepat_waktu = 10
    const terlambat = 3
    const tidak_hadir = 2
    
    return HttpResponse.json({
      kartu: {
        total_karyawan_aktif,
        tanggal_referensi: new Date().toISOString().split('T')[0],
        tepat_waktu,
        terlambat,
        tidak_hadir,
        absen_fingerprint: 4
      },
      rekap_kehadiran: {
        tepat_waktu: 200,
        terlambat: 20,
        tidak_hadir: 5,
        total: 225
      },
      rata_rata_kriteria: {
        C1: 85, C2: 90, C3: 95
      },
      distribusi_kategori: {
        positif: { jumlah: 5, persen: 33 },
        pertimbangan: { jumlah: 4, persen: 27 },
        perhatian: { jumlah: 3, persen: 20 },
        evaluasi_khusus: { jumlah: 3, persen: 20 }
      },
      peringatan: {
        absen_tanpa_alasan: tidak_hadir,
        perangkat_tidak_aktif: 1
      },
      periode: periods[0]
    })
  }),

  http.get('*/api/dashboard/attendance-trend', () => {
    return HttpResponse.json([
      { tanggal: '2026-08-01', hadir: 10, terlambat: 2, tidak_hadir: 1 }
    ])
  }),

  http.get('*/api/dashboard/recent-attendance', () => {
    return HttpResponse.json(attendances.slice(0, 10))
  }),

  // ─── Reports ──────────────────────────────────────────────────────────────
  http.get('*/api/evaluation-periods/:periodId/export', ({ request }) => {
    const url = new URL(request.url)
    const format = url.searchParams.get('format') || 'pdf'
    
    const fileContent = `Mock ${format.toUpperCase()} Content for Evaluation Report`
    const blob = new Blob([fileContent], { 
      type: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
    })
    
    return new HttpResponse(blob, {
      headers: {
        'Content-Type': blob.type,
        'Content-Disposition': `attachment; filename="laporan-evaluasi-mock.${format}"`
      }
    })
  }),
]
