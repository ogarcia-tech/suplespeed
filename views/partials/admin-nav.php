<?php
/**
 * Navegación principal del admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
$current_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';

$default_dashboard_tabs = [
    'overview'      => [
        'label' => __('Overview', 'suple-speed'),
        'icon'  => 'dashicons-chart-area',
    ],
    'performance'   => [
        'label' => __('Performance', 'suple-speed'),
        'icon'  => 'dashicons-performance',
    ],
    'cache'         => [
        'label' => __('Cache', 'suple-speed'),
        'icon'  => 'dashicons-update',
    ],
    'assets'        => [
        'label' => __('Assets', 'suple-speed'),
        'icon'  => 'dashicons-media-code',
    ],
    'critical'      => [
        'label' => __('Critical & Preloads', 'suple-speed'),
        'icon'  => 'dashicons-star-filled',
    ],
    'fonts'         => [
        'label' => __('Fonts', 'suple-speed'),
        'icon'  => 'dashicons-editor-textcolor',
    ],
    'images'        => [
        'label' => __('Images', 'suple-speed'),
        'icon'  => 'dashicons-format-image',
    ],
    'rules'         => [
        'label' => __('Rules', 'suple-speed'),
        'icon'  => 'dashicons-admin-settings',
    ],
    'compatibility' => [
        'label' => __('Compatibility', 'suple-speed'),
        'icon'  => 'dashicons-yes-alt',
    ],
    'database'      => [
        'label' => __('Database', 'suple-speed'),
        'icon'  => 'dashicons-database',
    ],
    'tools'         => [
        'label' => __('Tools', 'suple-speed'),
        'icon'  => 'dashicons-admin-tools',
    ],
    'logs'          => [
        'label' => __('Logs', 'suple-speed'),
        'icon'  => 'dashicons-list-view',
    ],
];

$dashboard_tabs = [];
if (isset($tabs) && is_array($tabs) && !empty($tabs)) {
    $dashboard_tabs = $tabs;
} else {
    $dashboard_tabs = $default_dashboard_tabs;

    if (isset($this) && is_object($this) && method_exists($this, 'get_onboarding_steps')) {
        $onboarding_steps = $this->get_onboarding_steps();

        if (!is_array($onboarding_steps)) {
            $onboarding_steps = [];
        }

        if (!empty($onboarding_steps)) {
            $dashboard_tabs = ['getting-started' => [
                'label' => __('Guía rápida', 'suple-speed'),
                'icon'  => 'dashicons-welcome-learn-more',
            ]] + $dashboard_tabs;
        }
    }
}

if ('suple-speed' === $current_page) {
    if ('' === $current_section || !isset($dashboard_tabs[$current_section])) {
        $current_section = 'overview';
    }
}

$nav_items = [];

foreach ($dashboard_tabs as $section_slug => $section_data) {
    $section_key = sanitize_key($section_slug);
    $nav_items[] = [
        'page'    => 'suple-speed',
        'section' => $section_key,
        'title'   => $section_data['label'] ?? $section_data['title'] ?? '',
        'icon'    => $section_data['icon'] ?? 'dashicons-admin-page',
        'url'     => add_query_arg([
            'page'    => 'suple-speed',
            'section' => $section_key,
        ], admin_url('admin.php')),
    ];
}

$nav_items[] = [
    'page'    => 'suple-speed-settings',
    'section' => '',
    'title'   => __('Settings', 'suple-speed'),
    'icon'    => 'dashicons-admin-generic',
    'url'     => add_query_arg([
        'page' => 'suple-speed-settings',
    ], admin_url('admin.php')),
];
?>

<nav class="suple-speed-nav">
    <ul>
        <?php foreach ($nav_items as $nav_item): ?>
        <li>
            <?php
            $is_current = ($current_page === $nav_item['page']);
            if ('suple-speed' === $nav_item['page']) {
                $is_current = $is_current && ($current_section === $nav_item['section']);
            }
            ?>
            <a href="<?php echo esc_url($nav_item['url']); ?>"
               class="<?php echo $is_current ? 'current' : ''; ?>">
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