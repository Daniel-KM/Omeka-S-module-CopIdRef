$(document).ready(function() {

    const basePath = window.location.pathname.replace(/\/admin\/.*/, '');

    // Adapté de http://documentation.abes.fr/aideidrefdeveloppeur/index.html#installation
    // TODO Async.

    const crossDomain = 'https://www.idref.fr';
    const iframeUrl = 'https://www.idref.fr/autorites/autorites.html';
    const proxy = '';
    const idAutorite = '';
    var remoteClientExist = false;
    var oFrame;
    var idrefinit = false;

    // Les correspondances idref/omeka sont dans le module (data/mappings/mappings.json).
    // Ceci est utilisé en cas de problème avec idref.
    const defaultMapping = [
        {
          "from": {
            "type": "data",
            "path": "e",
          },
          "to": {
            "type": "property",
            "data": {
              "property": "dcterms:title",
              "property_id": 1,
            }
          }
        },
        {
          "from": {
            "type": "data",
            "path": "c",
          },
          "to": {
            "type": "property",
            "data": {
              "property": "dcterms:alternative",
              "property_id": 17,
            }
          }
        },
        {
          "from": {
            "type": "data",
            "path": "b",
          },
          "to": {
            "type": "property",
            "format": "concat",
            "args": {
              "0": "https://www.idref.fr/",
              "1": "__value__"
            },
            "data": {
              "property": "bibo:identifier",
              "property_id": 98,
              "type": "uri"
            }
          }
        },
        {
          "from": {
            "type": "xpath",
            "path": "/record/datafield[@tag='101']/subfield[@code='a'][1]"
          },
          "to": {
            "type": "property",
            "format": "concat",
            "args": {
              "0": "http://id.loc.gov/vocabulary/iso639-2/",
              "1": "__value__"
            },
            "data": {
              "property": "dcterms:language",
              "property_id": 12,
              "type": "valuesuggest:lc:iso6392"
            }
          }
        }
    ];

    const serializer = {

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
        if (!initClient()) {
           return;
        };

        var cleanInput = function (v) {
            return v ? v.replace(/'/g, '\\\'') : null;
        }

        index1 = cleanInput(index1);
        index1Value = cleanInput(index1Value);
        index2 = cleanInput(index2);
        index2Value = cleanInput(index2Value);
        filtre1 = cleanInput(filtre1);
        filtre1Value = cleanInput(filtre1Value);
        filtre2 = cleanInput(filtre2);
        filtre2Value = cleanInput(filtre2Value);
        zones = cleanInput(zones);

        index1 = index1 === null ? '' : index1;
        index1Value = index1Value === null ? '' : index1Value;

        oFrame = document.getElementById('popupFrame');
        if (!idrefinit) {
            // TODO IdrefInit est toujours false ?
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
            alert(Omeka.jsTranslate('oFrame.contentWindow Failed?') + ' ' + e);
        }
    }

    function initClient() {
        // Requiert jQuery-ui.
        // $('#popupContainer').draggable();

        showPopWin('', $(window).width() * 0.89, $(window).height() * 0.74, null);
        if (remoteClientExist) {
            return true;
        }

        var processResultat = function(e) {
            if (e.origin !== crossDomain) {
                alert(Omeka.jsTranslate('Warning: cross-domain request!'));
                return false;
            }
            traiteResultat(e);
            return true
        };

        remoteClientExist = true;
        document.addEventListener
            ? window.addEventListener('message', processResultat)
            : window.attachEvent('onmessage', processResultat);

        return true;
    }

    function traiteResultat(e) {
        const data = serializer.parse(e.data);

        if (!data) {
            alert(Omeka.jsTranslate('Data from endpoint are empty.'));
            return;
        }

        if (data.quit === 'ok') {
            return;
        }

        const resourceType = typeResource();
        if (!resourceType || !resourceType.length) {
            alert(Omeka.jsTranslate('Unable to determine the resource type.'));
            return;
        }

        if (data['g'] == null || data['b'] == null || data['f'] == null) {
            alert(Omeka.jsTranslate('Data are missing or incomplete.'));
            console.log(data);
            return;
        }

        hidePopWin(null);


        const apiResourceType = apiTypeResource(resourceType);

        // Crée une nouvelle resource à partir des données.
        const resource = idrefRecordToResource(data, apiResourceType);

        const url = basePath + '/api-proxy/' + apiResourceType;
        $.ajax({
                type: 'POST',
                url: url,
                data: JSON.stringify(resource),
                async: false,
                contentType: 'application/json; charset=utf-8',
                dataType: 'json',
            })
            .done(function(apiResource) {
                console.log(Omeka.jsTranslate('Resource created from api successfully.'));
                // Attach the new resource to the current resource.
                // The trigger requires a button "#select-item a", and data in ".resource details".
                const resourceDetails = '<div class="resource-details" style="display:none;"></div>';
                const valueObj = {
                    '@id': location.protocol + '//' + location.hostname + basePath + '/api/' + apiResourceType + '/' + apiResource['o:id'],
                    'type': 'resource',
                    'value_resource_id': apiResource['o:id'],
                    'value_resource_name': apiResourceType,
                    'url': basePath + '/admin/' + resourceType + '/' + apiResource['o:id'],
                    'display_title': apiResource['o:title'] ? apiResource['o:title'] : Omeka.jsTranslate('[Untitled]'),
                    'thumbnail_url': apiResource['thumbnail_display_urls']['square'],
                    // 'thumbnail_title': 'title.jpeg',
                    // 'thumbnail_type': 'image/jpeg',
                }
                $('#ws-type').after(resourceDetails);
                $('.resource-details').data('resource-values', valueObj);
                $('#select-item a').click();
            })
            .fail(function(jqXHR) {
                let val = '';
                if (jqXHR.responseJSON && jqXHR.responseJSON.errors) {
                    for (let k in jqXHR.responseJSON.errors) {
                        if (typeof jqXHR.responseJSON.errors[k] === 'string' || jqXHR.responseJSON.errors[k] instanceof String) {
                            val += "\n" + v;
                        } else {
                            for (let v of jqXHR.responseJSON.errors[k]) {
                                val += "\n" + v;
                            }
                        }
                    }
                } else {
                    console.log(jqXHR);
                }
                alert(Omeka.jsTranslate('Failed creating resource from api.') + val);
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

    function idrefRecordToResource(record, apiResourceType, mapping) {
        // http://documentation.abes.fr/aideidrefdeveloppeur/index.html#filtres
        const idrefTypes = {
            'a': 'Personne',
            'b': 'Collectivité',
            's': 'Congrès',
            'c': 'Nom géographique',
            // d est utilisé 2 fois.
            'd': 'Marque',
            'd': 'Famille',
            'f': 'Titre uniforme',
            'h': 'Auteur Titre',
            'r': 'Rameau',
            't': 'FMeSH',
            'u': 'Forme Rameau',
            'v': 'Genre Rameau',
            'w': 'RCR',
       };
       const idrefType = idrefTypes[record['g']] ? idrefTypes[record['g']] : 'Autre';

        const rdfTypes = {
            'items': 'o:Item',
            'item_sets': 'o:ItemSet',
            'media': 'o:Media',
            'annotations': 'o:Annotation',
        };
        apiResourceType = rdfTypes[apiResourceType] ? apiResourceType : 'items';

        if (!mapping) {
            const url = basePath + '/modules/CopIdRef/data/mappings/mappings.json';
            $.ajax({url: url, async: false})
                .done(function(data) {
                    mapping = data[idrefType] ? data[idrefType] : defaultMapping;
                })
                .fail(function(jqXHR) {
                    alert(Omeka.jsTranslate('Failed to load mapping. Creating a default resource.'));
                    console.log(jqXHR);
                    mapping = defaultMapping;
                });
        }

        var resource = {
            '@context': location.protocol + '//' + location.hostname + basePath + '/api-context',
            '@id': null,
            '@type': rdfTypes[apiResourceType],
            'o:id' : null,
            'o:is_public': false,
            // Filled by the controller.
            'o:owner': null,
            'o:title': record['e'],
        };

        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(record['f'], 'application/xml');

        var from;
        var to;
        var val;
        var value;
        var xpath;
        var xpathResult;
        var propertyValue;
        var property;
        var mappingLength = Object.keys(mapping).length;
        for (let i = 0; i < mappingLength; i++) {
            to = mapping[i]['to'];
            if (typeof to.data === 'undefined') {
                continue;
            }

            value = null;
            from = mapping[i]['from'];
            if (from.type === 'static') {
                // Check the mapping.
                if (to.data['o:resource_class'] && (!to.data['o:resource_class']['o:id'] || to.data['o:resource_class']['o:id'] > 105) && to.data['o:resource_class']['o:term']) {
                    to.data['o:resource_class']['o:id'] = getResourceId('resource_classes', {'term': to.data['o:resource_class']['o:term']});
                }
                if (to.data['o:resource_template'] && !to.data['o:resource_template']['o:id'] && to.data['o:resource_template']['o:label']) {
                    to.data['o:resource_template']['o:id'] = getResourceId('resource_templates', {'label': to.data['o:resource_template']['o:label']});
                }
                if (to.data['o:resource_class']['o:id'] && !to.data['o:resource_class']['o:id']) {
                    alert(Omeka.jsTranslate('Mapping for resource class is incorrect. Skipped.') + ' (' + to.data['o:resource_class']['o:term'] + ')');
                    to.data['o:resource_class'] = null;
                }
                if (to.data['o:resource_template']['o:id'] && !to.data['o:resource_template']['o:id']) {
                    alert(Omeka.jsTranslate('Mapping for resource template  is incorrect. Skipped.') + ' (' + to.data['o:resource_template']['o:term'] + ')');
                    to.data['o:resource_template'] = null;
                }
                value = to.data;
            } else if (from.type === 'data') {
                value = record[from.path] ? record[from.path] : null;
            } else if (from.type === 'xpath') {
                xpath = from.path;
                xpathResult = xmlDoc.evaluate(xpath, xmlDoc, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null);
                value = xpathResult.singleNodeValue ? xpathResult.singleNodeValue.textContent : null;
                value = value && value.trim().length ? value.trim() : null;
            }
            if (typeof value === 'undefined'
                || value === null
                || (typeof value === 'object' && !Object.keys(value).length)
                || (typeof value !== 'object' && !value.length)
            ) {
                continue;
            }

            if (!to.data.type) {
                to.data.type = 'literal';
            }

            if (to.format) {
                if (to.format === 'concat') {
                    val = '';
                    try {
                        for (let k in to.args) {
                            val += to.args[k] === '__value__' ? value : to.args[k];
                        }
                    } catch (ex) {
                        for (let v of to.args) {
                            val += v === '__value__' ? value : v;
                        }
                    }
                    value = val;
                } else if (to.format === 'table') {
                    if (to.args[value]) {
                        value = to.args[value];
                    } else {
                        to.data.type = 'literal';
                    }
                } else if (to.format === 'number_to_date') {
                    if (value.match(/^-?[\d]+$/gm)) {
                        value = (value.substring(0, 4) + '-' + value.substring(4, 6) + '-' + value.substring(6, 8)).replace(/-+$/, '');
                    } else {
                        to.data.type = 'literal';
                    }
                }
            }

            if (to.type === 'static') {
                for (const key in to.data) {
                    resource[key] = to.data[key];
                }
            } else if (to.type === 'property') {
                property = to.data.property;
                if (!property) {
                    continue;
                }
                // Check the property.
                if (!to.data.property_id || to.data.property_id > 184) {
                    to.data.property_id = getResourceId('properties', {'term': property});
                    if (!to.data.property_id) {
                        alert(Omeka.jsTranslate('Mapping for property is incorrect. Skipped.') + ' (' + property + ')');
                        continue;
                    }
                }
                if (!to.data.type) {
                    to.data.type === 'literal';
                }
                if (!resource[property]) {
                    resource[property] = [];
                }
                propertyValue = {
                    'type': to.data.type,
                    'property_id': to.data.property_id,
                    'is_public': typeof to.data.is_public === 'undefined' || to.data.is_public ? true : false,
                    '@language': typeof to.data['@language'] === 'undefined' || to.data['@language'].trim() === '' ? null : to.data['@language'].trim(),
                };
                if (to.data.type === 'uri'
                    || to.data.type.substring(0, 12) === 'valuesuggest'
                ) {
                    propertyValue['@id'] = value;
                    propertyValue['o:label'] = typeof to.data['o:label'] === 'undefined' || to.data['o:label'].trim() === '' ? null : to.data['o:label'].trim();
                } else if (to.data.type.substring(0, 8) === 'resource') {
                    propertyValue['value_resource_id'] = value;
                    propertyValue['value_resource_name'] = typeof to.data.value_resource_name === 'undefined' ? null : to.data.value_resource_name.trim();
                } else {
                    propertyValue['@value'] = value;
                }
                resource[property].push(propertyValue);
            }
        }

        return resource;
    }

    /**
     * Determine the resource type (routing).
     *
     * @todo  Determine the resource type in a cleaner way (cf. fix #omeka/omeka-s/1655).
     */
    function typeResource() {
        const resourceType = $('#select-resource.sidebar').find('#sidebar-resource-search').data('search-url');
        return resourceType.substring(resourceType.lastIndexOf('/admin/') + 7, resourceType.lastIndexOf('/sidebar-select'));
    }

    /**
     * Determine the resource type (api) from the route resource type.
     */
    function apiTypeResource(resourceType) {
        const resourceTypes = {
            'item': 'items',
            'item-set': 'item_sets',
            'media': 'media',
            'annotation': 'annotations',
        };
        return resourceTypes[resourceType] ? resourceTypes[resourceType] : null;
    }

    /**
     * Get the resource id via the api.
     *
     * @return ?int
     */
    function getResourceId(apiResourceType, data) {
        var result;
        $.ajax({
                url: basePath + '/api/' + apiResourceType,
                data: data,
                async: false,
            })
            .done(function(apiResource) {
                result = apiResource[0]['o:id'];
            })
            .fail(function(jqXHR) {
                result = null;
            });
        return result;
    }

    // Append the button to create a new resource.
    $(document).on('o:sidebar-content-loaded', 'body.sidebar-open', function(e) {
        if (typeof availableIdRefResources === 'undefined' || !availableIdRefResources.length) {
            return;
        }
        const sidebar = $('#select-resource.sidebar');
        if (sidebar.find('.quick-add-webservice').length || !sidebar.find('#sidebar-resource-search').length) {
            return;
        }
        const resourceType = typeResource();
        if (!resourceType || !resourceType.length) {
            return;
        }

        const iconResourceType = resourceType === 'media' ? 'media' : resourceType + 's';
        var select = $(`
    <select id="ws-type" class="o-icon-${iconResourceType} button quick-add-webservice submodal">
        <option value="">Ressource via IdRef</option>
    </select>`);
        availableIdRefResources.forEach((availableIdRefResource) => {
            select.append($('<option>', {
                value: availableIdRefResource,
                text: availableIdRefResource,
            }));
        });
        const button = $(`<div data-data-type="resource:${resourceType}"></div>`);
        button.append(select);
        sidebar.find('.search-nav').after(button);
    });

    $(document).on('change', '.quick-add-webservice', function (e) {
        e.preventDefault();
        // La requête peut avoir plusieurs valeurs et filtres, ceux de la page idref.
        const type = $(this).val();
        if (type === '') {
            return;
        }
        const query = $(this).closest('#item-results').find('#resource-list-search').val();
        const newRecord = null;
        envoiClient(type, query, '', '', '', '', '', '', newRecord);
    });

});
