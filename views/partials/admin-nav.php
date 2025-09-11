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
    'suple-speed-performance' => [
        'title' => __('Performance', 'suple-speed'), 
        'icon' => 'dashicons-performance'
    ],
    'suple-speed-cache' => [
        'title' => __('Cache', 'suple-speed'),
        'icon' => 'dashicons-update'
    ],
    'suple-speed-assets' => [
        'title' => __('Assets', 'suple-speed'),
        'icon' => 'dashicons-media-code'
    ],
    'suple-speed-critical' => [
        'title' => __('Critical & Preloads', 'suple-speed'),
        'icon' => 'dashicons-star-filled'
    ],
    'suple-speed-fonts' => [
        'title' => __('Fonts', 'suple-speed'),
        'icon' => 'dashicons-editor-textcolor'
    ],
    'suple-speed-images' => [
        'title' => __('Images', 'suple-speed'),
        'icon' => 'dashicons-format-image'
    ],
    'suple-speed-rules' => [
        'title' => __('Rules', 'suple-speed'),
        'icon' => 'dashicons-admin-settings'
    ],
    'suple-speed-compatibility' => [
        'title' => __('Compatibility', 'suple-speed'),
        'icon' => 'dashicons-yes-alt'
    ],
    'suple-speed-tools' => [
        'title' => __('Tools', 'suple-speed'),
        'icon' => 'dashicons-admin-tools'
    ],
    'suple-speed-logs' => [
        'title' => __('Logs', 'suple-speed'),
        'icon' => 'dashicons-list-view'
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