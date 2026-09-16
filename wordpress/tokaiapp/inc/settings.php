<?php
/**
 * settings.php — 管理画面（外観 → カスタマイズ → 東海App 設定）で入れる値
 *
 * zip を上書きしても消えない値は、テーマのファイルではなく
 * WordPress のデータベース（テーマの設定）に持たせる。ここではその3つ。
 *
 *   1. GA4 の測定ID            （G-XXXXXXXXXX）
 *   2. OGP 画像                （1200×630px。メディアにアップロードして選ぶ）
 *   3. お問い合わせフォームのショートコード
 *                               （[contact-form-7 id="…" title="…"]）
 *
 * 値の取り出しは tokaiapp_setting('ga4_id') のように呼ぶ。
 * 空なら、その機能は何も出さない（計測タグを出さない、画像なし、メール案内のまま）。
 *
 * 手順は docs/WEB集客_受け皿の設定.md
 */

if (!defined('ABSPATH')) { exit; }

/**
 * 設定値を返す。未設定なら空文字。
 *
 * @param string $key 'ga4_id' | 'ogp_image' | 'contact_form'
 * @return string
 */
function tokaiapp_setting($key) {
    $value = get_theme_mod('tokaiapp_' . $key, '');
    return is_string($value) ? trim($value) : '';
}

/** GA4 の測定IDは G- で始まる英数字だけを通す。それ以外は捨てる */
function tokaiapp_sanitize_ga4_id($value) {
    $value = strtoupper(trim((string) $value));
    return preg_match('/^G-[A-Z0-9]{4,20}$/', $value) ? $value : '';
}

/** ショートコードは [contact-form-7 …] の形だけを通す（他のコードを流し込めないように） */
function tokaiapp_sanitize_contact_form($value) {
    $value = trim((string) $value);
    return preg_match('/^\[contact-form-7\s[^\[\]]*\]$/', $value) ? $value : '';
}

add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('tokaiapp_settings', array(
        'title'       => '東海App 設定',
        'priority'    => 30,
        'description' => '計測・SNS・お問い合わせフォームの設定。テーマの zip を上書きしても消えません。',
    ));

    // 1. GA4
    $wp_customize->add_setting('tokaiapp_ga4_id', array(
        'default'           => '',
        'type'              => 'theme_mod',
        'sanitize_callback' => 'tokaiapp_sanitize_ga4_id',
    ));
    $wp_customize->add_control('tokaiapp_ga4_id', array(
        'section'     => 'tokaiapp_settings',
        'label'       => 'Google アナリティクス（GA4）の測定ID',
        'description' => '「G-」で始まるID。空欄なら計測タグを出しません。ログイン中の閲覧は計測しません。',
        'type'        => 'text',
        'input_attrs' => array('placeholder' => 'G-XXXXXXXXXX'),
    ));

    // 2. OGP 画像
    $wp_customize->add_setting('tokaiapp_ogp_image', array(
        'default'           => '',
        'type'              => 'theme_mod',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'tokaiapp_ogp_image', array(
        'section'     => 'tokaiapp_settings',
        'label'       => 'OGP画像（SNS・メールにURLを貼ったときの画像）',
        'description' => '1200×630px の PNG か JPG。全ページ共通で使います。',
    )));

    // 3. お問い合わせフォーム
    $wp_customize->add_setting('tokaiapp_contact_form', array(
        'default'           => '',
        'type'              => 'theme_mod',
        'sanitize_callback' => 'tokaiapp_sanitize_contact_form',
    ));
    $wp_customize->add_control('tokaiapp_contact_form', array(
        'section'     => 'tokaiapp_settings',
        'label'       => 'お問い合わせフォームのショートコード',
        'description' => 'Contact Form 7 で作ったフォームのショートコードをそのまま貼ります。空欄ならメールでのご案内を表示します。',
        'type'        => 'text',
        'input_attrs' => array('placeholder' => '[contact-form-7 id="123" title="お問い合わせ"]'),
    ));
});
