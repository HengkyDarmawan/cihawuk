/* Chart data desa: membaca JSON dari elemen data, bukan skrip inline.
   Jenis grafik mengikuti payload.type (bar, bar_horizontal, donut); tabel angka
   yang setara selalu ada di halaman sehingga grafik bukan satu-satunya sumber. */
(function () {
	'use strict';
	if (!window.Chart) return;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var palette = ['#174B3A', '#D7AF67', '#6FA58C', '#8A6420', '#2E6B55', '#B08A3E', '#A9C8B8', '#5B4A2E'];
	var idFormat = function (v) { return Number(v).toLocaleString('id-ID'); };
	/* payload.format === 'rupiah': "Rp2,1 M", "Rp350 jt" di sumbu; tooltip memakai "miliar"/"juta". */
	var rupiahShort = function (v, long) {
		var n = Number(v), abs = Math.abs(n);
		var fmt = function (x) { return x.toLocaleString('id-ID', { maximumFractionDigits: 1 }); };
		if (abs >= 1e9) return 'Rp' + fmt(n / 1e9) + (long ? ' miliar' : ' M');
		if (abs >= 1e6) return 'Rp' + fmt(n / 1e6) + (long ? ' juta' : ' jt');
		return 'Rp' + idFormat(n);
	};

	document.querySelectorAll('canvas[id^="chart-"]').forEach(function (canvas) {
		var dataEl = document.getElementById(canvas.id + '-data');
		if (!dataEl) return;
		var payload;
		try { payload = JSON.parse(dataEl.textContent); } catch (e) { return; }
		if (!payload || !payload.series || !payload.series.length) return;

		var kind = payload.type || 'bar';
		var horizontal = kind === 'bar_horizontal';
		var donut = kind === 'donut';
		var money = payload.format === 'rupiah';
		var valueText = money ? function (v) { return rupiahShort(v, true); } : idFormat;
		var axisText = money ? function (v) { return rupiahShort(v, false); } : idFormat;

		var datasets = payload.series.map(function (s, i) {
			return {
				label: s.label,
				data: s.data,
				backgroundColor: donut
					? payload.labels.map(function (_, j) { return palette[j % palette.length]; })
					: palette[i % palette.length],
				borderRadius: donut ? 0 : 6,
				maxBarThickness: 42
			};
		});

		var options = {
			responsive: true,
			maintainAspectRatio: false,
			animation: reduce ? false : { duration: 600 },
			plugins: {
				legend: {
					position: 'bottom',
					display: donut || datasets.length > 1,
					labels: { font: { family: 'Manrope, sans-serif' }, boxWidth: 12 }
				},
				tooltip: {
					callbacks: {
						label: function (ctx) {
							var value = donut ? ctx.parsed : (horizontal ? ctx.parsed.x : ctx.parsed.y);
							var name = donut ? ctx.label : ctx.dataset.label;
							var text = name + ': ' + valueText(value);
							if (donut && money) {
								var sum = ctx.dataset.data.reduce(function (a, b) { return a + Number(b); }, 0);
								if (sum > 0) text += ' (' + (value / sum * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%)';
							}
							return text;
						}
					}
				}
			}
		};

		if (!donut) {
			options.indexAxis = horizontal ? 'y' : 'x';
			var valueAxis = { beginAtZero: true, ticks: { callback: axisText }, grid: { color: 'rgba(23,75,58,.08)' } };
			var labelAxis = { grid: { display: false } };
			options.scales = horizontal ? { x: valueAxis, y: labelAxis } : { y: valueAxis, x: labelAxis };
		}

		if (donut && money) {
			options.cutout = '58%';
			options.plugins.legend.labels.padding = 12;
		}

		new window.Chart(canvas.getContext('2d'), {
			type: donut ? 'doughnut' : 'bar',
			data: { labels: payload.labels, datasets: datasets },
			options: options
		});
	});
})();
