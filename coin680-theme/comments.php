<?php
/**
 * Comment list + form. Loaded automatically by comments_template() (called
 * from single.php). Anonymous commenting -- no login, no email, just a
 * name/nickname (see functions.php for the field filters that enforce that).
 */

if (post_password_required()) {
    return;
}
?>
<div id="comments" class="c680-comments">

    <?php if (have_comments()) : ?>
        <h2 class="c680-comments-title">
            <?php
            $coin680_comment_count = get_comments_number();
            printf(
                /* translators: %s: number of comments */
                esc_html(_n('%s Comment', '%s Comments', $coin680_comment_count, 'coin680')),
                esc_html(number_format_i18n($coin680_comment_count))
            );
            ?>
        </h2>
        <ul class="c680-comment-list">
            <?php
            wp_list_comments(array(
                'style'      => 'ul',
                'short_ping' => true,
                'avatar_size' => 44,
            ));
            ?>
        </ul>
        <?php the_comments_navigation(); ?>
    <?php endif; ?>

    <?php if (!comments_open() && get_comments_number()) : ?>
        <p class="c680-comments-closed"><?php esc_html_e('Comments are closed.', 'coin680'); ?></p>
    <?php endif; ?>

    <?php comment_form(); ?>

</div>
