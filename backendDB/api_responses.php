<?php
$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$req3 = \Illuminate\Http\Request::create('/api/contracts/groups', 'GET');
$res3 = app()->make(\App\Http\Controllers\ContractController::class)->groups($req3);
echo "GET /api/contracts/groups\n";
echo json_encode($res3->getData(true), JSON_PRETTY_PRINT) . "\n\n";
