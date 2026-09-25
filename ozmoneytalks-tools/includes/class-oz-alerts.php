<?php
/**
 * Daily job: send rate alerts that have been hit and, on digest day, the weekly email.
 *
 * Runs on WP-Cron at about 8am site time. ECB rates are published once a day in the
 * European afternoon, so checking more often adds nothing.
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Alerts {

	const DAILY_HOOK  = 'oz_tools_daily';
	const DIGEST_HOOK = 'oz_tools_digest_batch';
	const BATCH       = 40;

	public static function init() {
		add_action( self::DAILY_HOOK, array( __CLASS__, 'run_daily' ) );
		add_action( self::DIGEST_HOOK, array( __CLASS__, 'send_digest_batch' ), 10, 2 );
		// Re-schedule if the event went missing (e.g. plugin files updated without reactivation).
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
				self::schedule();
			}
		} );
	}

	public static function schedule() {
		if ( wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}
		$next = new DateTime( 'today 08:00', wp_timezone() );
		if ( $next->getTimestamp() <= time() ) {
			$next->modify( '+1 day' );
		}
		wp_schedule_event( $next->getTimestamp(), 'daily', self::DAILY_HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_clear_scheduled_hook( self::DIGEST_HOOK );
	}

	public static function run_daily() {
		$data = Oz_Tools_Rates::get( true );
		if ( ! $data || ! empty( $data['stale'] ) ) {
			return; // Never alert on an old rate.
		}
		self::send_rate_alerts( $data );

		$s = oz_tools_settings();
		if ( $s['weekly_digest'] && (int) wp_date( 'N' ) === (int) $s['digest_day'] ) {
			self::send_digest_batch( 0, wp_date( 'Y-m-d' ) );
		}
	}

	/**
	 * Alerts are one-shot: once sent, the target is cleared and the reader can set a new one.
	 */
	public static function send_rate_alerts( $data ) {
		global $wpdb;
		$table = Oz_Tools_Subscribers::table();
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'confirmed' AND target_rate IS NOT NULL AND target_rate <= %f",
			$data['rate']
		) );
		$urls = oz_tools_urls();

		foreach ( $rows as $row ) {
			$body = '<p style="font-size:18px"><strong>1 AUD = ' . esc_html( oz_tools_fmt_rate( $data['rate'] ) ) . '</strong></p>'
				. '<p>The Australian dollar has reached your target of ' . esc_html( oz_tools_fmt_rate( $row->target_rate ) )
				. ' (European Central Bank reference rate for ' . esc_html( wp_date( 'j M Y', strtotime( $data['date'] ) ) ) . ').</p>'
				. '<p>Transfer providers add a margin on top of this rate, so compare the total amount the recipient gets before you send.</p>';
			if ( $urls['remittance'] ) {
				$body .= oz_tools_mail_button( $urls['remittance'], 'Compare transfer costs' );
			}
			if ( $urls['rate_alert'] ) {
				$body .= '<p><a href="' . esc_url( $urls['rate_alert'] ) . '">Set a new alert</a></p>';
			}
			$sent = oz_tools_mail( $row->email, 'AUD→INR has hit ' . oz_tools_fmt_rate( $data['rate'] ), $body, Oz_Tools_Subscribers::link( 'unsubscribe', $row->token ) );
			if ( $sent ) {
				$wpdb->update( $table, array( 'target_rate' => null, 'last_alert_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ) );
			}
		}
	}

	/**
	 * Weekly email, sent in batches so a large list doesn't time out one cron run.
	 * $week guards against sending twice in the same week.
	 */
	public static function send_digest_batch( $after_id, $week ) {
		global $wpdb;
		$table = Oz_Tools_Subscribers::table();
		$data  = Oz_Tools_Rates::get();
		if ( ! $data ) {
			return;
		}
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'confirmed' AND weekly = 1 AND id > %d
			 AND ( last_digest_at IS NULL OR last_digest_at < %s ) ORDER BY id ASC LIMIT %d",
			$after_id,
			gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ),
			self::BATCH
		) );
		if ( ! $rows ) {
			return;
		}

		$body_core = self::digest_body( $data );
		$subject   = 'This week: 1 AUD = ' . oz_tools_fmt_rate( $data['rate'] );
		foreach ( $rows as $row ) {
			oz_tools_mail( $row->email, $subject, $body_core, Oz_Tools_Subscribers::link( 'unsubscribe', $row->token ) );
			$wpdb->update( $table, array( 'last_digest_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ) );
		}

		if ( count( $rows ) === self::BATCH ) {
			$last = end( $rows );
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::DIGEST_HOOK, array( (int) $last->id, $week ) );
		}
	}

	public static function digest_body( $data ) {
		$hist  = $data['history'];
		$rates = wp_list_pluck( $hist, 1 );
		$week  = count( $hist ) > 5 ? $hist[ count( $hist ) - 6 ][1] : $hist[0][1];
		$diff  = $data['rate'] - $week;
		$dir   = $diff >= 0 ? 'up' : 'down';
		$urls  = oz_tools_urls();

		$body = '<p style="font-size:18px;margin-top:0"><strong>1 AUD = ' . esc_html( oz_tools_fmt_rate( $data['rate'] ) ) . '</strong></p>'
			. '<p>That\'s ' . $dir . ' ' . esc_html( '₹' . number_format( abs( $diff ), 2 ) ) . ' on last week. '
			. 'Over the past 30 days it has ranged from ' . esc_html( oz_tools_fmt_rate( min( $rates ) ) )
			. ' to ' . esc_html( oz_tools_fmt_rate( max( $rates ) ) ) . '.</p>';

		$posts = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish', 'date_query' => array( array( 'after' => '8 days ago' ) ) ) );
		if ( $posts ) {
			$body .= '<h3 style="font-size:16px;margin:20px 0 8px">New on the site</h3><ul style="padding-left:20px;margin:0">';
			foreach ( $posts as $p ) {
				$body .= '<li style="margin-bottom:6px"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
			}
			$body .= '</ul>';
		}

		$links = array_filter( array(
			'Compare transfer costs'         => $urls['remittance'],
			'India vs Australia savings'     => $urls['savings'],
			'Set or change your rate alert'  => $urls['rate_alert'],
		) );
		if ( $links ) {
			$body .= '<h3 style="font-size:16px;margin:20px 0 8px">Tools</h3><p>';
			$parts = array();
			foreach ( $links as $label => $url ) {
				$parts[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
			$body .= implode( ' · ', $parts ) . '</p>';
		}
		return apply_filters( 'oz_tools_digest_body', $body, $data );
	}
}
