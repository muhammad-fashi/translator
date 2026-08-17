<?php
/**
 * Translation engine screen.
 *
 * @package AdvancedLanguageSwitcher
 *
 * @var \ALS\Plugin      $plugin
 * @var \ALS\Admin\Admin $admin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ALS\Settings;

$als_providers = $plugin->engine()->get_providers();
$als_active    = (string) Settings::get( 'provider', 'builtin' );
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Translation Provider', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro">
		<?php esc_html_e( 'The Built-in Translator is selected by default and needs no account, no API key and no setup. The other engines are optional and only become available once you add a key.', 'advanced-language-switcher' ); ?>
	</p>
	<p class="als-card__intro">
		<?php esc_html_e( 'Providers are adapters behind a shared interface, so switching engines never changes your stored translations.', 'advanced-language-switcher' ); ?>
	</p>

	<div class="als-provider-choices">
		<?php foreach ( $als_providers as $als_slug => $als_provider ) : ?>
			<label class="als-provider" for="als-provider-<?php echo esc_attr( (string) $als_slug ); ?>">
				<input
					type="radio"
					id="als-provider-<?php echo esc_attr( (string) $als_slug ); ?>"
					name="provider"
					value="<?php echo esc_attr( (string) $als_slug ); ?>"
					data-als-setting="provider"
					<?php checked( $als_active, (string) $als_slug ); ?>
				/>
				<span class="als-provider__body">
					<span class="als-provider__name"><?php echo esc_html( $als_provider->get_label() ); ?></span>
					<span class="als-provider__meta">
						<?php if ( 'builtin' === $als_slug ) : ?>
							<span class="als-badge als-badge--ok"><?php esc_html_e( 'Ready to use — no API key', 'advanced-language-switcher' ); ?></span>
						<?php elseif ( ! $als_provider->is_automatic() ) : ?>
							<?php esc_html_e( 'Human translation only', 'advanced-language-switcher' ); ?>
						<?php elseif ( $als_provider->is_configured() ) : ?>
							<span class="als-badge als-badge--ok"><?php esc_html_e( 'API key saved', 'advanced-language-switcher' ); ?></span>
						<?php else : ?>
							<span class="als-badge als-badge--missing"><?php esc_html_e( 'Optional — needs an API key', 'advanced-language-switcher' ); ?></span>
						<?php endif; ?>
					</span>
				</span>
			</label>
		<?php endforeach; ?>
	</div>

	<?php $admin->save_button(); ?>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'API Keys (optional)', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro">
		<?php esc_html_e( 'Only needed if you choose Google, DeepL or OpenAI above. Leave this section untouched to keep using the Built-in Translator.', 'advanced-language-switcher' ); ?>
	</p>
	<p class="als-card__intro">
		<?php esc_html_e( 'Keys are stored in a dedicated, non-autoloaded option, are readable only by administrators and are never sent to the browser on the front end.', 'advanced-language-switcher' ); ?>
	</p>

	<?php foreach ( $als_providers as $als_slug => $als_provider ) : ?>
		<?php if ( ! $als_provider->is_automatic() || 'builtin' === $als_slug ) : ?>
			<?php continue; ?>
		<?php endif; ?>

		<div class="als-credential" data-provider="<?php echo esc_attr( (string) $als_slug ); ?>">
			<div class="als-field">
				<label class="als-field__label" for="als-key-<?php echo esc_attr( (string) $als_slug ); ?>">
					<?php
					printf(
						/* translators: %s: provider name. */
						esc_html__( '%s API Key', 'advanced-language-switcher' ),
						esc_html( $als_provider->get_label() )
					);
					?>
				</label>
				<input
					type="password"
					id="als-key-<?php echo esc_attr( (string) $als_slug ); ?>"
					autocomplete="off"
					spellcheck="false"
					placeholder="<?php echo esc_attr( Settings::mask_credential( (string) $als_slug ) ?: __( 'Not configured', 'advanced-language-switcher' ) ); ?>"
				/>
				<p class="als-field__description">
					<?php if ( '' !== Settings::mask_credential( (string) $als_slug ) ) : ?>
						<?php
						printf(
							/* translators: %s: masked API key. */
							esc_html__( 'Stored key: %s. Leave the field empty to keep it, or type a new key to replace it.', 'advanced-language-switcher' ),
							'<code>' . esc_html( Settings::mask_credential( (string) $als_slug ) ) . '</code>'
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'No key stored yet.', 'advanced-language-switcher' ); ?>
					<?php endif; ?>
				</p>
			</div>

			<p class="als-actions">
				<button type="button" class="button" data-als-save-key="<?php echo esc_attr( (string) $als_slug ); ?>">
					<?php esc_html_e( 'Save key', 'advanced-language-switcher' ); ?>
				</button>
				<button type="button" class="button" data-als-test-connection="<?php echo esc_attr( (string) $als_slug ); ?>">
					<?php esc_html_e( 'Test Connection', 'advanced-language-switcher' ); ?>
				</button>
				<button type="button" class="button-link als-danger" data-als-clear-key="<?php echo esc_attr( (string) $als_slug ); ?>">
					<?php esc_html_e( 'Remove key', 'advanced-language-switcher' ); ?>
				</button>
			</p>
		</div>
	<?php endforeach; ?>
</section>

