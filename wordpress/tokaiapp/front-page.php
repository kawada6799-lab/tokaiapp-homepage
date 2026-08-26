<?php
/**
 * front-page.php — トップページ。中身は pages/home.html。
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main id="main">
<?php
$body = tokaiapp_page_body();
if ($body !== '') {
    echo $body; // phpcs:ignore WordPress.Security.EscapeOutput
} else {
    echo tokaiapp_notice('pages/home.html が読み込めませんでした。'); // phpcs:ignore WordPress.Security.EscapeOutput
}
?>
</main>
<?php
get_footer();
