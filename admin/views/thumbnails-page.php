<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$tab   = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'pending';
$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$posts_per_page = 10;

// Count total pending
$pending_count_query = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => '_aap_generated', 'value' => '1', 'compare' => '=' ],
        [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ]
    ]
]);
$total_pending = $pending_count_query->post_count;

// Count total completed
$completed_count_query = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => '_aap_generated', 'value' => '1', 'compare' => '=' ],
        [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ]
    ]
]);
$total_completed = $completed_count_query->post_count;

// Run paginated query for current tab
$args = [
    'post_type'      => 'post',
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => '_aap_generated', 'value' => '1', 'compare' => '=' ]
    ]
];

if ( $tab === 'pending' ) {
    $args['meta_query'][] = [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ];
} else {
    $args['meta_query'][] = [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ];
}

$query = new WP_Query( $args );
$display_posts = $query->posts;
$total_pages   = $query->max_num_pages;
?>

<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Thumbnail Manager</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link active">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-tags'); ?>" class="aap-nav-link">Tags Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-translator'); ?>" class="aap-nav-link">Bulk Translator</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
                <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <!-- Tab navigation -->
    <div class="aap-tabs" style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.06); padding-bottom:10px;">
        <a href="<?php echo admin_url('admin.php?page=aap-thumbnails&tab=pending'); ?>" class="aap-btn <?php echo $tab === 'pending' ? 'aap-btn-primary' : 'aap-btn-secondary'; ?>" style="font-weight:600; border-radius:6px; font-size:12px; padding:8px 16px;">
            ⚠️ Pending Thumbnails (<?php echo $total_pending; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=aap-thumbnails&tab=completed'); ?>" class="aap-btn <?php echo $tab === 'completed' ? 'aap-btn-primary' : 'aap-btn-secondary'; ?>" style="font-weight:600; border-radius:6px; font-size:12px; padding:8px 16px;">
            🖼️ Existing Featured Images (<?php echo $total_completed; ?>)
        </a>
    </div>

    <div class="aap-content">
        <?php if ( $tab === 'pending' ): ?>
        <!-- PENDING THUMBNAILS TAB -->
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">⚠️ Pending Thumbnails List</h2>
            </div>
            
            <?php if ( empty($display_posts) ): ?>
            <div class="aap-empty-state">🎉 All generated posts have thumbnails! No pending thumbnails found.</div>
            <?php else: ?>

            <!-- Bulk Actions Bar -->
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:15px 0 10px;">
                <label style="font-size:12px; color:#94a3b8; font-weight:600;">Bulk Engine:</label>
                <select id="aap-bulk-thumb-engine" class="aap-select" style="width:auto; min-width:200px; font-size:12px; padding:6px 10px;">
                    <option value="ai">⚡ AI Generated (Gemini)</option>
                    <option value="text_to_image">🎨 Title Text Thumbnail (GD)</option>
                </select>
                <button type="button" id="aap-btn-generate-selected-thumbs" class="aap-btn aap-btn-primary aap-btn-small" style="background:#6366f1; border-color:#6366f1;" disabled>
                    🖼️ Generate Selected
                </button>
                <button type="button" id="aap-btn-generate-all-thumbs" class="aap-btn aap-btn-primary aap-btn-small" style="background:linear-gradient(135deg,#059669,#10b981); border:none;">
                    🚀 Generate All Pending (<?php echo $total_pending; ?>)
                </button>
            </div>

            <!-- Bulk Progress Bar -->
            <div id="aap-bulk-thumb-progress" style="display:none; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                    <span id="aap-bulk-thumb-status" style="font-size:12px; color:#a5b4fc; font-weight:600;">Processing...</span>
                    <span id="aap-bulk-thumb-count" style="font-size:11px; color:#94a3b8;">0 / 0</span>
                </div>
                <div style="width:100%; height:8px; background:#1e293b; border-radius:4px; overflow:hidden;">
                    <div id="aap-bulk-thumb-bar" style="width:0%; height:100%; background:linear-gradient(90deg,#6366f1,#10b981); border-radius:4px; transition:width 0.4s ease;"></div>
                </div>
            </div>

            <table class="aap-table" id="aap-pending-thumbs-table">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="aap-thumb-select-all" title="Select All"></th>
                        <th width="80">Post ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $display_posts as $p ): 
                        $cats = get_the_category($p->ID);
                        $cat_name = ! empty($cats) ? $cats[0]->name : '—';
                    ?>
                    <tr id="aap-thumb-row-<?php echo $p->ID; ?>">
                        <td><input type="checkbox" class="aap-thumb-checkbox" data-post-id="<?php echo $p->ID; ?>"></td>
                        <td><code>#<?php echo $p->ID; ?></code></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($p->ID); ?>" target="_blank" style="font-weight:600;">
                                <?php echo esc_html($p->post_title); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($cat_name); ?></td>
                        <td>
                            <span class="aap-status-badge aap-status-exhausted">🖼️ Missing Thumbnail</span>
                        </td>
                        <td class="aap-thumb-action-cell" style="vertical-align: middle;">
                            <button type="button" class="aap-btn aap-btn-primary aap-btn-small aap-btn-gen-thumb-ai" data-post-id="<?php echo $p->ID; ?>" style="margin-bottom: 5px; display: block; width: 100%; text-align: center;">
                                ⚡ Generate AI Thumbnail
                            </button>
                            <button type="button" class="aap-btn aap-btn-secondary aap-btn-small aap-btn-gen-thumb-t2i" data-post-id="<?php echo $p->ID; ?>" style="display: block; width: 100%; text-align: center; background: #4f46e5; border-color: #4f46e5; color: #fff;">
                                🎨 Generate Title Text Thumbnail
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination Links -->
            <?php if ( $total_pages > 1 ): ?>
            <div class="aap-pagination" style="display:flex; justify-content:center; gap:5px; margin-top:20px;">
                <?php
                echo paginate_links([
                    'base'     => add_query_arg( 'paged', '%#%' ),
                    'format'   => '',
                    'total'    => $total_pages,
                    'current'  => $paged,
                    'prev_text' => '« Prev',
                    'next_text' => 'Next »',
                ]);
                ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- COMPLETED THUMBNAILS TAB -->
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🖼️ Existing Featured Images List</h2>
            </div>
            
            <?php if ( empty($display_posts) ): ?>
            <div class="aap-empty-state">No posts with thumbnails found yet.</div>
            <?php else: ?>
            <table class="aap-table">
                <thead>
                    <tr>
                        <th width="100">Preview</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $display_posts as $p ): 
                        $cats = get_the_category($p->ID);
                        $cat_name = ! empty($cats) ? $cats[0]->name : '—';
                        $thumb_url = get_the_post_thumbnail_url($p->ID, 'thumbnail');
                    ?>
                    <tr id="aap-thumb-row-<?php echo $p->ID; ?>">
                        <td class="aap-thumb-preview-cell">
                            <img src="<?php echo esc_url($thumb_url); ?>" alt="Preview" style="max-height:50px; border-radius:4px; border:1px solid #ccd0d4; display:block;">
                        </td>
                        <td>
                            <a href="<?php echo get_edit_post_link($p->ID); ?>" target="_blank" style="font-weight:600;">
                                <?php echo esc_html($p->post_title); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($cat_name); ?></td>
                        <td class="aap-thumb-action-cell" style="vertical-align: middle;">
                            <button type="button" class="aap-btn aap-btn-primary aap-btn-small aap-btn-gen-thumb-ai" data-post-id="<?php echo $p->ID; ?>" style="margin-bottom: 5px; display: block; width: 100%; text-align: center;">
                                ⚡ Re-generate AI
                            </button>
                            <button type="button" class="aap-btn aap-btn-secondary aap-btn-small aap-btn-gen-thumb-t2i" data-post-id="<?php echo $p->ID; ?>" style="display: block; width: 100%; text-align: center; background: #4f46e5; border-color: #4f46e5; color: #fff;">
                                🎨 Re-generate Title Text
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination Links -->
            <?php if ( $total_pages > 1 ): ?>
            <div class="aap-pagination" style="display:flex; justify-content:center; gap:5px; margin-top:20px;">
                <?php
                echo paginate_links([
                    'base'     => add_query_arg( 'paged', '%#%' ),
                    'format'   => '',
                    'total'    => $total_pages,
                    'current'  => $paged,
                    'prev_text' => '« Prev',
                    'next_text' => 'Next »',
                ]);
                ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
