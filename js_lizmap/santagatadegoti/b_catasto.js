var html = '<div id="gsv-monter"></div><div id="gsv-monter-result"></div>';
if ($('#info-user-login').length > 0) {
    lizMap.addDock(
        'catasto',
        'Catasto',
        'minidock',
        html,
        'icon-search'
    );
}
lizMap.events.on({
    minidockopened: function (e) {
        if (e.id == 'catasto') {
            var headerStyles = window.getComputedStyle(document.getElementById('header'))
            var height, width;

            if (headerStyles.display == 'none') {
                // we are in the iframe mode. No header displayed
                var mapStyles = window.getComputedStyle(document.getElementById('map'));
                height = (parseFloat(mapStyles.height) * 45 / 100) - 15;
                width = document.getElementById('mini-dock').getBoundingClientRect().width - 20;
            } else {
                // we are in the normal mode
                var sidemenuStyles = window.getComputedStyle(document.getElementById('mapmenu'))
                var minidockStyles = window.getComputedStyle(document.getElementById('mini-dock'));
                height = (parseFloat(sidemenuStyles.height) * 45 / 100) - 15;
                width = 850;
            }

            var monterPop = document.getElementById('catasto');
            var mini_dock = document.getElementById('mini-dock');
            var mini_docks_cl = document.getElementsByClassName('mini-dock-close');

            monterPop.style.width = width + 'px';
            monterPop.style.height = height + 'px';

            mini_dock.style.maxWidth = width + 'px';
            mini_dock.style.maxHeight = height + 'px';

            var menu_content = monterPop.querySelector('.menu-content');
            menu_content.style.height = height + 'px';

            for (var i = 0; i < mini_docks_cl.length; i++) {
                mini_docks_cl[i].style.marginRight = '15px';
            }
            

            var res = generatePopupBody();
            $('#gsv-monter').append(res);
        }
    },
    minidockclosed: function (e) {
        if (e.id == 'catasto') {
            //Facciamo qualcosa in chiusura
            $('#gsv-monter').show();
            $('#gsv-monter-result').hide();
            $('#gsv-monter').empty();
            $('#gsv-monter-result').empty();
        }
    }
});

function openVisura(foglio, particella) {
    document.getElementById('button-catasto').click();
    $('#foglio').val(foglio);
    $('#particella').val(particella);
    goToSearch();
}

