#!/usr/bin/env node
/**
 * pack-theme.mjs — WordPress にアップロードするテーマを組み立てる
 *
 *   node pack-theme.mjs
 *
 * やること:
 *   1. build/tokaiapp/ を作り直す
 *   2. wordpress/tokaiapp/ の PHP と style.css をコピー
 *   3. parts/ ・ pages/ ・ assets/ を同じ場所にコピー
 *      （テーマはこの3つを読んでページを組み立てる）
 *   4. zip があれば build/tokaiapp.zip も作る
 *
 * できた zip を WordPress の「外観 → テーマ → 新規追加 → テーマのアップロード」
 * に入れれば反映される。手順は docs/WordPress反映手順.md
 */

import { cp, mkdir, rm, readdir, stat } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const run = promisify(execFile);
const root = path.dirname(fileURLToPath(import.meta.url));

const THEME = 'tokaiapp';
const SOURCES = {
  theme:  path.join(root, 'wordpress', THEME),
  parts:  path.join(root, 'parts'),
  pages:  path.join(root, 'pages'),
  assets: path.join(root, 'assets'),
};
const outDir = path.join(root, 'build');
const themeOut = path.join(outDir, THEME);

async function totalSize(dir) {
  let bytes = 0;
  for (const entry of await readdir(dir, { withFileTypes: true, recursive: true })) {
    if (!entry.isFile()) continue;
    bytes += (await stat(path.join(entry.parentPath ?? entry.path, entry.name))).size;
  }
  return (bytes / 1024).toFixed(1) + ' KB';
}

for (const [label, p] of Object.entries(SOURCES)) {
  if (!existsSync(p)) {
    console.error(`見つかりません（${label}）: ${p}`);
    process.exit(1);
  }
}

await rm(outDir, { recursive: true, force: true });
await mkdir(themeOut, { recursive: true });

await cp(SOURCES.theme, themeOut, { recursive: true });
await cp(SOURCES.parts,  path.join(themeOut, 'parts'),  { recursive: true });
await cp(SOURCES.pages,  path.join(themeOut, 'pages'),  { recursive: true });
await cp(SOURCES.assets, path.join(themeOut, 'assets'), { recursive: true });

console.log(`テーマを組み立てました: build/${THEME}/  (${await totalSize(themeOut)})`);

try {
  await run('zip', ['-rq', `${THEME}.zip`, THEME], { cwd: outDir });
  console.log(`zip を作りました:       build/${THEME}.zip`);
  console.log('');
  console.log('WordPress の 外観 → テーマ → 新規追加 → テーマのアップロード に入れてください。');
} catch {
  console.log('');
  console.log('zip コマンドが無いため、フォルダのみ作成しました。');
  console.log(`build/${THEME}/ を手で zip にするか、FTP でそのまま`);
  console.log('wp-content/themes/ に置いてください。');
}
