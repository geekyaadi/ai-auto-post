<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$categories = get_categories( [ 'hide_empty' => false ] );
$languages  = [
    'English'    => 'English',
    'Hindi'      => 'Hindi (हिन्दी)',
    'Spanish'    => 'Spanish (Español)',
    'French'     => 'French (Français)',
    'German'     => 'German (Deutsch)',
    'Italian'    => 'Italian (Italiano)',
    'Portuguese' => 'Portuguese (Português)',
    'Arabic'     => 'Arabic (العربية)',
    'Russian'    => 'Russian (Русский)',
    'Japanese'   => 'Japanese (日本語)',
    'Bengali'    => 'Bengali (বাংলা)',
];
?>

<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Bulk Planner</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link active">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
<a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link">Settings</a>
            </div>
        </div>
    </div>

    <div class="aap-content">
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🗓️ Bulk Article Planner</h2>
                <div class="aap-hint">Plan multiple articles at once. The background queue processor will auto-generate and publish them.</div>
            </div>

            <!-- Planner Form -->
            <div class="aap-planner-form-row" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; margin-bottom:10px;">
                <div class="aap-field-planner-niche" style="flex:2; min-width:250px;">
                    <label class="aap-label">Niche or Topic</label>
                    <input type="text" id="aap-planner-niche" class="aap-input" placeholder="e.g. Smart Gardening, Weight Loss tips..." autocomplete="off">
                </div>
                <div class="aap-field-planner-mode" style="flex:1; min-width:180px;">
                    <label class="aap-label">Planner Mode</label>
                    <select id="aap-planner-mode" class="aap-select">
                        <option value="standard">Standard Plan</option>
                        <option value="silo">Pillar & Silo (1 Pillar + 5 Clusters)</option>
                    </select>
                </div>
                <div class="aap-field-planner-count" id="aap-planner-count-wrapper" style="flex:1; min-width:120px;">
                    <label class="aap-label">Number of Posts</label>
                    <select id="aap-planner-count" class="aap-select">
                        <option value="5">5 Posts</option>
                        <option value="10">10 Posts</option>
                        <option value="15">15 Posts</option>
                        <option value="20" selected>20 Posts (Default)</option>
                        <option value="25">25 Posts</option>
                        <option value="30">30 Posts</option>
                        <option value="35">35 Posts</option>
                        <option value="40">40 Posts</option>
                        <option value="45">45 Posts</option>
                        <option value="50">50 Posts</option>
                    </select>
                </div>
                <div class="aap-field-planner-lang" style="flex:1; min-width:120px;">
                    <label class="aap-label">Language</label>
                    <select id="aap-planner-lang" class="aap-select">
                        <?php foreach ( $languages as $key => $lbl ): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aap-field-planner-cat" style="flex:1; min-width:180px;">
                    <label class="aap-label">Default Category</label>
                    <select id="aap-planner-default-cat" class="aap-select">
                        <option value="">— Suggest category automatically —</option>
                        <?php foreach ( $categories as $cat ): ?>
                        <option value="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:0 0 auto; min-width:120px;">
                    <label class="aap-label">Tags Count</label>
                    <select id="aap-planner-tag-count" class="aap-select">
                        <?php
                        $saved_count = (int) get_option( 'aap_tag_count', 15 );
                        foreach ( [5, 10, 15, 20, 25, 30, 40, 50, 75, 100] as $tc ):
                        ?>
                        <option value="<?php echo $tc; ?>" <?php selected( $saved_count, $tc ); ?>><?php echo $tc; ?> tags</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aap-field-planner-btn" style="margin-bottom:2px;">
                    <button type="button" id="aap-btn-planner-find" class="aap-btn aap-btn-primary">
                        🔍 Generate Plan
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Thumbnail Settings -->
        <div class="aap-panel" style="margin-top:20px;">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🖼️ Bulk Thumbnail Settings</h2>
            </div>
            <div class="aap-settings-grid">
                <div class="aap-field" style="margin-bottom:0;">
                    <label class="aap-label">Thumbnail Method</label>
                    <select id="aap-planner-thumb-type" class="aap-select">
                        <option value="ai" <?php selected( get_option('aap_thumb_type','ai'), 'ai' ); ?>>AI Generated (Gemini)</option>
                        <option value="text_to_image" <?php selected( get_option('aap_thumb_type','ai'), 'text_to_image' ); ?>>Title to Image (Local GD)</option>
                    </select>
                </div>
                <div class="aap-field aap-t2i-only" style="display:none; margin-bottom:0;">
                    <label class="aap-label">Background Selection</label>
                    <select id="aap-planner-t2i-bg-type" class="aap-select">
                        <option value="gradient" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'gradient' ); ?>>Gradient Background</option>
                        <option value="solid" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'solid' ); ?>>Solid Color Background</option>
                        <option value="image" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'image' ); ?>>Default Image Background (admin/default-thumbnail.jpg)</option>
                        <option value="mix" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'mix' ); ?>>🎲 Mix Background (Randomize)</option>
                    </select>
                </div>
                <div class="aap-field aap-t2i-only" id="aap-planner-t2i-gradient-group" style="display:none; margin-bottom:0;">
                    <label class="aap-label">Gradient Color Palette</label>
                    <select id="aap-planner-t2i-bg-val-gradient" class="aap-select">
                        <?php 
                        $saved_bg = get_option('aap_t2i_bg_val', 'blue_purple');
                        foreach ( AAP_Text_To_Image::get_gradients() as $key => $g ): 
                        ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_bg, $key); ?>><?php echo esc_html($g['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aap-field aap-t2i-only" id="aap-planner-t2i-solid-group" style="display:none; margin-bottom:0;">
                    <label class="aap-label">Solid Color Background</label>
                    <select id="aap-planner-t2i-bg-val-solid" class="aap-select">
                        <?php 
                        $saved_bg = get_option('aap_t2i_bg_val', 'dark_slate');
                        foreach ( AAP_Text_To_Image::get_solid_colors() as $key => $s ): 
                        ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_bg, $key); ?>><?php echo esc_html($s['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aap-field aap-t2i-only" style="display:none; margin-bottom:0;">
                    <label class="aap-label">Image Ratio &amp; Size</label>
                    <select id="aap-planner-t2i-size" class="aap-select">
                        <option value="600x315" <?php selected(get_option('aap_t2i_size','600x315'), '600x315'); ?>>Landscape (2:1) — 600×315 px</option>
                        <option value="1200x630" <?php selected(get_option('aap_t2i_size','600x315'), '1200x630'); ?>>OpenGraph (2:1) — 1200×630 px</option>
                        <option value="500x500" <?php selected(get_option('aap_t2i_size','600x315'), '500x500'); ?>>Square (1:1) — 500×500 px</option>
                        <option value="1000x1000" <?php selected(get_option('aap_t2i_size','600x315'), '1000x1000'); ?>>Square High-Res (1:1) — 1000×1000 px</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Planned Titles Table (Hidden initially) -->
        <div class="aap-panel" id="aap-planner-results-panel" style="display:none;">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">✏️ Edit & Select Planned Titles</h2>
                <div class="aap-panel-actions">
                    <button type="button" id="aap-btn-select-all" class="aap-btn aap-btn-ghost aap-btn-sm">Select All</button>
                    <button type="button" id="aap-btn-deselect-all" class="aap-btn aap-btn-ghost aap-btn-sm">Deselect All</button>
                </div>
            </div>

            <table class="aap-table" id="aap-planner-table">
                <thead>
                    <tr>
                        <th width="40"><input type="checkbox" id="aap-check-master" checked></th>
                        <th>#</th>
                        <th>Post Title (You can edit this)</th>
                        <th>Target Category</th>
                    </tr>
                </thead>
                <tbody id="aap-planner-table-body">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>

            <div class="aap-form-actions" style="margin-top:20px;">
                <button type="button" id="aap-btn-save-tasks" class="aap-btn aap-btn-primary">
                    💾 Save Selected as Background Tasks
                </button>
            </div>
        </div>

        <!-- Categories source template data (Hidden) -->
        <select id="aap-cat-template-source" style="display:none;">
            <option value="">— Auto Suggest Category —</option>
            <?php foreach ( $categories as $cat ): ?>
            <option value="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($cat->name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
