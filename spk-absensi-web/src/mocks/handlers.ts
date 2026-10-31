import { http, HttpResponse } from 'msw'
import type { AuthResponse } from '@/types/auth'
import { apiHandlers } from './apiHandlers'

const DUMMY_USER = {
  id: 1,
  name: 'Admin SPK',
  email: 'admin@spk.test',
  role: 'admin' as const,
}
const DUMMY_TOKEN = 'mock-token-spk-absensi-2024'

export const handlers = [
  ...apiHandlers,
  // POST /api/login
  http.post('*/api/login', async ({ request }) => {
    const body = (await request.json()) as { email?: string; password?: string }

    if (body.email === 'admin@spk.test' && body.password === 'password') {
      const response: AuthResponse = {
        user: DUMMY_USER,
        token: DUMMY_TOKEN,
        token_type: 'Bearer',
      }
      return HttpResponse.json(response, { status: 200 })
    }

    // Wrong credentials → 422 with field errors
    return HttpResponse.json(
      {
        message: 'These credentials do not match our records.',
        errors: {
          email: ['These credentials do not match our records.'],
        },
      },
      { status: 422 },
    )
  }),

  // POST /api/logout
  http.post('*/api/logout', () => {
    return HttpResponse.json({ message: 'Logged out successfully.' }, { status: 200 })
  }),

  // GET /api/user
  http.get('*/api/user', ({ request }) => {
    const auth = request.headers.get('Authorization')
    if (auth === `Bearer ${DUMMY_TOKEN}`) {
      return HttpResponse.json(DUMMY_USER, { status: 200 })
    }
    return HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })
  }),
]
