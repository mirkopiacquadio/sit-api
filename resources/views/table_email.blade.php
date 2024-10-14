<style>
    @page {
        margin: 1cm 1cm;
    }

    /** Define now the real margins of every page in the PDF **/
    body {
        margin-bottom: 0cm;
        width: 100%;
        height: 100%;
        left: 0cm;
        right: 0cm;
    }

    .wrapper-page {
        page-break-after: always;
    }

    .wrapper-page:last-child {
        page-break-after: avoid;
    }

    .tableNotHeader {
        width: 100%
    }

    td {
        font-family: trebuchet ms;
        color: #000;
        padding: 4px;
        border: 1px solid #cdcbcb;
    }

    th {
        font-family: trebuchet ms;
        color: #000;
        padding: 4px;
        border: 1px solid #cdcbcb;
        background-color: #e5e2e2;
        text-align: center;
        font-size: 10px;
    }

    .font_label {
        font-family: 'Calibri', Arial, sans-serif;
    }

    .frase_immobile {
        font-size: 11pt;
    }
</style>
<div class="responsive-table-wrapper">
    <table cellspacing="0" style="width: 100%;">
        <tbody>
            <tr valign="top">
                <td style="width: 10%; border-right: 0px;vertical-align: middle;">
                    <img src="https://sitmonter.it/loghi/{{ $comune[0]->nome_immagine }}" alt="comune" style="max-height: 100px" /><br>
                </td>
                <td style="width: 25%; border-left: 0px;vertical-align: middle;">
                    <div class="font_label" style="font-size: 10pt;"><b>Comune di</b></div>
                    <span class="font_label" style="font-size: 16pt;"><b>{{ $comune[0]->comune ?? '' }}</b></span><br>
                    <span class="font_label" style="font-size: 12pt;">{{ $comune[0]->indirizzo ?? '' }}</span><br>
                    <span class="font_label" style="font-size: 8pt;">{{ $comune[0]->cap ?? '' }}</span> - 
                    <span class="font_label" style="font-size: 8pt;">{{ $comune[0]->provincia ?? '' }}</span>
                </td>
                <td style="width: 65%;text-align:center;vertical-align: middle;">
                    <div class="font_label" style="font-size: 22pt;">CDU</div>
                    <span class="font_label"style="font-size: 16pt;"><b>Calcolo Destinazione Urbanistica</b></span><br><br>
                    <div class="font_label" style="font-size: 9pt;text-align: right;"><b>Situazione al {{ date('d/m/Y') }}</b></div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<br>
<span class="frase_immobile font_label">L'immobile identificato in NCT - NCEU al Fg. {{ $elUiu[0]->fg ?? '' }} p.lla {{ $elUiu[0]->plla ?? '' }}, di superficie pari a {{ $data_uiu['mq'] }}, ricade nelle seguenti destinazioni urbanistiche:</span>
<br>
<br>
<div id="tabella_riassunto">
</div>
<div class="responsive-table-wrapper">
    <table class="table table-condensed table-striped table-bordered" cellspacing="0" style="width: 100%;">
        <thead>
            <tr>
                <th class="font_label" style="width: 25%;word-wrap: break-word; word-break: break-all;">Piano</th>
                <th class="font_label" style="width: 50%;word-wrap: break-word; word-break: break-all;">Zona</th>
                <th class="font_label" style="width: 25%;word-wrap: break-word; word-break: break-all;">Inclusione mq (%)</th>
            </tr>
        </thead>
        <tbody>
            @isset($data_uiu)
                @foreach ($data_uiu['intersects'] as $key => $dati)
                    @foreach ($dati as $data)
                        @php
                            $nomeNorma = isset($nmPiani[$key]) ? $nmPiani[$key] : $key;
                            if (str_contains($nomeNorma, 'urbutm')) {
                                $nomeNorma = str_replace('urbutm', '', $key);
                            }
                        @endphp
                        <tr>
                            <td class="align-middle font_label" style="width: 20%;"><b>{{ strtoupper($nomeNorma) }}<b></td>
                            <td class="align-middle font_label" style="width: 60%;">{{ $data['STRING'] }}</td>
                            <td class="align-middle font_label" style="width: 20%;">{{ $data['cal'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endisset
        </tbody>
    </table>
</div>
