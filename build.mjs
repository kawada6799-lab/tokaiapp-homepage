#!/usr/bin/env node
/**
 * build.mjs — ブラウザで確認するためのHTMLを preview/ に組み立てる
 *
 *   node build.mjs
 *
 * やること:
 *   parts/header.html + pages/<slug>.html + parts/cta.html + parts/footer.html
 *   を1枚のHTMLにつなげて preview/ に書き出す。
 *
 * 本番（WordPress）ではテーマが同じ部品を読むので、
 * ここで作った preview/ はアップロードしない（.gitignore 済み）。
 *
 * リンクは本番のURL（/price/ など）で書いてある。
 * preview では相対ファイル名（price.html）に置き換える。
 */

import { readFile, writeFile, mkdir, rm, cp } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));
const read = (p) => readFile(path.join(root, p), 'utf8');

const PAGES = JSON.parse(await read('pages.json'));

/** 本番URL → preview のファイル名 */
const urlMap = new Map(PAGES.map((p) => [p.url, p.out]));

/**
 * href="/price/" のような本番URLを、preview 用の相対パスに書き換える。
 * ページ内アンカー（/#service）や tel: はそのまま扱う。
 */
/**
 * 画像などの参照は {{ASSETS}}/img/xxx.png と書いておく。
 * preview では assets/img/xxx.png に、
 * WordPress ではテーマのURLに置き換わる（functions.php の tokaiapp_html）。
 *
 * こう書いておかないと、/service/douinavi/ のような下層ページで
 * 相対パスがずれて画像が出なくなる。
 */
function resolveAssets(html) {
  return html.replaceAll('{{ASSETS}}', 'assets');
}

function localizeLinks(html) {
  return html.replace(/href="(\/[^"]*)"/g, (whole, url) => {
    const [pathPart, hash] = url.split('#');
    const target = urlMap.get(pathPart);
    if (target) return `href="${target}${hash ? '#' + hash : ''}"`;
    // 未作成のページ（プライバシーポリシー等）はリンクを殺しておく
    return `href="#" data-todo="${url}"`;
  });
}

const shell = (page, body) => `<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${page.title}</title>
<meta name="description" content="${page.desc}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/photos.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
${body}
<script src="assets/js/main.js" defer></script>
</body>
</html>
`;

const [header, cta, footer] = await Promise.all([
  read('parts/header.html'),
  read('parts/cta.html'),
  read('parts/footer.html'),
]);

const out = path.join(root, 'preview');
await rm(out, { recursive: true, force: true });
await mkdir(out, { recursive: true });
await cp(path.join(root, 'assets'), path.join(out, 'assets'), { recursive: true });

let made = 0;
const missing = [];

for (const page of PAGES) {
  const src = path.join(root, 'pages', `${page.slug}.html`);
  if (!existsSync(src)) { missing.push(page.slug); continue; }

  const body = await readFile(src, 'utf8');
  const html = resolveAssets(localizeLinks(
    shell(page, `${header}\n<main id="main">\n${body}\n</main>\n${cta}\n${footer}`)
  ));
  await writeFile(path.join(out, page.out), html);
  made++;
}

console.log(`preview/ に ${made} ページ書き出しました`);
if (missing.length) {
  console.log(`まだ pages/ に無いページ: ${missing.join(', ')}`);
}
console.log('preview/index.html をブラウザで開いて確認してください。');
