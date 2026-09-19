/*
 * Peta Leaflet untuk titik dan batas yang sudah terverifikasi.
 * Data dibaca dari atribut data-features (GeoJSON tervalidasi server).
 * Bila tile provider tidak dapat dimuat, kontainer tetap menampilkan pesan teks.
 */
(function () {
	'use strict';
	if (!window.L) return;

	document.querySelectorAll('[data-map]').forEach(function (el) {
		var features;
		try { features = JSON.parse(el.getAttribute('data-features') || '[]'); } catch (e) { features = []; }
		if (!features.length) return;

		var tileUrl = el.getAttribute('data-tile');
		var attribution = el.getAttribute('data-attribution') || '';
		if (!tileUrl) return;

		var map = window.L.map(el, { scrollWheelZoom: false, attributionControl: true });
		window.L.tileLayer(tileUrl, { attribution: attribution, maxZoom: 18 }).addTo(map);

		var layer = window.L.geoJSON(
			{
				type: 'FeatureCollection',
				features: features.map(function (f) {
					return { type: 'Feature', properties: { title: f.title }, geometry: f.geometry };
				})
			},
			{
				style: { color: '#174B3A', weight: 2, fillColor: '#174B3A', fillOpacity: 0.12 },
				pointToLayer: function (feature, latlng) {
					return window.L.circleMarker(latlng, { radius: 8, color: '#174B3A', fillColor: '#D7AF67', fillOpacity: 1, weight: 3 });
				},
				onEachFeature: function (feature, lyr) {
					if (feature.properties && feature.properties.title) {
						lyr.bindPopup(String(feature.properties.title));
						lyr.bindTooltip(String(feature.properties.title));
					}
				}
			}
		).addTo(map);

		try {
			map.fitBounds(layer.getBounds(), { padding: [24, 24], maxZoom: 16 });
		} catch (e) {
			map.setView([0, 0], 2);
		}

		// Aktifkan scroll zoom hanya setelah peta difokuskan/diklik.
		map.on('focus click', function () { map.scrollWheelZoom.enable(); });
		map.on('blur mouseout', function () { map.scrollWheelZoom.disable(); });
	});
})();
