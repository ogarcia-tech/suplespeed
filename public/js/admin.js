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
            this.initializeAssetsUI();
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

            // Overrides manuales de assets
            $(document).on('change', '.suple-handle-group-select', this.onManualGroupChange.bind(this));
            $(document).on('click', '.suple-save-manual-groups', this.saveManualGroups.bind(this));
            $(document).on('click', '.suple-regenerate-bundles', this.regenerateBundles.bind(this));
            $(document).on('click', '.suple-run-preload-collector', this.runPreloadCollector.bind(this));
            $(document).on('click', '.suple-refresh-preload-recommendations', this.refreshPreloadRecommendations.bind(this));
            $(document).on('click', '.suple-accept-preload', this.acceptPreloadRecommendation.bind(this));
            $(document).on('click', '.suple-reject-preload', this.rejectPreloadRecommendation.bind(this));
        },

        initComponents: function() {
            // Inicializar tooltips
            this.initTooltips();

            // Inicializar contadores en tiempo real
            this.initRealTimeUpdates();

            // Inicializar drag & drop
            this.initDragDrop();

            // Inicializar guía rápida
            this.initOnboarding();
        },

        setupTabs: function() {
            const $tabNav = $('.suple-tab-nav');
            if ($tabNav.length === 0) {
                return;
            }

            const openTab = function(targetTab) {
                if (!targetTab || targetTab.charAt(0) !== '#') {
                    return;
                }

                const $target = $(targetTab);
                if ($target.length === 0) {
                    return;
                }

                $tabNav.find('a').removeClass('active');
                $tabNav.find('a[href="' + targetTab + '"]').addClass('active');

                $('.suple-tab-content').removeClass('active');
                $target.addClass('active');
            };

            $tabNav.on('click', 'a', function(e) {
                e.preventDefault();

                const targetTab = $(this).attr('href');
                openTab(targetTab);

                if (history.replaceState) {
                    const baseUrl = window.location.href.split('#')[0];
                    history.replaceState(null, '', baseUrl + targetTab);
                } else {
                    window.location.hash = targetTab;
                }
            });

            const initialHash = window.location.hash;
            if (initialHash && $(initialHash).hasClass('suple-tab-content')) {
                openTab(initialHash);
            }

            $(window).on('hashchange', function() {
                const newHash = window.location.hash;
                if (newHash && $(newHash).hasClass('suple-tab-content')) {
                    openTab(newHash);
                }
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
            const $forms = $('.suple-auto-save');
            if ($forms.length === 0) return;

            const autoSaveEnabled = typeof supleSpeedAdmin === 'undefined' || supleSpeedAdmin.autoSaveEnabled !== false;

            if (!autoSaveEnabled) {
                clearTimeout(window.supleAutoSaveTimer);
                $forms.each(function() {
                    const $form = $(this);
                    const indicatorTimer = $form.data('autoSaveIndicatorTimer');
                    if (indicatorTimer) {
                        clearTimeout(indicatorTimer);
                        $form.removeData('autoSaveIndicatorTimer');
                    }

                    const $indicator = $form.data('autoSaveIndicator');
                    if ($indicator && $indicator.length) {
                        $indicator.remove();
                        $form.removeData('autoSaveIndicator');
                    }
                });
                return;
            }

            $forms.each(function() {
                const $form = $(this);
                const formAutoSave = $form.data('auto-save');
                if (formAutoSave === false || formAutoSave === 'false') {
                    return;
                }

                $form.on('change', 'input, select, textarea', function() {
                    clearTimeout(window.supleAutoSaveTimer);
                    window.supleAutoSaveTimer = setTimeout(function() {
                        SupleSpeedAdmin.autoSave($form);
                    }, 2000);
                });
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
                    if (response && response.success) {
                        if (successCallback) successCallback(response.data);
                        return;
                    }

                    let message = response && typeof response.data !== 'undefined'
                        ? response.data
                        : null;

                    if (message && typeof message === 'object' && message.message) {
                        message = message.message;
                    }

                    if (typeof message !== 'string' || message === '') {
                        message = (supleSpeedAdmin.strings && supleSpeedAdmin.strings.error) || 'An error occurred';
                    }

                    if (errorCallback) {
                        errorCallback(message);
                    } else {
                        SupleSpeedAdmin.showNotice('error', message);
                    }
                },
                error: function(xhr) {
                    let message = null;

                    if (xhr && xhr.responseJSON && typeof xhr.responseJSON.data !== 'undefined') {
                        if (typeof xhr.responseJSON.data === 'string') {
                            message = xhr.responseJSON.data;
                        } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            message = xhr.responseJSON.data.message;
                        }
                    } else if (xhr && xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed && typeof parsed.data !== 'undefined') {
                                if (typeof parsed.data === 'string') {
                                    message = parsed.data;
                                } else if (parsed.data && parsed.data.message) {
                                    message = parsed.data.message;
                                }
                            }
                        } catch (e) {}
                    }

                    if (typeof message !== 'string' || message === '') {
                        message = (supleSpeedAdmin.strings && supleSpeedAdmin.strings.error) || 'An error occurred';
                    }

                    if (errorCallback) {
                        errorCallback(message);
                    } else {
                        SupleSpeedAdmin.showNotice('error', message);
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
            const $results = $('#handles-detected');
            const scanningText = (supleSpeedAdmin.strings && supleSpeedAdmin.strings.scanningHandles) || 'Scanning handles...';

            $button.html('<span class="suple-spinner"></span> ' + supleSpeedAdmin.strings.processing);
            $button.prop('disabled', true);

            if ($results.length) {
                $results.addClass('suple-loading');
                $results.html('<p class="suple-muted"><span class="suple-spinner"></span> ' + SupleSpeedAdmin.escapeHtml(scanningText) + '</p>');
            }

            SupleSpeedAdmin.ajaxRequest('scan_handles', {
                scan_url: scanUrl
            }, function(data) {
                $button.text(originalText);
                $button.prop('disabled', false);

                if ($results.length) {
                    $results.removeClass('suple-loading');
                }

                // Mostrar resultados de handles
                SupleSpeedAdmin.displayHandlesResults(data);
            }, function(error) {
                $button.text(originalText);
                $button.prop('disabled', false);

                const fallbackError = (supleSpeedAdmin.strings && supleSpeedAdmin.strings.scanHandlesError)
                    || (supleSpeedAdmin.strings && supleSpeedAdmin.strings.error)
                    || 'An error occurred';
                const message = (typeof error === 'string' && error) ? error : fallbackError;

                if ($results.length) {
                    $results.removeClass('suple-loading');
                    $results.html('<p class="suple-error">' + SupleSpeedAdmin.escapeHtml(message) + '</p>');
                }

                SupleSpeedAdmin.showNotice('error', message);
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
            const arraySettings = {};

            $form.find('input, select, textarea').each(function() {
                const $input = $(this);
                const rawName = $input.attr('name');

                if (!rawName) return;

                const isArrayField = rawName.slice(-2) === '[]';
                const name = isArrayField ? rawName.slice(0, -2) : rawName;

                if (isArrayField) {
                    if (!Array.isArray(arraySettings[name])) {
                        arraySettings[name] = [];
                    }

                    if ($input.is(':checkbox')) {
                        if ($input.is(':checked')) {
                            arraySettings[name].push($input.val());
                        }
                    } else {
                        const value = $input.val();

                        if (value === null || typeof value === 'undefined') {
                            return;
                        }

                        const isArrayValue = Array.isArray ? Array.isArray(value) : $.isArray(value);

                        if (isArrayValue) {
                            arraySettings[name] = arraySettings[name].concat(value);
                        } else {
                            arraySettings[name].push(value);
                        }
                    }

                    return;
                }

                if ($input.is(':checkbox')) {
                    settings[name] = $input.is(':checked');
                } else if ($input.is('[multiple]')) {
                    settings[name] = $input.val() || [];
                } else {
                    settings[name] = $input.val();
                }
            });

            $.each(arraySettings, function(key, values) {
                settings[key] = values;
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
            this.assetHandles = data || { css: {}, js: {} };

            const $detected = $('#handles-detected');
            if ($detected.length) {
                $detected.removeClass('suple-loading');
                let html = '';
                const labels = supleSpeedAdmin.labels || {};
                const groupPrefix = labels.groupPrefix || 'Group';
                const self = this;

                if (data && data.css && Object.keys(data.css).length > 0) {
                    html += '<h4>' + (labels.css || 'CSS') + '</h4>';
                    html += '<table class="suple-table">';
                    html += '<thead><tr>' +
                        '<th>' + (labels.handle || 'Handle') + '</th>' +
                        '<th>' + (labels.detectedGroup || 'Detected') + '</th>' +
                        '<th>' + (labels.source || 'Source') + '</th>' +
                        '<th>' + (labels.canMerge || 'Can merge') + '</th>' +
                        '</tr></thead>';
                    html += '<tbody>';

                    Object.keys(data.css).forEach(function(handle) {
                        const css = data.css[handle];
                        const badge = css.group ? self.escapeHtml(groupPrefix + ' ' + css.group) : '&mdash;';
                        html += '<tr>' +
                            '<td><code>' + self.escapeHtml(handle) + '</code></td>' +
                            '<td><span class="suple-badge">' + badge + '</span></td>' +
                            '<td><small>' + self.escapeHtml(css.src || 'N/A') + '</small></td>' +
                            '<td>' + (css.can_merge ? '✅' : '❌') + '</td>' +
                            '</tr>';
                    });

                    html += '</tbody></table>';
                }

                if (data && data.js && Object.keys(data.js).length > 0) {
                    html += '<h4>' + (labels.js || 'JS') + '</h4>';
                    html += '<table class="suple-table">';
                    html += '<thead><tr>' +
                        '<th>' + (labels.handle || 'Handle') + '</th>' +
                        '<th>' + (labels.detectedGroup || 'Detected') + '</th>' +
                        '<th>' + (labels.source || 'Source') + '</th>' +
                        '<th>' + (labels.canMerge || 'Can merge') + '</th>' +
                        '<th>' + (labels.canDefer || 'Can defer') + '</th>' +
                        '</tr></thead>';
                    html += '<tbody>';

                    Object.keys(data.js).forEach(function(handle) {
                        const js = data.js[handle];
                        const badge = js.group ? self.escapeHtml(groupPrefix + ' ' + js.group) : '&mdash;';
                        html += '<tr>' +
                            '<td><code>' + self.escapeHtml(handle) + '</code></td>' +
                            '<td><span class="suple-badge">' + badge + '</span></td>' +
                            '<td><small>' + self.escapeHtml(js.src || 'N/A') + '</small></td>' +
                            '<td>' + (js.can_merge ? '✅' : '❌') + '</td>' +
                            '<td>' + (js.can_defer ? '✅' : '❌') + '</td>' +
                            '</tr>';
                    });

                    html += '</tbody></table>';
                }

                if (!html) {
                    const emptyMessage = labels.noHandlesDetected || labels.noHandles || 'No handles detected yet.';
                    html = '<p class="suple-muted">' + self.escapeHtml(emptyMessage) + '</p>';
                }

                $detected.html(html);
            }

            this.renderManualGroupsTable();
        },

        initializeAssetsUI: function() {
            const hasAssetsUI = $('#handles-results').length > 0 || $('#bundle-status').length > 0 || $('#suple-preload-recommendations').length > 0;

            if (!hasAssetsUI) {
                return;
            }

            this.assetGroups = supleSpeedAdmin.assetGroups || {};
            this.manualAssetGroups = $.extend({}, supleSpeedAdmin.manualAssetGroups || {});
            this.bundleStatus = supleSpeedAdmin.bundleStatus || { css: {}, js: {} };
            this.assetHandles = this.assetHandles || { css: {}, js: {} };
            this.manualDirty = false;
            this.needsRegeneration = false;
            this.preloadRecommendations = supleSpeedAdmin.preloadRecommendations || [];

            this.renderManualGroupsTable();
            this.updateBundleStatus(this.bundleStatus);
            this.renderPreloadRecommendations(this.preloadRecommendations);
        },

        renderManualGroupsTable: function() {
            const $container = $('#handles-results');
            if ($container.length === 0) return;

            const labels = supleSpeedAdmin.labels || {};
            const assetGroups = this.assetGroups || {};
            const handlesData = this.assetHandles || { css: {}, js: {} };
            const manualGroups = this.manualAssetGroups || {};
            const rows = {};
            const self = this;
            const groupPrefix = labels.groupPrefix || 'Group';

            const addRow = function(handle, typeLabel, data) {
                const normalized = (handle || '').toString();
                const key = normalized.toLowerCase();
                const existing = rows[key] || {
                    handle: normalized,
                    type: typeLabel,
                    detectedGroup: '',
                    source: '',
                    canMerge: null,
                    canDefer: null
                };

                if (data && typeof data.group !== 'undefined' && data.group) {
                    existing.detectedGroup = data.group;
                }

                if (data && typeof data.src !== 'undefined') {
                    existing.source = data.src || '';
                }

                if (data && typeof data.can_merge !== 'undefined') {
                    existing.canMerge = data.can_merge;
                }

                if (data && typeof data.can_defer !== 'undefined') {
                    existing.canDefer = data.can_defer;
                }

                existing.type = typeLabel;
                rows[key] = existing;
            };

            Object.keys(handlesData.css || {}).forEach(function(handle) {
                addRow(handle, labels.css || 'CSS', handlesData.css[handle]);
            });

            Object.keys(handlesData.js || {}).forEach(function(handle) {
                addRow(handle, labels.js || 'JS', handlesData.js[handle]);
            });

            Object.keys(manualGroups).forEach(function(handle) {
                if (!rows[handle]) {
                    rows[handle] = {
                        handle: handle,
                        type: labels.manual || 'Manual',
                        detectedGroup: '',
                        source: '',
                        canMerge: null,
                        canDefer: null
                    };
                } else {
                    rows[handle].type = rows[handle].type || labels.manual || 'Manual';
                }
            });

            const keys = Object.keys(rows);

            if (keys.length === 0) {
                $container.html('<p>' + (labels.noHandles || '') + '</p>');
                return;
            }

            keys.sort();

            let html = '<table class="suple-table">';
            html += '<thead><tr>' +
                '<th>' + (labels.handle || 'Handle') + '</th>' +
                '<th>' + (labels.type || 'Type') + '</th>' +
                '<th>' + (labels.detectedGroup || 'Detected group') + '</th>' +
                '<th>' + (labels.manualGroup || 'Manual group') + '</th>' +
                '<th>' + (labels.source || 'Source') + '</th>' +
                '<th>' + (labels.canMerge || 'Can merge') + '</th>' +
                '<th>' + (labels.canDefer || 'Can defer') + '</th>' +
                '</tr></thead><tbody>';

            keys.forEach(function(key) {
                const row = rows[key];
                const manualValue = manualGroups[key] || '';
                const groupLabel = row.detectedGroup ? self.escapeHtml(groupPrefix + ' ' + row.detectedGroup) : '&mdash;';
                const source = row.source ? '<small>' + self.escapeHtml(row.source) + '</small>' : '&mdash;';
                let manualSelect = '<select class="suple-handle-group-select" data-handle="' + self.escapeHtml(row.handle) + '">';
                manualSelect += '<option value="">' + (labels.auto || 'Automatic') + '</option>';

                Object.keys(assetGroups).forEach(function(groupKey) {
                    const isSelected = manualValue === groupKey ? ' selected' : '';
                    const optionLabel = groupPrefix + ' ' + groupKey + (assetGroups[groupKey] ? ' · ' + assetGroups[groupKey] : '');
                    manualSelect += '<option value="' + groupKey + '"' + isSelected + '>' + self.escapeHtml(optionLabel) + '</option>';
                });

                manualSelect += '</select>';

                html += '<tr>' +
                    '<td><code>' + self.escapeHtml(row.handle) + '</code></td>' +
                    '<td>' + self.escapeHtml(row.type) + '</td>' +
                    '<td><span class="suple-badge">' + groupLabel + '</span></td>' +
                    '<td>' + manualSelect + '</td>' +
                    '<td>' + source + '</td>' +
                    '<td>' + (row.canMerge === null ? '&mdash;' : (row.canMerge ? '✅' : '❌')) + '</td>' +
                    '<td>' + (row.canDefer === null ? '&mdash;' : (row.canDefer ? '✅' : '❌')) + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';

            $container.html(html);

            $('.suple-regenerate-bundles').prop('disabled', !this.needsRegeneration);
        },

        renderPreloadRecommendations: function(list) {
            const $container = $('#suple-preload-recommendations');
            if ($container.length === 0) return;

            const labels = supleSpeedAdmin.labels || {};
            const strings = supleSpeedAdmin.strings || {};
            const emptyMessage = $container.data('emptyMessage') || strings.preloadNoSuggestions || '';
            const recommendations = Array.isArray(list) ? list : [];
            this.preloadRecommendations = recommendations;

            if (recommendations.length === 0) {
                const message = emptyMessage ? this.escapeHtml(emptyMessage) : '';
                $container.html(message ? '<p class="suple-muted">' + message + '</p>' : '');
                return;
            }

            let html = '<table class="suple-table">';
            html += '<thead><tr>' +
                '<th>' + this.escapeHtml(labels.type || 'Type') + '</th>' +
                '<th>' + this.escapeHtml(labels.resource || 'Resource') + '</th>' +
                '<th>' + this.escapeHtml(labels.size || 'Size') + '</th>' +
                '<th>' + this.escapeHtml(labels.seenOn || 'Seen on') + '</th>' +
                '<th>' + this.escapeHtml(labels.position || 'Position') + '</th>' +
                '<th>' + this.escapeHtml(labels.actions || 'Actions') + '</th>' +
                '</tr></thead><tbody>';

            const self = this;

            recommendations.forEach(function(rec) {
                const id = self.escapeHtml(rec.id || '');
                const type = rec.type || rec.as || '';
                const typeLabel = type ? self.escapeHtml(type.charAt(0).toUpperCase() + type.slice(1)) : self.escapeHtml(strings.unknown || 'Unknown');
                const url = rec.url ? self.escapeHtml(rec.url) : '';
                const size = typeof rec.size === 'number' && rec.size > 0 ? self.escapeHtml(self.formatBytes(rec.size)) : '&mdash;';
                const pages = Array.isArray(rec.pages) ? rec.pages : [];
                const crossoriginLabel = strings.crossorigin || 'crossorigin';
                const crossorigin = rec.crossorigin ? '<br><small>' + self.escapeHtml(crossoriginLabel + ': ' + rec.crossorigin) + '</small>' : '';
                const position = rec.position ? '#' + self.escapeHtml(rec.position) : '&mdash;';

                let pagesHtml = '&mdash;';
                if (pages.length > 0) {
                    pagesHtml = pages.map(function(page) {
                        const escaped = self.escapeHtml(page);
                        return '<div><a href="' + escaped + '" target="_blank" rel="noopener">' + escaped + '</a></div>';
                    }).join('');
                }

                html += '<tr data-id="' + id + '">' +
                    '<td><span class="suple-badge">' + typeLabel + '</span>' + crossorigin + '</td>' +
                    '<td>' + (url ? '<code>' + url + '</code>' : '&mdash;') + '</td>' +
                    '<td>' + size + '</td>' +
                    '<td>' + pagesHtml + '</td>' +
                    '<td>' + position + '</td>' +
                    '<td>' +
                        '<div class="suple-flex suple-gap-0-5 suple-flex-wrap">' +
                            '<button type="button" class="suple-button success suple-accept-preload" data-id="' + id + '"><span class="dashicons dashicons-upload"></span> ' + self.escapeHtml(strings.addPreload || 'Add preload') + '</button>' +
                            '<button type="button" class="suple-button secondary suple-reject-preload" data-id="' + id + '"><span class="dashicons dashicons-no-alt"></span> ' + self.escapeHtml(strings.dismiss || 'Dismiss') + '</button>' +
                        '</div>' +
                    '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';

            $container.html(html);
        },

        runPreloadCollector: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            if ($button.prop('disabled')) return;

            const strings = supleSpeedAdmin.strings || {};
            const originalHtml = $button.html();
            const loadingText = strings.preloadCollecting || strings.processing || 'Processing…';

            $button.data('originalHtml', originalHtml);
            $button.prop('disabled', true).html('<span class="suple-spinner"></span> ' + this.escapeHtml(loadingText));

            const self = this;

            this.ajaxRequest('generate_preload_recommendations', {}, function(response) {
                const message = response && response.message ? response.message : (strings.success || 'Done');
                $button.prop('disabled', false).html(originalHtml);

                if (response && response.recommendations) {
                    self.renderPreloadRecommendations(response.recommendations);
                }

                self.showNotice('success', message);
            }, function(error) {
                const errorMessage = error || strings.preloadCollectorError || strings.error || 'Error';
                $button.prop('disabled', false).html(originalHtml);
                self.showNotice('error', errorMessage);
            });
        },

        refreshPreloadRecommendations: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            if ($button.prop('disabled')) return;

            const strings = supleSpeedAdmin.strings || {};
            const originalHtml = $button.html();

            $button.data('originalHtml', originalHtml);
            $button.prop('disabled', true).html('<span class="suple-spinner"></span> ' + this.escapeHtml(strings.processing || 'Processing…'));

            const self = this;

            this.ajaxRequest('get_preload_recommendations', {}, function(response) {
                $button.prop('disabled', false).html(originalHtml);

                if (response && response.recommendations) {
                    self.renderPreloadRecommendations(response.recommendations);
                }
            }, function(error) {
                const errorMessage = error || strings.error || 'Error';
                $button.prop('disabled', false).html(originalHtml);
                self.showNotice('error', errorMessage);
            });
        },

        acceptPreloadRecommendation: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            if ($button.prop('disabled')) return;

            const id = $button.data('id');
            if (!id) return;

            const strings = supleSpeedAdmin.strings || {};
            const originalHtml = $button.html();

            $button.prop('disabled', true).html('<span class="suple-spinner"></span>');

            const self = this;

            this.ajaxRequest('accept_preload_recommendation', { id: id }, function(response) {
                $button.prop('disabled', false).html(originalHtml);

                if (response && response.recommendations) {
                    self.renderPreloadRecommendations(response.recommendations);
                }

                const message = response && response.message ? response.message : (strings.preloadAcceptSuccess || strings.success || 'Saved');
                self.showNotice('success', message);
            }, function(error) {
                const errorMessage = error || strings.error || 'Error';
                $button.prop('disabled', false).html(originalHtml);
                self.showNotice('error', errorMessage);
            });
        },

        rejectPreloadRecommendation: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            if ($button.prop('disabled')) return;

            const id = $button.data('id');
            if (!id) return;

            const strings = supleSpeedAdmin.strings || {};
            const originalHtml = $button.html();

            $button.prop('disabled', true).html('<span class="suple-spinner"></span>');

            const self = this;

            this.ajaxRequest('reject_preload_recommendation', { id: id }, function(response) {
                $button.prop('disabled', false).html(originalHtml);

                if (response && response.recommendations) {
                    self.renderPreloadRecommendations(response.recommendations);
                }

                const message = response && response.message ? response.message : (strings.preloadDismissed || strings.success || 'Done');
                self.showNotice('success', message);
            }, function(error) {
                const errorMessage = error || strings.error || 'Error';
                $button.prop('disabled', false).html(originalHtml);
                self.showNotice('error', errorMessage);
            });
        },

        onManualGroupChange: function(e) {
            const $select = $(e.currentTarget);
            const handle = ($select.data('handle') || '').toString().toLowerCase();
            const value = $select.val();

            if (!handle) {
                return;
            }

            if (value) {
                this.manualAssetGroups[handle] = value;
            } else {
                delete this.manualAssetGroups[handle];
            }

            this.manualDirty = true;
            this.needsRegeneration = false;
            $('.suple-regenerate-bundles').prop('disabled', true);
        },

        saveManualGroups: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const originalHtml = $button.html();
            const manualGroups = {};
            const self = this;

            $('.suple-handle-group-select').each(function() {
                const $select = $(this);
                const handle = ($select.data('handle') || '').toString().toLowerCase();
                const value = $select.val();

                if (handle && value) {
                    manualGroups[handle] = value;
                }
            });

            $button.prop('disabled', true).html('<span class="suple-spinner"></span> ' + (supleSpeedAdmin.strings.processing || 'Processing...'));

            SupleSpeedAdmin.ajaxRequest('save_manual_asset_groups', {
                manual_groups: manualGroups
            }, function(data) {
                $button.prop('disabled', false).html(originalHtml);

                self.manualAssetGroups = data.manual_groups || {};
                self.manualDirty = false;
                self.needsRegeneration = !!data.needs_regeneration;
                self.updateBundleStatus(data.bundles || {});
                self.renderManualGroupsTable();

                if (self.needsRegeneration) {
                    $('.suple-regenerate-bundles').prop('disabled', false);
                }

                SupleSpeedAdmin.showNotice('success', data.message || (supleSpeedAdmin.strings.success || 'Saved'));
            }, function(error) {
                $button.prop('disabled', false).html(originalHtml);
                SupleSpeedAdmin.showNotice('error', error || supleSpeedAdmin.strings.error);
            });
        },

        regenerateBundles: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            if ($button.is(':disabled')) {
                return;
            }

            const originalHtml = $button.html();
            const self = this;

            $button.prop('disabled', true).html('<span class="suple-spinner"></span> ' + (supleSpeedAdmin.strings.processing || 'Processing...'));

            SupleSpeedAdmin.ajaxRequest('regenerate_asset_bundles', {}, function(data) {
                $button.html(originalHtml);
                self.needsRegeneration = false;
                self.updateBundleStatus(data.bundles || {});
                SupleSpeedAdmin.showNotice('success', data.message || (supleSpeedAdmin.strings.success || 'Done'));
            }, function(error) {
                $button.prop('disabled', false).html(originalHtml);
                SupleSpeedAdmin.showNotice('error', error || supleSpeedAdmin.strings.error);
            });
        },

        updateBundleStatus: function(bundles) {
            const $container = $('#bundle-status');
            if ($container.length === 0) return;

            const labels = supleSpeedAdmin.labels || {};
            const self = this;
            const groupPrefix = labels.groupPrefix || 'Group';

            this.bundleStatus = bundles && typeof bundles === 'object' ? bundles : { css: {}, js: {} };

            let hasBundles = false;
            Object.keys(this.bundleStatus).forEach(function(type) {
                const groups = self.bundleStatus[type];
                if (groups && Object.keys(groups).length > 0) {
                    const total = Object.values(groups).reduce(function(count, list) {
                        return count + (Array.isArray(list) ? list.length : 0);
                    }, 0);

                    if (total > 0) {
                        hasBundles = true;
                    }
                }
            });

            if (!hasBundles) {
                $container.html('<p>' + (labels.noBundles || '') + '</p>');
                return;
            }

            let html = '<table class="suple-table">';
            html += '<thead><tr>' +
                '<th>' + (labels.bundlesType || 'Type') + '</th>' +
                '<th>' + (labels.bundlesGroup || 'Group') + '</th>' +
                '<th>' + (labels.bundlesIdentifier || 'Identifier') + '</th>' +
                '<th>' + (labels.bundlesVersion || 'Version') + '</th>' +
                '<th>' + (labels.bundlesGenerated || 'Generated') + '</th>' +
                '<th>' + (labels.bundlesHandles || 'Handles') + '</th>' +
                '<th>' + (labels.bundlesSize || 'Size') + '</th>' +
                '</tr></thead><tbody>';

            ['css', 'js'].forEach(function(type) {
                const groups = self.bundleStatus[type] || {};
                Object.keys(groups).forEach(function(groupKey) {
                    (groups[groupKey] || []).forEach(function(bundle) {
                        const groupLabel = self.assetGroups[bundle.group] || '';
                        const identifier = self.escapeHtml(bundle.identifier || '');
                        const version = bundle.version || '';
                        const created = bundle.created ? self.formatDate(bundle.created) : '';
                        const handles = Array.isArray(bundle.handles) ? bundle.handles : [];
                        const handlesCount = handles.length;
                        const handlesHtml = handlesCount > 0 ? '<br><small>' + self.escapeHtml(handles.join(', ')) + '</small>' : '';

                        html += '<tr>' +
                            '<td>' + self.escapeHtml(type === 'css' ? (labels.css || 'CSS') : (labels.js || 'JS')) + '</td>' +
                            '<td><span class="suple-badge">' + self.escapeHtml(groupPrefix + ' ' + bundle.group + (groupLabel ? ' · ' + groupLabel : '')) + '</span></td>' +
                            '<td><code>' + identifier + '</code></td>' +
                            '<td>' + version + '</td>' +
                            '<td>' + self.escapeHtml(created) + '</td>' +
                            '<td>' + handlesCount + handlesHtml + '</td>' +
                            '<td>' + self.formatBytes(bundle.size || 0) + '</td>' +
                            '</tr>';
                    });
                });
            });

            html += '</tbody></table>';

            $container.html(html);
        },

        formatDate: function(timestamp) {
            const date = new Date(parseInt(timestamp, 10) * 1000);

            if (isNaN(date.getTime())) {
                return '';
            }

            return date.toLocaleString();
        },

        formatBytes: function(bytes) {
            let value = parseInt(bytes, 10);

            if (!value) {
                return '0 B';
            }

            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let unitIndex = 0;

            while (value >= 1024 && unitIndex < units.length - 1) {
                value = value / 1024;
                unitIndex++;
            }

            const precision = unitIndex === 0 ? 0 : 1;
            return value.toFixed(precision) + ' ' + units[unitIndex];
        },

        escapeHtml: function(value) {
            if (typeof value !== 'string') {
                return value;
            }

            return value.replace(/[&<>"']/g, function(match) {
                const entities = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                };

                return entities[match] || match;
            });
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

        autoSave: function($form) {
            const autoSaveEnabled = typeof supleSpeedAdmin === 'undefined' || supleSpeedAdmin.autoSaveEnabled !== false;
            if (!autoSaveEnabled) return;

            const $targetForm = $form && $form.length ? $form : $('.suple-auto-save');
            const formAutoSave = $targetForm.data('auto-save');
            if ($targetForm.length === 0 || formAutoSave === false || formAutoSave === 'false') return;

            const settings = {};
            const arraySettings = {};

            $targetForm.find('input, select, textarea').each(function() {
                const $input = $(this);
                const rawName = $input.attr('name');

                if (!rawName) return;

                const isArrayField = rawName.slice(-2) === '[]';
                const name = isArrayField ? rawName.slice(0, -2) : rawName;

                if (isArrayField) {
                    if (!Array.isArray(arraySettings[name])) {
                        arraySettings[name] = [];
                    }

                    if ($input.is(':checkbox')) {
                        if ($input.is(':checked')) {
                            arraySettings[name].push($input.val());
                        }
                    } else {
                        const value = $input.val();

                        if (value === null || typeof value === 'undefined') {
                            return;
                        }

                        const isArrayValue = Array.isArray ? Array.isArray(value) : $.isArray(value);

                        if (isArrayValue) {
                            arraySettings[name] = arraySettings[name].concat(value);
                        } else {
                            arraySettings[name].push(value);
                        }
                    }

                    return;
                }

                if ($input.is(':checkbox')) {
                    settings[name] = $input.is(':checked');
                } else if ($input.is('[multiple]')) {
                    settings[name] = $input.val() || [];
                } else {
                    settings[name] = $input.val();
                }
            });

            $.each(arraySettings, function(key, values) {
                settings[key] = values;
            });

            let $indicator = $targetForm.data('autoSaveIndicator');
            if (!$indicator || !$indicator.length) {
                $indicator = $('<span class="suple-auto-save-indicator"></span>').hide();
                $('body').append($indicator);
                $targetForm.data('autoSaveIndicator', $indicator);
            }

            const previousTimer = $targetForm.data('autoSaveIndicatorTimer');
            if (previousTimer) {
                clearTimeout(previousTimer);
                $targetForm.removeData('autoSaveIndicatorTimer');
            }

            const setIndicatorState = function(text, state) {
                $indicator.stop(true, true);
                $indicator.removeClass('success error');

                if (state === 'success') {
                    $indicator.addClass('success');
                } else if (state === 'error') {
                    $indicator.addClass('error');
                }

                $indicator.text(text).fadeIn(150);
            };

            const savingText = (supleSpeedAdmin.strings && supleSpeedAdmin.strings.processing) || 'Saving...';
            setIndicatorState(savingText, null);

            SupleSpeedAdmin.ajaxRequest('save_settings', {
                settings: settings
            }, function(data) {
                const message = data && data.message ? data.message : ((supleSpeedAdmin.strings && supleSpeedAdmin.strings.success) || 'Saved');
                setIndicatorState(message, 'success');

                const hideTimeout = setTimeout(function() {
                    $indicator.fadeOut(200);
                }, 2000);

                $targetForm.data('autoSaveIndicatorTimer', hideTimeout);
            }, function(error) {
                const message = (typeof error === 'string' && error) ? error : ((supleSpeedAdmin.strings && supleSpeedAdmin.strings.error) || 'An error occurred');
                setIndicatorState(message, 'error');
                SupleSpeedAdmin.showNotice('error', message);
            });
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

        initOnboarding: function() {
            const $guide = $('.suple-onboarding');
            if ($guide.length === 0) {
                return;
            }

            $(document).on('change', '.suple-onboarding-step', function() {
                const $checkbox = $(this);
                const step = $checkbox.data('step');

                if (!step) {
                    return;
                }

                const completed = $checkbox.is(':checked');
                const previousState = !completed;
                const $card = $checkbox.closest('.suple-onboarding-card');
                const $container = $card.closest('.suple-onboarding');

                $card.toggleClass('completed', completed);
                $card.addClass('loading');
                $checkbox.prop('disabled', true);

                SupleSpeedAdmin.ajaxRequest('update_onboarding', {
                    step: step,
                    completed: completed ? 1 : 0
                }, function(data) {
                    $checkbox.prop('disabled', false);
                    $card.removeClass('loading');
                    $card.toggleClass('completed', completed);

                    if (data) {
                        SupleSpeedAdmin.updateOnboardingProgress($container, data);
                    }
                }, function(error) {
                    $checkbox.prop('disabled', false);
                    $card.removeClass('loading');
                    $checkbox.prop('checked', previousState);
                    $card.toggleClass('completed', previousState);

                    const errorMessage = (error && error.message)
                        ? error.message
                        : (typeof error === 'string' && error)
                            ? error
                            : (supleSpeedAdmin.strings && supleSpeedAdmin.strings.error) || 'An error occurred';

                    SupleSpeedAdmin.showNotice('error', errorMessage);
                });
            });
        },

        updateOnboardingProgress: function($container, data) {
            if (!$container || $container.length === 0) {
                return;
            }

            const total = data.total || parseInt($container.attr('data-total'), 10) || 0;
            const completed = data.completed || 0;
            const progress = data.progress || 0;

            $container.attr('data-total', total);
            $container.attr('data-completed', completed);

            const $count = $container.find('.suple-onboarding-progress-count');
            if ($count.length) {
                $count.text(completed + '/' + total);
            }

            const $progressFill = $container.find('.suple-onboarding-progress-bar-fill');
            if ($progressFill.length) {
                $progressFill.css('width', progress + '%');
            }

            const $progressLabel = $container.find('.suple-onboarding-progress-label');
            if ($progressLabel.length) {
                $progressLabel.text(progress + '%');
            }

            const $status = $container.find('.suple-onboarding-status');
            if ($status.length) {
                if (data.remaining_critical && data.remaining_critical.length > 0) {
                    const template = $status.data('warning-template');
                    const labels = (data.remaining_labels && data.remaining_labels.length > 0)
                        ? data.remaining_labels.join(', ')
                        : data.remaining_critical.join(', ');

                    const message = template
                        ? template.replace('%1$s', data.remaining_critical.length).replace('%2$s', labels)
                        : labels;

                    $status.removeClass('success').addClass('warning').text(message);
                } else {
                    const successText = $status.data('success-text') || '';
                    $status.removeClass('warning').addClass('success').text(successText);
                }
            }
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