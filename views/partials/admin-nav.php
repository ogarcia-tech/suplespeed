<?php
/**
 * Navegación principal del admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

$nav_items = [
    [
        'page'  => 'suple-speed',
        'title' => __('Suple Speed', 'suple-speed'),
        'icon'  => 'dashicons-performance',
        'url'   => add_query_arg([
            'page' => 'suple-speed',
        ], admin_url('admin.php')),
    ],
    [
        'page'  => 'suple-speed-settings',
        'title' => __('Settings', 'suple-speed'),
        'icon'  => 'dashicons-admin-generic',
        'url'   => add_query_arg([
            'page' => 'suple-speed-settings',
        ], admin_url('admin.php')),
    ],
];
?>

<nav class="suple-speed-nav">
    <ul>
        <?php foreach ($nav_items as $nav_item): ?>
        <li>
            <a href="<?php echo esc_url($nav_item['url']); ?>"
               class="<?php echo ($current_page === $nav_item['page']) ? 'current' : ''; ?>">
                <span class="dashicons <?php echo esc_attr($nav_item['icon']); ?>"></span>
                <?php echo esc_html($nav_item['title']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</nav>

<style>
.suple-speed-nav a {
    display: flex;
    align-items: center;
    gap: 8px;
}

.suple-speed-nav .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>
