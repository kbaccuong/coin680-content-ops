<?php
/**
 * Fallback template (used for the blog if the front page is set to a static
 * page elsewhere on the site, and as the final fallback in the template
 * hierarchy). Most traffic hits front-page.php / category.php / single.php
 * instead.
 */
get_header();
?>
<main class="c680-archive">
    <div class="c680-card-grid">
        <?php if (have_posts()) : ?>
            <?php
            while (have_posts()) :
                the_post();
                get_template_part('template-parts/card', null, array('variant' => 'standard'));
            endwhile;
            ?>
        <?php else : ?>
            <p><?php esc_html_e('No posts found.', 'coin680'); ?></p>
        <?php endif; ?>
    </div>
    <?php
    the_posts_pagination(array(
        'prev_text' => __('← Newer', 'coin680'),
        'next_text' => __('Older →', 'coin680'),
    ));
    ?>
</main>
<?php get_footer(); ?>
