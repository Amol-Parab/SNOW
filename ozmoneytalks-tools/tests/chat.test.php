<?php
// Run with: php ozmoneytalks-tools/tests/chat.test.php
// Tests the chat helper's matching against the real keyword lists in includes/chat-data.php.

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-oz-chat-match.php';
$data = require __DIR__ . '/../includes/chat-data.php';

$failed = 0;
function check( $name, $actual, $expected ) {
	global $failed;
	if ( $actual === $expected ) {
		echo "ok   - $name\n";
		return;
	}
	$failed++;
	echo "FAIL - $name\n       expected " . var_export( $expected, true ) . "\n       got      " . var_export( $actual, true ) . "\n";
}

$M = 'Oz_Tools_Chat_Match';
$n = function ( $s ) use ( $M ) {
	return $M::normalise( $s );
};
$tools = function ( $q ) use ( $M, $n, $data ) {
	return $M::tools( $n( $q ), $data['tools'] );
};

check( 'normalise: case and punctuation', $n( "  Today's AUD→INR rate?? " ), 'today s aud inr rate' );

// Tools: best match first, at most two.
check( 'tools: send money', $tools( 'What is the cheapest way to send money to India?' )[0], 'remittance' );
check( 'tools: provider name', $tools( 'Is Wise cheaper than Remitly?' ), array( 'remittance' ) );
check( 'tools: NRE vs NRO', $tools( 'NRE vs NRO fixed deposit' ), array( 'savings' ) );
check( 'tools: bond', $tools( 'How much bond do I pay in Victoria?' ), array( 'rent' ) );
check( 'tools: plurals match', $tools( 'what fees apply' ), array( 'remittance' ) );
check( 'tools: just arrived', $tools( 'I just arrived. Do I need a TFN?' ), array( 'checklist' ) );
check( 'tools: interest rate is savings, not the rate alert', $tools( 'What interest rate do NRE accounts pay?' ), array( 'savings' ) );
check( 'tools: exchange rate', $tools( 'AUD to INR exchange rate' ), array( 'rate_alert' ) );
check( 'tools: nothing', $tools( 'What is the weather in Perth?' ), array() );
check( 'tools: no partial words ("super" is not "superb")', $tools( 'superb food' ), array() );
check( 'tools: max two', count( $tools( 'send money, rent bond, NRE interest and TFN' ) ), 2 );

// Rate questions.
$rate = function ( $q ) use ( $M, $n, $data ) {
	return $M::score( $n( $q ), $data['rate_phrases'] ) > 0;
};
check( 'rate: asked', $rate( "What's today's rate?" ), true );
check( 'rate: aud to inr', $rate( 'aud to inr' ), true );
check( 'rate: interest rate is not the exchange rate', $rate( 'best interest rate' ), false );

// Personal advice.
$advice = function ( $q ) use ( $M, $n, $data ) {
	return $M::score( $n( $q ), $data['advice_phrases'] ) > 0;
};
check( 'advice: should I', $advice( 'Should I move my savings to an NRE FD?' ), true );
check( 'advice: which is better', $advice( 'Which is better, Wise or Remitly?' ), true );
check( 'advice: recommend', $advice( 'Can you recommend a provider' ), true );
check( 'advice: plain question', $advice( 'How does an NRE account work?' ), false );

// Admin answers: highest score wins, and multi-word keywords outweigh single words.
$answers = array(
	array( 'keywords' => 'tax', 'answer' => 'general tax', 'link' => '' ),
	array( 'keywords' => 'tfn, tax file number', 'answer' => 'tfn answer', 'link' => '' ),
);
check( 'answer: best match', $M::answer( $n( 'How do I get a tax file number?' ), $answers )['answer'], 'tfn answer' );
check( 'answer: single keyword', $M::answer( $n( 'Do I pay tax?' ), $answers )['answer'], 'general tax' );
check( 'answer: none', $M::answer( $n( 'rent' ), $answers ), null );
check( 'answer: blank keywords never match', $M::answer( $n( 'anything' ), array( array( 'keywords' => ' , ', 'answer' => 'x', 'link' => '' ) ) ), null );

// Search terms.
check( 'terms: stopwords removed, stemmed, longest first',
	$M::terms( $n( 'How do I send money to India when renting?' ), $data['stopwords'] ),
	array( 'send', 'rent' ) );
check( 'terms: numbers dropped', $M::terms( $n( '1000 dollars' ), $data['stopwords'] ), array( 'dollar' ) );
check( 'terms: at most 5', count( $M::terms( $n( 'alpha bravo charlie delta echo foxtrot golf' ), $data['stopwords'] ) ), 5 );
check( 'stem: -ies', $M::stem( 'properties' ), 'property' );
check( 'stem: -ing', $M::stem( 'transferring' ), 'transferr' );
check( 'stem: short -ing kept', $M::stem( 'going' ), 'going' );
check( 'stem: -ss kept', $M::stem( 'address' ), 'address' );
check( 'stem: -s', $M::stem( 'accounts' ), 'account' );

// Scrubbing before logging.
check( 'scrub: email', $M::scrub( 'I am raj@example.com' ), 'I am [email]' );
check( 'scrub: mobile', $M::scrub( 'call 0412 345 678' ), 'call [number]' );
check( 'scrub: TFN with dashes', $M::scrub( 'tfn 123-456-789 ok' ), 'tfn [number] ok' );
check( 'scrub: amounts and rates kept', $M::scrub( 'send $20000 at 57.83' ), 'send $20000 at 57.83' );

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit( $failed ? 1 : 0 );
