<?php if ( ! defined( 'ABSPATH' ) ) exit;
$keys          = AAP_Key_Manager::get_all_keys();
$reset_minutes = (int) get_option( 'aap_key_reset_minutes', 60 );
$msg_map       = [
    'key_added'   => ['type'=>'success','text'=>'✅ API key added successfully.'],
    'key_exists'  => ['type'=>'warning','text'=>'⚠️ This API key already exists.'],
    'key_empty'   => ['type'=>'error',  'text'=>'❌ API key cannot be empty.'],
    'key_deleted' => ['type'=>'success','text'=>'✅ API key deleted.'],
    'key_reset'   => ['type'=>'success','text'=>'✅ API key reset to active.'],
    'saved'       => ['type'=>'success','text'=>'✅ Settings saved.'],
];
$msg = $_GET['msg'] ?? ( isset($_GET['saved']) ? 'saved' : '' );

// Default Prompts for Prefilling
$default_prompt_titles  = "Generate exactly {count} highly engaging, CTR-optimized, SEO blog post title ideas for the niche: \"{niche}\". Output as a JSON array of title strings.";
$default_prompt_article = "Write a comprehensive, 100% unique, human-like, SEO-optimized blog post titled: \"{title}\". Language: {language}. Word Count: {word_count}. Tone: {tone}. Use proper HTML tags (h2, h3, p, ul, ol, strong, em).";
$default_prompt_meta    = "Write an SEO-optimized meta description (max 160 characters) for a blog post titled: \"{title}\". Language: {language}.";
$default_prompt_tags    = "Generate exactly {tag_count} relevant, specific SEO tags for a blog post titled: \"{title}\". Language: {language}. Return as a JSON array.";
$default_prompt_faq     = "Generate exactly {faq_count} relevant Frequently Asked Questions with detailed answers for a blog post titled: \"{title}\". Language: {language}. Return as a JSON array of objects with \"question\" and \"answer\" keys.";

$val_prompt_titles  = get_option( 'aap_prompt_titles', '' );
if ( empty( $val_prompt_titles ) ) $val_prompt_titles = $default_prompt_titles;

$val_prompt_article = get_option( 'aap_prompt_article', '' );
if ( empty( $val_prompt_article ) ) $val_prompt_article = $default_prompt_article;

$val_prompt_meta    = get_option( 'aap_prompt_meta', '' );
if ( empty( $val_prompt_meta ) ) $val_prompt_meta = $default_prompt_meta;

$val_prompt_tags    = get_option( 'aap_prompt_tags', '' );
if ( empty( $val_prompt_tags ) ) $val_prompt_tags = $default_prompt_tags;

