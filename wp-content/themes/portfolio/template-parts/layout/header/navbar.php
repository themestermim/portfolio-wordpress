<?php
$menu_items = mim_get_menu_tree('menu-1');

if (empty($menu_items)) {
	return;
}
?>
<ul class="mim-menu" id="mim-header-menu">
	<?php get_template_part('template-parts/layout/header/menu-items', null, [
		'items' => $menu_items,
		'level' => 1,
	]); ?>
</ul>
