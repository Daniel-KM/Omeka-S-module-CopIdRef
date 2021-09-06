$(document).ready(function() {

    // Adapté de http://documentation.abes.fr/aideidrefdeveloppeur/index.html#installation

    var crossDomain = 'https://www.idref.fr';
    var iframeUrl = 'https://www.idref.fr/autorites/autorites.html';
    var proxy;
    var idAutorite = '';
    var remoteClientExist = false;
    var oFrame;
    var idrefinit = false;

    var serializer = {

        stringify: function(data) {
            var message = '';
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    message += key + '=' + escape(data[key]) + '&';
                }
            }
            return message.substring(0, message.length - 1);
        },

        parse: function(message) {
            var data = {};
            var d = message.split('&');
            var pair, key, value;
            for (var i = 0, len = d.length; i < len; i++) {
                pair = d[i];
                key = pair.substring(0, pair.indexOf('='));
                value = pair.substring(key.length + 1);
                data[key] = unescape(value);
            }
            return data;
        }
    };

    /**
     * Envoie une requête à IdRef.
     *
     * @see http://documentation.abes.fr/aideidrefdeveloppeur/index.html#ConstructionRequete
     */
    function envoiClient(index1, index1Value, index2, index2Value, filtre1, filtre1Value, filtre2, filtre2Value, zones) {

        index1Value = index1Value.replace(/'/g, '\\\'');
        // a commenter pour votre application
        $('#resultat').html('');
        $('#resultat').hide();

        if (initClient() == 0) {
        };

        oFrame = document.getElementById('popupFrame');
        if (!idrefinit) {
            oFrame.contentWindow.postMessage(serializer.stringify({Init: 'true'}), '*');
            idrefinit = false;
        }
        try {
            if (zones != null) {
                eval('oFrame.contentWindow.postMessage(serializer.stringify({Index1:\'' + index1 + '\',Index1Value:\'' + index1Value + '\',Index2:\'' + index2 + '\',Index2Value:\'' + index2Value + '\',Filtre1:\'' + filtre1 + "/" + filtre1Value + '\',Filtre2:\'' + filtre2 + "/" + filtre2Value + '\',' + zones + ',fromApp:\'Omeka\',AutoClick:\'false\'}), "*"); ');
            } else if (filtre2 != null) {
                eval('oFrame.contentWindow.postMessage(serializer.stringify({Index1:\'' + index1 + '\',Index1Value:\'' + index1Value + '\',Index2:\'' + index2 + '\',Index2Value:\'' + index2Value + '\',Filtre1:\'' + filtre1 + "/" + filtre1Value + '\',Filtre2:\'' + filtre2 + "/" + filtre2Value + '\',fromApp:\'Omeka\',AutoClick:\'false\'}), "*"); ');
            } else if (filtre1 != null) {
                eval('oFrame.contentWindow.postMessage(serializer.stringify({Index1:\'' + index1 + '\',Index1Value:\'' + index1Value + '\',Index2:\'' + index2 + '\',Index2Value:\'' + index2Value + '\',Filtre1:\'' + filtre1 + "/" + filtre1Value + '\',fromApp:\'Omeka\',AutoClick:\'false\'}), "*"); ');
            } else if (index2 != null) {
                eval('oFrame.contentWindow.postMessage(serializer.stringify({Index1:\'' + index1 + '\',Index1Value:\'' + index1Value + '\',Index2:\'' + index2 + '\',Index2Value:\'' + index2Value + '\',fromApp:\'Omeka\',AutoClick:\'false\'}), "*"); ');
            } else {
                eval('oFrame.contentWindow.postMessage(serializer.stringify({Index1:\'' + index1 + '\',Index1Value:\'' + index1Value + '\',fromApp:\'Omeka\',AutoClick:\'false\'}), "*"); ');
            }
        } catch(e) {
            alert('oFrame.contentWindow Failed? ' + e);
        }
    }

    function initClient() {
        // Requiert jQuery-ui.
        // $('#popupContainer').draggable();

        showPopWin('', $(window).width() * 0.89, $(window).height() * 0.74, null);
        if (remoteClientExist) {
            return 0;
        }

        remoteClientExist = true;
        if (document.addEventListener) {
            window.addEventListener('message', function(e) {
                if (e.origin !== crossDomain) {
                    alert('Warning: cross domain issue!');
                    return 0;
                }
                traiteResultat(e);
            });
        } else {
            window.attachEvent('onmessage', function(e) {
                if (e.origin !== crossDomain) {
                    alert('Warning: cross domain issue!');
                    return 0;
                }
                traiteResultat(e);
            });
        }
        return 0;
    }

    function traiteResultat(e) {
        var data = serializer.parse(e.data);

        if (data['g'] != null) {
            var resHtml = '<ul>';
            resHtml += '<li>data["a"] : ' + data['a'] + '</li>';
            resHtml += '<li>data["b"] : ' + data['b'] + '</li>';
            resHtml += '<li>data["c"] : ' + data['c'] + '</li>';
            resHtml += '<li>data["d"] : ' + data['d'] + '</li>';
            resHtml += '<li>data["e"] : ' + data['e'] + '</li>';
            resHtml += '<li>data["f"] : ' + escapeHtml(data['f']) + '</li>';
            resHtml += '<li>data["g"] : ' + data['g'] + '</li>';
            resHtml += '</ul>';

            $('#resultat').html(resHtml);
            $('#resultat').show();
            hidePopWin(null);
        }
    }

    function escapeHtml(texte) {
        return texte
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/'/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Append the button to create a new resource.
    $(document).on('o:sidebar-content-loaded', 'body.sidebar-open', function(e) {
        var sidebar = $('#select-resource.sidebar');
        if (sidebar.find('.quick-add-webservice').length || !sidebar.find('#sidebar-resource-search').length) {
            return;
        }
        // TODO Determine the resource type in a cleaner way (cf. fix #omeka/omeka-s/1655).
        var resourceType = sidebar.find('#sidebar-resource-search').data('search-url');
        resourceType = resourceType.substring(resourceType.lastIndexOf('/admin/') + 7, resourceType.lastIndexOf('/sidebar-select'));
        if (!resourceType) {
            return;
        }
        var button = `<div data-data-type="resource:${resourceType}">
    <select id="ws-type" class="o-icon-${resourceType}s button quick-add-webservice submodal">
        <option value="">Nouvelle resource depuis IdRef</option>
        <option value="Nom de personne">Nom de personne</option>
        <option value="Nom de collectivité">Nom de collectivité</option>
        <option value="Nom commun">Nom commun</option>
        <option value="Nom géographique">Nom géographique</option>
        <option value="Famille">Famille</option>
        <option value="Titre">Titre</option>
        <option value="Auteur-Titre">Auteur-Titre</option>
        <option value="Nom de marque">Nom de marque</option>
        <option value="Ppn">Ppn</option>
        <option value="Rcr">Rcr</option>
        <option value="Tout">Tout</option>
    </select>
</div>
<div id="resultat"></div>
`;
        sidebar.find('.search-nav').after(button);
    });

    $(document).on('change', '.quick-add-webservice', function (e) {
        e.preventDefault();
        // La requête prut avoir plusieurs valeurs et filtres.
        var type = $(this).val();
        if (type === '') {
            return;
        }
        var query = $(this).closest('#item-results').find('#resource-list-search').val();
        var newRecord = null;
        envoiClient(type, query, '', '', '', '', '', '', newRecord);
    });

});
