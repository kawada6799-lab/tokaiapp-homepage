<?php
/**
 * index.php — WordPress がテーマに必ず求めるファイル。
 * お知らせ（投稿）の一覧と、該当が無いときの受け皿。
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main id="main">

  <section class="page-head">
    <div class="wrap">
      <nav class="breadcrumb" aria-label="現在位置">
        <a href="<?php echo esc_url(home_url('/')); ?>">ホーム</a><span>›</span>
        <span aria-current="page"><?php echo esc_html(is_home() ? 'お知らせ' : wp_get_document_title()); ?></span>
      </nav>
      <p class="page-head__en">News</p>
      <h1 class="page-head__title"><?php echo esc_html(is_home() ? 'お知らせ' : '一覧'); ?></h1>
    </div>
  </section>

  <section class="section">
    <div class="wrap wrap--narrow">
      <?php if (have_posts()) : ?>
        <ul class="news">
          <?php while (have_posts()) : the_post(); ?>
            <li class="news__item">
              <a href="<?php the_permalink(); ?>">
                <time class="news__date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                  <?php echo esc_html(get_the_date('Y.m.d')); ?>
                </time>
                <?php $cats = get_the_category(); ?>
                <span class="news__cat">
                  <?php echo esc_html($cats ? $cats[0]->name : 'お知らせ'); ?>
                </span>
                <span class="news__title"><?php the_title(); ?></span>
              </a>
            </li>
          <?php endwhile; ?>
        </ul>

        <div class="pagination">
          <?php echo paginate_links(array('prev_text' => '‹ 前へ', 'next_text' => '次へ ›')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
      <?php else : ?>
        <p>まだ記事がありません。</p>
      <?php endif; ?>
    </div>
  </section>

</main>
<?php
get_footer();