function generatePopupBody() {
    var html = '';
    html += '<ul class="nav nav-tabs">';
    html += '<li class="active">';
    html += '<a href="#" onclick="changeSearchType(\'rapida\')">Ricerca rapida</a>';
    html += '</li>';
    html += '<li>';
    html += '<a href="#" onclick="changeSearchType(\'avanzata\')">Ricerca avanzata</a>';
    html += '</li>';
    html += '</ul>';
    html += '<div id="campiRicercaRapida" class="tab-pane active" style="display: flex; flex-wrap: wrap; margin-top: 10px; margin-bottom: 10px;">';
    html += '<div class="span2" style="display: flex; flex-direction: column;">';
    html += '<label for="foglio">Foglio</label>';
    html += '<input type="text" class="span3" id="foglio" name="foglio" style="display: flex; flex-direction: column; width:50%">';
    html += '</div>'; // Fine span3
    html += '<div class="span2" style="display: flex; flex-direction: column;">';
    html += '<label for="particella">Particella</label>';
    html += '<input type="text" class="span3" id="particella" name="particella" style="display: flex; flex-direction: column; width:50%">';
    html += '</div>'; // Fine span3
    html += '<div class="span2" style="display: flex; flex-direction: column;">';
    html += '<label for="sub">Sub</label>';
    html += '<input type="text" class="span3" id="sub" name="sub" style="display: flex; flex-direction: column; width:50%">';
    html += '</div>'; // Fine span3
    html += '<div class="span10" style="margin-top: 10px; margin-bottom: 10px;">';
    html += '<a href="javascript:;" onclick="goToSearch()" type="button" class="btn btn-primary">Cerca</a>';
    html += '</div>';
    html += '</div>'; // Fine campiRicercaRapida
    html += '<div id="campiRicercaAvanzata" class="tab-pane" style="display: flex; flex-wrap: wrap; margin-top: 10px; margin-bottom: 10px; display: none;">';
    html += '<div class="span10">';
    html += '<label for="tipoPersona">Tipo Persona:</label>';
    html += '<label class="radio">';
    html += '<input type="radio" name="tipoPersona" id="fisica" value="f" checked onchange="radioPersona()"> Fisica';
    html += '</label>';
    html += '<label class="radio">';
    html += '<input type="radio" name="tipoPersona" id="giuridica" value="g" onchange="radioPersona()"> Giuridica';
    html += '</label>';
    html += '</div>';

    html += '<div class="span3 div_fisico" style="display: flex; flex-direction: column;">';
    html += '<label  for="nome">Nome</label>';
    html += '<input type="text" class="span3" id="nome" name="nome" >';
    html += '</div>';
    html += '<div class="span3 div_fisico" style="display: flex; flex-direction: column;">';
    html += '<label  for="cognome">Cognome</label>';
    html += '<input type="text" class="span3" id="cognome" name="cognome" >';
    html += '</div>';
    html += '<div class="span3 div_fisico" style="display: flex; flex-direction: column;">';
    html += '<label  for="codiceFiscale">Codice Fiscale</label>';
    html += '<input type="text" class="span3" id="codiceFiscale" name="codiceFiscale" >';
    html += '</div>';

    html += '<div class="span3 div_giu" style="display: flex; flex-direction: column; display:none;">';
    html += '<label  for="denominazione">Denominazione</label>';
    html += '<input type="text" class="span3" id="denominazione" name="denominazione" >';
    html += '</div>';
    html += '<div class="span3 div_giu" style="display: flex; flex-direction: column; display:none;">';
    html += '<label  for="piva">PIVA</label>';
    html += '<input type="text" class="span3" id="piva" name="piva" >';
    html += '</div>';

    html += '<div class="span10" style="margin-top: 10px; margin-bottom: 10px;">';
    html += '<a href="javascript:;" onclick="goToSearchAvanzata()" type="button" class="btn btn-primary">Cerca</a>';
    html += '</div>';


    html += '<div class="span8" style="display: flex; flex-wrap: wrap; margin-top: 10px; margin-bottom: 10px;">';
    html += '<div style="margin-top: 10px; margin-bottom: 10px;" id="table-result-avanzata">';
    html += '</div>';
    html += '</div>';
    html += '</div>'; // Fine campiRicercaAvanzata

    html += '<div style="display: flex; flex-wrap: wrap; margin-top: 10px; margin-bottom: 10px;">';
    html += '<div class="span8" style="margin-top: 10px; margin-bottom: 10px;" id="table-result">';
    html += '</div>';
    html += '<div class="span8" style="margin-top: 10px; margin-bottom: 10px;" id="table-result-sub">';
    html += '</div>';
    html += '</div>';

    // End of main content body
    html += '</div>';

    return html;
}

function changeSearchType(type) {
    if (type === 'rapida') {
        $('#campiRicercaRapida').show();
        $('#campiRicercaAvanzata').hide();
    } else if (type === 'avanzata') {
        $('#campiRicercaRapida').hide();
        $('#campiRicercaAvanzata').show();
    }
}

function goToSearch() {
    var foglio = $('#foglio').val();
    var particella = $('#particella').val();
    var sub = $('#sub').val();

    if (foglio == '' && particella == '') {
        alert('Attenzione almeno uno tra foglio e particella deve essere compilato!');
        return;
    }

    $.ajax({
        type: "GET",
        url: "https://sitmonter.it/api/selectFgPllaSubCatasto",
        data: {
            code_comune: comuneUtente,
            foglio: foglio,
            particella: particella,
            sub: sub
        },
        success: function (data) {

            var table = $('<table id="data-table" class="display"></table>');

            $('#table-result').empty().append(table);
            var mergedData = data[0].concat(data[1]);
            var dataTable = $('#data-table').DataTable({
                data: mergedData,
                columns: [
                    { title: 'Foglio', data: 'foglio' },
                    { title: 'Particella', data: 'numero' },
                    { title: 'Sub', data: 'sub' },
                    { title: 'Tipologia', data: 'tipologia' },
                    { title: 'Categoria', data: 'catqua' },
                    { title: 'ID', data: 'id', visible: false }
                ]
            });

            $('#data-table tbody').on('click', 'tr', function () {
                var rowData = dataTable.row(this).data();
                openLayer(rowData);
                searchRow(rowData);
            });

        }
    });
};

