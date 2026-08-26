<?php
/**
 * single.php — お知らせの個別記事。
 */
if (!defined('ABSPATH')) { exit; }
get_header();
while (have_posts()) : the_post(); ?>
<main id="main">

  <section class="page-head">
    <div class="wrap">
      <nav class="breadcrumb" aria-label="現在位置">
        <a href="<?php echo esc_url(home_url('/')); ?>">ホーム</a><span>›</span>
        <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts'))); ?>">お知らせ</a><span>›</span>
        <span aria-current="page"><?php the_title(); ?></span>
      </nav>
      <p class="page-head__en"><?php echo esc_html(get_the_date('Y.m.d')); ?></p>
      <h1 class="page-head__title"><?php the_title(); ?></h1>
    </div>
  </section>

  <section class="section">
    <div class="wrap wrap--narrow entry"><?php the_content(); ?></div>
  </section>

</main>
<?php endwhile;
get_footer();
