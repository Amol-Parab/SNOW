<?php
/**
 * Shared helpers.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Figures that change over time (tax, state rules, defaults).
 */
function oz_tools_config() {
	static $config = null;
	if ( null === $config ) {
		$config = apply_filters( 'oz_tools_config', require OZ_TOOLS_DIR . 'includes/config.php' );
	}
	return $config;
}

/**
 * Checklist personas, phases and items.
 */
function oz_tools_checklist() {
	static $data = null;
	if ( null === $data ) {
		$data = apply_filters( 'oz_tools_checklist', require OZ_TOOLS_DIR . 'includes/checklist-data.php' );
	}
	return $data;
}

/**
 * Checklist items that apply to one persona, grouped by phase.
 */
function oz_tools_checklist_for( $persona ) {
	$data   = oz_tools_checklist();
	$groups = array();
	foreach ( $data['phases'] as $key => $label ) {
		$groups[ $key ] = array( 'label' => $label, 'items' => array() );
	}
	foreach ( $data['items'] as $item ) {
		if ( in_array( 'all', $item['personas'], true ) || in_array( $persona, $item['personas'], true ) ) {
			$groups[ $item['phase'] ]['items'][] = $item;
		}
	}
	return array_filter( $groups, function ( $g ) {
		return ! empty( $g['items'] );
	} );
}

/**
 * Admin settings, merged with defaults.
 */
function oz_tools_settings() {
	$defaults = array(
		'accent'        => '#0f766e',
		'site_name'     => get_bloginfo( 'name' ),
		'weekly_digest' => 1,
		'digest_day'    => 1, // 1 = Monday (ISO-8601).
		'url_checklist' => '',
		'url_rent'      => '',
		'url_savings'   => '',
		'url_rate_alert' => '',
		'url_remittance' => '',
		'url_privacy'   => get_privacy_policy_url(),
	);
	$s = wp_parse_args( (array) get_option( 'oz_tools_settings', array() ), $defaults );
	if ( '' === trim( (string) $s['site_name'] ) ) {
		$s['site_name'] = $defaults['site_name'];
	}
	return $s;
}

/**
 * Remittance providers: the admin-edited table if saved, otherwise the config defaults.
 *
 * @return array { checked: 'Y-m-d' or '', items: [ {name, fee_fixed, fee_pct, margin_pct, url, affiliate, note} ] }
 */
function oz_tools_providers() {
	$saved = get_option( 'oz_tools_providers' );
	if ( is_array( $saved ) && ! empty( $saved['items'] ) ) {
		return $saved;
	}
	return array(
		'checked' => '',
		'items'   => oz_tools_config()['remittance']['providers'],
	);
}

/**
 * Public URLs of each tool page, used for cross-links between tools and in emails.
 */
function oz_tools_urls() {
	$s = oz_tools_settings();
	return array(
		'checklist'  => $s['url_checklist'],
		'rent'       => $s['url_rent'],
		'savings'    => $s['url_savings'],
		'rate_alert' => $s['url_rate_alert'],
		'remittance' => $s['url_remittance'],
		'privacy'    => $s['url_privacy'],
	);
}

/**
 * Send an HTML email wrapped in a simple branded layout.
 */
function oz_tools_mail( $to, $subject, $body_html, $unsubscribe_url = '' ) {
	$s      = oz_tools_settings();
	$accent = sanitize_hex_color( $s['accent'] ) ?: '#0f766e';
	$site   = esc_html( $s['site_name'] );
	$footer = '<p style="font-size:12px;color:#6b7280;margin:24px 0 0">General information only, not personal financial advice. ';
	if ( $unsubscribe_url ) {
		$footer .= '<a href="' . esc_url( $unsubscribe_url ) . '" style="color:#6b7280">Unsubscribe</a> · ';
	}
	$footer .= '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:#6b7280">' . $site . '</a></p>';

	$html = '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">'
		. '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
		. '<div style="font-weight:bold;font-size:18px;color:' . $accent . ';margin-bottom:12px">' . $site . '</div>'
		. '<div style="background:#fff;border-radius:8px;padding:24px;line-height:1.55;font-size:15px">' . $body_html . '</div>'
		. $footer . '</div></body></html>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( $unsubscribe_url ) {
		$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $unsubscribe_url ) . '>';
	}
	return wp_mail( $to, $subject, $html, $headers );
}

/**
 * A button-style link for emails.
 */
function oz_tools_mail_button( $url, $label ) {
	$accent = sanitize_hex_color( oz_tools_settings()['accent'] ) ?: '#0f766e';
	return '<p style="margin:20px 0"><a href="' . esc_url( $url ) . '" style="background:' . $accent
		. ';color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;display:inline-block;font-weight:bold">'
		. esc_html( $label ) . '</a></p>';
}

/**
 * style="" attribute for each tool's root element, carrying the brand colour.
 * An attribute survives CSS optimisers (LiteSpeed, WP Rocket) that strip or delay inline <style> blocks.
 */
function oz_tools_root_style() {
	$accent = sanitize_hex_color( oz_tools_settings()['accent'] );
	return $accent ? ' style="--oz-accent:' . esc_attr( $accent ) . '"' : '';
}

/**
 * Format an AUD→INR rate for display.
 */
function oz_tools_fmt_rate( $rate ) {
	return '₹' . number_format( (float) $rate, 2 );
}
