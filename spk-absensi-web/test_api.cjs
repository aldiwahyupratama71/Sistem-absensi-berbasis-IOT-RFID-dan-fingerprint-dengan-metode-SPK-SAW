const fs = require('fs');

async function testApi() {
  const baseUrl = 'http://localhost:8000/api';
  let token = '';

  try {
    const loginRes = await fetch(`${baseUrl}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ email: 'hrd@spk-absensi.local', password: 'password' })
    });
    const loginData = await loginRes.json();
    token = loginData.token;
    if (!token) throw new Error('No token obtained. Response: ' + JSON.stringify(loginData));
  } catch (err) {
    console.error('Failed to login', err.message);
    return;
  }

  const results = [];

  const check = async (page, method, path) => {
    try {
      const res = await fetch(`${baseUrl}${path}`, {
        method,
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      if (!res.ok) {
        const body = await res.text();
        results.push({ page, method, path, status: res.status, error: body.substring(0, 500) });
      } else {
        results.push({ page, method, path, status: res.status, error: 'OK' });
      }
    } catch (err) {
      results.push({ page, method, path, status: 0, error: err.message });
    }
  };

  // Dashboard
  await check('Dashboard', 'GET', '/dashboard');
  await check('Dashboard', 'GET', '/dashboard/attendance-trend?range=7');
  await check('Dashboard', 'GET', '/dashboard/recent-attendance');
  await check('Dashboard', 'GET', '/evaluation-periods?page=1'); // active periods list

  // Absensi
  await check('Absensi', 'GET', '/absence-reasons');
  await check('Absensi', 'GET', '/attendance?page=1');

  // Evaluasi
  await check('Evaluasi', 'GET', '/evaluation-periods?page=1');
  await check('Evaluasi', 'GET', '/evaluation-periods/1/results');

  // Karyawan
  await check('Karyawan', 'GET', '/employees?page=1');
  await check('Karyawan (Detail)', 'GET', '/employees/1/cards'); // not defined in API!
  await check('Karyawan (Detail)', 'GET', '/employees/1/fingerprints'); // exists

  // Kontrak
  await check('Kontrak', 'GET', '/contracts?page=1');
  await check('Kontrak (Detail)', 'GET', '/evaluation-periods?contract_id=1');

  // Perangkat
  await check('Perangkat', 'GET', '/devices?page=1');

  // Laporan
  await check('Laporan', 'GET', '/evaluation-periods?page=1');
  await check('Laporan', 'GET', '/evaluation-periods/1/export?format=pdf');

  console.log(JSON.stringify(results.filter(r => r.status !== 200), null, 2));
}

testApi();
