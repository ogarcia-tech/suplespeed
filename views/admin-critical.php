<?php
/**
 * Página de Critical CSS y preloads
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_settings = $this->get_current_settings();
$fonts_module = function_exists('suple_speed') ? suple_speed()->fonts : null;
$font_preloads = [];

if ($fonts_module && method_exists($fonts_module, 'get_font_preloads')) {
    $font_preloads = $fonts_module->get_font_preloads();
}

$asset_preloads = $current_settings['preload_assets'] ?? [];
$critical_css = $current_settings['critical_css'] ?? '';
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Critical & Preloads', 'suple-speed'); ?></h1>
        <p><?php _e('Manage critical CSS snippets and resource preloads.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Critical CSS', 'suple-speed'); ?></h3>
            <?php if (!empty($critical_css)): ?>
                <p><?php _e('A custom critical CSS snippet is currently active.', 'suple-speed'); ?></p>
                <textarea class="widefat" rows="6" readonly><?php echo esc_textarea(wp_trim_words($critical_css, 120, '…')); ?></textarea>
            <?php else: ?>
                <p><?php _e('No critical CSS has been configured yet. You can add one in the settings panel.', 'suple-speed'); ?></p>
            <?php endif; ?>
            <a class="suple-button" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-assets')); ?>">
                <?php _e('Edit Critical CSS', 'suple-speed'); ?>
            </a>
        </div>

        <div class="suple-card">
            <h3><?php _e('Asset Preloads', 'suple-speed'); ?></h3>
            <?php if (!empty($asset_preloads)): ?>
                <ul class="suple-list">
                    <?php foreach ($asset_preloads as $preload): ?>
                        <li>
                            <strong><?php echo esc_html($preload['type'] ?? ($preload['as'] ?? '')); ?></strong> –
                            <span><?php echo esc_html($preload['url'] ?? ($preload['href'] ?? '')); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('No asset preloads defined. Configure CSS/JS preloads to improve paint times.', 'suple-speed'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Font Preloads', 'suple-speed'); ?></h3>
        <?php if (!empty($font_preloads)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('URL', 'suple-speed'); ?></th>
                        <th><?php _e('Type', 'suple-speed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($font_preloads as $preload): ?>
                        <tr>
                            <td><?php echo esc_html($preload['href'] ?? ''); ?></td>
                            <td><?php echo esc_html($preload['type'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No font preloads detected yet. Suple Speed will populate this list after scanning your pages.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>
</div>
