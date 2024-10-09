<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class NtaController extends Controller
{
    public function nta(Request $request) {
        $comune = strtoupper($request->code_comune);
        $dir = $request->input('dir');
        $file = $request->input('val');
    
        $tempHtmlPath = storage_path('app/'.$comune.'/Urbanistica/' . $dir . '/' . $file . '.html');
    
        if (!file_exists($tempHtmlPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }
    
        // Load the HTML content
        $htmlContent = file_get_contents($tempHtmlPath);
    
        // Create a new DOMDocument object
        $dom = new \DOMDocument();
        // Load the HTML into the DOMDocument object
        // Suppress errors due to malformed HTML
        @$dom->loadHTML($htmlContent);
    
        // Get the body element
        $body = $dom->getElementsByTagName('body')->item(0);
    
        // Get the inner HTML of the body element
        $bodyContent = '';
        foreach ($body->childNodes as $child) {
            $bodyContent .= $dom->saveHTML($child);
        }
    
        // Return the body content as JSON
        return response()->json(['content' => $bodyContent]);
    }

    public function print_nta_from_modal(Request $request) {
       $comune = strtoupper($request->code_comune);

        $dir = $request->input('dir');
        $file = $request->input('val');
    
        $tempHtmlPath = storage_path('app/'.$comune.'/Urbanistica/' . $dir . '/' . $file . '.html');
    
        if (!file_exists($tempHtmlPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }
    
        // Load the HTML content
        $htmlContent = file_get_contents($tempHtmlPath);

    
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', TRUE);
        $options->set('debugKeepTemp', TRUE);
        $options->set('isHtml5ParserEnabled', TRUE);
        $options->set('chroot', '/');
        $options->setIsRemoteEnabled(true);
        $dompdf = new Dompdf($options);


        $dompdf->loadHtml($htmlContent);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream('NTA', ["compress" => 1, "Attachment" => false]);
        exit;
    }
}
