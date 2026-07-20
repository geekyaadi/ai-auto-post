<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<div class="aap-wrap">

    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Generator</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link active">Generate Post</a>
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

        <?php
        $key_stats = AAP_Key_Manager::get_stats();
        if ( $key_stats['total'] === 0 ):
        ?>
        <div class="aap-alert aap-alert-warning">
            <span class="aap-alert-icon">⚠️</span>
            No API keys configured. <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>">Add your Gemini API key →</a>
        </div>
        <?php endif; ?>

        <?php if ( $key_stats['active'] === 0 && $key_stats['total'] > 0 ): ?>
        <div class="aap-alert aap-alert-error">
            <span class="aap-alert-icon">🔴</span>
            All API keys are currently exhausted. Keys auto-reset after <?php echo (int) get_option('aap_key_reset_minutes', 60); ?> minutes, or you can <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>">manually reset them</a>.
        </div>
        <?php endif; ?>

        <div class="aap-two-col">

            <!-- LEFT: Generation Form -->
            <div class="aap-panel aap-generate-panel">
                <div class="aap-panel-header">
                    <h2 class="aap-panel-title">✦ Generate New Post</h2>
                    <div class="aap-key-badge">
                        <span class="aap-key-dot <?php echo $key_stats['active'] > 0 ? 'active' : 'inactive'; ?>"></span>
                        <span><?php echo $key_stats['active']; ?>/<?php echo $key_stats['total']; ?> Keys Active</span>
                    </div>
                </div>

                <!-- Step 1: Niche Input -->
                <div class="aap-step" id="aap-step-niche">
                    <div class="aap-step-number">1</div>
                    <div class="aap-step-body">
                        <label class="aap-label" for="aap-niche-input">Enter Your Niche or Topic</label>
                        <input type="text" id="aap-niche-input" class="aap-input"
                            placeholder="e.g. Personal Finance, Fitness for Beginners, AI Tools..."
                            value="" autocomplete="off" style="margin-bottom:12px;" />

                        <label class="aap-label" for="aap-keywords-input">Focus Keywords (SEO)</label>
                        <input type="text" id="aap-keywords-input" class="aap-input"
                            placeholder="e.g. passive income tips, smart investing (comma separated)"
                            value="" autocomplete="off" style="margin-bottom:14px;" />

                        <button id="aap-btn-find-titles" class="aap-btn aap-btn-primary aap-btn-full" style="display:block; width:100%;">
                            <span class="aap-btn-icon">🔍</span> Find Titles
                        </button>
                        <div class="aap-hint" style="margin-top:8px;">AI will suggest 5 engaging title ideas tailored to your niche and keywords.</div>
                    </div>
                </div>

                <!-- Step 2: Title Selection -->
                <div class="aap-step aap-step-locked" id="aap-step-titles">
                    <div class="aap-step-number">2</div>
                    <div class="aap-step-body">
                        <label class="aap-label">Choose a Title</label>
                        <div id="aap-titles-list" class="aap-titles-list">
                            <div class="aap-titles-placeholder">Titles will appear here after Step 1</div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Options -->
                <div class="aap-step aap-step-locked" id="aap-step-options">
                    <div class="aap-step-number">3</div>
                    <div class="aap-step-body">
                        <label class="aap-label">Post Options</label>
                        <div class="aap-options-row" style="grid-template-columns: 1fr 1fr; margin-bottom: 12px;">
                            <div class="aap-option-group">
                                <label class="aap-option-label">Status</label>
                                <select id="aap-post-status" class="aap-select">
                                    <option value="draft" <?php selected( get_option('aap_default_status','draft'), 'draft' ); ?>>Draft</option>
                                    <option value="publish" <?php selected( get_option('aap_default_status','draft'), 'publish' ); ?>>Published</option>
                                </select>
                            </div>
                            <div class="aap-option-group">
                                <label class="aap-option-label">Tags Count</label>
                                <select id="aap-tag-count" class="aap-select">
                                    <?php
                                    $saved_count = (int) get_option( 'aap_tag_count', 15 );
                                    foreach ( [5, 10, 15, 20, 25, 30, 40, 50, 75, 100] as $tc ):
                                    ?>
                                    <option value="<?php echo $tc; ?>" <?php selected( $saved_count, $tc ); ?>><?php echo $tc; ?> tags</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="aap-options-row" style="grid-template-columns: 1fr; margin-bottom: 12px;">
                            <div class="aap-option-group">
                                <label class="aap-option-label">Category</label>
                                <select id="aap-post-category" class="aap-select" style="width: 100%;">
                                    <option value="">⚙️ Auto-Suggest (Gemini)</option>
                                    <?php 
                                    $categories = get_categories( [ 'hide_empty' => false ] );
                                    foreach ( $categories as $cat ): 
                                    ?>
                                    <option value="<?php echo esc_attr( $cat->name ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Thumbnail Generation Options -->
                        <div class="aap-options-row" style="grid-template-columns: 1fr 1fr; margin-bottom: 12px;">
                            <div class="aap-option-group">
                                <label class="aap-option-label">Thumbnail Method</label>
                                <select id="aap-thumb-type" class="aap-select">
                                    <option value="ai" <?php selected( get_option('aap_thumb_type','ai'), 'ai' ); ?>>AI Generated (Gemini)</option>
                                    <option value="text_to_image" <?php selected( get_option('aap_thumb_type','ai'), 'text_to_image' ); ?>>Title to Image (Local GD)</option>
                                </select>
                            </div>
                             <div class="aap-option-group aap-t2i-only" style="display:none;">
                                 <label class="aap-option-label">Background Selection</label>
                                  <select id="aap-t2i-bg-type" class="aap-select">
                                      <option value="gradient" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'gradient' ); ?>>Gradient Background</option>
                                      <option value="solid" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'solid' ); ?>>Solid Color Background</option>
                                      <option value="image" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'image' ); ?>>Default Image Background (admin/default-thumbnail.jpg)</option>
                                      <option value="mix" <?php selected( get_option('aap_t2i_bg_type','gradient'), 'mix' ); ?>>🎲 Mix Background (Randomize)</option>
                                  </select>
                             </div>
                        </div>

                        <div class="aap-options-row aap-t2i-only" style="grid-template-columns: 1fr 1fr; margin-bottom: 12px; display:none;">
                            <div class="aap-option-group" id="aap-t2i-gradient-group">
                                <label class="aap-option-label">Gradient Color Palette</label>
                                <select id="aap-t2i-bg-val-gradient" class="aap-select">
                                    <?php 
                                    $saved_bg = get_option('aap_t2i_bg_val', 'blue_purple');
                                    foreach ( AAP_Text_To_Image::get_gradients() as $key => $g ): 
                                    ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_bg, $key); ?>><?php echo esc_html($g['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="aap-option-group" id="aap-t2i-solid-group" style="display:none;">
                                <label class="aap-option-label">Solid Color Background</label>
                                <select id="aap-t2i-bg-val-solid" class="aap-select">
                                    <?php 
                                    $saved_bg = get_option('aap_t2i_bg_val', 'dark_slate');
                                    foreach ( AAP_Text_To_Image::get_solid_colors() as $key => $s ): 
                                    ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_bg, $key); ?>><?php echo esc_html($s['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="aap-option-group">
                                <label class="aap-option-label">Image Ratio &amp; Size</label>
                                <select id="aap-t2i-size" class="aap-select">
                                    <option value="600x315" <?php selected(get_option('aap_t2i_size','600x315'), '600x315'); ?>>Landscape (2:1) — 600×315 px</option>
                                    <option value="1200x630" <?php selected(get_option('aap_t2i_size','600x315'), '1200x630'); ?>>OpenGraph (2:1) — 1200×630 px</option>
                                    <option value="500x500" <?php selected(get_option('aap_t2i_size','600x315'), '500x500'); ?>>Square (1:1) — 500×500 px</option>
                                    <option value="1000x1000" <?php selected(get_option('aap_t2i_size','600x315'), '1000x1000'); ?>>Square High-Res (1:1) — 1000×1000 px</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reference Image Upload (AI Generated Only) -->
                        <div class="aap-ref-img-section aap-ai-thumb-only">
                            <div class="aap-ref-img-header">
                                <span class="aap-ref-img-title">🖼️ Thumbnail Style Reference</span>
                                <?php $has_default = ! empty( AAP_Gemini::get_default_reference_image() ); ?>
                                <?php if ( $has_default ): ?>
                                <span class="aap-badge aap-badge-default" title="A default reference image is set in Settings">✦ Default Set</span>
                                <?php endif; ?>
                                <span class="aap-ref-img-hint">Upload a sample image — Gemini will match its style for the 600×315 thumbnail</span>
                            </div>

                            <div class="aap-upload-zone" id="aap-upload-zone">
                                <div class="aap-upload-idle" id="aap-upload-idle">
                                    <span class="aap-upload-icon">📁</span>
                                    <span class="aap-upload-text">Drag & drop an image here, or <label for="aap-ref-img-input" class="aap-upload-link">browse</label></span>
                                    <span class="aap-upload-sub">JPG, PNG, WEBP — max 4MB</span>
                                </div>
                                <div class="aap-upload-preview" id="aap-upload-preview" style="display:none;">
                                    <img id="aap-ref-img-thumb" src="" alt="Reference preview">
                                    <div class="aap-upload-preview-info">
                                        <span id="aap-ref-img-name" class="aap-upload-filename"></span>
                                        <button type="button" id="aap-btn-clear-ref" class="aap-btn-small aap-btn-danger">✕ Remove</button>
                                    </div>
                                </div>
                                <input type="file" id="aap-ref-img-input" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                            </div>

                            <?php if ( $has_default ): ?>
                            <div class="aap-ref-default-note" id="aap-ref-default-note">
                                <span>ℹ️ Using your <a href="<?php echo admin_url('admin.php?page=aap-settings#thumbnail-ref'); ?>">default reference image</a> from Settings. Upload above to override for this post only.</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="aap-action-buttons">
                            <button id="aap-btn-preview" class="aap-btn aap-btn-secondary">
                                <span>👁</span> Preview First
                            </button>
                            <button id="aap-btn-generate" class="aap-btn aap-btn-primary" disabled>
                                <span>⚡</span> Generate &amp; Publish
                            </button>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="aap-session-id" value="">
            </div>

            <!-- RIGHT: Progress + Result -->
            <div class="aap-panel aap-progress-panel">
                <div class="aap-panel-header">
                    <h2 class="aap-panel-title">📊 Generation Progress</h2>
                </div>

                <div id="aap-progress-idle" class="aap-progress-idle">
                    <div class="aap-idle-icon">✦</div>
                    <p>Enter a niche and select a title to begin generation.</p>
                </div>

                <div id="aap-progress-steps" class="aap-progress-steps" style="display:none;">
                    <?php
                    $steps = [
                        'article'   => ['icon' => '📝', 'label' => 'Writing Article (~1000 words)'],
                        'tags'      => ['icon' => '🏷️', 'label' => 'Generating Tags'],
                        'meta'      => ['icon' => '🔍', 'label' => 'Creating Meta Description'],
                        'category'  => ['icon' => '📂', 'label' => 'Assigning Category'],
                        'thumbnail' => ['icon' => '🖼️', 'label' => 'Generating Thumbnail'],
                        'og_image'  => ['icon' => '📸', 'label' => 'Creating OG Image (1200×630)'],
                        'alt_text'  => ['icon' => '♿', 'label' => 'Writing Alt Text'],
                        'publish'   => ['icon' => '🚀', 'label' => 'Publishing Post'],
                    ];
                    foreach ( $steps as $key => $step ):
                    ?>
                    <div class="aap-progress-step" id="aap-pstep-<?php echo esc_attr($key); ?>" data-step="<?php echo esc_attr($key); ?>">
                        <div class="aap-pstep-icon"><?php echo $step['icon']; ?></div>
                        <div class="aap-pstep-body">
                            <div class="aap-pstep-label"><?php echo esc_html($step['label']); ?></div>
                            <div class="aap-pstep-meta"></div>
                        </div>
                        <div class="aap-pstep-status">
                            <span class="aap-pstep-dot waiting"></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Key switch notification -->
                <div id="aap-key-switch-notice" class="aap-key-switch-notice" style="display:none;">
                    <span class="aap-ksn-icon">⚡</span>
                    <span id="aap-key-switch-text"></span>
                </div>

                <!-- Result -->
                <div id="aap-result" class="aap-result" style="display:none;"></div>

                <!-- Preview Modal -->
                <div id="aap-preview-panel" class="aap-preview-panel" style="display:none;">
                    <div class="aap-preview-header">
                        <h3 id="aap-preview-title"></h3>
                        <div class="aap-preview-meta">
                            <span id="aap-preview-category" class="aap-preview-tag"></span>
                            <span id="aap-preview-meta-desc" class="aap-preview-meta-desc"></span>
                        </div>
                    </div>
                    <div id="aap-preview-content" class="aap-preview-content"></div>
                    <div class="aap-preview-tags">
                        <strong>Tags:</strong>
                        <span id="aap-preview-tags"></span>
                    </div>
                    <div class="aap-preview-actions">
                        <button id="aap-btn-confirm-publish" class="aap-btn aap-btn-primary">✅ Looks Good — Publish</button>
                        <button id="aap-btn-cancel-preview" class="aap-btn aap-btn-ghost">✏️ Back to Edit</button>
                    </div>
                </div>

            </div>

        </div><!-- .aap-two-col -->

    </div><!-- .aap-content -->
</div>
