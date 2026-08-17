<?php
/**
 * Admin screens.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS\Admin;

use ALS\Database;
use ALS\Installer;
use ALS\Language;
use ALS\Plugin;
use ALS\Porter;
use ALS\Security;
use ALS\Settings;
use ALS\Switcher;
use ALS\Translation_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the admin menu and renders every screen.
 */
class Admin {

	/**
	 * Top level menu slug.
	 */
	public const MENU_SLUG = 'als-dashboard';

	/**
	 * Plugin container.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Registered screen slugs mapped to their view file.
	 *
	 * @var array<string,string>
	 */
	protected array $screens = array();

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin container.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		$this->screens = array(
			'als-dashboard'    => 'dashboard',
			'als-languages'    => 'languages',
			'als-translations' => 'translations',
			'als-settings'     => 'settings',
			'als-providers'    => 'providers',
			'als-urls'         => 'urls',
			'als-cache'        => 'cache',
			'als-elementor'    => 'elementor',
			'als-woocommerce'  => 'woocommerce',
			'als-tools'        => 'tools',
			'als-status'       => 'system-status',
		);
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'admin_init', array( $this, 'maybe_add_capabilities' ) );
		add_filter( 'plugin_action_links_' . ALS_PLUGIN_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Makes sure the translation capability exists on the usual roles.
	 *
	 * @return void
	 */
	public function maybe_add_capabilities(): void {
		if ( get_option( 'als_caps_version' ) === ALS_VERSION ) {
			return;
		}

		Security::add_capabilities();
		update_option( 'als_caps_version', ALS_VERSION, false );
	}

	/**
	 * Adds the Language Translator menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$capability = Security::CAPABILITY;

		add_menu_page(
			__( 'Language Translator', 'advanced-language-switcher' ),
			__( 'Language Translator', 'advanced-language-switcher' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-translation',
			58
		);

		$pages = array(
			'als-dashboard'    => __( 'Dashboard', 'advanced-language-switcher' ),
			'als-languages'    => __( 'Languages', 'advanced-language-switcher' ),
			'als-translations' => __( 'Translations', 'advanced-language-switcher' ),
			'als-settings'     => __( 'Translation Settings', 'advanced-language-switcher' ),
			'als-providers'    => __( 'Translation Engine', 'advanced-language-switcher' ),
			'als-urls'         => __( 'URL Settings', 'advanced-language-switcher' ),
			'als-cache'        => __( 'Cache', 'advanced-language-switcher' ),
			'als-elementor'    => __( 'Elementor', 'advanced-language-switcher' ),
			'als-woocommerce'  => __( 'WooCommerce', 'advanced-language-switcher' ),
			'als-tools'        => __( 'Import / Export', 'advanced-language-switcher' ),
			'als-status'       => __( 'System Status', 'advanced-language-switcher' ),
		);

		foreach ( $pages as $slug => $title ) {
			// The translation editor is available to translators too.
			$page_capability = 'als-translations' === $slug && Security::can_translate()
				? Security::TRANSLATE_CAPABILITY
				: $capability;

			add_submenu_page(
				self::MENU_SLUG,
				$title,
				$title,
				$page_capability,
				$slug,
				array( $this, 'render_page' )
			);
		}

		// The auto-generated first submenu duplicates the parent label.
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'advanced-language-switcher' ),
			__( 'Dashboard', 'advanced-language-switcher' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the current screen.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : self::MENU_SLUG;

		if ( self::MENU_SLUG === $page ) {
			$page = 'als-dashboard';
		}

		if ( ! isset( $this->screens[ $page ] ) ) {
			$page = 'als-dashboard';
		}

		$allowed = 'als-translations' === $page ? Security::can_translate() : Security::can_manage();

		if ( ! $allowed ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'advanced-language-switcher' ) );
		}

		$view = ALS_PLUGIN_DIR . 'admin/views/' . $this->screens[ $page ] . '.php';

		if ( ! is_readable( $view ) ) {
			wp_die( esc_html__( 'That screen could not be loaded.', 'advanced-language-switcher' ) );
		}

		// Values every view relies on.
		$plugin    = $this->plugin;
		$admin     = $this;
		$languages = $plugin->languages()->all();
		$settings  = Settings::all();

		echo '<div class="wrap als-wrap">';

		$this->render_header( $page );

		include $view;

		echo '</div>';
	}

	/**
	 * Renders the shared page header and tab bar.
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	protected function render_header( string $current ): void {
		$titles = array(
			'als-dashboard'    => __( 'Dashboard', 'advanced-language-switcher' ),
			'als-languages'    => __( 'Languages', 'advanced-language-switcher' ),
			'als-translations' => __( 'Translations', 'advanced-language-switcher' ),
			'als-settings'     => __( 'Translation Settings', 'advanced-language-switcher' ),
			'als-providers'    => __( 'Translation Engine', 'advanced-language-switcher' ),
			'als-urls'         => __( 'URL Settings', 'advanced-language-switcher' ),
			'als-cache'        => __( 'Cache', 'advanced-language-switcher' ),
			'als-elementor'    => __( 'Elementor', 'advanced-language-switcher' ),
			'als-woocommerce'  => __( 'WooCommerce', 'advanced-language-switcher' ),
			'als-tools'        => __( 'Import / Export', 'advanced-language-switcher' ),
			'als-status'       => __( 'System Status', 'advanced-language-switcher' ),
		);

		?>
		<div class="als-header">
			<div class="als-header__brand">
				<span class="dashicons dashicons-translation" aria-hidden="true"></span>
				<div>
					<h1 class="als-header__title"><?php echo esc_html( $titles[ $current ] ?? __( 'Language Translator', 'advanced-language-switcher' ) ); ?></h1>
					<p class="als-header__subtitle"><?php esc_html_e( 'Advanced Language Switcher', 'advanced-language-switcher' ); ?> <?php echo esc_html( ALS_VERSION ); ?></p>
				</div>
			</div>
			<div class="als-header__actions">
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View site', 'advanced-language-switcher' ); ?>
				</a>
			</div>
		</div>
		<nav class="als-tabs" aria-label="<?php esc_attr_e( 'Language Translator sections', 'advanced-language-switcher' ); ?>">
			<?php foreach ( $titles as $slug => $title ) : ?>
				<a
					class="als-tab<?php echo $slug === $current ? ' als-tab--active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
					<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>
				><?php echo esc_html( $title ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="als-flash" id="als-flash" role="status" aria-live="polite"></div>
		<?php
	}

	/**
	 * Loads the admin CSS and JS on plugin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ): void {
		if ( ! is_string( $hook ) || ! str_contains( $hook, 'als-' ) ) {
			return;
		}

		wp_enqueue_style(
			'als-admin',
			ALS_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			ALS_VERSION
		);

		wp_enqueue_style(
			'als-language-switcher',
			ALS_PLUGIN_URL . 'frontend/css/language-switcher.css',
			array(),
			ALS_VERSION
		);

		wp_enqueue_script(
			'als-admin',
			ALS_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			ALS_VERSION,
			true
		);

		wp_localize_script(
			'als-admin',
			'ALSAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => Security::create_nonce(),
				'restUrl'   => esc_url_raw( rest_url( 'als/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'saving'        => __( 'Saving…', 'advanced-language-switcher' ),
					'saved'         => __( 'Saved.', 'advanced-language-switcher' ),
					'error'         => __( 'Something went wrong. Please try again.', 'advanced-language-switcher' ),
					'confirmDelete' => __( 'This cannot be undone. Continue?', 'advanced-language-switcher' ),
					'translating'   => __( 'Translating…', 'advanced-language-switcher' ),
					'scanning'      => __( 'Scanning…', 'advanced-language-switcher' ),
					'done'          => __( 'Done.', 'advanced-language-switcher' ),
				),
			)
		);
	}

	/**
	 * Shows the onboarding notice and any warning that needs attention.
	 *
	 * @return void
	 */
	public function render_notices(): void {
		if ( ! Security::can_manage() ) {
			return;
		}

		if ( get_option( Installer::ONBOARDING_OPTION ) ) {
			?>
			<div class="notice notice-info is-dismissible als-onboarding" data-als-dismiss="1">
				<p>
					<strong><?php esc_html_e( 'Advanced Language Switcher is ready.', 'advanced-language-switcher' ); ?></strong>
					<?php esc_html_e( 'Add your languages, run a scan, then drop the Language Switcher widget into your Elementor header.', 'advanced-language-switcher' ); ?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=als-languages' ) ); ?>">
						<?php esc_html_e( 'Add languages', 'advanced-language-switcher' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		$missing = Database::missing_tables();

		if ( $missing ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: comma separated table names. */
						__( 'Advanced Language Switcher could not find these database tables: %s. Deactivate and reactivate the plugin to create them.', 'advanced-language-switcher' ),
						implode( ', ', $missing )
					)
				)
			);
		}

		$new_strings = (int) get_option( 'als_new_strings_notice', 0 );

		if ( $new_strings > 0 ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of untranslated strings. */
						_n( '%d new untranslated string was detected.', '%d new untranslated strings were detected.', $new_strings, 'advanced-language-switcher' ),
						$new_strings
					)
				),
				esc_url( admin_url( 'admin.php?page=als-translations' ) ),
				esc_html__( 'Translate now', 'advanced-language-switcher' )
			);

			delete_option( 'als_new_strings_notice' );
		}
	}

	/**
	 * Adds a settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
				esc_html__( 'Settings', 'advanced-language-switcher' )
			)
		);

		return $links;
	}

	/* ---------------------------------------------------------------------
	 * View helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Renders a settings field.
	 *
	 * @param string              $key    Setting key.
	 * @param array<string,mixed> $field  Field definition.
	 * @return void
	 */
	public function field( string $key, array $field ): void {
		$value = Settings::get( $key );
		$type  = (string) ( $field['type'] ?? 'text' );
		$id    = 'als-field-' . sanitize_html_class( $key );

		echo '<div class="als-field als-field--' . esc_attr( $type ) . '">';

		if ( 'toggle' === $type ) {
			printf(
				'<label class="als-toggle" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s data-als-setting="%2$s" /><span class="als-toggle__track" aria-hidden="true"></span><span class="als-toggle__label">%4$s</span></label>',
				esc_attr( $id ),
				esc_attr( $key ),
				checked( (bool) $value, true, false ),
				esc_html( (string) ( $field['label'] ?? $key ) )
			);
		} else {
			printf(
				'<label class="als-field__label" for="%s">%s</label>',
				esc_attr( $id ),
				esc_html( (string) ( $field['label'] ?? $key ) )
			);

			switch ( $type ) {
				case 'select':
					printf( '<select id="%s" name="%s" data-als-setting="%s">', esc_attr( $id ), esc_attr( $key ), esc_attr( $key ) );

					foreach ( (array) ( $field['options'] ?? array() ) as $option_value => $option_label ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( (string) $option_value ),
							selected( (string) $value, (string) $option_value, false ),
							esc_html( (string) $option_label )
						);
					}

					echo '</select>';
					break;

				case 'textarea':
					printf(
						'<textarea id="%s" name="%s" rows="%d" data-als-setting="%s">%s</textarea>',
						esc_attr( $id ),
						esc_attr( $key ),
						(int) ( $field['rows'] ?? 4 ),
						esc_attr( $key ),
						esc_textarea( (string) $value )
					);
					break;

				case 'number':
					printf(
						'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" data-als-setting="%s" />',
						esc_attr( $id ),
						esc_attr( $key ),
						esc_attr( (string) $value ),
						esc_attr( (string) ( $field['min'] ?? '' ) ),
						esc_attr( (string) ( $field['max'] ?? '' ) ),
						esc_attr( (string) ( $field['step'] ?? 1 ) ),
						esc_attr( $key )
					);
					break;

				case 'color':
					printf(
						'<input type="color" id="%s" name="%s" value="%s" data-als-setting="%s" />',
						esc_attr( $id ),
						esc_attr( $key ),
						esc_attr( (string) $value ),
						esc_attr( $key )
					);
					break;

				default:
					printf(
						'<input type="text" id="%s" name="%s" value="%s" data-als-setting="%s" />',
						esc_attr( $id ),
						esc_attr( $key ),
						esc_attr( (string) $value ),
						esc_attr( $key )
					);
			}
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="als-field__description">%s</p>', esc_html( (string) $field['description'] ) );
		}

		echo '</div>';
	}

	/**
	 * Renders a group of settings fields inside a card.
	 *
	 * @param string                           $title  Card title.
	 * @param array<string,array<string,mixed>> $fields Field definitions keyed by setting.
	 * @param string                           $intro  Optional intro paragraph.
	 * @return void
	 */
	public function field_group( string $title, array $fields, string $intro = '' ): void {
		echo '<section class="als-card">';
		printf( '<h2 class="als-card__title">%s</h2>', esc_html( $title ) );

		if ( '' !== $intro ) {
			printf( '<p class="als-card__intro">%s</p>', esc_html( $intro ) );
		}

		echo '<div class="als-fields">';

		foreach ( $fields as $key => $field ) {
			$this->field( (string) $key, (array) $field );
		}

		echo '</div></section>';
	}

	/**
	 * Renders the save button used by every settings screen.
	 *
	 * @return void
	 */
	public function save_button(): void {
		printf(
			'<p class="als-actions"><button type="button" class="button button-primary" data-als-save-settings>%s</button></p>',
			esc_html__( 'Save changes', 'advanced-language-switcher' )
		);
	}

	/**
	 * The plugin container, for views.
	 *
	 * @return Plugin
	 */
	public function plugin(): Plugin {
		return $this->plugin;
	}
}
