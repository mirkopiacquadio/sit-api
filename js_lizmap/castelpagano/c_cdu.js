var comuneUtente = '';
var uiu = [];
var html_cdu = '<div id="gsv-monter-cdu"></div><div id="gsv-monter-cdu-result"></div>';
if ($('#info-user-login').length > 0) {
    lizMap.addDock(
        'cdu',
        'Certificato di destinazione urbanistica',
        'minidock',
        html_cdu,
        'icon-edit'
    );
}

function generatePopupBody2() {
    var html = '';
    html += '<div class="container">';
    html += '<form method="GET" action="https://sitmonter.it/api/generaCDU" target="_blank" >';
    html += '<input type="hidden" name="code_comune" value="' + comuneUtente + '" >';
    html += '<div class="row">';
    html += '<div class="span8">';
    html += '<fieldset>';
    html += '<legend>Dati</legend>';
    html += '<div class="row">';
    html += '<div class="span3">';
    html += '<label>Protocollo numero</label>';
    html += '<input style="width: 100%" type="text" name="cduprot" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Data</label>';
    html += '<input style="width: 100%" type="date" name="cdudata" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Protocollo richiesta</label>';
    html += '<input style="width: 100%" type="text" name="cduprotric" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Data richiesta</label>';
    html += '<input style="width: 100%" type="date" name="cdudataric" />';
    html += '</div>';
    html += '</div>';
    html += '</fieldset>';
    
    html += '</div>';
    html += '<div class="span8">';
    html += '<fieldset>';
    html += '<legend>Dati del richiedente</legend>';
    html += '<div class="row">';
    html += '<div class="span3">';
    html += '<label>Titolo</label>';
    html += '<select style="width: 100%" name="cdutitolo">';
    html += '<option value="Sig.">Sig.</option>';
    html += '<option value="Sig.ra">Sig.ra</option>';
    html += '</select>';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>In qualità di</label>';
    html += '<select style="width: 100%" name="cduqualita">';
    html += '<option value="Proprietà">Proprietà</option>';
    html += '<option value="Delegato">Delegato</option>';
    html += '</select>';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Cognome</label>';
    html += '<input style="width: 100%" type="text" name="cducgn" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Nome</label>';
    html += '<input style="width: 100%" type="text" name="cdunm" />';
    html += '</div>';
    /*html += '<div class="span3">';
    html += '<label>Genere</label>';
    html += '<input style="width: 100%" type="text" />';
    html += '</div>';*/
    html += '<div class="span3">';
    html += '<label>Data di nascita</label>';
    html += '<input style="width: 100%" type="date" name="cdudatan" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Luogo di nascita</label>';
    html += '<input style="width: 100%" type="text" name="cduluogo" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Provincia di nascita</label>';
    html += '<input style="width: 100%" type="text" name="cduprovn" />';
    html += '</div>';


    html += '<div class="span3">';
    html += '<label>Codice Fiscale</label>';
    html += '<input style="width: 100%" type="text" name="cducf" />';
    html += '</div>';
    html += '</div>';
    html += '</fieldset>';
    html += '</div>';
    html += '<div class="span8">';
    html += '<fieldset>';
    html += '<div class="row">';
    html += '<div class="span3">';
    html += '<label>Residente in via</label>';
    html += '<input style="width: 100%" type="text" name="cduvia" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Città</label>';
    html += '<input style="width: 100%" type="text" name="cducitta" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Numero</label>';
    html += '<input style="width: 100%" type="text" name="cdunum" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Provicia</label>';
    html += '<input style="width: 100%" type="text" name="cduprovv" />';
    html += '</div>';
    html += '<div class="span3">';
    html += '<label>Cap</label>';
    html += '<input style="width: 100%" type="text" name="cducap" />';
    html += '</div>';
    html += '</div>';
    html += '</fieldset>';
    html += '</div>';
    html += '</div>';
    html += '<div class="row">';
    html += '<div class="span8">';
    html += '<fieldset>';
    html += '<legend>Dati UIU</legend>';
    html += '<div class="row">';
    html += '<div class="span1">';
    html += '<label>Foglio</label>';
    html += '<input id="fogliocdu" style="width: 100%" type="text" />';
    html += '</div>';
    html += '<div class="span1">';
    html += '<label>Particella</label>';
    html += '<input id="particellacdu" style="width: 100%" type="text" />';
    html += '</div>';
    html += '<div class="span1">';
    html += '<label>Sub</label>';
    html += '<input id="subcdu" style="width: 100%" type="text" />';
    html += '</div>';
    html += '<div class="span2">';
    html += '<a href="javascript:void(0)" onclick="addInTable()" class="btn btn-primary" style="width: 80%; margin-top: 24px">Aggiungi</a>';
    html += '</div>';
    html += '</div>';
    html += '<div class="row">';
    html += '<div class="span4">';
    html += '<table class="table table-striped">';
    html += '<thead>';
    html += '<tr>';
    html += '<th>Foglio</th>';
    html += '<th>Particella</th>';
    html += '<th>Sub</th>';
    html += '<th>Tipologia</th>';
    html += '<th></th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody id="tableBodyCdu">';
    html += '</tbody></table></div></div></fieldset></div>';
    html += '<div class="span8">';
    html += '<fieldset>';
    html += '<legend>Piani da includere</legend>';

    /**
     * SEZIONE PER RINOMINARE I PIANI DEL POPUP CDU
     */

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="prgurbutm">PRG';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="pianorecuperourbutm">Piano di Recupero';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="pianoespansioneurbutm">Piano di Espansione';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="psaiurbutm">PSAI';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="viurbutm">Vincolo Idrogeologico';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="sicfiumeurbutm">SIC - Fiume';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="sicboscourbutm">SIC - Bosco';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="usiciviciurbutm">USI CIVICI';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="vincoliopelegisurbutm">VINCOLI OPE LEGIS - Fiumi';
    html += '</label>';

    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="piano[]" value="pipurbutm">PIP';
    html += '</label>';

    html += '<a href="javascript:void(0)" onclick="selectAllPiani()" class="btn btn-secondary" style="width: 28%; margin-top: 24px">Includi tutti i piani</a>';
    html += '</fieldset>';
    html += '</div>';
    html += '<div class="span8">';
    html += '<fieldset>';
    html += '<legend>Formato numerico</legend>';
    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="cdusetmq" value="1">visualizza metri quadrati';
    html += '</label>';
    html += '<label class="checkbox">';
    html += '<input type="checkbox" name="cdusetperc" value="1">visualizza percentuali';
    html += '</label>';
    html += '<span class="label">Cifre decimali visualizzabili</span>';
    html += '<label class="radio">';
    html += '<input type="radio" name="cifdecvisu" value="2">1234,12 (due cifre decimali)';
    html += '</label>';
    html += '<label class="radio">';
    html += '<input type="radio" name="cifdecvisu" value="1">1234,1 (una cifra decimale)';
    html += '</label>';
    html += '<label class="radio">';
    html += '<input type="radio" name="cifdecvisu" value="0">1234 (nessuna cifra decimale)';
    html += '</label>';    
    html += '<label class="checkbox" style="margin-top: 20px;">';
    html += '<input type="checkbox" value="1" name="cdusetapprox">approssima ultima cifra';
    html += '</label>';
    html += '</fieldset>';
    html += '</div>';
    html += '</div>';
    html += '<input type="hidden" name="uiu" id="cduUid">';
    html += '<div class="row">';
    html += '<div class="span3">';
    html += '<button type="submit" target="_blank" class="btn btn-success" style="width: 80%; margin-top: 24px">Genera documento CDU</a>';
    html += '</div>';
    html += '<div class="span2">';
    html += '<a href="javascript:void(0)" onclick="resetData()" class="btn btn-secondary" style="width: 80%; margin-top: 24px">Cancella dati inseriti</a>';
    html += '</div>';
    html += '</form>';
    html += '</div>';
    html += '</div>';

    return html;
}

