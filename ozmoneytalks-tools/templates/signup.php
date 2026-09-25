<?php
/**
 * Newsletter signup box. Vars: $source, $title, $text, $privacy, $id.
 */
defined( 'ABSPATH' ) || exit;
?>
<form class="oz-signup" data-oz-signup data-source="<?php echo esc_attr( $source ); ?>" novalidate>
	<p class="oz-signup__title"><?php echo esc_html( $title ); ?></p>
	<p class="oz-signup__text"><?php echo esc_html( $text ); ?></p>
	<div class="oz-signup__row">
		<label class="oz-sr" for="<?php echo esc_attr( $id ); ?>-email">Email address</label>
		<input id="<?php echo esc_attr( $id ); ?>-email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
		<button type="submit" class="oz-btn">Subscribe</button>
	</div>
	<label class="oz-check">
		<input type="checkbox" name="consent" value="1" required>
		<span>I agree to receive emails from <?php echo esc_html( oz_tools_settings()['site_name'] ); ?>. Unsubscribe any time.<?php if ( $privacy ) : ?> <a href="<?php echo esc_url( $privacy ); ?>">Privacy policy</a>.<?php endif; ?></span>
	</label>
	<input type="hidden" name="weekly" value="1">
	<div class="oz-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
	<p class="oz-msg" role="status" aria-live="polite"></p>
</form>
