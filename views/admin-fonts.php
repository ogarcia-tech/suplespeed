<?php
/**
 * Página de gestión de fuentes
 */

if (!defined('ABSPATH')) {
    exit;
}

$fonts_module = function_exists('suple_speed') ? suple_speed()->fonts : null;
$fonts_stats = [
    'total_localized' => 0,
    'total_size' => 0,
    'total_size_formatted' => size_format(0),
    'families' => [],
    'files' => [],
];

if ($fonts_module && method_exists($fonts_module, 'get_fonts_stats')) {
    $fonts_stats = $fonts_module->get_fonts_stats();
    if (empty($fonts_stats['total_size_formatted'])) {
        $fonts_stats['total_size_formatted'] = size_format((int) ($fonts_stats['total_size'] ?? 0));
    }
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Fonts', 'suple-speed'); ?></h1>
        <p><?php _e('Localize Google Fonts and review font optimization status.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Localization Summary', 'suple-speed'); ?></h3>
            <ul class="suple-stats">
                <li class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($fonts_stats['total_localized']); ?></span>
                    <span class="suple-stat-label"><?php _e('Localized Fonts', 'suple-speed'); ?></span>
                </li>
                <li class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($fonts_stats['total_size_formatted']); ?></span>
                    <span class="suple-stat-label"><?php _e('Storage Used', 'suple-speed'); ?></span>
                </li>
            </ul>
            <button type="button" class="suple-button suple-localize-fonts">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <?php _e('Localize Fonts Now', 'suple-speed'); ?>
            </button>
        </div>

        <div class="suple-card">
            <h3><?php _e('Font Families', 'suple-speed'); ?></h3>
            <?php if (!empty($fonts_stats['families'])): ?>
                <ul class="suple-list">
                    <?php foreach ($fonts_stats['families'] as $family): ?>
                        <li><?php echo esc_html($family); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('No localized font families detected yet.', 'suple-speed'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Localized Files', 'suple-speed'); ?></h3>
        <?php if (!empty($fonts_stats['files'])): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('File', 'suple-speed'); ?></th>
                        <th><?php _e('Size', 'suple-speed'); ?></th>
                        <th><?php _e('Last Modified', 'suple-speed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fonts_stats['files'] as $file): ?>
                        <tr>
                            <td><?php echo esc_html($file['name'] ?? ''); ?></td>
                            <td><?php echo isset($file['size']) ? esc_html(size_format((int) $file['size'])) : ''; ?></td>
                            <td><?php echo !empty($file['modified']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['modified'])) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No font files have been localized yet. Run a localization to populate this list.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>
</div>
