<?php
/**
 * Plugin Name: Marco's Home Control
 * Plugin URI: https://marcohom.com/
 * Description: قناة آمنة لإدارة تعديلات موقع Marco's Home المنشورة من فرع WordPress المخصص.
 * Version: 0.3.3
 * Author: Marco's Home
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MH_CONTROL_VERSION', '0.3.3');

function mh_control_add_admin_page(): void {
    add_management_page(
        'Marco\'s Home Control',
        'Marco\'s Home Control',
        'manage_options',
        'marcos-home-control',
        'mh_control_render_admin_page'
    );
}
add_action('admin_menu', 'mh_control_add_admin_page');

function mh_control_render_admin_page(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap" dir="rtl">
        <h1>ربط Marco's Home</h1>
        <p><strong>حالة الإضافة:</strong> جاهزة</p>
        <p><strong>الإصدار:</strong> <?php echo esc_html(MH_CONTROL_VERSION); ?></p>
        <p>هذه الإضافة مخصصة لنشر تعديلات الموقع المعتمدة من قناة GitHub المنفصلة، بدون تعديل ملفات تطبيق Marco's Home.</p>
    </div>
    <?php
}

function mh_control_activation_notice(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (get_transient('mh_control_activated') !== 'yes') {
        return;
    }
    delete_transient('mh_control_activated');
    echo '<div class="notice notice-success is-dismissible"><p>تم تفعيل قناة Marco\'s Home Control بنجاح.</p></div>';
}
add_action('admin_notices', 'mh_control_activation_notice');

function mh_control_activate(): void {
    set_transient('mh_control_activated', 'yes', 60);
}
register_activation_hook(__FILE__, 'mh_control_activate');


function mh_control_register_status_route(): void {
    register_rest_route('marcos-home/v1', '/status', [
        'methods' => 'GET',
        'callback' => static function (): WP_REST_Response {
            return new WP_REST_Response([
                'connected' => true,
                'version' => MH_CONTROL_VERSION,
                'site' => home_url('/'),
            ], 200);
        },
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'mh_control_register_status_route');


function mh_control_query_status(): void {
    if (!isset($_GET['mh_control_status']) || $_GET['mh_control_status'] !== '1') {
        return;
    }
    wp_send_json([
        'connected' => true,
        'version' => MH_CONTROL_VERSION,
        'site' => home_url('/'),
    ]);
}
add_action('template_redirect', 'mh_control_query_status');


function mh_control_homepage_markup(): string {
    ob_start();
    ?>
    <main class="mh-home" dir="rtl">
        <section class="mh-hero">
            <div class="mh-hero__overlay"></div>
            <div class="mh-shell mh-hero__content">
                <span class="mh-kicker">تصميم وتنفيذ ديكورات داخلية في الكويت</span>
                <h1>حوّل بيتك لمساحة<br>تعبر عن ذوقك</h1>
                <p>خلفيات شاشة، أركان قهوة، باركيه، فواصل بديل الخشب وتصميمات مخصصة بخامات مختارة وتنفيذ احترافي.</p>
                <div class="mh-actions">
                    <a class="mh-btn mh-btn--primary" href="https://wa.me/96550204320?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%20%D9%85%D8%A7%D8%B1%D9%83%D9%88%D8%B2%20%D9%87%D9%88%D9%85%D8%8C%20%D8%A3%D8%B1%D9%8A%D8%AF%20%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D9%81%D8%B3%D8%A7%D8%B1%20%D8%B9%D9%86%20%D8%AA%D8%B5%D9%85%D9%8A%D9%85" target="_blank" rel="noopener">اطلب استشارة على واتساب</a>
                    <a class="mh-btn mh-btn--ghost" href="#mh-services">شاهد خدماتنا</a>
                </div>
            </div>
        </section>

        <section class="mh-trust" aria-label="مميزات ماركوز هوم">
            <div class="mh-shell mh-trust__grid">
                <div><strong>تنفيذ احترافي</strong><span>تفاصيل دقيقة وتشطيب نظيف</span></div>
                <div><strong>خامات مختارة</strong><span>حلول عملية تناسب الاستخدام</span></div>
                <div><strong>خدمة داخل الكويت</strong><span>معاينة وقياس وتركيب</span></div>
            </div>
        </section>

        <section class="mh-section" id="mh-services">
            <div class="mh-shell">
                <div class="mh-heading">
                    <span class="mh-kicker mh-kicker--dark">اختار المساحة التي تريد تطويرها</span>
                    <h2>خدمات ماركوز هوم</h2>
                    <p>تصميمات عصرية قابلة للتخصيص بالألوان والمقاسات التي تناسب بيتك.</p>
                </div>
                <div class="mh-cards">
                    <a class="mh-card mh-card--wide" href="https://marcohom.com/product-category/%d9%86%d9%85%d8%a7%d8%b0%d8%ac-%d9%88%d8%aa%d8%b5%d9%85%d9%8a%d9%85%d8%a7%d8%aa/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg" alt="خلفيات شاشة وتصميمات ديكور من ماركوز هوم">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>خلفيات الشاشة والديكور</b><small>تصميمات متكاملة بمقاسات وألوان متعددة</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/product-category/%d9%88%d8%a7%d8%ad%d8%af%d8%a7%d8%aa-%d8%a7%d9%84%d8%aa%d8%ae%d8%b2%d9%8a%d9%86-tv/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/11/Generated-Image-November-03-2025-7_43PM-270x270.png" alt="طاولات تلفزيون معلقة">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>طاولات TV</b><small>مقاسات وألوان تناسب تصميمك</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/product-category/%d8%a7%d8%b1%d8%b6%d9%8a%d8%a7%d8%aa-%d8%a8%d8%a7%d8%b1%d9%83%d9%8a%d9%87/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_9a98879a98879a98.jpeg" alt="أرضيات باركيه">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>أرضيات باركيه</b><small>دفء وأناقة وسهولة في التنظيف</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/product-category/%d9%82%d9%88%d8%a7%d8%b7%d8%b9-%d8%a7%d9%84%d8%a7%d8%b9%d9%85%d8%af%d8%a9/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_125uc4125uc4125u-Copy.jpg" alt="فواصل أعمدة بديل الخشب">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>فواصل بديل الخشب</b><small>فصل أنيق للمساحات بدون إغلاقها</small></span>
                    </a>
                    <a class="mh-card mh-card--wide" href="https://marcohom.com/product-category/%d8%a7%d9%84%d9%81%d9%8a%d8%b1-%d8%a7%d9%84%d8%b9%d8%b7%d8%b1/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/11/Art-Fireplace-AFW230-3D-Water-Vapor-Fireplace-product-1.webp" alt="جهاز الفير المعطر">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>جهاز الفير المعطر</b><small>ديكور مائي مميز بأحجام متعددة</small></span>
                    </a>
                </div>
            </div>
        </section>

        <section class="mh-process">
            <div class="mh-shell">
                <div class="mh-heading mh-heading--light">
                    <span class="mh-kicker">من الفكرة إلى التنفيذ</span>
                    <h2>ثلاث خطوات لمساحة أجمل</h2>
                </div>
                <div class="mh-process__grid">
                    <div><span>01</span><h3>أرسل المقاس والصورة</h3><p>شاركنا صورة المكان والمقاسات عبر واتساب.</p></div>
                    <div><span>02</span><h3>اختار التصميم واللون</h3><p>نساعدك في اختيار الحل الأنسب للمساحة والميزانية.</p></div>
                    <div><span>03</span><h3>التنفيذ والتركيب</h3><p>تنفيذ احترافي وتسليم نهائي مرتب وجاهز.</p></div>
                </div>
            </div>
        </section>

        <section class="mh-cta">
            <div class="mh-shell mh-cta__box">
                <div><span class="mh-kicker mh-kicker--dark">جاهز تبدأ؟</span><h2>ابعث صورة المكان وخد اقتراح مناسب</h2><p>رد سريع عبر واتساب لمعرفة التصميم والمقاس والتكلفة.</p></div>
                <a class="mh-btn mh-btn--dark" href="https://wa.me/96550204320?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7%20%D9%85%D8%A7%D8%B1%D9%83%D9%88%D8%B2%20%D9%87%D9%88%D9%85%D8%8C%20%D9%87%D8%B0%D9%87%20%D8%B5%D9%88%D8%B1%D8%A9%20%D8%A7%D9%84%D9%85%D9%83%D8%A7%D9%86%20%D9%88%D8%A3%D8%B1%D9%8A%D8%AF%20%D8%A7%D9%82%D8%AA%D8%B1%D8%A7%D8%AD%20%D8%AA%D8%B5%D9%85%D9%8A%D9%85" target="_blank" rel="noopener">تواصل معنا الآن</a>
            </div>
        </section>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_replace_front_content(string $content): string {
    if (is_admin() || !is_front_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    return mh_control_homepage_markup();
}
add_filter('the_content', 'mh_control_replace_front_content', 999);

function mh_control_front_title(array $parts): array {
    if (is_front_page()) {
        $parts['title'] = 'ماركوز هوم | تصميمات وديكور في الكويت';
    }
    return $parts;
}
add_filter('document_title_parts', 'mh_control_front_title');

function mh_control_front_styles(): void {
    if (!is_front_page()) {
        return;
    }
    ?>
    <style id="mh-home-styles">
    :root{--mh-blue:#1266d6;--mh-navy:#071a33;--mh-ink:#132238;--mh-soft:#f3f6f9;--mh-gold:#d6aa62}
    .mh-home{font-family:Tahoma,Arial,sans-serif;color:var(--mh-ink);background:#fff;width:100vw;margin-inline:calc(50% - 50vw);overflow:hidden}
    .mh-home *{box-sizing:border-box}.mh-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mh-hero{min-height:650px;display:flex;align-items:center;position:relative;background:url('https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg') center 63%/cover no-repeat}
    .mh-hero__overlay{position:absolute;inset:0;background:linear-gradient(90deg,rgba(5,18,37,.2),rgba(5,18,37,.88))}
    .mh-hero__content{position:relative;z-index:1;color:#fff;padding-block:100px}.mh-kicker{display:inline-flex;align-items:center;gap:10px;font-size:15px;font-weight:700;letter-spacing:.1px;margin-bottom:18px;color:#d8e8ff}.mh-kicker:before{content:"";width:34px;height:2px;background:var(--mh-gold)}
    .mh-kicker--dark{color:var(--mh-blue)}.mh-hero h1{font-size:clamp(42px,6vw,76px);line-height:1.12;margin:0 0 22px;max-width:760px;color:#fff;font-weight:800}.mh-hero p{font-size:19px;line-height:1.9;max-width:680px;margin:0 0 34px;color:#eef5ff}
    .mh-actions{display:flex;gap:14px;flex-wrap:wrap}.mh-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 24px;border-radius:8px;text-decoration:none!important;font-weight:800;transition:.2s ease}.mh-btn:hover{transform:translateY(-2px)}
    .mh-btn--primary{background:#20b95a;color:#fff!important}.mh-btn--ghost{border:1px solid rgba(255,255,255,.7);color:#fff!important;background:rgba(255,255,255,.08)}.mh-btn--dark{background:var(--mh-navy);color:#fff!important}
    .mh-trust{background:var(--mh-navy);color:#fff}.mh-trust__grid{display:grid;grid-template-columns:repeat(3,1fr)}.mh-trust__grid>div{padding:28px 34px;border-inline-start:1px solid rgba(255,255,255,.12)}.mh-trust__grid>div:first-child{border-inline-start:0}.mh-trust strong,.mh-trust span{display:block}.mh-trust strong{font-size:17px;margin-bottom:5px}.mh-trust span{font-size:13px;color:#aebdd0}
    .mh-section{padding:92px 0}.mh-heading{text-align:center;max-width:720px;margin:0 auto 46px}.mh-heading h2{font-size:clamp(32px,5vw,50px);line-height:1.2;margin:0 0 14px;color:var(--mh-navy)}.mh-heading p{margin:0;color:#627086;font-size:17px;line-height:1.8}
    .mh-cards{display:grid;grid-template-columns:repeat(4,1fr);grid-auto-rows:310px;gap:18px}.mh-card{position:relative;border-radius:16px;overflow:hidden;text-decoration:none!important;background:#ddd}.mh-card--wide{grid-column:span 2}.mh-card img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease}.mh-card:hover img{transform:scale(1.05)}.mh-card__shade{position:absolute;inset:0;background:linear-gradient(0deg,rgba(3,15,31,.9),rgba(3,15,31,0) 70%)}.mh-card__text{position:absolute;inset-inline:22px;bottom:20px;color:#fff}.mh-card__text b,.mh-card__text small{display:block}.mh-card__text b{font-size:22px;margin-bottom:7px}.mh-card__text small{font-size:13px;color:#dce7f5}
    .mh-process{padding:90px 0;background:var(--mh-navy);color:#fff}.mh-heading--light h2{color:#fff}.mh-process__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.mh-process__grid>div{padding:32px;border:1px solid rgba(255,255,255,.13);border-radius:14px;background:rgba(255,255,255,.04)}.mh-process__grid span{font-size:14px;color:var(--mh-gold);font-weight:800}.mh-process__grid h3{font-size:21px;color:#fff;margin:14px 0 10px}.mh-process__grid p{margin:0;color:#aebdd0;line-height:1.8}
    .mh-cta{padding:74px 0;background:#edf3f9}.mh-cta__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;border-radius:18px;padding:46px 52px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mh-cta h2{margin:0 0 10px;font-size:clamp(28px,4vw,42px);color:var(--mh-navy)}.mh-cta p{margin:0;color:#68768a}
    @media(max-width:900px){.mh-hero{min-height:580px;background-position:40% center}.mh-trust__grid{grid-template-columns:1fr}.mh-trust__grid>div{border-inline-start:0;border-bottom:1px solid rgba(255,255,255,.1);padding:20px}.mh-cards{grid-template-columns:repeat(2,1fr)}.mh-card--wide{grid-column:span 1}.mh-process__grid{grid-template-columns:1fr}.mh-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:560px){.mh-shell{width:min(100% - 28px,1180px)}.mh-hero{min-height:590px}.mh-hero__content{padding-block:70px}.mh-hero h1{font-size:40px}.mh-hero p{font-size:16px}.mh-actions{display:grid}.mh-btn{width:100%}.mh-section,.mh-process{padding:64px 0}.mh-cards{grid-template-columns:1fr;grid-auto-rows:260px}.mh-cta{padding:48px 0}.mh-cta__box{padding:30px 24px}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_front_styles', 99);


function mh_control_final_front_title(string $title): string {
    if (is_front_page()) {
        return 'ماركوز هوم | تصميمات وديكور في الكويت';
    }
    return $title;
}
add_filter('pre_get_document_title', 'mh_control_final_front_title', 99);

function mh_control_arabic_menu_labels(array $items): array {
    if (is_admin()) {
        return $items;
    }
    foreach ($items as $item) {
        $label = trim(wp_strip_all_tags((string) $item->title));
        if (strcasecmp($label, 'Portfolio') === 0) {
            $item->title = 'أعمالنا';
        } elseif ($label === 'نماذج') {
            $item->title = 'التصميمات';
        }
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'mh_control_arabic_menu_labels', 20);


function mh_control_seo_title(string $title): string {
    if (is_front_page()) {
        return 'ماركوز هوم | تصميمات وديكور في الكويت';
    }
    return $title;
}

function mh_control_seo_description(string $description): string {
    if (is_front_page()) {
        return 'ماركوز هوم لخلفيات الشاشة وأركان القهوة والباركيه وفواصل بديل الخشب وطاولات التلفزيون والديكورات الداخلية في الكويت.';
    }
    return $description;
}

add_filter('aioseo_title', 'mh_control_seo_title', 999);
add_filter('aioseo_description', 'mh_control_seo_description', 999);
add_filter('wpseo_title', 'mh_control_seo_title', 999);
add_filter('wpseo_metadesc', 'mh_control_seo_description', 999);
add_filter('rank_math/frontend/title', 'mh_control_seo_title', 999);
add_filter('rank_math/frontend/description', 'mh_control_seo_description', 999);
add_filter('wp_title', 'mh_control_seo_title', 999);


/**
 * Portfolio page refresh — roadmap step 1.
 * The original Elementor content remains untouched and can be restored by disabling this module.
 */
