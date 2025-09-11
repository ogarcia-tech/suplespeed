# Suple Speed – Optimización Inteligente

Un plugin de optimización avanzado para WordPress, diseñado especialmente para sitios construidos con Elementor. Mejora significativamente las puntuaciones de Google PageSpeed Insights mediante técnicas inteligentes de caché, optimización de assets y compatibilidad total con Elementor.

## ✨ Características Principales

### 🚀 Caché de Página Inteligente
- **Caché a disco** con purga automática e inteligente
- **Variaciones por dispositivo** (móvil/desktop) y idioma
- **Compatibilidad con CDN** (Cloudflare, reverse proxy)
- **Purga selectiva** por URL, post, categoría y taxonomía
- **Warm-up automático** desde sitemap

### 🎯 Optimización de Assets
- **Fusión inteligente** de CSS/JS respetando dependencias de WordPress
- **Agrupación por categorías**: Core/Theme, Plugins, Elementor, Third-party
- **Minificación segura** con fallbacks
- **Defer/Async inteligente** evitando roturas
- **Modo test** para validar antes de aplicar a todos los usuarios
- **Detector binario** para identificar archivos problemáticos

### 🎨 Elementor-Aware
- **Detección automática** del modo editor y preview
- **Respeto total** por orden y dependencias de Elementor
- **Preservación de estilos inline** críticos
- **Compatibilidad con motion effects** y animaciones
- **CSS per-page** de Elementor protegido

### ⚡ Critical CSS y Preloads
- **Critical CSS** por plantilla o URL específica
- **Carga no bloqueante** de CSS no crítico con `loadCSS`
- **Preload selectivo** de assets críticos
- **Red de preconexión**: preconnect, dns-prefetch, prefetch
- **Optimización de fuentes** con preload de woff2

### 🔤 Optimización de Fuentes
- **Localización de Google Fonts** automática
- **Descarga y hosting local** para mejor rendimiento y privacidad
- **Font-display: swap** automático
- **Preload inteligente** de fuentes críticas
- **Detección y conversión** de @import y enlaces

### 🖼️ Optimización de Imágenes
- **Lazy loading** respetando el nativo de WordPress
- **LQIP (Low Quality Image Placeholders)** opcional
- **Integración con EWWW/WebP Express** sin duplicar trabajo
- **Reescritura a WebP/AVIF** cuando hay soporte
- **Preload de imágenes críticas**

### 📊 PageSpeed Insights Integration
- **API v5** con ejecución automática de tests
- **Historial y comparativas** antes/después
- **Sugerencias automáticas** con aplicación un clic
- **Métricas Core Web Vitals** (LCP, INP, CLS)
- **Tests programados** y validación de mejoras

### 🎛️ Motor de Reglas Avanzado
- **Reglas globales** y específicas por URL/página
- **Selectores flexibles**: regex, post type, template, categoría
- **Configuración granular** por regla (caché, fusión, critical CSS)
- **Exclusiones dinámicas** por cookies, parámetros, roles
- **Sistema de prioridades** y herencia

### 🔍 Observabilidad Completa
- **Sistema de logs** detallado con rotación
- **Métricas Web Vitals** en tiempo real con beacon
- **Reporte por página** y estadísticas globales
- **Diagnóstico de conflictos** y modo seguro
- **Rollback instantáneo** ante problemas

## 🛠️ Instalación

1. **Descarga** el archivo ZIP del plugin
2. **Sube** a WordPress vía `Plugins > Añadir nuevo > Subir plugin`
3. **Activa** el plugin
4. **Configura** desde `Suple Speed > Settings`
5. **Obtén API Key** de PageSpeed Insights (opcional pero recomendado)

## ⚙️ Configuración Básica

### 1. Configuración Inicial
```php
// Configuración recomendada para sitios con Elementor
Caché de página: ✅ Habilitado
Optimización de assets: ✅ Habilitado
Compatibilidad Elementor: ✅ Habilitado
Localización de fuentes: ✅ Habilitado
Lazy loading de imágenes: ✅ Habilitado
```

### 2. Grupos de Fusión Recomendados
- **CSS**: Grupos A (Core/Theme) + B (Plugins)
- **JavaScript**: Grupos A (Core/Theme) + B (Plugins)
- **Evitar fusionar**: Grupo C (Elementor) inicialmente

