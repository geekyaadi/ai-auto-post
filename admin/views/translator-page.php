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

$languages = [
    'Spanish'    => 'Spanish 🇪🇸',
    'French'     => 'French 🇫🇷',
    'German'     => 'German 🇩🇪',
    'Hindi'      => 'Hindi 🇮🇳',
    'Italian'    => 'Italian 🇮🇹',
    'Portuguese' => 'Portuguese 🇵🇹',
    'Russian'    => 'Russian 🇷🇺',
    'Arabic'     => 'Arabic 🇸🇦',
    'Japanese'   => 'Japanese 🇯🇵',
    'Chinese'    => 'Chinese 🇨🇳',
    'English'    => 'English 🇺🇸',
];
?>

<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Bulk Translator</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-tags'); ?>" class="aap-nav-link">Tags Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-translator'); ?>" class="aap-nav-link active">Bulk Translator</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
                <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <div class="aap-content">
        <!-- Translator Options -->
        <div class="aap-panel" style="margin-bottom: 20px;">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🌐 Bulk Translation Options</h2>
            </div>
            
            <div style="display:flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                <div class="aap-field" style="flex:1; min-width:200px; margin-bottom:0;">
                    <label class="aap-label">Target Language</label>
                    <select id="aap-translator-target-lang" class="aap-select" style="width:100%; height:38px; padding:6px 12px; border-radius:6px;">
                        <?php foreach ( $languages as $code => $label ): ?>
                        <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aap-field" style="flex:1; min-width:200px; margin-bottom:0;">
                    <label class="aap-label">Destination Post Status</label>
                    <select id="aap-translator-status" class="aap-select" style="width:100%; height:38px; padding:6px 12px; border-radius:6px;">
                        <option value="draft" selected>Save as Draft</option>
                        <option value="publish">Publish Instantly</option>
                    </select>
                </div>

                <div style="flex:1; min-width:220px;">
                    <button type="button" id="aap-btn-translate-selected" class="aap-btn aap-btn-primary" style="background:#4f46e5; border-color:#4f46e5; width:100%; font-weight:600; height:38px; border-radius:6px; padding:0 20px; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                        🌐 Translate Selected Posts
                    </button>
                </div>
            </div>

            <!-- Progress Block -->
            <div id="aap-translator-progress-container" style="display:none; margin-top:20px; border-top:1px solid rgba(255,255,255,0.06); padding-top:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-weight:600; font-size:13px; color:var(--aap-text-dark);">
                    <span id="aap-trans-progress-text">Processing translation queue...</span>
                    <span id="aap-trans-progress-percent">0%</span>
                </div>
                <div style="background:rgba(255,255,255,0.05); height:8px; border-radius:4px; overflow:hidden; margin-bottom:15px;">
                    <div id="aap-trans-progress-bar" style="background:linear-gradient(90deg, #4f46e5, #06b6d4); width:0%; height:100%; transition: width 0.3s ease;"></div>
                </div>
                <div id="aap-trans-log" style="background:#0f172a; color:#cbd5e1; font-family:monospace; font-size:12px; padding:12px 15px; border-radius:6px; max-height:150px; overflow-y:auto; line-height:1.5;">
                    <div>[Console initialized] Select posts and click Translate to begin.</div>
                </div>
            </div>
        </div>

        <!-- Posts List -->
        <div class="aap-panel">
            <div class="aap-panel-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2 class="aap-panel-title">📚 Select Articles to Translate (Showing <?php echo count($posts); ?> of <?php echo $total_posts; ?>)</h2>
                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--aap-text-muted); cursor:pointer; display:flex; align-items:center; gap:5px;">
                        <input type="checkbox" id="aap-translator-select-all" style="vertical-align:middle; margin:0;"> Select All
                    </label>
                </div>
            </div>

            <?php if ( empty($posts) ): ?>
            <div class="aap-empty-state">No plugin-generated posts found. Generate some posts first!</div>
            <?php else: ?>
            <table class="aap-table" id="aap-translator-table">
                <thead>
                    <tr>
                        <th width="40">Select</th>
                        <th width="80">Post ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Categories</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $posts as $p ): 
                        $cats = get_the_category($p->ID);
                        $cat_names = ! empty($cats) ? implode(', ', wp_list_pluck($cats, 'name')) : '—';
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="aap-translator-checkbox" data-post-id="<?php echo $p->ID; ?>">
                        </td>
                        <td><code>#<?php echo $p->ID; ?></code></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($p->ID); ?>" target="_blank" style="font-weight:600; color:var(--aap-text-dark);">
                                <?php echo esc_html($p->post_title); ?>
                            </a>
                        </td>
                        <td>
                            <span class="aap-status-badge <?php echo $p->post_status === 'publish' ? 'aap-status-active' : 'aap-status-exhausted'; ?>">
                                <?php echo $p->post_status === 'publish' ? 'Published' : 'Draft'; ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($cat_names); ?></td>
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