function goToSearchAvanzata() {
    var nome = $('#nome').val();
    var cognome = $('#cognome').val();
    var cf = $('#codiceFiscale').val();
    var denominazione = $('#denominazione').val();
    var piva = $('#piva').val();
    if (valoreSelezionato == 'f') {
        if (nome == '' && cognome == '' && cf == '') {
            alert('Attenzione almeno uno tra i campi proposti deve essere compilato!');
            return;
        }
    }
    if (valoreSelezionato == 'g') {
        if (denominazione == '' && piva == '') {
            alert('Attenzione almeno uno tra i campi proposti deve essere compilato!');
            return;
        }
    }

    var valoreSelezionato = $('input[name="tipoPersona"]:checked').val();
    var link = 'https://sitmonter.it/api/selectPersoneFisicheCatasto';
    if (valoreSelezionato == 'g') link = 'https://sitmonter.it/api/selectPersoneGiuridicheCatasto';

    var dataBody = {
        code_comune: comuneUtente,
        nome: nome,
        cognome: cognome,
        cf: cf
    }

    if (valoreSelezionato == 'g') {
        dataBody = {
            code_comune: comuneUtente,
            denominazione: denominazione,
            piva: piva
        }
    }

    $.ajax({
        type: "GET",
        url: link,
        data: dataBody,
        success: function (data) {
            var table = $('<table id="data-table-avanzata" class="display"></table>');

            $('#table-result-avanzata').empty().append(table);
            if (valoreSelezionato == 'g') {
                var dataTable = $('#data-table-avanzata').DataTable({
                    data: data,
                    columns: [
                        { title: 'Denominazione', data: 'denominazione' },
                        { title: 'Partita iva', data: 'cf' },
                        { title: 'ID', data: 'id', visible: false }
                    ]
                });
            } else {
                var dataTable = $('#data-table-avanzata').DataTable({
                    data: data,
                    columns: [
                        { title: 'Nome', data: 'nome' },
                        { title: 'Cognome', data: 'cognome' },
                        { title: 'CF', data: 'cf' },
                        { title: 'Data di nascita', data: 'data_nascita' },
                        { title: 'Luogo di nascita', data: 'desc_l_nas' },
                        { title: 'Prov di nascita', data: 'pv_nas' },
                        { title: 'Indicazione', data: 'indicazioni' },
                        { title: 'ID', data: 'id', visible: false }
                    ]
                });
            }

            $('#data-table-avanzata tbody').on('click', 'tr', function () {
                var rowData = dataTable.row(this).data();
                searchRowAvanzata(rowData);
            });

        }
    });
};

