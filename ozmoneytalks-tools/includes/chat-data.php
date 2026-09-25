<?php
/**
 * Chat helper content: which words point to which tool, what counts as a request
 * for personal advice, and the starter suggestions. Edit freely.
 *
 * Keywords are matched as whole words or phrases, ignoring case and punctuation,
 * with plurals ("fees" matches "fee"). Can be overridden with the 'oz_tools_chat_data' filter.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'greeting'    => 'Hi! Ask me about sending money to India, the exchange rate, renting, savings or settling in. I\'ll point you to the right tool or guide.',

	// Starter buttons shown before the first question: label => question sent.
	'suggestions' => array(
		'Cheapest way to send money' => 'What is the cheapest way to send money to India?',
		'Today\'s AUD→INR rate'      => 'What is today\'s AUD to INR exchange rate?',
		'Moving-in costs'            => 'How much money do I need to move into a rental?',
		'NRE vs NRO'                 => 'NRE vs NRO fixed deposit',
		'Just arrived'               => 'I just arrived in Australia. What do I need to set up?',
	),

	// Tool keys match the page URLs under Settings → OzMoneyTalks Tools. A tool is only
	// suggested once its page URL is set.
	'tools'       => array(
		'remittance' => array(
			'label'    => 'Send money to India comparator',
			'why'      => 'Compare how many rupees your family receives with each transfer provider.',
			'keywords' => array( 'send money', 'sending money', 'transfer', 'transferring', 'remit', 'remittance', 'wise', 'remitly', 'western union', 'instarem', 'ofx', 'xe', 'fee', 'money to india', 'cheapest way', 'provider', 'wire' ),
		),
		'rate_alert' => array(
			'label'    => 'AUD→INR rate alert',
			'why'      => 'See today\'s rate and the last 30 days, and get an email when it reaches your target.',
			'keywords' => array( 'exchange rate', 'aud to inr', 'aud inr', 'rupee', 'inr', 'rate today', 'today s rate', 'todays rate', 'current rate', 'conversion rate', 'forex', 'alert', 'notify', 'how many rupees', 'dollar to rupee' ),
		),
		'rent'       => array(
			'label'    => 'Rent move-in cost calculator',
			'why'      => 'Work out the upfront cash for bond, rent in advance, furniture and setup.',
			'keywords' => array( 'rent', 'renting', 'rental', 'bond', 'lease', 'tenancy', 'tenant', 'landlord', 'real estate', 'move in', 'moving in', 'apartment', 'flat', 'unit', 'share house', 'accommodation', 'furniture' ),
		),
		'savings'    => array(
			'label'    => 'India vs Australia savings comparison',
			'why'      => 'Compare an Australian savings account with NRE and NRO fixed deposits after tax and currency moves.',
			'keywords' => array( 'savings', 'saving', 'nre', 'nro', 'fd', 'fixed deposit', 'term deposit', 'interest', 'interest rate', 'dtaa', 'tds', 'tax on interest', 'invest', 'investment', 'high interest' ),
		),
		'checklist'  => array(
			'label'    => 'Settling-in checklist',
			'why'      => 'A step-by-step list for your first weeks and months, by visa type.',
			'keywords' => array( 'checklist', 'new to australia', 'just arrived', 'arrived', 'arriving', 'moving to australia', 'first week', 'tfn', 'tax file number', 'medicare', 'oshc', 'ovhc', 'health cover', 'health insurance', 'bank account', 'sim', 'mygov', 'licence', 'license', 'super', 'superannuation', 'visa', 'student', 'parent', 'settle', 'settling', 'set up' ),
		),
	),

	// Questions that mention one of these get today's reference rate as the answer.
	'rate_phrases' => array( 'exchange rate', 'aud to inr', 'aud inr', 'aud to rupee', 'rupee rate', 'inr rate', 'today s rate', 'todays rate', 'current rate', 'rate today', 'conversion rate', 'how many rupees', 'dollar to rupee' ),

	// Questions asking what the reader personally should do. The helper still points to
	// the tools, but says it can't give personal advice.
	'advice_phrases' => array( 'should i', 'should we', 'what should', 'is it better', 'is it worth', 'is it wise', 'is it smart', 'good idea', 'recommend', 'recommendation', 'advise me', 'advice for me', 'best for me', 'right for me', 'which is better', 'worth it', 'what would you do' ),

	'advice_notice' => 'I can\'t tell you what\'s right for you: that would be personal financial advice. The tools below show the numbers for your own situation. For personal advice, talk to a licensed financial adviser or a registered tax agent.',

	// Words ignored when searching posts. Words that appear in almost every post on this site are here too.
	'stopwords'   => array(
		'a', 'about', 'after', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'been', 'before', 'best', 'but', 'by',
		'can', 'could', 'did', 'do', 'does', 'doing', 'for', 'from', 'get', 'getting', 'give', 'go', 'good', 'got', 'had', 'has', 'have',
		'hello', 'help', 'hi', 'how', 'i', 'if', 'im', 'in', 'into', 'is', 'it', 'its', 'just', 'know', 'like', 'll', 'me', 'much', 'my',
		'need', 'needs', 'no', 'not', 'now', 'of', 'on', 'one', 'or', 'our', 'out', 'please', 'right', 's', 'should', 'so', 'some',
		'tell', 'than', 'thank', 'thanks', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this', 'to', 'up', 'us',
		'use', 'using', 've', 'vs', 'versus', 'want', 'was', 'way', 'we', 'what', 'when', 'where', 'which', 'who', 'why', 'will',
		'with', 'would', 'you', 'your',
		'india', 'indian', 'indians', 'australia', 'australian', 'oz', 'money',
	),
);
