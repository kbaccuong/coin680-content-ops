<?php
/**
 * Header template: breaking bar + main header (logo, nav, ticker).
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
$coin680_breaking = new WP_Query(array(
    'category_name'       => 'crypto-market-news',
    'posts_per_page'      => 1,
    'ignore_sticky_posts' => 1,
));
if ($coin680_breaking->have_posts()) :
    $coin680_breaking->the_post();
?>
<div class="c680-breaking-bar">
    <span class="c680-breaking-label"><?php esc_html_e('Breaking', 'coin680'); ?></span>
    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
</div>
<?php
endif;
wp_reset_postdata();
?>

<header class="c680-header">
    <div class="c680-header-inner">
        <div class="c680-branding">
            <?php if (has_custom_logo()) : the_custom_logo(); else : ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="c680-logo-text"><?php bloginfo('name'); ?></a>
            <?php endif; ?>
        </div>
        <button type="button" class="c680-nav-toggle" aria-label="<?php esc_attr_e('Menu', 'coin680'); ?>" aria-expanded="false" aria-controls="c680-primary-nav">
            <span></span><span></span><span></span>
        </button>
        <nav id="c680-primary-nav" class="c680-primary-nav" aria-label="<?php esc_attr_e('Primary', 'coin680'); ?>">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'c680-menu',
                'fallback_cb'    => false,
            ));
            ?>
        </nav>
    </div>
</header>
<script>
(function () {
    var toggle = document.querySelector('.c680-nav-toggle');
    var nav = document.getElementById('c680-primary-nav');
    if (!toggle || !nav) { return; }
    toggle.addEventListener('click', function () {
        var isOpen = nav.classList.toggle('c680-nav-open');
        toggle.classList.toggle('c680-nav-toggle-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
})();
</script>
