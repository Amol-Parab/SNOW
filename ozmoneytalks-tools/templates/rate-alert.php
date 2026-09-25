<?php
/**
 * AUD→INR rate alert + weekly email signup. Vars: $urls.
 */
defined( 'ABSPATH' ) || exit;

$id = wp_unique_id( 'oz-ra-' );
?>
<div class="oz-tool oz-rate"<?php echo oz_tools_root_style(); // phpcs:ignore ?> data-oz-rate>
	<div class="oz-card">
		<div class="oz-head">
			<h2 class="oz-title">AUD→INR rate alert</h2>
			<p class="oz-sub">Tell us the rate you're waiting for. We'll email you once when the Australian dollar gets there.</p>
		</div>

		<div class="oz-grid">
			<div class="oz-rate__now">
				<p class="oz-kicker">Today</p>
				<p class="oz-big" data-oz-rate-now>1 AUD = ₹—</p>
				<p class="oz-note" data-oz-rate-meta>Loading the latest rate…</p>
				<figure class="oz-spark" data-oz-spark aria-hidden="true"></figure>
				<dl class="oz-stats" data-oz-stats></dl>
			</div>

			<form class="oz-signup oz-signup--plain" data-oz-signup data-source="rate_alert" novalidate>
				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-target">Alert me when 1 AUD reaches</label>
					<div class="oz-input oz-input--pre"><span>₹</span><input id="<?php echo esc_attr( $id ); ?>-target" name="target_rate" type="number" inputmode="decimal" min="10" max="500" step="0.05" placeholder="e.g. 60.00"></div>
					<p class="oz-hint" data-oz-target-hint></p>
				</div>
				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-email">Email address</label>
					<input id="<?php echo esc_attr( $id ); ?>-email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
				</div>
				<label class="oz-check">
					<input type="checkbox" name="weekly" value="1" checked>
					<span>Also send the weekly rate update and new guides</span>
				</label>
				<label class="oz-check">
					<input type="checkbox" name="consent" value="1" required>
					<span>I agree to receive these emails. Unsubscribe any time.<?php if ( $urls['privacy'] ) : ?> <a href="<?php echo esc_url( $urls['privacy'] ); ?>">Privacy policy</a>.<?php endif; ?></span>
				</label>
				<div class="oz-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<button type="submit" class="oz-btn oz-btn--block">Set my alert</button>
				<p class="oz-msg" role="status" aria-live="polite"></p>
			</form>
		</div>

		<p class="oz-note oz-note--muted">This is the European Central Bank reference (mid-market) rate, updated once each business day. Banks and transfer services add a margin, so you'll get a little less.<?php if ( $urls['remittance'] ) : ?> <a href="<?php echo esc_url( $urls['remittance'] ); ?>">Compare what you'd actually receive →</a><?php endif; ?></p>
	</div>

	<p class="oz-disclaimer">Rates from the European Central Bank via Frankfurter. For information only; not a recommendation to buy or sell currency.</p>
</div>
