const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const postcss = require('postcss');
const cssnano = require('cssnano');

const root = path.join(__dirname, '..');
const assetsPath = (...parts) => path.join(root, 'assets', ...parts);

function buildJS() {
  const input = assetsPath('js', 'admin.js');
  const output = assetsPath('js', 'admin.min.js');
  execSync(`npx uglifyjs "${input}" -c -m -o "${output}"`);
  console.log('Minified JS to', output);
}

async function buildCSS() {
  const cssFiles = ['admin-style.css', 'admin-settings.css'].map(f => assetsPath('css', f));
  const combined = cssFiles.map(f => fs.readFileSync(f, 'utf8')).join('\n');
  const result = await postcss([cssnano]).process(combined, { from: undefined });
  fs.writeFileSync(assetsPath('css', 'admin.min.css'), result.css);
  console.log('Minified CSS to', assetsPath('css', 'admin.min.css'));
}

(async () => {
  buildJS();
  await buildCSS();
})();
