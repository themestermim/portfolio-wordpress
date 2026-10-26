<?php
/* Template Name: Init Page */
get_header();
?>

<div>
    <?php if (function_exists('yoast_breadcrumb')): ?>
        <?php yoast_breadcrumb('<div class="container" id="breadcrumbs">', '</div>'); ?>
    <?php endif; ?>
</div>

<?php
get_footer();
?>