<?php
/**
 * Email list: signup (double opt-in), confirm, unsubscribe, and the
 * "email me my checklist" request.
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Subscribers {

	const DB_VERSION = '1';
	const SOURCES    = array( 'checklist', 'rent', 'savings', 'rate_alert', 'remittance', 'other' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oz_subscribers';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			token char(32) NOT NULL,
			target_rate decimal(10,4) DEFAULT NULL,
			weekly tinyint(1) NOT NULL DEFAULT 1,
			source varchar(20) NOT NULL DEFAULT 'other',
			persona varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			confirmed_at datetime DEFAULT NULL,
			confirm_sent_at datetime DEFAULT NULL,
			last_alert_at datetime DEFAULT NULL,
			last_digest_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY token (token)
		) {$charset};" );
		update_option( 'oz_tools_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'oz_tools_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_link' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function register_routes() {
		register_rest_route( 'oz-tools/v1', '/subscribe', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'rest_subscribe' ),
			'args'                => array(
				'email'       => array( 'type' => 'string', 'required' => true ),
				'consent'     => array( 'type' => 'boolean', 'default' => false ),
				'weekly'      => array( 'type' => 'boolean', 'default' => true ),
				'target_rate' => array( 'type' => array( 'number', 'string', 'null' ), 'default' => null ),
				'source'      => array( 'type' => 'string', 'default' => 'other' ),
				'persona'     => array( 'type' => 'string', 'default' => '' ),
				'website'     => array( 'type' => 'string', 'default' => '' ), // Honeypot.
			),
		) );
	}

	public static function rest_subscribe( WP_REST_Request $req ) {
		// Bots fill the hidden field. Pretend it worked.
		if ( '' !== trim( (string) $req['website'] ) ) {
			return array( 'ok' => true, 'message' => 'Thanks! Check your inbox.' );
		}

		$email = sanitize_email( (string) $req['email'] );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'oz_bad_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}
		if ( ! self::allow_request() ) {
			return new WP_Error( 'oz_rate_limited', 'Too many requests. Please try again in a little while.', array( 'status' => 429 ) );
		}

		$source  = in_array( $req['source'], self::SOURCES, true ) ? $req['source'] : 'other';
		$persona = sanitize_key( (string) $req['persona'] );
		$consent = (bool) $req['consent'];
		$target  = self::clean_target( $req['target_rate'] );
		$sent_checklist = false;

		if ( 'checklist' === $source ) {
			$personas = oz_tools_checklist()['personas'];
			if ( ! isset( $personas[ $persona ] ) ) {
				return new WP_Error( 'oz_bad_persona', 'Please pick a checklist first.', array( 'status' => 400 ) );
			}
			$sent_checklist = self::send_checklist( $email, $persona );
		} elseif ( ! $consent ) {
			return new WP_Error( 'oz_no_consent', 'Please tick the box to agree to receive emails.', array( 'status' => 400 ) );
		}

		if ( 'rate_alert' === $source && null === $target && ! $req['weekly'] ) {
			return new WP_Error( 'oz_nothing', 'Set a target rate or choose the weekly email.', array( 'status' => 400 ) );
		}

		if ( ! $consent ) {
			return array(
				'ok'      => true,
				'message' => $sent_checklist ? 'Sent! Check your inbox for your checklist.' : 'Sorry, we could not send the email. Please try again later.',
			);
		}

		$result = self::upsert( $email, array(
			'target_rate' => $target,
			'weekly'      => $req['weekly'] ? 1 : 0,
			'source'      => $source,
			'persona'     => $persona,
		) );

		if ( 'confirmed' === $result ) {
			$message = 'rate_alert' === $source && null !== $target
				? 'You\'re already subscribed — your alert is now set to ' . oz_tools_fmt_rate( $target ) . '.'
				: 'You\'re already subscribed. Thanks!';
		} else {
			$message = 'Almost done — check your inbox and click the link to confirm.';
		}
		if ( $sent_checklist ) {
			$message = 'Checklist sent! ' . $message;
		}
		return array( 'ok' => true, 'message' => $message );
	}

	/**
	 * Create or update a subscriber. Returns 'confirmed' if they were already confirmed,
	 * otherwise 'pending' (a confirmation email has been sent, or was sent recently).
	 */
	public static function upsert( $email, $prefs ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$row   = self::find_by_email( $email );

		if ( $row && 'confirmed' === $row->status ) {
			$update = array( 'weekly' => max( (int) $row->weekly, $prefs['weekly'] ) );
			if ( null !== $prefs['target_rate'] ) {
				$update['target_rate'] = $prefs['target_rate'];
			}
			$wpdb->update( $table, $update, array( 'id' => $row->id ) );
			return 'confirmed';
		}

		$token = $row ? $row->token : wp_generate_password( 32, false );
		$data  = array(
			'email'       => $email,
			'status'      => 'pending',
			'token'       => $token,
			'target_rate' => $prefs['target_rate'],
			'weekly'      => $prefs['weekly'],
			'source'      => $prefs['source'],
			'persona'     => $prefs['persona'],
		);

		if ( $row ) {
			$wpdb->update( $table, $data, array( 'id' => $row->id ) );
			// Don't resend if we sent one in the last 10 minutes (double clicks, abuse).
			if ( $row->confirm_sent_at && strtotime( $row->confirm_sent_at . ' UTC' ) > time() - 10 * MINUTE_IN_SECONDS ) {
				return 'pending';
			}
			$id = $row->id;
		} else {
			$data['created_at'] = $now;
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}

		self::send_confirmation( $email, $token, $prefs );
		$wpdb->update( $table, array( 'confirm_sent_at' => $now ), array( 'id' => $id ) );
		return 'pending';
	}

	public static function find_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s', $email ) );
	}

	public static function find_by_token( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ) );
	}

	public static function link( $action, $token ) {
		return add_query_arg( array( 'oz_tools' => $action, 't' => $token ), home_url( '/' ) );
	}

	private static function clean_target( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$v = (float) $value;
		// Sanity range for AUD→INR.
		return ( $v >= 10 && $v <= 500 ) ? round( $v, 2 ) : null;
	}

	/**
	 * Simple per-IP limit: 10 requests per hour.
	 * Behind Cloudflare or another proxy, REMOTE_ADDR may be the proxy's address, which makes
	 * all visitors share one limit. Use the 'oz_tools_client_ip' filter to supply the real IP.
	 */
	private static function allow_request() {
		$ip  = apply_filters( 'oz_tools_client_ip', isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
		$key = 'oz_tools_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= (int) apply_filters( 'oz_tools_hourly_limit', 10 ) ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	private static function send_confirmation( $email, $token, $prefs ) {
		$what = array();
		if ( null !== $prefs['target_rate'] ) {
			$what[] = 'an alert when 1 AUD reaches <strong>' . esc_html( oz_tools_fmt_rate( $prefs['target_rate'] ) ) . '</strong>';
		}
		if ( $prefs['weekly'] ) {
			$what[] = 'a short weekly email with the AUD→INR rate and new money guides';
		}
		$body = '<p>Please confirm your email address.</p>';
		if ( $what ) {
			$body .= '<p>You asked for ' . implode( ' and ', $what ) . '.</p>';
		}
		$body .= oz_tools_mail_button( self::link( 'confirm', $token ), 'Yes, confirm my email' )
			. '<p style="color:#6b7280;font-size:13px">If you didn\'t request this, ignore this email and you won\'t hear from us.</p>';
		oz_tools_mail( $email, 'Confirm your email', $body );
	}

	private static function send_checklist( $email, $persona ) {
		$data   = oz_tools_checklist();
		$urls   = oz_tools_urls();
		$groups = oz_tools_checklist_for( $persona );
		$label  = $data['personas'][ $persona ]['label'];

		$body = '<p>Here is your settling-in checklist for <strong>' . esc_html( $label ) . '</strong>.</p>';
		foreach ( $groups as $group ) {
			$body .= '<h3 style="margin:20px 0 8px;font-size:16px">' . esc_html( $group['label'] ) . '</h3><ul style="padding-left:20px;margin:0">';
			foreach ( $group['items'] as $item ) {
				$body .= '<li style="margin-bottom:10px"><strong>' . esc_html( $item['title'] ) . '</strong><br>'
					. '<span style="color:#4b5563">' . esc_html( $item['why'] ) . '</span>';
				if ( ! empty( $item['link'] ) ) {
					$body .= '<br><a href="' . esc_url( $item['link'][1] ) . '">' . esc_html( $item['link'][0] ) . '</a>';
				}
				if ( ! empty( $item['tool'] ) && ! empty( $urls[ $item['tool'] ] ) ) {
					$body .= '<br><a href="' . esc_url( $urls[ $item['tool'] ] ) . '">Open the calculator</a>';
				}
				$body .= '</li>';
			}
			$body .= '</ul>';
		}
		if ( $urls['checklist'] ) {
			$body .= oz_tools_mail_button( $urls['checklist'], 'Tick items off online' );
		}
		return oz_tools_mail( $email, 'Your settling-in checklist: ' . $label, $body );
	}

	/**
	 * Confirm and unsubscribe links. GET shows a button; POST does the action.
	 * (Email security scanners open links automatically, so GET must not change anything.)
	 */
	public static function handle_link() {
		if ( empty( $_GET['oz_tools'] ) || empty( $_GET['t'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['oz_tools'] ) );
		if ( ! in_array( $action, array( 'confirm', 'unsubscribe' ), true ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['t'] ) );
		$row   = self::find_by_token( $token );
		$home  = array( 'response' => 200, 'link_url' => home_url( '/' ), 'link_text' => 'Back to ' . oz_tools_settings()['site_name'] );

		if ( ! $row ) {
			wp_die( '<p>This link is invalid or has expired.</p>', 'Link not valid', $home );
		}

		$is_post = 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' );

		if ( ! $is_post ) {
			$label = 'confirm' === $action ? 'Confirm my email' : 'Unsubscribe me';
			$intro = 'confirm' === $action
				? 'Click below to confirm <strong>' . esc_html( $row->email ) . '</strong>.'
				: 'Click below to stop all emails to <strong>' . esc_html( $row->email ) . '</strong>.';
			$form = '<p>' . $intro . '</p><form method="post" action="' . esc_url( self::link( $action, $token ) ) . '">'
				. '<button type="submit" class="button button-primary" style="padding:8px 16px;font-size:15px">' . esc_html( $label ) . '</button></form>';
			wp_die( $form, $label, array( 'response' => 200 ) );
		}

		global $wpdb;
		if ( 'confirm' === $action ) {
			if ( 'confirmed' !== $row->status ) {
				$wpdb->update( self::table(), array( 'status' => 'confirmed', 'confirmed_at' => current_time( 'mysql', true ) ), array( 'id' => $row->id ) );
				do_action( 'oz_tools_subscriber_confirmed', $row->email, self::find_by_token( $token ) );
			}
			$msg = '<p><strong>You\'re in.</strong> ';
			if ( null !== $row->target_rate ) {
				$msg .= 'We\'ll email you when 1 AUD reaches ' . esc_html( oz_tools_fmt_rate( $row->target_rate ) ) . '. ';
			}
			if ( $row->weekly ) {
				$msg .= 'Your first weekly update arrives next week.';
			}
			wp_die( $msg . '</p>', 'Subscription confirmed', $home );
		}

		$wpdb->update( self::table(), array( 'status' => 'unsubscribed', 'target_rate' => null ), array( 'id' => $row->id ) );
		do_action( 'oz_tools_subscriber_unsubscribed', $row->email );
		wp_die( '<p>You\'ve been unsubscribed. You won\'t receive any more emails from us.</p>', 'Unsubscribed', $home );
	}

	/* ---- WordPress privacy tools (Tools → Export/Erase Personal Data) ---- */

	public static function register_exporter( $exporters ) {
		$exporters['oz-tools'] = array(
			'exporter_friendly_name' => 'OzMoneyTalks Tools email list',
			'callback'               => function ( $email ) {
				$row  = self::find_by_email( $email );
				$data = array();
				if ( $row ) {
					$data[] = array(
						'group_id'    => 'oz-tools',
						'group_label' => 'Email list',
						'item_id'     => 'oz-sub-' . $row->id,
						'data'        => array(
							array( 'name' => 'Email', 'value' => $row->email ),
							array( 'name' => 'Status', 'value' => $row->status ),
							array( 'name' => 'Rate alert', 'value' => (string) $row->target_rate ),
							array( 'name' => 'Weekly email', 'value' => $row->weekly ? 'Yes' : 'No' ),
							array( 'name' => 'Signed up', 'value' => $row->created_at ),
						),
					);
				}
				return array( 'data' => $data, 'done' => true );
			},
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['oz-tools'] = array(
			'eraser_friendly_name' => 'OzMoneyTalks Tools email list',
			'callback'             => function ( $email ) {
				global $wpdb;
				$removed = (bool) $wpdb->delete( self::table(), array( 'email' => $email ) );
				return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
}
