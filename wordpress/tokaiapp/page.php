<?php
/**
 * page.php — 固定ページ。
 *
 * pages/<スラッグ>.html があればそれを出す。
 * 無ければ、WordPress の編集画面で書いた本文を出す。
 * （料金やお知らせを WordPress 側で運用したくなったときは、
 *   pages/ から該当ファイルを消せば、自動でそちらに切り替わる）
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
    while (have_posts()) {
        the_post();
        ?>
        <section class="page-head">
          <div class="wrap">
            <h1 class="page-head__title"><?php the_title(); ?></h1>
          </div>
        </section>
        <section class="section">
          <div class="wrap wrap--narrow entry"><?php the_content(); ?></div>
        </section>
        <?php
    }
}
?>
</main>
<?php
get_footer();
