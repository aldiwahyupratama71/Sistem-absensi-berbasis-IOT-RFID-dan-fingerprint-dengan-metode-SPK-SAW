<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('attendance:mark-absent')
    ->dailyAt(config('attendance.jam_generate_absent', '17:00'))
    ->timezone('Asia/Jakarta');
