// Run with: node --test ozmoneytalks-tools/tests/calc.test.js
// Expected values are worked by hand in the comments.
const test = require('node:test');
const assert = require('node:assert/strict');
const C = require('../assets/js/oz-calc.js');

const close = (a, b, eps = 0.01) => assert.ok(Math.abs(a - b) < eps, `${a} ≉ ${b}`);

test('rent: bond + advance + furniture + setup', () => {
	const r = C.rentCosts({ rent: 500, bondWeeks: 4, advanceWeeks: 2, furniture: 1200, setup: { a: 300, b: 150 } });
	assert.equal(r.bond, 2000);
	assert.equal(r.advance, 1000);
	assert.equal(r.total, 4650);
	assert.equal(r.refundable, 2000);
});

test('rent: negative and blank inputs are treated as zero', () => {
	const r = C.rentCosts({ rent: '', bondWeeks: -3, advanceWeeks: 2, furniture: 'x', setup: { a: -50 } });
	assert.equal(r.total, 0);
});

test('affordability bands and 30% max rent', () => {
	const limits = { comfortable: 0.25, stress: 0.30 };
	const a = C.affordability(500, 100000, limits); // 26,000 / 100,000
	close(a.share, 0.26, 1e-9);
	assert.equal(a.level, 'tight');
	close(a.maxWeekly, 576.92);
	assert.equal(C.affordability(400, 100000, limits).level, 'comfortable');
	assert.equal(C.affordability(700, 100000, limits).level, 'stress');
	assert.equal(C.affordability(500, 0, limits), null);
});

const base = {
	amount: 10000, years: 1, marginal: 0.30, medicare: true, medicareRate: 0.02,
	auRate: 5, nreRate: 7, nroRate: 7, fx: 50, inrChange: 0, fxCost: 0, nroTds: 0.312, dtaa: 0.15
};

test('savings: temporary resident — Indian interest not taxed in Australia', () => {
	const r = C.savingsCompare({ ...base, status: 'temporary' });
	close(r.au.end, 10340);          // 500 interest − 32% tax
	close(r.nre.end, 10700);         // ₹35,000 interest, no tax
	close(r.nro.end, 10481.6);       // ₹35,000 − 31.2% TDS = ₹24,080
	close(r.nro.reclaimable, 113.4); // (31.2% − 15%) × ₹35,000 / 50
});

test('savings: PR — NRE taxed by ATO; NRO gets FITO capped at the 15% treaty rate', () => {
	const r = C.savingsCompare({ ...base, status: 'permanent' });
	close(r.nre.end, 10476);   // ₹35,000 − 32% = ₹23,800
	close(r.nro.end, 10362.6); // TDS ₹10,920; AU tax ₹11,200 − FITO ₹5,250 = ₹5,950
	close(r.nro.tax, 337.4);   // (₹10,920 + ₹5,950) / 50
});

test('savings: PR with treaty TDS — FITO wipes out double tax', () => {
	const r = C.savingsCompare({ ...base, status: 'permanent', nroTds: 0.15 });
	close(r.nro.end, r.nre.end); // 15% India + 17% Australia = 32% total, same as NRE
	assert.equal(r.nro.reclaimable, 0);
});

test('savings: rupee fall and transfer costs', () => {
	const fall = C.savingsCompare({ ...base, status: 'temporary', inrChange: 10 });
	close(fall.nre.end, 9727.27); // ₹535,000 / 55
	const cost = C.savingsCompare({ ...base, status: 'temporary', fxCost: 1 });
	close(cost.nre.end, 10487.07); // ₹495,000 × 1.07 / 50 × 0.99
	close(cost.au.end, 10340);      // No FX cost on the Australian account
});

test('savings: multi-year compounding of after-tax interest', () => {
	const r = C.savingsCompare({ ...base, status: 'temporary', years: 3 });
	close(r.au.end, 10000 * Math.pow(1 + 0.05 * 0.68, 3));
	close(r.au.annual, 0.034, 1e-9);
});

test('break-even rupee fall where NRE equals Australian savings', () => {
	const be = C.breakEvenChange({ ...base, status: 'temporary' }, 'nre');
	close(be, (10700 / 10340 - 1) * 100, 1e-6); // ≈ 3.48% a year
	// Australian rate far higher: NRE never wins, even with a strengthening rupee.
	assert.equal(C.breakEvenChange({ ...base, status: 'temporary', auRate: 60 }, 'nre'), null);
});

test('rate stats', () => {
	const h = [50, 51, 52, 53, 54, 55, 56].map((v, i) => ['2026-09-0' + (i + 1), v]);
	const s = C.rateStats(h);
	assert.equal(s.last, 56);
	assert.equal(s.min, 50);
	assert.equal(s.max, 56);
	assert.equal(s.weekChange, 5);
	assert.equal(s.monthChange, 6);
	assert.equal(C.rateStats([]), null);
});
