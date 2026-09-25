<?php
/**
 * Settling-in checklist content. Edit freely: add, remove or reword items.
 *
 * Each item: id (stable, used to remember ticks — don't rename once live), title,
 * why (one or two sentences), optional link [label, url], and the personas it applies to.
 * Persona keys: student, skilled, partner, parent. 'all' means every persona.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'personas' => array(
		'student' => array( 'label' => 'Student', 'hint' => 'Subclass 500' ),
		'skilled' => array( 'label' => 'Skilled worker', 'hint' => '482, 186, 189, 190, 491' ),
		'partner' => array( 'label' => 'Partner or family', 'hint' => '820/801, 309/100' ),
		'parent'  => array( 'label' => 'Parent visiting', 'hint' => 'Visitor 600' ),
	),

	'phases'   => array(
		'before' => 'Before you leave India',
		'week1'  => 'First week',
		'month1' => 'First month',
		'month3' => 'First three months',
	),

	'items'    => array(
		// Before you leave India.
		array(
			'id' => 'health-cover', 'phase' => 'before', 'personas' => array( 'student', 'skilled', 'parent' ),
			'title' => 'Buy the health cover your visa requires, starting from your arrival date',
			'why'   => 'India has no reciprocal health care agreement with Australia, so most temporary visa holders can\'t use Medicare. Students need OSHC; many work visas need OVHC; visiting parents need visitor insurance. A single hospital night can cost thousands.',
			'link'  => array( 'Visa condition 8501 (Home Affairs)', 'https://immi.homeaffairs.gov.au/visas/already-have-a-visa/check-visa-details-and-conditions/see-your-visa-conditions' ),
		),
		array(
			'id' => 'nro-convert', 'phase' => 'before', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Tell your Indian bank you are becoming an NRI',
			'why'   => 'Once you are an NRI, your resident savings accounts must be converted to NRO. Open an NRE account too if you plan to send Australian money home — NRE money is freely repatriable.',
		),
		array(
			'id' => 'kyc-investments', 'phase' => 'before', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Update residential status on mutual funds, demat and insurance',
			'why'   => 'Fund houses and brokers need your NRI status and overseas address. Leaving it for later makes redemptions and transfers slow.',
		),
		array(
			'id' => 'au-bank-early', 'phase' => 'before', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Open an Australian bank account online before you fly',
			'why'   => 'Several big banks let migrants open an account before arrival. You can move money at a good rate in advance and have a card ready on day one.',
		),
		array(
			'id' => 'documents', 'phase' => 'before', 'personas' => array( 'all' ),
			'title' => 'Scan and carry key documents',
			'why'   => 'Passport, visa grant letter, degrees and transcripts, work references, driving licence, marriage and birth certificates. Keep copies in cloud storage as well as paper.',
		),
		array(
			'id' => 'medicines', 'phase' => 'before', 'personas' => array( 'all' ),
			'title' => 'Pack prescription medicines with a doctor\'s letter',
			'why'   => 'You can generally bring up to three months\' supply of personal medicine. Keep it in original packaging with the prescription, and declare it.',
			'link'  => array( 'Travelling with medicines (TGA)', 'https://www.tga.gov.au/products/unapproved-therapeutic-goods/travelling-medicines-and-medical-devices' ),
		),
		array(
			'id' => 'declare', 'phase' => 'before', 'personas' => array( 'all' ),
			'title' => 'Know what to declare at the airport',
			'why'   => 'Declare cash of AUD 10,000 or more, and declare food, spices, seeds and ayurvedic products. Declaring is fine; not declaring can mean fines.',
			'link'  => array( 'What you can bring in (ABF)', 'https://www.abf.gov.au/entering-and-leaving-australia/can-you-bring-it-in' ),
		),

		// First week.
		array(
			'id' => 'sim', 'phase' => 'week1', 'personas' => array( 'all' ),
			'title' => 'Get an Australian prepaid SIM',
			'why'   => 'Banks, employers and landlords will want an Australian mobile number. Prepaid is cheaper and needs no credit check.',
		),
		array(
			'id' => 'tfn', 'phase' => 'week1', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Apply for a Tax File Number (TFN) — it\'s free',
			'why'   => 'Without a TFN your employer must withhold tax at the top rate, and your bank will withhold tax on interest. Apply directly with the ATO; never pay a third party for this.',
			'link'  => array( 'Apply for a TFN (ATO)', 'https://www.ato.gov.au/individuals-and-families/tax-file-number/apply-for-a-tfn' ),
		),
		array(
			'id' => 'bank-verify', 'phase' => 'week1', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Verify your bank account and add your TFN to it',
			'why'   => 'Pre-arrival accounts usually need an in-branch passport check before you get full access.',
		),
		array(
			'id' => 'transport', 'phase' => 'week1', 'personas' => array( 'all' ),
			'title' => 'Get your city\'s transport card',
			'why'   => 'Opal (Sydney), myki (Melbourne), go card (Brisbane), SmartRider (Perth), metroCARD (Adelaide). Many cities also accept tap-on with a bank card.',
		),
		array(
			'id' => 'vevo', 'phase' => 'week1', 'personas' => array( 'all' ),
			'title' => 'Check your visa conditions on VEVO',
			'why'   => 'VEVO shows your work limits, study conditions and stay period. Employers and landlords may ask you to share it.',
			'link'  => array( 'VEVO (Home Affairs)', 'https://immi.homeaffairs.gov.au/visas/already-have-a-visa/check-visa-details-and-conditions/overview' ),
		),
		array(
			'id' => 'work-limits', 'phase' => 'week1', 'personas' => array( 'student' ),
			'title' => 'Understand your work hour limit',
			'why'   => 'Student visa holders can work up to 48 hours per fortnight while their course is in session. Breaching it can put your visa at risk.',
		),
		array(
			'id' => 'no-work', 'phase' => 'week1', 'personas' => array( 'parent' ),
			'title' => 'Remember visitor visas don\'t allow work',
			'why'   => 'Helping family at home is fine; paid work, including cash jobs, is not.',
		),
		array(
			'id' => 'medicare', 'phase' => 'week1', 'personas' => array( 'skilled', 'partner' ),
			'title' => 'Enrol in Medicare if you are eligible',
			'why'   => 'Permanent residents, and many people who have applied for permanent residence (including onshore partner visa applicants), can enrol. Temporary work visa holders usually can\'t.',
			'link'  => array( 'Enrol in Medicare (Services Australia)', 'https://www.servicesaustralia.gov.au/enrolling-medicare-if-you-are-migrant-or-refugee' ),
		),

		// First month.
		array(
			'id' => 'mygov', 'phase' => 'month1', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Create a myGov account and link the ATO',
			'why'   => 'This is where you lodge your tax return, see your super and track Medicare if eligible.',
			'link'  => array( 'myGov', 'https://my.gov.au/' ),
		),
		array(
			'id' => 'super', 'phase' => 'month1', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Choose a super fund and give it your TFN',
			'why'   => 'Your employer pays super on top of your wage. Picking one fund early stops multiple accounts charging fees. If you leave Australia permanently you can claim it back.',
			'link'  => array( 'Super for temporary residents (ATO)', 'https://www.ato.gov.au/individuals-and-families/super-for-individuals-and-families/super/temporary-residents-and-super' ),
		),
		array(
			'id' => 'payslip', 'phase' => 'month1', 'personas' => array( 'skilled', 'student', 'partner' ),
			'title' => 'Check your first payslip',
			'why'   => 'Make sure tax is being withheld at resident rates (if you are a tax resident) and super is shown. Mistakes are easier to fix early.',
		),
		array(
			'id' => 'rental-pack', 'phase' => 'month1', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Prepare a rental application pack',
			'why'   => 'No Australian rental history? Include your employment letter or CoE, three payslips or bank statements, references from your Indian landlord, and ID. Never pay a bond before inspecting, and make sure the bond is lodged with your state\'s bond authority.',
			'tool'  => 'rent',
		),
		array(
			'id' => 'remit', 'phase' => 'month1', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Pick a cheap way to send money to India',
			'why'   => 'Bank transfers can lose 2–4% in exchange margin. Compare total cost, not just the fee.',
			'tool'  => 'remittance',
		),
		array(
			'id' => 'licence', 'phase' => 'month1', 'personas' => array( 'skilled', 'partner', 'student' ),
			'title' => 'Check when you must convert your Indian driving licence',
			'why'   => 'Rules differ by state and by visa. Permanent residents usually have a short window (often three to six months) to get a local licence.',
		),

		// First three months.
		array(
			'id' => 'budget', 'phase' => 'month3', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Build a starter emergency fund',
			'why'   => 'Aim for one to three months of rent and bills in an Australian savings account before sending large amounts home.',
		),
		array(
			'id' => 'savings-where', 'phase' => 'month3', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Decide where your savings should sit: India or Australia',
			'why'   => 'NRE interest is tax-free in India, but permanent residents must declare it to the ATO. Many temporary visa holders don\'t. The rupee usually weakens over time, too.',
			'tool'  => 'savings',
		),
		array(
			'id' => 'itr', 'phase' => 'month3', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Check if you still need to file an Indian tax return',
			'why'   => 'If your Indian income (rent, NRO interest, capital gains) is above the basic exemption, or you want a TDS refund, you need to file an ITR in India.',
		),
		array(
			'id' => 'lhc', 'phase' => 'month3', 'personas' => array( 'skilled', 'partner' ),
			'title' => 'New PR? Look at private hospital cover before the deadline',
			'why'   => 'Lifetime Health Cover loading adds 2% per year to premiums if you don\'t take hospital cover in time. New migrants get until one year after Medicare registration.',
			'link'  => array( 'Lifetime Health Cover (privatehealth.gov.au)', 'https://www.privatehealth.gov.au/health_insurance/surcharges_incentives/lifetime_health_cover.htm' ),
		),
		array(
			'id' => 'credit', 'phase' => 'month3', 'personas' => array( 'student', 'skilled', 'partner' ),
			'title' => 'Start building an Australian credit history',
			'why'   => 'Your Indian CIBIL score doesn\'t transfer. Paying phone and utility bills on time helps. Check your free credit report once a year.',
		),
		array(
			'id' => 'stay-limit', 'phase' => 'month3', 'personas' => array( 'parent' ),
			'title' => 'Track the stay limit on the visitor visa',
			'why'   => 'Many parent visitor visas carry condition 8558: no more than 12 months in any 18-month period. Plan return flights around it.',
		),
		array(
			'id' => 'rate-alert', 'phase' => 'month3', 'personas' => array( 'all' ),
			'title' => 'Set an AUD→INR rate alert',
			'why'   => 'Timing a big transfer around a better rate can be worth more than the fee difference between providers.',
			'tool'  => 'rate_alert',
		),
	),
);