$val_prompt_faq     = get_option( 'aap_prompt_faq', '' );
if ( empty( $val_prompt_faq ) ) $val_prompt_faq = $default_prompt_faq;
?>
<div class="aap-wrap">
    <div class="aap-header">
        <div class="aap-header-inner">
            <div class="aap-logo">
                <img src="<?php echo esc_url( AAP_PLUGIN_URL . 'admin/ai-auto-post-by-aadi.png' ); ?>" alt="Logo" style="height:32px; width:auto; vertical-align:middle; margin-right:10px; border-radius:4px;">
                <span class="aap-logo-badge">Settings (v<?php echo AAP_VERSION; ?>)</span>
            </div>
            <div class="aap-header-nav">
                <a href="<?php echo admin_url('admin.php?page=ai-auto-post'); ?>" class="aap-nav-link">Dashboard</a>
                <a href="<?php echo admin_url('admin.php?page=aap-generate'); ?>" class="aap-nav-link">Generate Post</a>
                <a href="<?php echo admin_url('admin.php?page=aap-planner'); ?>" class="aap-nav-link">Bulk Planner</a>
                <a href="<?php echo admin_url('admin.php?page=aap-scheduler'); ?>" class="aap-nav-link">Scheduler</a>
                <a href="<?php echo admin_url('admin.php?page=aap-thumbnails'); ?>" class="aap-nav-link">Thumbnail Manager</a>
                <a href="<?php echo admin_url('admin.php?page=aap-gsc'); ?>" class="aap-nav-link">Google Indexing</a>
                <a href="<?php echo admin_url('admin.php?page=aap-rewriter'); ?>" class="aap-nav-link">Article Rewriter</a>
                <a href="<?php echo admin_url('admin.php?page=aap-settings'); ?>" class="aap-nav-link active">Settings</a>
            </div>
        </div>
    </div>
    <div class="aap-content">

        <?php if ( $msg && isset($msg_map[$msg]) ): ?>
        <div class="aap-alert aap-alert-<?php echo $msg_map[$msg]['type']; ?>">
            <?php echo esc_html($msg_map[$msg]['text']); ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================ -->
        <!-- API KEYS -->
        <!-- ============================================================ -->
        <div class="aap-panel">
            <div class="aap-panel-header">
                <h2 class="aap-panel-title">🔑 API Key Pool</h2>
                <div class="aap-key-badge">
                    <?php $stats = AAP_Key_Manager::get_stats(); ?>
                    <span class="aap-key-dot <?php echo $stats['active']>0?'active':'inactive'; ?>"></span>
                    <?php echo $stats['active']; ?> Active / <?php echo $stats['total']; ?> Total
                </div>
            </div>

            <!-- Add key -->
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="aap-add-key-form">
                <?php wp_nonce_field('aap_add_key'); ?>
                <input type="hidden" name="action" value="aap_add_key">
                <div class="aap-add-key-inputs">
                    <div class="aap-field-provider">
                        <select name="api_key_provider" class="aap-select" id="aap-key-provider-select">
                            <option value="gemini">Google Gemini</option>
                            <option value="openai">OpenAI ChatGPT</option>
                        </select>
                    </div>
                    <div class="aap-field-key-input">
                        <input type="text" name="api_key" class="aap-input" id="aap-key-input-field" placeholder="Paste Gemini API key here (AIza...)" autocomplete="off">
                    </div>
                    <button type="submit" class="aap-btn aap-btn-primary">➕ Add Key</button>
                </div>
                <div class="aap-hint" id="aap-add-key-hint">Get your free Gemini API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio →</a></div>
            </form>

            <!-- Key table -->
            <?php if ( empty($keys) ): ?>
            <div class="aap-empty-state">No API keys added yet.</div>
            <?php else: ?>
            <div class="aap-key-table-toolbar">
                <span class="aap-hint"><?php echo count($keys); ?> key(s) configured</span>
                <button type="button" id="aap-btn-ping-all" class="aap-btn aap-btn-secondary aap-btn-sm">
                    🏓 Ping All Keys
                </button>
            </div>
            <table class="aap-table" id="aap-keys-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Provider</th>
                        <th>API Key</th>
                        <th>Status</th>
                        <th>Resets In</th>
                        <th>Requests</th>
                        <th>Tokens Used</th>
                        <th>Last Ping</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $keys as $i => $k ):
                        $reset_secs = AAP_Key_Manager::seconds_until_reset( $k );
                        $status_cls = $k['status'] === 'active' ? 'active' :
                                     ( $k['status'] === 'invalid' ? 'invalid' : 'exhausted' );
                        $provider   = $k['provider'] ?? 'gemini';
                    ?>
                    <tr class="aap-key-row <?php echo $k['status'] !== 'active' ? 'aap-row-exhausted' : ''; ?>"
                        data-key-index="<?php echo $i; ?>"
                        data-reset-ts="<?php echo (int)($k['reset_at_ts'] ?? 0); ?>">
                        <td><?php echo $i+1; ?></td>
                        <td>
                            <span class="aap-badge aap-badge-<?php echo esc_attr($provider); ?>">
                                <?php echo $provider === 'openai' ? 'OpenAI' : 'Gemini'; ?>
                            </span>
                        </td>
                        <td><code class="aap-key-masked"><?php echo esc_html(AAP_Key_Manager::mask_key($k['key'])); ?></code></td>
                        <td class="aap-key-status-cell">
                            <span class="aap-status-badge aap-status-<?php echo $status_cls; ?>">
                                <?php
                                if ( $k['status'] === 'active' )         echo '✅ Active';
                                elseif ( $k['status'] === 'invalid' )    echo '⛔ Invalid';
                                else                                      echo '🔴 Exhausted';
                                ?>
                            </span>
                        </td>
                        <td class="aap-key-countdown-cell">
                            <?php if ( $k['status'] === 'exhausted' && $reset_secs !== null ): ?>
                            <span class="aap-countdown" data-reset-ts="<?php echo (int)$k['reset_at_ts']; ?>">
                                ⏱ <span class="aap-countdown-val"><?php echo AAP_Key_Manager::format_seconds($reset_secs); ?></span>
                            </span>
                            <?php elseif ( $k['status'] === 'exhausted' ): ?>
                            <span class="aap-text-muted">Unknown</span>
                            <?php else: ?>
                            <span class="aap-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format((int)($k['requests']??0)); ?></td>
                        <td><?php
                            $t = (int)($k['tokens_used']??0);
                            echo $t > 0 ? '~' . number_format($t) : '—';
                        ?></td>
                        <td class="aap-key-ping-cell">
                            <?php if ( $k['last_ping_at'] ): ?>
                            <span class="aap-ping-badge aap-ping-<?php echo esc_attr($k['last_ping_status']??''); ?>">
                                <?php
                                $ps = $k['last_ping_status'] ?? '';
                                if ($ps === 'active')    echo '✅';
                                elseif ($ps === 'exhausted') echo '🔴';
                                elseif ($ps === 'invalid')   echo '⛔';
                                else echo '❓';
                                ?>
                            </span>
                            <span class="aap-ping-time"><?php echo esc_html(date('H:i', strtotime($k['last_ping_at']))); ?></span>
                            <?php else: ?>
                            <span class="aap-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="aap-actions">
                            <button type="button"
                                class="aap-btn-small aap-btn-ghost aap-btn-ping"
                                data-key-index="<?php echo $i; ?>"
                                title="Test this key with a minimal API call">
                                🏓
                            </button>
                            <?php if ( in_array($k['status'], ['exhausted','invalid'], true) ): ?>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                <?php wp_nonce_field('aap_reset_key'); ?>
                                <input type="hidden" name="action" value="aap_reset_key">
                                <input type="hidden" name="key_index" value="<?php echo $i; ?>">
                                <button class="aap-btn-small aap-btn-success" type="submit" title="Force reset to active">↺</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('Delete this API key?')">
                                <?php wp_nonce_field('aap_delete_key'); ?>
                                <input type="hidden" name="action" value="aap_delete_key">
                                <input type="hidden" name="key_index" value="<?php echo $i; ?>">
                                <button class="aap-btn-small aap-btn-danger" type="submit">✕</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ============================================================ -->
        <!-- SETTINGS FORM (Balanced Double Columns Layout) -->
        <!-- ============================================================ -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('aap_save_settings'); ?>
            <input type="hidden" name="action" value="aap_save_settings">

            <div class="aap-settings-layout-grid">
                
                <!-- ── COLUMN 1 (Core Settings, Internal Linking, Filters) ── -->
                <div class="aap-settings-column">
                    
                    <!-- CARD 1: CORE SETTINGS -->
                    <div class="aap-panel">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">⚙️ Core Settings</h2>
                        </div>
                        
                        <div class="aap-settings-grid">
                            <div class="aap-field">
                                <label class="aap-label">Default Post Status</label>
                                <select name="aap_default_status" class="aap-select">
                                    <option value="draft" <?php selected(get_option('aap_default_status','draft'),'draft'); ?>>Draft</option>
                                    <option value="publish" <?php selected(get_option('aap_default_status','draft'),'publish'); ?>>Published</option>
                                </select>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Default Author</label>
                                <?php
                                wp_dropdown_users([
                                    'name'             => 'aap_default_author',
                                    'selected'         => get_option('aap_default_author', get_current_user_id()),
                                    'class'            => 'aap-select',
                                    'who'              => 'authors',
                                ]);
                                ?>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Active AI Provider</label>
                                <select name="aap_active_provider" class="aap-select" id="aap-active-provider-select">
                                    <option value="gemini" <?php selected(get_option('aap_active_provider', 'gemini'), 'gemini'); ?>>Google Gemini</option>
                                    <option value="openai" <?php selected(get_option('aap_active_provider', 'gemini'), 'openai'); ?>>OpenAI ChatGPT</option>
                                </select>
                                <div class="aap-hint">Select the active service provider. Make sure keys are added for the active provider.</div>
                            </div>

                            <div class="aap-field" id="aap-field-gemini-model" style="display: <?php echo get_option('aap_active_provider', 'gemini') === 'gemini' ? 'block' : 'none'; ?>;">
                                <label class="aap-label">Active Gemini Text Model</label>
                                <select name="aap_text_model" class="aap-select">
                                    <?php foreach ( AAP_Rate_Limits::MODELS as $id => $m ): if ($m['type'] !== 'text' || ($m['provider'] ?? 'gemini') !== 'gemini') continue; ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected(AAP_Gemini::get_text_model(), $id); ?>>
                                        <?php echo esc_html($m['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="aap-hint">Select model. Gemini 3.1 Flash Lite offers the highest daily limit (500 free calls/day).</div>
                            </div>

                            <div class="aap-field" id="aap-field-openai-model" style="display: <?php echo get_option('aap_active_provider', 'gemini') === 'openai' ? 'block' : 'none'; ?>;">
                                <label class="aap-label">Active OpenAI Text Model</label>
                                <select name="aap_openai_model" class="aap-select">
                                    <?php foreach ( AAP_Rate_Limits::MODELS as $id => $m ): if ($m['type'] !== 'text' || ($m['provider'] ?? 'gemini') !== 'openai') continue; ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected(AAP_Gemini::get_text_model(), $id); ?>>
                                        <?php echo esc_html($m['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="aap-hint">Select ChatGPT model. GPT-4o Mini is highly recommended.</div>
                            </div>

                            <div class="aap-field" id="aap-field-gemini-image" style="display: <?php echo get_option('aap_active_provider', 'gemini') === 'gemini' ? 'block' : 'none'; ?>;">
                                <label class="aap-label">Active Gemini Image Model</label>
                                <select name="aap_image_model" class="aap-select">
                                    <?php foreach ( AAP_Rate_Limits::MODELS as $id => $m ): if ($m['type'] !== 'image' || ($m['provider'] ?? 'gemini') !== 'gemini') continue; ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected(AAP_Gemini::get_image_model(), $id); ?>>
                                        <?php echo esc_html($m['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="aap-hint">Select model for thumbnail/OG generation.</div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Article Word Count</label>
                                <select name="aap_word_count" class="aap-select">
                                    <?php foreach ([500,750,1000,1500,2000,2500] as $wc): ?>
                                    <option value="<?php echo $wc; ?>" <?php selected(get_option('aap_word_count',1000),$wc); ?>><?php echo $wc; ?> words</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Default Tag Count</label>
                                <select name="aap_tag_count" class="aap-select">
                                    <?php
                                    $saved_tag_count = (int) get_option( 'aap_tag_count', 15 );
                                    foreach ( [5, 10, 15, 20, 25, 30, 40, 50, 75, 100] as $tc ):
                                    ?>
                                    <option value="<?php echo $tc; ?>" <?php selected( $saved_tag_count, $tc ); ?>><?php echo $tc; ?> tags</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="aap-hint">Default number of SEO tags generated per post.</div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Content Tone</label>
                                <select name="aap_content_tone" class="aap-select">
                                    <?php foreach (['professional'=>'Professional','casual'=>'Casual','friendly'=>'Friendly','academic'=>'Academic','humorous'=>'Humorous'] as $v=>$l): ?>
                                    <option value="<?php echo $v; ?>" <?php selected(get_option('aap_content_tone','professional'),$v); ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Key Auto-Reset Interval</label>
                                <div class="aap-input-suffix">
                                    <input type="number" name="aap_key_reset_minutes" class="aap-input" value="<?php echo $reset_minutes; ?>" min="1" max="1440">
                                    <span class="aap-suffix">mins</span>
                                </div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Human Review Mode</label>
                                <label class="aap-toggle">
                                    <input type="checkbox" name="aap_review_mode" value="1" <?php checked(get_option('aap_review_mode',0),1); ?>>
                                    <span class="aap-toggle-slider"></span>
                                    <span class="aap-toggle-label">Always save as Draft</span>
                                </label>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Dynamic FAQ &amp; Schema Generator</label>
                                <label class="aap-toggle">
                                    <input type="checkbox" name="aap_enable_faq" value="1" <?php checked(get_option('aap_enable_faq',1),1); ?>>
                                    <span class="aap-toggle-slider"></span>
                                    <span class="aap-toggle-label">Enable FAQ Section &amp; Schema</span>
                                </label>
                                <div class="aap-hint">Auto-appends FAQ &amp; JSON-LD Schema.</div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Number of FAQs to Generate</label>
                                <select name="aap_faq_count" class="aap-select">
                                    <?php foreach ([3,4,5] as $fc): ?>
                                    <option value="<?php echo $fc; ?>" <?php selected(get_option('aap_faq_count',3),$fc); ?>><?php echo $fc; ?> FAQs</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06);">
                            <button type="submit" class="aap-btn aap-btn-primary" style="width: 100%; border-radius: 6px;">💾 Save Core Settings</button>
                        </div>
                    </div>

                    <!-- CARD 2: INTERNAL LINKING & INDEXING -->
                    <div class="aap-panel">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">🔗 Internal Linking &amp; Indexing</h2>
                        </div>
                        <div class="aap-settings-grid">
                            <div class="aap-field">
                                <label class="aap-label">Auto-Internal Linking</label>
                                <label class="aap-toggle">
                                    <input type="checkbox" name="aap_enable_internal_linking" value="1" <?php checked(get_option('aap_enable_internal_linking',0),1); ?>>
                                    <span class="aap-toggle-slider"></span>
                                    <span class="aap-toggle-label">Enable Auto-Internal Linking</span>
                                </label>
                                <div class="aap-hint">Automatically link keyword phrases in new posts to older posts.</div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Max Links Per Post</label>
                                <input type="number" name="aap_max_internal_links" class="aap-input" value="<?php echo esc_attr(get_option('aap_max_internal_links',3)); ?>" min="1" max="10">
                                <div class="aap-hint">Maximum number of internal links (recommended: 3).</div>
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">Google Indexing API (GSC)</label>
                                <label class="aap-toggle">
                                    <input type="checkbox" name="aap_enable_gsc_auto_ping" value="1" <?php checked(get_option('aap_enable_gsc_auto_ping',0),1); ?>>
                                    <span class="aap-toggle-slider"></span>
                                    <span class="aap-toggle-label">Auto-ping Google Indexing API</span>
                                </label>
                                <div class="aap-hint">Submits new posts automatically to Google search index on publish.</div>
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">Google Service Account JSON Key</label>
                                <div class="aap-gsc-tabs" style="display: flex; gap: 5px; margin-bottom: 10px; background: rgba(255,255,255,0.03); padding: 4px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); max-width: 100%;">
                                    <button type="button" class="aap-btn aap-gsc-tab-btn active" data-tab="paste" style="flex: 1; font-size: 11px; padding: 6px; border-radius: 4px; background: rgba(255,255,255,0.1); border: none; color: #fff; cursor: pointer; transition: all 0.2s;">📋 Paste JSON</button>
                                    <button type="button" class="aap-btn aap-gsc-tab-btn" data-tab="upload" style="flex: 1; font-size: 11px; padding: 6px; border-radius: 4px; background: transparent; border: none; color: #94a3b8; cursor: pointer; transition: all 0.2s;">📁 Upload file</button>
                                </div>
                                
                                <div class="aap-gsc-tab-content" id="aap-gsc-tab-paste">
                                    <textarea name="aap_gsc_json" id="aap_gsc_json_textarea" class="aap-textarea" rows="4" placeholder="Paste your Google Service Account key file (.json) content here..."><?php echo esc_textarea(get_option('aap_gsc_json','')); ?></textarea>
                                </div>
                                <div class="aap-gsc-tab-content" id="aap-gsc-tab-upload" style="display: none;">
                                    <div class="aap-gsc-upload-area" id="aap-gsc-drag-drop-zone" style="border: 2px dashed rgba(255,255,255,0.1); border-radius: 8px; padding: 20px; text-align: center; background: rgba(255,255,255,0.01); cursor: pointer; transition: border-color 0.2s;">
                                        <span style="font-size: 24px; display: block; margin-bottom: 8px;">📄</span>
                                        <span style="font-size: 12px; color: #e2e8f0; font-weight: 600;">Choose JSON File</span>
                                        <span style="font-size: 11px; color: #94a3b8; display: block; margin-top: 4px;">Click or drag file here</span>
                                    </div>
                                    <input type="file" id="aap-gsc-file-input" accept=".json" style="opacity: 0; position: absolute; width: 0; height: 0; z-index: -1;">
                                    <div id="aap-gsc-file-status" style="margin-top: 8px; font-size: 11px; font-weight: 600; text-align: center; color: #34d399;"></div>
                                </div>
                                <div class="aap-hint">Required for GSC Indexing Tool. Upload or paste the full JSON content from your Google Cloud Console Service Account key.</div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06);">
                            <button type="submit" class="aap-btn aap-btn-primary" style="width: 100%; border-radius: 6px;">💾 Save Indexing Settings</button>
                        </div>
                    </div>

                    <!-- CARD 3: CONTENT FILTERS -->
                    <div class="aap-panel">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">🚫 Content Filters</h2>
                        </div>
                        <div class="aap-field">
                            <label class="aap-label">Blacklist Words / Phrases</label>
                            <textarea name="aap_blacklist_words" class="aap-textarea" rows="3"
                                placeholder="Enter comma-separated words or phrases to exclude from all generated content e.g. casino, gambling, adult content"
                            ><?php echo esc_textarea(get_option('aap_blacklist_words','')); ?></textarea>
                            <div class="aap-hint">These words will be explicitly excluded from articles, tags, titles, and meta descriptions.</div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06);">
                            <button type="submit" class="aap-btn aap-btn-primary" style="width: 100%; border-radius: 6px;">💾 Save Filters</button>
                        </div>
                    </div>

                </div>
                
                <!-- ── COLUMN 2 (Thumbnail Generator, Custom Prompts, Style Reference) ── -->
                <div class="aap-settings-column">

                    <!-- CARD 1: THUMBNAIL GENERATOR & ENGAGEMENT -->
                    <div class="aap-panel">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">🖼️ Thumbnail Generator &amp; Engagement</h2>
                        </div>
                        <div class="aap-settings-grid">
                            
                            <div class="aap-field">
                                <label class="aap-label">Default Thumbnail Option</label>
                                <select name="aap_thumb_type" id="aap_thumb_type" class="aap-select">
                                    <option value="ai" <?php selected(get_option('aap_thumb_type','ai'), 'ai'); ?>>AI Generated Thumbnail (Gemini)</option>
                                    <option value="text_to_image" <?php selected(get_option('aap_thumb_type','ai'), 'text_to_image'); ?>>Title to Image (Local GD)</option>
                                </select>
                            </div>

                            <div class="aap-field aap-t2i-only" style="display:none;">
                                <label class="aap-label">Background Selection Type</label>
                                <select name="aap_t2i_bg_type" id="aap_t2i_bg_type" class="aap-select">
                                    <option value="gradient" <?php selected(get_option('aap_t2i_bg_type','gradient'), 'gradient'); ?>>Gradient Background</option>
                                    <option value="solid" <?php selected(get_option('aap_t2i_bg_type','gradient'), 'solid'); ?>>Solid Color Background</option>
                                    <option value="image" <?php selected(get_option('aap_t2i_bg_type','gradient'), 'image'); ?>>Default Image Background (admin/default-thumbnail.jpg)</option>
                                    <option value="mix" <?php selected(get_option('aap_t2i_bg_type','gradient'), 'mix'); ?>>🎲 Mix Background (Randomize)</option>
                                </select>
                            </div>

                            <div class="aap-field aap-t2i-only" id="aap-t2i-gradient-field" style="display:none;">
                                <label class="aap-label">Gradient Color Palette</label>
                                <select name="aap_t2i_bg_val_gradient" class="aap-select">
                                    <?php 
                                    $saved_bg = get_option('aap_t2i_bg_val', 'blue_purple');
                                    foreach ( AAP_Text_To_Image::get_gradients() as $key => $g ): 
                                    ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_bg, $key); ?>><?php echo esc_html($g['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aap-field aap-t2i-only" id="aap-t2i-solid-field" style="display:none;">
                                <label class="aap-label">Solid Color Background</label>
                                <select name="aap_t2i_bg_val_solid" class="aap-select">
                                    <?php 
                                    $saved_bg = get_option('aap_t2i_bg_val', 'dark_slate');
                                    foreach ( AAP_Text_To_Image::get_solid_colors() as $key => $s ): 
                                    ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($saved_bg, $key); ?>><?php echo esc_html($s['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aap-field aap-t2i-only" style="display:none;">
                                <label class="aap-label">Image Ratio &amp; Dimension</label>
                                <select name="aap_t2i_size" class="aap-select">
                                    <option value="600x315" <?php selected(get_option('aap_t2i_size','600x315'), '600x315'); ?>>Landscape (2:1) — 600 × 315 px</option>
                                    <option value="1200x630" <?php selected(get_option('aap_t2i_size','600x315'), '1200x630'); ?>>OpenGraph (2:1) — 1200 × 630 px</option>
                                    <option value="500x500" <?php selected(get_option('aap_t2i_size','600x315'), '500x500'); ?>>Square (1:1) — 500 × 500 px</option>
                                    <option value="1000x1000" <?php selected(get_option('aap_t2i_size','600x315'), '1000x1000'); ?>>Square High-Res (1:1) — 1000 × 1000 px</option>
                                </select>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Auto-Comment Generator</label>
                                <label class="aap-toggle">
                                    <input type="checkbox" name="aap_enable_comments" value="1" <?php checked(get_option('aap_enable_comments',0),1); ?>>
                                    <span class="aap-toggle-slider"></span>
                                    <span class="aap-toggle-label">Auto-generate comments for new posts</span>
                                </label>
                                <div class="aap-hint">Generates discussions on newly published articles.</div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Comments Count</label>
                                <select name="aap_comments_count" class="aap-select">
                                    <?php foreach ([1,2,3,4,5] as $cc): ?>
                                    <option value="<?php echo $cc; ?>" <?php selected(get_option('aap_comments_count',2),$cc); ?>><?php echo $cc; ?> Comments</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Thumbnail Text Overlay</label>
                                <label class="aap-toggle">
                                    <input type="checkbox" name="aap_enable_text_overlay" value="1" <?php checked(get_option('aap_enable_text_overlay',0),1); ?>>
                                    <span class="aap-toggle-slider"></span>
                                    <span class="aap-toggle-label">Overlay title on featured image</span>
                                </label>
                                <div class="aap-hint">Writes the post title directly on the AI-generated thumbnail.</div>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Overlay Position</label>
                                <select name="aap_overlay_position" class="aap-select">
                                    <option value="bottom" <?php selected(get_option('aap_overlay_position','bottom'),'bottom'); ?>>Bottom Banner</option>
                                    <option value="center" <?php selected(get_option('aap_overlay_position','bottom'),'center'); ?>>Centered Box</option>
                                    <option value="top" <?php selected(get_option('aap_overlay_position','bottom'),'top'); ?>>Top Banner</option>
                                </select>
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Text Color (Hex)</label>
                                <input type="text" name="aap_overlay_color" class="aap-input" value="<?php echo esc_attr(get_option('aap_overlay_color','#ffffff')); ?>" placeholder="#ffffff">
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Background Banner Color (Hex)</label>
                                <input type="text" name="aap_overlay_bg_color" class="aap-input" value="<?php echo esc_attr(get_option('aap_overlay_bg_color','#000000')); ?>" placeholder="#000000">
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Overlay Font Size (px)</label>
                                <input type="number" name="aap_overlay_font_size" class="aap-input" value="<?php echo esc_attr(get_option('aap_overlay_font_size',24)); ?>" min="12" max="64">
                            </div>

                            <div class="aap-field">
                                <label class="aap-label">Background Opacity (0–100)</label>
                                <input type="number" name="aap_overlay_bg_opacity" class="aap-input" value="<?php echo esc_attr(get_option('aap_overlay_bg_opacity',60)); ?>" min="0" max="100">
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06);">
                            <button type="submit" class="aap-btn aap-btn-primary" style="width: 100%; border-radius: 6px;">💾 Save Thumbnail Settings</button>
                        </div>
                    </div>

                    <!-- CARD 2: CUSTOM PROMPT TEMPLATES -->
                    <div class="aap-panel">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">📝 Custom Prompt Templates</h2>
                        </div>
                        <div class="aap-settings-grid">
                            <div class="aap-hint" style="margin-bottom:10px; line-height: 1.4; grid-column: 1 / -1;">
                                Merge tags supported: <code>{title}</code>, <code>{niche}</code>, <code>{keywords}</code>, <code>{language}</code>, <code>{word_count}</code>, <code>{tone}</code>, <code>{tag_count}</code>, <code>{faq_count}</code>.
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">Title Generation Prompt</label>
                                <textarea name="aap_prompt_titles" class="aap-textarea" rows="3"><?php echo esc_textarea($val_prompt_titles); ?></textarea>
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">Article Writing Prompt</label>
                                <textarea name="aap_prompt_article" class="aap-textarea" rows="4"><?php echo esc_textarea($val_prompt_article); ?></textarea>
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">Meta Description Prompt</label>
                                <textarea name="aap_prompt_meta" class="aap-textarea" rows="2"><?php echo esc_textarea($val_prompt_meta); ?></textarea>
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">Tags Prompt</label>
                                <textarea name="aap_prompt_tags" class="aap-textarea" rows="2"><?php echo esc_textarea($val_prompt_tags); ?></textarea>
                            </div>

                            <div class="aap-field aap-field-full">
                                <label class="aap-label">FAQs Prompt</label>
                                <textarea name="aap_prompt_faq" class="aap-textarea" rows="2"><?php echo esc_textarea($val_prompt_faq); ?></textarea>
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06);">
                            <button type="submit" class="aap-btn aap-btn-primary" style="width: 100%; border-radius: 6px;">💾 Save Prompt Templates</button>
                        </div>
                    </div>

                    <!-- CARD 3: DEFAULT THUMBNAIL REFERENCE IMAGE -->
                    <div class="aap-panel" id="thumbnail-ref">
                        <div class="aap-panel-header">
                            <h2 class="aap-panel-title">🖼️ Default Thumbnail Style Reference</h2>
                            <?php $default_ref = AAP_Gemini::get_default_reference_image(); ?>
                            <?php if ( ! empty( $default_ref ) ): ?>
                            <span class="aap-status-badge aap-status-active">✅ Image Set</span>
                            <?php else: ?>
                            <span class="aap-status-badge aap-status-exhausted">No image set</span>
                            <?php endif; ?>
                        </div>

                        <p class="aap-hint" style="margin-bottom:15px;">
                            Upload a reference image here to use as the default style guide for <strong>all</strong> AI-generated thumbnails.
                        </p>

                        <?php if ( ! empty( $default_ref ) ): ?>
                        <!-- Current default reference preview -->
                        <div class="aap-ref-current" id="aap-settings-ref-current" style="margin-bottom:15px;">
                            <div class="aap-ref-current-inner">
                                <img src="data:<?php echo esc_attr($default_ref['mime_type']); ?>;base64,<?php echo esc_attr($default_ref['base64']); ?>"
                                     id="aap-settings-ref-thumb"
                                     alt="Default reference image"
                                     style="max-height:100px;border-radius:8px;border:1px solid var(--aap-border);">
                                <div class="aap-ref-current-actions">
                                    <span class="aap-hint">Current default reference image</span>
                                    <button type="button" id="aap-btn-settings-delete-ref" class="aap-btn aap-btn-secondary aap-btn-small">
                                        🗑 Remove Default Image
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Upload Zone -->
                        <div class="aap-upload-zone aap-upload-zone-settings" id="aap-settings-upload-zone">
                            <div class="aap-upload-idle" id="aap-settings-upload-idle">
                                <span class="aap-upload-icon">📁</span>
                                <span class="aap-upload-text">
                                    Drag & drop image, or
                                    <label for="aap-settings-ref-input" class="aap-upload-link">browse to upload</label>
                                </span>
                                <span class="aap-upload-sub">JPG, PNG, WEBP — max 4MB</span>
                            </div>
                            <div class="aap-upload-preview" id="aap-settings-upload-preview" style="display:none;">
                                <img id="aap-settings-ref-preview" src="" alt="Preview">
                                <div class="aap-upload-preview-info">
                                    <span id="aap-settings-ref-name" class="aap-upload-filename"></span>
                                    <button type="button" id="aap-btn-settings-save-ref" class="aap-btn aap-btn-primary aap-btn-small">
                                        💾 Save
                                    </button>
                                    <button type="button" id="aap-btn-settings-clear-ref" class="aap-btn aap-btn-ghost aap-btn-small">
                                        ✕ Cancel
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="aap-settings-ref-input"
                                   accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="display:none;">
                        </div>

                        <div id="aap-settings-ref-msg" style="margin-top:10px;display:none;"></div>
                    </div>

                </div>

            </div>

            <!-- SAVE SETTINGS FLOATING BAR -->
            <div class="aap-form-actions" style="margin-top:15px; display: flex; justify-content: flex-end;">
                <button type="submit" class="aap-btn aap-btn-primary" style="padding: 10px 24px; font-weight: 600; border-radius: 6px;">💾 Save All Settings</button>
            </div>
        </form>

    </div><!-- .aap-content -->
</div>
