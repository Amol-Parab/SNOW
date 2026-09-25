<?php
/**
 * "Ask" chat helper: a floating box on every page that answers from the site's own content.
 *
 * For each question it looks for, in order: an answer written under Settings → OzMoneyTalks Tools,
 * today's AUD→INR rate, the tools that fit, and matching posts and pages. It never writes its
 * own financial content, and it adds a notice when someone asks what they personally should do.
 *
 * No AI service is called. To plug one in, use the 'oz_tools_chat_reply' filter, which receives
 * everything found for the question (see README).
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Chat {

	const ANSWERS_OPTION = 'oz_tools_chat_answers';
	const LOG_OPTION     = 'oz_tools_chat_log';
	const LOG_MAX        = 300;
	const MAX_LENGTH     = 300;

	/** What the last reply() found, for the question log. */
	private static $last_found = '';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		if ( oz_tools_settings()['chat_enabled'] && ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'wp_footer', array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Words, tools and suggestions from includes/chat-data.php.
	 */
	public static function data() {
		static $data = null;
		if ( null === $data ) {
			$data = apply_filters( 'oz_tools_chat_data', require OZ_TOOLS_DIR . 'includes/chat-data.php' );
		}
		return $data;
	}

	/**
	 * Admin-written answers: [ { keywords, answer, link } ].
	 */
	public static function answers() {
		$saved = get_option( self::ANSWERS_OPTION );
		return is_array( $saved ) && ! empty( $saved['items'] ) ? $saved['items'] : array();
	}

	private static function show() {
		return (bool) apply_filters( 'oz_tools_chat_show', true );
	}

	public static function enqueue() {
		if ( ! self::show() ) {
			return;
		}
		$data = self::data();
		wp_enqueue_style( 'oz-chat', OZ_TOOLS_URL . 'assets/css/oz-chat.css', array(), OZ_TOOLS_VERSION );
		wp_enqueue_script( 'oz-chat', OZ_TOOLS_URL . 'assets/js/oz-chat.js', array(), OZ_TOOLS_VERSION, true );
		wp_add_inline_script( 'oz-chat', 'window.OZChat = ' . wp_json_encode( array(
			'rest'        => esc_url_raw( rest_url( 'oz-tools/v1/chat' ) ),
			'greeting'    => $data['greeting'],
			'suggestions' => $data['suggestions'],
			'maxLength'   => self::MAX_LENGTH,
		) ) . ';', 'before' );
	}

	public static function render() {
		if ( ! self::show() ) {
			return;
		}
		include OZ_TOOLS_DIR . 'templates/chat.php';
	}

	public static function register_routes() {
		register_rest_route( 'oz-tools/v1', '/chat', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'rest_chat' ),
			'args'                => array(
				'message' => array( 'type' => 'string', 'required' => true ),
			),
		) );
	}

	public static function rest_chat( WP_REST_Request $req ) {
		if ( ! oz_tools_settings()['chat_enabled'] ) {
			return new WP_Error( 'oz_chat_off', 'The helper is switched off.', array( 'status' => 403 ) );
		}
		$message = trim( sanitize_textarea_field( (string) $req['message'] ) );
		if ( '' === $message ) {
			return new WP_Error( 'oz_chat_empty', 'Please type a question.', array( 'status' => 400 ) );
		}
		$message = mb_substr( $message, 0, self::MAX_LENGTH );
		if ( ! oz_tools_allow_request( 'chat', (int) apply_filters( 'oz_tools_chat_hourly_limit', 30 ) ) ) {
			return new WP_Error( 'oz_rate_limited', 'You\'ve asked a lot of questions in a short time. Please try again in a little while.', array( 'status' => 429 ) );
		}

		$reply = self::reply( $message );
		self::log( $message, ! empty( $reply['answered'] ) );
		return rest_ensure_response( $reply );
	}

	/**
	 * Build the reply for one question.
	 *
	 * @return array { text, notice, more, tools: [{label, why, url}], links: [{title, url, excerpt}], answered }
	 */
	public static function reply( $message ) {
		$data  = self::data();
		$text  = Oz_Tools_Chat_Match::normalise( $message );
		$urls  = oz_tools_urls();
		$parts = array();
		$more  = '';

		$answer = Oz_Tools_Chat_Match::answer( $text, self::answers() );
		if ( $answer ) {
			$parts[] = $answer['answer'];
			$more    = $answer['link'];
		}

		$rate_asked = Oz_Tools_Chat_Match::score( $text, $data['rate_phrases'] ) > 0;
		if ( $rate_asked ) {
			$rate = Oz_Tools_Rates::get();
			$parts[] = $rate
				? sprintf(
					'On %s the reference rate was 1 AUD = %s (the European Central Bank\'s mid-market rate). Transfer providers give you less than this, so compare what your family would actually receive.',
					mysql2date( 'j M Y', $rate['date'] ),
					oz_tools_fmt_rate( $rate['rate'] )
				)
				: 'I can\'t load the exchange rate right now. Please try again later.';
		}

		$tools = array();
		foreach ( Oz_Tools_Chat_Match::tools( $text, $data['tools'] ) as $key ) {
			if ( ! empty( $urls[ $key ] ) ) {
				$tools[] = self::tool( $key );
			}
		}

		$links = self::search( $text, array_merge( array( $more ), wp_list_pluck( $tools, 'url' ) ) );

		$found = array();
		if ( $parts ) {
			$found[] = 'answer';
		}
		if ( $tools ) {
			$found[] = count( $tools ) . ( 1 === count( $tools ) ? ' tool' : ' tools' );
		}
		if ( $links ) {
			$found[] = count( $links ) . ( 1 === count( $links ) ? ' post' : ' posts' );
		}

		$answered = (bool) $found;
		if ( ! $answered ) {
			$parts[] = 'I couldn\'t find anything on the site about that. Try asking another way, or start with one of these:';
			foreach ( array_keys( $data['tools'] ) as $key ) {
				if ( ! empty( $urls[ $key ] ) ) {
					$tools[] = self::tool( $key );
				}
			}
		} elseif ( ! $parts ) {
			$parts[] = 'Here\'s what might help:';
		}

		$reply = array(
			'text'     => implode( "\n\n", $parts ),
			'notice'   => Oz_Tools_Chat_Match::score( $text, $data['advice_phrases'] ) > 0 ? $data['advice_notice'] : '',
			'more'     => $more,
			'tools'    => $tools,
			'links'    => $links,
			'answered' => $answered,
		);
		if ( $reply['notice'] ) {
			$found[] = 'advice notice';
		}
		self::$last_found = $found ? implode( ', ', $found ) : 'nothing';

		/**
		 * Replace or extend the reply, for example with an AI model's answer written from $reply's links.
		 * Keep 'notice' and the disclaimer: the site must not give personal financial advice.
		 *
		 * @param array  $reply   See above.
		 * @param string $message The reader's question, already trimmed and length-limited.
		 */
		return apply_filters( 'oz_tools_chat_reply', $reply, $message );
	}

	private static function tool( $key ) {
		$t = self::data()['tools'][ $key ];
		return array( 'label' => $t['label'], 'why' => $t['why'], 'url' => oz_tools_urls()[ $key ] );
	}

	/**
	 * Up to 3 published posts or pages matching the question's words. A post that matches more
	 * words ranks higher, and a word in the title counts extra. Results are cached for 10 minutes.
	 *
	 * @param string   $text Normalised question.
	 * @param string[] $skip URLs already in the reply.
	 */
	private static function search( $text, $skip ) {
		$terms = Oz_Tools_Chat_Match::terms( $text, self::data()['stopwords'] );
		if ( ! $terms ) {
			return array();
		}
		$post_types = (array) apply_filters( 'oz_tools_chat_post_types', array( 'post', 'page' ) );
		$cache_key  = 'oz_tools_chat_s_' . md5( implode( ' ', $terms ) . '|' . implode( ',', $post_types ) );
		$found      = get_transient( $cache_key );

		if ( false === $found ) {
			// WordPress search requires every word to match, which natural questions rarely do,
			// so search each word separately and pool the results.
			$ids = array();
			foreach ( $terms as $term ) {
				$ids = array_merge( $ids, get_posts( array(
					's'                => $term,
					'post_type'        => $post_types,
					'post_status'      => 'publish',
					'has_password'     => false,
					'posts_per_page'   => 20,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				) ) );
			}

			// Score each post: one point per word found in its text, two more if it's in the title.
			// Words must start a word ("rent" matches "rental", not "current" or "parent"), which
			// WordPress's substring search doesn't check.
			$found = array();
			foreach ( array_unique( $ids ) as $id ) {
				if ( self::is_tool_page( get_post( $id ) ) ) {
					continue;
				}
				$title = (string) get_post_field( 'post_title', $id );
				$body  = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_excerpt', $id ) . ' ' . get_post_field( 'post_content', $id ) ) );
				$score = 0;
				foreach ( $terms as $term ) {
					$re = '/(?<![\p{L}\p{N}])' . preg_quote( $term, '/' ) . '/iu';
					$score += preg_match( $re, $body ) + 2 * preg_match( $re, $title );
				}
				if ( $score > 0 ) {
					$found[] = array( 'id' => $id, 'score' => $score );
				}
			}
			usort( $found, function ( $a, $b ) {
				return $b['score'] - $a['score'] ?: $b['id'] - $a['id']; // Ties: newest first.
			} );
			// Drop weak matches, e.g. a post that only shares "rate" with a question about the exchange
			// rate: below half the best score, or a single word from a longer question.
			$top   = $found ? $found[0]['score'] : 0;
			$min   = count( $terms ) >= 3 ? 2 : 1;
			$found = array_filter( $found, function ( $f ) use ( $top, $min ) {
				return $f['score'] >= $min && $f['score'] * 2 >= $top;
			} );
			$found = wp_list_pluck( array_slice( $found, 0, 8 ), 'id' );
			set_transient( $cache_key, $found, 10 * MINUTE_IN_SECONDS );
		}

		$skip  = array_map( 'untrailingslashit', array_filter( $skip ) );
		$links = array();
		foreach ( $found as $id ) {
			$post = get_post( $id );
			$url  = get_permalink( $post );
			if ( ! $post || 'publish' !== $post->post_status || in_array( untrailingslashit( $url ), $skip, true ) ) {
				continue;
			}
			$excerpt = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
			$links[] = array(
				'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'     => $url,
				'excerpt' => html_entity_decode( wp_trim_words( wp_strip_all_tags( $excerpt ), 22 ), ENT_QUOTES, 'UTF-8' ),
			);
			if ( count( $links ) >= 3 ) {
				break;
			}
		}
		return $links;
	}

	/**
	 * Pages holding a tool whose URL is set. The helper suggests those through the tool list,
	 * with a description, so they're left out of search results.
	 */
	private static function is_tool_page( $post ) {
		$shortcodes = array(
			'remittance' => 'oz_remittance',
			'rate_alert' => 'oz_rate_alert',
			'rent'       => 'oz_rent_calculator',
			'savings'    => 'oz_savings_compare',
			'checklist'  => 'oz_settling_checklist',
		);
		foreach ( oz_tools_urls() as $key => $url ) {
			if ( $url && isset( $shortcodes[ $key ] ) && has_shortcode( $post->post_content, $shortcodes[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep the latest questions (without names, IPs, emails or long numbers) so the site owner
	 * can see what readers ask and what the helper couldn't answer. Two questions arriving at
	 * the same moment can overwrite each other; that's acceptable for this purpose.
	 */
	private static function log( $message, $answered ) {
		if ( ! oz_tools_settings()['chat_log'] ) {
			return;
		}
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift( $log, array(
			't' => time(),
			'q' => mb_substr( Oz_Tools_Chat_Match::scrub( $message ), 0, 200 ),
			'a' => (bool) $answered,
			'f' => self::$last_found,
		) );
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_MAX ), false );
	}

	/**
	 * @return array Newest first: [ { t, q, a, f } ].
	 */
	public static function recent( $limit = 50 ) {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? array_slice( $log, 0, $limit ) : array();
	}
}
