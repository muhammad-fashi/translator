<?php
/**
 * Contract every translation provider must satisfy.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Providers;

use ALS\Language;

defined( 'ABSPATH' ) || exit;

/**
 * Translation provider interface.
 *
 * Adapters are intentionally thin: they receive already-masked plain strings
 * and return translated strings in the same order. Everything else (caching,
 * persistence, placeholder handling) is the engine's job.
 */
interface Translation_Provider_Interface {

	/**
	 * Machine readable slug, e.g. "deepl".
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Human readable label shown in the admin.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Whether the provider calls an external API.
	 *
	 * @return bool
	 */
	public function is_automatic(): bool;

	/**
	 * Whether the provider has everything it needs to run (API key, etc.).
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Maximum number of strings that may be sent in one request.
	 *
	 * @return int
	 */
	public function get_max_batch_size(): int;

	/**
	 * Translates a batch of strings.
	 *
	 * @param string[]      $texts   Strings to translate, already masked.
	 * @param Language|null $source  Source language, null for auto detect.
	 * @param Language      $target  Target language.
	 * @param bool          $is_html Whether the payload contains markup.
	 * @return string[]|\WP_Error Translations in input order, or an error.
	 */
	public function translate( array $texts, ?Language $source, Language $target, bool $is_html = false );

	/**
	 * Verifies credentials against the remote service.
	 *
	 * @return true|\WP_Error
	 */
	public function test_connection();
}
