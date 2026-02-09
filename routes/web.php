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
    // Pagina principale con sidebar
    Route::get('/', [BoosterController::class, 'index'])->name('index');
    
    // API per caricamento dati comune (AJAX)
    Route::post('/zto', [BoosterController::class, 'showZto'])->name('zto');
    
    // Elabora le ZTO selezionate (AJAX)
    Route::post('/elabora', [BoosterController::class, 'elaboraWeb'])->name('elaboraWeb');
    
    // Lista elaborazioni esistenti per un comune (AJAX)
    Route::get('/elaborazioni/{code_comune}', [BoosterController::class, 'listaElaborazioni'])->name('elaborazioni');
    
    // Dettaglio elaborazione con paginazione (pagina HTML)
    Route::get('/dettaglio/{code_comune}/{table}', [BoosterController::class, 'dettaglioElaborazione'])->name('dettaglio');
    
    // Download CSV
    Route::get('/download/{code_comune}/{table}', [BoosterController::class, 'downloadElaborazione'])->name('download');
    
    // Elimina elaborazione (AJAX)
    Route::delete('/elimina/{code_comune}/{table}', [BoosterController::class, 'eliminaElaborazione'])->name('elimina');
    
    // Verifica errori catasto/urbanistica
    Route::get('/errori-catasto/{code_comune}', [BoosterController::class, 'erroriCatasto'])->name('errori.catasto');
    Route::get('/errori-urbanistica/{code_comune}', [BoosterController::class, 'erroriUrbanistica'])->name('errori.urbanistica');
});