$(document).ready(function() {

    var modal;
    // Append the button to create a new resource.
    $(document).on('o:sidebar-content-loaded', 'body.sidebar-open', function(e) {
        var sidebar = $('#select-resource.sidebar');
        if (sidebar.find('.quick-add-resource').length || !sidebar.find('#sidebar-resource-search').length) {
            return;
        }
        // TODO Determine the resource type in a cleaner way (cf. fix #omeka/omeka-s/1655).
        var resourceType = sidebar.find('#sidebar-resource-search').data('search-url');
        resourceType = resourceType.substring(resourceType.lastIndexOf('/admin/') + 7, resourceType.lastIndexOf('/sidebar-select'));
        if (!resourceType) {
            return;
        }
        var button = `<div data-data-type="resource:${resourceType}">
    <a class="o-icon-${resourceType}s button quick-add-idref" href="${baseUrl + 'admin/' + resourceType}/add?window=modal" target="_blank"> ${Omeka.jsTranslate('Créer via IdRef')}</a>
</div>`;
        sidebar.find('.search-nav').after(button)
    });

});
