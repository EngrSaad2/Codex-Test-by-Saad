(function ( $ ) {
    function formatPrice( price ) {
        if ( typeof accounting !== 'undefined' && accounting.formatMoney ) {
            return accounting.formatMoney(
                price,
                WooBulkVariationPricing.currency_format_symbol,
                WooBulkVariationPricing.currency_format_num_decimals,
                WooBulkVariationPricing.currency_format_thousand_sep,
                WooBulkVariationPricing.currency_format_decimal_sep,
                WooBulkVariationPricing.currency_format
            );
        }

        return WooBulkVariationPricing.currency_format_symbol + parseFloat( price ).toFixed( WooBulkVariationPricing.currency_format_num_decimals );
    }

    function getBulkPrice( qty, rules, basePrice ) {
        var price = basePrice;

        if ( ! rules || ! rules.length ) {
            return price;
        }

        $.each( rules, function ( index, rule ) {
            if ( qty >= parseInt( rule.quantity, 10 ) ) {
                price = parseFloat( rule.price );
            }
        } );

        return price;
    }

    function updateDisplayPrice( form, price ) {
        var priceHtml = '<span class="price">' + formatPrice( price ) + '</span>';
        form.find( '.single_variation .price' ).html( priceHtml );
    }

    $( function () {
        $( '.variations_form' ).each( function () {
            var $form = $( this );
            var $qtyInput = $form.closest( 'form.cart' ).find( 'input.qty' );

            if ( ! $qtyInput.length ) {
                return;
            }

            $form.on( 'found_variation', function ( event, variation ) {
                var basePrice = parseFloat( variation.display_price );
                var rules = variation.bulk_pricing_rules || [];

                $form.data( 'wooBulkPricingBaseHtml', variation.price_html );
                $form.data( 'wooBulkPricingBasePrice', basePrice );
                $form.data( 'wooBulkPricingRules', rules );

                var qty = parseInt( $qtyInput.val(), 10 ) || 1;
                var bulkPrice = getBulkPrice( qty, rules, basePrice );

                if ( ! isNaN( bulkPrice ) && parseFloat( bulkPrice ) !== parseFloat( basePrice ) ) {
                    updateDisplayPrice( $form, bulkPrice );
                } else {
                    $form.find( '.single_variation .price' ).html( variation.price_html );
                }
            } );

            $qtyInput.on( 'change keyup', function () {
                var qty = parseInt( $( this ).val(), 10 );
                if ( ! qty || qty < 1 ) {
                    qty = 1;
                }

                var rules = $form.data( 'wooBulkPricingRules' );
                var basePrice = parseFloat( $form.data( 'wooBulkPricingBasePrice' ) );
                var baseHtml = $form.data( 'wooBulkPricingBaseHtml' );

                if ( ! rules || ! rules.length ) {
                    if ( baseHtml ) {
                        $form.find( '.single_variation .price' ).html( baseHtml );
                    }
                    return;
                }

                if ( isNaN( basePrice ) ) {
                    return;
                }

                var bulkPrice = getBulkPrice( qty, rules, basePrice );

                if ( ! isNaN( bulkPrice ) && parseFloat( bulkPrice ) !== parseFloat( basePrice ) ) {
                    updateDisplayPrice( $form, bulkPrice );
                } else if ( baseHtml ) {
                    $form.find( '.single_variation .price' ).html( baseHtml );
                }
            } );
        } );
    } );
})( jQuery );
