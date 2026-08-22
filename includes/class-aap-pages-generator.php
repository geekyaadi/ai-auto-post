<?php
/**
 * Essential Pages & Legal Content Generator Engine for AI Auto Post
 * Uses AI (Gemini / OpenAI) for 1000+ Words Custom Human-Grade Legal Pages with High Quality Static Fallback
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Pages_Generator {

    /**
     * Generates a high quality essential WordPress page using AI with fallback
     */
    public static function create_page( $page_type, $site_name, $site_url, $contact_email ) {
        $site_name = sanitize_text_field( $site_name );
        $site_url  = esc_url( $site_url );
        $email     = sanitize_email( $contact_email );

        $ai_prompt = '';
        switch ( $page_type ) {
            case 'about_us':
                $title     = 'About Us';
                $ai_prompt = "Write an extremely comprehensive, 1000+ words, highly professional and human-grade 'About Us' page for the website named '{$site_name}' (URL: {$site_url}, Contact Email: {$email}).
Include rich sections:
1. Welcome & Brand Overview
2. Our Core Vision and Mission Statement
3. Our Story, Origin, and Passion for Quality Content
4. What Sets Us Apart (Strict E-E-A-T Standards, In-Depth Research, Human-Centric Perspective)
5. Our Editorial Integrity & Verification Process
6. Content Accessibility & Community Commitment
7. How to Connect With Our Editorial Team
Use clean, well-formatted HTML with headings (<h2>, <h3>), paragraphs (<p>), bullet points (<ul>, <li>), and <strong> tags. Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_about_us_content( $site_name, $site_url, $email );
                break;

            case 'contact_us':
                $title     = 'Contact Us';
                $ai_prompt = "Write a comprehensive, 800+ words 'Contact Us' page for '{$site_name}' (URL: {$site_url}, Email: {$email}).
Include:
1. Welcome & Open Communication Policy
2. Official Direct Contact Channels (Email, Website URL, Business Hours)
3. Guidelines for Guest Post Proposals, Media Inquiries, Advertising, & DMCA Notices
4. Response Time Commitment (24 to 48 business hours)
5. Community Engagement & Reader Feedback Statement
Use clean, structured HTML with headings and styled callouts. Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_contact_us_content( $site_name, $site_url, $email );
                break;

            case 'privacy_policy':
                $title     = 'Privacy Policy';
                $ai_prompt = "Write an exhaustive, 1200+ words GDPR, CCPA, COPPA & Google AdSense compliant 'Privacy Policy' page for '{$site_name}' (URL: {$site_url}, Contact Email: {$email}).
Cover in full detail:
1. General Data Controller Information & Scope
2. Personal Information Collected & Lawful Basis of Processing under GDPR
3. Log Files, IP Tracking, & Technical Web Analytics
4. Cookies, Web Beacons, & Google DoubleClick DART Cookies Policy
5. Third-Party Advertising Partners & Vendor Privacy Links
6. California Consumer Privacy Act (CCPA) Privacy Rights (Do Not Sell My Personal Information)
7. General Data Protection Regulation (GDPR) Data Protection Rights (Access, Rectification, Erasure, Restriction, Objection)
8. Children's Online Privacy Protection Act (COPPA) Notice
9. Data Security, Storage & Retention Policies
10. Contacting Our Data Protection Representative
Use clean HTML formatting (<h2>, <h3>, <p>, <ul>, <li>). Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_privacy_policy_content( $site_name, $site_url, $email );
                break;

            case 'disclaimer':
                $title     = 'Disclaimer';
                $ai_prompt = "Write a thorough, 1000+ words 'Disclaimer & Legal Disclosure' page for '{$site_name}' (URL: {$site_url}, Contact Email: {$email}).
Cover:
1. General Information & Accuracy Disclaimer
2. External Hyperlinks & Third-Party Site Disclaimer
3. Professional (Financial, Legal, Health, Tech) Advice Disclaimer
4. Affiliate Marketing & Product Review Disclosure (FTC Compliance)
5. Errors, Omissions & 'As-Is' Warranty Limitation
6. User Responsibility & Consent Acknowledgement
Use clean HTML formatting. Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_disclaimer_content( $site_name, $site_url, $email );
                break;

            case 'terms_conditions':
                $title     = 'Terms and Conditions';
                $ai_prompt = "Write a comprehensive, 1200+ words 'Terms and Conditions of Use' page for '{$site_name}' (URL: {$site_url}, Contact Email: {$email}).
Cover:
1. Agreement to Terms & Acceptance of Conditions
2. Intellectual Property Rights & Content Ownership
3. User Conduct, Acceptable Use & Prohibited Activities
4. User-Generated Content & Comments License
5. Limitation of Liability & Indemnification
6. Third-Party Links & External Services Disclaimer
7. Account Termination & Service Suspension
8. Governing Law & Dispute Resolution
9. Amendments & Updates to Terms
10. Contact Details for Legal Inquiries
Use clean HTML formatting. Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_terms_content( $site_name, $site_url, $email );
                break;

            case 'dmca':
                $title     = 'DMCA & Copyright Policy';
                $ai_prompt = "Write a detailed, 1000+ words 'DMCA & Copyright Policy' page for '{$site_name}' (URL: {$site_url}, Contact Email: {$email}).
Cover:
1. Statement of Intellectual Property Protection & DMCA Compliance
2. Procedure for Filing a Formal DMCA Takedown Notice (List all 6 required elements: Physical/Electronic Signature, Identification of Work, URL, Contact Info, Good Faith Statement, Perjury Penalty Statement)
3. Counter-Notification Procedure for Affected Content Creators
4. Repeat Infringer Policy & Account Termination
5. Designated Copyright Agent Contact Information
Use clean HTML formatting. Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_dmca_content( $site_name, $site_url, $email );
                break;

            case 'editorial_guidelines':
                $title     = 'Editorial Guidelines & Fact-Checking Policy';
                $ai_prompt = "Write a comprehensive, 1000+ words 'Editorial Guidelines, Transparency & Fact-Checking Policy' page for '{$site_name}' (URL: {$site_url}, Contact Email: {$email}).
Cover:
1. Our Core Editorial Standards & Commitment to Truth
2. Rigorous Research, Sourcing & Fact-Checking Workflow
3. Google E-E-A-T Framework Compliance (Experience, Expertise, Authoritativeness, Trustworthiness)
4. Correction Policy, Transparency & Update Log Rules
5. Independence, Conflicts of Interest & Commercial Integrity
6. Responsible AI Integration & Human Editorial Oversight Policy
7. Author Accountability & Readers' Feedback Loop
Use clean HTML formatting. Do NOT wrap in markdown code blocks.";
                $fallback_content = self::get_editorial_content( $site_name, $site_url, $email );
                break;

            default:
                return new WP_Error( 'invalid_type', 'Invalid page type requested.' );
        }

        // Attempt AI Content Generation
        $content = '';
        if ( class_exists( 'AAP_Gemini' ) && method_exists( 'AAP_Gemini', 'generate_custom_text' ) ) {
            $ai_result = AAP_Gemini::generate_custom_text( $ai_prompt );
            if ( ! is_wp_error( $ai_result ) && ! empty( $ai_result ) && strlen( $ai_result ) > 300 ) {
                $content = $ai_result;
            }
        }

        // Fallback to Rich Template if AI Generation was empty or unavailable
        if ( empty( $content ) ) {
            $content = $fallback_content;
        }

        // Clean any residual code block markup
        $content = preg_replace( '/^```(?:html|markdown)?\s*/i', '', trim( $content ) );
        $content = preg_replace( '/```\s*$/', '', $content );

        // Check if page already exists
        $page_query = new WP_Query( [
            'title'                  => $title,
            'post_type'              => 'page',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ] );
        $existing = ! empty( $page_query->posts ) ? $page_query->posts[0] : null;
        if ( $existing ) {
            $page_id = wp_update_post( [
                'ID'           => $existing->ID,
                'post_content' => $content,
                'post_status'  => 'publish',
            ] );
        } else {
            $page_id = wp_insert_post( [
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id() ?: 1,
            ] );
        }

        return $page_id;
    }

    private static function get_about_us_content( $site_name, $site_url, $email ) {
        return '<h2>Welcome to ' . esc_html($site_name) . '</h2>
<p>At <strong>' . esc_html($site_name) . '</strong> (accessible at <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a>), our primary mission is to deliver authentic, deeply researched, and highly engaging content that empowers readers across the globe. We take immense pride in publishing thorough, unbiased, and value-packed articles designed to solve real-world problems and answer your most pressingly asked questions.</p>

<h3>Our Vision and Mission</h3>
<p>Our vision is simple yet profound: to become the internet\'s most dependable, transparent, and authoritative destination for comprehensive tutorials, unbiased evaluations, and cutting-edge insights. In a digital landscape often cluttered with superficial summaries, we aim to stand out by providing meticulous accuracy, depth, and clarity in every piece we publish.</p>

<h3>What Sets Us Apart?</h3>
<ul>
    <li><strong>Uncompromising Research:</strong> Every article published on ' . esc_html($site_name) . ' undergoes extensive preliminary research, expert verification, and editorial refinement before reaching your screen.</li>
    <li><strong>Human-Centric Approach:</strong> We prioritize clarity, legibility, and practical utility above all else. Our content is written for real people, offering clear step-by-step guidance without unnecessary fluff.</li>
    <li><strong>Strict E-E-A-T Standards:</strong> Our editorial team adheres strictly to Experience, Expertise, Authoritativeness, and Trustworthiness (E-E-A-T) principles to ensure maximum informational value.</li>
</ul>

<h3>Our Editorial Integrity & Verification Process</h3>
<p>Information accuracy is the bedrock of our digital publication. Our writers and subject-matter reviewers continuously cross-examine data against primary academic papers, official documentation, and industry benchmarks. We never compromise on objectivity or accuracy.</p>

<h3>Get in Touch with Our Team</h3>
<p>We value open dialogue with our community. If you have inquiries, feedback, topic recommendations, or partnership proposals, please do not hesitate to connect with us directly via email at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a> or visit our Contact Us page.</p>';
    }

    private static function get_contact_us_content( $site_name, $site_url, $email ) {
        return '<h2>Get in Touch with ' . esc_html($site_name) . '</h2>
<p>Have questions, feedback, media inquiries, or partnership proposals? We would love to hear from you! At <strong>' . esc_html($site_name) . '</strong>, we value our community\'s input and strive to respond to all legitimate inquiries promptly within 24 to 48 business hours.</p>

<div class="aap-contact-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:24px; margin:25px 0;">
    <h3 style="margin-top:0; color:#0f172a;">📬 Direct Communication Channels</h3>
    <ul style="list-style:none; padding-left:0; margin-bottom:0;">
        <li style="margin-bottom:12px;">📧 <strong>Official Email:</strong> <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a></li>
        <li style="margin-bottom:12px;">🌐 <strong>Website URL:</strong> <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a></li>
        <li style="margin-bottom:0;">🕒 <strong>Operating Hours:</strong> Monday – Friday, 9:00 AM – 6:00 PM (EST)</li>
    </ul>
</div>

<h3>Send Us a Direct Message</h3>
<p>Please write directly to our editorial mailbox at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>. When reaching out regarding a published article, please include the specific article title and URL in your message for faster resolution.</p>';
    }

    private static function get_privacy_policy_content( $site_name, $site_url, $email ) {
        return '<h2>Privacy Policy for ' . esc_html($site_name) . '</h2>
<p>At <strong>' . esc_html($site_name) . '</strong>, accessible from <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a>, one of our main priorities is the privacy of our visitors. This Privacy Policy document contains types of information that is collected and recorded by ' . esc_html($site_name) . ' and how we use it.</p>
<p>If you have additional questions or require more information about our Privacy Policy, do not hesitate to contact us at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>

<h3>1. General Data Protection Regulation (GDPR) & CCPA Compliance</h3>
<p>We are a Data Controller of your information. ' . esc_html($site_name) . '\'s legal basis for collecting and using the personal information described in this Privacy Policy depends on the Personal Information we collect and the specific context in which we collect the information:</p>
<ul>
    <li>' . esc_html($site_name) . ' needs to perform a contract with you.</li>
    <li>You have given ' . esc_html($site_name) . ' permission to do so.</li>
    <li>Processing your personal information is in ' . esc_html($site_name) . '\'s legitimate interests.</li>
    <li>' . esc_html($site_name) . ' needs to comply with the law.</li>
</ul>

<h3>2. Log Files and Analytical Cookies</h3>
<p>' . esc_html($site_name) . ' follows a standard procedure of using log files. These files log visitors when they visit websites. The information collected by log files includes internet protocol (IP) addresses, browser type, Internet Service Provider (ISP), date and time stamp, referring/exit pages, and possibly the number of clicks.</p>

<h3>3. Google DoubleClick DART Cookie & Third-Party Vendors</h3>
<p>Google is one of a third-party vendor on our site. It also uses cookies, known as DART cookies, to serve ads to our site visitors based upon their visit to ' . esc_url($site_url) . ' and other sites on the internet. However, visitors may choose to decline the use of DART cookies by visiting the Google ad and content network Privacy Policy at <a href="https://policies.google.com/technologies/ads" target="_blank" rel="nofollow">https://policies.google.com/technologies/ads</a>.</p>

<h3>4. Contact Information</h3>
<p>For any privacy inquiries or data removal requests, please write to us at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>';
    }

    private static function get_disclaimer_content( $site_name, $site_url, $email ) {
        return '<h2>Disclaimer for ' . esc_html($site_name) . '</h2>
<p>If you require any more information or have any questions about our site\'s disclaimer, please feel free to contact us by email at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>

<h3>1. General Information and Accuracy</h3>
<p>All the information on this website – <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a> – is published in good faith and for general information purpose only. <strong>' . esc_html($site_name) . '</strong> does not make any warranties about the completeness, reliability, and accuracy of this information.</p>

<h3>2. External Links Disclaimer</h3>
<p>From our website, you can visit other websites by following hyperlinks to such external sites. While we strive to provide only quality links to useful and ethical websites, we have no control over the content and nature of these sites.</p>

<h3>3. Professional & Affiliate Disclosure</h3>
<p>The content provided on ' . esc_html($site_name) . ' is intended for informational purposes only. Some links on this site may be affiliate links, meaning we may earn a small commission at no extra cost to you.</p>';
    }

    private static function get_terms_content( $site_name, $site_url, $email ) {
        return '<h2>Terms and Conditions for ' . esc_html($site_name) . '</h2>
<p>Welcome to <strong>' . esc_html($site_name) . '</strong>! These terms and conditions outline the rules and regulations for the use of ' . esc_html($site_name) . '\'s Website, located at <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a>.</p>
<p>By accessing this website we assume you accept these terms and conditions. Do not continue to use ' . esc_html($site_name) . ' if you do not agree to take all of the terms and conditions stated on this page.</p>

<h3>Intellectual Property Rights</h3>
<p>Other than the content you own, under these Terms, ' . esc_html($site_name) . ' and/or its licensors own all the intellectual property rights and materials contained in this Website.</p>';
    }

    private static function get_dmca_content( $site_name, $site_url, $email ) {
        return '<h2>DMCA & Copyright Infringement Policy for ' . esc_html($site_name) . '</h2>
<p><strong>' . esc_html($site_name) . '</strong> respects the intellectual property rights of others. Per the Digital Millennium Copyright Act (DMCA), we will respond expeditiously to claims of copyright infringement committed using the ' . esc_html($site_name) . ' website.</p>
<p>If you are a copyright owner, authorized to act on behalf of one, or authorized to act under any exclusive right under copyright, please report alleged copyright infringements taking place on or through the site to <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>';
    }

    private static function get_editorial_content( $site_name, $site_url, $email ) {
        return '<h2>Editorial Guidelines & Fact-Checking Policy</h2>
<p>At <strong>' . esc_html($site_name) . '</strong>, we are committed to maintaining the highest standards of journalistic integrity, accuracy, and editorial independence. Our guidelines ensure that every article published meets strict E-E-A-T criteria.</p>
<h3>Fact-Checking and Verification</h3>
<p>Our editorial team verifies all key claims against reputable data sources, peer-reviewed journals, and official industry documentation prior to publishing.</p>';
    }
}
