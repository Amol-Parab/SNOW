<?php
/**
 * Settings → OzMoneyTalks Tools: page links, brand colour, weekly email, subscriber list and CSV export.
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Admin {

	const SLUG = 'oz-tools';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_oz_tools_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_oz_tools_test_digest', array( __CLASS__, 'test_digest' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( OZ_TOOLS_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">Settings</a>' );
			return $links;
		} );
	}

	public static function menu() {
		add_options_page( 'OzMoneyTalks Tools', 'OzMoneyTalks Tools', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function register_settings() {
		register_setting( 'oz_tools', 'oz_tools_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function sanitize( $in ) {
		$in  = (array) $in;
		$out = array(
			'accent'        => sanitize_hex_color( isset( $in['accent'] ) ? $in['accent'] : '' ) ?: '#0f766e',
			'site_name'     => sanitize_text_field( isset( $in['site_name'] ) ? $in['site_name'] : '' ),
			'weekly_digest' => empty( $in['weekly_digest'] ) ? 0 : 1,
			'digest_day'    => min( 7, max( 1, (int) ( isset( $in['digest_day'] ) ? $in['digest_day'] : 1 ) ) ),
		);
		foreach ( array( 'checklist', 'rent', 'savings', 'rate_alert', 'remittance', 'privacy' ) as $k ) {
			$out[ 'url_' . $k ] = esc_url_raw( isset( $in[ 'url_' . $k ] ) ? trim( $in[ 'url_' . $k ] ) : '' );
		}
		return $out;
	}

	private static function counts() {
		global $wpdb;
		$table = Oz_Tools_Subscribers::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) n, SUM(target_rate IS NOT NULL) alerts FROM {$table} GROUP BY status", OBJECT_K ); // phpcs:ignore
		return array(
			'confirmed'    => isset( $rows['confirmed'] ) ? (int) $rows['confirmed']->n : 0,
			'pending'      => isset( $rows['pending'] ) ? (int) $rows['pending']->n : 0,
			'unsubscribed' => isset( $rows['unsubscribed'] ) ? (int) $rows['unsubscribed']->n : 0,
			'alerts'       => isset( $rows['confirmed'] ) ? (int) $rows['confirmed']->alerts : 0,
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$s      = oz_tools_settings();
		$counts = self::counts();
		$rate   = Oz_Tools_Rates::get();
		$next   = wp_next_scheduled( Oz_Tools_Alerts::DAILY_HOOK );
		$recent = $wpdb->get_results( 'SELECT email, status, target_rate, weekly, source, created_at FROM ' . Oz_Tools_Subscribers::table() . ' ORDER BY id DESC LIMIT 25' ); // phpcs:ignore
		$days   = array( 1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		$pages  = array(
			'checklist'  => array( 'Settling-in checklist', '[oz_settling_checklist]' ),
			'rent'       => array( 'Rent move-in calculator', '[oz_rent_calculator]' ),
			'savings'    => array( 'India vs Australia savings', '[oz_savings_compare]' ),
			'rate_alert' => array( 'Rate alert', '[oz_rate_alert]' ),
			'remittance' => array( 'Remittance comparator (#2)', 'your existing tool' ),
			'privacy'    => array( 'Privacy policy', '' ),
		);
		?>
		<div class="wrap">
			<h1>OzMoneyTalks Tools</h1>

			<?php if ( isset( $_GET['oz_test'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success"><p>Test weekly email sent to <?php echo esc_html( wp_get_current_user()->user_email ); ?>. If it didn't arrive, set up an SMTP plugin (see below).</p></div>
			<?php endif; ?>

			<h2>At a glance</h2>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
					<tr><th>Confirmed subscribers</th><td><?php echo (int) $counts['confirmed']; ?></td></tr>
					<tr><th>Waiting to confirm</th><td><?php echo (int) $counts['pending']; ?></td></tr>
					<tr><th>Active rate alerts</th><td><?php echo (int) $counts['alerts']; ?></td></tr>
					<tr><th>Unsubscribed</th><td><?php echo (int) $counts['unsubscribed']; ?></td></tr>
					<tr><th>AUD→INR</th><td><?php echo $rate ? esc_html( oz_tools_fmt_rate( $rate['rate'] ) . ' on ' . $rate['date'] . ( $rate['stale'] ? ' (stale — API unreachable)' : '' ) ) : 'Unavailable — check your host allows outgoing requests to api.frankfurter.dev'; ?></td></tr>
					<tr><th>Next alert check</th><td><?php echo $next ? esc_html( wp_date( 'D j M Y, g:ia', $next ) ) : 'Not scheduled'; ?></td></tr>
					<tr><th>Figures last reviewed</th><td><?php echo esc_html( oz_tools_config()['reviewed'] ); ?> <span class="description">— update <code>includes/config.php</code> every 1 July.</span></td></tr>
				</tbody>
			</table>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oz_tools_export' ), 'oz_tools_export' ) ); ?>">Export confirmed subscribers (CSV)</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oz_tools_test_digest' ), 'oz_tools_test_digest' ) ); ?>">Send me a test weekly email</a>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'oz_tools' ); ?>
				<h2>Tool pages</h2>
				<p class="description">Create a page for each tool, paste the shortcode, then enter the page URL here. The tools and emails link to each other using these.</p>
				<table class="form-table" role="presentation">
					<?php foreach ( $pages as $key => $p ) : ?>
						<tr>
							<th scope="row"><label for="oz-url-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $p[0] ); ?></label></th>
							<td>
								<input id="oz-url-<?php echo esc_attr( $key ); ?>" class="regular-text" type="url" name="oz_tools_settings[url_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $s[ 'url_' . $key ] ); ?>" placeholder="https://ozmoneytalks.com/...">
								<?php if ( $p[1] ) : ?><p class="description"><code><?php echo esc_html( $p[1] ); ?></code></p><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2>Look and emails</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="oz-accent">Brand colour</label></th>
						<td><input id="oz-accent" type="text" name="oz_tools_settings[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>" class="small-text" style="width:8em"> <span class="description">Hex, e.g. #0f766e. Used for buttons, bars and highlights.</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="oz-name">Sender name in emails</label></th>
						<td><input id="oz-name" type="text" name="oz_tools_settings[site_name]" value="<?php echo esc_attr( $s['site_name'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row">Weekly email</th>
						<td>
							<label><input type="checkbox" name="oz_tools_settings[weekly_digest]" value="1" <?php checked( $s['weekly_digest'] ); ?>> Send the weekly AUD→INR update on</label>
							<select name="oz_tools_settings[digest_day]">
								<?php foreach ( $days as $n => $d ) : ?>
									<option value="<?php echo (int) $n; ?>" <?php selected( (int) $s['digest_day'], $n ); ?>><?php echo esc_html( $d ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Sent around 8am site time with the latest posts from the past week.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2>Latest signups</h2>
			<table class="widefat striped">
				<thead><tr><th>Email</th><th>Status</th><th>Alert at</th><th>Weekly</th><th>From tool</th><th>Signed up (UTC)</th></tr></thead>
				<tbody>
					<?php if ( ! $recent ) : ?>
						<tr><td colspan="6">No signups yet.</td></tr>
					<?php endif; ?>
					<?php foreach ( $recent as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r->email ); ?></td>
							<td><?php echo esc_html( $r->status ); ?></td>
							<td><?php echo null !== $r->target_rate ? esc_html( oz_tools_fmt_rate( $r->target_rate ) ) : '—'; ?></td>
							<td><?php echo $r->weekly ? 'Yes' : 'No'; ?></td>
							<td><?php echo esc_html( $r->source ); ?></td>
							<td><?php echo esc_html( $r->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Email delivery</h2>
			<p class="description" style="max-width:720px">WordPress sends mail with your host's PHP mail by default, which often lands in spam. Install an SMTP plugin (for example WP Mail SMTP) with a transactional sender such as Brevo, Mailgun or Amazon SES. WP-Cron only runs when someone visits the site — for reliable 8am alerts, ask your host to call <code>wp-cron.php</code> every 15 minutes.</p>
		</div>
		<?php
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'oz_tools_export' ) ) {
			wp_die( 'Not allowed.' );
		}
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT email, target_rate, weekly, source, persona, created_at, confirmed_at FROM " . Oz_Tools_Subscribers::table() . " WHERE status = 'confirmed' ORDER BY id", ARRAY_A ); // phpcs:ignore

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=oz-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'target_rate', 'weekly', 'source', 'persona', 'created_at_utc', 'confirmed_at_utc' ), ',', '"', '' );
		foreach ( $rows as $r ) {
			// Stop spreadsheet formula injection.
			$r = array_map( function ( $v ) {
				return is_string( $v ) && preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
			}, $r );
			fputcsv( $out, $r, ',', '"', '' );
		}
		fclose( $out );
		exit;
	}

	public static function test_digest() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'oz_tools_test_digest' ) ) {
			wp_die( 'Not allowed.' );
		}
		$data = Oz_Tools_Rates::get();
		if ( $data ) {
			oz_tools_mail( wp_get_current_user()->user_email, '[Test] This week: 1 AUD = ' . oz_tools_fmt_rate( $data['rate'] ), Oz_Tools_Alerts::digest_body( $data ) );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&oz_test=1' ) );
		exit;
	}
}
