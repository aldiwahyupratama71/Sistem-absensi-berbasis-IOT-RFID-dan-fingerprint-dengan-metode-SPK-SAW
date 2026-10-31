import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import DashboardLayout from '@/components/layout/DashboardLayout'
import ProtectedRoute from '@/components/shared/ProtectedRoute'
import { AuthProvider } from '@/hooks/useAuth'
import { ToastProvider } from '@/components/shared/Toast'

// Pages
import AbsensiPage from '@/pages/Absensi'
import DashboardPage from '@/pages/Dashboard'
import EvaluasiPage from '@/pages/Evaluasi'
import KaryawanPage from '@/pages/Karyawan'
import KontrakPage from '@/pages/Kontrak'
import LaporanPage from '@/pages/Laporan'
import LoginPage from '@/pages/Login'
import PerangkatPage from '@/pages/Perangkat'

export default function App() {
  return (
    <ToastProvider>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            {/* Public Routes */}
            <Route path="/login" element={<LoginPage />} />

            {/* Protected Routes */}
            <Route element={<ProtectedRoute />}>
              <Route element={<DashboardLayout />}>
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/karyawan" element={<KaryawanPage />} />
                <Route path="/perangkat" element={<PerangkatPage />} />
                <Route path="/absensi" element={<AbsensiPage />} />
                <Route path="/kontrak" element={<KontrakPage />} />
                <Route path="/evaluasi" element={<EvaluasiPage />} />
                <Route path="/laporan" element={<LaporanPage />} />
              </Route>
            </Route>

            {/* Redirect root to dashboard */}
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            
            {/* Fallback */}
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </ToastProvider>
  )
}

