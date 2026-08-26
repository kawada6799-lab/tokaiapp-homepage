<?php
/**
 * 404.php — ページが見つからないとき。
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main id="main">
  <section class="page-head">
    <div class="wrap">
      <p class="page-head__en">404</p>
      <h1 class="page-head__title">ページが見つかりませんでした</h1>
      <p class="page-head__lead">
        アドレスが変わったか、削除された可能性があります。
      </p>
    </div>
  </section>
  <section class="section">
    <div class="wrap wrap--narrow">
      <a class="btn btn--outline" href="<?php echo esc_url(home_url('/')); ?>">ホームへ戻る</a>
    </div>
  </section>
</main>
<?php
get_footer();
