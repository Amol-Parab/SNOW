<?php
/**
 * Remittance comparator (AUD → INR). Vars: $config, $providers, $urls.
 */
defined( 'ABSPATH' ) || exit;

$id        = wp_unique_id( 'oz-rem-' );
$affiliate = (bool) array_filter( wp_list_pluck( $providers['items'], 'affiliate' ) );
?>
<div class="oz-tool oz-remit"<?php echo oz_tools_root_style(); // phpcs:ignore ?> data-oz-remit>
	<div class="oz-card">
		<div class="oz-head">
			<h2 class="oz-title">Send money to India: who gives you the most rupees?</h2>
			<p class="oz-sub">A "zero fee" transfer can still cost you through a worse exchange rate. This compares what your family actually receives.</p>
		</div>

		<div class="oz-grid">
			<div class="oz-form">
				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-amt">You send</label>
					<div class="oz-input oz-input--pre"><span>A$</span><input id="<?php echo esc_attr( $id ); ?>-amt" name="amount" type="number" inputmode="decimal" min="0" step="50" value="<?php echo esc_attr( $config['remittance']['amount'] ); ?>"></div>
					<div class="oz-chips" role="group" aria-label="Quick amounts">
						<?php foreach ( array( 500, 1000, 2000, 5000, 10000 ) as $a ) : ?>
							<button type="button" class="oz-chip" data-amount="<?php echo (int) $a; ?>">$<?php echo esc_html( number_format( $a ) ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-mid">Mid-market rate (1 AUD)</label>
					<div class="oz-input oz-input--pre"><span>₹</span><input id="<?php echo esc_attr( $id ); ?>-mid" name="mid" type="number" inputmode="decimal" min="1" step="0.01" value="<?php echo esc_attr( $config['savings']['fallback_aud_inr'] ); ?>"></div>
					<p class="oz-hint" data-oz-mid-note>Fetching today's rate…</p>
				</div>

				<label class="oz-check">
					<input type="checkbox" name="monthly" value="1" checked>
					<span>I send about this much every month</span>
				</label>

				<details class="oz-details">
					<summary>Compare a quote you've been given</summary>
					<p class="oz-hint" style="margin:0 0 10px">Got a rate from your bank or another service? Enter it to see where it ranks.</p>
					<div class="oz-field-row">
						<div class="oz-field">
							<label for="<?php echo esc_attr( $id ); ?>-qr">Their rate (1 AUD)</label>
							<div class="oz-input oz-input--pre"><span>₹</span><input id="<?php echo esc_attr( $id ); ?>-qr" name="quote_rate" type="number" inputmode="decimal" min="0" step="0.01" placeholder="e.g. 56.20"></div>
						</div>
						<div class="oz-field">
							<label for="<?php echo esc_attr( $id ); ?>-qf">Their fee</label>
							<div class="oz-input oz-input--pre"><span>A$</span><input id="<?php echo esc_attr( $id ); ?>-qf" name="quote_fee" type="number" inputmode="decimal" min="0" step="1" placeholder="0"></div>
						</div>
					</div>
				</details>
			</div>

			<div class="oz-results" aria-live="polite">
				<p class="oz-kicker">Your family receives</p>
				<ol class="oz-rank" data-oz-rank></ol>
				<ul class="oz-insights" data-oz-remit-insights></ul>
			</div>
		</div>

		<p class="oz-note oz-note--muted">
			<?php if ( $providers['checked'] ) : ?>
				Provider fees and margins last checked <?php echo esc_html( wp_date( 'j F Y', strtotime( $providers['checked'] ) ) ); ?>. Rates move all day, so confirm the live quote before you send.
			<?php else : ?>
				Provider fees and margins are estimates. Always confirm the live quote before you send.
			<?php endif; ?>
			<?php if ( $urls['rate_alert'] ) : ?>
				<a href="<?php echo esc_url( $urls['rate_alert'] ); ?>">Waiting for a better rate? Set an alert →</a>
			<?php endif; ?>
		</p>
		<?php if ( $affiliate ) : ?>
			<p class="oz-note oz-note--muted">Some links are affiliate links, so we may earn a commission if you sign up. That doesn't change the order: it's always sorted by what your recipient gets.</p>
		<?php endif; ?>
	</div>

	<?php echo Oz_Tools_Shortcodes::signup_box( 'remittance', 'Get the weekly AUD→INR update', 'The rupee rate, the week\'s movement and new money guides. One short email a week.' ); // phpcs:ignore ?>

	<p class="oz-disclaimer">General information only, not a recommendation of any provider. Check fees, limits and the provider's terms before you send. Mid-market rate from the European Central Bank via Frankfurter.</p>
</div>
