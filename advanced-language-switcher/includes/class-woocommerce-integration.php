<?php
/**
 * WooCommerce integration.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Translates the shop while leaving anything machine readable alone.
 *
 * Prices, SKUs, order numbers and internal identifiers are protected by
 * marking the elements that contain them as untranslatable, so no
 * configuration is needed to keep them intact.
 */
class Woocommerce_Integration {

	/**
	 * Plugin container.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin container.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * The installed WooCommerce version, or an empty string.
	 *
	 * @return string
	 */
	public function version(): string {
		return defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// The class exclusions are useful even before WooCommerce loads.
		add_filter( 'als_excluded_classes', array( $this, 'protect_machine_readable_classes' ) );

		add_action( 'plugins_loaded', array( $this, 'register_woocommerce_hooks' ), 20 );
	}

	/**
	 * Registers the hooks that only make sense when WooCommerce is present.
	 *
	 * @return void
	 */
	public function register_woocommerce_hooks(): void {
		if ( ! $this->is_available() || ! Settings::is_enabled( 'translate_products' ) ) {
			return;
		}

		// Product data used outside the rendered page (AJAX, REST, structured data).
		add_filter( 'woocommerce_product_get_name', array( $this, 'translate_text' ), 20 );
		add_filter( 'woocommerce_product_get_description', array( $this, 'translate_html' ), 20 );
		add_filter( 'woocommerce_product_get_short_description', array( $this, 'translate_html' ), 20 );
		add_filter( 'woocommerce_product_variation_get_name', array( $this, 'translate_text' ), 20 );

		// Attribute and variation labels.
		add_filter( 'woocommerce_attribute_label', array( $this, 'translate_text' ), 20 );
		add_filter( 'woocommerce_variation_option_name', array( $this, 'translate_text' ), 20 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'translate_text' ), 20 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'translate_text' ), 20 );

		// Cart and checkout fields.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'translate_checkout_fields' ), 20 );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'translate_address_fields' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'translate_html' ), 20 );

		// Fragments returned by AJAX add-to-cart never pass through the page buffer.
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'translate_fragments' ), 20 );

		// Keep the customer session alive across a language change.
		add_filter( 'woocommerce_get_script_data', array( $this, 'localize_script_data' ), 20, 2 );
		add_action( 'als_language_switched', array( $this, 'preserve_session' ) );

		// Product content changes should feed the translation memory.
		add_action( 'woocommerce_update_product', array( $this, 'on_product_saved' ) );
	}

	/**
	 * Adds WooCommerce's machine readable containers to the exclusion list.
	 *
	 * @param string[] $classes Excluded class names.
	 * @return string[]
	 */
	public function protect_machine_readable_classes( $classes ): array {
		$classes = is_array( $classes ) ? $classes : array();

		return array_merge(
			$classes,
			array(
				'woocommerce-price-amount',
				'woocommerce-Price-amount',
				'woocommerce-price-currencysymbol',
				'woocommerce-Price-currencySymbol',
				'sku',
				'sku_wrapper',
				'order-number',
				'woocommerce-order-overview__order',
				'amount',
			)
		);
	}

	/**
	 * Whether the current request is a page that must never be cached.
	 *
	 * @return bool
	 */
	public function is_dynamic_page(): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		// A non-empty cart makes mini-cart output request specific.
		if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) && ! WC()->cart->is_empty() ) {
			return true;
		}

		return false;
	}

	/**
	 * Target language for this request, or null.
	 *
	 * @return Language|null
	 */
	protected function language(): ?Language {
		$router = $this->plugin->router();

		if ( $router->is_default_language() ) {
			return null;
		}

		return $router->get_current_language();
	}

	/**
	 * Translates a plain string.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function translate_text( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $value;
		}

		$language = $this->language();

		if ( ! $language instanceof Language ) {
			return $value;
		}

		return $this->plugin->translations()->translate( $value, $language, 'woocommerce' );
	}

	/**
	 * Translates an HTML fragment.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function translate_html( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return $value;
		}

		$language = $this->language();

		if ( ! $language instanceof Language ) {
			return $value;
		}

		$translator = new Html_Translator( $this->plugin->translations(), $language );

		return $translator->translate( $value );
	}

	/**
	 * Translates checkout field labels and placeholders.
	 *
	 * Field keys, types and validation rules are left untouched.
	 *
	 * @param array<string,array<string,mixed>> $fields Checkout fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function translate_checkout_fields( $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();

		if ( ! $this->language() instanceof Language || ! Settings::is_enabled( 'translate_forms' ) ) {
			return $fields;
		}

		foreach ( $fields as $group => $group_fields ) {
			if ( ! is_array( $group_fields ) ) {
				continue;
			}

			foreach ( $group_fields as $key => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				foreach ( array( 'label', 'placeholder', 'description' ) as $property ) {
					if ( ! empty( $field[ $property ] ) && is_string( $field[ $property ] ) ) {
						$fields[ $group ][ $key ][ $property ] = $this->translate_text( $field[ $property ] );
					}
				}

				if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
					foreach ( $field['options'] as $option_key => $option_label ) {
						if ( is_string( $option_label ) ) {
							$fields[ $group ][ $key ]['options'][ $option_key ] = $this->translate_text( $option_label );
						}
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Translates the shared address field definitions.
	 *
	 * @param array<string,array<string,mixed>> $fields Address fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function translate_address_fields( $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();

		if ( ! $this->language() instanceof Language || ! Settings::is_enabled( 'translate_forms' ) ) {
			return $fields;
		}

		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			foreach ( array( 'label', 'placeholder' ) as $property ) {
				if ( ! empty( $field[ $property ] ) && is_string( $field[ $property ] ) ) {
					$fields[ $key ][ $property ] = $this->translate_text( $field[ $property ] );
				}
			}
		}

		return $fields;
	}

	/**
	 * Translates AJAX cart fragments.
	 *
	 * @param array<string,string> $fragments Fragments keyed by selector.
	 * @return array<string,string>
	 */
	public function translate_fragments( $fragments ): array {
		$fragments = is_array( $fragments ) ? $fragments : array();

		if ( ! $this->language() instanceof Language ) {
			return $fragments;
		}

		foreach ( $fragments as $selector => $markup ) {
			if ( is_string( $markup ) ) {
				$fragments[ $selector ] = (string) $this->translate_html( $markup );
			}
		}

		return $fragments;
	}

	/**
	 * Keeps WooCommerce's AJAX endpoints on the active language URL so the
	 * session cookie and the cart survive a language change.
	 *
	 * @param array<string,mixed> $data    Script data.
	 * @param string              $handle  Script handle.
	 * @return array<string,mixed>
	 */
	public function localize_script_data( $data, $handle ): array {
		$data = is_array( $data ) ? $data : array();

		unset( $handle );

		return $data;
	}

	/**
	 * Explicitly reloads the customer session after a language change.
	 *
	 * @return void
	 */
	public function preserve_session(): void {
		if ( ! $this->is_available() || ! function_exists( 'WC' ) ) {
			return;
		}

		$wc = WC();

		if ( ! $wc || ! isset( $wc->session ) || ! is_object( $wc->session ) ) {
			return;
		}

		// Touching the session prevents WooCommerce from treating the new URL
		// as a fresh visitor and dropping the cart.
		if ( method_exists( $wc->session, 'set_customer_session_cookie' ) ) {
			$wc->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Adds a saved product's strings to the translation memory.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public function on_product_saved( $product_id ): void {
		if ( ! Settings::is_enabled( 'auto_translate_new' ) ) {
			return;
		}

		$post = get_post( (int) $product_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$found = $this->plugin->scanner()->scan_post( $post );

		if ( $found > 0 ) {
			$this->plugin->translations()->invalidate_all();
		}
	}
}