lizMap.events.on({
    minidockopened: function (e) {
        if (e.id == 'cdu') {
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
                height = (parseFloat(sidemenuStyles.height) * 45 / 100);
                width = 700;
            }

            var monterPop = document.getElementById('cdu');
            var mini_dock = document.getElementById('mini-dock');
            var mini_docks_cl = document.getElementsByClassName('mini-dock-close');

            monterPop.style.width = width + 'px';
            monterPop.style.height = height + 'px';

            mini_dock.style.maxWidth = width + 'px';
            mini_dock.style.maxHeight = height + 'px';

            for (var i = 0; i < mini_docks_cl.length; i++) {
                mini_docks_cl[i].style.marginRight = '15px';
            }

            uiu = [];
            var res = generatePopupBody2();
            $('#gsv-monter-cdu').append(res);
        }
    },
    minidockclosed: function (e) {
        if (e.id == 'cdu') {
            //Facciamo qualcosa in chiusura
            $('#gsv-monter-cdu').empty();
            $('#gsv-monter-cdu-result').empty();
        }
    }
});

function addInTable(js_foglio = '', js_particella = '') {

    var foglio = js_foglio;
    if (foglio == '') foglio = document.getElementById('fogliocdu').value;
    var particella = js_particella;
    if (particella == '') particella = document.getElementById('particellacdu').value;

    var sub = document.getElementById('subcdu').value;

    if (foglio && particella) {

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
                var particelleSoppresse = []; // Array per le particelle non inserite

                // Itera su tutti gli elementi restituiti dall'API
                if(data[0].length>0) {
                    data[0].forEach(function (item) {
                        // Se catqua è 'soppresso', salta questa particella e aggiungila all'array particelleSoppresse
                        if (item.catqua === 'soppresso') {
                            particelleSoppresse.push({
                                foglio: item.foglio,
                                particella: item.numero,
                                sub: item.sub ? item.sub : ''
                            });
                            return; // Salta il resto del ciclo per questa particella
                        }

                        // Estrai i dati necessari dalla risposta
                        var foglio = item.foglio;
                        var particella = item.numero;
                        var sub = item.sub ? item.sub : ''; // Se sub è null, lascialo vuoto
                        var tipologiaCatqua = item.tipologia + " " + item.catqua;

                        // Aggiungi i dati nella tabella
                        var tableBody = document.getElementById('tableBodyCdu');
                        var newRow = document.createElement('tr');

                        var foglioCell = document.createElement('td');
                        foglioCell.textContent = foglio;
                        newRow.appendChild(foglioCell);

                        var particellaCell = document.createElement('td');
                        particellaCell.textContent = particella;
                        newRow.appendChild(particellaCell);

                        var subCell = document.createElement('td');
                        subCell.textContent = sub;
                        newRow.appendChild(subCell);

                        var tipologiaCell = document.createElement('td');
                        tipologiaCell.textContent = tipologiaCatqua;
                        newRow.appendChild(tipologiaCell);

                        var actionCell = document.createElement('td');
                        var deleteButton = document.createElement('button');
                        deleteButton.textContent = 'Elimina';
                        deleteButton.className = 'btn btn-danger btn-sm text-right';
                        deleteButton.onclick = function () {
                            deleteRowCdu(this);
                        };
                        actionCell.appendChild(deleteButton);
                        newRow.appendChild(actionCell);

                        tableBody.appendChild(newRow);

                        // Aggiorna l'array uiu con i dati aggiunti
                        uiu.push({ fg: foglio, plla: particella, sb: sub });
                        document.getElementById('cduUid').value = JSON.stringify(uiu);
                    });
                }

                // Se ci sono particelle soppresse, mostra un alert con l'elenco
                if (particelleSoppresse.length > 0) {
                    var alertMessage = "Ci sono particelle non inserite perchè soppresse!\nElenco:\n";
                    particelleSoppresse.forEach(function (item) {
                        alertMessage += `Foglio: ${item.foglio}, Particella: ${item.particella}, Sub: ${item.sub || 'N/A'}\n`;
                    });
                    alert(alertMessage);
                }

                // Pulisci i campi di input
                document.getElementById('fogliocdu').value = '';
                document.getElementById('particellacdu').value = '';
                document.getElementById('subcdu').value = '';
            },
            error: function (error) {
                console.error("Errore nella chiamata AJAX", error);
                alert('Errore durante il recupero dei dati!');
            }
        });

    } else {
        alert('Foglio e Particella devono essere compilati!');
    }
}


