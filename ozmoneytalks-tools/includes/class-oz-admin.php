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
		register_setting( 'oz_tools_providers_group', 'oz_tools_providers', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_providers' ),
		) );
	}

	public static function sanitize_providers( $in ) {
		$in    = (array) $in;
		$items = array();
		foreach ( isset( $in['items'] ) ? (array) $in['items'] : array() as $row ) {
			$name = sanitize_text_field( isset( $row['name'] ) ? $row['name'] : '' );
			if ( '' === $name ) {
				continue; // Blank rows are how you delete a provider.
			}
			$items[] = array(
				'name'       => $name,
				'fee_fixed'  => max( 0, min( 500, (float) ( isset( $row['fee_fixed'] ) ? $row['fee_fixed'] : 0 ) ) ),
				'fee_pct'    => max( 0, min( 20, (float) ( isset( $row['fee_pct'] ) ? $row['fee_pct'] : 0 ) ) ),
				'margin_pct' => max( -5, min( 20, (float) ( isset( $row['margin_pct'] ) ? $row['margin_pct'] : 0 ) ) ),
				'url'        => esc_url_raw( isset( $row['url'] ) ? trim( $row['url'] ) : '', array( 'http', 'https' ) ),
				'affiliate'  => empty( $row['affiliate'] ) ? 0 : 1,
				'note'       => mb_substr( sanitize_text_field( isset( $row['note'] ) ? $row['note'] : '' ), 0, 140 ),
			);
		}
		$checked = isset( $in['checked'] ) ? sanitize_text_field( $in['checked'] ) : '';
		return array(
			'checked' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checked ) ? $checked : '',
			'items'   => $items,
		);
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
			'remittance' => array( 'Send money to India', '[oz_remittance]' ),
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

			<h2 id="providers">Money transfer providers</h2>
			<?php $prov = oz_tools_providers(); ?>
			<p class="description" style="max-width:760px">Used by <code>[oz_remittance]</code>. For each provider, get a live quote for sending A$1,000 to India and work out: <strong>fee</strong> (fixed A$ and/or % of the amount) and <strong>FX margin</strong> = how far their rate is below the mid-market rate, as a %. Example: mid-market ₹58.00, their rate ₹57.42 → margin 1%. Clear a name to remove a row. Rows are sorted on the page by what the recipient gets, not by this order.</p>
			<?php if ( ! $prov['checked'] ) : ?>
				<div class="notice notice-warning inline"><p>These are starting estimates, not checked figures. The page tells readers they are estimates until you enter a "last checked" date.</p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'oz_tools_providers_group' ); ?>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th>Name</th><th>Fixed fee (A$)</th><th>Fee %</th><th>FX margin %</th><th>Link</th><th>Affiliate?</th><th>Note shown to readers</th></tr></thead>
					<tbody>
						<?php
						$rows = array_values( $prov['items'] );
						$rows = array_pad( $rows, max( 8, count( $rows ) + 2 ), array( 'name' => '', 'fee_fixed' => '', 'fee_pct' => '', 'margin_pct' => '', 'url' => '', 'affiliate' => 0, 'note' => '' ) );
						foreach ( $rows as $i => $r ) :
							$n = 'oz_tools_providers[items][' . (int) $i . ']';
							?>
							<tr>
								<td><input type="text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $r['name'] ); ?>" style="width:100%"></td>
								<td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $n ); ?>[fee_fixed]" value="<?php echo esc_attr( $r['fee_fixed'] ); ?>" style="width:6em"></td>
								<td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $n ); ?>[fee_pct]" value="<?php echo esc_attr( $r['fee_pct'] ); ?>" style="width:6em"></td>
								<td><input type="number" step="0.01" min="-5" name="<?php echo esc_attr( $n ); ?>[margin_pct]" value="<?php echo esc_attr( $r['margin_pct'] ); ?>" style="width:6em"></td>
								<td><input type="url" name="<?php echo esc_attr( $n ); ?>[url]" value="<?php echo esc_attr( $r['url'] ); ?>" style="width:100%"></td>
								<td style="text-align:center"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[affiliate]" value="1" <?php checked( ! empty( $r['affiliate'] ) ); ?>></td>
								<td><input type="text" name="<?php echo esc_attr( $n ); ?>[note]" value="<?php echo esc_attr( $r['note'] ); ?>" maxlength="140" style="width:100%"></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<label>Figures last checked <input type="date" name="oz_tools_providers[checked]" value="<?php echo esc_attr( $prov['checked'] ); ?>"></label>
					<span class="description">Shown to readers. Re-check at least monthly.</span>
				</p>
				<?php submit_button( 'Save providers' ); ?>
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
