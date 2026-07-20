<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$gsc_json = get_option( 'aap_gsc_json', '' );
$has_creds = ! empty( $gsc_json );

$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$posts_per_page = 10;

// Query published posts with pagination
$args = [
    'post_type'      => 'post',
    'post_status'    => 'publish',
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
                <span class="aap-logo-badge">Google Indexing Tool</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-tags'); ?>" class="aap-nav-link">Tags Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-translator'); ?>" class="aap-nav-link">Bulk Translator</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link active">Google Indexing</a>
                <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <div class="aap-content">

        <!-- Status Panel -->
        <div class="aap-panel" style="margin-bottom:20px;">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🔌 Google Indexing API Status</h2>
                <?php if ( $has_creds ): ?>
                <span class="aap-status-badge aap-status-active">✅ Credentials Configured</span>
                <?php else: ?>
                <span class="aap-status-badge aap-status-exhausted">❌ No Credentials</span>
                <?php endif; ?>
            </div>
            <?php if ( ! $has_creds ): ?>
            <div class="aap-alert aap-alert-warning" style="margin:15px 0 5px;">
                ⚠️ Google Service Account JSON key is not configured. Go to <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>">Settings → Internal Linking & Indexing</a> to paste or upload your credentials.
            </div>
            <?php else: ?>
            <div class="aap-hint" style="padding: 10px 0 0;">
                Your Google Service Account is connected. Use the buttons below to manually request instant indexing for any published post.
                <?php if ( get_option( 'aap_enable_gsc_auto_ping', 0 ) ): ?>
                <br><strong>Auto-Ping is ON</strong> — New posts will be automatically submitted to Google on publish.
                <?php else: ?>
                <br><em>Auto-Ping is OFF</em> — Enable it in <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>">Settings</a> to auto-submit new posts.
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Posts List -->
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🚀 Request Indexing (Showing <?php echo count($posts); ?> of <?php echo $total_posts; ?>)</h2>
            </div>

            <?php if ( empty($posts) ): ?>
            <div class="aap-empty-state">No published posts found. Generate and publish some posts first!</div>
            <?php else: ?>
            <table class="aap-table" id="aap-gsc-table">
                <thead>
                    <tr>
                        <th width="80">Post ID</th>
                        <th>Title</th>
                        <th width="150">Published</th>
                        <th width="160">Last Indexed Ping</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $posts as $p ):
                        $last_ping = get_post_meta($p->ID, '_aap_gsc_last_ping', true);
                    ?>
                    <tr id="aap-gsc-row-<?php echo $p->ID; ?>">
                        <td><code>#<?php echo $p->ID; ?></code></td>
                        <td>
                            <a href="<?php echo get_permalink($p->ID); ?>" target="_blank" style="font-weight:600; color:var(--aap-text-dark);">
                                <?php echo esc_html($p->post_title); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(get_the_date('M j, Y', $p->ID)); ?></td>
                        <td class="aap-gsc-ping-cell">
                            <?php if ( $last_ping ): ?>
                            <span class="aap-status-badge" style="background:#dcfce7; color:#166534; font-size:10px;">
                                ✅ <?php echo esc_html( date('M j, H:i', strtotime($last_ping)) ); ?>
                            </span>
                            <?php else: ?>
                            <span style="color:#94a3b8; font-style:italic; font-size:11px;">— Never pinged</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button"
                                class="aap-btn aap-btn-primary aap-btn-small aap-btn-request-indexing"
                                data-post-id="<?php echo $p->ID; ?>"
                                style="background:#059669; border-color:#059669; color:#fff; width:100%; border-radius:6px; font-weight:600;"
                                <?php disabled( ! $has_creds ); ?>>
                                🚀 Request Indexing
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
