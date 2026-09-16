<?php
/**
 * functions.php — テーマの下ごしらえ
 *
 * やっていることは3つ。
 *   1. テーマとして最低限の宣言
 *   2. CSS と JavaScript の読み込み
 *   3. parts/ と pages/ のHTMLを読み出す関数を用意する
 *
 * ページの中身はこのテーマには入っていない。
 * テーマフォルダの中の pages/ ・ parts/ から読む。
 * （pack-theme.mjs が、その2つをテーマフォルダの中にコピーしてくれる）
 */

if (!defined('ABSPATH')) { exit; }

define('TOKAIAPP_VERSION', '1.0.0');

/* 0. 国外からのアクセス制限 ---------------------------------------------
   詳しくは inc/geo-guard.php の冒頭と
   docs/セキュリティ_国外アクセス制限.md を参照。
   国コードのヘッダが届かない環境では何もしません（締め出し防止）。 */
require_once get_template_directory() . '/inc/geo-guard.php';

/* 0-2. 管理画面の設定値（GA4・OGP画像・フォーム）と、<head> に出すSEO情報 ----
   詳しくは inc/settings.php ・ inc/seo.php の冒頭と
   docs/WEB集客_受け皿の設定.md を参照。 */
require_once get_template_directory() . '/inc/settings.php';
require_once get_template_directory() . '/inc/seo.php';

/* 1. テーマの宣言 ------------------------------------------------------- */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('style', 'script', 'search-form'));
    add_theme_support('responsive-embeds');
});

/* 2. CSS / JavaScript の読み込み ---------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    $dir = get_template_directory();
    $uri = get_template_directory_uri();

    // ファイルの更新時刻をバージョンにする。
    // → 編集してアップロードし直せば、ブラウザのキャッシュが自動で切り替わる
    $ver = function ($rel) use ($dir) {
        $path = $dir . '/' . $rel;
        return file_exists($path) ? (string) filemtime($path) : TOKAIAPP_VERSION;
    };

    wp_enqueue_style(
        'tokaiapp-fonts',
        'https://fonts.googleapis.com/css2?family=Barlow:wght@500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap',
        array(),
        null
    );
    wp_enqueue_style('tokaiapp-tokens', $uri . '/assets/css/tokens.css', array(), $ver('assets/css/tokens.css'));
    wp_enqueue_style('tokaiapp-photos', $uri . '/assets/css/photos.css', array('tokaiapp-tokens'), $ver('assets/css/photos.css'));
    wp_enqueue_style('tokaiapp-base',   $uri . '/assets/css/style.css',  array('tokaiapp-photos'), $ver('assets/css/style.css'));
    wp_enqueue_style('tokaiapp-theme',  $uri . '/style.css',             array('tokaiapp-base'),   $ver('style.css'));

    wp_enqueue_script('tokaiapp-main', $uri . '/assets/js/main.js', array(), $ver('assets/js/main.js'), true);
});

/* 4. 管理画面から pages/ と parts/ を編集できるようにする ----------------
   WordPress の「テーマファイルエディター」は、既定では php と css しか
   一覧に出さない。このサイトは中身が .html なので、html も編集できるようにする。

   ★注意★ ここで直した内容は、次に zip を上書きアップロードすると消えます。
   直したら、リポジトリ側（pages/ ・ parts/）にも同じ変更を入れてください。
   詳しくは docs/文言の直し方.md */
add_filter('wp_theme_editor_filetypes', function ($types) {
    if (!in_array('html', $types, true)) { $types[] = 'html'; }
    return $types;
});

/**
 * テーマフォルダの中のHTMLを、そのまま読んで返す。
 *
 * @param string $rel テーマフォルダからの相対パス（例 'parts/header.html'）
 * @return string 読めなければ空文字
 */
