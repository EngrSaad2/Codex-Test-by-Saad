<?php
/**
 * Plugin Name: WooCommerce Bulk Variation Pricing
 * Description: Adds bulk pricing tiers for WooCommerce variable product variations.
 * Version: 1.0.0
 * Author: ChatGPT
 * License: GPL2
 * Text Domain: woo-bulk-variation-pricing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class.
 */
class Woo_Bulk_Variation_Pricing {
    const META_KEY = '_woo_bulk_pricing_rules';

    /**
     * Bootstraps the plugin hooks.
     */
    public static function init() {
        // Admin UI hooks.
        add_action( 'woocommerce_product_after_variable_attributes', [ __CLASS__, 'render_variation_bulk_pricing_fields' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ __CLASS__, 'save_variation_bulk_pricing_fields' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );

        // Frontend hooks.
        add_filter( 'woocommerce_available_variation', [ __CLASS__, 'append_variation_bulk_pricing_data' ], 10, 3 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'apply_bulk_pricing_to_cart' ], 20, 1 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'store_bulk_price_on_order_item' ], 10, 4 );
    }

    /**
     * Enqueue admin assets for variation bulk pricing UI.
     */
    public static function enqueue_admin_assets( $hook_suffix ) {
        if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
            return;
        }

        $screen = get_current_screen();
        if ( empty( $screen ) || 'product' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_style(
            'woo-bulk-variation-pricing-admin',
            plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'woo-bulk-variation-pricing-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin-variation-bulk-pricing.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script(
            'woo-bulk-variation-pricing-admin',
            'woo_bulk_variation_pricing_admin',
            [
                'i18n_qty'   => esc_html__( 'Quantity', 'woo-bulk-variation-pricing' ),
                'i18n_price' => esc_html__( 'Price', 'woo-bulk-variation-pricing' ),
            ]
        );
    }

    /**
     * Render bulk pricing fields within the variation settings.
     */
    public static function render_variation_bulk_pricing_fields( $loop, $variation_data, $variation ) {
        $rules = self::get_bulk_pricing_rules( $variation->ID );
        ?>
        <div class="bulk-pricing-wrapper">
            <p class="form-row form-row-full">
                <label><?php esc_html_e( 'Bulk Pricing Tiers', 'woo-bulk-variation-pricing' ); ?></label>
            </p>
            <table class="widefat bulk-pricing-table" data-loop="<?php echo esc_attr( $loop ); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Minimum Quantity', 'woo-bulk-variation-pricing' ); ?></th>
                        <th><?php esc_html_e( 'Price', 'woo-bulk-variation-pricing' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'woo-bulk-variation-pricing' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ( ! empty( $rules ) ) {
                    foreach ( $rules as $index => $rule ) {
                        self::render_bulk_pricing_row( $loop, $index, $rule['quantity'], $rule['price'] );
                    }
                } else {
                    self::render_bulk_pricing_row( $loop, 0, '', '' );
                }
                ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button add-bulk-pricing-tier" data-loop="<?php echo esc_attr( $loop ); ?>">
                    <?php esc_html_e( 'Add Tier', 'woo-bulk-variation-pricing' ); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Output an individual bulk pricing row.
     */
    protected static function render_bulk_pricing_row( $loop, $index, $quantity, $price ) {
        ?>
        <tr class="bulk-pricing-row">
            <td>
                <input type="number" min="1" step="1" name="bulk_pricing_qty[<?php echo esc_attr( $loop ); ?>][]" value="<?php echo esc_attr( $quantity ); ?>" placeholder="<?php esc_attr_e( 'Quantity', 'woo-bulk-variation-pricing' ); ?>" />
            </td>
            <td>
                <input type="text" name="bulk_pricing_price[<?php echo esc_attr( $loop ); ?>][]" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php esc_attr_e( 'Price', 'woo-bulk-variation-pricing' ); ?>" />
            </td>
            <td class="actions">
                <button type="button" class="button remove-bulk-pricing-tier">&times;</button>
            </td>
        </tr>
        <?php
    }

    /**
     * Save variation bulk pricing data.
     */
    public static function save_variation_bulk_pricing_fields( $variation_id, $i ) {
        $quantities = isset( $_POST['bulk_pricing_qty'][ $i ] ) ? (array) $_POST['bulk_pricing_qty'][ $i ] : [];
        $prices     = isset( $_POST['bulk_pricing_price'][ $i ] ) ? (array) $_POST['bulk_pricing_price'][ $i ] : [];

        $rules = [];

        foreach ( $quantities as $index => $quantity ) {
            $quantity = absint( $quantity );
            $price    = isset( $prices[ $index ] ) ? wc_clean( wp_unslash( $prices[ $index ] ) ) : '';

            if ( $quantity > 0 && '' !== $price ) {
                $rules[] = [
                    'quantity' => $quantity,
                    'price'    => wc_format_decimal( $price ),
                ];
            }
        }

        usort(
            $rules,
            function ( $a, $b ) {
                return $a['quantity'] <=> $b['quantity'];
            }
        );

        if ( ! empty( $rules ) ) {
            update_post_meta( $variation_id, self::META_KEY, $rules );
        } else {
            delete_post_meta( $variation_id, self::META_KEY );
        }
    }

    /**
     * Retrieve bulk pricing rules for a variation.
     */
    public static function get_bulk_pricing_rules( $variation_id ) {
        $rules = get_post_meta( $variation_id, self::META_KEY, true );

        if ( empty( $rules ) || ! is_array( $rules ) ) {
            return [];
        }

        // Ensure data integrity.
        $normalized = [];
        foreach ( $rules as $rule ) {
            if ( isset( $rule['quantity'], $rule['price'] ) ) {
                $normalized[] = [
                    'quantity' => absint( $rule['quantity'] ),
                    'price'    => wc_format_decimal( $rule['price'] ),
                ];
            }
        }

        usort(
            $normalized,
            function ( $a, $b ) {
                return $a['quantity'] <=> $b['quantity'];
            }
        );

        return $normalized;
    }

    /**
     * Append bulk pricing data to the variation object passed to the frontend.
     */
    public static function append_variation_bulk_pricing_data( $variation_data, $product, $variation ) {
        $rules = self::get_bulk_pricing_rules( $variation->get_id() );
        if ( ! empty( $rules ) ) {
            $variation_data['bulk_pricing_rules'] = array_map(
                function ( $rule ) {
                    return [
                        'quantity' => (int) $rule['quantity'],
                        'price'    => (float) $rule['price'],
                    ];
                },
                $rules
            );
        }

        return $variation_data;
    }

    /**
     * Enqueue frontend script to dynamically update displayed price.
     */
    public static function enqueue_frontend_assets() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        wp_enqueue_script(
            'woo-bulk-variation-pricing-frontend',
            plugin_dir_url( __FILE__ ) . 'assets/js/frontend-bulk-pricing.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script(
            'woo-bulk-variation-pricing-frontend',
            'WooBulkVariationPricing',
            [
                'currency_format_num_decimals' => wc_get_price_decimals(),
                'currency_format_symbol'       => get_woocommerce_currency_symbol(),
                'currency_format_decimal_sep'  => wc_get_price_decimal_separator(),
                'currency_format_thousand_sep' => wc_get_price_thousand_separator(),
                'currency_format'              => get_woocommerce_price_format(),
            ]
        );
    }

    /**
     * Get the correct price for a given quantity.
     */
    public static function get_price_for_quantity( $quantity, $rules, $fallback_price ) {
        $price = wc_format_decimal( $fallback_price );

        foreach ( $rules as $rule ) {
            if ( $quantity >= (int) $rule['quantity'] ) {
                $price = wc_format_decimal( $rule['price'] );
            }
        }

        return (float) $price;
    }

    /**
     * Apply bulk pricing to cart items based on quantity.
     */
    public static function apply_bulk_pricing_to_cart( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( empty( $cart->get_cart() ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['variation_id'] ) ) {
                continue;
            }

            $variation_id = $cart_item['variation_id'];
            $rules        = self::get_bulk_pricing_rules( $variation_id );

            if ( empty( $rules ) ) {
                continue;
            }

            $quantity = $cart_item['quantity'];
            $product  = $cart_item['data'];

            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            if ( ! isset( $cart_item['_woo_bulk_original_price'] ) ) {
                $cart_item['_woo_bulk_original_price'] = (float) $product->get_price();
            }

            $base_price = (float) $cart_item['_woo_bulk_original_price'];
            $bulk_price = (float) self::get_price_for_quantity( $quantity, $rules, $base_price );

            if ( $bulk_price <= 0 ) {
                continue;
            }

            $product->set_price( $bulk_price );

            if ( $bulk_price !== (float) $base_price ) {
                $cart_item['data']->update_meta_data( '_woo_bulk_price_applied', $bulk_price );
            } else {
                $cart_item['data']->delete_meta_data( '_woo_bulk_price_applied' );
            }
        }
    }

    /**
     * Store the applied bulk price on the order item for reference.
     */
    public static function store_bulk_price_on_order_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['data'] ) ) {
            $bulk_price = (float) $values['data']->get_meta( '_woo_bulk_price_applied' );

            if ( $bulk_price > 0 ) {
                $item->add_meta_data( __( 'Bulk price applied', 'woo-bulk-variation-pricing' ), wc_price( $bulk_price ) );
            }
        }
    }
}

Woo_Bulk_Variation_Pricing::init();
