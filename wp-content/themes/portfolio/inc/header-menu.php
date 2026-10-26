<?php
/** Menu tree shared by the Alpine header templates, following the Arasb structure. */
function mim_get_menu_tree($menu_name, $max_depth = 0)
{
	$locations = get_nav_menu_locations();
	$menu_id = $locations[$menu_name] ?? 0;
	$menu = $menu_id ? wp_get_nav_menu_object($menu_id) : false;


	if (!$menu) {
		return [];
	}

	$menu_items = wp_get_nav_menu_items($menu->term_id);

	if (!is_array($menu_items) || empty($menu_items)) {
		return [];
	}

	// Use the same current-page and ancestor classes as wp_nav_menu().
	_wp_menu_item_classes_by_context($menu_items);

	$children_by_parent = [];

	foreach ($menu_items as $menu_item) {
		$parent_id = (int) $menu_item->menu_item_parent;

		if (!isset($children_by_parent[$parent_id])) {
			$children_by_parent[$parent_id] = [];
		}

		$children_by_parent[$parent_id][] = $menu_item;
	}

	$build_tree = static function ($parent_id, $depth) use (&$build_tree, $children_by_parent, $max_depth) {
		if (($max_depth > 0 && $depth > $max_depth) || empty($children_by_parent[$parent_id])) {
			return [];
		}

		$items = [];

		foreach ($children_by_parent[$parent_id] as $item) {
			$url = trim((string) $item->url);
			$classes = is_array($item->classes) ? $item->classes : [];
			$children = $build_tree((int) $item->ID, $depth + 1);

			$items[] = [
				'id' => (int) $item->ID,
				'title' => $item->title,
				'url' => $url,
				'has_link' => $url !== '' && $url !== '#',
				'attr_title' => $item->attr_title,
				'target' => $item->target,
				'xfn' => $item->xfn,
				'classes' => $classes,
				'is_active' => !empty(array_intersect([
					'current-menu-item',
					'current-menu-parent',
					'current-menu-ancestor',
					'current_page_item',
				], $classes)),
				'children' => $children,
			];
		}

		return $items;
	};

	return $build_tree(0, 1);
}
