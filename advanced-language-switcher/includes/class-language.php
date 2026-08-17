<?php
/**
 * Language value object.
 *
 * @package AdvancedLanguageSwitcher
 */

declare( strict_types=1 );

namespace ALS;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable representation of a configured language row.
 */
class Language {

	/**
	 * Row id.
	 *
	 * @var int
	 */
	public int $id = 0;

	/**
	 * English name, e.g. "Spanish".
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * Native name, e.g. "Español".
	 *
	 * @var string
	 */
	public string $native_name = '';

	/**
	 * Short code used in URLs and the switcher, e.g. "es".
	 *
	 * @var string
	 */
	public string $code = '';

	/**
	 * WordPress locale, e.g. "es_ES".
	 *
	 * @var string
	 */
	public string $locale = '';

	/**
	 * ISO country code used for flags.
	 *
	 * @var string
	 */
	public string $country = '';

	/**
	 * Emoji flag.
	 *
	 * @var string
	 */
	public string $flag = '';

	/**
	 * Custom flag image URL.
	 *
	 * @var string
	 */
	public string $flag_url = '';

	/**
	 * Text direction, ltr or rtl.
	 *
	 * @var string
	 */
	public string $direction = 'ltr';

	/**
	 * Whether the language is enabled.
	 *
	 * @var bool
	 */
	public bool $status = true;

	/**
	 * Whether this is the site default language.
	 *
	 * @var bool
	 */
	public bool $is_default = false;

	/**
	 * Manual ordering weight.
	 *
	 * @var int
	 */
	public int $sort_order = 0;

	/**
	 * Builds a language from a database row.
	 *
	 * @param array<string,mixed>|object $row Database row.
	 */
	public function __construct( $row = array() ) {
		$row = (array) $row;

		$this->id          = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$this->name        = isset( $row['name'] ) ? (string) $row['name'] : '';
		$this->native_name = isset( $row['native_name'] ) ? (string) $row['native_name'] : '';
		$this->code        = isset( $row['code'] ) ? (string) $row['code'] : '';
		$this->locale      = isset( $row['locale'] ) ? (string) $row['locale'] : '';
		$this->country     = isset( $row['country'] ) ? (string) $row['country'] : '';
		$this->flag        = isset( $row['flag'] ) ? (string) $row['flag'] : '';
		$this->flag_url    = isset( $row['flag_url'] ) ? (string) $row['flag_url'] : '';
		$this->direction   = isset( $row['direction'] ) && 'rtl' === $row['direction'] ? 'rtl' : 'ltr';
		$this->status      = ! empty( $row['status'] );
		$this->is_default  = ! empty( $row['is_default'] );
		$this->sort_order  = isset( $row['sort_order'] ) ? (int) $row['sort_order'] : 0;
	}

	/**
	 * Whether the language is written right to left.
	 *
	 * @return bool
	 */
	public function is_rtl(): bool {
		return 'rtl' === $this->direction;
	}

	/**
	 * The name shown to visitors: native name when present, English name otherwise.
	 *
	 * @return string
	 */
	public function display_name(): string {
		return '' !== $this->native_name ? $this->native_name : $this->name;
	}

	/**
	 * The uppercase short code shown in the switcher.
	 *
	 * @return string
	 */
	public function display_code(): string {
		return strtoupper( $this->code );
	}

	/**
	 * HTML lang attribute value, e.g. "es-ES".
	 *
	 * @return string
	 */
	public function html_lang(): string {
		$locale = '' !== $this->locale ? $this->locale : $this->code;

		return str_replace( '_', '-', $locale );
	}

	/**
	 * Array form used by REST responses and the Elementor editor.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'id'          => $this->id,
			'name'        => $this->name,
			'native_name' => $this->native_name,
			'code'        => $this->code,
			'locale'      => $this->locale,
			'country'     => $this->country,
			'flag'        => $this->flag,
			'flag_url'    => $this->flag_url,
			'direction'   => $this->direction,
			'status'      => $this->status,
			'is_default'  => $this->is_default,
			'sort_order'  => $this->sort_order,
			'html_lang'   => $this->html_lang(),
		);
	}
}
