<?php
/**
 * Rent move-in cost and affordability calculator. Vars: $config, $state.
 */
defined( 'ABSPATH' ) || exit;

$id = wp_unique_id( 'oz-rent-' );
$st = $config['states'][ $state ];
?>
<div class="oz-tool oz-rent"<?php echo oz_tools_root_style(); // phpcs:ignore ?> data-oz-rent>
	<div class="oz-card">
		<div class="oz-head">
			<h2 class="oz-title">How much cash do you need to move in?</h2>
			<p class="oz-sub">Bond, rent in advance and setup costs add up fast. Work out your number before you apply.</p>
		</div>

		<div class="oz-grid">
			<div class="oz-form">
				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-state">State or territory</label>
					<select id="<?php echo esc_attr( $id ); ?>-state" name="state">
						<?php foreach ( $config['states'] as $code => $s ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $state ); ?>><?php echo esc_html( $s['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-rent">Weekly rent</label>
					<div class="oz-input oz-input--pre"><span>$</span><input id="<?php echo esc_attr( $id ); ?>-rent" name="rent" type="number" inputmode="decimal" min="0" step="10" value="550"></div>
				</div>

				<div class="oz-field-row">
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-bond">Bond (weeks)</label>
						<input id="<?php echo esc_attr( $id ); ?>-bond" name="bond_weeks" type="number" inputmode="decimal" min="0" max="8" step="0.01" value="<?php echo esc_attr( $st['bond_weeks'] ); ?>">
					</div>
					<div class="oz-field">
						<label for="<?php echo esc_attr( $id ); ?>-adv">Rent in advance (weeks)</label>
						<input id="<?php echo esc_attr( $id ); ?>-adv" name="advance_weeks" type="number" inputmode="decimal" min="0" max="8" step="0.01" value="<?php echo esc_attr( $st['advance_weeks'] ); ?>">
					</div>
				</div>
				<p class="oz-hint" data-oz-state-note>Typical maximums in <?php echo esc_html( $st['name'] ); ?>. <a href="<?php echo esc_url( $st['url'] ); ?>" target="_blank" rel="noopener">Check with <?php echo esc_html( $st['authority'] ); ?> ↗</a></p>

				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-furn">Furniture</label>
					<select id="<?php echo esc_attr( $id ); ?>-furn" name="furniture">
						<?php foreach ( $config['furniture'] as $key => $f ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, 'basic' ); ?>><?php echo esc_html( $f['label'] ); ?> — $<?php echo esc_html( number_format( $f['amount'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<details class="oz-details">
					<summary>Other setup costs</summary>
					<?php foreach ( $config['setup_costs'] as $key => $c ) : ?>
						<div class="oz-field">
							<label for="<?php echo esc_attr( $id . '-' . $key ); ?>"><?php echo esc_html( $c['label'] ); ?></label>
							<div class="oz-input oz-input--pre"><span>$</span><input id="<?php echo esc_attr( $id . '-' . $key ); ?>" name="setup_<?php echo esc_attr( $key ); ?>" data-setup="<?php echo esc_attr( $key ); ?>" type="number" inputmode="decimal" min="0" step="10" value="<?php echo esc_attr( $c['amount'] ); ?>"></div>
						</div>
					<?php endforeach; ?>
				</details>

				<div class="oz-field">
					<label for="<?php echo esc_attr( $id ); ?>-income">Household income before tax (per year) <span class="oz-optional">optional</span></label>
					<div class="oz-input oz-input--pre"><span>$</span><input id="<?php echo esc_attr( $id ); ?>-income" name="income" type="number" inputmode="decimal" min="0" step="1000" placeholder="e.g. 85000"></div>
				</div>
			</div>

			<div class="oz-results" aria-live="polite">
				<p class="oz-kicker">Cash needed to move in</p>
				<p class="oz-big" data-oz-total>—</p>
				<p class="oz-note" data-oz-bond-note></p>
				<ul class="oz-breakdown" data-oz-breakdown></ul>
				<div class="oz-afford" data-oz-afford hidden></div>
			</div>
		</div>

		<div class="oz-tips">
			<h3>Before you pay anything</h3>
			<ul>
				<li><strong>Inspect first.</strong> Rental scams targeting new arrivals often ask for a bond before you've seen the place.</li>
				<li><strong>Your bond must be lodged</strong> with the state bond authority, and you should get a receipt. It's refunded when you move out, minus any damage.</li>
				<li><strong>No local rental history?</strong> Attach your job offer or CoE, recent payslips or bank statements, a reference from your landlord in India and your ID.</li>
			</ul>
		</div>
	</div>

	<?php echo Oz_Tools_Shortcodes::signup_box( 'rent', 'Get the weekly update for new arrivals', 'Rupee rate, money guides and new tools for Indians settling in Australia. One email a week.' ); // phpcs:ignore ?>

	<p class="oz-disclaimer">Estimates only. Bond and rent-in-advance limits can depend on the rent amount and lease type. Figures last reviewed <?php echo esc_html( wp_date( 'j F Y', strtotime( $config['reviewed'] ) ) ); ?>.</p>
</div>
