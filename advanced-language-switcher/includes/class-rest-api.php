<?php
/**
 * REST endpoints.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Public read-only endpoints plus authenticated management endpoints.
 *
 * Everything that changes data or touches provider credentials requires the
 * management capability and a valid REST nonce.
 */
class Rest_Api {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'als/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/languages',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'get_languages' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/translate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'public_permission' ),
				'callback'            => array( $this, 'translate' ),
				'args'                => array(
					'text'     => array(
						'required' => true,
						'type'     => array( 'string', 'array' ),
					),
					'language' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'context'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/switch',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'public_permission' ),
				'callback'            => array( $this, 'switch_language' ),
				'args'                => array(
					'language' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'url'      => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'manage_permission' ),
				'callback'            => array( $this, 'get_stats' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/strings',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'translate_permission' ),
				'callback'            => array( $this, 'get_strings' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/strings/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'translate_permission' ),
				'callback'            => array( $this, 'update_string' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Permissions
	 * ------------------------------------------------------------------ */

	/**
	 * Public endpoints are open but rate-limited by WordPress itself; they
	 * expose nothing that is not already on the rendered page.
	 *
	 * @return bool
	 */
	public function public_permission(): bool {
		return true;
	}

	/**
	 * Management endpoints require the plugin capability.
	 *
	 * @return bool|\WP_Error
	 */
	public function manage_permission() {
		if ( ! Security::can_manage() ) {
			return new \WP_Error(
				'als_forbidden',
				__( 'You do not have permission to do that.', 'advanced-language-switcher' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Translation endpoints require the translation capability.
	 *
	 * @return bool|\WP_Error
	 */
	public function translate_permission() {
		if ( ! Security::can_translate() ) {
			return new \WP_Error(
				'als_forbidden',
				__( 'You do not have permission to edit translations.', 'advanced-language-switcher' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Lists the enabled languages and their URLs for the current request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_languages( \WP_REST_Request $request ): \WP_REST_Response {
		$url        = (string) $request->get_param( 'url' );
		$alternates = $this->plugin->router()->get_alternate_urls( $url );
		$items      = array();

		foreach ( $alternates as $code => $alternate ) {
			/** @var Language $language */
			$language = $alternate['language'];

			$items[] = array_merge(
				$language->to_array(),
				array( 'url' => $alternate['url'] )
			);
		}

		$current = $this->plugin->router()->get_current_language();

		return new \WP_REST_Response(
			array(
				'languages' => $items,
				'current'   => $current instanceof Language ? $current->code : '',
			),
			200
		);
	}

	/**
	 * Translates one or more strings using the stored translation memory.
	 *
	 * This never triggers a provider call for anonymous visitors, so the
	 * endpoint cannot be used to burn through an API quota.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function translate( \WP_REST_Request $request ) {
		$text    = $request->get_param( 'text' );
		$code    = (string) $request->get_param( 'language' );
		$context = (string) $request->get_param( 'context' );

		$language = '' !== $code
			? $this->plugin->languages()->get_by_code( $code )
			: $this->plugin->router()->get_current_language();

		if ( ! $language instanceof Language ) {
			return new \WP_Error(
				'als_unknown_language',
				__( 'Unknown language.', 'advanced-language-switcher' ),
				array( 'status' => 400 )
			);
		}

		$allow_auto = Security::can_translate();
		$items      = is_array( $text ) ? $text : array( $text );
		$results    = array();

		foreach ( $items as $item ) {
			$item = sanitize_textarea_field( (string) $item );

			$results[] = $this->plugin->translations()->translate(
				$item,
				$language,
				$context,
				array(
					'register'   => $allow_auto,
					'allow_auto' => $allow_auto && Settings::is_enabled( 'auto_translate_missing' ),
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'language'     => $language->code,
				'translations' => is_array( $text ) ? $results : ( $results[0] ?? '' ),
			),
			200
		);
	}

	/**
	 * Records a language choice and returns the URL to navigate to.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function switch_language( \WP_REST_Request $request ) {
		$code     = (string) $request->get_param( 'language' );
		$language = $this->plugin->languages()->get_by_code( $code );

		if ( ! $language instanceof Language || ! $language->status ) {
			return new \WP_Error(
				'als_unknown_language',
				__( 'Unknown language.', 'advanced-language-switcher' ),
				array( 'status' => 400 )
			);
		}

		$url = (string) $request->get_param( 'url' );
		$url = '' !== $url ? $url : $this->plugin->router()->current_url();

		$this->plugin->router()->set_cookie( $language->code );

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), 'als_language', $language->code );
		}

		/** This action is documented in includes/class-ajax.php */
		do_action( 'als_language_switched', $language );

		return new \WP_REST_Response(
			array(
				'language'  => $language->code,
				'direction' => $language->direction,
				'htmlLang'  => $language->html_lang(),
				'url'       => $this->plugin->router()->get_language_url( $language, $url ),
			),
			200
		);
	}

	/**
	 * Returns translation statistics.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats(): \WP_REST_Response {
		$stats = $this->plugin->translations()->global_stats();

		$per_language = array();

		foreach ( $stats['per_language'] as $row ) {
			/** @var Language $language */
			$language = $row['language'];

			$per_language[] = array(
				'code'       => $language->code,
				'name'       => $language->name,
				'total'      => $row['total'],
				'translated' => $row['translated'],
				'missing'    => $row['missing'],
				'percent'    => $row['percent'],
			);
		}

		$stats['per_language'] = $per_language;

		return new \WP_REST_Response( $stats, 200 );
	}

	/**
	 * Lists translation strings for the editor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_strings( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->plugin->translations()->query(
			array(
				'language_id' => (int) $request->get_param( 'language_id' ),
				'search'      => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'status'      => sanitize_key( (string) $request->get_param( 'status' ) ),
				'per_page'    => (int) ( $request->get_param( 'per_page' ) ?: 25 ),
				'page'        => (int) ( $request->get_param( 'page' ) ?: 1 ),
			)
		);

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Saves a translation from the editor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_string( \WP_REST_Request $request ) {
		$string_id   = (int) $request->get_param( 'id' );
		$language_id = (int) $request->get_param( 'language_id' );
		$translation = (string) $request->get_param( 'translation' );
		$status      = sanitize_key( (string) $request->get_param( 'status' ) );

		if ( ! $this->plugin->languages()->get_by_id( $language_id ) instanceof Language ) {
			return new \WP_Error(
				'als_unknown_language',
				__( 'Unknown language.', 'advanced-language-switcher' ),
				array( 'status' => 400 )
			);
		}

		$saved = $this->plugin->translations()->save_translation(
			$string_id,
			$language_id,
			wp_kses_post( $translation ),
			'' !== $status ? $status : Translation_Manager::STATUS_MANUAL,
			'manual'
		);

		if ( ! $saved ) {
			return new \WP_Error(
				'als_save_failed',
				__( 'The translation could not be saved.', 'advanced-language-switcher' ),
				array( 'status' => 500 )
			);
		}

		return new \WP_REST_Response( array( 'saved' => true ), 200 );
	}
}
