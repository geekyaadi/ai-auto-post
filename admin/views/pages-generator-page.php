<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$site_name = get_option( 'aap_site_name', get_bloginfo( 'name' ) );
$site_url  = get_option( 'aap_site_url', home_url( '/' ) );
$email     = get_option( 'aap_contact_email', get_bloginfo( 'admin_email' ) );

$cookie_enable        = get_option( 'aap_cookie_enable', '1' );
$cookie_style         = get_option( 'aap_cookie_style', 'bottom_banner' );
$cookie_text          = get_option( 'aap_cookie_text', 'We use cookies to personalize content, ads, and analyze traffic for maximum user experience.' );
$cookie_btn_text      = get_option( 'aap_cookie_btn_text', 'Accept All Cookies 🍪' );
$cookie_enable_reject = get_option( 'aap_cookie_enable_reject', '0' );
$cookie_reject_text   = get_option( 'aap_cookie_reject_btn_text', 'Decline Non-Essential' );
$cookie_privacy_url   = get_option( 'aap_cookie_privacy_url', home_url('/privacy-policy/') );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$pages_generated = isset( $_GET['pages_created'] ) ? (int) $_GET['pages_created'] : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$cookie_saved    = isset( $_GET['cookie_updated'] ) && $_GET['cookie_updated'] === 'true';
?>

<div class="aap-wrap">
    <!-- Header -->
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">📄 Pages & Cookie Consent</span>
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
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-pages') ); ?>" class="aap-nav-link active">Pages Generator</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-redirects') ); ?>" class="aap-nav-link">Redirect</a>
        <a href="<?php echo esc_url( admin_url('admin.php?page=aap-randomizer') ); ?>" class="aap-nav-link">Date Randomizer</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-codes') ); ?>" class="aap-nav-link">Codes</a>
    <a href="<?php echo esc_url( admin_url('admin.php?page=aap-settings') ); ?>" class="aap-nav-link">Settings</a>
