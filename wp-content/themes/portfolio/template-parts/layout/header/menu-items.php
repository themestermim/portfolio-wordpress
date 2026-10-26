<?php
$menu_items = $args['items'] ?? [];
$level = (int) ($args['level'] ?? 1);
?>
<?php foreach ($menu_items as $item):
	$item_id = (int) $item['id'];
	$has_children = !empty($item['children']);
	$submenu_id = 'mim-submenu-' . $item_id;
	$classes = array_filter($item['classes']);
	$classes[] = 'menu-item';
	if ($has_children) {
		$classes[] = 'menu-item-has-children';
	}
	$rel_values = preg_split('/\s+/', trim($item['xfn']));
	if ($item['target'] === '_blank') {
		$rel_values[] = 'noopener';
		$rel_values[] = 'noreferrer';
	}
	$rel = implode(' ', array_unique(array_filter($rel_values)));
?>
	<li class="<?= esc_attr(implode(' ', $classes)); ?>"
		<?php if ($has_children): ?>
			x-on:mouseenter="openDesktopItem(<?= $level; ?>, <?= $item_id; ?>)"
			x-on:mouseleave="closeDesktopItem(<?= $level; ?>, <?= $item_id; ?>, $event)"
			x-on:focusout="if (!$el.contains($event.relatedTarget)) closeItem(<?= $level; ?>, <?= $item_id; ?>)"
			x-on:keydown.escape.stop.prevent="closeItem(<?= $level; ?>, <?= $item_id; ?>); $el.querySelector(':scope > .mim-submenu-toggle').focus()"
		<?php endif; ?>>
		<?php if ($item['has_link']): ?>
			<a href="<?= esc_url($item['url']); ?>"
				<?php if ($item['attr_title']): ?>title="<?= esc_attr($item['attr_title']); ?>"<?php endif; ?>
				<?php if ($item['target']): ?>target="<?= esc_attr($item['target']); ?>"<?php endif; ?>
				<?php if ($rel): ?>rel="<?= esc_attr($rel); ?>"<?php endif; ?>
				<?php if (in_array('current-menu-item', $classes, true)): ?>aria-current="page"<?php endif; ?>>
				<?= esc_html($item['title']); ?>
			</a>
		<?php elseif ($has_children): ?>
			<button type="button" class="mim-menu-label" x-on:click="toggleItem(<?= $level; ?>, <?= $item_id; ?>)"
				aria-controls="<?= esc_attr($submenu_id); ?>" x-bind:aria-expanded="isOpen(<?= $level; ?>, <?= $item_id; ?>).toString()">
				<?= esc_html($item['title']); ?>
			</button>
		<?php else: ?>
			<span class="mim-menu-label"><?= esc_html($item['title']); ?></span>
		<?php endif; ?>
		<?php if ($has_children): ?>
			<button type="button" class="mim-submenu-toggle" aria-label="<?= esc_attr('زیرمنوی ' . $item['title']); ?>"
				aria-controls="<?= esc_attr($submenu_id); ?>" x-bind:aria-expanded="isOpen(<?= $level; ?>, <?= $item_id; ?>).toString()"
				x-on:click="toggleItem(<?= $level; ?>, <?= $item_id; ?>)">
			</button>
			<ul class="sub-menu" id="<?= esc_attr($submenu_id); ?>" x-cloak x-show="isOpen(<?= $level; ?>, <?= $item_id; ?>)">
				<?php get_template_part('template-parts/layout/header/menu-items', null, [
					'items' => $item['children'],
					'level' => $level + 1,
				]); ?>
			</ul>
		<?php endif; ?>
	</li>
<?php endforeach; ?>
