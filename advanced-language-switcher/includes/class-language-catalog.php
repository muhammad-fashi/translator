<?php
/**
 * Static catalog of well known languages used to pre-fill the "Add Language" form.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Provides sensible defaults (native name, locale, flag, direction) per code.
 */
class Language_Catalog {

	/**
	 * Language codes that are written right to left.
	 *
	 * @var string[]
	 */
	protected const RTL_CODES = array( 'ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv', 'ku', 'ckb' );

	/**
	 * Cached catalog.
	 *
	 * @var array<string,array<string,string>>|null
	 */
	protected static ?array $catalog = null;

	/**
	 * Returns the whole catalog keyed by two letter code.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function all(): array {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}

		$catalog = array(
			'en' => array( 'English', 'English', 'en_US', 'US', '🇺🇸' ),
			'es' => array( 'Spanish', 'Español', 'es_ES', 'ES', '🇪🇸' ),
			'fr' => array( 'French', 'Français', 'fr_FR', 'FR', '🇫🇷' ),
			'de' => array( 'German', 'Deutsch', 'de_DE', 'DE', '🇩🇪' ),
			'it' => array( 'Italian', 'Italiano', 'it_IT', 'IT', '🇮🇹' ),
			'pt' => array( 'Portuguese', 'Português', 'pt_PT', 'PT', '🇵🇹' ),
			'br' => array( 'Portuguese (Brazil)', 'Português do Brasil', 'pt_BR', 'BR', '🇧🇷' ),
			'nl' => array( 'Dutch', 'Nederlands', 'nl_NL', 'NL', '🇳🇱' ),
			'pl' => array( 'Polish', 'Polski', 'pl_PL', 'PL', '🇵🇱' ),
			'ru' => array( 'Russian', 'Русский', 'ru_RU', 'RU', '🇷🇺' ),
			'uk' => array( 'Ukrainian', 'Українська', 'uk', 'UA', '🇺🇦' ),
			'tr' => array( 'Turkish', 'Türkçe', 'tr_TR', 'TR', '🇹🇷' ),
			'ar' => array( 'Arabic', 'العربية', 'ar', 'SA', '🇸🇦' ),
			'he' => array( 'Hebrew', 'עברית', 'he_IL', 'IL', '🇮🇱' ),
			'fa' => array( 'Persian', 'فارسی', 'fa_IR', 'IR', '🇮🇷' ),
			'ur' => array( 'Urdu', 'اردو', 'ur', 'PK', '🇵🇰' ),
			'hi' => array( 'Hindi', 'हिन्दी', 'hi_IN', 'IN', '🇮🇳' ),
			'bn' => array( 'Bengali', 'বাংলা', 'bn_BD', 'BD', '🇧🇩' ),
			'id' => array( 'Indonesian', 'Bahasa Indonesia', 'id_ID', 'ID', '🇮🇩' ),
			'ms' => array( 'Malay', 'Bahasa Melayu', 'ms_MY', 'MY', '🇲🇾' ),
			'th' => array( 'Thai', 'ไทย', 'th', 'TH', '🇹🇭' ),
			'vi' => array( 'Vietnamese', 'Tiếng Việt', 'vi', 'VN', '🇻🇳' ),
			'ja' => array( 'Japanese', '日本語', 'ja', 'JP', '🇯🇵' ),
			'ko' => array( 'Korean', '한국어', 'ko_KR', 'KR', '🇰🇷' ),
			'zh' => array( 'Chinese (Simplified)', '简体中文', 'zh_CN', 'CN', '🇨🇳' ),
			'tw' => array( 'Chinese (Traditional)', '繁體中文', 'zh_TW', 'TW', '🇹🇼' ),
			'sv' => array( 'Swedish', 'Svenska', 'sv_SE', 'SE', '🇸🇪' ),
			'no' => array( 'Norwegian', 'Norsk', 'nb_NO', 'NO', '🇳🇴' ),
			'da' => array( 'Danish', 'Dansk', 'da_DK', 'DK', '🇩🇰' ),
			'fi' => array( 'Finnish', 'Suomi', 'fi', 'FI', '🇫🇮' ),
			'cs' => array( 'Czech', 'Čeština', 'cs_CZ', 'CZ', '🇨🇿' ),
			'sk' => array( 'Slovak', 'Slovenčina', 'sk_SK', 'SK', '🇸🇰' ),
			'hu' => array( 'Hungarian', 'Magyar', 'hu_HU', 'HU', '🇭🇺' ),
			'ro' => array( 'Romanian', 'Română', 'ro_RO', 'RO', '🇷🇴' ),
			'bg' => array( 'Bulgarian', 'Български', 'bg_BG', 'BG', '🇧🇬' ),
			'el' => array( 'Greek', 'Ελληνικά', 'el', 'GR', '🇬🇷' ),
			'hr' => array( 'Croatian', 'Hrvatski', 'hr', 'HR', '🇭🇷' ),
			'sr' => array( 'Serbian', 'Српски', 'sr_RS', 'RS', '🇷🇸' ),
			'sl' => array( 'Slovenian', 'Slovenščina', 'sl_SI', 'SI', '🇸🇮' ),
			'et' => array( 'Estonian', 'Eesti', 'et', 'EE', '🇪🇪' ),
			'lv' => array( 'Latvian', 'Latviešu', 'lv', 'LV', '🇱🇻' ),
			'lt' => array( 'Lithuanian', 'Lietuvių', 'lt_LT', 'LT', '🇱🇹' ),
			'ca' => array( 'Catalan', 'Català', 'ca', 'ES', '🇪🇸' ),
			'eu' => array( 'Basque', 'Euskara', 'eu', 'ES', '🇪🇸' ),
			'gl' => array( 'Galician', 'Galego', 'gl_ES', 'ES', '🇪🇸' ),
			'af' => array( 'Afrikaans', 'Afrikaans', 'af', 'ZA', '🇿🇦' ),
			'sw' => array( 'Swahili', 'Kiswahili', 'sw', 'KE', '🇰🇪' ),
			'fil' => array( 'Filipino', 'Filipino', 'fil', 'PH', '🇵🇭' ),
			'is' => array( 'Icelandic', 'Íslenska', 'is_IS', 'IS', '🇮🇸' ),
			'ga' => array( 'Irish', 'Gaeilge', 'ga', 'IE', '🇮🇪' ),
		);

		$normalized = array();

		foreach ( $catalog as $code => $data ) {
			$normalized[ $code ] = array(
				'code'        => $code,
				'name'        => $data[0],
				'native_name' => $data[1],
				'locale'      => $data[2],
				'country'     => $data[3],
				'flag'        => $data[4],
				'direction'   => in_array( $code, self::RTL_CODES, true ) ? 'rtl' : 'ltr',
			);
		}

		/**
		 * Filters the built in language catalog.
		 *
		 * @param array<string,array<string,string>> $normalized Catalog entries.
		 */
		self::$catalog = apply_filters( 'als_language_catalog', $normalized );

		return self::$catalog;
	}

	/**
	 * Returns a single catalog entry, or null.
	 *
	 * @param string $code Two letter language code.
	 * @return array<string,string>|null
	 */
	public static function get( string $code ): ?array {
		$all  = self::all();
		$code = strtolower( trim( $code ) );

		return $all[ $code ] ?? null;
	}

	/**
	 * Whether a code is right-to-left according to the catalog.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function is_rtl( string $code ): bool {
		$code = strtolower( substr( trim( $code ), 0, 3 ) );

		foreach ( self::RTL_CODES as $rtl ) {
			if ( $code === $rtl || str_starts_with( $code, $rtl ) ) {
				return true;
			}
		}

		return false;
	}
}
