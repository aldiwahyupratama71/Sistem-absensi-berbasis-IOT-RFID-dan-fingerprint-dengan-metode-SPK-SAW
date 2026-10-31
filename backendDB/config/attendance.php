<?php

return [
    'jam_masuk' => env('ATTENDANCE_JAM_MASUK', '07:00'),
    'hari_kerja' => explode(',', env('ATTENDANCE_HARI_KERJA', 'Monday,Tuesday,Wednesday,Thursday,Friday')),
    'jam_generate_absent' => env('ATTENDANCE_JAM_GENERATE_ABSENT', '17:00'),
    'device_online_threshold_seconds' => env('ATTENDANCE_DEVICE_ONLINE_THRESHOLD_SECONDS', 90),
];
