<?php
/**
 * Floating "Ask" helper, printed in the footer of every page when switched on.
 * oz-chat.js fills in the conversation.
 */
defined( 'ABSPATH' ) || exit;

$oz_site    = oz_tools_settings()['site_name'];
$oz_privacy = oz_tools_urls()['privacy'];
?>
<div class="oz-chat" data-oz-chat<?php echo oz_tools_root_style(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function. ?>>
	<button type="button" class="oz-chat__toggle" aria-expanded="false" aria-controls="oz-chat-panel">
		<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm5 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm5 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>
		<span>Ask a question</span>
	</button>

	<section id="oz-chat-panel" class="oz-chat__panel" role="dialog" aria-labelledby="oz-chat-title" hidden>
		<header class="oz-chat__head">
			<div>
				<h2 id="oz-chat-title" class="oz-chat__title">Ask <?php echo esc_html( $oz_site ); ?></h2>
				<p class="oz-chat__sub">Finds the right tool or guide on this site</p>
			</div>
			<button type="button" class="oz-chat__close" aria-label="Close">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
			</button>
		</header>

		<div class="oz-chat__log" role="log" aria-live="polite" aria-relevant="additions"></div>

		<form class="oz-chat__form" novalidate>
			<label class="oz-chat__sr" for="oz-chat-input">Your question</label>
			<input id="oz-chat-input" type="text" name="message" autocomplete="off" maxlength="<?php echo (int) Oz_Tools_Chat::MAX_LENGTH; ?>" placeholder="Type your question…" required>
			<button type="submit" class="oz-chat__send" aria-label="Send">
				<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>
			</button>
		</form>
		<p class="oz-chat__fine">
			General information only, not personal financial advice. Please don't type personal details<?php echo oz_tools_settings()['chat_log'] ? ': questions are saved without your name to help improve the site' : ''; ?>.<?php if ( $oz_privacy ) : ?> <a href="<?php echo esc_url( $oz_privacy ); ?>">Privacy</a><?php endif; ?>
		</p>
	</section>
</div>
