<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$header_code = get_option( 'aap_header_code', '' );
$footer_code = get_option( 'aap_footer_code', '' );
$ads_txt     = get_option( 'aap_ads_txt_content', '' );

$ads_txt_url = home_url( '/ads.txt' );
$saved = isset( $_GET['updated'] ) && $_GET['updated'] === 'true';
?>

<div class="aap-wrap">
    <!-- Header -->
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">💻 Header, Footer & ads.txt</span>
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
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-speed') ); ?>" class="aap-nav-link">Optimizer</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-sitemap') ); ?>" class="aap-nav-link">Sitemap</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-pages') ); ?>" class="aap-nav-link">Pages Generator</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-redirects') ); ?>" class="aap-nav-link">Redirect</a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=aap-randomizer') ); ?>" class="aap-nav-link">Date Randomizer</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-codes') ); ?>" class="aap-nav-link active">Codes</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-settings') ); ?>" class="aap-nav-link">Settings</a>
</div>
        </div>
    </div>

    <div class="aap-content">
        <?php if ( $saved ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>✅ Header, Footer & ads.txt Codes Saved Successfully!</strong> All custom scripts and ads.txt rules are active on your site.
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'aap_codes_nonce' ); ?>
            <input type="hidden" name="action" value="aap_save_custom_codes">

            <div class="aap-two-col">
                <!-- Column 1: Header & Footer Scripts -->
                <div>
                    <div class="aap-panel">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">💻 Custom Header &amp; Footer Code Injector</h2>
                        </div>

                        <div class="aap-field">
                            <label class="aap-label">Header Code (Injected inside &lt;head&gt;)</label>
                            <textarea name="aap_header_code" class="aap-textarea" rows="7" placeholder="e.g. Google Analytics (gtag.js), Google Tag Manager, Meta Pixel, or custom <style> tags..."><?php echo esc_textarea( $header_code ); ?></textarea>
                            <div class="aap-hint">Placed right before <code>&lt;/head&gt;</code> on all frontend site pages.</div>
                        </div>

                        <div class="aap-field">
                            <label class="aap-label">Footer Code (Injected right before &lt;/body&gt;)</label>
                            <textarea name="aap_footer_code" class="aap-textarea" rows="7" placeholder="e.g. Chat widgets, tracking scripts, or custom JavaScript..."><?php echo esc_textarea( $footer_code ); ?></textarea>
                            <div class="aap-hint">Placed right before <code>&lt;/body&gt;</code> on all frontend site pages.</div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: ads.txt Manager -->
                <div>
                    <div class="aap-panel">
                        <div class="aap-panel-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <h2 class="aap-panel-title">💰 Google AdSense ads.txt Manager</h2>
                            <a href="<?php echo esc_url( $ads_txt_url ); ?>" target="_blank" class="button button-secondary button-small">↗️ View Live ads.txt</a>
                        </div>

                        <p style="color:var(--aap-text-muted); font-size:12px; margin-bottom:15px;">
                            Manage your official <code>ads.txt</code> records for Google AdSense, Ezoic, Mediavine, AdX, or setup sellers. This plugin automatically syncs and serves the live <code>ads.txt</code> file at <a href="<?php echo esc_url( $ads_txt_url ); ?>" target="_blank"><?php echo esc_url( $ads_txt_url ); ?></a>.
                        </p>

                        <div class="aap-field">
                            <label class="aap-label">ads.txt Content (1 seller entry per line)</label>
                            <textarea name="aap_ads_txt_content" class="aap-textarea" rows="15" style="font-family:monospace; font-size:12px;" placeholder="google.com, pub-0000000000000000, DIRECT, f00c287aed0ee64b&#10;ezoic.com, 12345, RESELLER"><?php echo esc_textarea( $ads_txt ); ?></textarea>
                            <div class="aap-hint">Example format: <code>google.com, pub-XXXXXXX, DIRECT, f00c287aed0ee64b</code></div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:10px;">
                <button type="submit" class="button button-primary button-large" style="padding:6px 24px; font-size:14px;">💾 Save All Custom Codes &amp; Sync ads.txt</button>
            </div>
        </form>
    </div>
</div>
