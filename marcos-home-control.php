<?php
/**
 * Plugin Name: Marco's Home Control
 * Plugin URI: https://marcohom.com/
 * Description: قناة آمنة لإدارة تعديلات موقع Marco's Home المنشورة من فرع WordPress المخصص.
 * Version: 0.5.0
 * Author: Marco's Home
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MH_CONTROL_VERSION', '0.5.0');

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
                    <a class="mh-card" href="https://marcohom.com/coffee-corner/">
                        <img src="https://coffee.marcohom.com/coffee/brown-travertine.webp" alt="ركن قهوة من ماركوز هوم">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>ركن القهوة</b><small>7 تصميمات — يبدأ من 35 د.ك</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/product/%d8%b7%d8%a7%d9%88%d9%84%d8%a7%d8%aa-tv/">
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
                        <div class="mhp-project__info"><span>02</span><h2>أركان قهوة</h2><p>ركن عملي ومميز يناسب المساحة ويجمع التخزين مع جمال التفاصيل.</p><a href="https://marcohom.com/coffee-corner/">شاهد التصميمات والأسعار</a></div>
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
                        <div class="mhp-project__info"><span>04</span><h2>طاولات TV</h2><p>وحدات معلقة بخامات وألوان متعددة، وتصميم نظيف يسهل استخدامه.</p><a href="https://marcohom.com/product/%d8%b7%d8%a7%d9%88%d9%84%d8%a7%d8%aa-tv/">شاهد المقاسات والأسعار</a></div>
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


/**
 * Coffee Corner sales page — product roadmap item 1.
 * Virtual route keeps the existing WordPress database and coffee app untouched.
 */
function mh_control_is_coffee_page(): bool {
    $path = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    return untrailingslashit((string) $path) === '/coffee-corner';
}

