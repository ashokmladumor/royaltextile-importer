jQuery(document).ready(function(){

	jQuery('.rt-mapping-cat-list').select2({
        minimumResultsForSearch: Infinity,
        theme: 'default rt-mapping-cat-list',
        placeholder: "Select a Category"
    });

    jQuery('#rt-mapping--categories-table,#rt-mapping--subgroup-table').fancyTable({
      sortColumn: 0, // column number for initial sorting
      sortOrder: 'descending', // 'desc', 'descending', 'asc', 'ascending', -1 (descending) and 1 (ascending)
      sortable: true,
      pagination: true, // default: false
      searchable: false,
      globalSearch: false,
      perPage: 20,
      onInit:function(e){
        var foot = '<tr><td></td><td style="text-align: right;"><button type="submit" class="button button-primary button-large save-mapped-data">Save</button></td></tr>';
        jQuery(this).find('tfoot').prepend(foot);
        jQuery('.rt-mapping-cat-list').select2({
            minimumResultsForSearch: Infinity,
            theme: 'default rt-mapping-cat-list',
            placeholder: "Select a Category"
        });
        if (typeof sessionStorage.getItem("mapped_page") !== "undefined" && sessionStorage.getItem("mapped_page") != '') {
            jQuery('.rt-mapping-table td.pag a[data-n='+sessionStorage.getItem("mapped_page")+']').click();
        }
        jQuery('.rt-mapping-table td.pag a').on('click', function() {
            sessionStorage.setItem("mapped_page", jQuery(this).attr('data-n'));
        });
      }
    });

    var getUrlParameter = function getUrlParameter(sParam) {
        var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;

        for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');

            if (sParameterName[0] === sParam) {
                return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
        }
    };
    if (getUrlParameter('page') != 'rt-mapping-tool') {
        sessionStorage.setItem("mapped_page", '1');
    }
});

function rt_import_product(page = 0, status = 'running') {
    jQuery('.rt-importer-btn').addClass('active');
    jQuery('.rt-importer-btn').attr('disabled', true);
    console.log(jQuery(this));
    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: script_data.ajax_url,
        data: {
            action: 'rt_import_woo_product',
            page_num: page,
            status: status,
            nonce: script_data.nonce
        },
        success: function(response) {
            console.log(response);
            jQuery('.rt-importer-btn').removeClass('active');
            jQuery('.rt-importer-btn').attr('disabled', false);
            jQuery('.rt-progress').show();
            jQuery('.rt-progress-bar').css({width: parseInt(response.percent) + '%'});
            var text = parseInt(response.percent) + '%';
            //' Skiped: ' + parseInt(response.skip_products) + ' Imported: ' + parseInt(response.total)
            jQuery('.rt-progress-bar').text(text);

            if (response.status != 'running') {
                return;
            }
            rt_import_product(response.page, response.status);
        }
    });
}

function save_mapped_data(e, elm) {
    e.preventDefault();
    var $form = jQuery(elm).parents('form');
    jQuery(elm).attr('disabled', true);
    jQuery('.rt-mapping-content').append('<div class="show-loader"></div>');

    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: script_data.ajax_url,
        data: {
            action: 'rt_save_mapped_data',
            data: $form.serialize()
        },
        success: function(response) {
            console.log(response);
            if (response.status) {
                jQuery(elm).attr('disabled', false);
                jQuery('.rt-mapping-content').find('.show-loader').remove();
            }
            if (response.import && response.old_status == 'empty') {
                location.reload();
            }
        }
    });
}