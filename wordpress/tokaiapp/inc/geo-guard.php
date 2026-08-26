<?php
/**
 * geo-guard.php — 国外からのアクセスを制限する
 *
 * ------------------------------------------------------------------
 * 前提: これは「いちばん外側の守り」ではありません
 * ------------------------------------------------------------------
 * ここで弾く時点で、WordPress はすでに起動しています。
 * つまり攻撃の負荷そのものは減りません。テーマを切り替えると外れます。
 *
 * 本命は、サーバー側かCDN側で止めることです（docs/セキュリティ_国外アクセス制限.md）。
 *   1. ConoHa WING の「海外アクセス制限」            ← まずこれ
 *   2. Cloudflare の WAF ルール
 *   3. このファイル                                  ← 取りこぼしの受け皿
 *
 * ------------------------------------------------------------------
 * 既定で何を止めるか
 * ------------------------------------------------------------------
 * 止める : ログイン画面 / 管理画面 / XML-RPC / REST APIの書き込み /
 *          コメント投稿 / お問い合わせフォームの送信
 * 止めない: 公開ページ
 *
 * 公開ページを止めないのは、Googleの検索ロボットが主に米国のIPから
 * アクセスするためです。サイト全体を国外遮断すると、
 * **検索結果に出なくなります**。
 * それでも全体を止めたい場合は、wp-config.php に次を書きます。
 *
 *     define('TOKAIAPP_GEO_BLOCK_ALL', true);
 *
 * ------------------------------------------------------------------
 * 弱点: ヘッダは偽装できます
 * ------------------------------------------------------------------
 * 国の判定は、CDNやサーバーが付けるヘッダを読んでいるだけです。
 * サーバーのIPに直接アクセスできる状態だと、攻撃者は
 * 「CF-IPCountry: JP」を自分で付けるだけで通り抜けられます。
 *
 * **このファイル単独では守りになりません。**
 * 必ず、ConoHa WING の「海外アクセス制限」か、
 * Cloudflare（＋Cloudflare以外からの接続拒否）と組み合わせてください。
 *
 * ------------------------------------------------------------------
 * 判定できないときは通します（フェイルオープン）
 * ------------------------------------------------------------------
 * 国が判定できるのは、CDNやサーバーが国コードのヘッダを付けている場合だけです。
 * 付いていない環境では、このファイルは何もしません。
 * 「判定できないから全部止める」にすると、設定変更のたびに
 * 自分が締め出される事故が起きるためです。
 */

if (!defined('ABSPATH')) { exit; }

/**
 * 訪問者の国コード（2文字）を返す。判定できなければ空文字。
 *
 * 対応しているヘッダは、CDN・WAF・サーバーが付けるもの。
 * 使っている環境に合わせて増やしてよい。
 */
function tokaiapp_visitor_country() {
    static $cache = null;
    if ($cache !== null) { return $cache; }

    $headers = array(
        'HTTP_CF_IPCOUNTRY',              // Cloudflare
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY', // Amazon CloudFront
        'HTTP_X_APPENGINE_COUNTRY',       // Google Cloud
        'HTTP_GEOIP_COUNTRY_CODE',        // Apache / nginx の GeoIP モジュール
        'HTTP_X_COUNTRY_CODE',            // 一部のWAF
    );

    foreach ($headers as $key) {
        if (empty($_SERVER[$key])) { continue; }
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $_SERVER[$key]));
        if (strlen($code) === 2) { return $cache = $code; }
    }
    return $cache = '';
}

/**
 * このアクセスを「国外から」と見なすか。
 *
 * 判定できないとき・日本のときは false。
 * 自分のIPを wp-config.php で許可しておけば、いつでも false。
 *
 *     define('TOKAIAPP_ALLOW_IPS', '203.0.113.10, 198.51.100.20');
 */
function tokaiapp_is_foreign_access() {
    $country = tokaiapp_visitor_country();

    // 判定できない → 通す
    if ($country === '') { return false; }

    // 日本 → 通す。T1 は Tor、XX は判定不能を表すことがある
    if ($country === 'JP') { return false; }

    // 明示的に許可したIP → 通す
    if (defined('TOKAIAPP_ALLOW_IPS') && !empty($_SERVER['REMOTE_ADDR'])) {
        $allowed = array_filter(array_map('trim', explode(',', TOKAIAPP_ALLOW_IPS)));
        if (in_array($_SERVER['REMOTE_ADDR'], $allowed, true)) { return false; }
    }

    // サーバー自身からの呼び出し（wp-cron など）は止めない
    if (defined('DOING_CRON') && DOING_CRON) { return false; }
    if (defined('WP_CLI') && WP_CLI) { return false; }

    return true;
}

