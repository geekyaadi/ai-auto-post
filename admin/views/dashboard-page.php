<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$history    = AAP_History::get_all(30);
$stats      = AAP_History::get_summary_stats();
$key_stats  = AAP_Key_Manager::get_stats();
$all_keys   = AAP_Key_Manager::get_all_keys();

$msg_map = [
    'deleted' => ['type'=>'success','text'=>'✅ History entry deleted.'],
    'cleared' => ['type'=>'success','text'=>'✅ History cleared.'],
];
$msg = $_GET['msg'] ?? '';
$cost_per_1k = 0.00015; // average cost estimate in USD per 1k tokens across models
?>

<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Control Center</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link active">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
<a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <div class="aap-content">
        <?php if ( $msg && isset($msg_map[$msg]) ): ?>
        <div class="aap-alert aap-alert-<?php echo $msg_map[$msg]['type']; ?>"><?php echo esc_html($msg_map[$msg]['text']); ?></div>
        <?php endif; ?>

        <!-- Two Column Dashboard Grid -->
        <div class="aap-two-col">

            <!-- LEFT: Stats, Keys, & History -->
            <div class="aap-col-left">

                <!-- Stats Cards -->
                <div class="aap-stats-grid select-none">
                    <div class="aap-stat-card aap-stat-primary">
                        <div class="aap-stat-icon">📝</div>
                        <div class="aap-stat-value"><?php echo $stats['total']; ?></div>
                        <div class="aap-stat-label">Total Generated</div>
                    </div>
                    <div class="aap-stat-card aap-stat-success">
                        <div class="aap-stat-icon">✅</div>
                        <div class="aap-stat-value"><?php echo $stats['success']; ?></div>
                        <div class="aap-stat-label">Successful</div>
                    </div>
                    <div class="aap-stat-card aap-stat-info">
                        <div class="aap-stat-icon">🔢</div>
                        <div class="aap-stat-value"><?php echo number_format($stats['total_tokens']); ?></div>
                        <div class="aap-stat-label">Est. Tokens Used</div>
                    </div>
                    <div class="aap-stat-card aap-stat-warning">
                        <div class="aap-stat-icon">💰</div>
                        <div class="aap-stat-value">~$<?php echo number_format($stats['total_tokens'] * $cost_per_1k / 1000, 4); ?></div>
                        <div class="aap-stat-label">Est. Cost (USD)</div>
                    </div>
                </div>

                <!-- API Keys Status Panel -->
                <div class="aap-panel">
                    <div class="aap-panel-header">
                        <h2 class="aap-panel-title">🔑 API Key Health Dashboard</h2>
                        <div class="aap-panel-actions">
                            <button type="button" id="aap-btn-ping-all" class="aap-btn aap-btn-secondary aap-btn-sm">🏓 Ping All</button>
                            <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-btn aap-btn-ghost aap-btn-sm">⚙️ Manage</a>
                        </div>
                    </div>

                    <?php if (empty($all_keys)): ?>
                    <div class="aap-empty-state">
                        No API keys configured. <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>">Configure keys first →</a>
                    </div>
                    <?php else: ?>
                    <table class="aap-table" id="aap-keys-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>API Key</th>
                                <th>Status</th>
                                <th>Resets In</th>
                                <th>Success Rate</th>
                                <th>Test</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_keys as $i => $k):
                                $req   = (int)($k['requests'] ?? 0);
                                $fail  = (int)($k['failures'] ?? 0);
                                $rate  = $req > 0 ? round(($req - $fail) / $req * 100) : 100;
                                $reset = AAP_Key_Manager::seconds_until_reset($k);
                                $prov  = $k['provider'] ?? 'gemini';
                            ?>
                            <tr data-key-index="<?php echo $i; ?>" data-reset-ts="<?php echo (int)($k['reset_at_ts'] ?? 0); ?>">
                                <td>
                                    <span class="aap-badge aap-badge-<?php echo esc_attr($prov); ?>">
                                        <?php echo $prov === 'openai' ? 'OpenAI' : 'Gemini'; ?>
                                    </span>
                                </td>
                                <td><code class="aap-key-masked"><?php echo esc_html(AAP_Key_Manager::mask_key($k['key'])); ?></code></td>
                                <td class="aap-key-status-cell">
                                    <span class="aap-status-badge aap-status-<?php echo esc_attr($k['status']); ?>">
                                        <?php
                                        if ($k['status'] === 'active')    echo '✅ Active';
                                        elseif ($k['status'] === 'invalid') echo '⛔ Invalid';
                                        else                               echo '🔴 Exhausted';
                                        ?>
                                    </span>
                                </td>
                                <td class="aap-key-countdown-cell">
                                    <?php if ($k['status'] === 'exhausted' && $reset !== null): ?>
                                    <span class="aap-countdown" data-reset-ts="<?php echo (int)$k['reset_at_ts']; ?>">
                                        ⏱ <span class="aap-countdown-val"><?php echo AAP_Key_Manager::format_seconds($reset); ?></span>
                                    </span>
                                    <?php else: ?>
                                    <span class="aap-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="aap-progress-pct"><?php echo $rate; ?>%</span>
                                </td>
                                <td>
                                    <button type="button" class="aap-btn-small aap-btn-ghost aap-btn-ping" data-key-index="<?php echo $i; ?>">🏓</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- History Log Panel -->
                <div class="aap-panel">
                    <div class="aap-panel-header">
                        <h2 class="aap-panel-title">📋 Recent Generations Log</h2>
                        <?php if (!empty($history)): ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Clear ALL history? This cannot be undone.')">
                            <?php wp_nonce_field('aap_clear_history'); ?>
                            <input type="hidden" name="action" value="aap_clear_history">
                            <button class="aap-btn-small aap-btn-danger" type="submit">🗑 Clear All</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($history)): ?>
                    <div class="aap-empty-state">No posts generated yet. Use the Quick Generator to start.</div>
                    <?php else: ?>
                    <table class="aap-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Tokens</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?php echo esc_html(date('M j, H:i', strtotime($row->created_at))); ?></td>
                                <td>
                                    <?php if ($row->post_id): ?>
                                    <a href="<?php echo get_permalink($row->post_id); ?>" target="_blank" class="aap-history-title-link" style="font-weight:600;">
                                        <?php echo esc_html(wp_trim_words($row->title, 8)); ?>
                                    </a>
                                    <?php
                                    $pending = [];
                                    if ( ! has_post_thumbnail( $row->post_id ) ) {
                                        $pending[] = '<span class="aap-status-badge aap-status-exhausted" style="font-size:10px; padding:1px 4px; margin-top:4px; display:inline-block; margin-right:4px;">🖼️ Thumbnail Pending</span>';
                                    }
                                    $t = get_the_tags( $row->post_id );
                                    if ( empty( $t ) ) {
                                        $pending[] = '<span class="aap-status-badge aap-status-exhausted" style="font-size:10px; padding:1px 4px; margin-top:4px; display:inline-block; margin-right:4px;">🏷️ Tags Pending</span>';
                                    }
                                    if ( ! empty( $pending ) ) {
                                        echo '<div class="aap-log-item-meta" style="margin-top:2px;">' . implode( '', $pending ) . '</div>';
                                    }
                                    ?>
                                    <?php else: ?>
                                    <?php echo esc_html(wp_trim_words($row->title, 8)); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="aap-badge aap-badge-<?php echo esc_attr($row->key_used && strpos($row->key_used, 'sk-') === 0 ? 'openai' : 'gemini'); ?>">
                                        <?php echo $row->key_used && strpos($row->key_used, 'sk-') === 0 ? 'OpenAI' : 'Gemini'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="aap-status-badge aap-status-<?php echo $row->status==='success'?'active':'exhausted'; ?>">
                                        <?php echo $row->status==='success' ? '✅ Success' : '❌ Failed'; ?>
                                    </span>
                                </td>
                                <td>~<?php echo number_format($row->token_estimate); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RIGHT: Quick Generator Form -->
            <div class="aap-col-right">

                <div class="aap-panel aap-generate-panel">
                    <div class="aap-panel-header">
                        <h2 class="aap-panel-title">⚡ Quick Post Generator</h2>
                        <div class="aap-key-badge">
                            <span class="aap-key-dot <?php echo $key_stats['active'] > 0 ? 'active' : 'inactive'; ?>"></span>
                            <span><?php echo $key_stats['active']; ?>/<?php echo $key_stats['total']; ?> Keys Active</span>
                        </div>
                    </div>

                    <!-- Step 1: Niche & Keywords -->
                    <div class="aap-step" id="aap-step-niche">
                        <div class="aap-step-number">1</div>
                        <div class="aap-step-body">
                            <label class="aap-label" for="aap-niche-input">Enter Niche or Topic</label>
                            <input type="text" id="aap-niche-input" class="aap-input"
                                placeholder="e.g. Finance, Weight Loss, Tech Reviews..." autocomplete="off" />

                            <label class="aap-label" for="aap-keywords-input" style="margin-top: 14px;">Focus Keywords (SEO)</label>
                            <input type="text" id="aap-keywords-input" class="aap-input"
                                placeholder="e.g. weight loss tips, how to lose fat (comma separated)" autocomplete="off" />

                            <div style="margin-top: 14px;">
                                <button type="button" id="aap-btn-find-titles" class="aap-btn aap-btn-primary aap-btn-full">
                                    <span class="aap-btn-icon">🔍</span> Find Titles
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Title selection -->
                    <div class="aap-step aap-step-locked" id="aap-step-titles">
                        <div class="aap-step-number">2</div>
                        <div class="aap-step-body">
                            <label class="aap-label">Select Title</label>
                            <div class="aap-titles-list" id="aap-titles-list">
                                <div class="aap-titles-placeholder">Find titles first...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Options & Reference Image -->
                    <div class="aap-step aap-step-locked" id="aap-step-options">
                        <div class="aap-step-number">3</div>
                        <div class="aap-step-body">
                            <div class="aap-options-row">
                                <div class="aap-option-group">
                                    <label class="aap-option-label">Status</label>
                                    <select id="aap-post-status" class="aap-select">
                                        <option value="draft" <?php selected( get_option('aap_default_status','draft'), 'draft' ); ?>>Draft</option>
                                        <option value="publish" <?php selected( get_option('aap_default_status','draft'), 'publish' ); ?>>Published</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Image style reference -->
                            <div class="aap-ref-img-section">
                                <div class="aap-ref-img-header">
                                    <span class="aap-ref-img-title">🖼️ Thumbnail Reference Style</span>
                                    <?php $has_default = ! empty( AAP_Gemini::get_default_reference_image() ); ?>
                                    <?php if ( $has_default ): ?>
                                    <span class="aap-badge aap-badge-default" title="A default reference image is set in Settings">✦ Default Set</span>
                                    <?php endif; ?>
                                </div>

                                <div class="aap-upload-zone" id="aap-upload-zone">
                                    <div class="aap-upload-idle" id="aap-upload-idle">
                                        <span class="aap-upload-icon">📁</span>
                                        <span class="aap-upload-text">Drag sample image here or <label for="aap-ref-img-input" class="aap-upload-link">browse</label></span>
                                    </div>
                                    <div class="aap-upload-preview" id="aap-upload-preview" style="display:none;">
                                        <img id="aap-ref-img-thumb" src="" alt="Reference Preview">
                                        <div class="aap-upload-preview-info">
                                            <span id="aap-ref-img-name" class="aap-upload-filename"></span>
                                            <button type="button" id="aap-btn-clear-ref" class="aap-btn-small aap-btn-danger">✕</button>
                                        </div>
                                    </div>
                                    <input type="file" id="aap-ref-img-input" accept="image/*" style="display:none;">
                                </div>
                            </div>

                            <div class="aap-action-buttons" style="margin-top:20px;">
                                <button id="aap-btn-preview" class="aap-btn aap-btn-secondary">👁 Preview</button>
                                <button id="aap-btn-generate" class="aap-btn aap-btn-primary" disabled>⚡ Generate</button>
                            </div>
                        </div>
                    </div>

                    <!-- Progress pipeline -->
                    <div class="aap-progress-panel" id="aap-progress-steps" style="display:none;">
                        <div class="aap-panel-header" style="padding: 10px 0; border-bottom: 1px solid var(--aap-border);">
                            <h3 class="aap-panel-title" style="font-size:13px;">🔄 Pipeline Progress</h3>
                        </div>

                        <!-- Live key switch notice -->
                        <div class="aap-alert aap-alert-warning" id="aap-key-switch-notice" style="display:none; margin-top:10px;">
                            <span class="aap-alert-icon">⚡</span>
                            <span id="aap-key-switch-text">Switching keys...</span>
                        </div>

                        <div class="aap-progress-list">
                            <?php
                            $steps = [
                                'article'   => '📝 Write Article (Unique & Human)',
                                'tags'      => '🏷️ SEO Tags Generation',
                                'meta'      => '🔍 Meta Description',
                                'category'  => '📂 Category Auto-Assignment',
                                'thumbnail' => '🖼️ Thumbnail Style Match',
                                'og_image'  => '📱 Social OpenGraph Image',
                                'alt_text'  => '✍️ Image Alt Text Tags',
                                'publish'   => '🚀 Create Post & Publish',
                            ];
                            foreach ( $steps as $step_id => $step_label ):
                            ?>
                            <div class="aap-progress-step" id="aap-pstep-<?php echo $step_id; ?>">
                                <div class="aap-pstep-dot waiting"></div>
                                <div class="aap-pstep-body">
                                    <div class="aap-pstep-label"><?php echo esc_html($step_label); ?></div>
                                    <div class="aap-pstep-meta"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Output Results -->
                    <div class="aap-result-box" id="aap-result" style="display:none;"></div>
                </div>

            </div>

        </div>

        <!-- Preview Modal Overlay -->
        <div class="aap-preview-panel" id="aap-preview-panel" style="display:none;">
            <div class="aap-preview-header">
                <div class="aap-preview-header-title">👁 Post Preview (SEO & Readability Approved)</div>
                <div class="aap-preview-header-actions">
                    <button id="aap-btn-confirm-publish" class="aap-btn aap-btn-primary">🚀 Publish Post Now</button>
                    <button id="aap-btn-cancel-preview" class="aap-btn aap-btn-secondary">✕ Close</button>
                </div>
            </div>
            <div class="aap-preview-body">
                <div class="aap-preview-meta-row">
                    <strong>Category:</strong> <span id="aap-preview-category"></span> |
                    <strong>Tags:</strong> <span id="aap-preview-tags"></span>
                </div>
                <div class="aap-preview-meta-row">
                    <strong>Meta Description:</strong> <span id="aap-preview-meta-desc"></span>
                </div>
                <h1 class="aap-preview-post-title" id="aap-preview-title"></h1>
                <div class="aap-preview-content-area" id="aap-preview-content"></div>
            </div>
        </div>

    </div>
</div>

<input type="hidden" id="aap-session-id" value="">
