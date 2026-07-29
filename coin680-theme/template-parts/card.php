<?php
/**
 * Reusable post card. Called with:
 * get_template_part('template-parts/card', null, array('variant' => 'hero|compact|standard'));
 * Must be called inside a loop (relies on the current global $post).
 */

$variant = isset($args['variant']) ? $args['variant'] : 'standard';
?>
<article <?php post_class('c680-card c680-card-' . esc_attr($variant)); ?>>
    <a href="<?php the_permalink(); ?>" class="c680-card-thumb">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail($variant === 'hero' ? 'large' : 'medium'); ?>
        <?php else : ?>
            <div class="c680-card-thumb-placeholder"></div>
        <?php endif; ?>
    </a>
    <div class="c680-card-body">
        <?php
        $coin680_cats = get_the_category();
        if (!empty($coin680_cats)) :
        ?>
            <span class="c680-card-cat"><?php echo esc_html($coin680_cats[0]->name); ?></span>
        <?php endif; ?>
        <h3 class="c680-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <?php if ('compact' !== $variant) : ?>
            <p class="c680-card-excerpt"><?php echo esc_html(coin680_card_excerpt(18)); ?></p>
        <?php endif; ?>
        <div class="c680-card-meta">
            <span><?php echo esc_html(get_the_date()); ?></span>
            <span> · <?php echo esc_html(coin680_reading_time()); ?> <?php esc_html_e('min read', 'coin680'); ?></span>
        </div>
    </div>
</article>
