/**
 * Suple Speed - Admin JavaScript
 */
(function($) {
    'use strict';

    // Variables globales
    const SupleSpeedAdmin = {
        init: function() {
            this.bindEvents();
            this.initComponents();
            this.setupTabs();
            this.setupToggles();
            this.setupAjaxForms();
        },

        bindEvents: function() {
            // Purgar caché
            $(document).on('click', '.suple-purge-cache', this.purgeCache);
            
            // Ejecutar test PSI
            $(document).on('click', '.suple-run-psi', this.runPSITest);
            
            // Escanear handles
            $(document).on('click', '.suple-scan-handles', this.scanHandles);
            
            // Escanear fuentes
            $(document).on('click', '.suple-scan-fonts', this.scanFonts);
            
            // Localizar fuentes
            $(document).on('click', '.suple-localize-fonts', this.localizeFonts);
            
            // Guardar configuración
            $(document).on('click', '.suple-save-settings', this.saveSettings);
            
            // Resetear configuración
            $(document).on('click', '.suple-reset-settings', this.resetSettings);
            
            // Exportar configuración
            $(document).on('click', '.suple-export-settings', this.exportSettings);
            
            // Importar configuración
            $(document).on('change', '#suple-import-file', this.importSettings);
            
            // Aplicar sugerencias PSI
            $(document).on('click', '.suple-apply-suggestion', this.applySuggestion);
            
            // Crear regla
            $(document).on('click', '.suple-create-rule', this.createRule);
            
            // Cargar logs
            $(document).on('click', '.suple-load-logs', this.loadLogs);
        },

        initComponents: function() {
            // Inicializar tooltips
            this.initTooltips();
            
            // Inicializar contadores en tiempo real
            this.initRealTimeUpdates();
            
            // Inicializar drag & drop
            this.initDragDrop();
        },

        setupTabs: function() {
            $('.suple-tab-nav a').on('click', function(e) {
                e.preventDefault();
                
                const $this = $(this);
                const targetTab = $this.attr('href');
                
                // Actualizar navegación
                $this.closest('.suple-tab-nav').find('a').removeClass('active');
                $this.addClass('active');
                
                // Mostrar contenido
                $('.suple-tab-content').removeClass('active');
                $(targetTab).addClass('active');
            });
        },

        setupToggles: function() {
            $('.suple-toggle input[type="checkbox"]').on('change', function() {
                const $toggle = $(this).closest('.suple-toggle');
                const isEnabled = this.checked;
                
                // Actualizar dependencias
                const dependsOn = $toggle.data('depends-on');
                if (dependsOn) {
                    $(dependsOn).prop('disabled', !isEnabled);
                }
                
                // Mostrar/ocultar secciones
                const togglesSection = $toggle.data('toggles-section');
                if (togglesSection) {
                    if (isEnabled) {
                        $(togglesSection).slideDown();
                    } else {
                        $(togglesSection).slideUp();
                    }
                }
            });
        },

        setupAjaxForms: function() {
            // Auto-guardar en formularios
            $('.suple-auto-save input, .suple-auto-save select, .suple-auto-save textarea').on('change', function() {
                clearTimeout(window.supleAutoSaveTimer);
                window.supleAutoSaveTimer = setTimeout(function() {
                    SupleSpeedAdmin.autoSave();
                }, 2000);
            });
        },

        // === AJAX METHODS ===

        ajaxRequest: function(action, data, successCallback, errorCallback) {
            data = $.extend({
                action: 'suple_speed_' + action,
                nonce: supleSpeedAdmin.nonce
            }, data);

            return $.ajax({
                url: supleSpeedAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response.data);
                    } else {
                        if (errorCallback) {
                            errorCallback(response.data);
                        } else {
                            SupleSpeedAdmin.showNotice('error', response.data || supleSpeedAdmin.strings.error);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (errorCallback) {
                        errorCallback(error);
                    } else {
                        SupleSpeedAdmin.showNotice('error', supleSpeedAdmin.strings.error);
                    }
                }
            });
        },

        purgeCache: function(e) {
            e.preventDefault();
            
            if (!confirm(supleSpeedAdmin.strings.confirmPurge)) {
                return;
            }
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            const purgeAction = $button.data('purge-action') || 'all';
            const url = $button.data('url') || '';
            const postId = $button.data('post-id') || '';
            
            SupleSpeedAdmin.ajaxRequest('purge_cache', {
                purge_action: purgeAction,
                url: url,
                post_id: postId
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('success', data.message);
                
                // Actualizar estadísticas si están visibles
                SupleSpeedAdmin.updateCacheStats();
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        runPSITest: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            const url = $button.data('url') || $('#psi-test-url').val() || window.location.origin;
            const strategy = $button.data('strategy') || $('#psi-strategy').val() || 'mobile';
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('run_psi', {
                url: url,
                strategy: strategy
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                
                // Mostrar resultados
                SupleSpeedAdmin.displayPSIResults(data.result, data.suggestions);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        scanHandles: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            const scanUrl = $button.data('scan-url') || window.location.origin;
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('scan_handles', {
                scan_url: scanUrl
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                
                // Mostrar resultados de handles
                SupleSpeedAdmin.displayHandlesResults(data);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        scanFonts: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('scan_fonts', {}, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                
                // Mostrar fuentes encontradas
                SupleSpeedAdmin.displayFontsResults(data.fonts_found, data.stats);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        localizeFonts: function(e) {
            e.preventDefault();
            
            const fontUrls = [];
            $('.suple-font-item:checked').each(function() {
                fontUrls.push($(this).val());
            });
            
            if (fontUrls.length === 0) {
                SupleSpeedAdmin.showNotice('warning', 'Please select fonts to localize');
                return;
            }
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('localize_fonts', {
                font_urls: fontUrls
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                
                // Mostrar resultados
                SupleSpeedAdmin.displayLocalizationResults(data);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        saveSettings: function(e) {
            e.preventDefault();
            
            const $form = $(this).closest('form');
            const $button = $(this);
            const originalText = $button.text();
            
            const settings = {};
            $form.find('input, select, textarea').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                
                if (!name) return;
                
                if ($input.is(':checkbox')) {
                    settings[name] = $input.is(':checked');
                } else if ($input.is('[multiple]')) {
                    settings[name] = $input.val() || [];
                } else {
                    settings[name] = $input.val();
                }
            });
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('save_settings', {
                settings: settings
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('success', data.message);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(supleSpeedAdmin.strings.confirmReset)) {
                return;
            }
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('reset_settings', {}, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('success', data.message);
                
                // Recargar página para mostrar configuración reseteada
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        exportSettings: function(e) {
            e.preventDefault();
            
            SupleSpeedAdmin.ajaxRequest('export_settings', {}, function(data) {
                // Crear y descargar archivo
                const blob = new Blob([data.data], { type: 'application/json' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                SupleSpeedAdmin.showNotice('success', 'Settings exported successfully');
            });
        },

        importSettings: function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('action', 'suple_speed_import_settings');
            formData.append('nonce', supleSpeedAdmin.nonce);
            formData.append('import_file', file);
            
            $.ajax({
                url: supleSpeedAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        SupleSpeedAdmin.showNotice('success', response.data.message);
                        
                        // Recargar página para mostrar configuración importada
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        SupleSpeedAdmin.showNotice('error', response.data);
                    }
                },
                error: function() {
                    SupleSpeedAdmin.showNotice('error', supleSpeedAdmin.strings.error);
                }
            });
        },

        applySuggestion: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const suggestionId = $button.data('suggestion-id');
            const testId = $button.data('test-id');
            
            if (!suggestionId || !testId) return;
            
            const originalText = $button.text();
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('apply_psi_suggestions', {
                test_id: testId,
                suggestion_ids: [suggestionId]
            }, function(data) {
                $button.text('Applied');
                $button.removeClass('suple-button').addClass('suple-badge success');
                SupleSpeedAdmin.showNotice('success', data.message);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        createRule: function(e) {
            e.preventDefault();
            // TODO: Implementar creación de reglas
        },

        loadLogs: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const page = $button.data('page') || 1;
            const level = $('#log-level-filter').val();
            const module = $('#log-module-filter').val();
            
            const originalText = $button.text();
            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);
            
            SupleSpeedAdmin.ajaxRequest('get_logs', {
                page: page,
                level: level,
                module: module,
                per_page: 50
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);
                
                // Mostrar logs
                SupleSpeedAdmin.displayLogs(data.logs, data.stats);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);
                SupleSpeedAdmin.showNotice('error', error);
            });
        },

        // === DISPLAY METHODS ===

        displayPSIResults: function(result, suggestions) {
            const $container = $('#psi-results');
            if ($container.length === 0) return;
            
            let html = '<div class="suple-card">';
            html += '<h3>PageSpeed Insights Results</h3>';
            
            // Scores
            if (result.scores) {
                html += '<div class="suple-stats">';
                Object.keys(result.scores).forEach(function(category) {
                    const score = result.scores[category];
                    const color = score.score >= 90 ? 'success' : (score.score >= 50 ? 'warning' : 'error');
                    
                    html += '<div class="suple-stat-card ' + color + '">';
                    html += '<span class="suple-stat-value">' + Math.round(score.score) + '</span>';
                    html += '<span class="suple-stat-label">' + score.title + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            }
            
            // Core Web Vitals
            if (result.metrics) {
                html += '<h4>Core Web Vitals</h4>';
                html += '<div class="suple-metrics">';
                
                ['lcp', 'inp', 'cls'].forEach(function(metric) {
                    if (result.metrics[metric]) {
                        const data = result.metrics[metric];
                        html += '<div class="suple-metric">';
                        html += '<strong>' + data.title + ':</strong> ';
                        html += data.displayValue || data.value;
                        html += '</div>';
                    }
                });
                
                html += '</div>';
            }
            
            // Sugerencias
            if (suggestions && suggestions.length > 0) {
                html += '<h4>Optimization Suggestions</h4>';
                html += '<div class="suple-suggestions">';
                
                suggestions.forEach(function(suggestion) {
                    html += '<div class="suple-suggestion">';
                    html += '<div class="suple-flex">';
                    html += '<div class="suple-flex-1">';
                    html += '<strong>' + suggestion.title + '</strong><br>';
                    html += '<small>' + suggestion.description + '</small><br>';
                    html += '<span class="suple-badge info">Impact: ' + suggestion.impact_formatted + '</span>';
                    html += '</div>';
                    html += '<div>';
                    html += '<button type="button" class="suple-button success suple-apply-suggestion" ';
                    html += 'data-suggestion-id="' + suggestion.id + '" ';
                    html += 'data-test-id="' + result.timestamp + '">';
                    html += 'Apply';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            html += '</div>';
            
            $container.html(html);
        },

        displayHandlesResults: function(data) {
            const $container = $('#handles-results');
            if ($container.length === 0) return;
            
            let html = '<div class="suple-card">';
            html += '<h3>Detected Assets</h3>';
            
            // CSS Handles
            if (data.css && Object.keys(data.css).length > 0) {
                html += '<h4>CSS Files</h4>';
                html += '<table class="suple-table">';
                html += '<thead><tr><th>Handle</th><th>Group</th><th>Source</th><th>Can Merge</th></tr></thead>';
                html += '<tbody>';
                
                Object.keys(data.css).forEach(function(handle) {
                    const css = data.css[handle];
                    html += '<tr>';
                    html += '<td><code>' + handle + '</code></td>';
                    html += '<td><span class="suple-badge">Group ' + css.group + '</span></td>';
                    html += '<td><small>' + (css.src || 'N/A') + '</small></td>';
                    html += '<td>' + (css.can_merge ? '✅' : '❌') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
            }
            
            // JS Handles
            if (data.js && Object.keys(data.js).length > 0) {
                html += '<h4>JavaScript Files</h4>';
                html += '<table class="suple-table">';
                html += '<thead><tr><th>Handle</th><th>Group</th><th>Source</th><th>Can Merge</th><th>Can Defer</th></tr></thead>';
                html += '<tbody>';
                
                Object.keys(data.js).forEach(function(handle) {
                    const js = data.js[handle];
                    html += '<tr>';
                    html += '<td><code>' + handle + '</code></td>';
                    html += '<td><span class="suple-badge">Group ' + js.group + '</span></td>';
                    html += '<td><small>' + (js.src || 'N/A') + '</small></td>';
                    html += '<td>' + (js.can_merge ? '✅' : '❌') + '</td>';
                    html += '<td>' + (js.can_defer ? '✅' : '❌') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
            }
            
            html += '</div>';
            
            $container.html(html);
        },

        displayFontsResults: function(fonts, stats) {
            const $container = $('#fonts-results');
            if ($container.length === 0) return;
            
            let html = '<div class="suple-card">';
            html += '<h3>Google Fonts Detected</h3>';
            
            if (fonts.length > 0) {
                html += '<div class="suple-form">';
                fonts.forEach(function(font) {
                    html += '<div class="suple-form-row">';
                    html += '<label class="suple-form-toggle">';
                    html += '<input type="checkbox" class="suple-font-item" value="' + font.url + '">';
                    html += '<span><strong>' + font.source + '</strong>: ' + font.location + '</span>';
                    html += '<br><small>' + font.url + '</small>';
                    html += '</label>';
                    html += '</div>';
                });
                
                html += '<div class="suple-form-row">';
                html += '<button type="button" class="suple-button success suple-localize-fonts">Localize Selected Fonts</button>';
                html += '</div>';
                html += '</div>';
            } else {
                html += '<p>No Google Fonts detected.</p>';
            }
            
            // Estadísticas
            if (stats.total_localized > 0) {
                html += '<h4>Current Stats</h4>';
                html += '<div class="suple-stats">';
                html += '<div class="suple-stat-card">';
                html += '<span class="suple-stat-value">' + stats.total_localized + '</span>';
                html += '<span class="suple-stat-label">Localized Fonts</span>';
                html += '</div>';
                html += '<div class="suple-stat-card">';
                html += '<span class="suple-stat-value">' + stats.total_size_formatted + '</span>';
                html += '<span class="suple-stat-label">Total Size</span>';
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $container.html(html);
        },

        displayLocalizationResults: function(results) {
            let html = '<div class="suple-notice success">';
            html += '<h4>Localization Results</h4>';
            
            results.forEach(function(result) {
                html += '<div>';
                html += result.success ? '✅' : '❌';
                html += ' ' + result.original_url;
                html += '<br><small>' + result.message + '</small>';
                html += '</div>';
            });
            
            html += '</div>';
            
            $('#fonts-results').prepend(html);
        },

        displayLogs: function(logs, stats) {
            const $container = $('#logs-table');
            if ($container.length === 0) return;
            
            let html = '<table class="suple-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>Time</th>';
            html += '<th>Level</th>';
            html += '<th>Module</th>';
            html += '<th>Message</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            logs.forEach(function(log) {
                const levelClass = log.level === 'error' ? 'error' : 
                                  log.level === 'warning' ? 'warning' : 
                                  log.level === 'info' ? 'info' : 'secondary';
                
                html += '<tr>';
                html += '<td>' + log.timestamp + '</td>';
                html += '<td><span class="suple-badge ' + levelClass + '">' + log.level + '</span></td>';
                html += '<td>' + log.module + '</td>';
                html += '<td>' + log.message;
                
                if (log.url) {
                    html += '<br><small>URL: ' + log.url + '</small>';
                }
                
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            
            $container.html(html);
        },

        // === UTILITY METHODS ===

        showNotice: function(type, message) {
            const $notice = $('<div class="suple-notice ' + type + '">' + message + '</div>');
            
            // Buscar contenedor de notices o crear uno
            let $container = $('.suple-notices');
            if ($container.length === 0) {
                $container = $('<div class="suple-notices"></div>');
                $('.suple-speed-admin').prepend($container);
            }
            
            $container.append($notice);
            
            // Auto-remover después de 5 segundos
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        updateCacheStats: function() {
            // Actualizar estadísticas de caché si están visibles
            const $stats = $('.cache-stats');
            if ($stats.length === 0) return;
            
            SupleSpeedAdmin.ajaxRequest('get_cache_stats', {}, function(data) {
                $stats.find('.total-files').text(data.total_files);
                $stats.find('.total-size').text(data.total_size_formatted);
            });
        },

        autoSave: function() {
            const $form = $('.suple-auto-save');
            if ($form.length === 0) return;
            
            // Mostrar indicador de guardado
            const $indicator = $('<span class="suple-auto-save-indicator">Saving...</span>');
            $form.append($indicator);
            
            // Simular guardado (en implementación real, enviar AJAX)
            setTimeout(function() {
                $indicator.text('Saved').addClass('success');
                setTimeout(function() {
                    $indicator.fadeOut(function() {
                        $indicator.remove();
                    });
                }, 2000);
            }, 1000);
        },

        initTooltips: function() {
            // Implementar tooltips básicos
            $('[data-tooltip]').on('mouseenter', function() {
                const $this = $(this);
                const text = $this.data('tooltip');
                
                const $tooltip = $('<div class="suple-tooltip">' + text + '</div>');
                $('body').append($tooltip);
                
                const offset = $this.offset();
                $tooltip.css({
                    position: 'absolute',
                    top: offset.top - $tooltip.outerHeight() - 10,
                    left: offset.left + ($this.outerWidth() / 2) - ($tooltip.outerWidth() / 2),
                    zIndex: 9999
                });
            }).on('mouseleave', function() {
                $('.suple-tooltip').remove();
            });
        },

        initRealTimeUpdates: function() {
            // Actualizar métricas en tiempo real cada 30 segundos
            if ($('.suple-real-time').length > 0) {
                setInterval(function() {
                    SupleSpeedAdmin.updateRealTimeStats();
                }, 30000);
            }
        },

        updateRealTimeStats: function() {
            // TODO: Implementar actualización de estadísticas en tiempo real
        },

        initDragDrop: function() {
            // Implementar drag & drop para reordenar elementos
            $('.suple-sortable').sortable({
                handle: '.suple-drag-handle',
                update: function() {
                    // TODO: Guardar nuevo orden
                }
            });
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        SupleSpeedAdmin.init();
    });

    // Exponer objeto global para uso externo
    window.SupleSpeedAdmin = SupleSpeedAdmin;

})(jQuery);