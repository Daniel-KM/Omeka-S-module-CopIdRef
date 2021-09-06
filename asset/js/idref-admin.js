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
     * Envoie une requête à IdRef avec les paramètres correspondant à la page idref.
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
        const data = serializer.parse(e.data);

        if (!data) {
            alert(Omeka.jsTranslate('Data from endpoint are empty.'));
            return;
        }

        const resourceType = typeResource();
        if (!resourceType || !resourceType.length) {
            alert(Omeka.jsTranslate('Unable to determine the resource type.'));
            return;
        }

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

        if (data['g'] == null || data['b'] == null || data['f'] == null) {
            // TODO Vérifier pourquoi deux fois.
            // alert(Omeka.jsTranslate('Data are missing or incomplete.'));
            console.log(data);
            return;
        }

        const resourceTypes = {
            'item': 'items',
            'item-set': 'item_sets',
            'media': 'media',
            'annotation': 'annotations',
        };
        const apiResourceType = resourceTypes[resourceType] ? resourceTypes[resourceType] : 'items';

        // Crée une nouvelle resource à partir des données.
        const resource = recordToResource(resourceType, data);
        console.log(resource);

        const url = baseUrl + 'api-proxy/' + apiResourceType;
        $.ajax({
                type: 'POST',
                url: url,
                data: JSON.stringify(resource),
                async: false,
                contentType: 'application/json; charset=utf-8',
                dataType: 'json',
            })
            .done(function(apiResource) {
                console.log(Omeka.jsTranslate('Resource created from api.'));
                console.log(apiResource);
                // Attach the new resource to the current resource.
                // $('.value.selecting-resource');
                const valueObj = {
                    '@id': location.protocol + '//' + location.hostname + baseUrl + 'api/' + apiResourceType + '/' + apiResource['o:id'],
                    'type': 'resource',
                    'value_resource_id': apiResource['o:id'],
                    'value_resource_name': apiResourceType,
                    'url': baseUrl + 'admin/' + resourceType + '/' + apiResource['o:id'],
                    'display_title': data['o:title'] ? data['o:title'] : Omeka.jsTranslate('[Untitled]'),
                    'thumbnail_url': data['thumbnail_display_urls']['square'],
                    // 'thumbnail_title': 'title.jpeg',
                    // 'thumbnail_type': 'image/jpeg',
                }
                // The trigger requires a button "#select-item a", and data in ".resource details".
                var resourceDetails = '<div class="resource-details" style="display:none;"></div>';
                $('#ws-type').after(resourceDetails);
                $('.resource-details').data('resource-values', valueObj);
                $('#select-item a').click();
            })
            .fail(function(jqXHR) {
                alert(Omeka.jsTranslate('Failed creating resource from api.'));
                console.log(jqXHR);
            })
            .always(function () {
            });
    }

    function escapeHtml(texte) {
        return texte
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/'/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function recordToResource(resourceType, data) {
        const resourceTypes = {
            'item': 'o:Item',
            'item-set': 'o:ItemSet',
            'media': 'o:Media',
            'annotation': 'o:Annotation',
        };

        const mapping = [
            {
                'from': {
                    'xpath': '/record/datafield[@tag="103"]/subfield[@code="a"][1]'
                },
                'to': {
                    'property': 'bio:birth',
                    'property_id': 214,
                    'type': 'numeric:timestamp',
                    'format': 'number_to_isodate'
                },
            },
            {
                'from': {
                    'xpath': '/record/datafield[@tag="103"]/subfield[@code="b"][1]'
                },
                'to': {
                    'property': 'bio:death',
                    'property_id': 215,
                    'type': 'numeric:timestamp',
                    'format': 'number_to_isodate'
                },
            },
       ];

        var resource = {
            '@context': location.protocol + '//' + location.hostname + baseUrl + 'api-context',
            '@id': null,
            '@type': resourceTypes[resourceType] ? resourceTypes[resourceType] : 'o:Item',
            'o:id' : null,
            'o:is_public': false,
            // Filled by the controller.
            'o:owner': null,
            'o:title': data['e'],
            "o:resource_class": {
                "o:id": 94,
            },
            "o:resource_template": {
                "o:id": 38,
            },
            'foaf:name': [
                {
                    'type': 'literal',
                    'property_id': 138,
                    'is_public': true,
                    '@value': data['e'],
                }
            ],
            'foaf:familyName': [
                {
                    'type': 'literal',
                    'property_id': 145,
                    'is_public': true,
                    '@value': data['c'],
                }
            ],
            'foaf:givenName': [
                {
                    'type': 'literal',
                    'property_id': 141,
                    'is_public': true,
                    '@value': data['d'],
                }
            ],
            'bibo:uri': [
                {
                    'type': 'uri',
                    'property_id': 121,
                    'is_public': true,
                    '@value': 'https://www.idref.fr/' + data['b'],
                }
            ],
        };

        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(data['f'], 'application/xml');
        var value;
        var property;
        var map;
        var xpath;
        for (let i in mapping) {
            map = mapping[i];
            xpath = map['from']['xpath'];
            const xpathResult = xmlDoc.evaluate(xpath, xmlDoc, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null);
            value = xpathResult.singleNodeValue ? xpathResult.singleNodeValue.textContent : null;
            value = value ? value.trim() : null;
            if (value && value.length) {
                property = map['to']['property'];
                if (!resource[property]) {
                    resource[property] = [];
                }
                if (map['to']['format']) {
                    if (map['to']['format'] === 'number_to_date') {
                        value = (value.substring(0, 4) + '-' + value.substring(4, 6) + '-' + value.substring(6, 8)).replace(/-+$/, '');
                    }
                }
                resource[property].push({
                    'type': map['to']['type'] ? map['to']['type'] : 'literal',
                    'property_id': map['to']['property_id'],
                    'is_public': map['to']['is_public'] ? map['to']['is_public'] : true,
                    '@value': value,
                });
            }
        }

        return resource;
    }

    /**
     * Determine the resource type.
     *
     * @todo  Determine the resource type in a cleaner way (cf. fix #omeka/omeka-s/1655).
     */
    function typeResource() {
        var resourceType = $('#select-resource.sidebar').find('#sidebar-resource-search').data('search-url');
        return resourceType.substring(resourceType.lastIndexOf('/admin/') + 7, resourceType.lastIndexOf('/sidebar-select'));
    }

    // Append the button to create a new resource.
    $(document).on('o:sidebar-content-loaded', 'body.sidebar-open', function(e) {
        var sidebar = $('#select-resource.sidebar');
        if (sidebar.find('.quick-add-webservice').length || !sidebar.find('#sidebar-resource-search').length) {
            return;
        }
        var resourceType = typeResource();
        if (!resourceType || !resourceType.length) {
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
<!--
<div id="resultat"></div>
-->
`;
        sidebar.find('.search-nav').after(button);
    });

    $(document).on('change', '.quick-add-webservice', function (e) {
        e.preventDefault();
        // La requête prut avoir plusieurs valeurs et filtres, ceux de la page idref.
        var type = $(this).val();
        if (type === '') {
            return;
        }
        var query = $(this).closest('#item-results').find('#resource-list-search').val();
        var newRecord = null;
        envoiClient(type, query, '', '', '', '', '', '', newRecord);
    });

});
