<?php

use App\Http\Controllers\BoosterController;
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

Route::prefix('api/test/booster')->name('booster.')->group(function () {
    // Step 1: Selezione comune
    Route::get('/', [BoosterController::class, 'index'])->name('index');
    
    // Step 2: Visualizza ZTO disponibili per il comune selezionato
    Route::post('/zto', [BoosterController::class, 'showZto'])->name('zto');
    
    // Step 3: Elabora le ZTO selezionate
    Route::post('/elabora', [BoosterController::class, 'elaboraWeb'])->name('elaboraWeb');
    
    // Step 4: Lista elaborazioni esistenti
    Route::get('/elaborazioni/{code_comune}', [BoosterController::class, 'listaElaborazioni'])->name('elaborazioni');
    
    // Step 5: Dettaglio elaborazione con paginazione
    Route::get('/dettaglio/{code_comune}/{table}', [BoosterController::class, 'dettaglioElaborazione'])->name('dettaglio');
    
    // Download CSV
    Route::get('/download/{code_comune}/{table}', [BoosterController::class, 'downloadElaborazione'])->name('download');
    
    // Elimina elaborazione
    Route::delete('/elimina/{code_comune}/{table}', [BoosterController::class, 'eliminaElaborazione'])->name('elimina');
    
    // Verifica errori catasto/urbanistica
    Route::get('/errori-catasto/{code_comune}', [BoosterController::class, 'erroriCatasto'])->name('errori.catasto');
    Route::get('/errori-urbanistica/{code_comune}', [BoosterController::class, 'erroriUrbanistica'])->name('errori.urbanistica');
});