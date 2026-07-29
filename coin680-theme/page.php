<?php
/**
 * Generic static page template -- used for About, Advertising Disclosure,
 * Editorial Policy, Privacy Policy, Risk Disclaimer, Contact, etc.
 */
get_header();
?>
<main class="c680-page">
    <?php
    while (have_posts()) :
        the_post();
    ?>
        <article class="c680-page-article">
            <h1 class="c680-page-title"><?php the_title(); ?></h1>
            <div class="c680-page-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
