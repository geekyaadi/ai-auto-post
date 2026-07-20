<?php if ( ! defined( 'ABSPATH' ) ) exit;
$queue_items = AAP_Queue::get_all(50);
$queue_count = AAP_Queue::count_by_status();
$niches_text = get_option( AAP_Scheduler::OPTION_NICHES, '' );
$per_day     = (int) get_option( AAP_Scheduler::OPTION_PER_DAY, 3 );
$enabled     = AAP_Scheduler::is_enabled();
$next_run    = AAP_Scheduler::get_next_run();
$msg_map     = [
    'saved'                  => ['type'=>'success','text'=>'✅ Schedule settings saved.'],
    'queued'                 => ['type'=>'success','text'=>'✅ Niche added to queue.'],
    'queue_deleted'          => ['type'=>'success','text'=>'✅ Queue item deleted.'],
    'queue_paused'           => ['type'=>'success','text'=>'⏸ Queue item paused.'],
    'queue_resumed'          => ['type'=>'success','text'=>'▶ Queue item resumed/queued.'],
    'queue_cleared'          => ['type'=>'success','text'=>'🧹 Entire queue cleared successfully.'],
    'queue_selected_deleted' => ['type'=>'success','text'=>'🗑️ Selected queue items deleted.'],
];
$msg = $_GET['msg'] ?? ( isset($_GET['saved']) ? 'saved' : '' );
?>
<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Scheduler</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link active">Scheduler</a>
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

        <div class="aap-two-col">

            <!-- Schedule Settings -->
            <div class="aap-panel">
                <div class="aap-panel-header">
                    <h2 class="aap-panel-title">📅 Auto-Schedule Settings</h2>
                    <span class="aap-status-badge <?php echo $enabled ? 'aap-status-active' : 'aap-status-exhausted'; ?>">
                        <?php echo $enabled ? '✅ Running' : '⏸ Paused'; ?>
                    </span>
                </div>

                <?php if ( $enabled ): ?>
                <div class="aap-info-box">
                    <div class="aap-info-row">
                        <span class="aap-info-label">Next Run:</span>
                        <span class="aap-info-value"><?php echo esc_html($next_run); ?></span>
                    </div>
                    <div class="aap-info-row">
                        <span class="aap-info-label">Posts/Day:</span>
                        <span class="aap-info-value"><?php echo $per_day; ?></span>
                    </div>
                    <div class="aap-info-row">
                        <span class="aap-info-label">Queue Pending:</span>
                        <span class="aap-info-value"><?php echo AAP_Queue::count_by_status('queued'); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('aap_save_schedule'); ?>
                    <input type="hidden" name="action" value="aap_save_schedule">

                    <div class="aap-field">
                        <label class="aap-toggle">
                            <input type="checkbox" name="schedule_enabled" value="1" <?php checked($enabled,true); ?>>
                            <span class="aap-toggle-slider"></span>
                            <span class="aap-toggle-label"><strong>Enable Auto-Scheduler</strong></span>
                        </label>
                        <div class="aap-hint">Uses WP-Cron to automatically generate and publish posts on schedule.</div>
                    </div>

                    <div class="aap-field">
                        <label class="aap-label">Posts Per Day</label>
                        <input type="number" name="posts_per_day" class="aap-input aap-input-sm" value="<?php echo $per_day; ?>" min="1" max="50">
                    </div>

                    <div class="aap-field">
                        <label class="aap-label">Niche Rotation List</label>
                        <textarea name="schedule_niches" class="aap-textarea" rows="6"
                            placeholder="Enter one niche per line:&#10;Personal Finance&#10;Home Improvement&#10;Digital Marketing&#10;Fitness Tips"
                        ><?php echo esc_textarea($niches_text); ?></textarea>
                        <div class="aap-hint">Scheduler will cycle through these niches in order. If queue has pending items, those are processed first.</div>
                    </div>

                    <div class="aap-form-actions">
                        <button type="submit" class="aap-btn aap-btn-primary">💾 Save Schedule</button>
                    </div>
                </form>
            </div>

            <!-- Post Queue -->
            <div class="aap-panel">
                <div class="aap-panel-header">
                    <h2 class="aap-panel-title">📋 Post Queue & Planner Tasks</h2>
                    <div class="aap-panel-actions">
                        <button type="button" id="aap-btn-run-queue" class="aap-btn aap-btn-secondary aap-btn-sm" style="font-weight:600;">
                            🚀 Run Queue Now
                        </button>
                    </div>
                </div>

                <!-- Live Queue Runner Console (Hidden by default) -->
                <div class="aap-queue-console" id="aap-queue-console" style="display:none; background: rgba(0,0,0,0.2); border:1px solid var(--aap-border); border-radius:6px; padding:12px; margin-bottom:15px; font-size:12px; font-family:var(--aap-font);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong>⚙️ Active Queue Processor Console</strong>
                        <span id="aap-queue-console-status" style="color:var(--aap-primary); font-weight:bold;">Running...</span>
                    </div>
                    <div id="aap-queue-console-logs" style="max-height:100px; overflow-y:auto; color:#a7f3d0; line-height:1.5; font-family: monospace;">
                        Starting queue processor...
                    </div>
                </div>

                <!-- Add to queue -->
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="aap-add-key-form">
                    <?php wp_nonce_field('aap_enqueue_niche'); ?>
                    <input type="hidden" name="action" value="aap_enqueue_niche">
                    <div class="aap-input-row">
                        <input type="text" name="niche" class="aap-input" placeholder="Add custom niche line to queue (e.g. Finance | tips)...">
                        <button type="submit" class="aap-btn aap-btn-primary">➕ Add to Queue</button>
                    </div>
                </form>

                <?php if ( empty($queue_items) ): ?>
                <div class="aap-empty-state">Queue is empty. Plan some articles in the Bulk Planner.</div>
                <?php else: ?>
                
                <!-- Queue Bulk Actions Toolbar -->
                <div class="aap-queue-toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; background:rgba(255,255,255,0.03); padding:10px; border-radius:6px; border:1px solid var(--aap-border);">
                    <div class="aap-queue-toolbar-left" style="display:flex; align-items:center; gap:10px;">
                        <button type="button" id="aap-btn-delete-selected" class="aap-btn aap-btn-danger aap-btn-sm" style="display:none; background:#ea580c; border-color:#ea580c;" onclick="aapDeleteSelectedQueue()">✕ Delete Selected</button>
                    </div>
                    <div class="aap-queue-toolbar-right">
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Are you sure you want to clear the entire queue? This cannot be undone.')" style="margin:0;">
                            <?php wp_nonce_field('aap_clear_queue'); ?>
                            <input type="hidden" name="action" value="aap_clear_queue">
                            <button type="submit" class="aap-btn aap-btn-danger aap-btn-sm" style="background:#dc2626; border-color:#dc2626;">🗑️ Clear All Queue</button>
                        </form>
                    </div>
                </div>

                <!-- Hidden Delete Selected Form -->
                <form id="aap-form-delete-selected-queue" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('aap_delete_selected_queue'); ?>
                    <input type="hidden" name="action" value="aap_delete_selected_queue">
                    <input type="hidden" name="queue_ids" id="aap-hidden-delete-queue-ids" value="">
                </form>

                <table class="aap-table aap-table-sm" id="aap-queue-table">
                    <thead>
                        <tr>
                            <th style="width: 30px; text-align: center;"><input type="checkbox" id="aap-select-all-queue"></th>
                            <th>#</th>
                            <th>Niche & Title</th>
                            <th>Language</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue_items as $item): ?>
                        <tr class="aap-queue-row-<?php echo esc_attr($item->status); ?>" id="aap-queue-row-<?php echo (int)$item->id; ?>">
                            <td style="text-align: center;"><input type="checkbox" class="aap-queue-checkbox" value="<?php echo (int)$item->id; ?>"></td>
                            <td><?php echo (int)$item->id; ?></td>
                            <td>
                                <strong style="color:var(--aap-text);"><?php echo esc_html($item->niche); ?></strong>
                                <?php if ($item->title): ?>
                                <div class="aap-queue-item-title" style="font-size:11px; margin-top:2px; color:rgba(255,255,255,0.6);"><?php echo esc_html($item->title); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:11px;"><?php echo esc_html($item->language ?? 'English'); ?></span>
                            </td>
                            <td>
                                <span class="aap-badge-category" style="font-size:11px; color:#a78bfa; background:rgba(167,139,250,0.1); padding:2px 6px; border-radius:4px; font-weight:600;">
                                    <?php echo esc_html($item->category ?: 'Auto-Suggest'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="aap-status-badge aap-status-<?php echo esc_attr($item->status); ?>" style="display:inline-block;">
                                    <?php
                                    $status_labels = ['queued'=>'⏳ Queued','processing'=>'⚙️ Processing','published'=>'✅ Published','failed'=>'❌ Failed','paused'=>'⏸ Paused'];
                                    echo $status_labels[$item->status] ?? $item->status;
                                    ?>
                                </span>
                                <?php if ($item->status === 'failed' && !empty($item->error_msg)): ?>
                                <div class="aap-queue-item-error" style="color:#f87171; font-size:10px; margin-top:4px; max-width:180px; line-height:1.2; word-break:break-word;">
                                    ⚠️ <?php echo esc_html($item->error_msg); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size:11px;"><?php echo esc_html(date('M j, H:i', strtotime($item->created_at))); ?></span></td>
                            <td>
                                <?php if ($item->status === 'published' && $item->post_id): ?>
                                <a href="<?php echo get_permalink($item->post_id); ?>" target="_blank" class="aap-btn-small aap-btn-ghost">View</a>
                                <?php endif; ?>
                                
                                <?php if ( in_array($item->status, ['queued', 'processing'], true) ): ?>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                    <?php wp_nonce_field('aap_pause_queue'); ?>
                                    <input type="hidden" name="action" value="aap_pause_queue">
                                    <input type="hidden" name="queue_id" value="<?php echo (int)$item->id; ?>">
                                    <button class="aap-btn-small aap-btn-ghost" type="submit" title="Pause Task" style="background:#fffcf0; border-color:#fbbf24; color:#d97706; font-size:9px;">⏸</button>
                                </form>
                                <?php elseif ( in_array($item->status, ['paused', 'failed'], true) ): ?>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                    <?php wp_nonce_field('aap_resume_queue'); ?>
                                    <input type="hidden" name="action" value="aap_resume_queue">
                                    <input type="hidden" name="queue_id" value="<?php echo (int)$item->id; ?>">
                                    <button class="aap-btn-small aap-btn-success" type="submit" title="<?php echo $item->status === 'failed' ? 'Retry Task' : 'Resume Task'; ?>" style="font-size:9px; background: #059669; border-color: #059669;">
                                        <?php echo $item->status === 'failed' ? '🔄 Retry' : '▶'; ?>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('Delete this queue item?')">
                                    <?php wp_nonce_field('aap_delete_queue'); ?>
                                    <input type="hidden" name="action" value="aap_delete_queue">
                                    <input type="hidden" name="queue_id" value="<?php echo (int)$item->id; ?>">
                                    <button class="aap-btn-small aap-btn-danger" type="submit">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('aap-select-all-queue');
    const checkboxes = document.querySelectorAll('.aap-queue-checkbox');
    const deleteBtn = document.getElementById('aap-btn-delete-selected');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            updateDeleteSelectedBtn();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateDeleteSelectedBtn);
    });

    function updateDeleteSelectedBtn() {
        const checked = document.querySelectorAll('.aap-queue-checkbox:checked');
        if (checked.length > 0) {
            deleteBtn.style.display = 'inline-block';
            deleteBtn.textContent = '✕ Delete Selected (' + checked.length + ')';
        } else {
            deleteBtn.style.display = 'none';
        }
    }

    window.aapDeleteSelectedQueue = function() {
        if (!confirm('Are you sure you want to delete the selected queue items?')) return;
        const checked = document.querySelectorAll('.aap-queue-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        document.getElementById('aap-hidden-delete-queue-ids').value = ids.join(',');
        document.getElementById('aap-form-delete-selected-queue').submit();
    };
});
</script>