</div>
        </div>
    </div>

    <div class="aap-content">
        <?php if ( $pages_generated > 0 ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>🎉 Successfully Generated & Published <?php echo (int) $pages_generated; ?> Essential Pages!</strong> All selected pages (About, Contact, Privacy Policy, Terms, Disclaimer, DMCA, Editorial) are now live on your site.
            </div>
        <?php endif; ?>

        <?php if ( $cookie_saved ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>✅ Cookie Consent Banner Settings Saved Successfully!</strong>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Essential & Legal Pages Generator -->
        <div class="aap-panel" style="margin-bottom:25px;">
            <div class="aap-panel-header" style="padding-bottom:12px; border-bottom:1px solid #e2e8f0; margin-bottom:18px;">
                <h2 class="aap-panel-title" style="color:var(--aap-text-dark); margin:0 0 4px 0; font-size:16px; font-weight:600;">📄 One-Click Essential &amp; Legal Pages Generator</h2>
                <p style="color:var(--aap-text-muted); margin:0; font-size:13px;">Instantly generate 1000+ words, GDPR/AdSense compliant human-written pages tailored for your website.</p>
            </div>
            <div class="aap-panel-body">
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <?php wp_nonce_field('aap_generate_essential_pages'); ?>
                    <input type="hidden" name="action" value="aap_generate_essential_pages">

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:25px;">
                        <div>
                            <label style="font-weight:600; display:block; margin-bottom:6px;">Website / Business Name</label>
                            <input type="text" name="aap_site_name" value="<?php echo esc_attr($site_name); ?>" class="regular-text" style="width:100%; font-size:14px;" required placeholder="My Awesome Website">
                        </div>
                        <div>
                            <label style="font-weight:600; display:block; margin-bottom:6px;">Website Full URL</label>
                            <input type="url" name="aap_site_url" value="<?php echo esc_url($site_url); ?>" class="regular-text" style="width:100%; font-size:14px;" required placeholder="https://mywebsite.com">
                        </div>
                        <div>
                            <label style="font-weight:600; display:block; margin-bottom:6px;">Official Contact Email</label>
                            <input type="email" name="aap_contact_email" value="<?php echo esc_attr($email); ?>" class="regular-text" style="width:100%; font-size:14px;" required placeholder="contact@mywebsite.com">
                        </div>
                    </div>

                    <h3 style="margin-top:20px; margin-bottom:12px; font-size:15px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">Select Pages to Generate:</h3>
                    
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:12px; margin-bottom:25px;">
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="about_us" checked>
                            <span>ℹ️ <strong>About Us</strong> Page</span>
                        </label>
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="contact_us" checked>
                            <span>📬 <strong>Contact Us</strong> Page</span>
                        </label>
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="privacy_policy" checked>
                            <span>🔒 <strong>Privacy Policy</strong> (GDPR &amp; CCPA)</span>
                        </label>
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="disclaimer" checked>
                            <span>⚠️ <strong>Disclaimer</strong> (AdSense &amp; Affiliate)</span>
                        </label>
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="terms_conditions" checked>
                            <span>📜 <strong>Terms and Conditions</strong></span>
                        </label>
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="dmca" checked>
                            <span>🛡️ <strong>DMCA &amp; Copyright Policy</strong></span>
                        </label>
                        <label style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="pages[]" value="editorial_guidelines" checked>
                            <span>🌟 <strong>Editorial Guidelines (E-E-A-T)</strong></span>
                        </label>
                    </div>

                    <button type="submit" class="button button-primary button-large" style="padding:6px 20px; font-size:14px;">
                        🚀 Generate &amp; Publish Selected Pages
                    </button>
                </form>
            </div>
        </div>

        <!-- SECTION 2: Cookie Consent Banner Controller -->
        <div class="aap-panel">
            <div class="aap-panel-header" style="padding-bottom:12px; border-bottom:1px solid #e2e8f0; margin-bottom:18px;">
                <h2 class="aap-panel-title" style="color:var(--aap-text-dark); margin:0 0 4px 0; font-size:16px; font-weight:600;">🍪 Cookie Consent Banner Controller</h2>
                <p style="color:var(--aap-text-muted); margin:0; font-size:13px;">Customize style, layout, text content, and toggle cookie consent popup on your website.</p>
            </div>
            <div class="aap-panel-body" style="padding:24px;">
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <?php wp_nonce_field('aap_save_cookie_settings'); ?>
                    <input type="hidden" name="action" value="aap_save_cookie_settings">

                    <table class="form-table aap-settings-table">
                        <tr>
                            <th scope="row"><label for="aap_cookie_enable">Enable Cookie Consent Banner</label></th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_cookie_enable" name="aap_cookie_enable" value="1" <?php checked($cookie_enable, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Displays a non-intrusive cookie consent popup on the frontend of your website.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="aap_cookie_style">Banner Style & Layout</label></th>
                            <td>
                                <select id="aap_cookie_style" name="aap_cookie_style" class="regular-text">
                                    <option value="bottom_banner" <?php selected($cookie_style, 'bottom_banner'); ?>>📱 Full Width Bottom Banner (Dark Navy)</option>
                                    <option value="floating_card" <?php selected($cookie_style, 'floating_card'); ?>>🎴 Floating Left Card (Clean Light)</option>
                                    <option value="glassmorphism" <?php selected($cookie_style, 'glassmorphism'); ?>>✨ Glassmorphism Floating Bar (Blur FX)</option>
                                    <option value="dark_pill" <?php selected($cookie_style, 'dark_pill'); ?>>💊 Dark Pill Card (Bottom Right)</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="aap_cookie_text">Consent Message Text</label></th>
                            <td>
                                <textarea id="aap_cookie_text" name="aap_cookie_text" rows="3" class="large-text"><?php echo esc_textarea($cookie_text); ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="aap_cookie_btn_text">Accept Button Text</label></th>
                            <td>
                                <input type="text" id="aap_cookie_btn_text" name="aap_cookie_btn_text" value="<?php echo esc_attr($cookie_btn_text); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="aap_cookie_enable_reject">Include Optional Reject / Decline Button</label></th>
                            <td>
                                <label class="aap-switch">
                                    <input type="checkbox" id="aap_cookie_enable_reject" name="aap_cookie_enable_reject" value="1" <?php checked($cookie_enable_reject, '1'); ?>>
                                    <span class="aap-slider round"></span>
                                </label>
                                <p class="description">Enable if you want to show a second "Decline / Reject" button alongside Accept button (e.g. for strict EU GDPR rules).</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="aap_cookie_reject_btn_text">Reject Button Text</label></th>
                            <td>
                                <input type="text" id="aap_cookie_reject_btn_text" name="aap_cookie_reject_btn_text" value="<?php echo esc_attr($cookie_reject_text); ?>" class="regular-text" placeholder="Decline Non-Essential">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="aap_cookie_privacy_url">Privacy Policy Page Link</label></th>
                            <td>
                                <input type="url" id="aap_cookie_privacy_url" name="aap_cookie_privacy_url" value="<?php echo esc_url($cookie_privacy_url); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:20px;">
                        <button type="submit" class="aap-btn aap-btn-primary" style="padding:12px 25px; font-size:15px; background:#0f172a; border:none;">💾 Save Cookie Consent Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
