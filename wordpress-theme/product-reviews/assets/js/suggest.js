/**
 * Lightweight search-as-you-type. Attaches to any
 *   <input type="search">  or  input[data-pr-suggest]
 * inside the theme. Renders a panel of results beneath the input.
 */
(function () {
	if (typeof window === 'undefined' || !window.PR_SUGGEST) return;
	var cfg = window.PR_SUGGEST;

	function debounce(fn, wait) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(ctx, args); }, wait);
		};
	}

	function buildPanel(input) {
		var panel = document.createElement('div');
		panel.className = 'pr-suggest__panel';
		panel.setAttribute('role', 'listbox');
		panel.hidden = true;
		input.parentNode.style.position = input.parentNode.style.position || 'relative';
		input.parentNode.appendChild(panel);
		return panel;
	}

	function render(panel, data) {
		if (!data.results || !data.results.length) {
			panel.innerHTML = '<div class="pr-suggest__empty">No matches yet — press Enter to search.</div>';
			panel.hidden = false;
			return;
		}
		var html = data.results.map(function (r) {
			return '<a class="pr-suggest__item" role="option" href="' + r.url + '">' +
				'<span class="pr-suggest__type">' + r.type + '</span>' +
				'<span class="pr-suggest__title">' + (r.title || '') + '</span>' +
				(r.meta ? '<span class="pr-suggest__meta">' + r.meta + '</span>' : '') +
				'</a>';
		}).join('');
		panel.innerHTML = html;
		panel.hidden = false;
	}

	function attach(input) {
		if (input.__prSuggestBound) return;
		input.__prSuggestBound = true;
		input.setAttribute('autocomplete', 'off');
		var panel = buildPanel(input);

		var fetchSuggest = debounce(function (q) {
			fetch(cfg.endpoint + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) { render(panel, data); })
				.catch(function () { panel.hidden = true; });
		}, 180);

		input.addEventListener('input', function () {
			var q = input.value.trim();
			if (q.length < 2) { panel.hidden = true; return; }
			fetchSuggest(q);
		});
		input.addEventListener('blur', function () {
			setTimeout(function () { panel.hidden = true; }, 120);
		});
		input.addEventListener('focus', function () {
			if (input.value.trim().length >= 2) panel.hidden = false;
		});
	}

	function init() {
		var inputs = document.querySelectorAll('input[type=search], input[data-pr-suggest]');
		inputs.forEach(attach);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
