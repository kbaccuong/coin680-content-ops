<?php
/**
 * Category archive template (News sub-categories, Bitcoin Academy sub-categories,
 * Exchange Comparison, Exchange Reviews sub-categories).
 */
get_header();
?>
<main class="c680-archive">
    <header class="c680-archive-header">
        <h1 class="c680-archive-title"><?php single_cat_title(); ?></h1>
        <?php
        $coin680_desc = category_description();
        if ($coin680_desc) :
        ?>
            <div class="c680-archive-desc"><?php echo wp_kses_post($coin680_desc); ?></div>
        <?php endif; ?>
    </header>
    <div class="c680-card-grid">
        <?php if (have_posts()) : ?>
            <?php
            while (have_posts()) :
                the_post();
                get_template_part('template-parts/card', null, array('variant' => 'standard'));
            endwhile;
            ?>
        <?php else : ?>
            <p><?php esc_html_e('No articles yet -- check back soon.', 'coin680'); ?></p>
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
