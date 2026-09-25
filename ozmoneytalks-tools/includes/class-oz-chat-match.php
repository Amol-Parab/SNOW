<?php
/**
 * Text matching for the chat helper. Pure functions with no WordPress calls,
 * so tests/chat.test.php can run them with plain PHP.
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Chat_Match {

	/**
	 * Lowercase, and turn punctuation into single spaces: "Today's rate?" → "today s rate".
	 */
	public static function normalise( $text ) {
		$text = strtolower( (string) $text );
		$text = (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * How strongly $text matches a list of keyword phrases. Each matching phrase scores its
	 * word count, so "fixed deposit" outweighs "interest". Plurals of a phrase match too.
	 *
	 * @param string $text    Already normalised.
	 * @param array  $phrases Raw phrases; normalised here.
	 */
	public static function score( $text, $phrases ) {
		$score = 0;
		foreach ( $phrases as $phrase ) {
			$phrase = self::normalise( $phrase );
			if ( '' === $phrase ) {
				continue;
			}
			if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $phrase, '/' ) . '(?:s|es)?(?![\p{L}\p{N}])/u', $text ) ) {
				$score += substr_count( $phrase, ' ' ) + 1;
			}
		}
		return $score;
	}

	/**
	 * Tools whose keywords appear in the question, best match first.
	 *
	 * @param string $text  Already normalised.
	 * @param array  $tools key => { keywords: [...] }
	 * @param int    $max
	 * @return string[] Tool keys.
	 */
	public static function tools( $text, $tools, $max = 2 ) {
		$scores = array();
		foreach ( $tools as $key => $tool ) {
			$s = self::score( $text, $tool['keywords'] );
			if ( $s > 0 ) {
				$scores[ $key ] = $s;
			}
		}
		// Stable: ties keep the order tools are listed in.
		$keys = array_keys( $scores );
		usort( $keys, function ( $a, $b ) use ( $scores, $keys ) {
			return $scores[ $b ] - $scores[ $a ] ?: array_search( $a, $keys, true ) - array_search( $b, $keys, true );
		} );
		return array_slice( $keys, 0, $max );
	}

	/**
	 * The admin-written answer that best matches the question, or null.
	 *
	 * @param string $text    Already normalised.
	 * @param array  $answers [ { keywords: "comma, separated", answer, link } ]
	 */
	public static function answer( $text, $answers ) {
		$best       = null;
		$best_score = 0;
		foreach ( $answers as $a ) {
			$s = self::score( $text, explode( ',', (string) $a['keywords'] ) );
			if ( $s > $best_score ) {
				$best       = $a;
				$best_score = $s;
			}
		}
		return $best;
	}

	/**
	 * Search terms: the question's meaningful words, crudely stemmed, most specific (longest) first.
	 *
	 * @param string $text      Already normalised.
	 * @param array  $stopwords
	 * @param int    $max
	 */
	public static function terms( $text, $stopwords, $max = 5 ) {
		$stop  = array_flip( $stopwords );
		$terms = array();
		foreach ( explode( ' ', $text ) as $word ) {
			if ( isset( $stop[ $word ] ) || strlen( $word ) < 2 || ctype_digit( $word ) ) {
				continue;
			}
			$terms[ self::stem( $word ) ] = true;
		}
		$terms = array_keys( $terms );
		usort( $terms, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );
		return array_slice( $terms, 0, $max );
	}

	/**
	 * Just enough stemming for a LIKE search: "renting" → "rent" finds "rent", "rental" and "renting".
	 */
	public static function stem( $word ) {
		$len = strlen( $word );
		if ( $len > 5 && 'ies' === substr( $word, -3 ) ) {
			return substr( $word, 0, -3 ) . 'y';
		}
		if ( $len > 6 && 'ing' === substr( $word, -3 ) ) {
			return substr( $word, 0, -3 );
		}
		if ( $len > 4 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
			return substr( $word, 0, -1 );
		}
		return $word;
	}

	/**
	 * Remove details people shouldn't have typed before the question is logged.
	 */
	public static function scrub( $text ) {
		$text = (string) preg_replace( '/\S+@\S+/', '[email]', (string) $text );
		// Phone, TFN, account and card numbers: 7+ digits, possibly spaced or dashed.
		return (string) preg_replace( '/\+?\d(?:[\s-]?\d){6,}/', '[number]', $text );
	}
}
