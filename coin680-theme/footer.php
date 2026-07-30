<?php
/**
 * Footer template.
 */
?>
    <footer class="c680-footer">
        <div class="c680-footer-inner">
            <nav class="c680-footer-nav" aria-label="<?php esc_attr_e('Footer', 'coin680'); ?>">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'c680-footer-menu',
                    'fallback_cb'    => false,
                ));
                ?>
            </nav>
            <p class="c680-footer-disclaimer">
                <?php esc_html_e('Coin680 provides educational content about Bitcoin and the cryptocurrency market. Nothing on this site is financial advice. Cryptocurrency investments carry significant risk.', 'coin680'); ?>
                <a href="<?php echo esc_url(home_url('/advertising-disclosure/')); ?>"><?php esc_html_e('Advertising Disclosure', 'coin680'); ?></a>
                ·
                <a href="<?php echo esc_url(home_url('/risk-disclaimer/')); ?>"><?php esc_html_e('Risk Disclaimer', 'coin680'); ?></a>
            </p>
            <p class="c680-footer-copy">
                &copy; <?php echo esc_html(gmdate('Y')); ?> Coin680. <?php esc_html_e('All rights reserved.', 'coin680'); ?>
                · <a class="c680-footer-x" href="https://x.com/coin680" target="_blank" rel="noopener"><?php esc_html_e('Follow us on X', 'coin680'); ?></a>
            </p>
        </div>
    </footer>
    <?php wp_footer(); ?>
</body>
</html>
