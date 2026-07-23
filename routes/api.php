<?php

use App\Http\Controllers\BoosterController;
use App\Http\Controllers\CatastoImmobileController;
use App\Http\Controllers\CDUController;
use App\Http\Controllers\ComunitaMontanaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::get("/selectFgPllaSubCatasto", [CatastoImmobileController::class, "selectFgPllaSubCatasto"]);
Route::get("/elencoMutazioniCatastoTerreni", [CatastoImmobileController::class, "elencoMutazioniCatastoTerreni"]);
Route::get("/elencoMutazioniCatastoFabbricati", [CatastoImmobileController::class, "elencoMutazioniCatastoFabbricati"]);
Route::get("/selectPersoneGiuridicheCatasto", [CatastoImmobileController::class, "selectPersoneGiuridicheCatasto"]);
Route::get("/selectPersoneFisicheCatasto", [CatastoImmobileController::class, "selectPersoneFisicheCatasto"]);
Route::get("/selectUiuSogg", [CatastoImmobileController::class, "selectUiuSogg"]);

//CM
Route::get("/cantieri", [ComunitaMontanaController::class, "index"]);

//CDU
Route::get("/selectPoligonoUiuCat/{tabella}/{fg}/{nm}/{tipo}", [CDUController::class, "selectPoligonoUiuCat"]);
Route::get("/selectPoligonoUiuUrb/{tabella}/{nome}", [CDUController::class, "selectPoligonoUiuUrb"]);
Route::get("/elPiani", [CDUController::class, "elPiani"]);
Route::get("/elencoNormePiani/{tabella}", [CDUController::class, "elencoNormePiani"]);
Route::get("/intersezioniPianiUrbanistici/{table}/{oid}", [CDUController::class, "intersezioniPianiUrbanistici"]);
Route::get("/calcolaCdu/{foglio}/{numero}/{piano}", [CDUController::class, "calcolaCdu"]);
Route::get("/generaCDU", [CDUController::class, "generaCDU"]);
Route::get("/generaCDUHtml", [CDUController::class, "generaCDUHtml"]);

//Booster
Route::prefix('booster')->group(function () {
    Route::get("/getProprietariAttuali", [BoosterController::class, "getProprietariAttuali"]);
    Route::get("/elaborazioni", [BoosterController::class, "elaborazioni"]);
    Route::get("/downloadElaborazione", [BoosterController::class, "downloadElaborazione"]);
    Route::delete("/elaborazione", [BoosterController::class, "eliminaElaborazione"]);
    Route::get("/dettaglioElaborazione", [BoosterController::class, "dettaglioElaborazioneJson"]);

    // Prepara tabella (aggiunge id/lavorato se mancanti - retroattivo)
    Route::post("/preparaTabella", [BoosterController::class, "preparaTabella"]);
    // Imposta lavorato 0/1 su una o piu' righe
    Route::post("/lavorato", [BoosterController::class, "setLavorato"]);
    // Elimina definitivamente una o piu' righe
    Route::post("/eliminaRighe", [BoosterController::class, "eliminaRighe"]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
