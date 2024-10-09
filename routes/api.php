<?php

use App\Http\Controllers\CatastoImmobileController;
use App\Http\Controllers\CDUController;
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

//CDU
Route::get("/selectPoligonoUiuCat/{tabella}/{fg}/{nm}/{tipo}", [CDUController::class, "selectPoligonoUiuCat"]);
Route::get("/selectPoligonoUiuUrb/{tabella}/{nome}", [CDUController::class, "selectPoligonoUiuUrb"]);
Route::get("/elPiani", [CDUController::class, "elPiani"]);
Route::get("/elencoNormePiani/{tabella}", [CDUController::class, "elencoNormePiani"]);
Route::get("/intersezioniPianiUrbanistici/{table}/{oid}", [CDUController::class, "intersezioniPianiUrbanistici"]);
Route::get("/calcolaCdu/{foglio}/{numero}/{piano}", [CDUController::class, "calcolaCdu"]);
Route::get("/generaCDU", [CDUController::class, "generaCDU"]);
Route::get("/generaCDUHtml", [CDUController::class, "generaCDUHtml"]);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