function addInTable_(js_foglio = '', js_particella = '') {

    var foglio = js_foglio;
    if (foglio == '') foglio = document.getElementById('fogliocdu').value;
    var particella = js_particella;
    if (particella == '') particella = document.getElementById('particellacdu').value;

    var sub = document.getElementById('subcdu').value;

    if (foglio && particella) {

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
                console.log(data)    
            }
        });

        return;


        var tableBody = document.getElementById('tableBodyCdu');
        var newRow = document.createElement('tr');

        var foglioCell = document.createElement('td');
        foglioCell.textContent = foglio;
        newRow.appendChild(foglioCell);

        var particellaCell = document.createElement('td');
        particellaCell.textContent = particella;
        newRow.appendChild(particellaCell);

        var subCell = document.createElement('td');
        subCell.textContent = sub;
        newRow.appendChild(subCell);

        var actionCell = document.createElement('td');
        var deleteButton = document.createElement('button');
        deleteButton.textContent = 'Elimina';
        deleteButton.className = 'btn btn-danger btn-sm text-right';
        deleteButton.onclick = function () {
            deleteRowCdu(this);
        };
        actionCell.appendChild(deleteButton);
        newRow.appendChild(actionCell);

        tableBody.appendChild(newRow);

        uiu.push({ fg: foglio, plla: particella, sb: sub });
        document.getElementById('cduUid').value = JSON.stringify(uiu);

        // Clear the input fields
        document.getElementById('fogliocdu').value = '';
        document.getElementById('particellacdu').value = '';
        document.getElementById('subcdu').value = '';
    } else {
        alert('Foglio e Particella devono essere compilati!');
    }
}

