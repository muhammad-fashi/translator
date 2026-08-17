<?php
/**
 * AJAX endpoints.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Every admin-ajax handler used by the plugin.
 *
 * All privileged actions run through Security::verify_request(), which checks
 * the nonce and the capability before anything else happens.
 */
class Ajax {

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
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$manage = array(
			'save_language',
			'delete_language',
			'duplicate_language',
			'set_default_language',
			'toggle_language',
			'reorder_languages',
			'save_settings',
			'save_credential',
			'test_connection',
			'clear_cache',
			'clear_logs',
			'scan_website',
			'create_backup',
			'restore_backup',
			'delete_backup',
			'import_translations',
			'export_translations',
			'save_glossary',
			'delete_glossary',
			'dismiss_notice',
		);

		foreach ( $manage as $action ) {
			add_action( 'wp_ajax_als_' . $action, array( $this, 'handle_' . $action ) );
		}

		$translate = array( 'save_translation', 'reset_translation', 'auto_translate', 'get_strings' );

		foreach ( $translate as $action ) {
			add_action( 'wp_ajax_als_' . $action, array( $this, 'handle_' . $action ) );
		}

		// Public switcher endpoint.
		add_action( 'wp_ajax_als_switch_language', array( $this, 'handle_switch_language' ) );
		add_action( 'wp_ajax_nopriv_als_switch_language', array( $this, 'handle_switch_language' ) );
	}

	/**
	 * Verifies a privileged request, sending a JSON error and exiting on failure.
	 *
	 * @param string $capability "manage" or "translate".
	 * @return void
	 */
	protected function authorize( string $capability = 'manage' ): void {
		$check = Security::verify_request( $capability );

		if ( is_wp_error( $check ) ) {
			wp_send_json_error(
				array( 'message' => $check->get_error_message() ),
				403
			);
		}
	}

	/**
	 * Reads a sanitized string from the request.
	 *
	 * @param string $key      Request key.
	 * @param string $fallback Default value.
	 * @return string
	 */
	protected function param( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
	}

	/**
	 * Reads an integer from the request.
	 *
	 * @param string $key      Request key.
	 * @param int    $fallback Default value.
	 * @return int
	 */
	protected function int_param( string $key, int $fallback = 0 ): int {
		$value = $this->param( $key, (string) $fallback );

		return is_numeric( $value ) ? (int) $value : $fallback;
	}

	/* ---------------------------------------------------------------------
	 * Languages
	 * ------------------------------------------------------------------ */

	/**
	 * Creates or updates a language.
	 *
	 * @return void
	 */
	public function handle_save_language(): void {
		$this->authorize();

		$id   = $this->int_param( 'id' );
		$data = array(
			'name'        => $this->param( 'name' ),
			'native_name' => $this->param( 'native_name' ),
			'code'        => $this->param( 'code' ),
			'locale'      => $this->param( 'locale' ),
			'country'     => $this->param( 'country' ),
			'flag'        => $this->param( 'flag' ),
			'flag_url'    => $this->param( 'flag_url' ),
			'direction'   => $this->param( 'direction', 'auto' ),
			'status'      => $this->int_param( 'status', 1 ),
			'is_default'  => $this->int_param( 'is_default' ),
		);

		$manager = $this->plugin->languages();
		$result  = $id > 0 ? $manager->update( $id, $data ) : $manager->create( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->plugin->translations()->invalidate_all();

		wp_send_json_success(
			array(
				'message' => $id > 0
					? __( 'Language updated.', 'advanced-language-switcher' )
					: __( 'Language added.', 'advanced-language-switcher' ),
				'id'      => $id > 0 ? $id : (int) $result,
			)
		);
	}

	/**
	 * Deletes a language.
	 *
	 * @return void
	 */
	public function handle_delete_language(): void {
		$this->authorize();

		$result = $this->plugin->languages()->delete( $this->int_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->plugin->translations()->invalidate_all();

		wp_send_json_success( array( 'message' => __( 'Language deleted.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Duplicates a language.
	 *
	 * @return void
	 */
	public function handle_duplicate_language(): void {
		$this->authorize();

		$result = $this->plugin->languages()->duplicate( $this->int_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Language duplicated.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Promotes a language to default.
	 *
	 * @return void
	 */
	public function handle_set_default_language(): void {
		$this->authorize();

		$result = $this->plugin->languages()->set_default( $this->int_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->plugin->translations()->invalidate_all();

		wp_send_json_success( array( 'message' => __( 'Default language updated.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Enables or disables a language.
	 *
	 * @return void
	 */
	public function handle_toggle_language(): void {
		$this->authorize();

		$result = $this->plugin->languages()->set_status(
			$this->int_param( 'id' ),
			1 === $this->int_param( 'status' )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Language status updated.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Persists a manual language ordering.
	 *
	 * @return void
	 */
	public function handle_reorder_languages(): void {
		$this->authorize();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['order'] ) ? (array) wp_unslash( $_POST['order'] ) : array();
		$ids = array_map( 'absint', $raw );

		$this->plugin->languages()->reorder( array_filter( $ids ) );

		wp_send_json_success( array( 'message' => __( 'Order saved.', 'advanced-language-switcher' ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Settings and providers
	 * ------------------------------------------------------------------ */

	/**
	 * Saves the settings form.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->authorize();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		Settings::update( $raw );

		// URL structure or scope changes invalidate every cached rendering.
		$this->plugin->translations()->invalidate_all();
		update_option( Installer::FLUSH_OPTION, 1, false );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Stores an API credential.
	 *
	 * @return void
	 */
	public function handle_save_credential(): void {
		$this->authorize();

		$provider = sanitize_key( $this->param( 'provider' ) );

		if ( ! $this->plugin->engine()->get_provider( $provider ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'advanced-language-switcher' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST['value'] ) ? trim( (string) wp_unslash( $_POST['value'] ) ) : '';

		Settings::set_credential( $provider, $value );

		wp_send_json_success(
			array(
				'message' => __( 'API key saved.', 'advanced-language-switcher' ),
				'masked'  => Settings::mask_credential( $provider ),
			)
		);
	}

	/**
	 * Tests a provider connection.
	 *
	 * @return void
	 */
	public function handle_test_connection(): void {
		$this->authorize();

		$result = $this->plugin->engine()->test_connection( sanitize_key( $this->param( 'provider' ) ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful.', 'advanced-language-switcher' ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Translations
	 * ------------------------------------------------------------------ */

	/**
	 * Saves one translation.
	 *
	 * @return void
	 */
	public function handle_save_translation(): void {
		$this->authorize( 'translate' );

		$string_id   = $this->int_param( 'string_id' );
		$language_id = $this->int_param( 'language_id' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$translation = isset( $_POST['translation'] ) ? wp_kses_post( wp_unslash( (string) $_POST['translation'] ) ) : '';

		$status = sanitize_key( $this->param( 'status', Translation_Manager::STATUS_MANUAL ) );

		$saved = $this->plugin->translations()->save_translation(
			$string_id,
			$language_id,
			$translation,
			$status,
			'manual'
		);

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'The translation could not be saved.', 'advanced-language-switcher' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Saved.', 'advanced-language-switcher' ),
				'status'  => '' === trim( $translation ) ? Translation_Manager::STATUS_MISSING : $status,
			)
		);
	}

	/**
	 * Clears one translation.
	 *
	 * @return void
	 */
	public function handle_reset_translation(): void {
		$this->authorize( 'translate' );

		$this->plugin->translations()->reset_translation(
			$this->int_param( 'string_id' ),
			$this->int_param( 'language_id' )
		);

		wp_send_json_success( array( 'message' => __( 'Translation reset.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Returns a page of strings for the editor.
	 *
	 * @return void
	 */
	public function handle_get_strings(): void {
		$this->authorize( 'translate' );

		$result = $this->plugin->translations()->query(
			array(
				'language_id' => $this->int_param( 'language_id' ),
				'search'      => $this->param( 'search' ),
				'status'      => sanitize_key( $this->param( 'status' ) ),
				'object_type' => sanitize_key( $this->param( 'object_type' ) ),
				'per_page'    => $this->int_param( 'per_page', 25 ),
				'page'        => $this->int_param( 'page', 1 ),
			)
		);

		wp_send_json_success( $result );
	}

	/**
	 * Runs one automatic translation batch.
	 *
	 * @return void
	 */
	public function handle_auto_translate(): void {
		$this->authorize( 'translate' );

		$language_id = $this->int_param( 'language_id' );
		$scope       = sanitize_key( $this->param( 'scope', 'missing' ) );
		$batch       = $this->int_param( 'batch_size', 0 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$selected = isset( $_POST['string_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['string_ids'] ) ) : array();

		$result = $this->plugin->translations()->translate_batch(
			$language_id,
			$batch,
			in_array( $scope, array( 'missing', 'all' ), true ) ? $scope : 'missing',
			array_filter( $selected )
		);

		if ( $result['failed'] > 0 && 0 === $result['translated'] && '' !== $result['message'] ) {
			wp_send_json_error( array_merge( $result, array( 'message' => $result['message'] ) ) );
		}

		wp_send_json_success( $result );
	}

	/* ---------------------------------------------------------------------
	 * Scanner, cache, logs
	 * ------------------------------------------------------------------ */

	/**
	 * Runs one scanner step.
	 *
	 * @return void
	 */
	public function handle_scan_website(): void {
		$this->authorize();

		$step   = sanitize_key( $this->param( 'step', 'start' ) );
		$offset = $this->int_param( 'offset' );

		$result = $this->plugin->scanner()->run_step( $step, $offset );

		wp_send_json_success( $result );
	}

	/**
	 * Clears caches.
	 *
	 * @return void
	 */
	public function handle_clear_cache(): void {
		$this->authorize();

		$scope = sanitize_key( $this->param( 'scope', 'all' ) );

		if ( 'page' === $scope ) {
			$removed = $this->plugin->cache()->flush( 'page' );
		} elseif ( 'dictionary' === $scope ) {
			$removed = $this->plugin->cache()->flush( 'dictionary' );
		} else {
			$removed = $this->plugin->cache()->flush();
		}

		$this->plugin->translations()->invalidate_all();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of cache entries removed. */
					__( 'Cache cleared. %d entries removed.', 'advanced-language-switcher' ),
					$removed
				),
			)
		);
	}

	/**
	 * Empties the log table.
	 *
	 * @return void
	 */
	public function handle_clear_logs(): void {
		$this->authorize();

		$removed = $this->plugin->logger()->clear();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of log entries removed. */
					__( '%d log entries removed.', 'advanced-language-switcher' ),
					$removed
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Backups and transfer
	 * ------------------------------------------------------------------ */

	/**
	 * Creates a translation backup.
	 *
	 * @return void
	 */
	public function handle_create_backup(): void {
		$this->authorize();

		$result = $this->plugin->porter()->create_backup(
			$this->int_param( 'language_id' ),
			$this->param( 'name' )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Backup created.', 'advanced-language-switcher' ),
				'id'      => $result,
			)
		);
	}

	/**
	 * Restores a translation backup.
	 *
	 * @return void
	 */
	public function handle_restore_backup(): void {
		$this->authorize();

		$result = $this->plugin->porter()->restore_backup( $this->int_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of restored translations. */
					__( '%d translations restored.', 'advanced-language-switcher' ),
					$result
				),
			)
		);
	}

	/**
	 * Deletes a translation backup.
	 *
	 * @return void
	 */
	public function handle_delete_backup(): void {
		$this->authorize();

		$this->plugin->porter()->delete_backup( $this->int_param( 'id' ) );

		wp_send_json_success( array( 'message' => __( 'Backup deleted.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Imports translations from an uploaded file.
	 *
	 * @return void
	 */
	public function handle_import_translations(): void {
		$this->authorize();

		$language_id = $this->int_param( 'language_id' );
		$overwrite   = 1 === $this->int_param( 'overwrite' );

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'advanced-language-switcher' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tmp  = (string) $_FILES['file']['tmp_name'];
		$name = isset( $_FILES['file']['name'] ) ? sanitize_file_name( (string) $_FILES['file']['name'] ) : '';

		if ( ! is_uploaded_file( $tmp ) ) {
			wp_send_json_error( array( 'message' => __( 'The upload could not be verified.', 'advanced-language-switcher' ) ) );
		}

		$result = $this->plugin->porter()->import( $tmp, $name, $language_id, $overwrite );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Exports translations as a downloadable payload.
	 *
	 * @return void
	 */
	public function handle_export_translations(): void {
		$this->authorize();

		$language_id = $this->int_param( 'language_id' );
		$format      = sanitize_key( $this->param( 'format', 'json' ) );

		$result = $this->plugin->porter()->export( $language_id, $format );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/* ---------------------------------------------------------------------
	 * Glossary and notices
	 * ------------------------------------------------------------------ */

	/**
	 * Saves a glossary term.
	 *
	 * @return void
	 */
	public function handle_save_glossary(): void {
		$this->authorize( 'translate' );

		$result = $this->plugin->glossary()->save(
			$this->int_param( 'language_id' ),
			$this->param( 'source_term' ),
			$this->param( 'target_term' ),
			1 === $this->int_param( 'case_sensitive' )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Glossary term saved.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Deletes a glossary term.
	 *
	 * @return void
	 */
	public function handle_delete_glossary(): void {
		$this->authorize( 'translate' );

		$this->plugin->glossary()->delete( $this->int_param( 'id' ) );

		wp_send_json_success( array( 'message' => __( 'Glossary term deleted.', 'advanced-language-switcher' ) ) );
	}

	/**
	 * Dismisses the onboarding notice.
	 *
	 * @return void
	 */
	public function handle_dismiss_notice(): void {
		$this->authorize();

		delete_option( Installer::ONBOARDING_OPTION );

		wp_send_json_success();
	}

	/* ---------------------------------------------------------------------
	 * Public
	 * ------------------------------------------------------------------ */

	/**
	 * Records a visitor's language choice.
	 *
	 * @return void
	 */
	public function handle_switch_language(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, Security::PUBLIC_NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'advanced-language-switcher' ) ), 403 );
		}

		$code     = sanitize_key( $this->param( 'language' ) );
		$language = $this->plugin->languages()->get_by_code( $code );

		if ( ! $language instanceof Language || ! $language->status ) {
			wp_send_json_error( array( 'message' => __( 'Unknown language.', 'advanced-language-switcher' ) ), 400 );
		}

		$url = $this->param( 'url' );
		$url = '' !== $url ? esc_url_raw( $url ) : $this->plugin->router()->current_url();

		$this->plugin->router()->set_cookie( $language->code );

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), 'als_language', $language->code );
		}

		/**
		 * Fires when a visitor explicitly switches language.
		 *
		 * Integrations use this to keep their own session state intact.
		 *
		 * @param Language $language The newly selected language.
		 */
		do_action( 'als_language_switched', $language );

		wp_send_json_success(
			array(
				'language'  => $language->code,
				'direction' => $language->direction,
				'htmlLang'  => $language->html_lang(),
				'url'       => $this->plugin->router()->get_language_url( $language, $url ),
			)
		);
	}
}