function searchRow(rowData) {
    changeContext('detail')

    var html = '<div class="row" style="justify-content: space-between;"><div class="span4"><a href="javascript:;" onclick="changeContext(\'back\')" class="btn btn-danger">Indietro</a></div>';
    html += '<div class="span4 text-right" target="_blank">';
    html += '<form action="https://sitmonter.it/api/print_catasto" method="GET" target="_blank">';
    html += '<input type="hidden" name="code_comune" value="'+comuneUtente+'" >';
    html += '<input type="hidden" name="crypt" value="monterGe0M1rko">';
    var a = '';
    var b = '';
    var c = '';

    if (rowData.foglio != null && rowData.foglio != '' && rowData.foglio != undefined) a = rowData.foglio;
    if (rowData.numero != null && rowData.numero != '' && rowData.numero != undefined) b = rowData.numero;
    if (rowData.sub != null && rowData.sub != '' && rowData.sub != undefined) c = rowData.sub;
    html += '<input type="hidden" name="foglio" value="' + a + '">';
    html += '<input type="hidden" name="particella" value="' + b + '">';
    html += '<input type="hidden" name="sub" value="' + c + '">';
    html += '<input type="hidden" name="type" value="' + rowData.tipologia + '">';
    html += '<button type="submit" class="btn btn-success">SCARICA</b></form>';
    html += '</div></div>';
    if (rowData.tipologia == "fabbricato") html += '<div class="span8" style="margin-top: 10px; margin-right: 10px; margin-bottom: 10px;" id="table-fabb"></div>';
    else html += '<div class="span8" style="margin-top: 10px; margin-right: 10px; margin-bottom: 10px;" id="table-terre"></div>';
    html += '<div class="span8" style="margin-top: 10px; margin-right: 10px; margin-bottom: 10px;" id="table-proprietari"></div>';

    $('#gsv-monter-result').empty().append(html);

    if (rowData.tipologia == "fabbricato") {
        $.ajax({
            type: "GET",
            url: "https://sitmonter.it/api/elencoMutazioniCatastoFabbricati",
            data: {
            code_comune: comuneUtente,
            foglio: rowData.foglio,
                particella: rowData.numero,
                sub: rowData.sub
            },
            success: function (data) {

                var fabbricatiData = data[0];
                var proprietariData = data[1];
                var tableHTML = '';

                Object.values(fabbricatiData).forEach(function (fabbricato, indice) {

                    var infoAtti = fabbricato[1];
                    var partita_header = '';
                    if (fabbricato[2][0].partita != null) {
                        let partita_header_temp = parseInt(fabbricato[2][0].partita);
                        if (isNaN(partita_header_temp)) partita_header = fabbricato[2][0].partita;
                    }

                    if (infoAtti.descDati.includes('IMPIANTO')) {
                        if (indice === 0 && partita_header == '') {
                            tableHTML += '<h4><b>Unità immobiliare dall\'impianto meccanografico</b></h4>';
                        } else tableHTML += '<h4><b>' + partita_header + '</b></h4>';
                    } else {
                        if (indice === 0 && partita_header == '') {
                            tableHTML += '<h4><b>Unità immobiliare</b></h4>';
                        } else tableHTML += '<h4><b>' + partita_header + '</b></h4>';
                    }

                    tableHTML += '<table class="table bg-white">';
                    tableHTML += '<tbody>';
                    var data_efficacia = '';
                    var annotazione = '';
                    var indirizzo = '';
                    if (infoAtti.data_efficacia != null && infoAtti.data_efficacia != undefined) {
                        data_efficacia = infoAtti.data_efficacia;
                        var partiData = data_efficacia.split("-");
                        var dataConvertita = partiData[2] + "/" + partiData[1] + "/" + partiData[0];
                        data_efficacia = dataConvertita;
                    }
                    if (infoAtti.annotazione != null && infoAtti.annotazione != undefined) annotazione = infoAtti.annotazione;
                    if (infoAtti.indirizzo != null && infoAtti.indirizzo != undefined) indirizzo = infoAtti.indirizzo;

                    tableHTML += '</tbody>';
                    tableHTML += '</table>';
                    tableHTML += '<table class="table table-bordered" style="background-color: #FFF">';
                    tableHTML += '<tbody>';
                    tableHTML += '<tr>';
                    tableHTML += '<td colspan="8"><b>Dal</b>: ' + data_efficacia + '</td>';
                    tableHTML += '</tr>';
                    tableHTML += '<tr>';
                    tableHTML += '<td style="vertical-align: middle;">Sezione urbana</td>';
                    tableHTML += '<td style="vertical-align: middle;">Foglio</td>';
                    tableHTML += '<td style="vertical-align: middle;">Particella</td>';
                    tableHTML += '<td style="vertical-align: middle;">Sub</td>';
                    tableHTML += '<td style="vertical-align: middle;">Zona Cens.</td>';
                    tableHTML += '<td style="vertical-align: middle;">Micro Zona</td>';
                    tableHTML += '<td style="vertical-align: middle;">Categoria</td>';
                    tableHTML += '<td style="vertical-align: middle;">Classe</td>';
                    tableHTML += '<td style="vertical-align: middle;">Consistenza</td>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;">Superficie Catastale</td>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;">Rendita</td>';
                    tableHTML += '</tr>';

                    fabbricato[2].forEach(function (atto) {
                        var partita = '';
                        if (partita != null) {
                            let partita_temp = parseInt(atto.partita);
                            if (!isNaN(partita_temp)) partita = partita_temp;
                        }

                        // Inizializzazione delle variabili vuote
                        let foglioVar = '';
                        let numeroVar = '';
                        let subVar = '';
                        let cat = '';
                        let classe = '';
                        let superficie = '';
                        let rendita_euro = '';
                        let rendita_lire = '';
                        let consistenza = '';

                        // Controllo se atto.foglio è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.foglio !== null && atto.foglio !== undefined) {
                            foglioVar = atto.foglio;
                        }

                        // Controllo se atto.numero è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.numero !== null && atto.numero !== undefined) {
                            numeroVar = atto.numero;
                        }

                        // Controllo se atto.sub è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.sub !== null && atto.sub !== undefined) {
                            subVar = atto.sub;
                        }

                        // Controllo se atto.pz è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.cat1 !== null && atto.cat1 !== undefined) {
                            cat = atto.cat1;
                        }

                        if (atto.classe !== null && atto.classe !== undefined) {
                            classe = atto.classe;
                        }

                        if (atto.superficie !== null && atto.superficie !== undefined) {
                            superficie = atto.superficie;
                        }

                        if (atto.rendita_euro !== null && atto.rendita_euro !== undefined) {
                            rendita_euro = atto.rendita_euro;
                        }

                        if (atto.rendita_lire !== null && atto.rendita_lire !== undefined) {
                            rendita_lire = atto.rendita_lire;
                        }

                        if (atto.consistenza !== null && atto.consistenza !== undefined) {
                            consistenza = atto.consistenza;
                        }

                        // Utilizzo delle variabili per generare la tabella HTML
                        tableHTML += '<tr>';
                        tableHTML += '<td style="vertical-align: middle;"></td>';
                        tableHTML += '<td style="vertical-align: middle;">' + foglioVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;">' + numeroVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;">' + subVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;"></td>';
                        tableHTML += '<td style="vertical-align: middle;"></td>';
                        tableHTML += '<td style="vertical-align: middle;">' + cat + '</td>';
                        tableHTML += '<td style="vertical-align: middle; text-align: center;">' + classe + '</td>';
                        tableHTML += '<td style="vertical-align: middle; text-align: center;">' + consistenza + '</td>';
                        tableHTML += '<td style="text-align: center;">' + superficie + '</td>';
                        tableHTML += '<td style="text-align: center;">Euro&nbsp;' + rendita_euro + '<br>L.&nbsp;' + rendita_lire + '</td>';
                        tableHTML += '</tr>';
                        tableHTML += '<tr>';
                        tableHTML += '<td colspan="2"><b>Partita:</b>&nbsp;' + partita + '</td>';
                        tableHTML += '<td colspan="5"><b>Annotazioni</b>: ' + annotazione + '</td>';
                        tableHTML += '<td colspan="4"><b>Indirizzo:</b>: ' + indirizzo + '</td>';
                        tableHTML += '</tr>';
                    });
                });

                tableHTML += '</tbody>';
                tableHTML += '</table>';

                document.getElementById('table-fabb').innerHTML = tableHTML;


                var tableHTML = '';
                var initialData = '';
                Object.values(proprietariData).forEach(function (proprietari) {
                    proprietari.forEach(function (proprietario) {
                        var proprietarioInfo = proprietario.prop[0];
                        // Analizza la data dal formato DD/MM/YYYY
                        var dataSplit = proprietario.dataval.split('-');
                        var dataFormattata = dataSplit[2] + '/' + dataSplit[1] + '/' + dataSplit[0];

                        let desc = '';
                        if (proprietario.desc != null && proprietario.desc != undefined) {
                            desc = proprietario.desc;
                        }

                        if (initialData != dataFormattata && initialData == '') tableHTML += '<h4><b>Situazione degli intestati dal ' + dataFormattata + '</b></h4><table class="table table-bordered"  style="background-color: #FFF"><tbody><tr><td>Proprietario</td><td>Titolo</td><td>Descrizione</td></tr>';
                        else if (initialData != dataFormattata && initialData != '') tableHTML += '</tbody></table><h4><b>Situazione degli intestati dal ' + dataFormattata + '</b></h4><table class="table table-bordered"  style="background-color: #FFF"><tbody><tr><td>Proprietario</td><td>Titolo</td><td>Descrizione</td></tr>';
                        tableHTML += '<tr>';
                        tableHTML += '<td>' + proprietarioInfo.pers1 + '</td>';
                        tableHTML += '<td>' + proprietarioInfo.titolo + '</td>';
                        tableHTML += '<td>' + desc + '</td>';
                        tableHTML += '</tr>';

                        initialData = dataFormattata;
                    });
                });

                document.getElementById('table-proprietari').innerHTML = tableHTML;
            }
        });
    } else {
        $.ajax({
            type: "GET",
            url: "https://sitmonter.it/api/elencoMutazioniCatastoTerreni",
            data: {
            code_comune: comuneUtente,
            foglio: rowData.foglio,
                particella: rowData.numero,
                sub: rowData.sub
            },
            success: function (data) {
                var terreniData = data[0];
                var proprietariData = data[1];
                var tableHTML = '';

                Object.values(terreniData).forEach(function (terreno, indice) {


                    var infoAtti = terreno[1];
                    var partita_header = '';
                    if (terreno[2][0].partita != null) {
                        let partita_header_temp = parseInt(terreno[2][0].partita);
                        if (isNaN(partita_header_temp)) partita_header = terreno[2][0].partita;
                    }

                    if (infoAtti.descDati.includes('IMPIANTO')) {
                        if (indice === 0 && partita_header == '') {
                            tableHTML += '<h4><b>Unità immobiliare dall\'impianto meccanografico</b></h4>';
                        } else tableHTML += '<h4><b>' + partita_header + '</b></h4>';
                    } else {
                        if (indice === 0 && partita_header == '') {
                            tableHTML += '<h4><b>Unità immobiliare</b></h4>';
                        } else tableHTML += '<h4><b>' + partita_header + '</b></h4>';
                    }

                    tableHTML += '<table class="table bg-white">';
                    tableHTML += '<tbody>';
                    var data_efficacia = '';
                    var annotazione = '';
                    var coll = '';
                    if (infoAtti.data_efficacia != null && infoAtti.data_efficacia != undefined) {
                        data_efficacia = infoAtti.data_efficacia;
                        var partiData = data_efficacia.split("-");
                        var dataConvertita = partiData[2] + "/" + partiData[1] + "/" + partiData[0];
                        data_efficacia = dataConvertita;
                    } else if (infoAtti.data_registrazione_atti != null && infoAtti.data_registrazione_atti != undefined) {
                        data_efficacia = infoAtti.data_registrazione_atti;
                        var partiData = data_efficacia.split("-");
                        var dataConvertita = partiData[2] + "/" + partiData[1] + "/" + partiData[0];
                        data_efficacia = dataConvertita;
                    }
                    if (infoAtti.annotazione != null && infoAtti.annotazione != undefined) annotazione = infoAtti.annotazione;
                    if (infoAtti.coll != null && infoAtti.coll != undefined) coll = infoAtti.coll;

                    tableHTML += '</tbody>';
                    tableHTML += '</table>';
                    tableHTML += '<table class="table table-bordered" style="background-color: #FFF">';
                    tableHTML += '<tbody>';
                    tableHTML += '<tr>';
                    tableHTML += '<td colspan="8"><b>Dal</b>: ' + data_efficacia + '</td>';
                    tableHTML += '</tr>';
                    tableHTML += '<tr>';
                    tableHTML += '<td style="vertical-align: middle;" rowspan="2">Foglio</td>';
                    tableHTML += '<td style="vertical-align: middle;" rowspan="2">Particella</td>';
                    tableHTML += '<td style="vertical-align: middle;" rowspan="2">Sub</td>';
                    tableHTML += '<td style="vertical-align: middle;" rowspan="2">Porz</td>';
                    tableHTML += '<td style="vertical-align: middle;" rowspan="2">Qualità&nbsp;Classe</td>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;">Superficie(m²)</td>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;" colspan="2">Reddito</td>';
                    tableHTML += '</tr>';
                    tableHTML += '<tr>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;">ha&nbsp;are&nbsp;ca</td>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;">Domenicale</td>';
                    tableHTML += '<td style="vertical-align: middle; text-align: center;">Agrario</td>';
                    tableHTML += '</tr>';


                    terreno[2].forEach(function (atto) {
                        var partita = '';
                        if (partita != null) {
                            let partita_temp = parseInt(atto.partita);
                            if (!isNaN(partita_temp)) partita = partita_temp;
                        }

                        // Inizializzazione delle variabili vuote
                        let foglioVar = '';
                        let numeroVar = '';
                        let subVar = '';
                        let pzVar = '';
                        let quaClVar = '';

                        // Controllo se atto.foglio è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.foglio !== null && atto.foglio !== undefined) {
                            foglioVar = atto.foglio;
                        }

                        // Controllo se atto.numero è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.numero !== null && atto.numero !== undefined) {
                            numeroVar = atto.numero;
                        }

                        // Controllo se atto.sub è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.sub !== null && atto.sub !== undefined) {
                            subVar = atto.sub;
                        }

                        // Controllo se atto.pz è diverso da null e undefined, quindi assegnazione del valore
                        if (atto.pz !== null && atto.pz !== undefined) {
                            pzVar = atto.pz;
                        }

                        // Controllo se atto.qua e atto.cl sono diversi da null e undefined, quindi assegnazione del valore
                        if (atto.qua !== null && atto.qua !== undefined && atto.cl !== null && atto.cl !== undefined) {
                            quaClVar = atto.qua + ' ' + atto.cl;
                        }

                        // Utilizzo delle variabili per generare la tabella HTML
                        tableHTML += '<tr>';
                        tableHTML += '<td style="vertical-align: middle;">' + foglioVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;">' + numeroVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;">' + subVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;">' + pzVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle;">' + quaClVar + '</td>';
                        tableHTML += '<td style="vertical-align: middle; text-align: center;">' + atto.ha + ' ' + atto.a + ' ' + atto.ca + '</td>';
                        tableHTML += '<td style="text-align: center;">Euro&nbsp;' + atto.domeuro + '<br>L.&nbsp;' + atto.domlire + '</td>';
                        tableHTML += '<td style="text-align: center;">Euro&nbsp;' + atto.agreuro + '<br>L.&nbsp;' + atto.agrlire + '</td>';
                        tableHTML += '</tr>';
                        tableHTML += '<tr>';
                        tableHTML += '<td colspan="2"><b>Partita:</b>&nbsp;' + partita + '</td>';
                        tableHTML += '<td colspan="4"><b>Annotazioni</b>: ' + annotazione + '</td>';
                        tableHTML += '<td colspan="2"><b>Coll</b>: ' + coll + '</td>';
                        tableHTML += '</tr>';
                    });
                });

                tableHTML += '</tbody>';
                tableHTML += '</table>';

                document.getElementById('table-terre').innerHTML = tableHTML;


                var tableHTML = '';
                var initialData = '';
                Object.values(proprietariData).forEach(function (proprietari) {
                    proprietari.forEach(function (proprietario) {
                        var proprietarioInfo = proprietario.prop[0];
                        // Analizza la data dal formato DD/MM/YYYY
                        var dataSplit = proprietario.dataval.split('-');
                        var dataFormattata = dataSplit[2] + '/' + dataSplit[1] + '/' + dataSplit[0];

                        if (initialData != dataFormattata && initialData == '') tableHTML += '<h4><b>Situazione degli intestati dal ' + dataFormattata + '</b></h4><table class="table table-bordered"  style="background-color: #FFF"><tbody><tr><td>Proprietario</td><td>Titolo</td><td>Descrizione</td></tr>';
                        else if (initialData != dataFormattata && initialData != '') tableHTML += '</tbody></table><h4><b>Situazione degli intestati dal ' + dataFormattata + '</b></h4><table class="table table-bordered"  style="background-color: #FFF"><tbody><tr><td>Proprietario</td><td>Titolo</td><td>Descrizione</td></tr>';

                        let desc = '';
                        if (proprietario.desc != null && proprietario.desc != undefined) {
                            desc = proprietario.desc;
                        }

                        tableHTML += '<tr>';
                        tableHTML += '<td>' + proprietarioInfo.pers1 + '</td>';
                        tableHTML += '<td>' + proprietarioInfo.titolo + '</td>';
                        tableHTML += '<td>' + desc + '</td>';
                        tableHTML += '</tr>';

                        initialData = dataFormattata;
                    });
                });

                document.getElementById('table-proprietari').innerHTML = tableHTML;
            }
        });
    }
}