function deleteRowCdu(button) {
    var row = button.parentNode.parentNode;
    var tableBody = row.parentNode;
    var foglio = row.cells[0].textContent;
    var particella = row.cells[1].textContent;
    var sub = row.cells[2].textContent;

    // Rimuovi l'elemento dall'array uiu
    uiu = uiu.filter(item => item.fg !== foglio || item.plla !== particella || item.sb !== sub);

    // Aggiorna il campo nascosto con il JSON dell'array uiu
    document.getElementById('cduUid').value = JSON.stringify(uiu);

    // Rimuovi la riga dalla tabella
    tableBody.removeChild(row);
}

function selectAllPiani() {
    var checkboxes = document.querySelectorAll('input[name="piano[]"]');
    checkboxes.forEach(function (checkbox) {
        checkbox.checked = true;
    });
}

function openCdu(a, b) {
        /**
     * SEZIONE PER RINOMINARE I PIANI DEL POPUP CDU
     */
    var params = {
        code_comune: comuneUtente,
        cduprot: '',
        cdudata: '',
        cduprotric: '',
        cdudataric: '',
        cducgn: '',
        cdunm: '',
        cdudatan: '',
        cduluogo: '',
        cduprovn: '',
        cduvia: '',
        cducitta: '',
        cdunum: '',
        cduprovv: '',
        cducap: '',
        piano: JSON.stringify(['prgurbutm', 'pianorecuperourbutm', 'pianoespansioneurbutm', 'psaiurbutm', 'viurbutm', 'sicfiumeurbutm', 'sicboscourbutm', 'usiciviciurbutm', 'vincoliopelegisurbutm', 'pipurbutm']),
        uiu: JSON.stringify([{ "fg": a, "plla": b, "sb": "" }]),
        cdusetmq: 1,
        cdusetperc: 1
    };

    var queryString = Object.keys(params)
        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
        .join('&');

    var modalContent = `
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3 id="myModalLabel">Analisi intersezioni</h3>
    </div>
    <div class="modal-body">
        <h5>L'immobile identificato in NCT - NCEU al Fg. ${a} p.lla ${b}, di superficie pari a <span id="sup_cdu_modal"></span>, ricade nelle seguenti
        destinazioni urbanistiche:</h5>


        
        <div id="tabella_riassunto">
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-info" href="javascript:;" onclick="generaCdu('${a}', '${b}')">Genera CDU</button>
        <a href="https://sitmonter.it/api/print_cdu_from_modal?${queryString}" target="_blank" class="btn btn-success" type="submit">Stampa</a>
        <button class="btn" data-dismiss="modal" aria-hidden="true">Chiudi</button>
    </div>`;
    $('#lizmap-modal').html(modalContent);


        /**
     * SEZIONE PER RINOMINARE I PIANI DEL POPUP CDU
     */
    var params = {
        code_comune: comuneUtente,
        cduprot: '',
        cdudata: '',
        cduprotric: '',
        cdudataric: '',
        cducgn: '',
        cdunm: '',
        cdudatan: '',
        cduluogo: '',
        cduprovn: '',
        cduvia: '',
        cducitta: '',
        cdunum: '',
        cduprovv: '',
        cducap: '',
        piano: ['prgurbutm', 'pianorecuperourbutm', 'pianoespansioneurbutm', 'psaiurbutm', 'viurbutm', 'sicfiumeurbutm', 'sicboscourbutm', 'usiciviciurbutm', 'vincoliopelegisurbutm', 'pipurbutm'],
        uiu: JSON.stringify([{ "fg": a, "plla": b, "sb": "" }]),
        cdusetmq: 1,
        cdusetperc: 1
    };

    $.ajax({
        url: 'https://sitmonter.it/api/generaCDUHtml', // Modifica con l'URL del tuo endpoint API
        method: 'GET',
        data: params,
        success: function (data) {
            // Crea il contenuto HTML per il modal
            $('#tabella_riassunto').html(data.vista);
            $('#sup_cdu_modal').html(data.mq);

            // Mostra il modal
            $('#lizmap-modal').modal('show');
        },
        error: function (xhr, status, error) {
            console.error('Errore durante la chiamata AJAX:', error);
        }
    });

}

