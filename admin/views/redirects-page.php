<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';
$rules  = AAP_Redirects::get_redirect_rules();
$logs_404 = AAP_Redirects::get_404_logs( $filter );

$updated      = isset( $_GET['updated'] ) && $_GET['updated'] === 'true';
$deleted      = isset( $_GET['deleted'] ) && $_GET['deleted'] === 'true';
$converted    = isset( $_GET['converted'] ) && $_GET['converted'] === 'true';
$logs_cleared = isset( $_GET['logs_cleared'] ) && $_GET['logs_cleared'] === 'true';
?>

<div class="aap-wrap">
    <!-- Header -->
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">🔀 Redirect & 404 Manager</span>
            </div>
            <div class="aap-header-nav">
    <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
    <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
    <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
    <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
    <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Tool</a>
    <a href="<?php echo admin_url('admin.php?page=aap-tags'); ?>" class="aap-nav-link">Tags Tool</a>
    <a href="<?php echo admin_url('admin.php?page=aap-translator'); ?>" class="aap-nav-link">Translator</a>
    <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Indexing</a>
    <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Rewriter</a>
    <a href="<?php echo admin_url('admin.php?page=aap-speed'); ?>" class="aap-nav-link">Optimizer</a>
    <a href="<?php echo admin_url('admin.php?page=aap-sitemap'); ?>" class="aap-nav-link">Sitemap</a>
    <a href="<?php echo admin_url('admin.php?page=aap-pages'); ?>" class="aap-nav-link">Pages Generator</a>
    <a href="<?php echo admin_url('admin.php?page=aap-redirects'); ?>" class="aap-nav-link active">Redirect</a>
    <a href="<?php echo admin_url('admin.php?page=aap-codes'); ?>" class="aap-nav-link">Codes</a>
    <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
