// Scene dekoratif hero: lapisan kontur perbukitan abstrak (bukan topografi nyata).
// Dibundel dengan esbuild (tree-shaken) ke public/assets/site/js/hero-scene.bundle.js.
import {
	BufferAttribute,
	BufferGeometry,
	Color,
	LineSegments,
	PerspectiveCamera,
	Points,
	Scene,
	ShaderMaterial,
	WebGLRenderer,
	AdditiveBlending,
} from 'three';

const LINE_VERTEX = /* glsl */ `
	attribute float aLine;
	attribute float aT;
	uniform float uTime;
	uniform float uLines;
	varying float vLine;
	varying float vT;
	void main() {
		vec3 p = position;
		float phase = aLine * 0.55;
		p.y += sin(p.x * 0.9 + uTime * 0.18 + phase) * 0.06 + sin(p.x * 2.3 - uTime * 0.11 + phase * 1.7) * 0.025;
		vLine = aLine / uLines;
		vT = aT;
		gl_Position = projectionMatrix * modelViewMatrix * vec4(p, 1.0);
	}
`;

const LINE_FRAGMENT = /* glsl */ `
	uniform vec3 uGold;
	uniform vec3 uGreen;
	varying float vLine;
	varying float vT;
	void main() {
		float edge = smoothstep(0.0, 0.14, vT) * smoothstep(1.0, 0.86, vT);
		vec3 col = mix(uGold, uGreen, vLine);
		float alpha = edge * mix(0.85, 0.22, vLine);
		gl_FragColor = vec4(col, alpha);
	}
`;

const POINT_VERTEX = /* glsl */ `
	uniform float uTime;
	uniform float uPixelRatio;
	attribute float aSeed;
	varying float vAlpha;
	void main() {
		vec3 p = position;
		p.x += sin(uTime * 0.05 + aSeed * 6.28) * 0.12;
		p.y += sin(uTime * 0.08 + aSeed * 12.0) * 0.05;
		vec4 mv = modelViewMatrix * vec4(p, 1.0);
		gl_PointSize = (2.0 + aSeed * 2.5) * uPixelRatio;
		vAlpha = 0.25 + 0.35 * sin(uTime * 0.4 + aSeed * 20.0) * 0.5 + 0.2;
		gl_Position = projectionMatrix * mv;
	}
`;

const POINT_FRAGMENT = /* glsl */ `
	uniform vec3 uColor;
	varying float vAlpha;
	void main() {
		vec2 c = gl_PointCoord - 0.5;
		float d = length(c);
		if (d > 0.5) discard;
		gl_FragColor = vec4(uColor, (1.0 - d * 2.0) * vAlpha);
	}
`;

// Noise deterministik sederhana untuk profil bukit.
function ridge(x, seed) {
	return (
		Math.sin(x * 0.55 + seed * 1.3) * 0.42 +
		Math.sin(x * 1.25 + seed * 2.1) * 0.2 +
		Math.sin(x * 2.7 + seed * 0.7) * 0.07
	);
}

