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
</style>
<div class="responsive-table-wrapper">
    <table cellspacing="0" style="width: 100%;">
        <tbody>
            <tr valign="top">
                <td style="width: 12%; border-right: 0px;vertical-align: middle;">
                    <img src="https://sitmonter.it/loghi/{{ $comune[0]->nome_immagine }}" alt="comune" style="max-height: 100px" /><br>
                </td>
                <td style="width: 18%; border-left: 0px;vertical-align: middle;">
                    <div style="font-size: 15px;"><b>Comune di</b></div>
                    <span style="font-size: 15px;">{{ $comune[0]->comune ?? '' }}</span><br>
                    <span style="font-size: 15px;">{{ $comune[0]->indirizzo ?? '' }}</span><br>
                    <span style="font-size: 15px;">{{ $comune[0]->cap ?? '' }}</span> - 
                    <span style="font-size: 15px;">{{ $comune[0]->provincia ?? '' }}</span>
                </td>
                <td style="width: 50%;text-align:center;vertical-align: middle;">
                    <div style="font-size: 20px;"><b>CDU</b></div>
                    <span style="font-size: 20px;">Situazione degli atti informatizzati al {{ date('d/m/Y') }}</span>
                </td>
                <td style="width: 20%;text-align:right;vertical-align: middle;">
                    <div style="font-size: 15px;">Data:{{ date('d/m/Y') }}</div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<h3>La particella foglio {{ $elUiu[0]->fg ?? '' }} numero {{ $elUiu[0]->plla ?? '' }} di superficie pari a {{ $data_uiu['mq'] }} ha le seguenti informazioni:</h3>
<div id="tabella_riassunto">
</div>
<div class="responsive-table-wrapper">
    <table class="table table-condensed table-striped table-bordered" cellspacing="0" style="width: 100%;">
        <thead>
            <tr>
                <th style="width: 25%;word-wrap: break-word; word-break: break-all;">Piano</th>
                <th style="width: 50%;word-wrap: break-word; word-break: break-all;">Zona</th>
                <th style="width: 25%;word-wrap: break-word; word-break: break-all;">Inclusione mq (%)</th>
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
                            <td class="align-middle" style="width: 20%;"><b>{{ strtoupper($nomeNorma) }}<b></td>
                            <td class="align-middle" style="width: 60%;">{{ $data['STRING'] }}</td>
                            <td class="align-middle" style="width: 20%;">{{ $data['cal'] }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endisset
        </tbody>
    </table>
</div>