function searchRowAvanzata(rowData) {
    var valoreSelezionato = $('input[name="tipoPersona"]:checked').val();
    var id = rowData.id;
    if (id == '' && valoreSelezionato == '') {
        alert('Attenzione errore imprevisto contattare l\'amministratore di sistema!');
        return;
    }

    $.ajax({
        type: "GET",
        url: "https://sitmonter.it/api/selectUiuSogg",
        data: {
            code_comune: comuneUtente,
            id: id,
            tipoSogg: valoreSelezionato
        },
        success: function (data) {

            var table = $('<table id="data-table-avanzata" class="display"></table>');

            $('#table-result-avanzata').empty().append(table);

            var dataTable = $('#data-table-avanzata').DataTable({
                data: data,
                columns: [
                    { title: 'Foglio', data: 'foglio' },
                    { title: 'Particella', data: 'numero' },
                    { title: 'Sub', data: 'sub' },
                    { title: 'Tipologia', data: 'tipologia' },
                    { title: 'Categoria', data: 'catqua' },
                    { title: 'ID', data: 'id', visible: false }
                ]
            });

            $('#data-table-avanzata tbody').on('click', 'tr', function () {
                var rowData = dataTable.row(this).data();
                openLayer(rowData);
                searchRow(rowData);
            });

        }
    });
}

