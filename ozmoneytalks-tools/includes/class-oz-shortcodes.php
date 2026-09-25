<?php
/**
 * Shortcodes:
 *   [oz_settling_checklist persona="student"]
 *   [oz_rent_calculator state="NSW"]
 *   [oz_savings_compare]
 *   [oz_rate_alert]
 *   [oz_remittance]
 *   [oz_signup source="other" title="..."]   (standalone email signup box)
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Shortcodes {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'oz_settling_checklist', array( __CLASS__, 'checklist' ) );
		add_shortcode( 'oz_rent_calculator', array( __CLASS__, 'rent' ) );
		add_shortcode( 'oz_savings_compare', array( __CLASS__, 'savings' ) );
		add_shortcode( 'oz_rate_alert', array( __CLASS__, 'rate_alert' ) );
		add_shortcode( 'oz_remittance', array( __CLASS__, 'remittance' ) );
		add_shortcode( 'oz_signup', array( __CLASS__, 'signup' ) );
	}

	public static function register_assets() {
		wp_register_style( 'oz-tools', OZ_TOOLS_URL . 'assets/css/oz-tools.css', array(), OZ_TOOLS_VERSION );
		wp_register_script( 'oz-calc', OZ_TOOLS_URL . 'assets/js/oz-calc.js', array(), OZ_TOOLS_VERSION, true );
		wp_register_script( 'oz-tools', OZ_TOOLS_URL . 'assets/js/oz-tools.js', array( 'oz-calc' ), OZ_TOOLS_VERSION, true );

		// Load styles in <head> on pages we know use a tool (classic themes would otherwise print them late).
		if ( is_singular() ) {
			$content = (string) get_post_field( 'post_content', get_queried_object_id() );
			foreach ( array( 'oz_settling_checklist', 'oz_rent_calculator', 'oz_savings_compare', 'oz_rate_alert', 'oz_remittance', 'oz_signup' ) as $tag ) {
				if ( has_shortcode( $content, $tag ) ) {
					self::enqueue();
					break;
				}
			}
		}
	}

	/**
	 * Enqueue once per page, the first time any shortcode renders.
	 */
	private static function enqueue() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		if ( ! wp_script_is( 'oz-tools', 'registered' ) ) {
			self::register_assets();
		}
		$c = oz_tools_config();
		$s = oz_tools_settings();

		wp_enqueue_style( 'oz-tools' );
		$accent = sanitize_hex_color( $s['accent'] );
		if ( $accent ) {
			wp_add_inline_style( 'oz-tools', '.oz-tool{--oz-accent:' . $accent . '}' );
		}

		wp_enqueue_script( 'oz-tools' );
		wp_add_inline_script( 'oz-tools', 'window.OZTools = ' . wp_json_encode( array(
			'rest'     => esc_url_raw( rest_url( 'oz-tools/v1/' ) ),
			'reviewed' => $c['reviewed'],
			'tax'      => $c['tax'],
			'india'    => $c['india'],
			'savings'  => $c['savings'],
			'states'   => $c['states'],
			'setup'    => $c['setup_costs'],
			'furniture' => $c['furniture'],
			'afford'   => $c['affordability'],
			'providers' => array_map( function ( $p ) {
				// Only what the page needs; notes and links are shown as text/URLs.
				return array(
					'name'       => $p['name'],
					'fee_fixed'  => (float) $p['fee_fixed'],
					'fee_pct'    => (float) $p['fee_pct'],
					'margin_pct' => (float) $p['margin_pct'],
					'url'        => $p['url'],
					'affiliate'  => ! empty( $p['affiliate'] ),
					'note'       => $p['note'],
				);
			}, oz_tools_providers()['items'] ),
			'urls'     => oz_tools_urls(),
		) ) . ';', 'before' );
	}

	private static function render( $template, $vars ) {
		self::enqueue();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		ob_start();
		include OZ_TOOLS_DIR . 'templates/' . $template . '.php';
		return ob_get_clean();
	}

	public static function checklist( $atts ) {
		$atts = shortcode_atts( array( 'persona' => 'student' ), $atts, 'oz_settling_checklist' );
		$data = oz_tools_checklist();
		$persona = isset( $data['personas'][ $atts['persona'] ] ) ? $atts['persona'] : key( $data['personas'] );
		return self::render( 'checklist', array( 'data' => $data, 'active' => $persona, 'urls' => oz_tools_urls() ) );
	}

	public static function rent( $atts ) {
		$atts   = shortcode_atts( array( 'state' => 'NSW' ), $atts, 'oz_rent_calculator' );
		$config = oz_tools_config();
		$state  = isset( $config['states'][ strtoupper( $atts['state'] ) ] ) ? strtoupper( $atts['state'] ) : 'NSW';
		return self::render( 'rent', array( 'config' => $config, 'state' => $state ) );
	}

	public static function savings() {
		return self::render( 'savings', array( 'config' => oz_tools_config() ) );
	}

	public static function rate_alert() {
		return self::render( 'rate-alert', array( 'urls' => oz_tools_urls() ) );
	}

	public static function remittance() {
		return self::render( 'remittance', array(
			'config'    => oz_tools_config(),
			'providers' => oz_tools_providers(),
			'urls'      => oz_tools_urls(),
		) );
	}

	public static function signup( $atts ) {
		$atts = shortcode_atts( array(
			'source' => 'other',
			'title'  => 'Get the weekly AUD→INR update',
			'text'   => 'One short email a week: the rupee rate, new guides and tools for Indians in Australia.',
		), $atts, 'oz_signup' );
		$source = in_array( $atts['source'], Oz_Tools_Subscribers::SOURCES, true ) ? $atts['source'] : 'other';
		return '<div class="oz-tool">' . self::signup_box( $source, $atts['title'], $atts['text'] ) . '</div>';
	}

	/**
	 * Small newsletter box shown at the end of each tool.
	 */
	public static function signup_box( $source, $title, $text ) {
		self::enqueue();
		$privacy = oz_tools_urls()['privacy'];
		$id      = wp_unique_id( 'oz-su-' );
		ob_start();
		include OZ_TOOLS_DIR . 'templates/signup.php';
		return ob_get_clean();
	}
}
