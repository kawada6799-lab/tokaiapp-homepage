# 東海App コーポレートサイト（tokaiapp.com）

ビルド不要の静的サイト。**`pages/` と `parts/` の中のHTMLを編集すれば、
プレビューも本番も同時に変わる。**

```
tokaiapp-homepage/
├── pages/                ★ 各ページの中身（10ファイル）
│   ├── home.html                ホーム
│   ├── service.html             サービス一覧
│   ├── service-douinavi.html    サービス詳細：ドウイナビ
│   ├── service-clinic-app.html  サービス詳細：クリニック専用アプリ
│   ├── service-medical-dx.html  サービス詳細：医療DX導入支援
│   ├── price.html               料金
│   ├── company.html             会社概要
│   ├── contact.html             お問い合わせ
│   ├── news.html                お知らせ 一覧
│   └── privacy.html             プライバシーポリシー（下書き）
├── parts/                ★ 全ページ共通の部品
│   ├── header.html              ヘッダー
│   ├── cta.html                 CTA帯
│   └── footer.html              フッター＋スマホの追従CTA
├── assets/
│   ├── img/logo.png      ロゴ（透過PNG。濃い背景では自動で白抜きになる）
│   ├── css/tokens.css    ★ 色・余白・文字サイズ。見た目の調整はまずここ
│   ├── css/style.css        レイアウトと部品
│   └── js/main.js           ヘッダー・メニュー・スクロール表示（ライブラリなし）
├── wordpress/tokaiapp/   WordPressテーマ（pages/ と parts/ を読む薄い殻）
├── build.mjs             プレビューを組み立てる
├── pack-theme.mjs        アップロード用のテーマzipを作る
├── pages.json            ページの一覧（URL・タイトル・説明文）
└── docs/
    ├── 文言の直し方.md        ★ 「ここを1行変えたい」ときはこれ
    ├── 画像の入れ方.md        ★ 写真・フリー素材の扱い
    ├── お問い合わせフォームの設置.md ★ フォームを動かす＋迷惑送信対策
    ├── 要確認リスト.md        ★ 公開前に埋める項目
    ├── デザイン方針.md         配色と組み方の決めごと
    ├── WordPress反映手順.md    本番への反映手順
    └── セキュリティ_国外アクセス制限.md
```

## 確認する

```bash
node build.mjs
```

`preview/` に10ページ書き出されるので、`preview/index.html` をブラウザで開く。
サーバーは要りません。

## WordPressに反映する

```bash
node pack-theme.mjs
```

`build/tokaiapp.zip` ができる。WordPress の
**外観 → テーマ → 新規追加 → テーマのアップロード** に入れて有効化する。
詳しい手順とバックアップの取り方は `docs/WordPress反映手順.md`。

## 公開前に

`docs/要確認リスト.md` の項目を埋めること。
各ページの中の **`★要確認`** コメントが、埋めるべき箇所と対応しています。

```bash
grep -rn "★要確認" pages/ parts/
```

## リンクの書き方

ページ間のリンクは**本番のURL**（`/price/`、`/service/douinavi/`）で書きます。
`build.mjs` がプレビュー用に相対ファイル名へ置き換えます。
まだ無いページへのリンクは、プレビューでは自動で無効化されます。