<?php
$admin->field_group(
	__( 'Built-in Translator', 'advanced-language-switcher' ),
	array(
		'builtin_service'    => array(
			'type'        => 'select',
			'label'       => __( 'Service', 'advanced-language-switcher' ),
			'options'     => array(
				'mymemory'       => __( 'MyMemory — free, keyless, daily allowance', 'advanced-language-switcher' ),
				'libretranslate' => __( 'LibreTranslate — your own server, no limit', 'advanced-language-switcher' ),
			),
			'description' => __( 'MyMemory works immediately with nothing to configure. LibreTranslate is open source: run it yourself and no third party ever sees your content.', 'advanced-language-switcher' ),
		),
		'builtin_email'      => array(
			'type'        => 'text',
			'label'       => __( 'Contact e-mail (optional)', 'advanced-language-switcher' ),
			'description' => __( 'Not an API key and not an account. MyMemory grants a larger free daily allowance to requests that include a contact address.', 'advanced-language-switcher' ),
		),
		'libretranslate_url' => array(
			'type'        => 'text',
			'label'       => __( 'LibreTranslate server URL', 'advanced-language-switcher' ),
			'description' => __( 'For example https://translate.example.com. Only used when LibreTranslate is selected above.', 'advanced-language-switcher' ),
		),
	),
	__( 'Used when the Built-in Translator is the active provider.', 'advanced-language-switcher' )
);

$admin->field_group(
	__( 'Automatic Translation', 'advanced-language-switcher' ),
	array(
		'auto_translate_missing' => array(
			'type'        => 'toggle',
			'label'       => __( 'Translate missing strings automatically', 'advanced-language-switcher' ),
			'description' => __( 'New text is translated on its own as visitors browse. Turn this off to translate only from the Translations screen.', 'advanced-language-switcher' ),
		),
		'background_translate'   => array(
			'type'        => 'toggle',
			'label'       => __( 'Translate after the page is delivered', 'advanced-language-switcher' ),
			'description' => __( 'Strongly recommended. Translation happens once the visitor already has the page, so it never slows a page load.', 'advanced-language-switcher' ),
		),
		'background_batch'       => array(
			'type'        => 'number',
			'label'       => __( 'Strings per page view', 'advanced-language-switcher' ),
			'min'         => 1,
			'max'         => 50,
			'description' => __( 'How much is translated in the background on each page view. Higher finishes sooner; lower is gentler on the free service.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'OpenAI Options', 'advanced-language-switcher' ),
	array(
		'openai_model'        => array(
			'type'        => 'text',
			'label'       => __( 'Model', 'advanced-language-switcher' ),
			'description' => __( 'Any chat model your account can use.', 'advanced-language-switcher' ),
		),
		'openai_temperature'  => array(
			'type'        => 'number',
			'label'       => __( 'Temperature', 'advanced-language-switcher' ),
			'min'         => 0,
			'max'         => 2,
			'step'        => 0.1,
			'description' => __( 'Lower values keep translations literal and consistent.', 'advanced-language-switcher' ),
		),
		'openai_instructions' => array(
			'type'        => 'textarea',
			'label'       => __( 'Translation instructions', 'advanced-language-switcher' ),
			'rows'        => 5,
			'description' => __( 'Added to the system prompt on every request. Use it for tone of voice and industry terminology.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'DeepL Options', 'advanced-language-switcher' ),
	array(
		'deepl_formality' => array(
			'type'        => 'select',
			'label'       => __( 'Formality', 'advanced-language-switcher' ),
			'options'     => array(
				'default' => __( 'Default', 'advanced-language-switcher' ),
				'more'    => __( 'More formal', 'advanced-language-switcher' ),
				'less'    => __( 'Less formal', 'advanced-language-switcher' ),
			),
			'description' => __( 'Only applied to languages where DeepL supports it.', 'advanced-language-switcher' ),
		),
	)
);

$admin->field_group(
	__( 'Requests', 'advanced-language-switcher' ),
	array(
		'request_timeout' => array(
			'type'  => 'number',
			'label' => __( 'Request timeout (seconds)', 'advanced-language-switcher' ),
			'min'   => 5,
			'max'   => 120,
		),
	)
);

$admin->save_button();
?>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Check the Built-in Translator', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'Translates a single test word to confirm the free service is reachable from your server.', 'advanced-language-switcher' ); ?></p>
	<p class="als-actions">
		<button type="button" class="button" data-als-test-connection="builtin">
			<?php esc_html_e( 'Test Connection', 'advanced-language-switcher' ); ?>
		</button>
	</p>
	<p class="als-card__intro">
		<?php esc_html_e( 'If this fails, your host is most likely blocking outbound HTTP requests. Translations already saved keep working either way, and you can still translate by hand on the Translations screen.', 'advanced-language-switcher' ); ?>
	</p>
</section>

<section class="als-card">
	<h2 class="als-card__title"><?php esc_html_e( 'Adding your own engine', 'advanced-language-switcher' ); ?></h2>
	<p class="als-card__intro"><?php esc_html_e( 'Implement the provider interface and register it with a single filter:', 'advanced-language-switcher' ); ?></p>
	<pre class="als-code"><code>add_filter( 'als_translation_providers', function ( $providers ) {
    $providers['my_engine'] = new My_Engine_Provider();

    return $providers;
} );</code></pre>
	<p class="als-card__intro"><?php esc_html_e( 'The class must implement ALS\Providers\Translation_Provider_Interface. Everything else — caching, batching, placeholder protection and the glossary — is handled for you.', 'advanced-language-switcher' ); ?></p>
</section>
