<?php
/**
 * Manual translation provider (no external API).
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;

defined( 'ABSPATH' ) || exit;

/**
 * The default provider: everything is translated by a human in the editor.
 *
 * It implements the same interface as the API adapters so the rest of the
 * plugin never has to special case "no provider configured".
 */
class Manual_Provider implements Translation_Provider_Interface {

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'manual';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Manual Translation', 'advanced-language-switcher' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_automatic(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_max_batch_size(): int {
		return 0;
	}

	/**
	 * Returns an error: manual translation cannot fill strings automatically.
	 *
	 * @param string[]      $texts   Strings.
	 * @param Language|null $source  Source language.
	 * @param Language      $target  Target language.
	 * @param bool          $is_html Whether markup is present.
	 * @return string[]|\WP_Error
	 */
	public function translate( array $texts, ?Language $source, Language $target, bool $is_html = false ) {
		return new \WP_Error(
			'als_manual_provider',
			__( 'Manual translation is selected. Choose Google, DeepL or OpenAI to translate automatically.', 'advanced-language-switcher' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_connection() {
		return true;
	}
}