function mh_control_coffee_page_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=' . rawurlencode('مرحباً ماركوز هوم، أريد طلب ركن القهوة. اللون المطلوب:');
    ob_start();
    ?>
    <main class="mh-coffee" dir="rtl">
        <section class="mhc-hero">
            <div class="mhc-shell mhc-hero__grid">
                <div class="mhc-hero__copy">
                    <span class="mhc-eyebrow">ركن قهوة جاهز لمساحتك</span>
                    <h1>ركنك المفضل<br>بتصميم مرتب</h1>
                    <p>لوح ديكوري كامل الارتفاع مع طاولة معلقة ببابين، بألوان عصرية ومقاسات مدروسة تناسب البيت.</p>
                    <div class="mhc-actions">
                        <a class="mhc-btn mhc-btn--green" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">اطلب على واتساب</a>
                        <a class="mhc-btn mhc-btn--outline" href="https://coffee.marcohom.com/" target="_blank" rel="noopener">اختار التصميم واللون</a>
                    </div>
                    <div class="mhc-hero__trust">
                        <span>✓ 7 تصميمات</span><span>✓ تركيب داخل الكويت</span><span>✓ اختيار اللون قبل الطلب</span>
                    </div>
                </div>
                <figure class="mhc-hero__visual">
                    <img src="https://coffee.marcohom.com/coffee/brown-travertine.webp" alt="ركن قهوة ترافنتينو مع طاولة معلقة من ماركوز هوم">
                    <figcaption>ترافنتينو بني × خشب جوزي</figcaption>
                </figure>
            </div>
        </section>

        <section class="mhc-pricing" aria-label="أسعار ركن القهوة">
            <div class="mhc-shell mhc-pricing__grid">
                <div class="mhc-price">
                    <span>بدون تركيب</span>
                    <strong>35 <small>د.ك</small></strong>
                    <p>اللوح والطاولة جاهزان للاستلام</p>
                </div>
                <div class="mhc-price mhc-price--featured">
                    <i>الأكثر طلبًا</i>
                    <span>شامل التركيب</span>
                    <strong>50 <small>د.ك</small></strong>
                    <p>توريد وتركيب مرتب داخل الكويت</p>
                </div>
                <div class="mhc-pricing__note">
                    <b>محتاج مقاس مختلف؟</b>
                    <p>أرسل صورة المكان والمقاس، ونقترح عليك الحل الأنسب.</p>
                    <a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">أرسل صورة المكان ←</a>
                </div>
            </div>
        </section>

        <section class="mhc-specs">
            <div class="mhc-shell">
                <div class="mhc-heading">
                    <span class="mhc-eyebrow mhc-eyebrow--blue">المقاسات المعتمدة</span>
                    <h2>كل تفصيلة محسوبة</h2>
                    <p>توزيع متوازن يترك النعلة ظاهرة ويحافظ على شكل نظيف وعملي.</p>
                </div>
                <div class="mhc-specs__grid">
                    <div><b>122 سم</b><h3>عرض اللوح</h3><p>أعرض من الطاولة بـ 22 سم ليظهر التصميم متوازنًا.</p></div>
                    <div><b>290 سم</b><h3>ارتفاع اللوح</h3><p>يبدأ فوق النعلة الظاهرة ويصل إلى السقف.</p></div>
                    <div><b>100 سم</b><h3>عرض الطاولة</h3><p>طاولة معلقة ببابين متساويين، بدون أدراج.</p></div>
                    <div><b>10 سم</b><h3>النعلة ظاهرة</h3><p>اللوح يبدأ فوق النعلة لتشطيب مرتب وسهل التنظيف.</p></div>
                </div>
                <div class="mhc-specs__banner">
                    <span>لوح ديكوري كامل الارتفاع</span>
                    <span>طاولة معلقة ببابين</span>
                    <span>بدون إضاءة سفلية</span>
                    <span>اتجاه الخشب طولي</span>
                </div>
            </div>
        </section>

        <section class="mhc-designs" id="designs">
            <div class="mhc-shell">
                <div class="mhc-heading">
                    <span class="mhc-eyebrow mhc-eyebrow--blue">اختار اللون</span>
                    <h2>سبعة تصميمات جاهزة</h2>
                    <p>اضغط على التصميم داخل التطبيق لاختياره وإرسال الطلب.</p>
                </div>
                <div class="mhc-designs__grid">
                    <figure><img src="https://coffee.marcohom.com/coffee/white-lightwood.webp" alt="ركن قهوة أبيض وخشب فاتح" loading="lazy"><figcaption><b>أبيض × خشب فاتح</b><span>هادئ ومشرق</span></figcaption></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/brown-travertine.webp" alt="ركن قهوة ترافنتينو بني" loading="lazy"><figcaption><b>ترافنتينو × جوزي</b><span>دافئ وفاخر</span></figcaption></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/black-lightwood.webp" alt="ركن قهوة أسود وخشب فاتح" loading="lazy"><figcaption><b>أسود × خشب فاتح</b><span>تباين عصري</span></figcaption></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/darkgray-chevron.webp" alt="ركن قهوة رمادي غامق شيفرون" loading="lazy"><figcaption><b>رمادي غامق</b><span>شيفرون مميز</span></figcaption></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/lightgray-chevron.webp" alt="ركن قهوة رمادي فاتح شيفرون" loading="lazy"><figcaption><b>رمادي فاتح</b><span>خفيف وأنيق</span></figcaption></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/lightwood-chevron.webp" alt="ركن قهوة بيج خشبي شيفرون" loading="lazy"><figcaption><b>بيج خشبي</b><span>طبيعي وعملي</span></figcaption></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/honey-wood.webp" alt="ركن قهوة لون عسلي خشبي" loading="lazy"><figcaption><b>عسلي خشبي</b><span>درجة ماركوز هوم الدافئة</span></figcaption></figure>
                </div>
                <div class="mhc-designs__action">
                    <a class="mhc-btn mhc-btn--dark" href="https://coffee.marcohom.com/" target="_blank" rel="noopener">افتح أداة اختيار التصميم</a>
                </div>
            </div>
        </section>

        <section class="mhc-how">
            <div class="mhc-shell">
                <div class="mhc-heading mhc-heading--light">
                    <span class="mhc-eyebrow">الطلب في ثلاث خطوات</span>
                    <h2>اختار، أرسل، واستلم</h2>
                </div>
                <div class="mhc-how__grid">
                    <div><b>01</b><h3>اختار التصميم</h3><p>شاهد الألوان السبعة وحدد الشكل المناسب.</p></div>
                    <div><b>02</b><h3>أرسل بياناتك</h3><p>أدخل الاسم والمنطقة ورقم التواصل بسهولة.</p></div>
                    <div><b>03</b><h3>نؤكد الطلب</h3><p>نتواصل معك لتأكيد المقاس وموعد التركيب.</p></div>
                </div>
            </div>
        </section>

        <section class="mhc-cta">
            <div class="mhc-shell mhc-cta__box">
                <div><span class="mhc-eyebrow mhc-eyebrow--blue">جاهز تبدأ؟</span><h2>ركن قهوة كامل يبدأ من 35 د.ك</h2><p>اختار اللون المناسب أو أرسل صورة مكانك على واتساب.</p></div>
                <div class="mhc-actions">
                    <a class="mhc-btn mhc-btn--green" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">اطلب الآن</a>
                    <a class="mhc-btn mhc-btn--dark" href="https://coffee.marcohom.com/" target="_blank" rel="noopener">صمّم ركنك</a>
                </div>
            </div>
        </section>
        <a class="mhc-mobile-order" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">اطلب ركن القهوة — 35 د.ك</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_coffee_page(): void {
    if (!mh_control_is_coffee_page()) {
        return;
    }
    global $wp_query;
    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404 = false;
    }
    status_header(200);
    get_header();
    echo mh_control_coffee_page_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_coffee_page', 20);

function mh_control_coffee_title(string $title): string {
    return mh_control_is_coffee_page() ? 'ركن القهوة من ماركوز هوم | 35 د.ك' : $title;
}
add_filter('pre_get_document_title', 'mh_control_coffee_title', 110);

