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
    wp_enqueue_style('tokaiapp-base',   $uri . '/assets/css/style.css',  array('tokaiapp-tokens'), $ver('assets/css/style.css'));
    wp_enqueue_style('tokaiapp-theme',  $uri . '/style.css',             array('tokaiapp-base'),   $ver('style.css'));

    wp_enqueue_script('tokaiapp-main', $uri . '/assets/js/main.js', array(), $ver('assets/js/main.js'), true);
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
    return $html === false ? '' : $html;
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