function tokaiapp_html($rel) {
    // 想定外のパスを読まないよう、使ってよい文字を絞る
    if (!preg_match('#^(parts|pages)/[a-z0-9\-]+\.html$#', $rel)) {
        return '';
    }
    $path = get_template_directory() . '/' . $rel;
    if (!is_readable($path)) { return ''; }
    $html = file_get_contents($path);
    if ($html === false) { return ''; }

    // 画像などの参照は {{ASSETS}}/img/xxx.png と書いてある。
    // 下層ページ（/service/douinavi/ など）で相対パスがずれないよう、
    // ここでテーマのURLに置き換える。
    $html = str_replace('{{ASSETS}}', get_template_directory_uri() . '/assets', $html);

    // お問い合わせフォーム: <!-- {{FORM:START}} --> 〜 <!-- {{FORM:END}} --> で
    // 囲んだ「メールでのご案内」を、Contact Form 7 のフォームに差し替える
    return tokaiapp_replace_contact_form($html);
}

/**
 * お問い合わせフォームの差し替え。
 *
 * pages/contact.html には、送信できるフォームが無いときのための
 * 「メールでのご案内」が <!-- {{FORM:START}} --> 〜 <!-- {{FORM:END}} --> で
 * 囲んで置いてある。次の2つが揃ったときだけ、その部分をフォームに置き換える。
 *
 *   1. Contact Form 7 が有効になっている
 *   2. 外観 → カスタマイズ → 東海App 設定 に、ショートコードが入っている
 *
 * どちらか欠けていれば、メールでのご案内のまま（何も壊れない）。
 * 手順は docs/お問い合わせフォームの設置.md
 */
function tokaiapp_replace_contact_form($html) {
    $start = '<!-- {{FORM:START}} -->';
    $end   = '<!-- {{FORM:END}} -->';
    $from  = strpos($html, $start);
    $to    = strpos($html, $end);
    if ($from === false || $to === false || $to < $from) { return $html; }

    $shortcode = tokaiapp_setting('contact_form');
    if ($shortcode === '' || !shortcode_exists('contact-form-7')) { return $html; }

    $form = do_shortcode($shortcode);
    return substr($html, 0, $from) . $form . substr($html, $to + strlen($end));
}

/**
 * いま表示しているページに対応する pages/*.html の名前を決める。
 *
 * トップページ            → home
 * /price/                 → price
 * /service/douinavi/      → service-douinavi   （階層は - でつなぐ）
 *
 * @return string 見つからなければ空文字
 */
function tokaiapp_page_slug() {
    if (is_front_page()) { return 'home'; }

    if (is_page()) {
        // get_page_uri は 'service/douinavi' のような形で返る
        $uri = get_page_uri(get_queried_object_id());
        if (is_string($uri) && $uri !== '') {
            return str_replace('/', '-', trim($uri, '/'));
        }
    }
    return '';
}

/**
 * ページの中身を返す。
 * 対応する pages/*.html があればそれを、無ければ WordPress の本文を使う。
 *
 * これにより「まずHTMLで作って、あとから WordPress の編集画面に移す」
 * どちらの運用にも耐える。
 *
 * @return string
 */
function tokaiapp_page_body() {
    $slug = tokaiapp_page_slug();
    if ($slug !== '') {
        $html = tokaiapp_html('pages/' . $slug . '.html');
        if ($html !== '') { return $html; }
    }
    return '';
}

/**
 * ページの中身が読めなかったときに、理由を画面に出す。
 * 白いページになって原因が分からない、という状態を避けるため。
 */
function tokaiapp_notice($message) {
    return '<div style="max-width:640px;margin:160px auto;padding:32px;'
         . 'border:1px solid #e1e8f0;border-radius:12px;'
         . 'font-family:sans-serif;line-height:1.9;color:#1f2933;">'
         . '<p style="font-weight:700;margin:0 0 12px;">ページを表示できませんでした</p>'
         . '<p style="margin:0;color:#5a6572;">' . esc_html($message) . '</p>'
         . '</div>';
}
