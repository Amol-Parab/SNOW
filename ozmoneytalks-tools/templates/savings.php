<?php
/**
 * India vs Australia savings comparator. Vars: $config.
 */
defined( 'ABSPATH' ) || exit;

$id = wp_unique_id( 'oz-sav-' );
$d  = $config['savings'];
$tx = $config['tax'];

// Income bands from the tax brackets, e.g. "$45,001 – $135,000 (30%)".
$bands = array();
$lower = 0;
foreach ( $tx['brackets'] as $b ) {
	list( $upper, $rate ) = $b;
	$from    = $lower ? '$' . number_format( $lower + 1 ) : '$0';
	$label   = null === $upper ? 'Over $' . number_format( $lower ) : $from . ' – $' . number_format( $upper );
	$bands[] = array( 'rate' => $rate, 'label' => $label . ' (' . round( $rate * 100 ) . '%)' );
	$lower   = $upper;
}
?>
<div class="oz-tool oz-savings"<?php echo oz_tools_root_style(); // phpcs:ignore ?> data-oz-savings>
	<div class="oz-card">
		<div class="oz-head">
			<h2 class="oz-title">India or Australia: where should your savings sit?</h2>
			<p class="oz-sub">Compare an Australian savings account with an NRE or NRO fixed deposit — after Indian tax, Australian tax, exchange costs and the rupee's movement.</p>
		</div>

		<div class="oz-grid">
			<div class="oz-form">
				<div class="oz-field-row">
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-amt">Amount to save</label>
						<div class="oz-input oz-input--pre"><span>A$</span><input id="<?php echo esc_attr( $id ); ?>-amt" name="amount" type="number" inputmode="decimal" min="0" step="1000" value="<?php echo esc_attr( $d['amount'] ); ?>"></div>
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-yrs">For how long</label>
						<select id="<?php echo esc_attr( $id ); ?>-yrs" name="years">
							<?php for ( $y = 1; $y <= 10; $y++ ) : ?>
								<option value="<?php echo (int) $y; ?>" <?php selected( $y, (int) $d['years'] ); ?>><?php echo (int) $y; ?> year<?php echo $y > 1 ? 's' : ''; ?></option>
							<?php endfor; ?>
						</select>
					</div>
				</div>

				<fieldset class="oz-field">
					<legend>Your visa</legend>
					<div class="oz-seg">
						<label><input type="radio" name="status" value="temporary" checked> <span>Temporary visa<small>500, 482, 485, 491…</small></span></label>
						<label><input type="radio" name="status" value="permanent"> <span>PR or citizen<small>189, 190, 186, 820/801…</small></span></label>
					</div>
					<p class="oz-hint">This decides whether the ATO taxes your Indian interest.</p>
				</fieldset>

				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-inc">Your taxable income in Australia (<?php echo esc_html( $tx['year'] ); ?>)</label>
					<select id="<?php echo esc_attr( $id ); ?>-inc" name="marginal">
						<?php foreach ( $bands as $i => $band ) : ?>
							<option value="<?php echo esc_attr( $band['rate'] ); ?>" <?php selected( 2, $i ); ?>><?php echo esc_html( $band['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<label class="oz-check">
					<input type="checkbox" name="medicare" value="1" checked>
					<span>I pay the Medicare levy (2%). <small>Many temporary visa holders who can't use Medicare can claim an exemption.</small></span>
				</label>

				<div class="oz-field-row oz-field-row--3">
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-au">Australian savings</label>
						<div class="oz-input oz-input--post"><input id="<?php echo esc_attr( $id ); ?>-au" name="au_rate" type="number" inputmode="decimal" min="0" max="20" step="0.05" value="<?php echo esc_attr( $d['au_rate'] ); ?>"><span>%</span></div>
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-nre">NRE FD</label>
						<div class="oz-input oz-input--post"><input id="<?php echo esc_attr( $id ); ?>-nre" name="nre_rate" type="number" inputmode="decimal" min="0" max="20" step="0.05" value="<?php echo esc_attr( $d['nre_rate'] ); ?>"><span>%</span></div>
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-nro">NRO FD</label>
						<div class="oz-input oz-input--post"><input id="<?php echo esc_attr( $id ); ?>-nro" name="nro_rate" type="number" inputmode="decimal" min="0" max="20" step="0.05" value="<?php echo esc_attr( $d['nro_rate'] ); ?>"><span>%</span></div>
					</div>
				</div>
				<p class="oz-hint">Interest rates a year. Use the rates you're actually offered.</p>

				<details class="oz-details">
					<summary>Exchange rate and tax assumptions</summary>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-fx">AUD→INR today</label>
						<div class="oz-input oz-input--pre"><span>₹</span><input id="<?php echo esc_attr( $id ); ?>-fx" name="fx" type="number" inputmode="decimal" min="1" step="0.01" value="<?php echo esc_attr( $d['fallback_aud_inr'] ); ?>"></div>
						<p class="oz-hint" data-oz-fx-note>Fetching today's rate…</p>
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-chg">Rupee weakens against AUD by (per year)</label>
						<div class="oz-input oz-input--post"><input id="<?php echo esc_attr( $id ); ?>-chg" name="inr_change" type="number" inputmode="decimal" min="-10" max="15" step="0.1" value="<?php echo esc_attr( $d['inr_change'] ); ?>"><span>%</span></div>
						<p class="oz-hint">Over the last decade the rupee has lost roughly 1–2% a year against AUD on average, but some years it gained. Enter 0 to ignore currency movement.</p>
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-cost">Transfer cost each way</label>
						<div class="oz-input oz-input--post"><input id="<?php echo esc_attr( $id ); ?>-cost" name="fx_cost" type="number" inputmode="decimal" min="0" max="5" step="0.1" value="<?php echo esc_attr( $d['fx_cost'] ); ?>"><span>%</span></div>
						<p class="oz-hint">Fees plus exchange margin, sending money to India and bringing it back.</p>
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-tds">Indian tax deducted on NRO interest</label>
						<select id="<?php echo esc_attr( $id ); ?>-tds" name="nro_tds">
							<option value="<?php echo esc_attr( $config['india']['nro_tds'] ); ?>"><?php echo esc_html( round( $config['india']['nro_tds'] * 100, 1 ) ); ?>% — standard TDS</option>
							<option value="<?php echo esc_attr( $config['india']['dtaa_interest'] ); ?>"><?php echo esc_html( round( $config['india']['dtaa_interest'] * 100 ) ); ?>% — treaty rate claimed (TRC + Form 10F)</option>
						</select>
					</div>
				</details>
			</div>

			<div class="oz-results" aria-live="polite">
				<p class="oz-kicker" data-oz-headline-kicker>After <?php echo (int) $d['years']; ?> years, in Australian dollars</p>
				<div class="oz-compare" data-oz-compare></div>
				<ul class="oz-insights" data-oz-insights></ul>
			</div>
		</div>

		<details class="oz-details oz-method">
			<summary>How this is calculated</summary>
			<ul>
				<li>Interest is added once a year and tax on it is paid from that year's interest.</li>
				<li><strong>Australian savings:</strong> interest is taxed at your marginal rate (plus Medicare levy if ticked).</li>
				<li><strong>NRE FD:</strong> tax-free in India. If you're a temporary resident for Australian tax, foreign interest is generally not taxed in Australia. Permanent residents and citizens must declare it and pay tax at their marginal rate.</li>
				<li><strong>NRO FD:</strong> India deducts TDS. Permanent residents also pay Australian tax, reduced by a foreign income tax offset — capped at the <?php echo esc_html( round( $config['india']['dtaa_interest'] * 100 ) ); ?>% India can charge under the tax treaty. Any extra Indian TDS has to be reclaimed by filing an Indian return.</li>
				<li>Indian deposits are converted at today's rate (less the transfer cost) and brought back at the end at a rate that has moved by the rupee change you entered (less the transfer cost again).</li>
				<li>Tax rates for <?php echo esc_html( $tx['year'] ); ?>. "Temporary resident" has a specific ATO meaning — most temporary visa holders qualify, but not if your spouse is an Australian citizen or PR.</li>
			</ul>
		</details>
	</div>

	<?php echo Oz_Tools_Shortcodes::signup_box( 'savings', 'Watch the rupee without checking every day', 'Get the AUD→INR rate and new money guides in one short weekly email.' ); // phpcs:ignore ?>

	<p class="oz-disclaimer">General information only — not tax or financial advice. Your situation may differ; talk to a registered tax agent for your own return. Figures last reviewed <?php echo esc_html( wp_date( 'j F Y', strtotime( $config['reviewed'] ) ) ); ?>.</p>
</div>
