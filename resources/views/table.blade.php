<div class="responsive-table-wrapper" style="max-width: 550px; overflow-x: auto; margin: 0 auto;">
    <div class="table-responsive" style="width: 100%;">
        <table class="table table-condensed table-striped table-bordered lizmapPopupTable" style="max-width: 550px;">
            <thead>
                <tr>
                    <th style="word-wrap: break-word; word-break: break-all;">Piano</th>
                    <th style="word-wrap: break-word; word-break: break-all;">Zona</th>
                    <th style="word-wrap: break-word; word-break: break-all;">Inclusione mq (%)</th>
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
                                <td class="align-middle" style="word-wrap: break-word; word-break: break-all; max-width: 200px;"><b>{{ strtoupper($nomeNorma) }}<b></td>
                                <td class="align-middle" style="word-wrap: break-word; word-break: break-all; max-width: 200px;">{{ $data['STRING'] }}</td>
                                <td class="align-middle" style="word-wrap: break-word; word-break: break-all;">{{ $data['cal'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endisset
            </tbody>
        </table>
    </div>
</div>
