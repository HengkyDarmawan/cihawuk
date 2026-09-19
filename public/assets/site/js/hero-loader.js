/*
 * Pemuat scene Three.js hero (ditunda). Ilustrasi statis HTML/SVG tampil lebih dulu;
 * WebGL hanya dimuat bila perangkat dan preferensi pengguna memungkinkan.
 */
(function () {
	'use strict';
	var el = document.querySelector('[data-hero-scene]');
	if (!el) return;

	function webglAvailable() {
		try {
			var c = document.createElement('canvas');
			return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
		} catch (e) {
			return false;
		}
	}

	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
	var saveData = conn && (conn.saveData || /(^|-)2g$/.test(conn.effectiveType || ''));
	var lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory < 3;
	var fewCores = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency < 4;
	var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

	// Perangkat sentuh dengan sumber daya terbatas memakai fallback statis.
	if (reduce || saveData || lowMemory || (coarse && fewCores) || !webglAvailable()) {
		el.setAttribute('data-hero-scene-state', 'fallback');
		return;
	}

	var started = false;
	function load() {
		if (started) return;
		started = true;
		var src = el.getAttribute('data-src');
		// Muat setelah konten utama selesai dirender.
		var go = function () {
			import(src).then(function (mod) {
				mod.mount(el, {
					maxPixelRatio: 1.5,
					mist: !coarse,
					onFail: function () { el.setAttribute('data-hero-scene-state', 'fallback'); }
				});
				requestAnimationFrame(function () { el.classList.add('is-ready'); });
				el.setAttribute('data-hero-scene-state', 'running');
			}).catch(function () {
				el.setAttribute('data-hero-scene-state', 'fallback');
			});
		};
		if ('requestIdleCallback' in window) {
			window.requestIdleCallback(go, { timeout: 2500 });
		} else {
			setTimeout(go, 600);
		}
	}

	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			if (entries[0].isIntersecting) {
				io.disconnect();
				load();
			}
		}, { rootMargin: '200px' });
		io.observe(el);
	} else {
		load();
	}
})();