</div>
        </div>
    </div>

    <div class="aap-content">
        <?php if ( $updated ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>✅ Redirect Rule Saved Successfully!</strong>
            </div>
        <?php endif; ?>

        <?php if ( $deleted ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>✅ Redirect Rule Deleted.</strong>
            </div>
        <?php endif; ?>

        <?php if ( $converted ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>🎉 404 Error Converted to 301 Permanent Redirect!</strong> Traffic will now be automatically rerouted to your destination URL.
            </div>
        <?php endif; ?>

        <?php if ( $logs_cleared ): ?>
            <div class="aap-alert aap-alert-success" style="margin-bottom:20px;">
                <strong>🧹 404 Error Logs Cleared Successfully.</strong>
            </div>
        <?php endif; ?>

        <div class="aap-two-col">
            <!-- Left Column: Add New Redirect Rule -->
            <div>
                <div class="aap-panel">
                    <div class="aap-panel-header">
                        <h2 class="aap-panel-title">➕ Add New Redirect Rule</h2>
                    </div>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'aap_redirect_nonce' ); ?>
                        <input type="hidden" name="action" value="aap_save_redirect_rule">

                        <div class="aap-field">
                            <label class="aap-label">Source URL (Old Path)</label>
                            <input type="text" name="source_url" class="aap-input" placeholder="/old-article-slug or /broken-page" required>
                            <div class="aap-hint">Relative path or relative URL to intercept.</div>
                        </div>

                        <div class="aap-field">
                            <label class="aap-label">Target URL (Destination)</label>
                            <input type="text" name="target_url" class="aap-input" placeholder="https://yoursite.com/new-article-slug" required>
                            <div class="aap-hint">Full URL or internal relative path where visitors should be redirected.</div>
                        </div>

                        <div class="aap-field">
                            <label class="aap-label">Redirect HTTP Status Code</label>
                            <select name="redirect_type" class="aap-select">
                                <option value="301" selected>301 — Permanent Redirect (Recommended for SEO)</option>
                                <option value="302">302 — Temporary Redirect</option>
                                <option value="307">307 — Temporary Redirect (Strict)</option>
                            </select>
                        </div>

                        <button type="submit" class="button button-primary" style="margin-top:10px;">💾 Save Redirect Rule</button>
                    </form>
                </div>

                <!-- Active Redirect Rules Table -->
                <div class="aap-panel">
                    <div class="aap-panel-header">
                        <h2 class="aap-panel-title">📋 Active Redirect Rules (<?php echo count($rules); ?>)</h2>
                    </div>

                    <?php if ( empty( $rules ) ): ?>
                        <p style="color:#646970; font-size:13px;">No custom redirect rules created yet.</p>
                    <?php else: ?>
                        <table class="widefat striped" style="border:none;">
                            <thead>
                                <tr>
                                    <th>Source Path</th>
                                    <th>Target Destination</th>
                                    <th>Type</th>
                                    <th>Hits</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $rules as $r ): ?>
                                    <tr>
                                        <td><code><?php echo esc_html( $r->source_url ); ?></code></td>
                                        <td><a href="<?php echo esc_url( $r->target_url ); ?>" target="_blank"><?php echo esc_html( $r->target_url ); ?></a></td>
                                        <td><span class="aap-badge" style="background:#e0e7ff; color:#3730a3; padding:2px 6px; border-radius:3px; font-size:11px;"><?php echo esc_html( $r->redirect_type ); ?></span></td>
                                        <td><strong><?php echo number_format( $r->hit_count ); ?></strong></td>
                                        <td>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                                <?php wp_nonce_field( 'aap_redirect_nonce' ); ?>
                                                <input type="hidden" name="action" value="aap_delete_redirect_rule">
                                                <input type="hidden" name="rule_id" value="<?php echo (int)$r->id; ?>">
                                                <button type="submit" class="button button-small" onclick="return confirm('Delete this redirect rule?');">❌ Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: 404 Error Log Tracker & Filters -->
            <div>
                <div class="aap-panel">
                    <div class="aap-panel-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h2 class="aap-panel-title">🚨 Live 404 Error Monitor</h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
                            <?php wp_nonce_field( 'aap_redirect_nonce' ); ?>
                            <input type="hidden" name="action" value="aap_clear_404_logs">
                            <button type="submit" class="button button-secondary button-small" onclick="return confirm('Clear all 404 logs?');">🧹 Clear 404 Logs</button>
                        </form>
                    </div>

                    <!-- Filter Tabs -->
                    <div style="margin-bottom:15px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
                        <span style="font-weight:600; font-size:12px; margin-right:10px; color:#475569;">Filter 404 Logs:</span>
                        <a href="<?php echo admin_url('admin.php?page=aap-redirects&filter=all'); ?>" class="button button-small <?php echo $filter === 'all' ? 'active' : ''; ?>">All 404s</a>
                        <a href="<?php echo admin_url('admin.php?page=aap-redirects&filter=links'); ?>" class="button button-small <?php echo $filter === 'links' ? 'active' : ''; ?>">🔗 Pages & Links Only</a>
                        <a href="<?php echo admin_url('admin.php?page=aap-redirects&filter=images'); ?>" class="button button-small <?php echo $filter === 'images' ? 'active' : ''; ?>">🖼️ Missing Images Only</a>
                    </div>

                    <?php if ( empty( $logs_404 ) ): ?>
                        <p style="color:#646970; font-size:13px; text-align:center; padding:20px 0;">🎉 Great news! No 404 errors detected in this view filter.</p>
                    <?php else: ?>
                        <div style="max-height:550px; overflow-y:auto;">
                            <table class="widefat striped" style="border:none;">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Broken Request URL</th>
                                        <th>Hits</th>
                                        <th>Convert to 301</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $logs_404 as $log ): ?>
                                        <tr>
                                            <td>
                                                <?php if ( $log->is_image ): ?>
                                                    <span style="background:#fef3c7; color:#92400e; padding:2px 6px; border-radius:3px; font-size:11px; font-weight:600;">🖼️ Image</span>
                                                <?php else: ?>
                                                    <span style="background:#e0f2fe; color:#075985; padding:2px 6px; border-radius:3px; font-size:11px; font-weight:600;">🔗 Page</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong style="color:#0f172a; word-break:break-all; font-size:12px;"><?php echo esc_html( $log->url ); ?></strong>
                                                <div style="font-size:11px; color:#64748b; margin-top:2px;">Last hit: <?php echo esc_html( $log->last_detected ); ?></div>
                                            </td>
                                            <td><span class="aap-badge" style="background:#fee2e2; color:#991b1b; padding:2px 7px; border-radius:10px; font-weight:700;"><?php echo number_format( $log->hit_count ); ?></span></td>
                                            <td>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:4px; align-items:center;">
                                                    <?php wp_nonce_field( 'aap_redirect_nonce' ); ?>
                                                    <input type="hidden" name="action" value="aap_convert_404_to_301">
                                                    <input type="hidden" name="source_url" value="<?php echo esc_attr( $log->url ); ?>">
                                                    <input type="text" name="target_url" class="aap-input" placeholder="Destination URL..." style="font-size:11px; padding:3px 6px; width:130px; height:26px;" required>
                                                    <button type="submit" class="button button-primary button-small" style="font-size:11px; height:26px; line-height:24px;">Fix 301</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
