<?php

namespace App\Http\Controllers;

use App\Models\ComunitaMontana;
use Illuminate\Http\Request;

class ComunitaMontanaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Recupera tutti i record dalla tabella 'cantieri' nel DB 'cmtiternoaltotammaro'
            $cantieri = ComunitaMontana::with('squadra')->where('data_iniz', '!=', NULL)
                ->where('data_fine', '!=', NULL)
                ->where('cod_squad', '!=', NULL)
                ->orderby('Comune', 'ASC')
                ->orderby('data_iniz', 'ASC')
                ->get();
    
            // Se non ci sono dati, restituisce un messaggio con codice di stato 404
            if ($cantieri->isEmpty()) {
                return response()->json([
                    'message' => 'Nessun cantiere trovato.'
                ], 404);
            }
    
            // Restituisce i dati con codice di stato 200 (successo)
            return response()->json($cantieri, 200);
        } catch (\Exception $e) {
            // Restituisce un messaggio di errore generico con codice di stato 500 (errore del server)
            return response()->json([
                'message' => 'Errore interno del server. Si prega di riprovare più tardi.'
            ], 500);
        }
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ComunitaMontana $comunitaMontana)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ComunitaMontana $comunitaMontana)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ComunitaMontana $comunitaMontana)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ComunitaMontana $comunitaMontana)
    {
        //
    }
}
