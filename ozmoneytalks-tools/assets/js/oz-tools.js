/**
 * OzMoneyTalks Tools — browser behaviour for all shortcodes.
 * Config arrives in window.OZTools (see class-oz-shortcodes.php).
 */
(function () {
	'use strict';

	var C = window.OZTools || {};
	var Calc = window.OZCalc;

	/* ---------------- helpers ---------------- */

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function val(el) { var n = parseFloat(el && el.value); return isFinite(n) ? n : 0; }
	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	var audFmt = new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD', maximumFractionDigits: 0 });
	function aud(n) { return audFmt.format(Math.round(n)); }
	function inr(n) { return '₹' + Number(n).toFixed(2); }
	function pct(n, dp) { return (n * 100).toFixed(dp === undefined ? 1 : dp) + '%'; }

	function track(name, params) {
		params = params || {};
		try {
			if (typeof window.gtag === 'function') window.gtag('event', name, params);
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push(Object.assign({ event: name }, params));
		} catch (e) { /* analytics must never break the tool */ }
	}

	var store = {
		get: function (k, d) {
			try { var v = window.localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; }
		},
		set: function (k, v) {
			try { window.localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ }
		}
	};

	// Count a tool as "used" the first time the reader changes an input in it.
	function trackUseOnce(area, tool) {
		var done = false;
		function h() { if (!done) { done = true; track('oz_tool_use', { tool: tool }); } }
		area.addEventListener('input', h);
		area.addEventListener('change', h);
	}

	var ratePromise = null;
	function getRate() {
		if (!ratePromise) {
			ratePromise = fetch(C.rest + 'rate', { headers: { Accept: 'application/json' } })
				.then(function (r) { if (!r.ok) throw new Error('rate'); return r.json(); })
				.catch(function () { return null; });
		}
		return ratePromise;
	}

	function fmtDate(iso) {
		var d = new Date(iso + 'T00:00:00');
		return isNaN(d) ? iso : d.toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });
	}

	/* ---------------- email signup (all tools) ---------------- */

	function initSignup(form) {
		var msg = $('.oz-msg', form);
		var btn = $('button[type=submit]', form);
		var source = form.getAttribute('data-source') || 'other';

		function say(text, bad) {
			msg.textContent = text;
			msg.classList.toggle('is-error', !!bad);
			msg.classList.toggle('is-ok', !bad);
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var email = form.elements.email.value.trim();
			var consentEl = form.elements.consent;
			var weeklyEl = form.elements.weekly;
			var targetEl = form.elements.target_rate;

			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { say('Please enter a valid email address.', true); return; }
			if (source !== 'checklist' && consentEl && !consentEl.checked) { say('Please tick the box to agree to receive emails.', true); return; }
			if (source === 'rate_alert' && !targetEl.value && !weeklyEl.checked) { say('Set a target rate or choose the weekly email.', true); return; }

			var body = {
				email: email,
				source: source,
				consent: !!(consentEl && consentEl.checked),
				weekly: weeklyEl ? (weeklyEl.type === 'checkbox' ? weeklyEl.checked : true) : true,
				target_rate: targetEl && targetEl.value ? parseFloat(targetEl.value) : null,
				persona: form.elements.persona ? form.elements.persona.value : '',
				website: form.elements.website ? form.elements.website.value : ''
			};

			btn.disabled = true;
			say('Sending…');
			fetch(C.rest + 'subscribe', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify(body)
			})
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (res.ok && res.j.ok) {
						say(res.j.message);
						track('oz_signup', { tool: source, consent: body.consent, alert: body.target_rate !== null });
						form.elements.email.value = '';
					} else {
						say((res.j && res.j.message) || 'Something went wrong. Please try again.', true);
					}
				})
				.catch(function () { say('Could not connect. Please try again.', true); })
				.then(function () { btn.disabled = false; });
		});
	}

	/* ---------------- #1 settling-in checklist ---------------- */

	function initChecklist(root) {
		var KEY = 'oz_checklist_v1';
		var state = store.get(KEY, { persona: root.getAttribute('data-active'), done: {} });
		var items = $$('.oz-item', root);
		var personaInput = $('input[name=persona]', root);

		function applies(li, persona) {
			var ps = li.getAttribute('data-personas').split(' ');
			return ps.indexOf('all') !== -1 || ps.indexOf(persona) !== -1;
		}

		function render() {
			var persona = state.persona;
			$$('.oz-pill', root).forEach(function (b) {
				b.setAttribute('aria-checked', b.getAttribute('data-persona') === persona ? 'true' : 'false');
			});
			if (personaInput) personaInput.value = persona;

			var total = 0, done = 0;
			items.forEach(function (li) {
				var on = applies(li, persona);
				var isDone = !!state.done[li.getAttribute('data-id')];
				li.hidden = !on;
				li.classList.toggle('is-done', isDone);
				$('input', li).checked = isDone;
				if (on) { total++; if (isDone) done++; }
			});
			$$('.oz-phase', root).forEach(function (sec) {
				var vis = $$('.oz-item', sec).filter(function (li) { return !li.hidden; });
				var d = vis.filter(function (li) { return li.classList.contains('is-done'); }).length;
				sec.hidden = vis.length === 0;
				$('[data-oz-count]', sec).textContent = d + '/' + vis.length;
			});
			$('[data-oz-bar]', root).style.width = (total ? (done / total) * 100 : 0) + '%';
			$('[data-oz-progress]', root).textContent = done + ' of ' + total + ' done' + (total && done === total ? ' — nicely done!' : '');
		}

		$$('.oz-pill', root).forEach(function (b) {
			b.addEventListener('click', function () {
				state.persona = b.getAttribute('data-persona');
				store.set(KEY, state);
				render();
				track('oz_checklist_persona', { persona: state.persona });
			});
		});

		items.forEach(function (li) {
			$('input', li).addEventListener('change', function (e) {
				var id = li.getAttribute('data-id');
				if (e.target.checked) state.done[id] = true; else delete state.done[id];
				store.set(KEY, state);
				render();
				if (e.target.checked) track('oz_checklist_tick', { item: id, persona: state.persona });
			});
		});

		$('[data-oz-hide-done]', root).addEventListener('change', function (e) {
			root.classList.toggle('oz-hide-done', e.target.checked);
		});
		$('[data-oz-print]', root).addEventListener('click', function () { window.print(); });
		$('[data-oz-reset]', root).addEventListener('click', function () {
			if (window.confirm('Clear all ticks on this device?')) {
				state.done = {};
				store.set(KEY, state);
				render();
			}
		});

		// Keyboard: arrow keys move between persona pills (radiogroup pattern).
		$('.oz-pills', root).addEventListener('keydown', function (e) {
			if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(e.key) === -1) return;
			var pills = $$('.oz-pill', root);
			var i = pills.indexOf(document.activeElement);
			if (i === -1) return;
			e.preventDefault();
			var next = pills[(i + (e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 1) + pills.length) % pills.length];
			next.focus();
			next.click();
		});

		// A ?persona=skilled link overrides the saved choice (useful from articles).
		var qp = new URLSearchParams(window.location.search).get('persona');
		if (qp && $('.oz-pill[data-persona="' + qp.replace(/[^a-z]/g, '') + '"]', root)) state.persona = qp;
		if (!$('.oz-pill[data-persona="' + state.persona + '"]', root)) state.persona = root.getAttribute('data-active');
		render();
	}

	/* ---------------- #4 rent move-in cost ---------------- */

	function bars(list, total) {
		return list.map(function (row) {
			var w = total > 0 ? Math.max(row.value > 0 ? 1.5 : 0, (row.value / total) * 100) : 0;
			return '<li><div class="oz-bd__row"><span>' + esc(row.label) + '</span><strong>' + aud(row.value) + '</strong></div>' +
				'<div class="oz-bd__track"><span style="width:' + w.toFixed(1) + '%"></span></div></li>';
		}).join('');
	}

	function initRent(root) {
		var f = function (n) { return root.querySelector('[name="' + n + '"]'); };
		function stateChanged() {
			var st = C.states[f('state').value];
			if (!st) return;
			f('bond_weeks').value = st.bond_weeks;
			f('advance_weeks').value = st.advance_weeks;
			$('[data-oz-state-note]', root).innerHTML = 'Typical maximums in ' + esc(st.name) +
				'. <a href="' + esc(st.url) + '" target="_blank" rel="noopener">Check with ' + esc(st.authority) + ' ↗</a>';
			update();
		}

		function update() {
			var setup = {};
			$$('[data-setup]', root).forEach(function (el) { setup[el.getAttribute('data-setup')] = val(el); });
			var furnKey = f('furniture').value;
			var rent = val(f('rent'));
			var r = Calc.rentCosts({
				rent: rent,
				bondWeeks: val(f('bond_weeks')),
				advanceWeeks: val(f('advance_weeks')),
				furniture: C.furniture[furnKey] ? C.furniture[furnKey].amount : 0,
				setup: setup
			});

			$('[data-oz-total]', root).textContent = aud(r.total);
			$('[data-oz-bond-note]', root).textContent = r.bond > 0
				? aud(r.bond) + ' of this is your bond, which you should get back when you move out.'
				: '';

			var rows = [
				{ label: 'Bond (refundable)', value: r.bond },
				{ label: 'Rent in advance', value: r.advance },
				{ label: 'Furniture', value: r.furniture }
			];
			Object.keys(C.setup).forEach(function (k) { rows.push({ label: C.setup[k].label, value: r.setup[k] || 0 }); });
			$('[data-oz-breakdown]', root).innerHTML = bars(rows, r.total);

			var a = Calc.affordability(rent, val(f('income')), C.afford);
			var box = $('[data-oz-afford]', root);
			if (a && rent > 0) {
				var labels = {
					comfortable: ['Comfortable', 'Rent is under ' + pct(C.afford.comfortable, 0) + ' of your income.'],
					tight: ['Manageable but tight', 'Rent is between ' + pct(C.afford.comfortable, 0) + ' and ' + pct(C.afford.stress, 0) + ' of your income.'],
					stress: ['Rental stress', 'Rent is over ' + pct(C.afford.stress, 0) + ' of your income — the usual rental stress line.']
				};
				var L = labels[a.level];
				box.hidden = false;
				box.className = 'oz-afford oz-afford--' + a.level;
				box.innerHTML = '<p class="oz-afford__head"><span class="oz-afford__dot" aria-hidden="true"></span><strong>' + esc(L[0]) +
					'</strong> · ' + pct(a.share) + ' of income</p><p>' + esc(L[1]) + ' To stay under ' + pct(C.afford.stress, 0) +
					', aim for rent up to <strong>' + aud(a.maxWeekly) + ' a week</strong>. Monthly rent: ' + aud(rent * 52 / 12) + '.</p>';
			} else {
				box.hidden = true;
			}
		}

		f('state').addEventListener('change', stateChanged);
		root.addEventListener('input', function (e) { if (e.target.name !== 'state') update(); });
		root.addEventListener('change', function (e) { if (e.target.name === 'furniture') update(); });
		trackUseOnce($('.oz-grid', root), 'rent');
		update();
	}

	/* ---------------- #3 India vs Australia savings ---------------- */

	function initSavings(root) {
		var f = function (n) { return root.querySelector('[name="' + n + '"]'); };
		var fxTouched = false;
		var names = { au: 'Australian savings', nre: 'NRE fixed deposit', nro: 'NRO fixed deposit' };

		function params() {
			var status = root.querySelector('[name=status]:checked');
			return {
				amount: val(f('amount')),
				years: val(f('years')),
				status: status ? status.value : 'temporary',
				marginal: val(f('marginal')),
				medicare: f('medicare').checked,
				medicareRate: C.tax.medicare,
				auRate: val(f('au_rate')),
				nreRate: val(f('nre_rate')),
				nroRate: val(f('nro_rate')),
				fx: val(f('fx')) || C.savings.fallback_aud_inr,
				inrChange: val(f('inr_change')),
				fxCost: val(f('fx_cost')),
				nroTds: val(f('nro_tds')),
				dtaa: C.india.dtaa_interest
			};
		}

		function update() {
			var p = params();
			var r = Calc.savingsCompare(p);
			var keys = ['au', 'nre', 'nro'];
			var best = keys.reduce(function (b, k) { return r[k].end > r[b].end ? k : b; }, 'au');
			var max = Math.max(r.au.end, r.nre.end, r.nro.end, 1);

			$('[data-oz-headline-kicker]', root).textContent = 'After ' + p.years + ' year' + (p.years > 1 ? 's' : '') + ', in Australian dollars';

			$('[data-oz-compare]', root).innerHTML = keys.map(function (k) {
				var o = r[k];
				var extra = k === 'au' ? '' : ' · transfer costs ' + aud(o.fxCost);
				return '<div class="oz-opt' + (k === best ? ' is-best' : '') + '">' +
					'<div class="oz-opt__row"><span class="oz-opt__name">' + names[k] + (k === best ? ' <em>Best here</em>' : '') + '</span>' +
					'<strong class="oz-opt__end">' + aud(o.end) + '</strong></div>' +
					'<div class="oz-bd__track"><span style="width:' + ((Math.max(o.end, 0) / max) * 100).toFixed(1) + '%"></span></div>' +
					'<p class="oz-opt__meta">' + (o.gain >= 0 ? '+' : '−') + aud(Math.abs(o.gain)) + ' · ' + pct(o.annual, 2) +
					' a year after tax' + (k === 'au' ? '' : ' and FX') + ' · tax ' + aud(o.tax) + extra + '</p></div>';
			}).join('');

			// Plain-English takeaways.
			var out = [];
			var runner = keys.filter(function (k) { return k !== best; })
				.reduce(function (b, k) { return r[k].end > r[b].end ? k : b; }, best === 'au' ? 'nre' : 'au');
			var margin = r[best].end - r[runner].end;
			out.push('<strong>' + names[best] + '</strong> comes out ahead by ' + aud(margin) + ' over ' + p.years + ' year' + (p.years > 1 ? 's' : '') + ' with these assumptions.');

			if (p.status === 'permanent') {
				out.push('As a PR or citizen, your NRE interest is taxed in Australia at ' + pct(r.auTaxRate, 0) + '. Tax-free in India doesn\'t mean tax-free here — declare it in your tax return.');
			} else {
				out.push('As a temporary resident, Indian interest is generally not taxed in Australia. That changes when you get PR — switch to "PR or citizen" above to see the difference.');
			}

			var be = Calc.breakEvenChange(p, 'nre');
			if (be !== null) {
				out.push(be > 0
					? 'The NRE deposit beats the Australian account only if the rupee weakens by less than <strong>' + be.toFixed(1) + '% a year</strong> against AUD.'
					: 'Even with a stable rupee, the NRE deposit needs the rupee to strengthen by ' + Math.abs(be).toFixed(1) + '% a year to match the Australian account.');
			}

			if (r.nro.reclaimable > 1) {
				out.push('India is deducting more than the ' + pct(C.india.dtaa_interest, 0) + ' treaty rate on NRO interest. Claiming the treaty rate or filing an Indian return could recover about ' + aud(r.nro.reclaimable) + '.');
			}
			$('[data-oz-insights]', root).innerHTML = out.map(function (t) { return '<li>' + t + '</li>'; }).join('');
		}

		f('fx').addEventListener('input', function () { fxTouched = true; });
		root.addEventListener('input', update);
		root.addEventListener('change', update);
		trackUseOnce($('.oz-grid', root), 'savings');
		update();

		getRate().then(function (d) {
			var note = $('[data-oz-fx-note]', root);
			if (d && d.rate) {
				if (!fxTouched) { f('fx').value = Number(d.rate).toFixed(2); update(); }
				note.textContent = 'Mid-market rate on ' + fmtDate(d.date) + (d.stale ? ' (latest available)' : '') + '.';
			} else {
				note.textContent = 'Couldn\'t fetch today\'s rate — enter it yourself.';
			}
		});
	}

	/* ---------------- #11 rate alert ---------------- */

	function sparkline(fig, history) {
		var W = 320, H = 90, P = 6;
		var rates = history.map(function (h) { return h[1]; });
		var min = Math.min.apply(null, rates), max = Math.max.apply(null, rates);
		var span = max - min || 1;
		var x = function (i) { return P + (i / Math.max(1, rates.length - 1)) * (W - 2 * P); };
		var y = function (v) { return P + (1 - (v - min) / span) * (H - 2 * P); };
		var d = rates.map(function (v, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); }).join(' ');
		var last = rates.length - 1;

		fig.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img">' +
			'<line class="oz-spark__base" x1="' + P + '" x2="' + (W - P) + '" y1="' + (H - P) + '" y2="' + (H - P) + '"/>' +
			'<path class="oz-spark__line" d="' + d + '"/>' +
			'<line class="oz-spark__cross" y1="' + P + '" y2="' + (H - P) + '" x1="0" x2="0" hidden/>' +
			'<circle class="oz-spark__dot" r="4" cx="' + x(last).toFixed(1) + '" cy="' + y(rates[last]).toFixed(1) + '"/>' +
			'</svg><div class="oz-spark__tip" hidden></div>' +
			'<figcaption>Last 30 business days</figcaption>';

		var svg = $('svg', fig), tip = $('.oz-spark__tip', fig), cross = $('.oz-spark__cross', fig), dot = $('.oz-spark__dot', fig);
		function show(clientX) {
			var box = svg.getBoundingClientRect();
			var rel = (clientX - box.left) / box.width * W;
			var i = Math.max(0, Math.min(last, Math.round((rel - P) / (W - 2 * P) * last)));
			cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i)); cross.hidden = false;
			dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(rates[i]));
			tip.hidden = false;
			tip.innerHTML = '<strong>' + inr(rates[i]) + '</strong> ' + esc(fmtDate(history[i][0]));
			var px = (x(i) / W) * box.width;
			tip.style.left = Math.max(0, Math.min(box.width - tip.offsetWidth, px - tip.offsetWidth / 2)) + 'px';
		}
		function hide() {
			cross.hidden = true; tip.hidden = true;
			dot.setAttribute('cx', x(last)); dot.setAttribute('cy', y(rates[last]));
		}
		svg.addEventListener('pointermove', function (e) { show(e.clientX); });
		svg.addEventListener('pointerleave', hide);
	}

	function initRateAlert(root) {
		var target = $('input[name=target_rate]', root);
		var hint = $('[data-oz-target-hint]', root);
		var current = null;

		function hintText() {
			if (!current) return;
			var t = parseFloat(target.value);
			if (!t) { hint.textContent = ''; return; }
			if (t <= current) {
				hint.textContent = 'That\'s at or below today\'s rate, so you\'d get an alert tomorrow morning.';
			} else {
				hint.textContent = 'That\'s ' + ((t / current - 1) * 100).toFixed(1) + '% above today\'s rate.';
			}
		}
		target.addEventListener('input', hintText);

		getRate().then(function (d) {
			if (!d || !d.rate) {
				$('[data-oz-rate-meta]', root).textContent = 'The rate is unavailable right now. You can still set an alert.';
				return;
			}
			current = d.rate;
			$('[data-oz-rate-now]', root).textContent = '1 AUD = ' + inr(d.rate);
			$('[data-oz-rate-meta]', root).textContent = 'Reference rate for ' + fmtDate(d.date) + (d.stale ? ' (latest available)' : '');
			if (!target.value) {
				target.value = (Math.ceil((d.rate + 0.5) * 4) / 4).toFixed(2);
				target.setAttribute('placeholder', 'e.g. ' + target.value);
				hintText();
			}
			var s = Calc.rateStats(d.history);
			if (s) {
				var arrow = function (n) { return (n >= 0 ? '▲ ' : '▼ ') + inr(Math.abs(n)); };
				$('[data-oz-stats]', root).innerHTML =
					'<div><dt>This week</dt><dd>' + arrow(s.weekChange) + '</dd></div>' +
					'<div><dt>30-day low</dt><dd>' + inr(s.min) + '</dd></div>' +
					'<div><dt>30-day high</dt><dd>' + inr(s.max) + '</dd></div>';
				if (d.history.length > 1) sparkline($('[data-oz-spark]', root), d.history);
			}
		});
	}

	/* ---------------- boot ---------------- */

	function boot() {
		if (!Calc) return;
		$$('[data-oz-checklist]').forEach(initChecklist);
		$$('[data-oz-rent]').forEach(initRent);
		$$('[data-oz-savings]').forEach(initSavings);
		$$('[data-oz-rate]').forEach(initRateAlert);
		$$('[data-oz-signup]').forEach(initSignup);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
