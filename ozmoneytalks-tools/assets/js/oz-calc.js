/**
 * OzMoneyTalks Tools — pure calculation functions (no DOM).
 * Used by oz-tools.js in the browser and by tests/calc.test.js in Node.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.OZCalc = factory();
	}
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function num(v, fallback) {
		var n = parseFloat(v);
		return isFinite(n) ? n : (fallback || 0);
	}

	/* ---------------- Rent ---------------- */

	/**
	 * @param {object} p { rent, bondWeeks, advanceWeeks, furniture, setup: {key: amount} }
	 * @return {object} { bond, advance, furniture, setup: {key: amount}, total, refundable }
	 */
	function rentCosts(p) {
		var rent = Math.max(0, num(p.rent));
		var bond = rent * Math.max(0, num(p.bondWeeks));
		var advance = rent * Math.max(0, num(p.advanceWeeks));
		var furniture = Math.max(0, num(p.furniture));
		var setup = {};
		var setupTotal = 0;
		Object.keys(p.setup || {}).forEach(function (k) {
			setup[k] = Math.max(0, num(p.setup[k]));
			setupTotal += setup[k];
		});
		return {
			bond: bond,
			advance: advance,
			furniture: furniture,
			setup: setup,
			total: bond + advance + furniture + setupTotal,
			refundable: bond
		};
	}

	/**
	 * Rent as a share of gross income.
	 * @param {number} weeklyRent
	 * @param {number} annualIncome gross, household
	 * @param {object} limits { comfortable: 0.25, stress: 0.30 }
	 */
	function affordability(weeklyRent, annualIncome, limits) {
		var income = num(annualIncome);
		if (income <= 0) return null;
		var share = (num(weeklyRent) * 52) / income;
		var level = share <= limits.comfortable ? 'comfortable' : (share <= limits.stress ? 'tight' : 'stress');
		return {
			share: share,
			level: level,
			maxWeekly: (income * limits.stress) / 52
		};
	}

	/* ---------------- Savings: India vs Australia ---------------- */

	/**
	 * @param {object} p
	 *   amount (AUD), years, status ('temporary'|'permanent'), marginal (0.30), medicare (bool),
	 *   medicareRate (0.02), auRate/nreRate/nroRate (% a year), fx (INR per AUD today),
	 *   inrChange (% a year the rupee weakens), fxCost (% each way), nroTds (0.312), dtaa (0.15)
	 * @return {object} { au, nre, nro } each { end, gain, annual, tax, reclaimable, fxCost }
	 */
	function savingsCompare(p) {
		var amount = Math.max(0, num(p.amount));
		var years = Math.max(1, Math.round(num(p.years, 1)));
		var auTaxRate = num(p.marginal) + (p.medicare ? num(p.medicareRate, 0.02) : 0);
		var permanent = p.status === 'permanent';
		var fx = Math.max(0.0001, num(p.fx));
		var change = num(p.inrChange) / 100;
		var cost = Math.max(0, num(p.fxCost)) / 100;
		var dtaa = num(p.dtaa, 0.15);

		function annual(end) {
			return amount > 0 && end > 0 ? Math.pow(end / amount, 1 / years) - 1 : 0;
		}

		// Australian savings account.
		var bal = amount, auTax = 0;
		for (var y = 0; y < years; y++) {
			var i = bal * (num(p.auRate) / 100);
			var t = i * auTaxRate;
			auTax += t;
			bal += i - t;
		}
		var au = { end: bal, gain: bal - amount, annual: annual(bal), tax: auTax, reclaimable: 0, fxCost: 0 };

		// Indian deposit, in rupees, converted back at the end.
		function india(ratePct, indiaTaxRate) {
			var inr = amount * (1 - cost) * fx;
			var taxAud = 0, reclaimAud = 0;
			for (var y = 0; y < years; y++) {
				var rateThisYear = fx * Math.pow(1 + change, y + 1);
				var i = inr * (ratePct / 100);
				var indTax = i * indiaTaxRate;
				var auNet = 0;
				if (permanent) {
					var auT = i * auTaxRate;
					var fito = Math.min(indTax, i * dtaa, auT);
					auNet = auT - fito;
				}
				if (indiaTaxRate > dtaa) {
					reclaimAud += (i * (indiaTaxRate - dtaa)) / rateThisYear;
				}
				taxAud += (indTax + auNet) / rateThisYear;
				inr += i - indTax - auNet;
			}
			var endRate = fx * Math.pow(1 + change, years);
			var endGross = inr / endRate;
			var end = endGross * (1 - cost);
			return {
				end: end,
				gain: end - amount,
				annual: annual(end),
				tax: taxAud,
				reclaimable: reclaimAud,
				fxCost: amount * cost + endGross * cost
			};
		}

		return {
			au: au,
			nre: india(num(p.nreRate), 0),
			nro: india(num(p.nroRate), num(p.nroTds, 0.312)),
			auTaxRate: auTaxRate
		};
	}

	/**
	 * The yearly rupee fall (in %) at which an Indian option ends up equal to the Australian account.
	 * Returns null if there's no crossover in a sensible range.
	 */
	function breakEvenChange(p, key) {
		function diff(c) {
			var r = savingsCompare(Object.assign({}, p, { inrChange: c }));
			return r[key].end - r.au.end;
		}
		var lo = -20, hi = 40;
		if (diff(lo) < 0 || diff(hi) > 0) return null;
		for (var k = 0; k < 60; k++) {
			var mid = (lo + hi) / 2;
			if (diff(mid) > 0) lo = mid; else hi = mid;
		}
		return (lo + hi) / 2;
	}

	/* ---------------- Rate history ---------------- */

	/**
	 * @param {Array} history [[date, rate], ...] oldest first
	 */
	function rateStats(history) {
		if (!history || !history.length) return null;
		var rates = history.map(function (h) { return h[1]; });
		var last = rates[rates.length - 1];
		var weekAgo = rates.length > 5 ? rates[rates.length - 6] : rates[0];
		return {
			last: last,
			min: Math.min.apply(null, rates),
			max: Math.max.apply(null, rates),
			weekChange: last - weekAgo,
			monthChange: last - rates[0]
		};
	}

	return {
		rentCosts: rentCosts,
		affordability: affordability,
		savingsCompare: savingsCompare,
		breakEvenChange: breakEvenChange,
		rateStats: rateStats
	};
});
