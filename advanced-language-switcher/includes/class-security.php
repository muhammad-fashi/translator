<?php
/**
 * Capability and nonce helpers.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Single place where every permission decision is made.
 */
class Security {

	/**
	 * Capability required to manage languages, settings and API keys.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Capability required to edit translations.
	 *
	 * Editors can be granted this without gaining access to API keys.
	 */
	public const TRANSLATE_CAPABILITY = 'als_edit_translations';

	/**
	 * Nonce action used by every admin AJAX call.
	 */
	public const NONCE_ACTION = 'als_admin';

	/**
	 * Nonce action used by public front end calls.
	 */
	public const PUBLIC_NONCE_ACTION = 'als_public';

	/**
	 * Whether the current user may manage the plugin.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		/**
		 * Filters the management capability check.
		 *
		 * @param bool $allowed Whether the user may manage the plugin.
		 */
		return (bool) apply_filters( 'als_current_user_can_manage', current_user_can( self::CAPABILITY ) );
	}

	/**
	 * Whether the current user may edit translations.
	 *
	 * @return bool
	 */
	public static function can_translate(): bool {
		$allowed = current_user_can( self::CAPABILITY ) || current_user_can( self::TRANSLATE_CAPABILITY );

		/**
		 * Filters the translation editing capability check.
		 *
		 * @param bool $allowed Whether the user may edit translations.
		 */
		return (bool) apply_filters( 'als_current_user_can_translate', $allowed );
	}

	/**
	 * Verifies an admin request: nonce plus capability.
	 *
	 * @param string $capability Either "manage" or "translate".
	 * @param string $nonce_key  Request key holding the nonce.
	 * @return true|\WP_Error
	 */
	public static function verify_request( string $capability = 'manage', string $nonce_key = 'nonce' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_REQUEST[ $nonce_key ] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $nonce_key ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error(
				'als_invalid_nonce',
				__( 'Your session expired. Reload the page and try again.', 'advanced-language-switcher' ),
				array( 'status' => 403 )
			);
		}

		$allowed = 'translate' === $capability ? self::can_translate() : self::can_manage();

		if ( ! $allowed ) {
			return new \WP_Error(
				'als_forbidden',
				__( 'You do not have permission to do that.', 'advanced-language-switcher' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Creates the admin nonce.
	 *
	 * @return string
	 */
	public static function create_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Creates the public nonce.
	 *
	 * @return string
	 */
	public static function create_public_nonce(): string {
		return wp_create_nonce( self::PUBLIC_NONCE_ACTION );
	}

	/**
	 * Grants the translation capability to administrators and editors.
	 *
	 * Called on activation and whenever roles are refreshed.
	 *
	 * @return void
	 */
	public static function add_capabilities(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof \WP_Role && ! $role->has_cap( self::TRANSLATE_CAPABILITY ) ) {
				$role->add_cap( self::TRANSLATE_CAPABILITY );
			}
		}
	}

	/**
	 * Removes the custom capability. Used by uninstall.
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( self::TRANSLATE_CAPABILITY );
			}
		}
	}
}
