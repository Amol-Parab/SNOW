<?php
/**
 * Numbers that change over time. Review this file every 1 July (new tax year)
 * and whenever a state changes its tenancy rules, then update 'reviewed'.
 *
 * Everything here can also be overridden with the 'oz_tools_config' filter.
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Shown to readers as "Figures last reviewed".
	'reviewed'     => '2026-09-25',

	// Australian resident individual tax rates. Each bracket is [upper limit, marginal rate].
	// FY2026-27: the 16% rate fell to 15% from 1 July 2026.
	'tax'          => array(
		'year'     => '2026–27',
		'brackets' => array(
			array( 18200, 0 ),
			array( 45000, 0.15 ),
			array( 135000, 0.30 ),
			array( 190000, 0.37 ),
			array( null, 0.45 ),
		),
		'medicare' => 0.02,
	),

	// India side.
	'india'        => array(
		// TDS on NRO interest without treaty paperwork: 30% + 4% cess.
		'nro_tds'      => 0.312,
		// Maximum India can tax interest under the India–Australia DTAA (needs TRC + Form 10F).
		'dtaa_interest' => 0.15,
	),

	// Starting values for the savings comparator. Readers are told to overwrite them.
	'savings'      => array(
		'amount'          => 20000,
		'years'           => 3,
		'au_rate'         => 4.5,
		'nre_rate'        => 6.5,
		'nro_rate'        => 6.5,
		'inr_change'      => 2.0, // % per year the rupee weakens against AUD.
		'fx_cost'         => 0.5, // % lost to FX margin + fees, each direction.
		'fallback_aud_inr' => 58.0, // Used only if the live rate can't be fetched.
	),

	// Typical upfront rental costs by state. Bond and rent-in-advance are the usual
	// maximums; readers can overwrite them. Check each authority before a July review.
	'states'       => array(
		'NSW' => array( 'name' => 'New South Wales', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'NSW Fair Trading', 'url' => 'https://www.nsw.gov.au/housing-and-construction/renting-a-place-to-live' ),
		'VIC' => array( 'name' => 'Victoria', 'bond_weeks' => 4.33, 'advance_weeks' => 4.33, 'authority' => 'Consumer Affairs Victoria', 'url' => 'https://www.consumer.vic.gov.au/housing/renting' ),
		'QLD' => array( 'name' => 'Queensland', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'Residential Tenancies Authority', 'url' => 'https://www.rta.qld.gov.au/' ),
		'WA'  => array( 'name' => 'Western Australia', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'Consumer Protection WA', 'url' => 'https://www.commerce.wa.gov.au/consumer-protection/renting-home' ),
		'SA'  => array( 'name' => 'South Australia', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'Consumer and Business Services', 'url' => 'https://www.sa.gov.au/topics/housing/renting-and-letting' ),
		'TAS' => array( 'name' => 'Tasmania', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'Consumer, Building and Occupational Services', 'url' => 'https://www.cbos.tas.gov.au/topics/housing/renting' ),
		'ACT' => array( 'name' => 'Australian Capital Territory', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'ACT Government', 'url' => 'https://www.act.gov.au/housing-planning-and-property/renting' ),
		'NT'  => array( 'name' => 'Northern Territory', 'bond_weeks' => 4, 'advance_weeks' => 2, 'authority' => 'NT Consumer Affairs', 'url' => 'https://nt.gov.au/property/renters' ),
	),

	// One-off setup costs in the rent calculator (AUD). Readers can edit each one.
	'setup_costs'  => array(
		'moving'    => array( 'label' => 'Moving / removalist / ride with luggage', 'amount' => 300 ),
		'utilities' => array( 'label' => 'Electricity, gas and internet connection', 'amount' => 150 ),
		'pantry'    => array( 'label' => 'First grocery and household shop', 'amount' => 300 ),
	),
	'furniture'    => array(
		'none'  => array( 'label' => 'Furnished place / bringing my own', 'amount' => 0 ),
		'basic' => array( 'label' => 'Basics (mattress, table, kitchen, second-hand)', 'amount' => 1200 ),
		'full'  => array( 'label' => 'Mostly new furniture and appliances', 'amount' => 4000 ),
	),

	// Rent as a share of gross household income.
	'affordability' => array(
		'comfortable' => 0.25,
		'stress'      => 0.30,
	),

	// Currency data for the rate alert (Frankfurter, ECB reference rates).
	'fx_api'       => 'https://api.frankfurter.dev/v1',
);
