<?php
/**
 * Navegación principal del admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = $_GET['page'] ?? '';
$nav_items = [
    'suple-speed' => [
        'title' => __('Dashboard', 'suple-speed'),
        'icon' => 'dashicons-dashboard'
    ],
    'suple-speed-settings' => [
        'title' => __('Settings', 'suple-speed'),
        'icon' => 'dashicons-admin-generic'
    ]
];
?>

<nav class="suple-speed-nav">
    <ul>
        <?php foreach ($nav_items as $page_slug => $nav_item): ?>
        <li>
            <a href="<?php echo admin_url('admin.php?page=' . $page_slug); ?>" 
               class="<?php echo ($current_page === $page_slug) ? 'current' : ''; ?>">
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