### 3. Configurar PageSpeed Insights
1. Obtén API Key en [Google Cloud Console](https://developers.google.com/speed/docs/insights/v5/get-started)
2. Añádela en `Suple Speed > Settings > PageSpeed Insights`
3. Ejecuta tu primer test desde `Performance`

## 🔧 Configuración del Servidor

### Apache (.htaccess)
```apache
# Reglas generadas automáticamente por Suple Speed
# BEGIN Suple Speed
# Compresión Gzip y Brotli
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain text/html text/xml text/css
    AddOutputFilterByType DEFLATE text/javascript application/javascript
    AddOutputFilterByType DEFLATE application/xml application/json
</IfModule>

# Cache Headers
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
</IfModule>
# END Suple Speed
```

### Nginx
```nginx
# Compresión
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_comp_level 6;
gzip_types text/plain text/css application/json application/javascript;

# Cache Headers
location ~* \.(css|js|png|jpg|jpeg|gif|svg|woff|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}
```

## 🖥️ WP-CLI Commands

### Gestión de Caché
```bash
# Purgar toda la caché
wp suple-speed cache purge --all

# Purgar URL específica
wp suple-speed cache purge --url=https://example.com/page/

# Purgar post específico
wp suple-speed cache purge --post-id=123

# Estadísticas de caché
wp suple-speed cache stats

# Limpiar caché expirada
wp suple-speed cache cleanup
```

### PageSpeed Insights
```bash
# Ejecutar test
wp suple-speed psi --url=https://example.com --strategy=mobile

# Test desktop con salida JSON
wp suple-speed psi --url=https://example.com --strategy=desktop --format=json
```

### Gestión de Fuentes
```bash
# Escanear Google Fonts
wp suple-speed fonts scan

# Localizar fuentes específicas
wp suple-speed fonts localize --urls="https://fonts.googleapis.com/css?family=Open+Sans"

# Estadísticas de fuentes
wp suple-speed fonts stats

# Limpiar fuentes no utilizadas
wp suple-speed fonts cleanup
```

### Warm-up de Caché
```bash
# Warm-up desde sitemap
wp suple-speed warm --sitemap=https://example.com/sitemap.xml

# Warm-up con concurrencia personalizada
wp suple-speed warm --concurrent=5 --delay=2
```

### Gestión de Configuración
```bash
# Ver configuración
wp suple-speed settings get cache_enabled

# Cambiar configuración
wp suple-speed settings set cache_enabled true

# Listar todas las configuraciones
wp suple-speed settings list

# Resetear a valores por defecto
wp suple-speed settings reset
```

## 🎯 Casos de Uso Específicos

### Sitio Elementor + WooCommerce
```php
// Configuración recomendada
Grupos de fusión CSS: A, B (evitar C inicialmente)
Reglas específicas: Sin caché en checkout/carrito
Modo test: Habilitado durante configuración
Elementor compatibility: Habilitado
```

### Sitio Multiidioma (WPML/Polylang)
```php
// Configuración automática
Variaciones de caché: Por idioma habilitado
Parámetros excluidos: lang, language
Critical CSS: Por idioma si es necesario
```

### Sitio de Alto Tráfico
```php
// Optimización máxima
TTL de caché: 48 horas para páginas estáticas
Compresión: Gzip + Brotli habilitados
CDN integration: Cloudflare configurado
Warm-up programado: Diario desde sitemap
```

## 🔍 Resolución de Problemas

### ⚠️ Problemas Comunes

#### 1. Página en Blanco Después de Activar
**Causa**: Conflicto en fusión de assets
**Solución**:
1. Activar "Modo Seguro" desde `Settings`
2. Identificar handle problemático en `Assets > Scan Handles`
3. Añadir a lista de exclusiones
4. Desactivar modo seguro

#### 2. Elementor Editor No Carga
**Causa**: Optimizaciones activas en modo editor
**Solución**:
1. Verificar que "Elementor Compatibility" esté habilitado
2. El plugin detecta automáticamente el modo editor
3. Si persiste, revisar logs en `Logs`

#### 3. Fuentes No Se Localizan
**Causa**: Permisos de escritura o fuentes no detectadas
**Solución**:
1. Verificar permisos de `/wp-content/uploads/suple-speed/`
2. Usar `Fonts > Scan Fonts` para detectar fuentes
3. Localizar manualmente desde el escáner

#### 4. PageSpeed No Mejora
**Causa**: Configuración no óptima o factores externos
**Solución**:
1. Ejecutar test desde `Performance`
2. Revisar sugerencias automáticas
3. Aplicar sugerencias un clic
4. Verificar Critical CSS configurado

### 🛡️ Modo Seguro
Cuando está habilitado:
- ❌ Sin fusión de assets
- ✅ Minificación básica activa
- ✅ Defer leve en JavaScript no crítico
- ✅ Caché de página activo
- ✅ Rollback instantáneo disponible

### 📊 Diagnóstico Avanzado
```bash
# Estado general del plugin
wp suple-speed status

# Logs detallados (últimos 50)
wp suple-speed settings get log_level
wp suple-speed settings set log_level debug

# Test de assets específicos
wp suple-speed assets scan --url=https://example.com/problematic-page/
```

## 📈 Métricas y Monitorización

### Core Web Vitals Tracking
- **LCP (Largest Contentful Paint)**: < 2.5s
- **INP (Interaction to Next Paint)**: < 200ms  
- **CLS (Cumulative Layout Shift)**: < 0.1

### Beacon de Métricas
```javascript
// Envío automático al endpoint REST
POST /wp-json/suple-speed/v1/vitals
{
  "url": "https://example.com/page/",
  "lcp": 1200,
  "inp": 150,
  "cls": 0.05
}
```

## 🔗 Integraciones

### Plugins Compatibles
- ✅ **Elementor / Elementor Pro**: Compatibilidad completa
- ✅ **WooCommerce**: Reglas específicas incluidas
- ✅ **WPML / Polylang**: Caché por idioma
- ✅ **Yoast SEO / RankMath**: Sin conflictos
- ✅ **Contact Form 7**: Assets protegidos
- ✅ **EWWW Image Optimizer**: Integración WebP
- ✅ **WebP Express**: Detección automática

### CDN y Hosting
- ✅ **Cloudflare**: Purga automática con API
- ✅ **MaxCDN / KeyCDN**: Headers compatibles
- ✅ **WP Engine**: Configuración específica
- ✅ **SiteGround**: Optimizaciones complementarias
- ✅ **Kinsta**: Cache adicional sin conflictos

## 📝 Changelog

### v1.0.0 (Initial Release)
- ✨ Caché de página a disco con purga inteligente
- ✨ Fusión de assets por dependencias de WordPress
- ✨ Compatibilidad total con Elementor
- ✨ Localización de Google Fonts
- ✨ Integración PageSpeed Insights API v5
- ✨ Motor de reglas globales y por página
- ✨ Sistema de logs y observabilidad
- ✨ WP-CLI commands completos
- ✨ Interfaz de administración completa
- ✨ Optimización de imágenes con lazy loading
- ✨ Critical CSS y sistema de preloads

## 🤝 Soporte

### Documentación
- 📖 [Documentación completa](https://suple.com/speed/docs)
- 🎥 [Videos tutoriales](https://suple.com/speed/videos)  
- 💬 [Foro de soporte](https://suple.com/speed/forum)

### Reportar Issues
1. **Activar logs debug**: `Settings > Advanced > Log Level: Debug`
2. **Reproducir el problema**
3. **Exportar configuración**: `Settings > Advanced > Export`
4. **Enviar** logs + configuración + descripción detallada

### Desarrollo
- 🔧 [GitHub Repository](https://github.com/suple/suple-speed)
- 🐛 [Report Issues](https://github.com/suple/suple-speed/issues)
- 💡 [Feature Requests](https://github.com/suple/suple-speed/discussions)

## 📄 Licencia

GPL v2 or later. Ver `LICENSE` para detalles completos.

## 👥 Créditos

Desarrollado por **Suple** - Especialistas en optimización de WordPress y Elementor.

**Gracias especiales a**:
- Comunidad WordPress por las APIs estables
- Elementor por la excelente arquitectura
- Google por PageSpeed Insights API
- Todos los beta testers y contributors

---

**¿Te gusta Suple Speed?** ⭐ [Danos una calificación](https://wordpress.org/plugins/suple-speed/) y comparte con otros desarrolladores.