function generaCdu(foglio, particella) {
    $('#lizmap-modal').modal('hide');
    document.getElementById('button-cdu').click();
    addInTable(foglio, particella);
}

function openNta(val, dir) {
    var params = {
        code_comune: comuneUtente,
        val: val,
        dir: dir
    };

    var queryString = Object.keys(params)
        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
        .join('&');


    var modalContent = `
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3 id="myModalLabel">NTA</h3>
    </div>
    <div class="modal-body">
        <div id="tab_nta">
        </div>
    </div>
    <div class="modal-footer">
        <a href="https://sitmonter.it/api/print_nta_from_modal?${queryString}" target="_blank" class="btn btn-success" type="submit">Stampa</a>
        <button class="btn" data-dismiss="modal" aria-hidden="true">Chiudi</button>
    </div>`;
    $('#lizmap-modal').html(modalContent);

    $.ajax({
        url: 'https://sitmonter.it/api/nta', // Modifica con l'URL del tuo endpoint API
        method: 'GET',
        data: params,
        success: function (data) {
            // Crea il contenuto HTML per il modal
            if (data.content) $('#tab_nta').html(data.content);


            // Mostra il modal
            $('#lizmap-modal').modal('show');
        },
        error: function (xhr, status, error) {
            console.error('Errore durante la chiamata AJAX:', error);
        }
    });
}

$(document).ready(function () {
    if ($('#info-user-login').length > 0) {

        var user = $('#info-user-login').text();
        var params = {
            code_comune: comuneUtente,
            user: user
        };

        var queryString = Object.keys(params)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
            .join('&');

        $.ajax({
            url: 'https://sitmonter.it/admin.php/auth/account/getGroupsUser?' + queryString, // Modifica con l'URL del tuo endpoint API
            method: 'GET',
            success: function (data) {
                comuneUtente = data[1];
            },
            error: function (xhr, status, error) {
                console.error('Errore durante la chiamata AJAX:', error);
            }
        });
    }
});

function resetData() {
    uiu = [];
    document.getElementById('cduUid').value = JSON.stringify(uiu);
    var form = document.getElementById('form_cdu');
    if (form) {
        form.reset();
    }
}