function mh_control_coffee_seo_description(string $description): string {
    return mh_control_is_coffee_page()
        ? 'ركن قهوة من ماركوز هوم: لوح ديكوري 122×290 سم وطاولة معلقة 100 سم بسبعة ألوان. 35 د.ك بدون تركيب أو 50 د.ك شامل التركيب داخل الكويت.'
        : $description;
}
add_filter('aioseo_title', 'mh_control_coffee_title', 1100);
add_filter('aioseo_description', 'mh_control_coffee_seo_description', 1100);
add_filter('wpseo_title', 'mh_control_coffee_title', 1100);
add_filter('wpseo_metadesc', 'mh_control_coffee_seo_description', 1100);
add_filter('rank_math/frontend/title', 'mh_control_coffee_title', 1100);
add_filter('rank_math/frontend/description', 'mh_control_coffee_seo_description', 1100);

function mh_control_coffee_body_class(array $classes): array {
    if (mh_control_is_coffee_page()) {
        $classes[] = 'mh-coffee-page';
    }
    return $classes;
}
add_filter('body_class', 'mh_control_coffee_body_class');

function mh_control_coffee_head(): void {
    if (!mh_control_is_coffee_page()) {
        return;
    }
    ?>
    <link rel="canonical" href="https://marcohom.com/coffee-corner/">
    <meta name="description" content="ركن قهوة من ماركوز هوم: لوح ديكوري 122×290 سم وطاولة معلقة 100 سم. 35 د.ك بدون تركيب أو 50 د.ك شامل التركيب داخل الكويت.">
    <style id="mh-coffee-styles">
    :root{--mhc-blue:#1266d6;--mhc-navy:#071a33;--mhc-ink:#15263a;--mhc-soft:#f2f6fa;--mhc-gold:#d6aa62;--mhc-green:#20b95a}
    html:has(.mh-coffee),body:has(.mh-coffee){overflow-x:clip}.mh-coffee{font-family:Tahoma,Arial,sans-serif;color:var(--mhc-ink);background:#fff;width:100vw;margin-inline:calc(50% - 50vw);overflow:hidden}.mh-coffee *{box-sizing:border-box}.mhc-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhc-hero{padding:72px 0;background:linear-gradient(135deg,#f8fafc,#e9f0f7)}.mhc-hero__grid{display:grid;grid-template-columns:.95fr 1.05fr;gap:72px;align-items:center}.mhc-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#61738a;font-size:14px;font-weight:800;margin-bottom:15px}.mhc-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhc-gold)}.mhc-eyebrow--blue{color:var(--mhc-blue)}
    .mhc-hero h1{font-size:clamp(44px,6vw,72px);line-height:1.12;color:var(--mhc-navy);margin:0 0 20px;font-weight:900}.mhc-hero__copy>p{font-size:18px;line-height:1.9;color:#607187;margin:0 0 28px;max-width:610px}.mhc-actions{display:flex;gap:12px;flex-wrap:wrap}.mhc-btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 23px;border-radius:8px;font-weight:900;text-decoration:none!important;transition:.2s ease}.mhc-btn:hover{transform:translateY(-2px)}.mhc-btn--green{background:var(--mhc-green);color:#fff!important}.mhc-btn--outline{border:1px solid #b8c5d3;color:var(--mhc-navy)!important;background:#fff}.mhc-btn--dark{background:var(--mhc-navy);color:#fff!important}
    .mhc-hero__trust{display:flex;gap:18px;flex-wrap:wrap;margin-top:25px;color:#52657b;font-size:13px;font-weight:700}.mhc-hero__visual{margin:0;position:relative;border-radius:22px;overflow:hidden;background:#ddd;box-shadow:0 24px 60px rgba(7,26,51,.15)}.mhc-hero__visual img{display:block;width:100%;height:600px;object-fit:cover}.mhc-hero__visual figcaption{position:absolute;bottom:18px;right:18px;background:rgba(7,26,51,.88);color:#fff;padding:10px 15px;border-radius:8px;font-size:13px}
    .mhc-pricing{padding:64px 0;background:#fff}.mhc-pricing__grid{display:grid;grid-template-columns:1fr 1fr 1.25fr;gap:18px;align-items:stretch}.mhc-price,.mhc-pricing__note{position:relative;border:1px solid #e0e8f0;border-radius:16px;padding:28px;background:#fff}.mhc-price--featured{border:2px solid var(--mhc-blue);background:#f8fbff}.mhc-price i{position:absolute;top:-13px;right:20px;background:var(--mhc-blue);color:#fff;border-radius:999px;padding:5px 12px;font-size:11px;font-style:normal;font-weight:800}.mhc-price>span{display:block;color:#5f7084;font-weight:800;font-size:14px}.mhc-price strong{display:block;color:var(--mhc-navy);font-size:52px;line-height:1;margin:16px 0 12px}.mhc-price strong small{font-size:18px}.mhc-price p,.mhc-pricing__note p{margin:0;color:#6c7b8e;line-height:1.7;font-size:13px}.mhc-pricing__note{background:var(--mhc-navy);display:flex;flex-direction:column;justify-content:center}.mhc-pricing__note b{color:#fff;font-size:20px}.mhc-pricing__note p{color:#b9c7d7;margin:10px 0}.mhc-pricing__note a{color:#8dbdff;text-decoration:none!important;font-weight:800}
    .mhc-specs{padding:88px 0;background:var(--mhc-soft)}.mhc-heading{text-align:center;max-width:720px;margin:0 auto 44px}.mhc-heading h2{font-size:clamp(34px,5vw,52px);color:var(--mhc-navy);margin:0 0 13px}.mhc-heading p{color:#64758a;line-height:1.8;margin:0}.mhc-specs__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mhc-specs__grid>div{background:#fff;border-radius:15px;padding:27px;border:1px solid #e1e8ef}.mhc-specs__grid b{color:var(--mhc-blue);font-size:28px}.mhc-specs__grid h3{color:var(--mhc-navy);font-size:18px;margin:10px 0}.mhc-specs__grid p{color:#6b7b8e;font-size:13px;line-height:1.75;margin:0}.mhc-specs__banner{display:flex;justify-content:center;gap:9px;flex-wrap:wrap;margin-top:24px}.mhc-specs__banner span{background:#e5edf6;color:#3f536b;border-radius:999px;padding:9px 15px;font-size:12px;font-weight:700}
    .mhc-designs{padding:90px 0}.mhc-designs__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.mhc-designs figure{margin:0;border:1px solid #e1e8ef;border-radius:17px;overflow:hidden;background:#fff;box-shadow:0 14px 35px rgba(7,26,51,.06)}.mhc-designs figure:first-child{grid-column:span 2}.mhc-designs img{width:100%;height:390px;object-fit:cover;display:block;transition:transform .5s ease}.mhc-designs figure:hover img{transform:scale(1.025)}.mhc-designs figcaption{display:flex;justify-content:space-between;gap:12px;padding:18px 20px}.mhc-designs figcaption b{color:var(--mhc-navy)}.mhc-designs figcaption span{color:#748398;font-size:12px}.mhc-designs__action{text-align:center;margin-top:34px}
    .mhc-how{padding:88px 0;background:var(--mhc-navy);color:#fff}.mhc-heading--light h2{color:#fff}.mhc-heading--light p{color:#b8c6d6}.mhc-how__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.mhc-how__grid>div{border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:30px;background:rgba(255,255,255,.04)}.mhc-how__grid b{color:var(--mhc-gold);font-size:13px}.mhc-how__grid h3{color:#fff;margin:13px 0 9px;font-size:21px}.mhc-how__grid p{color:#aebed0;line-height:1.8;margin:0;font-size:14px}
    .mhc-cta{padding:70px 0;background:#eaf1f7}.mhc-cta__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhc-cta h2{font-size:clamp(29px,4vw,43px);color:var(--mhc-navy);margin:0 0 10px}.mhc-cta p{margin:0;color:#68798e}.mhc-mobile-order{display:none}
    @media(max-width:900px){.mhc-hero__grid{grid-template-columns:1fr;gap:35px}.mhc-hero__visual img{height:520px}.mhc-pricing__grid{grid-template-columns:1fr 1fr}.mhc-pricing__note{grid-column:span 2}.mhc-specs__grid{grid-template-columns:1fr 1fr}.mhc-designs__grid{grid-template-columns:1fr 1fr}.mhc-designs figure:first-child{grid-column:span 2}.mhc-how__grid{grid-template-columns:1fr}.mhc-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mhc-shell{width:min(100% - 28px,1180px)}.mhc-hero{padding:52px 0}.mhc-hero h1{font-size:43px}.mhc-hero__copy>p{font-size:16px}.mhc-actions{display:grid;width:100%}.mhc-btn{width:100%}.mhc-hero__visual img{height:450px}.mhc-pricing{padding:48px 0}.mhc-pricing__grid{grid-template-columns:1fr}.mhc-pricing__note{grid-column:auto}.mhc-specs,.mhc-designs,.mhc-how{padding:64px 0}.mhc-specs__grid,.mhc-designs__grid{grid-template-columns:1fr}.mhc-designs figure:first-child{grid-column:auto}.mhc-designs img{height:430px}.mhc-cta{padding:48px 0 90px}.mhc-cta__box{padding:30px 24px}.mhc-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mhc-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_coffee_head', 100);


/**
 * TV console product page — product roadmap item 2.
 * Keeps WooCommerce product 6455 and its public URL unchanged.
 */
function mh_control_is_tv_console_page(): bool {
    return is_singular('product') && get_queried_object_id() === 6455;
}

function mh_control_tv_console_markup(): string {
    $order_text = rawurlencode('مرحباً ماركوز هوم، أريد طلب طاولة TV مقاس 1.5 متر، اللون أبيض، بدون تركيب، السعر 40 د.ك.');
    ob_start();
    ?>
    <main class="mh-tv" dir="rtl">
        <section class="mht-hero">
            <div class="mht-shell mht-hero__grid">
                <div class="mht-hero__copy">
                    <span class="mht-eyebrow">طاولات TV معلقة</span>
                    <h1>شكل أنيق.<br>تخزين عملي.</h1>
                    <p>طاولة تلفزيون معلقة بأربعة أبواب متساوية، بتصميم بسيط وألوان تناسب ديكور بيتك.</p>
                    <div class="mht-badges"><span>ارتفاع 25 سم</span><span>عمق 32 سم</span><span>7 ألوان</span></div>
                </div>
                <figure class="mht-hero__visual">
                    <img src="https://marcohom.com/wp-content/uploads/2025/11/Generated-Image-November-03-2025-7_43PM.png" alt="طاولة تلفزيون معلقة من ماركوز هوم">
                </figure>
            </div>
        </section>

        <section class="mht-builder" id="order">
            <div class="mht-shell mht-builder__grid">
                <div class="mht-builder__choices">
                    <span class="mht-eyebrow mht-eyebrow--blue">كوّن طلبك</span>
                    <h2>اختار المقاس واللون</h2>

                    <fieldset>
                        <legend>1. المقاس</legend>
                        <div class="mht-choice-row">
                            <button class="mht-choice is-active" type="button" data-mht-size="1.5 متر" data-mht-base="40" data-mht-install="50" aria-pressed="true"><b>1.5 متر</b><small>4 أبواب — 40 د.ك</small></button>
                            <button class="mht-choice" type="button" data-mht-size="2 متر" data-mht-base="50" data-mht-install="60" aria-pressed="false"><b>2 متر</b><small>4 أبواب — 50 د.ك</small></button>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>2. اللون</legend>
                        <div class="mht-colors">
                            <button class="mht-color is-active" type="button" data-mht-color="أبيض" aria-pressed="true"><i style="--swatch:#f1f1ed"></i><span>أبيض</span></button>
                            <button class="mht-color" type="button" data-mht-color="أسود" aria-pressed="false"><i style="--swatch:#202124"></i><span>أسود</span></button>
                            <button class="mht-color" type="button" data-mht-color="رمادي فاتح" aria-pressed="false"><i style="--swatch:#c9cbca"></i><span>رمادي فاتح</span></button>
                            <button class="mht-color" type="button" data-mht-color="رمادي غامق" aria-pressed="false"><i style="--swatch:#55585b"></i><span>رمادي غامق</span></button>
                            <button class="mht-color" type="button" data-mht-color="بيج خشبي" aria-pressed="false"><i style="--swatch:#cbb89b"></i><span>بيج خشبي</span></button>
                            <button class="mht-color" type="button" data-mht-color="عسلي خشبي" aria-pressed="false"><i style="--swatch:#ad6f31"></i><span>عسلي</span></button>
                            <button class="mht-color" type="button" data-mht-color="جوزي" aria-pressed="false"><i style="--swatch:#6f4329"></i><span>جوزي</span></button>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>3. التركيب</legend>
                        <div class="mht-install">
                            <button class="mht-install-choice is-active" type="button" data-mht-installation="بدون تركيب" data-mht-installed="0" aria-pressed="true"><b>بدون تركيب</b><small>استلام الطاولة جاهزة</small></button>
                            <button class="mht-install-choice" type="button" data-mht-installation="شامل التركيب" data-mht-installed="1" aria-pressed="false"><b>مع التركيب</b><small>إضافة 10 د.ك</small></button>
                        </div>
                    </fieldset>
                </div>

                <aside class="mht-summary">
                    <span>السعر الحالي</span>
                    <strong><em id="mht-price">40</em> <small>د.ك</small></strong>
                    <ul>
                        <li><span>المقاس</span><b id="mht-size-summary">1.5 متر</b></li>
                        <li><span>اللون</span><b id="mht-color-summary">أبيض</b></li>
                        <li><span>الخدمة</span><b id="mht-install-summary">بدون تركيب</b></li>
                    </ul>
                    <a id="mht-whatsapp" class="mht-btn mht-btn--green" href="https://wa.me/96550204320?text=<?php echo esc_attr($order_text); ?>" target="_blank" rel="noopener">اطلب على واتساب</a>
                    <p>سنراجع اللون والمقاس معك قبل تأكيد الطلب.</p>
                </aside>
            </div>
        </section>

        <section class="mht-specs">
            <div class="mht-shell">
                <div class="mht-heading">
                    <span class="mht-eyebrow mht-eyebrow--blue">تفاصيل ثابتة</span>
                    <h2>تصميم نحيف ومساحة تخزين أكبر</h2>
                </div>
                <div class="mht-specs__grid">
                    <div><b>25 سم</b><h3>الارتفاع</h3><p>ارتفاع نحيف يحافظ على مظهر خفيف وأنيق.</p></div>
                    <div><b>32 سم</b><h3>العمق</h3><p>عمق عملي للتخزين بدون بروز زائد عن الحائط.</p></div>
                    <div><b>4 أبواب</b><h3>تقسيم متساوٍ</h3><p>واجهة نظيفة ومساحات منظمة للاستخدام اليومي.</p></div>
                    <div><b>معلّقة</b><h3>سهولة التنظيف</h3><p>شكل عصري مع فراغ واضح أسفل الطاولة.</p></div>
                </div>
            </div>
        </section>

        <section class="mht-colors-gallery">
            <div class="mht-shell">
                <div class="mht-heading">
                    <span class="mht-eyebrow mht-eyebrow--blue">اختار درجتك</span>
                    <h2>سبعة ألوان تناسب ديكورك</h2>
                    <p>الألوان المعروضة مرجع للاختيار، ونؤكد الدرجة النهائية معك قبل التنفيذ.</p>
                </div>
                <div class="mht-gallery">
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/IMG-20251031-WA0109.jpg" alt="طاولة تلفزيون بيضاء معلقة" loading="lazy"><figcaption>أبيض</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/IMG-20251031-WA0108.jpg" alt="طاولة تلفزيون سوداء معلقة" loading="lazy"><figcaption>أسود</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/IMG-20251031-WA0106.jpg" alt="طاولة تلفزيون رمادي فاتح" loading="lazy"><figcaption>رمادي فاتح</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/IMG-20251031-WA0111.jpg" alt="طاولة تلفزيون رمادي غامق" loading="lazy"><figcaption>رمادي غامق</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/IMG-20251031-WA0114-1.jpg" alt="طاولة تلفزيون بيج خشبي" loading="lazy"><figcaption>بيج خشبي</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/IMG-20251031-WA0115.jpg" alt="طاولة تلفزيون عسلي خشبي" loading="lazy"><figcaption>عسلي خشبي</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011.jpg" alt="طاولة تلفزيون جوزي" loading="lazy"><figcaption>جوزي</figcaption></figure>
                </div>
            </div>
        </section>

        <section class="mht-prices">
            <div class="mht-shell">
                <div class="mht-heading mht-heading--light">
                    <span class="mht-eyebrow">أسعار واضحة</span>
                    <h2>اختار المقاس المناسب</h2>
                </div>
                <div class="mht-prices__grid">
                    <div><span>1.5 متر</span><strong>40 <small>د.ك</small></strong><p>بدون تركيب</p><b>50 د.ك شامل التركيب</b></div>
                    <div class="is-featured"><i>مساحة أكبر</i><span>2 متر</span><strong>50 <small>د.ك</small></strong><p>بدون تركيب</p><b>60 د.ك شامل التركيب</b></div>
                </div>
                <p class="mht-install-note">خدمة التركيب داخل الكويت: 10 د.ك فقط.</p>
            </div>
        </section>

        <section class="mht-cta">
            <div class="mht-shell mht-cta__box">
                <div><span class="mht-eyebrow mht-eyebrow--blue">جاهز تطلب؟</span><h2>اختار المقاس واللون وأرسل الطلب</h2><p>رسالة واتساب جاهزة بكل اختياراتك والسعر النهائي.</p></div>
                <a class="mht-btn mht-btn--dark" href="#order">كوّن طلبك الآن</a>
            </div>
        </section>
        <a class="mht-mobile-order" href="#order">اختار طاولة TV — تبدأ من 40 د.ك</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_tv_console_page(): void {
    if (!mh_control_is_tv_console_page()) {
        return;
    }
    status_header(200);
    get_header();
    echo mh_control_tv_console_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_tv_console_page', 30);

function mh_control_tv_console_title(string $title): string {
    return mh_control_is_tv_console_page() ? 'طاولات TV معلقة من ماركوز هوم | تبدأ من 40 د.ك' : $title;
}
add_filter('pre_get_document_title', 'mh_control_tv_console_title', 120);

function mh_control_tv_console_description(string $description): string {
    return mh_control_is_tv_console_page()
        ? 'طاولات تلفزيون معلقة بمقاس 1.5 أو 2 متر، ارتفاع 25 سم وعمق 32 سم وسبعة ألوان. تبدأ من 40 د.ك، والتركيب داخل الكويت 10 د.ك.'
        : $description;
}
add_filter('aioseo_title', 'mh_control_tv_console_title', 1200);
add_filter('aioseo_description', 'mh_control_tv_console_description', 1200);
add_filter('wpseo_title', 'mh_control_tv_console_title', 1200);
add_filter('wpseo_metadesc', 'mh_control_tv_console_description', 1200);
add_filter('rank_math/frontend/title', 'mh_control_tv_console_title', 1200);
add_filter('rank_math/frontend/description', 'mh_control_tv_console_description', 1200);

function mh_control_tv_console_head(): void {
    if (!mh_control_is_tv_console_page()) {
        return;
    }
    ?>
    <meta name="description" content="طاولات تلفزيون معلقة بمقاس 1.5 أو 2 متر وسبعة ألوان. تبدأ من 40 د.ك، وخدمة التركيب داخل الكويت 10 د.ك.">
    <style id="mh-tv-styles">
    :root{--mht-blue:#1266d6;--mht-navy:#071a33;--mht-ink:#15263a;--mht-soft:#f2f6fa;--mht-gold:#d6aa62;--mht-green:#20b95a}
    html:has(.mh-tv),body:has(.mh-tv){overflow-x:clip}.mh-tv{font-family:Tahoma,Arial,sans-serif;color:var(--mht-ink);background:#fff;width:100vw;margin-inline:calc(50% - 50vw);overflow:hidden}.mh-tv *{box-sizing:border-box}.mht-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mht-hero{padding:72px 0;background:linear-gradient(135deg,#f8fafc,#e8eff6)}.mht-hero__grid{display:grid;grid-template-columns:.75fr 1.25fr;gap:60px;align-items:center}.mht-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#61738a;font-size:14px;font-weight:800;margin-bottom:15px}.mht-eyebrow:before{content:"";width:32px;height:2px;background:var(--mht-gold)}.mht-eyebrow--blue{color:var(--mht-blue)}.mht-hero h1{font-size:clamp(44px,6vw,72px);line-height:1.12;color:var(--mht-navy);margin:0 0 20px;font-weight:900}.mht-hero__copy>p{font-size:18px;line-height:1.9;color:#607187;margin:0 0 26px}.mht-badges{display:flex;gap:8px;flex-wrap:wrap}.mht-badges span{padding:8px 13px;border:1px solid #cad5df;border-radius:999px;color:#4d6075;font-size:12px;font-weight:700}.mht-hero__visual{margin:0;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 24px 60px rgba(7,26,51,.14)}.mht-hero__visual img{display:block;width:100%;height:520px;object-fit:cover}
    .mht-builder{padding:88px 0}.mht-builder__grid{display:grid;grid-template-columns:1.3fr .7fr;gap:36px;align-items:start}.mht-builder__choices h2,.mht-heading h2{font-size:clamp(34px,5vw,52px);color:var(--mht-navy);margin:0 0 32px}.mht-builder fieldset{border:0;padding:0;margin:0 0 29px}.mht-builder legend{font-size:17px;color:var(--mht-navy);font-weight:900;margin-bottom:13px}.mht-choice-row,.mht-install{display:grid;grid-template-columns:1fr 1fr;gap:12px}.mht-choice,.mht-install-choice{font:inherit;text-align:right;border:1px solid #d9e2eb;border-radius:12px;background:#fff;padding:18px 20px;cursor:pointer;color:var(--mht-ink)}.mht-choice b,.mht-choice small,.mht-install-choice b,.mht-install-choice small{display:block}.mht-choice small,.mht-install-choice small{color:#718096;margin-top:6px;font-size:12px}.mht-choice.is-active,.mht-install-choice.is-active{border:2px solid var(--mht-blue);background:#f5f9ff}.mht-colors{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.mht-color{font:inherit;border:1px solid #dde5ed;border-radius:11px;background:#fff;padding:12px 8px;cursor:pointer;color:#44576c}.mht-color i{display:block;width:30px;height:30px;border-radius:50%;margin:0 auto 7px;background:var(--swatch);border:1px solid rgba(0,0,0,.1)}.mht-color span{font-size:11px;font-weight:800}.mht-color.is-active{outline:2px solid var(--mht-blue);outline-offset:1px}
    .mht-summary{position:sticky;top:25px;border-radius:18px;padding:32px;background:var(--mht-navy);color:#fff;box-shadow:0 20px 45px rgba(7,26,51,.18)}.mht-summary>span{color:#aebed0;font-size:14px}.mht-summary>strong{display:block;font-size:60px;line-height:1;margin:14px 0 25px}.mht-summary>strong em{font-style:normal}.mht-summary>strong small{font-size:18px}.mht-summary ul{list-style:none;padding:0;margin:0 0 24px;border-block:1px solid rgba(255,255,255,.13)}.mht-summary li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;color:#b8c6d6;font-size:13px}.mht-summary li+li{border-top:1px solid rgba(255,255,255,.09)}.mht-summary li b{color:#fff}.mht-btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 23px;border-radius:8px;font-weight:900;text-decoration:none!important;transition:.2s ease}.mht-btn:hover{transform:translateY(-2px)}.mht-btn--green{background:var(--mht-green);color:#fff!important;width:100%}.mht-btn--dark{background:var(--mht-navy);color:#fff!important}.mht-summary>p{text-align:center;color:#9eafc2;font-size:11px;margin:13px 0 0}
    .mht-specs{padding:88px 0;background:var(--mht-soft)}.mht-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mht-heading p{color:#68798d;line-height:1.8;margin:0}.mht-specs__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mht-specs__grid>div{background:#fff;border:1px solid #e1e8ef;border-radius:15px;padding:27px}.mht-specs__grid b{font-size:27px;color:var(--mht-blue)}.mht-specs__grid h3{font-size:18px;color:var(--mht-navy);margin:10px 0}.mht-specs__grid p{font-size:13px;line-height:1.75;color:#6b7b8e;margin:0}
    .mht-colors-gallery{padding:90px 0}.mht-gallery{display:grid;grid-template-columns:repeat(4,1fr);gap:17px}.mht-gallery figure{margin:0;border:1px solid #e1e8ef;border-radius:15px;overflow:hidden;background:#fff}.mht-gallery figure:first-child{grid-column:span 2}.mht-gallery img{width:100%;height:310px;object-fit:contain;background:#f7f8f9;display:block;transition:transform .4s ease}.mht-gallery figure:hover img{transform:scale(1.025)}.mht-gallery figcaption{padding:16px 18px;color:var(--mht-navy);font-weight:900}
    .mht-prices{padding:88px 0;background:var(--mht-navy);color:#fff}.mht-heading--light h2{color:#fff}.mht-prices__grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:760px;margin:0 auto}.mht-prices__grid>div{position:relative;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);border-radius:16px;padding:31px}.mht-prices__grid>div.is-featured{border:2px solid #4f9cff}.mht-prices__grid i{position:absolute;top:-13px;right:20px;background:var(--mht-blue);padding:5px 11px;border-radius:999px;font-size:11px;font-style:normal}.mht-prices__grid span{display:block;color:#b9c6d5;font-weight:800}.mht-prices__grid strong{display:block;font-size:52px;margin:13px 0 8px}.mht-prices__grid strong small{font-size:17px}.mht-prices__grid p{margin:0 0 12px;color:#aebdce}.mht-prices__grid b{color:#fff}.mht-install-note{text-align:center;color:#b9c6d5;margin:24px 0 0}
    .mht-cta{padding:70px 0;background:#eaf1f7}.mht-cta__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mht-cta h2{font-size:clamp(29px,4vw,43px);color:var(--mht-navy);margin:0 0 10px}.mht-cta p{margin:0;color:#68798e}.mht-mobile-order{display:none}
    @media(max-width:900px){.mht-hero__grid,.mht-builder__grid{grid-template-columns:1fr}.mht-hero__visual img{height:440px}.mht-summary{position:static}.mht-specs__grid{grid-template-columns:1fr 1fr}.mht-gallery{grid-template-columns:1fr 1fr}.mht-gallery figure:first-child{grid-column:span 2}.mht-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mht-shell{width:min(100% - 28px,1180px)}.mht-hero{padding:52px 0}.mht-hero h1{font-size:43px}.mht-hero__visual img{height:330px}.mht-builder,.mht-specs,.mht-colors-gallery,.mht-prices{padding:64px 0}.mht-choice-row,.mht-install,.mht-specs__grid,.mht-gallery{grid-template-columns:1fr}.mht-colors{grid-template-columns:repeat(3,1fr)}.mht-gallery figure:first-child{grid-column:auto}.mht-gallery img{height:270px}.mht-prices__grid{grid-template-columns:1fr}.mht-cta{padding:48px 0 90px}.mht-cta__box{padding:30px 24px}.mht-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mht-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_tv_console_head', 101);

function mh_control_tv_console_script(): void {
    if (!mh_control_is_tv_console_page()) {
        return;
    }
    ?>
    <script id="mh-tv-builder">
    document.addEventListener('DOMContentLoaded',function(){
        var state={size:'1.5 متر',color:'أبيض',base:40,installed:0,installation:'بدون تركيب'};
        var price=document.getElementById('mht-price');
        var link=document.getElementById('mht-whatsapp');
        function update(){
            var finalPrice=state.installed?state.install:state.base;
            price.textContent=String(finalPrice);
            document.getElementById('mht-size-summary').textContent=state.size;
            document.getElementById('mht-color-summary').textContent=state.color;
            document.getElementById('mht-install-summary').textContent=state.installation;
            var msg='مرحباً ماركوز هوم، أريد طلب طاولة TV مقاس '+state.size+'، اللون '+state.color+'، '+state.installation+'، السعر '+finalPrice+' د.ك.';
            link.href='https://wa.me/96550204320?text='+encodeURIComponent(msg);
        }
        document.querySelectorAll('[data-mht-size]').forEach(function(button){
            button.addEventListener('click',function(){
                document.querySelectorAll('[data-mht-size]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});
                button.classList.add('is-active');button.setAttribute('aria-pressed','true');
                state.size=button.dataset.mhtSize;state.base=Number(button.dataset.mhtBase);state.install=Number(button.dataset.mhtInstall);update();
            });
        });
        document.querySelectorAll('[data-mht-color]').forEach(function(button){
            button.addEventListener('click',function(){
                document.querySelectorAll('[data-mht-color]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});
                button.classList.add('is-active');button.setAttribute('aria-pressed','true');
                state.color=button.dataset.mhtColor;update();
            });
        });
        document.querySelectorAll('[data-mht-installed]').forEach(function(button){
            button.addEventListener('click',function(){
                document.querySelectorAll('[data-mht-installed]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});
                button.classList.add('is-active');button.setAttribute('aria-pressed','true');
                state.installed=Number(button.dataset.mhtInstalled);state.installation=button.dataset.mhtInstallation;update();
            });
        });
        update();
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_tv_console_script', 100);
