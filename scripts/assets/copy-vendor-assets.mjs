// Salin aset frontend terpilih dari node_modules ke public/assets/vendor
// dan tulis manifest (versi, lisensi, sha256). Jalankan: npm run copy
import { cpSync, mkdirSync, readFileSync, writeFileSync, rmSync, existsSync, readdirSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const nm = join(here, 'node_modules');
const out = join(here, '..', '..', 'public', 'assets', 'vendor');

const pkg = (name) => JSON.parse(readFileSync(join(nm, name, 'package.json'), 'utf8'));

// [paket npm, folder tujuan, daftar [sumber relatif paket, tujuan relatif folder]]
const plan = [
  ['bootstrap', 'bootstrap5', [['dist/css/bootstrap.min.css', 'css/bootstrap.min.css'], ['dist/js/bootstrap.bundle.min.js', 'js/bootstrap.bundle.min.js'], ['LICENSE', 'LICENSE']]],
  ['bootstrap4', 'bootstrap4', [['dist/js/bootstrap.bundle.min.js', 'js/bootstrap.bundle.min.js'], ['LICENSE', 'LICENSE']]],
  ['jquery', 'jquery', [['dist/jquery.min.js', 'jquery.min.js'], ['LICENSE.txt', 'LICENSE.txt']]],
  ['jquery.easing', 'jquery-easing', [['jquery.easing.min.js', 'jquery.easing.min.js'], ['LICENSE', 'LICENSE']]],
  ['startbootstrap-sb-admin-2', 'sb-admin-2', [['css/sb-admin-2.min.css', 'css/sb-admin-2.min.css'], ['js/sb-admin-2.min.js', 'js/sb-admin-2.min.js'], ['LICENSE', 'LICENSE']]],
  ['@fortawesome/fontawesome-free', 'fontawesome', [['css/all.min.css', 'css/all.min.css'], ['webfonts', 'webfonts'], ['LICENSE.txt', 'LICENSE.txt']]],
  ['chart.js', 'chartjs', [['dist/chart.umd.min.js', 'chart.umd.min.js'], ['LICENSE.md', 'LICENSE.md']]],
  ['datatables.net', 'datatables', [['js/dataTables.min.js', 'js/dataTables.min.js'], ['License.txt', 'License.txt']]],
  ['datatables.net-bs4', 'datatables', [['js/dataTables.bootstrap4.min.js', 'js/dataTables.bootstrap4.min.js'], ['css/dataTables.bootstrap4.min.css', 'css/dataTables.bootstrap4.min.css']]],
  ['select2', 'select2', [['dist/js/select2.min.js', 'js/select2.min.js'], ['dist/js/i18n/id.js', 'js/i18n/id.js'], ['dist/css/select2.min.css', 'css/select2.min.css'], ['LICENSE.md', 'LICENSE.md']]],
  ['@ttskch/select2-bootstrap4-theme', 'select2', [['dist/select2-bootstrap4.min.css', 'css/select2-bootstrap4.min.css']]],
  ['sweetalert2', 'sweetalert2', [['dist/sweetalert2.min.js', 'sweetalert2.min.js'], ['dist/sweetalert2.min.css', 'sweetalert2.min.css'], ['LICENSE', 'LICENSE']]],
  ['swiper', 'swiper', [['swiper-bundle.min.js', 'swiper-bundle.min.js'], ['swiper-bundle.min.css', 'swiper-bundle.min.css'], ['LICENSE', 'LICENSE']]],
  ['leaflet', 'leaflet', [['dist/leaflet.js', 'leaflet.js'], ['dist/leaflet.css', 'leaflet.css'], ['dist/images', 'images'], ['LICENSE', 'LICENSE']]],
  ['feather-icons', 'feather-icons', [['dist/feather-sprite.svg', 'feather-sprite.svg'], ['LICENSE', 'LICENSE']]],
  ['@fontsource/manrope', 'fonts/manrope', [
    ...[400, 500, 600, 700, 800].flatMap((w) => [[`files/manrope-latin-${w}-normal.woff2`, `manrope-latin-${w}.woff2`], [`files/manrope-latin-ext-${w}-normal.woff2`, `manrope-latin-ext-${w}.woff2`]]),
    ['LICENSE', 'LICENSE'],
  ]],
  ['@fontsource/playfair-display', 'fonts/playfair-display', [
    ...[600, 700].flatMap((w) => [[`files/playfair-display-latin-${w}-normal.woff2`, `playfair-display-latin-${w}.woff2`], [`files/playfair-display-latin-ext-${w}-normal.woff2`, `playfair-display-latin-ext-${w}.woff2`]]),
    ['LICENSE', 'LICENSE'],
  ]],
];

const cleaned = new Set();
const manifest = { generated_at: new Date().toISOString(), source: 'npm registry (registry.npmjs.org)', packages: {} };

for (const [name, dest, files] of plan) {
  const info = pkg(name);
  const target = join(out, dest);
  if (!cleaned.has(target)) {
    rmSync(target, { recursive: true, force: true });
    cleaned.add(target);
  }
  for (const [src, dst] of files) {
    const from = join(nm, name, src);
    if (!existsSync(from)) {
      throw new Error(`Tidak ditemukan: ${name}/${src}`);
    }
    const to = join(target, dst);
    mkdirSync(dirname(to), { recursive: true });
    cpSync(from, to, { recursive: true, filter: (p) => !/.(eot|ttf|svg)$/.test(p) || p.endsWith('feather-sprite.svg') || p.includes('leaflet') });
  }
  manifest.packages[name] = { version: info.version, license: info.license || null, folder: `public/assets/vendor/${dest}` };
}

// Checksum seluruh berkas vendor
const hashes = {};
const walk = (dir) => {
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    if (statSync(p).isDirectory()) walk(p);
    else if (!p.endsWith('manifest.json')) hashes[relative(out, p).replaceAll('\\', '/')] = createHash('sha256').update(readFileSync(p)).digest('hex');
  }
};
walk(out);
manifest.sha256 = Object.fromEntries(Object.entries(hashes).sort());
writeFileSync(join(out, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
console.log(`Disalin ${Object.keys(manifest.packages).length} paket, ${Object.keys(hashes).length} berkas → ${out}`);
