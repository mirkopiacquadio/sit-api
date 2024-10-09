<html lang="it">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Visura catastale</title>
    <style>
        @page {
            margin: 1cm 1cm;
        }

        /** Define now the real margins of every page in the PDF **/
        body {
            margin-top: 3cm;
            margin-bottom: 0cm;
            width: 100%;
            height: 100%;
            left: 0cm;
            right: 0cm;
        }

        /** Define the header rules **/
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 3cm;
            width: 100%;
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
</head>

<body>
    <header>
        <table width="780" cellspacing="0">
            <tbody>
                <tr valign="top" width="780">
                    <td style="width: 240px;">
                        <img src="{{ public_path('/assets/logo/logoSGS.png') }}" style="max-height: 100px" /><br>
                    </td>
                    <td style="width: 300px; text-align:center;vertical-align: middle;">
                        <div style="font-size: 26px;"><b>Visura storica per immobile</b></div>
                        <span style="font-size: 20px;">Situazione degli atti informatizzati al {{ date('d/m/Y') }}</span>
                    </td>
                    <td style="width: 240px; text-align:right">
                        <div style="font-size: 20px;">Data: {{ date('d/m/Y') }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </header>
    <main class="wrapper-page">
        @php
            $data = $res;
            $fabbricatiData = $data[0];
            $proprietariData = $data[1];
            $i = 0;
        @endphp


        @foreach ($fabbricatiData as $fabbricato)
            @php
                $infoAtti = $fabbricato[1];
                $partita_header = $fabbricato[2][0]->partita ?? '';
                $data_efficacia = '';
                $annotazione = '';
                $coll = '';
            @endphp
            @if (str_contains($infoAtti['descDati'], 'IMPIANTO'))
                @if ($i == 0 && $partita_header == '')
                    <h4><b>Unità immobiliare dall'impianto meccanografico</b></h4>
                @else
                    <h4><b>{{ $partita_header }}</b></h4>
                @endif
            @else
                @if ($i == 0 && $partita_header == '')
                    <h4><b>Unità immobiliare</b></h4>
                @else
                    <h4><b>{{ $partita_header }}</b></h4>
                @endif
            @endif

            @if (isset($infoAtti['data_efficacia']) && $infoAtti['data_efficacia'] != null)
                @php
                    $data_efficacia = date('d/m/Y', strtotime($infoAtti['data_efficacia']));
                @endphp
            @elseif (isset($infoAtti['data_registrazione_atti']) && $infoAtti['data_registrazione_atti'] != null)
                @php
                    $data_efficacia = date('d/m/Y', strtotime($infoAtti['data_registrazione_atti']));
                @endphp
            @endif
      
            <table class="tableNotHeader" cellspacing="0" >
                <tbody>
                    <tr>
                        <td colspan="8"><b>Dal</b>: {{ $data_efficacia }}</td>
                    </tr>
       
                        <td style="vertical-align: middle;">Sezione urbana</td>
                        <td style="vertical-align: middle;">Foglio</td>
                        <td style="vertical-align: middle;">Particella</td>
                        <td style="vertical-align: middle;">Sub</td>
                        <td style="vertical-align: middle;">Zona Cens.</td>
                        <td style="vertical-align: middle;">Micro Zona</td>
                        <td style="vertical-align: middle;">Categoria</td>
                        <td style="vertical-align: middle;">Classe</td>
                        <td style="vertical-align: middle;">Consistenza</td>
                        <td style="vertical-align: middle; text-align: center;">Superficie Catastale</td>
                        <td style="vertical-align: middle; text-align: center;">Rendita</td>
                        </tr>'; 

                    @foreach ($fabbricato[2] as $atto)
                        <tr>
                            <td style="vertical-align: middle;"></td>
                            <td style="vertical-align: middle;">{{ $atto['foglio'] ?? '' }}</td>
                            <td style="vertical-align: middle;">{{ $atto['numero'] ?? '' }}</td>
                            <td style="vertical-align: middle;">{{ $atto['sub'] ?? '' }}</td>
                            <td style="vertical-align: middle;"></td>
                            <td style="vertical-align: middle;"></td>
                            <td style="vertical-align: middle;">{{ $atto['cat1'] ?? '' }}</td>
                            <td style="vertical-align: middle; text-align: center;">{{ $atto['classe'] ?? '' }}</td>
                            <td style="vertical-align: middle; text-align: center;">{{ $atto['consistenza'] ?? '' }}</td>
                            <td style="text-align: center;">{{ $atto['superficie'] ?? '' }}</td>
                            <td style="text-align: center;">Euro&nbsp;{{ $atto['rendita_euro'] ?? '' }}<br>L.&nbsp;{{ $atto['rendita_lire'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2"><b>Partita:</b>&nbsp;{{ $atto['partita'] ?? '' }}</td>
                            <td colspan="5"><b>Annotazioni</b>: {{ $infoAtti['annotazione'] ?? '' }}</td>
                            <td colspan="4"><b>Coll</b>: {{ $infoAtti['coll'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @php
                $i++;
            @endphp
        @endforeach

        @php
            $initialData = '';
        @endphp

        @foreach ($proprietariData as $proprietari)
            @foreach ($proprietari as $proprietario)
                @php
                    $proprietarioInfo = $proprietario['prop'][0];
                @endphp

                @if ($initialData != date('d/m/Y', strtotime($proprietario['dataval'])) && $initialData == '')
                    <h4><b>Situazione degli intestati dal {{ date('d/m/Y', strtotime($proprietario['dataval'])) }}</b></h4>
                    <table class="tableNotHeader" cellspacing="0" >
                        <tbody>
                            <tr>
                                <td>Proprietario</td>
                                <td>Titolo</td>
                                <td>Descrizione</td>
                            </tr>
                        @elseif ($initialData != date('d/m/Y', strtotime($proprietario['dataval'])) && $initialData != '')
                        </tbody>
                    </table>
                    <h4><b>Situazione degli intestati dal {{ date('d/m/Y', strtotime($proprietario['dataval'])) }}</b></h4>
                    <table class="tableNotHeader" cellspacing="0" >
                        <tbody>
                            <tr>
                                <td>Proprietario</td>
                                <td>Titolo</td>
                                <td>Descrizione</td>
                            </tr>
                @endif

                <tr>
                    <td>{{ $proprietarioInfo['pers1'] ?? '' }}</td>
                    <td>{{ $proprietarioInfo['titolo'] ?? '' }}</td>
                    <td>{{ $proprietario['desc'] ?? '' }}</td>
                </tr>

                @php
                    if ($proprietario['dataval'] != '') {
                        $initialData = date('d/m/Y', strtotime($proprietario['dataval']));
                    }
                @endphp
            @endforeach
        @endforeach
    </main>
</body>

</html>