export function mount(container, options = {}) {
	const maxDpr = options.maxPixelRatio || 1.5;
	const renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
	renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, maxDpr));
	renderer.setClearColor(0x000000, 0);
	const canvas = renderer.domElement;
	canvas.setAttribute('aria-hidden', 'true');
	canvas.setAttribute('tabindex', '-1');
	container.appendChild(canvas);

	const scene = new Scene();
	const camera = new PerspectiveCamera(38, 1, 0.1, 50);
	camera.position.set(0, 0.55, 6.2);
	camera.lookAt(0, -0.35, 0);

	// Kontur: N garis x M titik, digabung dalam satu LineSegments (1 draw call).
	const lines = 16;
	const pointsPerLine = 140;
	const width = 12;
	const segCount = lines * (pointsPerLine - 1);
	const positions = new Float32Array(segCount * 2 * 3);
	const lineAttr = new Float32Array(segCount * 2);
	const tAttr = new Float32Array(segCount * 2);
	let v = 0;
	for (let l = 0; l < lines; l++) {
		const z = -l * 0.42;
		const baseY = -1.35 + l * 0.085;
		const amp = 0.55 + l * 0.05;
		const pts = [];
		for (let i = 0; i < pointsPerLine; i++) {
			const t = i / (pointsPerLine - 1);
			const x = -width / 2 + t * width;
			pts.push([x, baseY + ridge(x, l * 0.37) * amp, z, t]);
		}
		for (let i = 0; i < pointsPerLine - 1; i++) {
			for (const p of [pts[i], pts[i + 1]]) {
				positions[v * 3] = p[0];
				positions[v * 3 + 1] = p[1];
				positions[v * 3 + 2] = p[2];
				lineAttr[v] = l;
				tAttr[v] = p[3];
				v++;
			}
		}
	}
	const lineGeometry = new BufferGeometry();
	lineGeometry.setAttribute('position', new BufferAttribute(positions, 3));
	lineGeometry.setAttribute('aLine', new BufferAttribute(lineAttr, 1));
	lineGeometry.setAttribute('aT', new BufferAttribute(tAttr, 1));
	const lineMaterial = new ShaderMaterial({
		vertexShader: LINE_VERTEX,
		fragmentShader: LINE_FRAGMENT,
		transparent: true,
		depthWrite: false,
		uniforms: {
			uTime: { value: 0 },
			uLines: { value: lines - 1 },
			uGold: { value: new Color('#D7AF67') },
			uGreen: { value: new Color('#6FA58C') },
		},
	});
	const contour = new LineSegments(lineGeometry, lineMaterial);
	scene.add(contour);

	// Kabut halus (1 draw call).
	const mistCount = options.mist === false ? 0 : 220;
	let mist = null;
	let mistGeometry = null;
	let mistMaterial = null;
	if (mistCount > 0) {
		const mp = new Float32Array(mistCount * 3);
		const seeds = new Float32Array(mistCount);
		for (let i = 0; i < mistCount; i++) {
			const s = (Math.sin(i * 12.9898) * 43758.5453) % 1;
			const r = Math.abs(s);
			mp[i * 3] = (r - 0.5) * width;
			mp[i * 3 + 1] = -0.9 + Math.abs((Math.sin(i * 78.233) * 12345.678) % 1) * 1.9;
			mp[i * 3 + 2] = -Math.abs((Math.sin(i * 3.14) * 999.1) % 1) * 6;
			seeds[i] = Math.abs((Math.sin(i * 4.1) * 77.7) % 1);
		}
		mistGeometry = new BufferGeometry();
		mistGeometry.setAttribute('position', new BufferAttribute(mp, 3));
		mistGeometry.setAttribute('aSeed', new BufferAttribute(seeds, 1));
		mistMaterial = new ShaderMaterial({
			vertexShader: POINT_VERTEX,
			fragmentShader: POINT_FRAGMENT,
			transparent: true,
			depthWrite: false,
			blending: AdditiveBlending,
			uniforms: {
				uTime: { value: 0 },
				uPixelRatio: { value: renderer.getPixelRatio() },
				uColor: { value: new Color('#F5E3BD') },
			},
		});
		mist = new Points(mistGeometry, mistMaterial);
		scene.add(mist);
	}

	// Parallax pointer sangat kecil (desktop saja); tidak menggeser DOM.
	const target = { x: 0, y: 0 };
	const current = { x: 0, y: 0 };
	const finePointer = window.matchMedia('(pointer: fine)').matches;
	const onPointer = (e) => {
		const rect = container.getBoundingClientRect();
		target.x = ((e.clientX - rect.left) / rect.width - 0.5) * 0.18;
		target.y = ((e.clientY - rect.top) / rect.height - 0.5) * 0.08;
	};
	if (finePointer) window.addEventListener('pointermove', onPointer, { passive: true });

	const resize = () => {
		const w = Math.max(1, container.clientWidth);
		const h = Math.max(1, container.clientHeight);
		renderer.setSize(w, h, false);
		camera.aspect = w / h;
		camera.fov = w < 700 ? 48 : 38;
		camera.updateProjectionMatrix();
	};
	const ro = new ResizeObserver(resize);
	ro.observe(container);
	resize();

	let running = false;
	let inView = true;
	let raf = 0;
	let last = performance.now();
	let elapsed = 0;

	const frame = (now) => {
		if (!running) return;
		const dt = Math.min(0.05, (now - last) / 1000);
		last = now;
		elapsed += dt;
		lineMaterial.uniforms.uTime.value = elapsed;
		if (mistMaterial) mistMaterial.uniforms.uTime.value = elapsed;
		current.x += (target.x - current.x) * 0.04;
		current.y += (target.y - current.y) * 0.04;
		camera.position.x = current.x;
		camera.position.y = 0.55 - current.y;
		camera.lookAt(0, -0.35, 0);
		renderer.render(scene, camera);
		raf = requestAnimationFrame(frame);
	};

	const start = () => {
		if (running || document.hidden || !inView) return;
		running = true;
		last = performance.now();
		raf = requestAnimationFrame(frame);
	};
	const stop = () => {
		running = false;
		cancelAnimationFrame(raf);
	};

	const onVisibility = () => (document.hidden ? stop() : start());
	document.addEventListener('visibilitychange', onVisibility);

	const io = new IntersectionObserver((entries) => {
		inView = entries[0].isIntersecting;
		inView ? start() : stop();
	});
	io.observe(container);

	let destroyed = false;
	const destroy = () => {
		if (destroyed) return;
		destroyed = true;
		stop();
		io.disconnect();
		ro.disconnect();
		document.removeEventListener('visibilitychange', onVisibility);
		if (finePointer) window.removeEventListener('pointermove', onPointer);
		canvas.removeEventListener('webglcontextlost', onContextLost);
		lineGeometry.dispose();
		lineMaterial.dispose();
		if (mistGeometry) mistGeometry.dispose();
		if (mistMaterial) mistMaterial.dispose();
		renderer.dispose();
		if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
	};

	// Context loss: hentikan dan kembalikan ke ilustrasi statis.
	const onContextLost = (e) => {
		e.preventDefault();
		container.classList.remove('is-ready');
		destroy();
		if (typeof options.onFail === 'function') options.onFail('context_lost');
	};
	canvas.addEventListener('webglcontextlost', onContextLost, false);

	renderer.render(scene, camera);
	start();
	return { destroy };
}
