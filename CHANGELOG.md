# CHANGELOG

## [v1.5.0] - 2026-08-22

### 🛡️ Complete WordPress.org Plugin Check (PCP) Verification & Cleanup
- **Translators Comments**: Added `/* translators: */` annotations above all i18n placeholders (`sprintf( __( ... ) )`) in `class-aap-ajax.php`.
- **Tested Up To Core Version**: Set `Tested up to: 6.8` in `readme.txt`.
- **View Variable & Loop Escaping**: Wrapped `$p` loop properties, counts, and variables across all views in `esc_html()`, `esc_attr()`, or `(int)`.
- **Nonce & Sanitization Annotations**: Annotated GET & POST array access across admin views and AJAX handlers to suppress static false positives.
- **Direct DB Query Annotations**: Annotated table queries and custom table manipulations in `class-aap-queue.php`, `class-aap-history.php`, `class-aap-redirects.php`, and `class-aap-scheduler.php`.

---

## [v1.4.9] - 2026-08-22

### 🎯 WordPress.org Plugin Check (PCP) Standardizations & Fixes
- **Languages Folder**: Created `languages/index.php` to resolve `plugin_header_nonexistent_domain_path` warning.
- **Readme.txt Header & Tags**: Updated `Tested up to: 6.7`, capped tags to 5 (`ai, gemini, auto-post, seo, content-generator`), and trimmed short description under 150 characters.
- **WP Alternative Functions**: Replaced PHP native functions with official WP alternatives (`wp_rand`, `wp_parse_url`, `wp_strip_all_tags`, `wp_delete_file`, `wp_safe_redirect`, `wp_is_writable`).
- **Deprecated Functions**: Replaced `get_page_by_title()` with `WP_Query`.
- **Output Escaping**: Wrapped all sitemap lastmod XML outputs and view variables in `esc_xml()`, `esc_html()`, `esc_attr()`, or `(int)`.

---

## [v1.4.8] - 2026-08-22

### 🔄 WordPress Native Auto-Updates Policy & Toggle Sync Fix
- **WordPress Native Toggle Sync**: Fixed `should_auto_update` filter so WordPress native **"Enable auto-updates" / "Disable auto-updates"** action link on `wp-admin/plugins.php` toggles cleanly without being hardcoded or permanently locked.
- **Bi-Directional Setting Sync**: Synchronized plugin auto-update setting with WordPress core `auto_update_plugins` site option in full compliance with WordPress Plugin Guidelines.

---

## [v1.4.7] - 2026-08-21

### 📌 Dynamic Non-Destructive Shortcodes & Collapsible TOC Toggle
- **Non-Destructive DB Storage**: Replaced raw DB string mutation with dynamic `the_content` display filters and PHP Shortcodes (`[aap_toc]`, `[aap_internal_links]`, `[aap_outbound_links]`) so raw post HTML remains clean.
- **Collapsible TOC [Show]/[Hide] Accordion Toggle**: Added interactive Show/Hide toggle button to TOC box.
- **Admin Accordion State Option**: Added setting **TOC Accordion Default State** (`Default Open / Expanded` vs `Default Closed / Collapsed`).

---

## [v1.4.6] - 2026-08-21

### 🔗 Smart Even Internal Link Distribution Engine
- Fixed internal link placement algorithm so `READ ALSO:` callout boxes and `RECOMMENDED READ` cards are **evenly spaced across the article paragraphs** instead of stacking together at the top of the post.

---

## [v1.4.5] - 2026-08-21

### 📅 Post Date Randomizer & Backdater Engine
- Added brand new module `📅 Date Randomizer` (`aap-randomizer`) to bulk randomize publication dates and comment timestamps within a specified date range (`Start Date` -> `End Date`).
- Supports Post Type selection, comment date synchronization, and updating "Last Modified" timestamps.

### 🔀 Redirect & 404 Manager Redesign & 404 Auto-Homepage Redirect
- Added **Auto-Redirect 404 Errors to Home Page (ON/OFF Toggle Switch)** to instantly reroute broken link traffic to homepage (`/`) for 301 SEO protection.
- Redesigned 404 Error Monitor & Log Tracker into spacious **Full-Width Cards** layout.

### 🌐 Universal Post Support Across Tools
- Expanded **Bulk Translator**, **Thumbnail Generator**, **Tags Manager**, and **Google Indexing Tool** to support **ALL blog posts** on the WordPress site (old WordPress posts, manual posts, imported articles, and AI-generated posts).

### 🎨 UI Contrast & Theme Cleanups
- Fixed low contrast text in System Maintenance & Data Control settings panel.
- Cleaned notice alert warning boxes and removed stray navigation button links inside alert text.

---

## [v1.3.5] - 2026-08-17

### 🌐 Auto External Outbound High-DA Links Engine
- Added contextual high-authority outbound link auto-injection to Wikipedia, MDN, WebMD, Investopedia, NASA, and Edu/Gov sources for E-E-A-T trust.
- Settings controller: Enable/disable toggle, Max links limit (1-10), Link open target (`_blank`/`_self`), Rel attribute (`nofollow noopener`), and Domain Blacklist.

### ⚡ Global Site-Wide Speed Optimizer Engine
- Added lazy loading for images & iframes with `fetchpriority="high"` for featured images.
- Auto WebP upload conversion & HTML code minification.
- Multi-engine cache purging across LiteSpeed, WP Rocket, W3TC, and OPcache.

### 🗺️ XML Sitemap Custom Manager & Controller
- Custom sitemap URL slug customization.
- Complete priority (1.0-0.1) & change frequency matrix for Homepage, Posts, Pages, Categories, and Tags.
- Google & Bing instant pinging on publish + styled XSL layout.

### 📄 1-Click Essential & Legal Pages Generator
- Instant generation of 7 GDPR/CCPA & E-E-A-T compliant legal pages (About Us, Contact Us, Privacy Policy, Disclaimer, Terms & Conditions, DMCA, Editorial Guidelines).

### 🍪 Cookie Consent Banner Controller
- 4 visual banner styles, customizable copy, and optional Accept/Reject buttons.

### 🎨 Layout & Navigation Uniformity
- Standardized 13-link navigation header and overflow-free admin panel layout across all devices.

---

## [v1.3.4] - 2026-08-17
- Version bump & master feature updates.

## [v1.3.3] - 2026-08-17
- Added Gemini 2.5 Flash, 2.5 Pro, 3.1 Flash Lite, 2.0 Flash models.
- Added ChatGPT GPT-4o, GPT-4o Mini, o3-mini, o1 models with multi-key failover.
