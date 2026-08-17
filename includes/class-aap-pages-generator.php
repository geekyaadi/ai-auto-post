<?php
/**
 * Essential Pages & Legal Content Generator Engine for AI Auto Post
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Pages_Generator {

    /**
     * Generates a high quality essential WordPress page
     */
    public static function create_page( $page_type, $site_name, $site_url, $contact_email ) {
        $site_name = sanitize_text_field( $site_name );
        $site_url  = esc_url( $site_url );
        $email     = sanitize_email( $contact_email );

        switch ( $page_type ) {
            case 'about_us':
                $title   = 'About Us';
                $content = self::get_about_us_content( $site_name, $site_url, $email );
                break;
            case 'contact_us':
                $title   = 'Contact Us';
                $content = self::get_contact_us_content( $site_name, $site_url, $email );
                break;
            case 'privacy_policy':
                $title   = 'Privacy Policy';
                $content = self::get_privacy_policy_content( $site_name, $site_url, $email );
                break;
            case 'disclaimer':
                $title   = 'Disclaimer';
                $content = self::get_disclaimer_content( $site_name, $site_url, $email );
                break;
            case 'terms_conditions':
                $title   = 'Terms and Conditions';
                $content = self::get_terms_content( $site_name, $site_url, $email );
                break;
            case 'dmca':
                $title   = 'DMCA & Copyright Policy';
                $content = self::get_dmca_content( $site_name, $site_url, $email );
                break;
            case 'editorial_guidelines':
                $title   = 'Editorial Guidelines & Fact-Checking Policy';
                $content = self::get_editorial_content( $site_name, $site_url, $email );
                break;
            default:
                return new WP_Error( 'invalid_type', 'Invalid page type requested.' );
        }

        // Check if page already exists
        $existing = get_page_by_title( $title );
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

<h3>Our Commitment to Readers</h3>
<p>We are dedicated to fostering an environment of absolute trust and transparency. We actively listen to reader feedback, regularly update our archives to reflect new industry updates, and strictly maintain reader privacy and data integrity.</p>

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
<p>Please fill out the form below or write directly to our editorial mailbox at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>. When reaching out regarding a published article, please include the specific article title and URL in your message for faster resolution.</p>';
    }

    private static function get_privacy_policy_content( $site_name, $site_url, $email ) {
        return '<h2>Privacy Policy for ' . esc_html($site_name) . '</h2>
<p>At <strong>' . esc_html($site_name) . '</strong>, accessible from <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a>, one of our main priorities is the privacy of our visitors. This Privacy Policy document contains types of information that is collected and recorded by ' . esc_html($site_name) . ' and how we use it.</p>
<p>If you have additional questions or require more information about our Privacy Policy, do not hesitate to contact us at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>

<h3>1. General Data Protection Regulation (GDPR) & CCPA Compliance</h3>
<p>We are a Data Controller of your information. ' . esc_html($site_name) . ' legal basis for collecting and using the personal information described in this Privacy Policy depends on the Personal Information we collect and the specific context in which we collect the information:</p>
<ul>
    <li>' . esc_html($site_name) . ' needs to perform a contract with you.</li>
    <li>You have given ' . esc_html($site_name) . ' permission to do so.</li>
    <li>Processing your personal information is in ' . esc_html($site_name) . ' legitimate interests.</li>
    <li>' . esc_html($site_name) . ' needs to comply with the law.</li>
</ul>

<h3>2. Log Files and Analytical Cookies</h3>
<p>' . esc_html($site_name) . ' follows a standard procedure of using log files. These files log visitors when they visit websites. All hosting companies do this as part of hosting services\' analytics. The information collected by log files includes internet protocol (IP) addresses, browser type, Internet Service Provider (ISP), date and time stamp, referring/exit pages, and possibly the number of clicks.</p>

<h3>3. Google DoubleClick DART Cookie & Third-Party Vendors</h3>
<p>Google is one of a third-party vendor on our site. It also uses cookies, known as DART cookies, to serve ads to our site visitors based upon their visit to ' . esc_url($site_url) . ' and other sites on the internet. However, visitors may choose to decline the use of DART cookies by visiting the Google ad and content network Privacy Policy at the following URL – <a href="https://policies.google.com/technologies/ads" target="_blank" rel="nofollow">https://policies.google.com/technologies/ads</a>.</p>

<h3>4. Third-Party Privacy Policies</h3>
<p>' . esc_html($site_name) . '\'s Privacy Policy does not apply to other advertisers or websites. Thus, we are advising you to consult the respective Privacy Policies of these third-party ad servers for more detailed information.</p>

<h3>5. Contact Information</h3>
<p>For any privacy inquiries or data removal requests, please write to us at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>';
    }

    private static function get_disclaimer_content( $site_name, $site_url, $email ) {
        return '<h2>Disclaimer for ' . esc_html($site_name) . '</h2>
<p>If you require any more information or have any questions about our site\'s disclaimer, please feel free to contact us by email at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>

<h3>1. General Information and Accuracy</h3>
<p>All the information on this website – <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a> – is published in good faith and for general information purpose only. <strong>' . esc_html($site_name) . '</strong> does not make any warranties about the completeness, reliability, and accuracy of this information. Any action you take upon the information you find on this website (' . esc_html($site_name) . '), is strictly at your own risk.</p>

<h3>2. External Links Disclaimer</h3>
<p>From our website, you can visit other websites by following hyperlinks to such external sites. While we strive to provide only quality links to useful and ethical websites, we have no control over the content and nature of these sites. These links to other websites do not imply a recommendation for all the content found on these sites.</p>

<h3>3. Professional & Affiliate Disclosure</h3>
<p>The content provided on ' . esc_html($site_name) . ' is intended for informational and educational purposes only and should not be construed as professional financial, legal, or medical advice. Some links on this site may be affiliate links, meaning we may earn a small commission at no extra cost to you if you complete a purchase.</p>

<h3>4. Consent</h3>
<p>By using our website, you hereby consent to our disclaimer and agree to its terms.</p>';
    }

    private static function get_terms_content( $site_name, $site_url, $email ) {
        return '<h2>Terms and Conditions for ' . esc_html($site_name) . '</h2>
<p>Welcome to <strong>' . esc_html($site_name) . '</strong>! These terms and conditions outline the rules and regulations for the use of ' . esc_html($site_name) . '\'s Website, located at <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a>.</p>

<h3>1. Intellectual Property Rights</h3>
<p>Unless otherwise stated, ' . esc_html($site_name) . ' and/or its licensors own the intellectual property rights for all material on ' . esc_html($site_name) . '. All intellectual property rights are reserved. You may access this from ' . esc_html($site_name) . ' for your own personal use subjected to restrictions set in these terms and conditions.</p>

<h3>2. User Comments and Content Standards</h3>
<p>Parts of this website offer an opportunity for users to post and exchange opinions and information in certain areas of the website. ' . esc_html($site_name) . ' does not filter, edit, publish or review Comments prior to their presence on the website. Comments reflect the views and opinions of the person who posts their views and opinions.</p>

<h3>3. Limitation of Liability</h3>
<p>In no event shall ' . esc_html($site_name) . ', nor any of its officers, directors, and employees, be held liable for anything arising out of or in any way connected with your use of this website whether such liability is under contract.</p>

<h3>4. Governing Law</h3>
<p>These Terms will be governed by and interpreted in accordance with the laws of the jurisdiction, and you submit to the non-exclusive jurisdiction of the state and federal courts located in the country for the resolution of any disputes.</p>';
    }

    private static function get_dmca_content( $site_name, $site_url, $email ) {
        return '<h2>DMCA & Copyright Infringement Policy</h2>
<p><strong>' . esc_html($site_name) . '</strong> respects the intellectual property rights of others and expects its users to do the same. In accordance with the Digital Millennium Copyright Act of 1998 (DMCA), we will respond expeditiously to claims of copyright infringement committed using our site (<a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a>).</p>

<h3>Filing a DMCA Takedown Notice</h3>
<p>If you believe that your copyrighted work has been copied in a way that constitutes copyright infringement, please provide our Designated Copyright Agent with the following written information:</p>
<ol>
    <li>A physical or electronic signature of the copyright owner or authorized representative.</li>
    <li>Identification of the copyrighted work claimed to have been infringed.</li>
    <li>Identification of the material that is claimed to be infringing and its exact URL on our site.</li>
    <li>Your contact address, telephone number, and email address.</li>
    <li>A statement that you have a good faith belief that use of the material is not authorized by the copyright owner.</li>
</ol>
<p>Please send all infringement notices to our Copyright Officer at <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a>.</p>';
    }

    private static function get_editorial_content( $site_name, $site_url, $email ) {
        return '<h2>Editorial Guidelines & Fact-Checking Policy</h2>
<p>At <strong>' . esc_html($site_name) . '</strong>, we are committed to producing high-quality, truthful, and accurate content that satisfies Google E-E-A-T (Experience, Expertise, Authoritativeness, and Trustworthiness) standards. Our editorial policy serves as our commitment to absolute integrity.</p>

<h3>1. Fact-Checking and Verification Standard</h3>
<p>Every article published on ' . esc_html($site_name) . ' undergoes rigorous fact-checking against primary sources, peer-reviewed data, official research documentation, and credible databases. We do not rely on unverified rumors or second-hand speculation.</p>

<h3>2. Corrections Policy</h3>
<p>When an error or factual inaccuracy occurs, we are dedicated to correcting it immediately. Readers who notice factual errors are encouraged to report them to <a href="mailto:' . esc_html($email) . '">' . esc_html($email) . '</a> for rapid review and correction.</p>

<h3>3. Independence and Bias Prevention</h3>
<p>Our editorial decisions are entirely independent of advertiser influence. Sponsored relationships or commercial partnerships never dictate our editorial ratings, reviews, or informational recommendations.</p>';
    }
}
