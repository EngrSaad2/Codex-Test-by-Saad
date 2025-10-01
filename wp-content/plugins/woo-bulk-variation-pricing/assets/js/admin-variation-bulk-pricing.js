jQuery( function ( $ ) {
    function getRowTemplate( loop ) {
        return (
            '<tr class="bulk-pricing-row">' +
            '<td><input type="number" min="1" step="1" name="bulk_pricing_qty[' + loop + '][]" placeholder="' + woo_bulk_variation_pricing_admin.i18n_qty + '" /></td>' +
            '<td><input type="text" name="bulk_pricing_price[' + loop + '][]" placeholder="' + woo_bulk_variation_pricing_admin.i18n_price + '" /></td>' +
            '<td class="actions"><button type="button" class="button remove-bulk-pricing-tier">&times;</button></td>' +
            '</tr>'
        );
    }

    $( document ).on( 'click', '.add-bulk-pricing-tier', function () {
        var loop = $( this ).data( 'loop' );
        var table = $( this ).closest( '.bulk-pricing-wrapper' ).find( '.bulk-pricing-table tbody' );
        table.append( getRowTemplate( loop ) );
    } );

    $( document ).on( 'click', '.remove-bulk-pricing-tier', function () {
        var row = $( this ).closest( 'tr' );
        var tbody = row.closest( 'tbody' );
        if ( tbody.find( 'tr' ).length > 1 ) {
            row.remove();
        } else {
            row.find( 'input' ).val( '' );
        }
    } );
} );
