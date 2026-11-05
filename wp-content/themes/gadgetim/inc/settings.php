<?php
/**
 * Storefront settings tab (Gadgetim → Settings → Storefront) through the plugin's Settings\Schema.
 *
 * Paths (read them with gadgetim_setting() / gadgetim_list_setting()):
 *   theme.promo_messages   i18n repeater  rotating promo strip lines (fallback: trust.badges)
 *   theme.search_popular   i18n repeater  "popular searches" chips in the empty search dropdown
 *   theme.campaign_end     text           real end of the amazing-offers campaign (Y-m-d H:i, site time)
 *   theme.enamad_url / theme.enamad_img          eNamad trust seal link + image URL
 *   theme.samandehi_url / theme.samandehi_img    Samandehi seal link + image URL
 *   theme.newsletter       toggle         show the footer newsletter form
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * init: registers the Storefront tab when the plugin's settings schema exists.
 */
function gadgetim_register_settings_tab(): void {
	if ( ! class_exists( 'Gadgetim\Core\Settings\Schema' ) || ! method_exists( 'Gadgetim\Core\Settings\Schema', 'add_tab' ) ) {
		return;
	}
	Gadgetim\Core\Settings\Schema::add_tab(
		'theme',
		__( 'Storefront', 'gadgetim' ),
		'manage_woocommerce',
		[
			'promo'  => [
				'label'  => __( 'Header and search', 'gadgetim' ),
				'fields' => [
					'theme.promo_messages' => [
						'type'        => 'repeater',
						'i18n'        => true,
						'label'       => __( 'Promo strip messages', 'gadgetim' ),
						'description' => __( 'Short lines rotating in the coloured strip above the header. Empty: the trust badges are used.', 'gadgetim' ),
						'default'     => [],
					],
					'theme.search_popular' => [
						'type'        => 'repeater',
						'i18n'        => true,
						'label'       => __( 'Popular searches', 'gadgetim' ),
						'description' => __( 'Suggested queries shown when the search box is opened empty.', 'gadgetim' ),
						'default'     => [
							'fa' => [ 'پاوربانک', 'هندزفری بلوتوثی', 'JBL', 'شارژر فست', 'فلش مموری', 'کابل تایپ سی' ],
							'en' => [],
							'ar' => [],
						],
					],
					'theme.campaign_end'   => [
						'type'        => 'text',
						'label'       => __( 'Amazing offers end', 'gadgetim' ),
						'description' => __( 'Real end of the current campaign, format 2026-11-30 23:59 (site time). Empty: countdowns use each product’s own sale end date, or are hidden.', 'gadgetim' ),
						'placeholder' => '2026-11-30 23:59',
						'default'     => '',
					],
				],
			],
			'footer' => [
				'label'  => __( 'Footer', 'gadgetim' ),
				'fields' => [
					'theme.newsletter'     => [
						'type'    => 'toggle',
						'label'   => __( 'Show the newsletter form', 'gadgetim' ),
						'default' => true,
					],
					'theme.enamad_url'     => [
						'type'  => 'url',
						'label' => __( 'eNamad seal link', 'gadgetim' ),
					],
					'theme.enamad_img'     => [
						'type'  => 'url',
						'label' => __( 'eNamad seal image URL', 'gadgetim' ),
					],
					'theme.samandehi_url'  => [
						'type'  => 'url',
						'label' => __( 'Samandehi seal link', 'gadgetim' ),
					],
					'theme.samandehi_img'  => [
						'type'  => 'url',
						'label' => __( 'Samandehi seal image URL', 'gadgetim' ),
					],
				],
			],
		],
		[ 'order' => 40 ]
	);
}
add_action( 'init', 'gadgetim_register_settings_tab', 5 );

/**
 * Per-language list setting (i18n repeater): current language, else Persian.
 *
 * @return string[]
 */
function gadgetim_list_setting( string $path ): array {
	$value = gadgetim_setting( $path, [] );
	if ( ! is_array( $value ) ) {
		return [];
	}
	if ( array_is_list( $value ) ) {
		$list = $value;
	} else {
		$list = $value[ gadgetim_lang() ] ?? [];
		if ( ! is_array( $list ) || ! array_filter( $list ) ) {
			$list = $value['fa'] ?? [];
		}
	}
	return array_values(
		array_filter(
			array_map(
				static fn( $v ): string => is_scalar( $v ) ? trim( (string) $v ) : '',
				(array) $list
			)
		)
	);
}

/**
 * Timestamp of the admin-set campaign end (0 when unset, unparsable or already past).
 */
function gadgetim_campaign_end(): int {
	$raw = trim( (string) gadgetim_setting( 'theme.campaign_end', '' ) );
	if ( '' === $raw ) {
		return 0;
	}
	try {
		$date = new DateTimeImmutable( gadgetim_ascii_digits( $raw ), wp_timezone() );
	} catch ( Exception $e ) {
		return 0;
	}
	$ts = $date->getTimestamp();
	return $ts > time() ? $ts : 0;
}
