<?php

/**
 * Plugin Name: Change Payment Description for Woocommerce
 * Plugin URI: https://poly-res.com/plugins/change-payment-desciption-for-woocommerce/
 * Description: Change Payment Description
 * Version: 1.0.1
 * Author: polyres
 * Author URI: https://poly-res.com/
 * Text Domain: change-payment-description-for-woocommerce
 * Domain Path: /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires WP:       4.8
 * Requires PHP:      5.3
 * Tested up to: 5.7.2
 * WC requires at least: 3.4.0
 * WC tested up to: 5.3.0
 *
 * @link      https://poly-res.com
 * @author    Ulf Schoenefeld
 * @license   GPL-2.0+
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

register_activation_hook( __FILE__, array( 'ChangePaymentDescription', 'install' ) );
register_uninstall_hook( __FILE__,  array( 'ChangePaymentDescription', 'uninstall' ) );


define( 'CHANGE_PAYMENT_DESCRIPTION_MIN_WC_VER', '5.0.1' );

/**
 * WooCommerce fallback notice.
 *
 * @since 1.0.0
 */
function change_desc_payment_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Change Payment Description requires WooCommerce to be installed and active. You can download %s here.', 'change-payment-description-for-woocommerce' ), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce fallback notice.
 *
 * @since 1.0.0
 */
function change_desc_payment_missing_gateway_stripe_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Change Payment Description requires WooCommerce Stripe Gateway to be installed and active. You can download %s here.', 'change-payment-description-for-woocommerce' ), '<a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank">WooCommerce Stripe Gateway</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 1.0.0
 */
function change_desc_payment_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Change Payment Description requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'change-payment-description-for-woocommerce' ), CHANGE_PAYMENT_DESCRIPTION_MIN_WC_VER, WC_VERSION ) . '</strong></p></div>';
}


class ChangePaymentDescription {

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 * @access  public
	 * @action change_payment_description_for_woocommerce_init
	 */
	public function __construct() {
		$plugin = plugin_basename( __FILE__ );
		add_filter( 'woocommerce_settings_tabs_array',                 array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_change_payment_description_for_woocommerce',          array( $this, 'settings_tab' ) );
		add_action( 'woocommerce_update_options_change_payment_description_for_woocommerce',         array( $this, 'update_settings' ) );
		add_filter( "plugin_action_links_$plugin",                     array( $this, 'plugin_add_settings_link' ) );
		add_action( 'init',                                           array( $this, 'load_plugin_textdomain') );
		add_filter( 'wc_stripe_generate_payment_request', 	       array( $this, 'filter_wc_stripe_payment_descriptionmod'), 3, 10 );

		do_action( 'change_payment_description_for_woocommerce_init' );
	}

	/**
	 * Load the translation
	 *
	 * @since    1.0.0
	 * @access  public
	 * @filter plugin_locale
	 */
	 public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'change-payment-description-for-woocommerce' );

		load_textdomain( 'change-payment-description-for-woocommerce', trailingslashit( WP_LANG_DIR ) . 'change-payment-description-for-woocommerce/change-payment-description-for-woocommerce-' . $locale . '.mo' );
		load_plugin_textdomain( 'change-payment-description-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add a link to plugin settings to the plugin list
	 *
	 * @since    1.0.0
	 * @access  public
	 */
	public function plugin_add_settings_link( $links ) {
		$settings_link = '<a href="'. admin_url( 'admin.php?page=wc-settings&tab=change_payment_description_for_woocommerce' ) . '">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Add a new settings tab to woocommerce/settings
	 *
	 * @since    1.0.0
	 * @access  public
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['change_payment_description_for_woocommerce'] = _x( 'Payment Description', 'WooCommerce Settings Tab', 'change-payment-description-for-woocommerce' );
		return $settings_tabs;
	}

	/**
	 * @ince    1.0.0
	 * @access  public
	 */
	public  function settings_tab() {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * @since    1.0.0
	 * @access  public
	 */
	function update_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Define the settings for this plugin
	 *
	 * @since    1.0.0
	 * @access  public
	 * @filters change-payment-description-for-woocommerce_settings
	 */
	public function get_settings() {
		$settings = array(
			'section_title' => array(
				'name'     => __( 'Change Payment Description', 'change-payment-description-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'cpd_section_title',
			),
			'payment_description' => array(
				'name' => __( 'Your own payment description for Stripe', 'change-payment-description-for-woocommerce' ),
				'type' => 'text',
				'desc' => __( 'Display an own Payment Description in the Stripe Backend.', 'change-payment-description-for-woocommerce' ),
				'id'   => 'cpd_payment_description',
			),
		);

		$settings = apply_filters( 'change-payment-description-for-woocommerce_settings_extend', $settings );

		$settings['section_end'] = array(
			'type' => 'sectionend',
			'id'   => 'cpd_section_end',
		);

		return apply_filters( 'change-payment-description-for-woocommerce_settings', $settings );
	}

	/**
	 * Filter Change Description Stripe
	 *
	 * @since    1.0.0
	 * @access  public
	 */
	function filter_wc_stripe_payment_descriptionmod( $post_data, $order, $prepared_source ) {
		$payment_description = get_option( 'cpd_payment_description' );
		if ( !empty( $payment_description ) ) {
    		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'change-payment-description-for-woocommerce' ), $payment_description, $order->get_order_number() );
	}
		return $post_data;
	}

	/**
	 * Check dependencies and Setup Database on activating the plugin
	 *
	 * @since    1.0.0
	 * @access  public
	 */
	static public function install() {
        if ( ! in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            add_action('admin_notices', 'cpd_admin_notice_woocommerce_error');
            // Deactivate the plugin
            deactivate_plugins( plugin_basename( __FILE__ ) );
            return;
        }
        /**
         * @todo hier später alle unterstützten Paymentsysteme abfragen und Meldung ausgeben das min eines davon aktiv sein muss
         */
        if ( ! in_array('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            add_action('admin_notices', 'cpd_admin_notice_stripe_error');
            // Deactivate the plugin
            deactivate_plugins(__FILE__);
            return;
        }

        /**
         * create default option
         */
        if ( false === get_option( 'cpd_payment_description' ) ) {
            add_option('cpd_payment_description', '');
        }
    }

    /**
     * Display "WooCommerce ist noch active" Error
     */
    static public function cpd_admin_notice_woocommerce_error() {
        /* translators: 1. URL link. */
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . sprintf( esc_html__( 'Stripe requires WooCommerce to be installed and active. You can download %s here.', 'change-payment-description-for-woocommerce' ), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
    }

    /**
     * Display "Stripe ist noch active" Error
     */
    static public function cpd_admin_notice_stripe_error() {
        $class = 'notice notice-error is-dismissible';
        $message = __( 'Stripe is not Active.', 'change-payment-description-for-woocommerce' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }

    /**
	 * Cleanup Database on deleting the plugin
	 *
	 * @since    1.0.0
	 * @access  public
	 */
	static public function uninstall() {
		delete_option( 'cpd_payment_description' );
	}
}


function change_desc_payment_gateway_stripe_init() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'change_desc_payment_missing_wc_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, CHANGE_PAYMENT_DESCRIPTION_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'change_desc_payment_wc_not_supported' );
		return;
	}

	if ( ! defined( 'WC_STRIPE_VERSION' ) ) {
		add_action( 'admin_notices', 'change_desc_payment_missing_gateway_stripe_notice' );
		return;
	}

	$polyresChangePaymentDescription = new ChangePaymentDescription();
}
add_action( 'plugins_loaded', 'change_desc_payment_gateway_stripe_init' );