function mh_control_is_portfolio_page(): bool {
    return is_page(6141) || is_page('portfolio');
}

function mh_control_portfolio_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=';
    $ask_text = rawurlencode('مرحباً ماركوز هوم، شاهدت صفحة أعمالنا وأريد تنفيذ تصميم مشابه. هذه صورة المكان:');
    ob_start();
    ?>
    <main class="mh-portfolio" dir="rtl">
        <section class="mhp-hero">
            <div class="mhp-hero__shade"></div>
            <div class="mhp-shell mhp-hero__content">
                <span class="mhp-eyebrow">تصميم وتنفيذ داخل الكويت</span>
                <h1>أعمالنا تتكلم عنّا</h1>
                <p>مجموعة مختارة من حلول ماركوز هوم للمساحات العصرية، من الفكرة والقياس إلى التنفيذ والتركيب.</p>
                <a class="mhp-btn mhp-btn--green" href="<?php echo esc_url($whatsapp . $ask_text); ?>" target="_blank" rel="noopener">أرسل صورة مكانك على واتساب</a>
            </div>
        </section>

        <nav class="mhp-categories" aria-label="فئات أعمالنا">
            <div class="mhp-shell">
                <a href="#screens">خلفيات شاشة</a>
                <a href="#coffee">أركان قهوة</a>
                <a href="#beds">خلفيات سرير</a>
                <a href="#tv">طاولات TV</a>
                <a href="#parquet">باركيه</a>
                <a href="#wpc">فواصل بديل الخشب</a>
                <a href="#fire">جهاز الفير المعطر</a>
            </div>
        </nav>

        <section class="mhp-intro">
            <div class="mhp-shell mhp-intro__grid">
                <div>
                    <span class="mhp-eyebrow mhp-eyebrow--blue">حلول مصممة لمساحتك</span>
                    <h2>اختار الفكرة، وإحنا نضبطها على المقاس</h2>
                </div>
                <p>كل مساحة لها مقاس واستخدام مختلف. لذلك نساعدك في اختيار الخامة واللون والتوزيع المناسب، ثم ننفذ التصميم بدقة وتشطيب مرتب.</p>
            </div>
        </section>

        <section class="mhp-gallery">
            <div class="mhp-shell">
                <article class="mhp-project mhp-project--feature" id="screens">
                    <div class="mhp-project__image">
                        <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_qd4tnvqd4tnvqd4t.jpg" alt="تصميم خلفية شاشة من ماركوز هوم" loading="eager">
                    </div>
                    <div class="mhp-project__info">
                        <span>01</span><h2>خلفيات شاشة</h2>
                        <p>توزيع متكامل للشاشة والوحدات والإضاءة بلمسات خشبية ودرجات محايدة عصرية.</p>
                        <a href="<?php echo esc_url($whatsapp . rawurlencode('مرحباً ماركوز هوم، أريد الاستفسار عن تصميم خلفية شاشة.')); ?>" target="_blank" rel="noopener">اطلب تصميم مشابه</a>
                    </div>
                </article>

                <div class="mhp-projects">
                    <article class="mhp-project" id="coffee">
                        <div class="mhp-project__image">
                            <img src="https://coffee.marcohom.com/coffee/brown-travertine.webp" alt="ركن قهوة بتصميم عصري" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>02</span><h2>أركان قهوة</h2><p>ركن عملي ومميز يناسب المساحة ويجمع التخزين مع جمال التفاصيل.</p></div>
                    </article>
                    <article class="mhp-project" id="beds">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.21-PM-1.jpeg" alt="خلفية سرير منفذة من ماركوز هوم" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>03</span><h2>خلفيات سرير</h2><p>تكوين هادئ بإضاءة مخفية وخامات متناسقة لغرفة نوم أكثر دفئًا.</p></div>
                    </article>
                    <article class="mhp-project" id="tv">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011.jpg" alt="طاولة تلفزيون معلقة من ماركوز هوم" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>04</span><h2>طاولات TV</h2><p>وحدات معلقة بخامات وألوان متعددة، وتصميم نظيف يسهل استخدامه.</p></div>
                    </article>
                    <article class="mhp-project" id="parquet">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_9a98879a98879a98.jpeg" alt="أرضيات باركيه عصرية" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>05</span><h2>أرضيات باركيه</h2><p>درجات خشبية تضيف دفئًا وأناقة، مع اختيار اللون الأنسب للأثاث.</p></div>
                    </article>
                    <article class="mhp-project" id="wpc">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_125uc4125uc4125u-Copy.jpg" alt="فواصل بديل الخشب للمساحات" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>06</span><h2>فواصل بديل الخشب</h2><p>تقسيم أنيق للمساحات يحافظ على الضوء والاتساع بدون جدران مغلقة.</p></div>
                    </article>
                    <article class="mhp-project" id="fire">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/11/Art-Fireplace-AFW230-3D-Water-Vapor-Fireplace-product-1.webp" alt="جهاز الفير المعطر ببخار الماء" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>07</span><h2>جهاز الفير المعطر</h2><p>تأثير لهب مائي مميز يضيف أجواء دافئة وتصميمًا لافتًا للمكان.</p></div>
                    </article>
                </div>
            </div>
        </section>

        <section class="mhp-real">
            <div class="mhp-shell">
                <div class="mhp-section-head">
                    <span class="mhp-eyebrow mhp-eyebrow--blue">تفاصيل من التنفيذ</span>
                    <h2>اختيارات هادئة وتشطيب نظيف</h2>
                </div>
                <div class="mhp-real__grid">
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg" alt="تنفيذ خلفية سرير رمادية" loading="lazy"><figcaption>خلفية سرير وإضاءة مخفية</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM.jpeg" alt="تنفيذ خلفية غرفة بدرجات خشبية" loading="lazy"><figcaption>خامات خشبية ودرجات محايدة</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg" alt="تصميم غرفة نوم بإضاءة دافئة" loading="lazy"><figcaption>إضاءة دافئة وتكوين متوازن</figcaption></figure>
                </div>
            </div>
        </section>

        <section class="mhp-steps">
            <div class="mhp-shell">
                <div class="mhp-section-head mhp-section-head--light">
                    <span class="mhp-eyebrow">من الصورة إلى التنفيذ</span>
                    <h2>كيف نبدأ مشروعك؟</h2>
                </div>
                <div class="mhp-steps__grid">
                    <div><b>01</b><h3>أرسل صورة المكان</h3><p>أرسل الصورة والمقاسات المتاحة على واتساب.</p></div>
                    <div><b>02</b><h3>نختار التصميم</h3><p>نحدد الخامة واللون والتوزيع الأنسب للمساحة.</p></div>
                    <div><b>03</b><h3>قياس وتنفيذ</h3><p>معاينة دقيقة ثم تنفيذ وتركيب وتشطيب نهائي.</p></div>
                </div>
            </div>
        </section>

        <section class="mhp-cta">
            <div class="mhp-shell mhp-cta__box">
                <div><span class="mhp-eyebrow mhp-eyebrow--blue">فكرتك ممكن تبدأ بصورة</span><h2>أرسل صورة مكانك وخد اقتراح مناسب</h2><p>تواصل مباشر لمعرفة التصميم والمقاس والتكلفة.</p></div>
                <a class="mhp-btn mhp-btn--dark" href="<?php echo esc_url($whatsapp . $ask_text); ?>" target="_blank" rel="noopener">ابدأ على واتساب</a>
            </div>
        </section>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_replace_portfolio_content(string $content): string {
    if (is_admin() || !mh_control_is_portfolio_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    return mh_control_portfolio_markup();
}
add_filter('the_content', 'mh_control_replace_portfolio_content', 998);

function mh_control_portfolio_title(string $title): string {
    if (mh_control_is_portfolio_page()) {
        return 'أعمال ماركوز هوم | تصميم وتنفيذ ديكور في الكويت';
    }
    return $title;
}
add_filter('pre_get_document_title', 'mh_control_portfolio_title', 100);

function mh_control_portfolio_seo_title(string $title): string {
    return mh_control_is_portfolio_page() ? 'أعمال ماركوز هوم | ديكور وتصميم داخلي في الكويت' : $title;
}

function mh_control_portfolio_seo_description(string $description): string {
    return mh_control_is_portfolio_page()
        ? 'شاهد أعمال ماركوز هوم في خلفيات الشاشة وأركان القهوة وخلفيات السرير وطاولات التلفزيون والباركيه وفواصل بديل الخشب والفير المعطر في الكويت.'
        : $description;
}
add_filter('aioseo_title', 'mh_control_portfolio_seo_title', 1000);
add_filter('aioseo_description', 'mh_control_portfolio_seo_description', 1000);
add_filter('wpseo_title', 'mh_control_portfolio_seo_title', 1000);
add_filter('wpseo_metadesc', 'mh_control_portfolio_seo_description', 1000);
add_filter('rank_math/frontend/title', 'mh_control_portfolio_seo_title', 1000);
add_filter('rank_math/frontend/description', 'mh_control_portfolio_seo_description', 1000);

function mh_control_portfolio_styles(): void {
    if (!mh_control_is_portfolio_page()) {
        return;
    }
    ?>
    <style id="mh-portfolio-styles">
    :root{--mhp-blue:#1266d6;--mhp-navy:#071a33;--mhp-ink:#14253b;--mhp-soft:#f2f6fa;--mhp-gold:#d6aa62}
    html:has(.mh-portfolio),body:has(.mh-portfolio){overflow-x:clip}.mh-portfolio{font-family:Tahoma,Arial,sans-serif;color:var(--mhp-ink);background:#fff;width:100vw;margin-inline:calc(50% - 50vw);overflow:hidden}
    .mh-portfolio *{box-sizing:border-box}.mhp-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhp-hero{min-height:520px;display:flex;align-items:center;position:relative;background:url('https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg') center 52%/cover no-repeat}
    .mhp-hero__shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,26,51,.22),rgba(7,26,51,.9))}
    .mhp-hero__content{position:relative;z-index:1;color:#fff;padding-block:90px}.mhp-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#dceaff;font-size:14px;font-weight:800;margin-bottom:16px}.mhp-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhp-gold)}.mhp-eyebrow--blue{color:var(--mhp-blue)}
    .mhp-hero h1{font-size:clamp(44px,7vw,78px);line-height:1.08;color:#fff;margin:0 0 20px;font-weight:900}.mhp-hero p{max-width:670px;font-size:18px;line-height:1.9;color:#edf4ff;margin:0 0 30px}
    .mhp-btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 24px;border-radius:8px;font-weight:800;text-decoration:none!important;transition:.2s ease}.mhp-btn:hover{transform:translateY(-2px)}.mhp-btn--green{background:#20b95a;color:#fff!important}.mhp-btn--dark{background:var(--mhp-navy);color:#fff!important}
    .mhp-categories{background:#fff;border-bottom:1px solid #e6edf4;position:relative;z-index:3}.mhp-categories .mhp-shell{display:flex;gap:8px;overflow-x:auto;padding-block:16px;scrollbar-width:none}.mhp-categories .mhp-shell::-webkit-scrollbar{display:none}.mhp-categories a{white-space:nowrap;border:1px solid #dbe4ed;border-radius:999px;padding:9px 16px;color:#31445b;text-decoration:none!important;font-size:13px;font-weight:700}.mhp-categories a:hover{background:var(--mhp-navy);color:#fff}
    .mhp-intro{padding:86px 0 55px}.mhp-intro__grid{display:grid;grid-template-columns:1.1fr .9fr;gap:70px;align-items:end}.mhp-intro h2,.mhp-section-head h2{font-size:clamp(32px,4vw,52px);line-height:1.25;color:var(--mhp-navy);margin:0}.mhp-intro p{margin:0;color:#637287;line-height:1.95;font-size:16px}
    .mhp-gallery{padding:25px 0 92px}.mhp-project{background:#fff;border:1px solid #e3eaf1;border-radius:18px;overflow:hidden;box-shadow:0 16px 42px rgba(7,26,51,.07);scroll-margin-top:100px}.mhp-project__image{height:360px;overflow:hidden;background:#e9eef3}.mhp-project__image img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .55s ease}.mhp-project:hover .mhp-project__image img{transform:scale(1.035)}.mhp-project__info{padding:26px 28px 30px}.mhp-project__info span{font-size:12px;color:var(--mhp-blue);font-weight:900}.mhp-project__info h2{font-size:25px;color:var(--mhp-navy);margin:8px 0 10px}.mhp-project__info p{font-size:14px;color:#68788d;line-height:1.8;margin:0}.mhp-project__info a{display:inline-block;margin-top:17px;color:var(--mhp-blue);font-weight:800;text-decoration:none!important}
    .mhp-project--feature{display:grid;grid-template-columns:1.45fr .55fr;margin-bottom:22px}.mhp-project--feature .mhp-project__image{height:520px}.mhp-project--feature .mhp-project__info{display:flex;flex-direction:column;justify-content:center;padding:50px}.mhp-project--feature .mhp-project__info h2{font-size:38px}
    .mhp-projects{display:grid;grid-template-columns:repeat(2,1fr);gap:22px}
    .mhp-real{background:var(--mhp-soft);padding:88px 0}.mhp-section-head{text-align:center;max-width:760px;margin:0 auto 42px}.mhp-real__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.mhp-real figure{margin:0;background:#fff;border-radius:15px;overflow:hidden}.mhp-real img{width:100%;height:380px;object-fit:cover;display:block}.mhp-real figcaption{padding:17px 20px;font-size:14px;font-weight:800;color:var(--mhp-navy)}
    .mhp-steps{padding:88px 0;background:var(--mhp-navy);color:#fff}.mhp-section-head--light h2{color:#fff}.mhp-steps__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.mhp-steps__grid>div{border:1px solid rgba(255,255,255,.13);background:rgba(255,255,255,.04);padding:30px;border-radius:14px}.mhp-steps__grid b{color:var(--mhp-gold);font-size:13px}.mhp-steps__grid h3{color:#fff;font-size:21px;margin:13px 0 9px}.mhp-steps__grid p{color:#aebed0;line-height:1.8;margin:0;font-size:14px}
    .mhp-cta{background:#edf3f9;padding:70px 0}.mhp-cta__box{background:#fff;border-radius:18px;padding:44px 50px;display:flex;align-items:center;justify-content:space-between;gap:30px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhp-cta h2{margin:0 0 10px;color:var(--mhp-navy);font-size:clamp(28px,4vw,42px)}.mhp-cta p{margin:0;color:#68778b}
    @media(max-width:900px){.mhp-intro__grid{grid-template-columns:1fr;gap:22px}.mhp-project--feature{grid-template-columns:1fr}.mhp-project--feature .mhp-project__image{height:420px}.mhp-project--feature .mhp-project__info{padding:30px}.mhp-real__grid{grid-template-columns:1fr 1fr}.mhp-steps__grid{grid-template-columns:1fr}.mhp-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:620px){.mhp-shell{width:min(100% - 28px,1180px)}.mhp-hero{min-height:540px}.mhp-hero__content{padding-block:70px}.mhp-hero h1{font-size:44px}.mhp-hero p{font-size:16px}.mhp-intro{padding:62px 0 38px}.mhp-gallery{padding-bottom:64px}.mhp-projects{grid-template-columns:1fr}.mhp-project__image,.mhp-project--feature .mhp-project__image{height:300px}.mhp-project--feature .mhp-project__info h2{font-size:29px}.mhp-real{padding:64px 0}.mhp-real__grid{grid-template-columns:1fr}.mhp-real img{height:340px}.mhp-steps{padding:64px 0}.mhp-cta{padding:48px 0}.mhp-cta__box{padding:30px 24px}.mhp-btn{width:100%}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_portfolio_styles', 99);


function mh_control_unify_visible_phone(): void {
    if (is_admin()) {
        return;
    }
    ?>
    <script id="mh-unified-phone">
    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.elementor-icon-box-title span').forEach(function(node){
            if(node.textContent.indexOf('0096550576266')!==-1){
                node.textContent=node.textContent.replace('0096550576266','0096550204320');
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_unify_visible_phone', 99);