function changeContext(type) {
    if (type == 'detail') {
        $('#gsv-monter').hide();
        $('#gsv-monter-result').show();
    } else {
        $('#gsv-monter').show();
        $('#gsv-monter-result').hide();
    }
}

function radioPersona() {

    var valoreSelezionato = $('input[name="tipoPersona"]:checked').val();
    // Esempio di azione basata sulla selezione

    if (valoreSelezionato == 'f') {
        $('.div_fisico').show();
        $('.div_giu').hide();
    } else if (valoreSelezionato == 'g') {
        $('.div_giu').show();
        $('.div_fisico').hide();
    }

}

function openLayer(rowData) {
    const comune = comuneUtente;
    var foglio = '';
    var particella = '';
    if (rowData.foglio != null && rowData.foglio != '' && rowData.foglio != undefined) foglio = rowData.foglio.toString().padStart(3, '0');
    if (rowData.numero != null && rowData.numero != '' && rowData.numero != undefined) particella = rowData.numero;

    if (foglio == '' || particella == '') return;

    if ($('#layer-' + comune + foglio + 'utm button.checkbox[value="' + comune + foglio + 'utm"]').hasClass('checked')) {
        console.log('Il layer è già selezionato.');
    } else {
        $('#layer-' + comune + foglio + 'utm button.checkbox[value="' + comune + foglio + 'utm"]').click();
    }

    const foglioSelect = $('#locate-layer-' + comune + '_catasto-FOGLIO');
    const foglioInput = foglioSelect.next('.custom-combobox').find('input');
    const foglioValue = rowData.foglio;

    foglioSelect.val(foglioValue);
    foglioInput.val(foglioSelect.find('option:selected').text());
    // foglioSelect.trigger('change');

    setTimeout(() => {
        const particellaSelect = $('#locate-layer-' + comune + '_catasto');
        const particellaInput = particellaSelect.next('.custom-combobox').find('input');
        
        const particellaText = particella; // La particella che vuoi selezionare (in questo caso "25")
    
        // Trova tutte le opzioni con il testo corrispondente a particellaText
        console.log(particellaSelect)
        const particellaOptions = particellaSelect.find('option').filter(function () {
            return $(this).text() === particellaText;
        });
    
        if (particellaOptions.length > 0) {
            particellaOptions.each(function() {
                const particellaValue = $(this).val();
                const particellaLabel = $(this).text();
                
                // Seleziona ogni particella trovata
                particellaSelect.val(particellaValue);
                particellaInput.val(particellaLabel);
                particellaSelect.trigger('change');
                
                console.log('Particella selezionata: ' + particellaLabel);
            });
        } else {
            console.log('Nessuna particella trovata con il testo: ' + particellaText);
        }
    }, 2500);
}