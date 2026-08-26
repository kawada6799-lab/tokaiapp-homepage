<?php
/**
 * footer.php — CTA帯とフッター。
 * 中身は parts/cta.html と parts/footer.html にある（プレビューと共通）。
 */
if (!defined('ABSPATH')) { exit; }
?>
<?php echo tokaiapp_html('parts/cta.html');    // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php echo tokaiapp_html('parts/footer.html'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php wp_footer(); ?>
</body>
</html>
