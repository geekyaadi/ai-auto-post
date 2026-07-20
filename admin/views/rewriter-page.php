<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
                <span class="aap-logo-badge">AI Article Rewriter</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-tags'); ?>" class="aap-nav-link">Tags Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-translator'); ?>" class="aap-nav-link">Bulk Translator</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
                <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link active">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <div class="aap-content">

        <!-- Info Panel -->
        <div class="aap-panel" style="margin-bottom:20px;">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">✍️ AI Article Rewriter & Freshness Updater</h2>
            </div>
            <div class="aap-hint" style="padding:10px 0 0;">
                Select any published post to rewrite & freshen its content using AI. You can optionally add custom instructions (e.g., "make it more conversational", "add latest 2026 updates", "shorten the article"). The AI will preserve the topic and heading structure while improving readability and SEO.
            </div>
        </div>

        <!-- Posts List -->
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">📄 Published Posts (Total: <?php echo $total_posts; ?>)</h2>
            </div>

            <?php if ( empty($posts) ): ?>
            <div class="aap-empty-state">No published posts found.</div>
            <?php else: ?>
            <div class="aap-rewriter-list">
                <?php foreach ( $posts as $p ):
                    $word_count = str_word_count( wp_strip_all_tags( $p->post_content ) );
                ?>
                <div class="aap-rewriter-item" id="aap-rewriter-row-<?php echo $p->ID; ?>" style="border:1px solid var(--aap-border); border-radius:10px; padding:18px; margin-bottom:12px; background:var(--aap-surface-2);">
                    <div style="display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap;">
                        <!-- Post Info -->
                        <div style="flex:1; min-width:280px;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                <code style="font-size:11px; color:#6366f1;">#<?php echo $p->ID; ?></code>
                                <a href="<?php echo get_permalink($p->ID); ?>" target="_blank" style="font-weight:700; color:var(--aap-text-dark); font-size:14px; text-decoration:none; transition: color 0.15s ease-in-out;">
                                    <?php echo esc_html($p->post_title); ?>
                                </a>
                            </div>
                            <div style="display:flex; gap:12px; font-size:11px; color:var(--aap-text-muted);">
                                <span>📅 <?php echo esc_html(get_the_date('M j, Y', $p->ID)); ?></span>
                                <span>📝 ~<?php echo number_format($word_count); ?> words</span>
                                <span>
                                    <?php if ( get_post_meta($p->ID, '_aap_generated', true) ): ?>
                                    <span style="background:rgba(99,102,241,0.15); color:#6366f1; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">AI Generated</span>
                                    <?php else: ?>
                                    <span style="background:rgba(148,163,184,0.15); color:#475569; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600;">Manual</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Instruction Input + Buttons -->
                        <div style="display:flex; align-items:center; gap:8px; flex-shrink:0; flex-wrap:wrap;">
                            <input type="text"
                                id="aap-rewrite-instructions-<?php echo $p->ID; ?>"
                                class="aap-input"
                                placeholder="Optional: custom instructions..."
                                style="width:260px; font-size:12px; padding:8px 12px; border-radius:6px; border: 1px solid var(--aap-border);">
                            <button type="button"
                                class="aap-btn aap-btn-primary aap-btn-small aap-btn-rewrite-post"
                                data-post-id="<?php echo $p->ID; ?>"
                                data-save="preview"
                                style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border:none; color:#fff; white-space:nowrap; border-radius:6px; font-weight:600; padding:8px 16px;">
                                🔄 Rewrite Preview
                            </button>
                        </div>
                    </div>

                    <!-- Preview Container (hidden by default) -->
                    <div id="aap-rewrite-preview-<?php echo $p->ID; ?>" style="display:none; margin-top:12px;"></div>
                </div>
                <?php endforeach; ?>
            </div>

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
