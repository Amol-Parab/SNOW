/**
 * OzMoneyTalks Tools — "Ask" chat helper.
 * Config arrives in window.OZChat (see class-oz-chat.php). Replies are built with
 * textContent only, so nothing from the server is ever inserted as HTML.
 */
(function () {
	'use strict';

	var C = window.OZChat || {};
	var STORE_KEY = 'oz_chat_v1';
	var KEEP = 30; // messages remembered while the tab is open

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (text) n.textContent = text;
		return n;
	}
	function safeUrl(u) { return /^https?:\/\//i.test(u || '') ? u : ''; }

	function track(name, params) {
		try {
			if (typeof window.gtag === 'function') window.gtag('event', name, params);
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push(Object.assign({ event: name }, params));
		} catch (e) { /* analytics must never break the helper */ }
	}

	var saved = {
		load: function () {
			try { return JSON.parse(window.sessionStorage.getItem(STORE_KEY)) || []; } catch (e) { return []; }
		},
		save: function (items) {
			try { window.sessionStorage.setItem(STORE_KEY, JSON.stringify(items.slice(-KEEP))); } catch (e) { /* private mode */ }
		}
	};

	function init(root) {
		var toggle = root.querySelector('.oz-chat__toggle');
		var panel = root.querySelector('.oz-chat__panel');
		var close = root.querySelector('.oz-chat__close');
		var log = root.querySelector('.oz-chat__log');
		var form = root.querySelector('.oz-chat__form');
		var input = form.querySelector('input');
		var send = form.querySelector('button');
		var items = saved.load();
		var chips = null;
		var busy = false;
		var started = false;

		/* ---------- rendering ---------- */

		function paragraphs(box, text) {
			String(text || '').split(/\n{2,}/).forEach(function (p) {
				if (p.trim()) box.appendChild(el('p', null, p.trim()));
			});
		}

		function linkList(box, heading, rows, titleKey, subKey) {
			if (!rows || !rows.length) return;
			box.appendChild(el('p', 'oz-chat__label', heading));
			var ul = el('ul', 'oz-chat__links');
			rows.forEach(function (r) {
				var url = safeUrl(r.url);
				if (!url) return;
				var li = el('li');
				var a = el('a', null, r[titleKey]);
				a.href = url;
				a.addEventListener('click', function () { track('oz_chat_click', { url: url }); });
				li.appendChild(a);
				if (r[subKey]) li.appendChild(el('span', null, r[subKey]));
				ul.appendChild(li);
			});
			box.appendChild(ul);
		}

		function renderBot(reply) {
			var box = el('div', 'oz-chat__msg oz-chat__msg--bot');
			if (reply.notice) box.appendChild(el('p', 'oz-chat__notice', reply.notice));
			paragraphs(box, reply.text);
			var more = safeUrl(reply.more);
			if (more) {
				var p = el('p');
				var a = el('a', 'oz-chat__more', 'Read more →');
				a.href = more;
				p.appendChild(a);
				box.appendChild(p);
			}
			linkList(box, 'Tools', reply.tools, 'label', 'why');
			linkList(box, 'From the site', reply.links, 'title', 'excerpt');
			return box;
		}

		function add(item, remember) {
			var node = item.role === 'user'
				? el('div', 'oz-chat__msg oz-chat__msg--user', item.text)
				: renderBot(item.reply);
			log.appendChild(node);
			if (remember) {
				items.push(item);
				saved.save(items);
			}
			log.scrollTop = log.scrollHeight;
			return node;
		}

		function renderChips() {
			var list = C.suggestions || {};
			var labels = Object.keys(list);
			if (!labels.length) return;
			chips = el('div', 'oz-chat__chips');
			labels.forEach(function (label) {
				var b = el('button', 'oz-chat__chip', label);
				b.type = 'button';
				b.addEventListener('click', function () { ask(list[label]); });
				chips.appendChild(b);
			});
			log.appendChild(chips);
		}

		function start() {
			if (started) return;
			started = true;
			add({ role: 'bot', reply: { text: C.greeting || 'Hi! What would you like to know?' } }, false);
			items.forEach(function (it) { add(it, false); });
			if (!items.length) renderChips();
		}

		/* ---------- asking ---------- */

		function ask(text) {
			text = String(text || '').trim().slice(0, C.maxLength || 300);
			if (!text || busy) return;
			busy = true;
			send.disabled = true;
			if (chips) { chips.remove(); chips = null; }
			add({ role: 'user', text: text }, true);
			input.value = '';

			var typing = el('div', 'oz-chat__msg oz-chat__msg--bot oz-chat__typing');
			typing.setAttribute('aria-label', 'Looking…');
			typing.appendChild(el('span')); typing.appendChild(el('span')); typing.appendChild(el('span'));
			log.appendChild(typing);
			log.scrollTop = log.scrollHeight;

			fetch(C.rest, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ message: text })
			})
				.then(function (r) {
					return r.json().catch(function () { return {}; }).then(function (body) {
						if (!r.ok) throw new Error(body && body.message ? body.message : '');
						return body;
					});
				})
				.then(function (reply) {
					track('oz_chat_ask', { answered: !!reply.answered });
					return reply;
				})
				.catch(function (e) {
					return { text: e.message || 'Sorry, something went wrong. Please try again.' };
				})
				.then(function (reply) {
					typing.remove();
					add({ role: 'bot', reply: reply }, true);
					busy = false;
					send.disabled = false;
					input.focus();
				});
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			ask(input.value);
		});

		/* ---------- open / close ---------- */

		function setOpen(open) {
			panel.hidden = !open;
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			root.classList.toggle('is-open', open);
			if (open) {
				start();
				log.scrollTop = log.scrollHeight;
				input.focus();
				track('oz_chat_open', {});
			} else {
				toggle.focus();
			}
		}

		toggle.addEventListener('click', function () { setOpen(panel.hidden); });
		close.addEventListener('click', function () { setOpen(false); });
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !panel.hidden) setOpen(false);
		});
	}

	function boot() {
		var root = document.querySelector('[data-oz-chat]');
		if (root && C.rest && window.fetch) init(root);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
