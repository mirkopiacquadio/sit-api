<?php

use App\Http\Controllers\CatastoImmobileController;
use App\Http\Controllers\CDUController;
use App\Http\Controllers\NtaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/api/istanzacdu', function () {
    return view('welcome');
});

Route::get('/api/download-marca-da-bollo', function () {
    $filePath = public_path('assets/marca_da_bollo.pdf');
    
    if (file_exists($filePath)) {
        return Response::download($filePath, 'marca_da_bollo.pdf');
    } else {
        abort(404, 'File not found.');
    }
})->name('download.marca_bollo');

Route::get('/api/print_catasto', [CatastoImmobileController::class, 'print_catasto']);
Route::get('/api/print_cdu_from_modal', [CDUController::class, 'print_cdu_from_modal']);
Route::get('/api/nta', [NtaController::class, 'nta']);
Route::get('/api/print_nta_from_modal', [NtaController::class, 'print_nta_from_modal']);