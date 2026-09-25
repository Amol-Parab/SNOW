<?php
/**
 * Settling-in checklist. Vars: $data, $active, $urls.
 * All items are in the HTML (good for search); JS shows the chosen persona's items.
 */
defined( 'ABSPATH' ) || exit;

$tool_labels = array(
	'rent'       => 'Rent move-in cost calculator',
	'remittance' => 'Compare transfer costs',
	'savings'    => 'India vs Australia savings',
	'rate_alert' => 'Set a rate alert',
);
$id = wp_unique_id( 'oz-cl-' );
?>
<div class="oz-tool oz-checklist" data-oz-checklist data-active="<?php echo esc_attr( $active ); ?>">
	<div class="oz-card">
		<div class="oz-head">
			<h2 class="oz-title">Your settling-in checklist</h2>
			<p class="oz-sub">Pick your situation. Tick items as you go — your progress is saved on this device.</p>
		</div>

		<div class="oz-pills" role="radiogroup" aria-label="Your situation">
			<?php foreach ( $data['personas'] as $key => $p ) : ?>
				<button type="button" class="oz-pill" role="radio" data-persona="<?php echo esc_attr( $key ); ?>" aria-checked="<?php echo $key === $active ? 'true' : 'false'; ?>">
					<span><?php echo esc_html( $p['label'] ); ?></span>
					<small><?php echo esc_html( $p['hint'] ); ?></small>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="oz-progress" aria-live="polite">
			<div class="oz-progress__bar"><span data-oz-bar style="width:0%"></span></div>
			<p class="oz-progress__text" data-oz-progress>0 of 0 done</p>
		</div>

		<?php foreach ( $data['phases'] as $phase_key => $phase_label ) : ?>
			<section class="oz-phase" data-phase="<?php echo esc_attr( $phase_key ); ?>">
				<h3 class="oz-phase__title"><?php echo esc_html( $phase_label ); ?> <span class="oz-phase__count" data-oz-count></span></h3>
				<ul class="oz-items">
					<?php
					foreach ( $data['items'] as $item ) :
						if ( $item['phase'] !== $phase_key ) {
							continue;
						}
						$applies = in_array( 'all', $item['personas'], true ) || in_array( $active, $item['personas'], true );
						$cb      = $id . '-' . $item['id'];
						?>
						<li class="oz-item" data-id="<?php echo esc_attr( $item['id'] ); ?>" data-personas="<?php echo esc_attr( implode( ' ', $item['personas'] ) ); ?>"<?php echo $applies ? '' : ' hidden'; ?>>
							<input type="checkbox" id="<?php echo esc_attr( $cb ); ?>">
							<div>
								<label for="<?php echo esc_attr( $cb ); ?>" class="oz-item__title"><?php echo esc_html( $item['title'] ); ?></label>
								<p class="oz-item__why"><?php echo esc_html( $item['why'] ); ?></p>
								<?php if ( ! empty( $item['link'] ) || ( ! empty( $item['tool'] ) && ! empty( $urls[ $item['tool'] ] ) ) ) : ?>
									<p class="oz-item__links">
										<?php if ( ! empty( $item['tool'] ) && ! empty( $urls[ $item['tool'] ] ) ) : ?>
											<a class="oz-item__tool" href="<?php echo esc_url( $urls[ $item['tool'] ] ); ?>"><?php echo esc_html( $tool_labels[ $item['tool'] ] ); ?> →</a>
										<?php endif; ?>
										<?php if ( ! empty( $item['link'] ) ) : ?>
											<a href="<?php echo esc_url( $item['link'][1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['link'][0] ); ?> ↗</a>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>

		<div class="oz-actions">
			<label class="oz-check oz-check--inline"><input type="checkbox" data-oz-hide-done> <span>Hide completed</span></label>
			<button type="button" class="oz-link-btn" data-oz-print>Print</button>
			<button type="button" class="oz-link-btn" data-oz-reset>Reset ticks</button>
		</div>
	</div>

	<form class="oz-signup" data-oz-signup data-source="checklist" novalidate>
		<p class="oz-signup__title">Email me this checklist</p>
		<p class="oz-signup__text">We'll send the list for your situation so you have it offline.</p>
		<div class="oz-signup__row">
			<label class="oz-sr" for="<?php echo esc_attr( $id ); ?>-email">Email address</label>
			<input id="<?php echo esc_attr( $id ); ?>-email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
			<button type="submit" class="oz-btn">Send it</button>
		</div>
		<label class="oz-check">
			<input type="checkbox" name="consent" value="1">
			<span>Also send me the weekly AUD→INR update and new guides. Unsubscribe any time.<?php if ( $urls['privacy'] ) : ?> <a href="<?php echo esc_url( $urls['privacy'] ); ?>">Privacy policy</a>.<?php endif; ?></span>
		</label>
		<input type="hidden" name="persona" value="<?php echo esc_attr( $active ); ?>">
		<input type="hidden" name="weekly" value="1">
		<div class="oz-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
		<p class="oz-msg" role="status" aria-live="polite"></p>
	</form>

	<p class="oz-disclaimer">General information only, not financial, tax or migration advice. Rules change — check the official links. Last reviewed <?php echo esc_html( wp_date( 'j F Y', strtotime( oz_tools_config()['reviewed'] ) ) ); ?>.</p>
</div>
