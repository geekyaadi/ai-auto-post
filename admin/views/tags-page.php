<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$posts_per_page = 10;

// Query plugin-generated posts with pagination
$args = [
    'post_type'      => 'post',
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'     => '_aap_generated',
            'value'   => '1',
            'compare' => '=',
        ]
    ]
];
$query = new WP_Query( $args );
$posts = $query->posts;
$total_posts = $query->found_posts;
$total_pages = $query->max_num_pages;
?>

<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Tags Manager</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-tags'); ?>" class="aap-nav-link active">Tags Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-translator'); ?>" class="aap-nav-link">Bulk Translator</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
                <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <div class="aap-content">
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🏷️ Manage Generated Post Tags (Showing <?php echo count($posts); ?> of <?php echo $total_posts; ?>)</h2>
            </div>
            
            <?php if ( empty($posts) ): ?>
            <div class="aap-empty-state">No plugin-generated posts found. Generate some posts first!</div>
            <?php else: ?>
            <!-- Bulk Actions Bar -->
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 0 15px; border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom:15px;">
                <label style="font-size:12px; color:#94a3b8; font-weight:600;">🎯 Apply Tag Quantity to All Rows:</label>
                <select id="aap-bulk-tag-qty" class="aap-select" style="width:auto; min-width:120px; font-size:12px; padding:6px 10px; height:32px; border-radius:6px;">
                    <option value="3">3 Tags</option>
                    <option value="5" selected>5 Tags</option>
                    <option value="8">8 Tags</option>
                    <option value="10">10 Tags</option>
                    <option value="15">15 Tags</option>
                    <option value="20">20 Tags</option>
                </select>
                <button type="button" id="aap-btn-apply-tag-qty-all" class="aap-btn aap-btn-secondary aap-btn-small" style="font-weight:600; border-radius:6px; font-size:12px; padding:6px 12px; height:32px; cursor:pointer;">
                    Apply to All
                </button>
            </div>
            <table class="aap-table" id="aap-tags-manager-table">
                <thead>
                    <tr>
                        <th width="80">Post ID</th>
                        <th width="300">Title</th>
                        <th>Current Tags</th>
                        <th width="120">Quantity</th>
                        <th width="160">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $posts as $p ): 
                        $tags_list = get_the_tags($p->ID);
                    ?>
                    <tr id="aap-tags-row-<?php echo $p->ID; ?>">
                        <td><code>#<?php echo $p->ID; ?></code></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($p->ID); ?>" target="_blank" style="font-weight:600; color: var(--aap-text-dark);">
                                <?php echo esc_html($p->post_title); ?>
                            </a>
                        </td>
                        <td class="aap-tags-list-cell" style="vertical-align: middle;">
                            <?php if ( ! empty($tags_list) ): ?>
                                <button type="button" class="aap-btn aap-btn-secondary aap-btn-small" style="padding:4px 8px; font-size:11px; font-weight:600; margin-bottom:5px; border-radius:4px;" onclick="jQuery('#aap-tags-container-<?php echo $p->ID; ?>').toggle(); var t = jQuery(this).text() === '👁️ Show Tags' ? '🙈 Hide Tags' : '👁️ Show Tags'; jQuery(this).text(t);">👁️ Show Tags</button>
                                <div class="aap-tags-container" id="aap-tags-container-<?php echo $p->ID; ?>" style="display:none; flex-wrap:wrap; gap:5px; margin-top:5px;">
                                    <?php foreach ( $tags_list as $t ): ?>
                                        <span class="aap-status-badge" style="background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.15); color:#6366f1; font-size:10px; font-weight:600; padding:2px 6px; border-radius:4px;">
                                            #<?php echo esc_html($t->name); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-style:italic; font-size:11px;">— No tags</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select class="aap-select aap-tag-qty-select" style="min-width:70px; height:34px; font-size:12px; padding:4px 8px; border-radius:6px;">
                                <option value="3">3 Tags</option>
                                <option value="5" selected>5 Tags</option>
                                <option value="8">8 Tags</option>
                                <option value="10">10 Tags</option>
                                <option value="15">15 Tags</option>
                                <option value="20">20 Tags</option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="aap-btn aap-btn-primary aap-btn-small aap-btn-gen-tags" data-post-id="<?php echo $p->ID; ?>" style="background: #4f46e5; border-color: #4f46e5; color: #fff; width:100%; display:block; text-align:center; height:34px; font-weight:600; border-radius:6px;">
                                ⚡ Generate Tags
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
    </div>
</div>