/**
 * 403 を返して終了する。
 * 検索ロボットに「このページは無い」と誤解させないよう、
 * noindex は付けず 403 のまま返す。
 */
function tokaiapp_deny($reason = '') {
    if (!headers_sent()) {
        status_header(403);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
       . '<title>403 Forbidden</title></head><body style="font-family:sans-serif;'
       . 'max-width:36rem;margin:15vh auto;padding:0 1.5rem;line-height:1.9;color:#1f2933;">'
       . '<h1 style="font-size:1.15rem;">このページは日本国内からのみご利用いただけます</h1>'
       . '<p style="color:#5a6572;">This page is available from Japan only.</p>'
       . '<p style="color:#8593a2;font-size:.85rem;">'
       . 'お困りの場合は <a href="mailto:info@tokaiapp.com" style="color:#076aa3;">'
       . 'info@tokaiapp.com</a> までご連絡ください。</p>'
       . '</body></html>';
    exit;
}

/* ======================================================================
   ここから、止める場所ごとの設定
   ====================================================================== */

// --- ログイン画面 -------------------------------------------------------
add_action('login_init', function () {
    if (tokaiapp_is_foreign_access()) { tokaiapp_deny('login'); }
}, 0);

// --- 管理画面（front-end からのAJAXは除く） -----------------------------
add_action('admin_init', function () {
    if (wp_doing_ajax()) { return; }
    if (tokaiapp_is_foreign_access()) { tokaiapp_deny('admin'); }
}, 0);

// --- XML-RPC ------------------------------------------------------------
// 総当たり攻撃の入口になりやすい。国外からは常に閉じる
add_filter('xmlrpc_enabled', function ($enabled) {
    return tokaiapp_is_foreign_access() ? false : $enabled;
});
add_action('init', function () {
    if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST && tokaiapp_is_foreign_access()) {
        tokaiapp_deny('xmlrpc');
    }
}, 0);

// --- REST API の書き込み -------------------------------------------------
// 読み取り（GET）は、ブロックエディタや外部連携で使うことがあるため通す
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($result)) { return $result; }
    if (!tokaiapp_is_foreign_access()) { return $result; }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    if (in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {
        return new WP_Error(
            'tokaiapp_geo_blocked',
            '日本国内からのみご利用いただけます。',
            array('status' => 403)
        );
    }
    return $result;
});

// --- コメント投稿 --------------------------------------------------------
add_filter('preprocess_comment', function ($commentdata) {
    if (tokaiapp_is_foreign_access()) {
        wp_die('日本国内からのみご利用いただけます。', '403 Forbidden', array('response' => 403));
    }
    return $commentdata;
}, 0);

// --- お問い合わせフォーム（Contact Form 7）------------------------------
// 送信そのものは通し、迷惑メール扱いにして通知だけ止める。
// 「送信できたように見えて届かない」のを避けたい場合は、
// この2つの add_filter をコメントアウトしてください。
add_filter('wpcf7_spam', function ($spam) {
    return tokaiapp_is_foreign_access() ? true : $spam;
}, 10, 1);

// --- サイト全体（既定では無効。SEOへの影響を理解したうえで有効化する）----
if (defined('TOKAIAPP_GEO_BLOCK_ALL') && TOKAIAPP_GEO_BLOCK_ALL) {
    add_action('template_redirect', function () {
        if (tokaiapp_is_foreign_access()) { tokaiapp_deny('site'); }
    }, 0);
}

/**
 * 管理画面に、いまの判定状況を出す。
 * 「設定したのに効いているか分からない」を防ぐため。
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) { return; }
    $country = tokaiapp_visitor_country();

    if ($country === '') {
        echo '<div class="notice notice-warning"><p>'
           . '<strong>国外アクセス制限：はたらいていません。</strong> '
           . 'サーバーから国コードのヘッダが届いていないため、国を判定できません。'
           . 'レンタルサーバーの「国外IPアクセス制限」機能、または Cloudflare の設定をご確認ください。'
           . '（テーマの docs/セキュリティ_国外アクセス制限.md）'
           . '</p></div>';
        return;
    }

    $all = defined('TOKAIAPP_GEO_BLOCK_ALL') && TOKAIAPP_GEO_BLOCK_ALL;
    echo '<div class="notice notice-success"><p>'
       . '<strong>国外アクセス制限：はたらいています。</strong> '
       . 'いまのアクセス元は <code>' . esc_html($country) . '</code> と判定されています。'
       . '対象は' . ($all ? 'サイト全体' : 'ログイン・管理画面・XML-RPC・APIの書き込み・フォーム送信')
       . 'です。</p></div>';
});
