<?php
/**
 * Single post template -- Bitcoin Academy article, Exchange Hub article, or
 * Crypto Market News article. All three share this template; the automatic
 * affiliate banner (functions.php: coin680_maybe_affiliate_banner) only
 * appears for Exchange Comparison / Exchange Reviews categories.
 */
get_header();
?>
<main class="c680-single">
    <div class="c680-single-layout">
        <article <?php post_class('c680-single-article'); ?>>
            <?php while (have_posts()) : the_post(); ?>
                <?php coin680_track_post_view(get_the_ID()); ?>
                <header class="c680-single-header">
                    <?php
                    $coin680_cats = get_the_category();
                    if (!empty($coin680_cats)) :
                    ?>
                        <a class="c680-card-cat" href="<?php echo esc_url(get_category_link($coin680_cats[0])); ?>"><?php echo esc_html($coin680_cats[0]->name); ?></a>
                    <?php endif; ?>
                    <h1 class="c680-single-title"><?php the_title(); ?></h1>
                    <div class="c680-single-meta">
                        <span><?php esc_html_e('By', 'coin680'); ?> <a href="<?php echo esc_url(home_url('/about/')); ?>" class="c680-author-link"><?php echo esc_html(coin680_author_name()); ?></a></span>
                        <span> · <?php the_date(); ?></span>
                        <span> · <?php echo esc_html(coin680_reading_time()); ?> <?php esc_html_e('min read', 'coin680'); ?></span>
                    </div>
                    <?php coin680_share_buttons(); ?>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="c680-single-thumb"><?php the_post_thumbnail('large'); ?></div>
                    <?php endif; ?>
                </header>
                <div class="c680-single-content">
                    <?php the_content(); ?>
                </div>
                <?php coin680_share_buttons(); ?>
                <?php coin680_author_box(); ?>
                <?php coin680_newsletter_form('article'); ?>
                <?php comments_template(); ?>
            <?php endwhile; ?>
        </article>
        <aside class="c680-single-sidebar">
            <?php
            $coin680_related_cats = wp_list_pluck(get_the_category(), 'term_id');
            if (!empty($coin680_related_cats)) :
                $coin680_related = new WP_Query(array(
                    'category__in'   => $coin680_related_cats,
                    'post__not_in'   => array(get_the_ID()),
                    'posts_per_page' => 5,
                ));
                if ($coin680_related->have_posts()) :
            ?>
                <h3 class="c680-widget-title"><?php esc_html_e('More Like This', 'coin680'); ?></h3>
                <?php
                while ($coin680_related->have_posts()) :
                    $coin680_related->the_post();
                ?>
                    <div class="c680-trending-item">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </div>
                <?php
                endwhile;
                endif;
                wp_reset_postdata();
            endif;
            if (is_active_sidebar('news-sidebar')) :
                dynamic_sidebar('news-sidebar');
            endif;
            ?>
        </aside>
    </div>
</main>
<?php get_footer(); ?>
