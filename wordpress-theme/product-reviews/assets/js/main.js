/* Frontend enhancements: mobile menu, dept mega-menu, TOC scrollspy,
   affiliate click tracking, mobile sticky CTA, exit-intent modal. */
(function () {
	"use strict";

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

	/* Mobile menu toggle */
	document.addEventListener("click", function (e) {
		var t = e.target.closest("[data-yf-toggle='mobile']");
		if (!t) return;
		var m = $("[data-yf-mobile]");
		if (!m) return;
		var open = m.classList.toggle("is-open");
		t.setAttribute("aria-expanded", open ? "true" : "false");
	});

	/* Department mega-menu */
	document.addEventListener("click", function (e) {
		var bar = $("[data-yf-deptbar]");
		if (!bar) return;
		var btn = e.target.closest("[data-yf-deptbtn]");
		if (btn) {
			var open = bar.classList.toggle("is-open");
			btn.setAttribute("aria-expanded", open ? "true" : "false");
			e.preventDefault();
			return;
		}
		if (bar.classList.contains("is-open") && !bar.contains(e.target)) {
			bar.classList.remove("is-open");
		}
	});
	document.addEventListener("keydown", function (e) {
		if (e.key === "Escape") {
			var bar = $("[data-yf-deptbar]");
			if (bar) bar.classList.remove("is-open");
		}
	});

	/* CTA click tracking */
	document.addEventListener("click", function (e) {
		var el = e.target.closest(".yf-cta[data-asin]");
		if (!el) return;
		try {
			var data = { asin: el.dataset.asin || "", slug: el.dataset.slug || "" };
			navigator.sendBeacon &&
				navigator.sendBeacon(
					(window.YadFood && YadFood.restUrl ? YadFood.restUrl : "/wp-json/yadfood/v1/") + "click",
					new Blob([JSON.stringify(data)], { type: "application/json" })
				);
		} catch (err) {}
	});

	/* TOC scrollspy */
	var tocLinks = $$(".yf-toc a[href^='#']");
	if (tocLinks.length && "IntersectionObserver" in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					tocLinks.forEach(function (a) {
						a.classList.toggle("is-active", a.getAttribute("href") === "#" + entry.target.id);
					});
				}
			});
		}, { rootMargin: "-40% 0px -55% 0px" });
		$$(".yf-review__main [id]").forEach(function (s) { io.observe(s); });
	}

	/* Mobile sticky CTA — mirrors top pick */
	(function stickyCta() {
		if (window.innerWidth >= 900) return;
		var firstCta = $(".yf-product .yf-cta[data-asin]");
		if (!firstCta) return;
		var bar = document.createElement("div");
		bar.className = "yf-sticky-cta";
		bar.innerHTML =
			'<img src="' + (firstCta.dataset.image || "") + '" alt="">' +
			'<div class="yf-sticky-cta__title"><strong>' + (firstCta.dataset.title || "").slice(0, 42) + '</strong>' +
			'<span class="yf-sticky-cta__price">' + (firstCta.dataset.price || "Top pick") + '</span></div>' +
			'<a class="yf-cta" href="' + firstCta.href + '" target="_blank" rel="nofollow sponsored noopener" data-asin="' + firstCta.dataset.asin + '" data-slug="' + firstCta.dataset.slug + '">Check Price ↗</a>';
		document.body.appendChild(bar);
		var trigger = firstCta.getBoundingClientRect().top + window.scrollY + 200;
		window.addEventListener("scroll", function () {
			var y = window.scrollY;
			bar.classList.toggle("is-visible", y > trigger && y + window.innerHeight < document.body.scrollHeight - 300);
		}, { passive: true });
	})();

	/* Exit-intent modal — desktop only, one-shot per session */
	(function exitIntent() {
		if (window.innerWidth < 900) return;
		if (sessionStorage.getItem("yf-exit-shown")) return;
		var firstCta = $(".yf-product .yf-cta[data-asin]");
		if (!firstCta) return;

		function show() {
			if (sessionStorage.getItem("yf-exit-shown")) return;
			sessionStorage.setItem("yf-exit-shown", "1");
			var modal = document.createElement("div");
			modal.className = "yf-exit-modal is-open";
			modal.innerHTML =
				'<div class="yf-exit-modal__card" role="dialog" aria-modal="true" aria-label="Don\'t miss this deal">' +
				'<button type="button" class="yf-exit-modal__close" aria-label="Close">×</button>' +
				'<h3 class="yf-exit-modal__title">Don\'t miss this deal</h3>' +
				'<p style="font-size:.85rem;color:var(--yf-ink-soft);margin:0;">Our top pick right now on Amazon:</p>' +
				'<div class="yf-exit-modal__body"><img src="' + (firstCta.dataset.image || "") + '" alt="">' +
				'<div><strong style="display:block;font-family:var(--yf-font-serif);font-size:1.05rem;">' + (firstCta.dataset.title || "").slice(0, 60) + '</strong>' +
				'<span style="font-size:.85rem;color:var(--yf-ink-soft);">' + (firstCta.dataset.price || "") + ' · Live Amazon price</span></div></div>' +
				'<a class="yf-cta" style="width:100%;" href="' + firstCta.href + '" target="_blank" rel="nofollow sponsored noopener">Check Price on Amazon ↗</a>' +
				'</div>';
			document.body.appendChild(modal);
			modal.addEventListener("click", function (e) {
				if (e.target === modal || e.target.classList.contains("yf-exit-modal__close")) modal.remove();
			});
		}

		document.addEventListener("mouseout", function (e) {
			if (!e.toElement && !e.relatedTarget && e.clientY < 10) show();
		});
	})();
})();
