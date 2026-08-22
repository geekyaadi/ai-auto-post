<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get Speed Optimizer Options with Defaults
$lazy_loading      = get_option( 'aap_speed_lazy_loading', '1' );
$html_minification = get_option( 'aap_speed_html_minification', '1' );
$webp_compression  = get_option( 'aap_speed_webp_compression', '1' );
$auto_cache_purge  = get_option( 'aap_speed_auto_cache_purge', '1' );
$preload_assets    = get_option( 'aap_speed_preload_assets', '1' );
$defer_js          = get_option( 'aap_speed_defer_js', '1' );

$saved = isset( $_GET['updated'] ) && $_GET['updated'] === 'true';
$purged = isset( $_GET['purged'] ) && $_GET['purged'] === 'true';
?>

<div class="aap-wrap">
    <!-- Header -->
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">⚡ Speed Optimizer</span>
            </div>
                        <div class="aap-header-nav">
    <a href="<?php echo esc_url( admin_url('admin.php?page=ai-auto-post') ); ?>" class="aap-nav-link">Dashboard</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-generate') ); ?>" class="aap-nav-link">Generate Post</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-planner') ); ?>" class="aap-nav-link">Bulk Planner</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-scheduler') ); ?>" class="aap-nav-link">Scheduler</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-thumbnails') ); ?>" class="aap-nav-link">Thumbnail Tool</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-tags') ); ?>" class="aap-nav-link">Tags Tool</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-translator') ); ?>" class="aap-nav-link">Translator</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-gsc') ); ?>" class="aap-nav-link">Indexing</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-rewriter') ); ?>" class="aap-nav-link">Rewriter</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-speed') ); ?>" class="aap-nav-link active">Optimizer</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-sitemap') ); ?>" class="aap-nav-link">Sitemap</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-pages') ); ?>" class="aap-nav-link">Pages Generator</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-redirects') ); ?>" class="aap-nav-link">Redirect</a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=aap-randomizer') ); ?>" class="aap-nav-link">Date Randomizer</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-codes') ); ?>" class="aap-nav-link">Codes</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-settings') ); ?>" class="aap-nav-link">Settings</a>
</div>
        </div>
    </div>

    <div class="aap-content">
        <?php if ( $saved ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>✅ Speed Optimization Settings Saved Successfully!</strong> All active performance rules are now enforced on generated posts.
            </div>
        <?php endif; ?>

        <?php if ( $purged ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>⚡ All Server Caches & Transients Purged!</strong> OPcache, Object Cache, LiteSpeed, WP Rocket, and Autoptimize cleared.
            </div>
        <?php endif; ?>

        <!-- Hero Speed Overview Card -->
        <div class="aap-panel" style="margin-bottom:20px; background: #ffffff; border: 1px solid var(--aap-border);">
            <div class="aap-panel-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <div>
                    <h2 class="aap-panel-title" style="color:var(--aap-text-dark); font-size:20px; margin:0 0 5px 0; display:flex; align-items:center; gap:8px;">
                        <span>⚡</span> AI Auto Post Speed & Performance Turbo Engine
                    </h2>
                    <p style="color:var(--aap-text-muted); margin:0; font-size:13px;">Optimize Core Web Vitals (LCP, CLS, INP) for Google PageSpeed 95+ and instant mobile rendering.</p>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:0;">
                    <?php wp_nonce_field('aap_purge_speed_cache'); ?>
                    <input type="hidden" name="action" value="aap_purge_speed_cache">
                    <button type="submit" class="button button-primary" style="background:#d63638; border-color:#b32d2e; color:#ffffff; padding:6px 16px; font-weight:600; cursor:pointer;">
                        ⚡ Purge All Site Caches Now
                    </button>
                </form>
            </div>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <?php wp_nonce_field('aap_save_speed_settings'); ?>
            <input type="hidden" name="action" value="aap_save_speed_settings">

            <!-- Toolset 1: PageSpeed & Media Optimization -->
            <div class="aap-panel" style="margin-bottom:20px;">
                <div class="aap-panel-header">
                    <h3 class="aap-panel-title">🖼️ Media & Article Content Optimization</h3>
                </div>
                <div class="aap-panel-body">
                    <table class="form-table aap-settings-table">
                        <tr>
                            <th scope="row">
                                <label for="aap_speed_lazy_loading">Native Image Lazy Loading & Priority Hints</label>
                            </th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_speed_lazy_loading" name="aap_speed_lazy_loading" value="1" <?php checked($lazy_loading, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Automatically adds <code>loading="lazy"</code> to inline images/iframes and sets <code>fetchpriority="high"</code> on top featured images to boost LCP (Largest Contentful Paint).</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="aap_speed_webp_compression">Auto WebP Conversion & 82% Compression</label>
                            </th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_speed_webp_compression" name="aap_speed_webp_compression" value="1" <?php checked($webp_compression, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Automatically converts all generated thumbnails and OpenGraph images into lightweight <code>.webp</code> format (saving ~75% disk space and reducing load times).</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="aap_speed_html_minification">HTML Article Code Minification</label>
                            </th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_speed_html_minification" name="aap_speed_html_minification" value="1" <?php checked($html_minification, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Strips redundant whitespaces, double linebreaks, and blank space characters from generated HTML content before saving to WP database.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Toolset 2: Server Cache & Script Execution -->
            <div class="aap-panel" style="margin-bottom:20px;">
                <div class="aap-panel-header">
                    <h3 class="aap-panel-title">🚀 Caching Engine & Script Optimization</h3>
                </div>
                <div class="aap-panel-body">
                    <table class="form-table aap-settings-table">
                        <tr>
                            <th scope="row">
                                <label for="aap_speed_auto_cache_purge">Auto Cache Flush on Publish</label>
                            </th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_speed_auto_cache_purge" name="aap_speed_auto_cache_purge" value="1" <?php checked($auto_cache_purge, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Triggers instant cache clearing for LiteSpeed, WP Rocket, WP Super Cache, Autoptimize, W3 Total Cache & OPcache whenever a new post is auto-published.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="aap_speed_preload_assets">Font & Style Resource Preloading</label>
                            </th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_speed_preload_assets" name="aap_speed_preload_assets" value="1" <?php checked($preload_assets, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Preloads key typography assets and inline TOC/Callout CSS rules to prevent Layout Shifts (CLS 0.00).</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="aap_speed_defer_js">Non-Critical Script Deferring</label>
                            </th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_speed_defer_js" name="aap_speed_defer_js" value="1" <?php checked($defer_js, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Defers non-essential plugin scripts on post pages to prevent render-blocking JavaScript bottlenecks.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Submit Button -->
            <div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
                <button type="submit" class="aap-btn aap-btn-primary" style="padding:12px 25px; font-size:15px;">💾 Save Speed Optimization Tools</button>
            </div>
        </form>
    </div>
</div>
