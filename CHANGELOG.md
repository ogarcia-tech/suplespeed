# Changelog

All notable changes to Suple Speed will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-01-15

### Added
- **Initial release** of Suple Speed - Optimización Inteligente
- **Page Cache System**: Disk-based caching with intelligent purging
  - Automatic cache variations by device (mobile/desktop) and language
  - Smart purge on content updates (posts, pages, taxonomies)
  - Cloudflare and reverse proxy compatibility
  - Cache warm-up from sitemap
- **Intelligent Asset Optimization**: 
  - CSS/JS merging respecting WordPress dependencies
  - Asset grouping by categories (Core/Theme, Plugins, Elementor, Third-party)
  - Safe minification with fallbacks
  - Smart defer/async without breaking functionality
  - Test mode for administrators
  - Binary detector for problematic files
- **Elementor-Aware Optimization**:
  - Automatic detection of editor and preview modes
  - Full respect for Elementor order and dependencies
  - Protection of critical inline styles
  - Motion effects and animations compatibility
  - Per-page CSS from Elementor protected
- **Critical CSS and Preloads**:
  - Critical CSS by template or specific URL
  - Non-blocking loading of non-critical CSS with loadCSS
  - Selective preload of critical assets
  - Preconnection network: preconnect, dns-prefetch, prefetch
  - Font optimization with woff2 preload
- **Google Fonts Localization**:
  - Automatic detection and download of Google Fonts
  - Local hosting for better performance and privacy
  - Automatic font-display: swap
  - Intelligent preload of critical fonts
  - Detection and conversion of @import and links
- **Image Optimization**:
  - Lazy loading respecting WordPress native implementation
  - Optional LQIP (Low Quality Image Placeholders)
  - Integration with EWWW/WebP Express without duplication
  - WebP/AVIF rewriting when supported
  - Preload of critical images
- **PageSpeed Insights Integration**:
  - API v5 with automatic test execution
  - History and before/after comparisons
  - Automatic suggestions with one-click application
  - Core Web Vitals metrics (LCP, INP, CLS)
  - Scheduled tests and improvement validation
- **Advanced Rules Engine**:
  - Global and URL/page-specific rules
  - Flexible selectors: regex, post type, template, category
  - Granular configuration per rule (cache, merging, critical CSS)
  - Dynamic exclusions by cookies, parameters, roles
  - Priority system and inheritance
- **Complete Observability**:
  - Detailed logging system with rotation
  - Real-time Web Vitals metrics with beacon
  - Per-page reports and global statistics
  - Conflict diagnosis and safe mode
  - Instant rollback on issues
- **Comprehensive Admin Interface**:
  - Modern and intuitive dashboard
  - Real-time statistics and monitoring
  - One-click optimization tools
  - Detailed configuration options
  - Import/export settings functionality
- **WP-CLI Integration**:
  - Complete cache management commands
  - PageSpeed Insights automation
  - Font localization tools
  - Cache warm-up utilities
  - Configuration management
- **Compatibility System**:
  - Automatic detection of popular plugins
  - Specific compatibility rules for Elementor, WooCommerce, WPML/Polylang
  - Conflict warnings and resolution suggestions
  - Safe mode for maximum compatibility
- **Multisite Support**:
  - Network-wide settings option
  - Per-site configuration capability
  - Centralized management for large networks

### Security
- Proper nonce verification for all AJAX calls
- Capability checks for administrative functions
- Input sanitization and validation
- SQL injection prevention
- XSS protection in admin interfaces

### Performance
- Minimal overhead when optimizations are active
- Efficient caching mechanisms
- Optimized database queries
- Lazy loading of admin assets
- Memory-conscious operations

### Developer Features
- Extensive filter system for customization
- Action hooks for integration
- PSR-4 autoloading
- Comprehensive error handling
- Debug mode for development

### Documentation
- Complete README with usage examples
- Inline code documentation
- WP-CLI command documentation
- Troubleshooting guide
- Best practices recommendations

---

## Development Roadmap

### [1.1.0] - Planned Features
- **Enhanced Critical CSS Generation**: Automatic critical CSS generation tool
- **Advanced Image Processing**: AVIF generation and serving
- **Database Optimization**: Query caching and optimization
- **Enhanced CDN Integration**: Pull zone management and edge cache purging
- **Performance Monitoring**: Advanced analytics dashboard
- **A/B Testing Framework**: Built-in performance testing tools

### [1.2.0] - Future Enhancements
- **Mobile-First Optimization**: Specific mobile performance optimizations
- **Progressive Web App Features**: Service worker integration
- **Advanced Caching Strategies**: Edge-side includes and fragment caching
- **Machine Learning Optimization**: AI-powered optimization suggestions
- **Third-Party Integration**: Enhanced compatibility with popular themes and plugins

---

## Support and Feedback

We welcome feedback and bug reports. Please use the following channels:

- **Documentation**: [Complete documentation](https://suple.com/speed/docs)
- **Support Forum**: [Community support](https://suple.com/speed/forum)
- **Bug Reports**: [GitHub Issues](https://github.com/suple/suple-speed/issues)
- **Feature Requests**: [GitHub Discussions](https://github.com/suple/suple-speed/discussions)

## Contributing

Contributions are welcome! Please read our contributing guidelines and submit pull requests for any improvements.

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.