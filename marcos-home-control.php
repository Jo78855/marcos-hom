<?php
/**
 * Plugin Name: Marco's Home Control
 * Plugin URI: https://marcohom.com/
 * Description: قناة آمنة لإدارة تعديلات موقع Marco's Home المنشورة من فرع WordPress المخصص.
 * Version: 1.12.3
 * Author: Marco's Home
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MH_CONTROL_VERSION', '1.12.4');
define('MH_CONTROL_SNAP_PIXEL_ID', '2770b368-fa3d-49f1-bf4e-685b62c10ecf');
define('MH_CONTROL_META_PIXEL_ID', '761400161961314');
define('MH_CONTROL_LEADS_DB_VERSION', '1.0');

function mh_control_google_maps_url(): string {
    return 'https://maps.app.goo.gl/GMPEmTXtd66YkdpY6?g_st=iwb';
}

function mh_control_request_path(): string {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
    $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $path = trim($path, '/');
    return $path === '' ? '/' : '/' . $path . '/';
}

function mh_control_leads_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'mh_leads';
}

function mh_control_install_leads_table(): void {
    if ((string) get_option('mh_control_leads_db_version', '') === MH_CONTROL_LEADS_DB_VERSION) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = mh_control_leads_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        name varchar(120) NOT NULL,
        phone varchar(24) NOT NULL,
        area varchar(120) NOT NULL DEFAULT '',
        product varchar(80) NOT NULL,
        details text NOT NULL,
        source varchar(80) NOT NULL DEFAULT 'direct',
        medium varchar(80) NOT NULL DEFAULT '',
        campaign varchar(120) NOT NULL DEFAULT '',
        page varchar(160) NOT NULL DEFAULT '',
        status varchar(30) NOT NULL DEFAULT 'new',
        consent tinyint(1) unsigned NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset};";
    dbDelta($sql);
    update_option('mh_control_leads_db_version', MH_CONTROL_LEADS_DB_VERSION, false);
}
add_action('plugins_loaded', 'mh_control_install_leads_table');

function mh_control_prepare_virtual_page(): void {
    global $wp_query;
    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
    }
    status_header(200);
}

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
    global $wpdb;
    $leads = $wpdb->get_results('SELECT * FROM ' . mh_control_leads_table() . ' ORDER BY created_at DESC LIMIT 200', ARRAY_A);
    $leads = is_array($leads) ? $leads : [];
    $lead_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . mh_control_leads_table());
    $status_labels = [
        'new' => 'جديد',
        'contacted' => 'تم التواصل',
        'quoted' => 'تم إرسال عرض',
        'won' => 'تم البيع',
        'closed' => 'مغلق',
    ];
    $stats = get_option('mh_control_ad_stats', []);
    $stats = is_array($stats) ? $stats : [];
    $stats = array_filter($stats, static fn($row): bool => is_array($row) && ($row['source'] ?? '') !== 'deployment_check');
    $views = 0;
    $clicks = 0;
    foreach ($stats as $row) {
        if (!is_array($row)) continue;
        $count = max(0, (int) ($row['count'] ?? 0));
        if (($row['event'] ?? '') === 'page_view') $views += $count;
        if (($row['event'] ?? '') === 'whatsapp_click') $clicks += $count;
    }
    uasort($stats, static fn($a, $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
    ?>
    <div class="wrap mh-admin" dir="rtl">
        <h1>لوحة إعلانات Marco's Home</h1>
        <p><strong>حالة الربط:</strong> جاهز لتسجيل الزيارات والنقرات &nbsp; | &nbsp; <strong>الإصدار:</strong> <?php echo esc_html(MH_CONTROL_VERSION); ?></p>
        <div class="mh-admin-cards">
            <div><span>زيارات صفحات الإعلان</span><strong><?php echo esc_html(number_format_i18n($views)); ?></strong></div>
            <div><span>نقرات واتساب</span><strong><?php echo esc_html(number_format_i18n($clicks)); ?></strong></div>
            <div><span>طلبات عرض السعر</span><strong><?php echo esc_html(number_format_i18n($lead_count)); ?></strong></div>
            <div><span>تحويل الزيارة إلى طلب</span><strong><?php echo esc_html($views > 0 ? number_format_i18n(($lead_count / $views) * 100, 1) . '%' : '—'); ?></strong></div>
        </div>
        <h2 id="mh-leads">العملاء وطلبات عرض السعر</h2>
        <p>تظهر الطلبات الجديدة هنا مع مصدر الحملة. استخدم الحالة لمتابعة العميل حتى إتمام البيع.</p>
        <div class="mh-admin-table mh-leads-table"><table class="widefat striped"><thead><tr><th>رقم</th><th>التاريخ</th><th>العميل</th><th>المنطقة</th><th>المنتج والتفاصيل</th><th>المصدر</th><th>الحالة</th><th>تواصل</th></tr></thead><tbody>
        <?php if ($leads === []): ?>
            <tr><td colspan="8">لا توجد طلبات بعد. سيظهر أول طلب فور إرساله من صفحة الفاير أو الطاولات.</td></tr>
        <?php else: foreach ($leads as $lead):
            $phone_digits = preg_replace('/\D+/', '', (string) ($lead['phone'] ?? ''));
            $product_label = ($lead['product'] ?? '') === 'fire-blaze' ? 'جهاز Fire Blaze' : 'طاولة TV معلقة';
            $current_status = (string) ($lead['status'] ?? 'new');
            $message = rawurlencode('مرحباً ' . (string) ($lead['name'] ?? '') . '، معك ماركوز هوم بخصوص طلب عرض السعر رقم #' . (string) ($lead['id'] ?? '') . '.');
        ?>
            <tr>
                <td><strong>#<?php echo esc_html((string) ($lead['id'] ?? '')); ?></strong></td>
                <td><?php echo esc_html((string) ($lead['created_at'] ?? '')); ?></td>
                <td><b><?php echo esc_html((string) ($lead['name'] ?? '')); ?></b><br><span dir="ltr"><?php echo esc_html((string) ($lead['phone'] ?? '')); ?></span></td>
                <td><?php echo esc_html((string) ($lead['area'] ?? '—')); ?></td>
                <td><b><?php echo esc_html($product_label); ?></b><br><?php echo esc_html((string) ($lead['details'] ?? '')); ?></td>
                <td><?php echo esc_html((string) ($lead['source'] ?? 'direct')); ?><br><small><?php echo esc_html((string) ($lead['campaign'] ?? '')); ?></small></td>
                <td>
                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="mh-status-form">
                        <input type="hidden" name="action" value="mh_update_lead_status">
                        <input type="hidden" name="lead_id" value="<?php echo esc_attr((string) ($lead['id'] ?? '')); ?>">
                        <?php wp_nonce_field('mh_update_lead_' . (string) ($lead['id'] ?? '')); ?>
                        <select name="status" onchange="this.form.submit()" aria-label="حالة الطلب">
                            <?php foreach ($status_labels as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td><a class="button button-primary" href="https://wa.me/<?php echo esc_attr($phone_digits); ?>?text=<?php echo esc_attr($message); ?>" target="_blank" rel="noopener">واتساب</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
        <h2>تفاصيل آخر 90 يومًا</h2>
        <p>البيانات مجمعة ولا تخزن اسم الزائر أو رقم الهاتف أو عنوان IP.</p>
        <div class="mh-admin-table"><table class="widefat striped"><thead><tr><th>التاريخ</th><th>الصفحة</th><th>المصدر</th><th>الحملة</th><th>النتيجة</th><th>اختيار العميل</th><th>العدد</th></tr></thead><tbody>
        <?php if ($stats === []): ?>
            <tr><td colspan="7">لا توجد بيانات بعد. ستبدأ اللوحة في التسجيل عند زيارة روابط UTM الجديدة.</td></tr>
        <?php else: foreach (array_slice($stats, 0, 200) as $row): ?>
            <tr>
                <td><?php echo esc_html((string) ($row['date'] ?? '')); ?></td>
                <td><?php echo esc_html((string) ($row['page'] ?? '')); ?></td>
                <td><?php echo esc_html((string) ($row['source'] ?? 'direct')); ?></td>
                <td><?php echo esc_html((string) ($row['campaign'] ?? '—')); ?></td>
                <td><?php echo esc_html(($row['event'] ?? '') === 'whatsapp_click' ? 'نقرة واتساب' : (($row['event'] ?? '') === 'lead_submitted' ? 'طلب عرض سعر' : 'زيارة')); ?></td>
                <td><?php echo esc_html((string) ($row['details'] ?? '—')); ?></td>
                <td><strong><?php echo esc_html(number_format_i18n((int) ($row['count'] ?? 0))); ?></strong></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </div>
    <style>.mh-admin{max-width:1280px}.mh-admin-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:24px 0}.mh-admin-cards>div{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px}.mh-admin-cards span{display:block;color:#646970;font-weight:700}.mh-admin-cards strong{display:block;font-size:34px;color:#071a33;margin-top:12px}.mh-admin-table{overflow:auto;background:#fff;margin-bottom:32px}.mh-leads-table td{vertical-align:middle;min-width:90px}.mh-leads-table td:nth-child(5){min-width:240px}.mh-status-form select{min-width:120px}@media(max-width:900px){.mh-admin-cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.mh-admin-cards{grid-template-columns:1fr}}</style>
    <?php
}

function mh_control_update_lead_status(): void {
    if (!current_user_can('manage_options')) {
        wp_die('غير مسموح.');
    }
    $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
    check_admin_referer('mh_update_lead_' . $lead_id);
    $status = isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'new';
    $allowed = ['new', 'contacted', 'quoted', 'won', 'closed'];
    if ($lead_id > 0 && in_array($status, $allowed, true)) {
        global $wpdb;
        $wpdb->update(
            mh_control_leads_table(),
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $lead_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    wp_safe_redirect(admin_url('tools.php?page=marcos-home-control#mh-leads'));
    exit;
}
add_action('admin_post_mh_update_lead_status', 'mh_control_update_lead_status');

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

function mh_control_register_ad_event_route(): void {
    register_rest_route('marcos-home/v1', '/ad-event', [
        'methods' => 'POST',
        'callback' => 'mh_control_record_ad_event',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'mh_control_register_ad_event_route');

function mh_control_register_lead_route(): void {
    register_rest_route('marcos-home/v1', '/lead', [
        'methods' => 'POST',
        'callback' => 'mh_control_create_lead',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'mh_control_register_lead_route');

function mh_control_record_internal_stat(string $event, string $page, string $source, string $campaign, string $medium): void {
    $date = current_time('Y-m-d');
    $source = $source !== '' ? $source : 'direct';
    $page = $page !== '' ? $page : '/';
    $stats = get_option('mh_control_ad_stats', []);
    $stats = is_array($stats) ? $stats : [];
    $key = md5(implode('|', [$date, $page, $source, $campaign, $medium, $event]));
    $stats[$key] = [
        'date' => $date,
        'page' => $page,
        'source' => $source,
        'campaign' => $campaign,
        'medium' => $medium,
        'event' => $event,
        'count' => max(0, (int) ($stats[$key]['count'] ?? 0)) + 1,
    ];
    $cutoff = gmdate('Y-m-d', strtotime('-90 days'));
    $stats = array_filter($stats, static fn($row): bool => is_array($row) && (string) ($row['date'] ?? '') >= $cutoff);
    update_option('mh_control_ad_stats', $stats, false);
}

function mh_control_create_lead(WP_REST_Request $request): WP_REST_Response {
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $origin = (string) $request->get_header('origin');
    $referer = (string) $request->get_header('referer');
    $request_host = strtolower((string) wp_parse_url($origin !== '' ? $origin : $referer, PHP_URL_HOST));
    if ($request_host !== $site_host && $request_host !== 'www.' . $site_host) {
        return new WP_REST_Response(['saved' => false, 'message' => 'تعذر التحقق من مصدر الطلب.'], 403);
    }
    if ((string) $request->get_param('website') !== '') {
        return new WP_REST_Response(['saved' => true], 200);
    }

    $started_at = (int) $request->get_param('started_at');
    $elapsed = (int) round(microtime(true) * 1000) - $started_at;
    if ($started_at <= 0 || $elapsed < 1500 || $elapsed > 21600000) {
        return new WP_REST_Response(['saved' => false, 'message' => 'حدّث الصفحة وحاول مرة أخرى.'], 400);
    }

    $clean = static function ($value, int $max): string {
        $value = sanitize_text_field((string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    };
    $name = $clean($request->get_param('name'), 120);
    $phone_digits = (string) preg_replace('/\D+/', '', (string) $request->get_param('phone'));
    if (strlen($phone_digits) === 11 && str_starts_with($phone_digits, '965')) {
        $phone_digits = substr($phone_digits, 3);
    }
    $area = $clean($request->get_param('area'), 120);
    $product = sanitize_key((string) $request->get_param('product'));
    $details = $clean($request->get_param('details'), 500);
    $source = strtolower($clean($request->get_param('source'), 80));
    $medium = strtolower($clean($request->get_param('medium'), 80));
    $campaign = $clean($request->get_param('campaign'), 120);
    $page = $clean($request->get_param('page'), 160);
    $consent = (bool) $request->get_param('consent');
    $allowed_products = ['fire-blaze', 'tv-tables'];

    if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) < 2) {
        return new WP_REST_Response(['saved' => false, 'field' => 'name', 'message' => 'اكتب الاسم بشكل صحيح.'], 400);
    }
    if (strlen($phone_digits) !== 8) {
        return new WP_REST_Response(['saved' => false, 'field' => 'phone', 'message' => 'اكتب رقم كويتي صحيح من 8 أرقام.'], 400);
    }
    if (!in_array($product, $allowed_products, true) || !$consent) {
        return new WP_REST_Response(['saved' => false, 'message' => 'راجع بيانات الطلب والموافقة.'], 400);
    }

    $rate_key = 'mh_lead_' . md5($phone_digits);
    if (get_transient($rate_key)) {
        return new WP_REST_Response(['saved' => false, 'message' => 'تم استلام طلبك بالفعل. يمكنك المتابعة على واتساب.'], 429);
    }

    mh_control_install_leads_table();
    global $wpdb;
    $now = current_time('mysql');
    $inserted = $wpdb->insert(
        mh_control_leads_table(),
        [
            'created_at' => $now,
            'updated_at' => $now,
            'name' => $name,
            'phone' => '+965' . $phone_digits,
            'area' => $area,
            'product' => $product,
            'details' => $details,
            'source' => $source !== '' ? $source : 'direct',
            'medium' => $medium,
            'campaign' => $campaign,
            'page' => $page,
            'status' => 'new',
            'consent' => 1,
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
    );
    if ($inserted === false) {
        return new WP_REST_Response(['saved' => false, 'message' => 'تعذر حفظ الطلب الآن. جرّب التواصل على واتساب.'], 500);
    }
    set_transient($rate_key, 1, 120);
    mh_control_record_internal_stat('lead_submitted', $page, $source, $campaign, $medium);

    $product_name = $product === 'fire-blaze' ? 'جهاز Fire Blaze' : 'طاولة TV معلقة';
    $message = "مرحباً ماركوز هوم، سجلت طلب عرض سعر رقم #{$wpdb->insert_id}.\n"
        . "الاسم: {$name}\nالمنتج: {$product_name}\nالمنطقة: " . ($area !== '' ? $area : 'غير محددة')
        . "\nالتفاصيل: " . ($details !== '' ? $details : 'أحتاج عرض سعر وتفاصيل أكثر.');
    return new WP_REST_Response([
        'saved' => true,
        'lead_id' => (int) $wpdb->insert_id,
        'whatsapp_url' => 'https://wa.me/96550204320?text=' . rawurlencode($message),
    ], 201);
}

function mh_control_record_ad_event(WP_REST_Request $request): WP_REST_Response {
    $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $origin = (string) $request->get_header('origin');
    $referer = (string) $request->get_header('referer');
    $request_host = strtolower((string) wp_parse_url($origin !== '' ? $origin : $referer, PHP_URL_HOST));
    if ($request_host !== '' && $request_host !== $site_host && $request_host !== 'www.' . $site_host) {
        return new WP_REST_Response(['recorded' => false], 403);
    }

    $event = sanitize_key((string) $request->get_param('event'));
    $allowed_events = ['page_view', 'whatsapp_click'];
    if (!in_array($event, $allowed_events, true)) {
        return new WP_REST_Response(['recorded' => false], 400);
    }

    $clean = static function ($value, int $max = 80): string {
        $value = sanitize_text_field((string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    };
    $page = $clean($request->get_param('page'), 100);
    $source = strtolower($clean($request->get_param('source'), 50));
    $campaign = $clean($request->get_param('campaign'), 80);
    $medium = $clean($request->get_param('medium'), 50);
    $details = $clean($request->get_param('details'), 300);
    $date = current_time('Y-m-d');
    $source = $source !== '' ? $source : 'direct';
    $page = $page !== '' ? $page : '/';
    if ($source === 'deployment_check') {
        return new WP_REST_Response(['recorded' => true, 'test' => true], 200);
    }

    $stats = get_option('mh_control_ad_stats', []);
    $stats = is_array($stats) ? $stats : [];
    $key = md5(implode('|', [$date, $page, $source, $campaign, $medium, $event, $details]));
    $stats[$key] = [
        'date' => $date,
        'page' => $page,
        'source' => $source,
        'campaign' => $campaign,
        'medium' => $medium,
        'event' => $event,
        'details' => $details,
        'count' => max(0, (int) ($stats[$key]['count'] ?? 0)) + 1,
    ];
    $cutoff = gmdate('Y-m-d', strtotime('-90 days'));
    $stats = array_filter($stats, static fn($row): bool => is_array($row) && (string) ($row['date'] ?? '') >= $cutoff);
    update_option('mh_control_ad_stats', $stats, false);

    return new WP_REST_Response(['recorded' => true], 200);
}

function mh_control_quote_form(): void {
    $path = mh_control_request_path();
    $products = [
        '/fire-blaze/' => ['slug' => 'fire-blaze', 'label' => 'جهاز Fire Blaze'],
        '/tv-tables/' => ['slug' => 'tv-tables', 'label' => 'طاولة TV معلقة'],
    ];
    if (!isset($products[$path])) return;
    $product = $products[$path];
    ?>
    <button type="button" class="mhq-floating" id="mhq-open">اطلب عرض سعر</button>
    <div class="mhq-modal" id="mhq-modal" hidden>
        <div class="mhq-backdrop" data-mhq-close></div>
        <section class="mhq-dialog" role="dialog" aria-modal="true" aria-labelledby="mhq-title" dir="rtl">
            <button type="button" class="mhq-close" data-mhq-close aria-label="إغلاق">×</button>
            <span class="mhq-kicker">طلب سريع — أقل من دقيقة</span>
            <h2 id="mhq-title">احصل على عرض سعر لـ<?php echo esc_html($product['label']); ?></h2>
            <p>سنسجل اختيارك ونفتح واتساب برسالة جاهزة لإكمال التفاصيل.</p>
            <form id="mhq-form" novalidate>
                <div class="mhq-grid">
                    <label><span>الاسم *</span><input name="name" type="text" autocomplete="name" maxlength="120" required></label>
                    <label><span>رقم الهاتف الكويتي *</span><input name="phone" type="tel" inputmode="numeric" autocomplete="tel" maxlength="15" placeholder="مثال: 50204320" required></label>
                </div>
                <label><span>المنطقة</span><input name="area" type="text" autocomplete="address-level2" maxlength="120" placeholder="مثال: حولي، السالمية، الفروانية"></label>
                <label><span>ملاحظة إضافية</span><textarea name="note" rows="3" maxlength="300" placeholder="المقاس أو موعد التواصل المناسب"></textarea></label>
                <label class="mhq-consent"><input name="consent" type="checkbox" value="1" required><span>أوافق على استخدام بياناتي للتواصل بخصوص هذا الطلب وفق <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank" rel="noopener">سياسة الخصوصية</a>.</span></label>
                <label class="mhq-hp" aria-hidden="true"><span>Website</span><input name="website" type="text" tabindex="-1" autocomplete="off"></label>
                <p class="mhq-error" id="mhq-error" role="alert" hidden></p>
                <button type="submit" class="mhq-submit">سجل الطلب وتابع على واتساب</button>
            </form>
            <div class="mhq-success" id="mhq-success" hidden><b>تم تسجيل طلبك بنجاح</b><span>جاري فتح واتساب لإكمال الطلب…</span></div>
        </section>
    </div>
    <style id="mhq-style">
    .mhq-floating{position:fixed;left:24px;bottom:24px;z-index:99990;border:0;border-radius:999px;background:#0877c9;color:#fff;padding:14px 22px;font:800 15px/1.2 inherit;box-shadow:0 12px 30px rgba(3,45,82,.28);cursor:pointer}.mhq-floating:hover{background:#065f9f}.mhq-modal[hidden]{display:none!important}.mhq-modal{position:fixed;inset:0;z-index:999999;display:grid;place-items:center;padding:18px}.mhq-backdrop{position:absolute;inset:0;background:rgba(2,14,28,.72);backdrop-filter:blur(4px)}.mhq-dialog{position:relative;width:min(620px,100%);max-height:calc(100vh - 36px);overflow:auto;background:#fff;border-radius:22px;padding:34px;box-shadow:0 28px 80px rgba(0,0,0,.3);color:#102238}.mhq-close{position:absolute;left:16px;top:12px;border:0;background:#eef3f7;width:38px;height:38px;border-radius:50%;font-size:28px;line-height:1;cursor:pointer}.mhq-kicker{display:inline-block;color:#0877c9;font-weight:800;margin-bottom:7px}.mhq-dialog h2{margin:0 0 8px;font-size:28px;line-height:1.35}.mhq-dialog>p{margin:0 0 22px;color:#586777}.mhq-dialog label{display:block;margin-bottom:15px}.mhq-dialog label>span:first-child{display:block;font-weight:750;margin-bottom:7px}.mhq-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.mhq-dialog input[type=text],.mhq-dialog input[type=tel],.mhq-dialog textarea{box-sizing:border-box;width:100%;border:1px solid #cad5df;border-radius:11px;padding:12px 13px;font:500 16px/1.5 inherit;background:#fff;color:#102238}.mhq-dialog input:focus,.mhq-dialog textarea:focus{outline:3px solid rgba(8,119,201,.16);border-color:#0877c9}.mhq-consent{display:flex!important;gap:9px;align-items:flex-start;color:#465567;font-size:14px}.mhq-consent input{margin-top:4px;flex:0 0 auto}.mhq-consent span{font-weight:500!important;margin:0!important}.mhq-consent a{color:#0877c9}.mhq-hp{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}.mhq-submit{width:100%;border:0;border-radius:12px;background:#1fa463;color:#fff;padding:14px 18px;font:800 16px/1.3 inherit;cursor:pointer}.mhq-submit:disabled{opacity:.6;cursor:wait}.mhq-error{background:#fff1f1;color:#a51d27;border-radius:9px;padding:10px 12px;margin:0 0 12px}.mhq-success{padding:28px;text-align:center;background:#effaf4;border-radius:14px;color:#12633d}.mhq-success b,.mhq-success span{display:block}.mhq-success b{font-size:23px;margin-bottom:8px}@media(max-width:640px){.mhq-floating{left:14px;bottom:78px;padding:12px 17px}.mhq-dialog{padding:30px 20px 22px;border-radius:18px}.mhq-dialog h2{font-size:23px}.mhq-grid{grid-template-columns:1fr;gap:0}}
    </style>
    <script id="mhq-script">
    (function(){
        var modal=document.getElementById('mhq-modal');
        var form=document.getElementById('mhq-form');
        var error=document.getElementById('mhq-error');
        var success=document.getElementById('mhq-success');
        var startedAt=Date.now();
        var endpoint=<?php echo wp_json_encode(rest_url('marcos-home/v1/lead')); ?>;
        var product=<?php echo wp_json_encode($product); ?>;
        var params=new URLSearchParams(location.search);
        function readText(selector){var node=document.querySelector(selector);return node?node.textContent.trim():'';}
        function productDetails(){
            var note=form.elements.note.value.trim();
            var parts=[];
            if(product.slug==='fire-blaze'){
                parts.push('المقاس: '+(readText('#mhf-size-summary')||'40 سم'));
                parts.push('السعر الظاهر: '+(readText('#mhf-price')||'85')+' د.ك');
            }else{
                parts.push('المقاس: '+(readText('#mht-size-summary')||'1.5 متر'));
                parts.push('اللون: '+(readText('#mht-color-summary')||'أبيض'));
                parts.push('الخدمة: '+(readText('#mht-install-summary')||'بدون تركيب'));
                parts.push('السعر الظاهر: '+(readText('#mht-price')||'40')+' د.ك');
            }
            if(note)parts.push('ملاحظة: '+note);
            return parts.join(' — ');
        }
        function openModal(){startedAt=Date.now();modal.hidden=false;document.body.style.overflow='hidden';setTimeout(function(){form.elements.name.focus();},50);}
        function closeModal(){modal.hidden=true;document.body.style.overflow='';}
        document.getElementById('mhq-open').addEventListener('click',openModal);
        modal.querySelectorAll('[data-mhq-close]').forEach(function(el){el.addEventListener('click',closeModal);});
        document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!modal.hidden)closeModal();});
        document.addEventListener('click',function(event){
            var target=event.target;
            var order=target&&target.closest?target.closest('#mht-whatsapp,#mhf-whatsapp'):null;
            if(!order)return;
            event.preventDefault();event.stopImmediatePropagation();openModal();
        },true);
        form.addEventListener('submit',function(event){
            event.preventDefault();
            error.hidden=true;
            if(!form.reportValidity())return;
            var button=form.querySelector('button[type=submit]');
            button.disabled=true;button.textContent='جاري تسجيل الطلب…';
            var payload={
                name:form.elements.name.value.trim(),phone:form.elements.phone.value.trim(),area:form.elements.area.value.trim(),
                product:product.slug,details:productDetails(),page:location.pathname,
                source:params.get('utm_source')||'direct',medium:params.get('utm_medium')||'',campaign:params.get('utm_campaign')||'',
                consent:form.elements.consent.checked,website:form.elements.website.value,started_at:startedAt
            };
            fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(payload)})
                .then(function(response){return response.json().then(function(data){if(!response.ok)throw new Error(data.message||'تعذر تسجيل الطلب.');return data;});})
                .then(function(data){
                    if(typeof window.fbq==='function'){window.fbq('track','Lead',{content_name:product.label,content_category:product.slug});}
                    if(typeof window.snaptr==='function'){window.snaptr('track','START_CHECKOUT',{item_ids:[product.slug],item_category:product.label});}
                    window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'quote_lead_submit',product:product.slug,lead_id:data.lead_id});
                    form.hidden=true;success.hidden=false;
                    setTimeout(function(){window.location.assign(data.whatsapp_url);},800);
                })
                .catch(function(err){error.textContent=err.message;error.hidden=false;button.disabled=false;button.textContent='سجل الطلب وتابع على واتساب';});
        });
    }());
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_quote_form', 996);

function mh_control_ad_tracking_script(): void {
    $tracked_paths = ['/fire-blaze/', '/tv-tables/', '/design-198/', '/services/', '/coffee-corner/'];
    if (!in_array(mh_control_request_path(), $tracked_paths, true)) return;
    ?>
    <script id="mh-ad-tracking">
    (function(){
        var endpoint=<?php echo wp_json_encode(rest_url('marcos-home/v1/ad-event')); ?>;
        var params=new URLSearchParams(location.search);
        var source=params.get('utm_source')||'';
        if(!source&&document.referrer){try{source=new URL(document.referrer).hostname.replace(/^www\./,'');}catch(e){}}
        var payload={page:location.pathname,source:source||'direct',medium:params.get('utm_medium')||'',campaign:params.get('utm_campaign')||''};
        function send(event,details){
            var body=JSON.stringify(Object.assign({},payload,{event:event,details:details||''}));
            if(navigator.sendBeacon){navigator.sendBeacon(endpoint,new Blob([body],{type:'application/json'}));}
            else{fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:body,credentials:'same-origin',keepalive:true});}
        }
        var viewKey='mh_ad_view:'+location.pathname+':'+payload.source+':'+payload.campaign;
        if(!sessionStorage.getItem(viewKey)){send('page_view');sessionStorage.setItem(viewKey,'1');}
        document.addEventListener('click',function(event){
            var link=event.target.closest('a[href*="wa.me/"]');
            if(link){send('whatsapp_click',link.dataset.mhAdDetails||'');}
        },true);
    }());
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_ad_tracking_script', 999);

function mh_control_snap_pixel_script(): void {
    $path = mh_control_request_path();
    $products = [
        '/fire-blaze/' => [
            'item_ids' => ['fire-blaze'],
            'item_category' => 'Home Decor',
            'price' => 85.00,
            'currency' => 'KWD',
        ],
        '/tv-tables/' => [
            'item_ids' => ['tv-tables'],
            'item_category' => 'TV Tables',
            'price' => 40.00,
            'currency' => 'KWD',
        ],
        '/design-198/' => [
            'item_ids' => ['design-198'],
            'item_category' => 'TV Wall Design',
            'price' => 130.00,
            'currency' => 'KWD',
        ],
    ];
    $tracked_paths = array_merge(array_keys($products), ['/services/', '/coffee-corner/']);
    if (!in_array($path, $tracked_paths, true)) return;

    $product = $products[$path] ?? null;
    ?>
    <script id="mh-snap-pixel">
    (function(e,t,n){
        if(e.snaptr)return;
        var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};
        a.queue=[];
        var s='script',r=t.createElement(s);
        r.async=true;r.src=n;
        var u=t.getElementsByTagName(s)[0];
        u.parentNode.insertBefore(r,u);
    })(window,document,'https://sc-static.net/scevent.min.js');
    snaptr('init',<?php echo wp_json_encode(MH_CONTROL_SNAP_PIXEL_ID); ?>);
    snaptr('track','PAGE_VIEW');
    <?php if (is_array($product)): ?>
    var mhSnapProduct=<?php echo wp_json_encode($product); ?>;
    snaptr('track','VIEW_CONTENT',mhSnapProduct);
    document.addEventListener('click',function(event){
        var target=event.target;
        var link=target&&target.closest?target.closest('a[href*="wa.me/"]'):null;
        if(link && !link.matches('#mh198-whatsapp,#mh198-mobile-whatsapp')){snaptr('track','START_CHECKOUT',mhSnapProduct);}
    },true);
    <?php endif; ?>
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_snap_pixel_script', 998);

function mh_control_meta_pixel_script(): void {
    $path = mh_control_request_path();
    $products = [
        '/fire-blaze/' => [
            'content_ids' => ['fire-blaze'],
            'content_name' => 'Fire Blaze Water Flame Diffuser',
            'content_category' => 'Home Decor',
            'content_type' => 'product',
            'value' => 85.00,
            'currency' => 'KWD',
        ],
        '/tv-tables/' => [
            'content_ids' => ['tv-tables'],
            'content_name' => 'Floating TV Table',
            'content_category' => 'TV Tables',
            'content_type' => 'product',
            'value' => 40.00,
            'currency' => 'KWD',
        ],
        '/design-198/' => [
            'content_ids' => ['design-198'],
            'content_name' => 'Design 198 Pyramid Wood',
            'content_category' => 'TV Wall Design',
            'content_type' => 'product',
            'value' => 130.00,
            'currency' => 'KWD',
        ],
    ];
    $tracked_paths = array_merge(array_keys($products), ['/services/', '/coffee-corner/']);
    if (!in_array($path, $tracked_paths, true)) return;

    $product = $products[$path] ?? null;
    $contact = is_array($product) ? $product : [
        'content_name' => 'Marco Home consultation',
        'content_category' => 'Interior Design',
    ];
    ?>
    <script id="mh-meta-pixel">
    !function(f,b,e,v,n,t,s){
        if(f.fbq)return;
        n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=true;n.version='2.0';n.queue=[];
        t=b.createElement(e);t.async=true;t.src=v;
        s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s);
    }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init',<?php echo wp_json_encode(MH_CONTROL_META_PIXEL_ID); ?>);
    fbq('track','PageView');
    <?php if (is_array($product)): ?>
    fbq('track','ViewContent',<?php echo wp_json_encode($product); ?>);
    <?php endif; ?>
    var mhMetaContact=<?php echo wp_json_encode($contact); ?>;
    document.addEventListener('click',function(event){
        var target=event.target;
        var link=target&&target.closest?target.closest('a[href*="wa.me/"]'):null;
        if(link && !link.matches('#mh198-whatsapp,#mh198-mobile-whatsapp')){fbq('track','Contact',mhMetaContact);}
    },true);
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_meta_pixel_script', 997);


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
                    <a class="mh-btn mh-btn--ghost" href="<?php echo esc_url(home_url('/services/')); ?>">شاهد خدماتنا</a>
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
                    <a class="mh-card mh-card--wide" href="https://marcohom.com/product-category/%d9%86%d9%85%d8%a7%d8%b0%d8%ac-%d9%88%d8%aa%d8%b5%d9%85%d9%8a%d9%85%d8%a7%d8%aa/?service=tv-wall">
                        <img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg" alt="خلفيات شاشة وتصميمات ديكور من ماركوز هوم">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>خلفيات الشاشة والديكور</b><small>تبدأ من 98 د.ك شامل التوريد والتركيب</small></span>
                    </a>
                    <a class="mh-card mh-card--wide" href="<?php echo esc_url(home_url('/design-198/')); ?>">
                        <img src="<?php echo esc_url(mh_control_design_198_asset('design-198-beige-wood.webp')); ?>" alt="تصميم 198 الخشب الهرمي من ماركوز هوم">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>تصميم 198 — الخشب الهرمي</b><small>يبدأ من 130 د.ك بدون تركيب / 170 د.ك مع التركيب</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/coffee-corner/">
                        <img src="https://coffee.marcohom.com/coffee/brown-travertine.webp" alt="ركن قهوة من ماركوز هوم">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>ركن القهوة</b><small>7 تصميمات — يبدأ من 35 د.ك</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/tv-tables/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/11/Generated-Image-November-03-2025-7_43PM-270x270.png" alt="طاولات تلفزيون معلقة">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>طاولات TV</b><small>مقاسات وألوان تناسب تصميمك</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/product/%d8%a8%d8%a7%d8%b1%d9%83%d9%8a%d8%a9-%d8%ae%d8%b4%d8%a8-k9188/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_9a98879a98879a98.jpeg" alt="أرضيات باركيه">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>أرضيات باركيه</b><small>دفء وأناقة وسهولة في التنظيف</small></span>
                    </a>
                    <a class="mh-card" href="https://marcohom.com/product/%d9%82%d8%a7%d8%b7%d8%b9-%d8%a7%d9%84%d8%a7%d8%b9%d9%85%d8%af%d8%a9/">
                        <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_125uc4125uc4125u-Copy.jpg" alt="فواصل أعمدة بديل الخشب">
                        <span class="mh-card__shade"></span><span class="mh-card__text"><b>فواصل بديل الخشب</b><small>فصل أنيق للمساحات بدون إغلاقها</small></span>
                    </a>
                    <a class="mh-card mh-card--wide" href="https://marcohom.com/product/%d8%a7%d9%84%d9%81%d9%8a%d8%b1-%d8%a7%d9%84%d9%85%d8%b9%d8%b7%d8%b1/">
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
    $html = (string) ob_get_clean();
    return (string) preg_replace('/<img(?![^>]*\\bloading=)/i', '<img loading="lazy" decoding="async"', $html);
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
    .mh-home{font-family:Tahoma,Arial,sans-serif;color:var(--mh-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}
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
                        <div class="mhp-project__info"><span>04</span><h2>طاولات TV</h2><p>وحدات معلقة بخامات وألوان متعددة، وتصميم نظيف يسهل استخدامه.</p><a href="https://marcohom.com/tv-tables/">شاهد المقاسات والأسعار</a></div>
                    </article>
                    <article class="mhp-project" id="parquet">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_9a98879a98879a98.jpeg" alt="أرضيات باركيه عصرية" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>05</span><h2>أرضيات باركيه</h2><p>درجات خشبية تضيف دفئًا وأناقة، مع اختيار اللون الأنسب للأثاث.</p><a class="mhp-project__link" href="https://marcohom.com/product/%d8%a8%d8%a7%d8%b1%d9%83%d9%8a%d8%a9-%d8%ae%d8%b4%d8%a8-k9188/">احسب المساحة والتكلفة</a></div>
                    </article>
                    <article class="mhp-project" id="wpc">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_125uc4125uc4125u-Copy.jpg" alt="فواصل بديل الخشب للمساحات" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>06</span><h2>فواصل بديل الخشب</h2><p>تقسيم أنيق للمساحات يحافظ على الضوء والاتساع بدون جدران مغلقة.</p><a class="mhp-project__link" href="https://marcohom.com/product/%d9%82%d8%a7%d8%b7%d8%b9-%d8%a7%d9%84%d8%a7%d8%b9%d9%85%d8%af%d8%a9/">احسب العدد والسعر</a></div>
                    </article>
                    <article class="mhp-project" id="fire">
                        <div class="mhp-project__image">
                            <img src="https://marcohom.com/wp-content/uploads/2025/11/Art-Fireplace-AFW230-3D-Water-Vapor-Fireplace-product-1.webp" alt="جهاز الفير المعطر ببخار الماء" loading="lazy">
                        </div>
                        <div class="mhp-project__info"><span>07</span><h2>جهاز الفير المعطر</h2><p>تأثير لهب مائي مميز يضيف أجواء دافئة وتصميمًا لافتًا للمكان.</p><a class="mhp-project__link" href="https://marcohom.com/product/%d8%a7%d9%84%d9%81%d9%8a%d8%b1-%d8%a7%d9%84%d9%85%d8%b9%d8%b7%d8%b1/">شاهد المقاسات والأسعار</a></div>
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
    $html = (string) ob_get_clean();
    return (string) preg_replace('/<img(?![^>]*\\bloading=)/i', '<img loading="lazy" decoding="async"', $html);
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
    html:has(.mh-portfolio),body:has(.mh-portfolio){overflow-x:clip}.mh-portfolio{font-family:Tahoma,Arial,sans-serif;color:var(--mhp-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}
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
    html:has(.mh-coffee),body:has(.mh-coffee){overflow-x:clip}.mh-coffee{font-family:Tahoma,Arial,sans-serif;color:var(--mhc-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-coffee *{box-sizing:border-box}.mhc-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
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
    return (is_singular('product') && get_queried_object_id() === 6455)
        || mh_control_request_path() === '/tv-tables/';
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

        <section id="table-gallery" class="mht-dimensions" aria-label="صور ومقاسات طاولات التلفزيون">
            <div class="mht-shell">
                <div class="mht-heading">
                    <span class="mht-eyebrow mht-eyebrow--blue">مقاس واضح قبل الاختيار</span>
                    <h2>شاهد الطاولة داخل المساحة</h2>
                    <p>سبعة تشطيبات لمقاس 1.5 متر بارتفاع 25 سم وعمق 32 سم، لتختار الدرجة الأنسب لديكورك.</p>
                </div>
                <div class="mht-dimensions__grid">
                    <figure>
                        <img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-walnut.webp" alt="طاولة تلفزيون معلقة خشبية مقاس 150 في 32 في 25 سم" width="1448" height="1086" loading="lazy">
                        <figcaption><b>تشطيب خشبي دافئ</b><span>150 × 32 × 25 سم</span></figcaption>
                    </figure>
                    <figure>
                        <img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-charcoal.webp" alt="طاولة تلفزيون معلقة رمادي غامق مقاس 150 في 32 في 25 سم" width="1448" height="1086" loading="lazy">
                        <figcaption><b>رمادي غامق</b><span>150 × 32 × 25 سم</span></figcaption>
                    </figure>
                    <figure>
                        <img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-light-gray.webp" alt="طاولة تلفزيون معلقة رمادي فاتح مقاس 150 في 32 في 25 سم" width="1448" height="1086" loading="lazy">
                        <figcaption><b>رمادي فاتح</b><span>150 × 32 × 25 سم</span></figcaption>
                    </figure>
                    <figure>
                        <img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-white.webp" alt="طاولة تلفزيون معلقة باللون الأبيض مقاس 150 في 32 في 25 سم" width="1448" height="1086" loading="lazy">
                        <figcaption><b>أبيض</b><span>150 × 32 × 25 سم</span></figcaption>
                    </figure>
                    <figure>
                        <img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-black.webp" alt="طاولة تلفزيون معلقة باللون الأسود مقاس 150 في 32 في 25 سم" width="1448" height="1086" loading="lazy">
                        <figcaption><b>أسود</b><span>150 × 32 × 25 سم</span></figcaption>
                    </figure>
                    <figure>
                        <img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-light-oak.webp" alt="طاولة تلفزيون معلقة خشب فاتح مقاس 150 في 32 في 25 سم" width="1448" height="1086" loading="lazy">
                        <figcaption><b>خشب فاتح</b><span>150 × 32 × 25 سم</span></figcaption>
                    </figure>
                </div>
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
                            <button class="mht-color is-active" type="button" data-mht-color="أبيض" aria-pressed="true"><img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-white.webp" alt="طاولة تلفزيون معلقة باللون الأبيض" width="1448" height="1086" loading="lazy"><span>أبيض</span></button>
                            <button class="mht-color" type="button" data-mht-color="أسود" aria-pressed="false"><img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-black.webp" alt="طاولة تلفزيون معلقة باللون الأسود" width="1448" height="1086" loading="lazy"><span>أسود</span></button>
                            <button class="mht-color" type="button" data-mht-color="رمادي فاتح" aria-pressed="false"><img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-light-gray.webp" alt="طاولة تلفزيون معلقة رمادي فاتح" width="1448" height="1086" loading="lazy"><span>رمادي فاتح</span></button>
                            <button class="mht-color" type="button" data-mht-color="رمادي غامق" aria-pressed="false"><img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-charcoal.webp" alt="طاولة تلفزيون معلقة رمادي غامق" width="1448" height="1086" loading="lazy"><span>رمادي غامق</span></button>
                            <button class="mht-color" type="button" data-mht-color="بيج خشبي" aria-pressed="false"><img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-light-oak.webp" alt="طاولة تلفزيون معلقة بتشطيب بيج خشبي" width="1448" height="1086" loading="lazy"><span>بيج خشبي</span></button>
                            <button class="mht-color" type="button" data-mht-color="عسلي خشبي" aria-pressed="false"><img src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-walnut.webp" alt="طاولة تلفزيون معلقة بتشطيب عسلي خشبي" width="1448" height="1086" loading="lazy"><span>عسلي</span></button>
                            <button class="mht-color" type="button" data-mht-color="جوزي" aria-pressed="false"><img class="mht-color__img--portrait" src="https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-dark-walnut.webp" alt="طاولة تلفزيون معلقة بتشطيب جوزي غامق" width="591" height="832" loading="lazy"><span>جوزي</span></button>
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

        <section class="mht-faq" id="faq">
            <div class="mht-shell">
                <div class="mht-heading">
                    <span class="mht-eyebrow mht-eyebrow--blue">قبل ما تطلب</span>
                    <h2>الأسئلة المتكررة</h2>
                    <p>إجابات واضحة عن المقاسات والأسعار والتركيب وطريقة تأكيد الطلب.</p>
                </div>
                <div class="mht-faq__list">
                    <details><summary>ما مقاسات طاولات التلفزيون المتاحة؟</summary><p>متاح مقاس 1.5 متر ومقاس 2 متر. الارتفاع 25 سم والعمق 32 سم، وكل طاولة تحتوي على أربعة أبواب متساوية.</p></details>
                    <details><summary>كم سعر الطاولة؟</summary><p>مقاس 1.5 متر بسعر 40 د.ك بدون تركيب، ومقاس 2 متر بسعر 50 د.ك بدون تركيب.</p></details>
                    <details><summary>هل التركيب متاح داخل الكويت؟</summary><p>نعم، التركيب اختياري داخل الكويت بقيمة 10 د.ك، ويُراجع العنوان ومتطلبات الحائط قبل تأكيد الطلب.</p></details>
                    <details><summary>ما الألوان المتاحة؟</summary><p>سبعة ألوان: أبيض، أسود، رمادي فاتح، رمادي غامق، بيج خشبي، عسلي خشبي، وجوزي. نؤكد الدرجة النهائية معك قبل التنفيذ لأن عرض اللون قد يختلف قليلًا من شاشة لأخرى.</p></details>
                    <details><summary>كيف أؤكد طلبي؟</summary><p>اختار المقاس واللون وخدمة التركيب من الصفحة، ثم أرسل الطلب على واتساب. لا يصبح الطلب مؤكدًا إلا بعد مراجعة التفاصيل والتكلفة وموعد التنفيذ معك.</p></details>
                    <details><summary>كم يستغرق التجهيز والتوصيل؟</summary><p>يتم تحديد المدة قبل تأكيد الطلب حسب المقاس واللون وتوفر الخامة وموقع التسليم داخل الكويت.</p></details>
                    <details><summary>ماذا أفعل إذا وصل المنتج تالفًا أو مختلفًا عن الطلب؟</summary><p>تواصل معنا فور الاستلام مع صورة واضحة للمشكلة، وسنراجع الحالة ونوضح الإجراء المناسب وفق سياسة الاستبدال والاسترجاع.</p></details>
                </div>
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
    mh_control_prepare_virtual_page();
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
function mh_control_tv_console_canonical(string $url): string {
    return mh_control_is_tv_console_page() ? home_url('/tv-tables/') : $url;
}

function mh_control_tv_console_price($price, $product) {
    return is_object($product) && method_exists($product, 'get_id') && (int) $product->get_id() === 6455 ? '40' : $price;
}
function mh_control_tv_console_sale_price($price, $product) {
    return is_object($product) && method_exists($product, 'get_id') && (int) $product->get_id() === 6455 ? '' : $price;
}
function mh_control_tv_console_is_on_sale(bool $on_sale, $product): bool {
    return is_object($product) && method_exists($product, 'get_id') && (int) $product->get_id() === 6455 ? false : $on_sale;
}
add_filter('woocommerce_product_get_price', 'mh_control_tv_console_price', 999, 2);
add_filter('woocommerce_product_get_regular_price', 'mh_control_tv_console_price', 999, 2);
add_filter('woocommerce_product_get_sale_price', 'mh_control_tv_console_sale_price', 999, 2);
add_filter('woocommerce_product_is_on_sale', 'mh_control_tv_console_is_on_sale', 999, 2);
add_filter('aioseo_title', 'mh_control_tv_console_title', 1200);
add_filter('aioseo_description', 'mh_control_tv_console_description', 1200);
add_filter('aioseo_canonical_url', 'mh_control_tv_console_canonical', 1200);
add_filter('wpseo_title', 'mh_control_tv_console_title', 1200);
add_filter('wpseo_metadesc', 'mh_control_tv_console_description', 1200);
add_filter('rank_math/frontend/title', 'mh_control_tv_console_title', 1200);
add_filter('rank_math/frontend/description', 'mh_control_tv_console_description', 1200);

function mh_control_tv_console_head(): void {
    if (!mh_control_is_tv_console_page()) {
        return;
    }
    ?>
    <style id="mh-tv-styles">
    :root{--mht-blue:#1266d6;--mht-navy:#071a33;--mht-ink:#15263a;--mht-soft:#f2f6fa;--mht-gold:#d6aa62;--mht-green:#20b95a}
    html:has(.mh-tv),body:has(.mh-tv){overflow-x:clip}.mh-tv{font-family:Tahoma,Arial,sans-serif;color:var(--mht-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-tv *{box-sizing:border-box}.mht-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mht-hero{padding:72px 0;background:linear-gradient(135deg,#f8fafc,#e8eff6)}.mht-hero__grid{display:grid;grid-template-columns:.75fr 1.25fr;gap:60px;align-items:center}.mht-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#61738a;font-size:14px;font-weight:800;margin-bottom:15px}.mht-eyebrow:before{content:"";width:32px;height:2px;background:var(--mht-gold)}.mht-eyebrow--blue{color:var(--mht-blue)}.mht-hero h1{font-size:clamp(44px,6vw,72px);line-height:1.12;color:var(--mht-navy);margin:0 0 20px;font-weight:900}.mht-hero__copy>p{font-size:18px;line-height:1.9;color:#607187;margin:0 0 26px}.mht-badges{display:flex;gap:8px;flex-wrap:wrap}.mht-badges span{padding:8px 13px;border:1px solid #cad5df;border-radius:999px;color:#4d6075;font-size:12px;font-weight:700}.mht-hero__visual{margin:0;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 24px 60px rgba(7,26,51,.14)}.mht-hero__visual img{display:block;width:100%;height:520px;object-fit:cover}
    .mht-builder{padding:88px 0}.mht-builder__grid{display:grid;grid-template-columns:1.3fr .7fr;gap:36px;align-items:start}.mht-builder__choices h2,.mht-heading h2{font-size:clamp(34px,5vw,52px);color:var(--mht-navy);margin:0 0 32px}.mht-builder fieldset{border:0;padding:0;margin:0 0 29px}.mht-builder legend{font-size:17px;color:var(--mht-navy);font-weight:900;margin-bottom:13px}.mht-choice-row,.mht-install{display:grid;grid-template-columns:1fr 1fr;gap:12px}.mht-choice,.mht-install-choice{font:inherit;text-align:right;border:1px solid #d9e2eb;border-radius:12px;background:#fff;padding:18px 20px;cursor:pointer;color:var(--mht-ink)}.mht-choice b,.mht-choice small,.mht-install-choice b,.mht-install-choice small{display:block}.mht-choice small,.mht-install-choice small{color:#718096;margin-top:6px;font-size:12px}.mht-choice.is-active,.mht-install-choice.is-active{border:2px solid var(--mht-blue);background:#f5f9ff}.mht-colors{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mht-color{font:inherit;text-align:right;border:1px solid #dde5ed;border-radius:12px;background:#fff;padding:0;overflow:hidden;cursor:pointer;color:#44576c;transition:.2s ease}.mht-color img{display:block;width:100%;height:auto;aspect-ratio:4/3;object-fit:cover;background:#f4f6f8}.mht-color img.mht-color__img--portrait{object-position:center 72%}.mht-color span{display:block;padding:10px 12px;font-size:12px;font-weight:900}.mht-color:hover{transform:translateY(-2px);border-color:#9fc8ed}.mht-color.is-active{outline:3px solid var(--mht-blue);outline-offset:1px}.mht-color.is-active span{color:var(--mht-blue)}
    .mht-summary{position:sticky;top:25px;border-radius:18px;padding:32px;background:var(--mht-navy);color:#fff;box-shadow:0 20px 45px rgba(7,26,51,.18)}.mht-summary>span{color:#aebed0;font-size:14px}.mht-summary>strong{display:block;font-size:60px;line-height:1;margin:14px 0 25px}.mht-summary>strong em{font-style:normal}.mht-summary>strong small{font-size:18px}.mht-summary ul{list-style:none;padding:0;margin:0 0 24px;border-block:1px solid rgba(255,255,255,.13)}.mht-summary li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;color:#b8c6d6;font-size:13px}.mht-summary li+li{border-top:1px solid rgba(255,255,255,.09)}.mht-summary li b{color:#fff}.mht-btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 23px;border-radius:8px;font-weight:900;text-decoration:none!important;transition:.2s ease}.mht-btn:hover{transform:translateY(-2px)}.mht-btn--green{background:var(--mht-green);color:#fff!important;width:100%}.mht-btn--dark{background:var(--mht-navy);color:#fff!important}.mht-summary>p{text-align:center;color:#9eafc2;font-size:11px;margin:13px 0 0}
    .mht-specs{padding:88px 0;background:var(--mht-soft)}.mht-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mht-heading p{color:#68798d;line-height:1.8;margin:0}.mht-specs__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mht-specs__grid>div{background:#fff;border:1px solid #e1e8ef;border-radius:15px;padding:27px}.mht-specs__grid b{font-size:27px;color:var(--mht-blue)}.mht-specs__grid h3{font-size:18px;color:var(--mht-navy);margin:10px 0}.mht-specs__grid p{font-size:13px;line-height:1.75;color:#6b7b8e;margin:0}
    .mht-dimensions{padding:90px 0;background:#fff}.mht-dimensions__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.mht-dimensions figure{margin:0;border:1px solid #e1e8ef;border-radius:17px;overflow:hidden;background:#fff;box-shadow:0 15px 38px rgba(7,26,51,.08)}.mht-dimensions img{display:block;width:100%;height:auto;aspect-ratio:4/3;object-fit:cover}.mht-dimensions figcaption{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;color:var(--mht-navy)}.mht-dimensions figcaption b{font-size:16px}.mht-dimensions figcaption span{font-size:13px;color:#6a7b8e;font-weight:800}
    .mht-colors-gallery{padding:90px 0}.mht-gallery{display:grid;grid-template-columns:repeat(4,1fr);gap:17px}.mht-gallery figure{margin:0;border:1px solid #e1e8ef;border-radius:15px;overflow:hidden;background:#fff}.mht-gallery figure:first-child{grid-column:span 2}.mht-gallery img{width:100%;height:310px;object-fit:contain;background:#f7f8f9;display:block;transition:transform .4s ease}.mht-gallery figure:hover img{transform:scale(1.025)}.mht-gallery figcaption{padding:16px 18px;color:var(--mht-navy);font-weight:900}
    .mht-prices{padding:88px 0;background:var(--mht-navy);color:#fff}.mht-heading--light h2{color:#fff}.mht-prices__grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:760px;margin:0 auto}.mht-prices__grid>div{position:relative;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);border-radius:16px;padding:31px}.mht-prices__grid>div.is-featured{border:2px solid #4f9cff}.mht-prices__grid i{position:absolute;top:-13px;right:20px;background:var(--mht-blue);padding:5px 11px;border-radius:999px;font-size:11px;font-style:normal}.mht-prices__grid span{display:block;color:#b9c6d5;font-weight:800}.mht-prices__grid strong{display:block;font-size:52px;margin:13px 0 8px}.mht-prices__grid strong small{font-size:17px}.mht-prices__grid p{margin:0 0 12px;color:#aebdce}.mht-prices__grid b{color:#fff}.mht-install-note{text-align:center;color:#b9c6d5;margin:24px 0 0}
    .mht-faq{padding:88px 0;background:#fff}.mht-faq__list{max-width:900px;margin:0 auto;display:grid;gap:12px}.mht-faq details{border:1px solid #dde6ef;border-radius:13px;background:#fff;padding:0 22px}.mht-faq summary{cursor:pointer;list-style:none;padding:21px 0;color:var(--mht-navy);font-weight:900;display:flex;justify-content:space-between;gap:18px}.mht-faq summary::-webkit-details-marker{display:none}.mht-faq summary:after{content:"+";color:var(--mht-blue);font-size:22px;line-height:1}.mht-faq details[open] summary:after{content:"−"}.mht-faq details p{margin:0;padding:0 0 21px;color:#68798d;line-height:1.9;font-size:14px}
    .mht-cta{padding:70px 0;background:#eaf1f7}.mht-cta__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mht-cta h2{font-size:clamp(29px,4vw,43px);color:var(--mht-navy);margin:0 0 10px}.mht-cta p{margin:0;color:#68798e}.mht-mobile-order{display:none}
    @media(max-width:900px){.mht-hero__grid,.mht-builder__grid{grid-template-columns:1fr}.mht-dimensions__grid{grid-template-columns:1fr 1fr}.mht-hero__visual img{height:440px}.mht-summary{position:static}.mht-specs__grid{grid-template-columns:1fr 1fr}.mht-gallery{grid-template-columns:1fr 1fr}.mht-gallery figure:first-child{grid-column:span 2}.mht-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mht-shell{width:min(100% - 28px,1180px)}.mht-hero{padding:52px 0}.mht-hero h1{font-size:43px}.mht-hero__visual img{height:330px}.mht-builder,.mht-specs,.mht-colors-gallery,.mht-prices,.mht-faq{padding:64px 0}.mht-choice-row,.mht-install,.mht-specs__grid,.mht-gallery,.mht-dimensions__grid{grid-template-columns:1fr}.mht-colors{grid-template-columns:repeat(2,minmax(0,1fr))}.mht-gallery figure:first-child{grid-column:auto}.mht-gallery img{height:270px}.mht-prices__grid{grid-template-columns:1fr}.mht-cta{padding:48px 0 90px}.mht-cta__box{padding:30px 24px}.mht-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mht-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
    $faq = [
        ['@type' => 'Question', 'name' => 'ما مقاسات طاولات التلفزيون المتاحة؟', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'متاح مقاس 1.5 متر ومقاس 2 متر، بارتفاع 25 سم وعمق 32 سم وأربعة أبواب متساوية.']],
        ['@type' => 'Question', 'name' => 'كم سعر طاولة التلفزيون المعلقة؟', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'مقاس 1.5 متر بسعر 40 د.ك بدون تركيب، ومقاس 2 متر بسعر 50 د.ك بدون تركيب. التركيب الاختياري داخل الكويت 10 د.ك.']],
        ['@type' => 'Question', 'name' => 'ما الألوان المتاحة؟', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'متاحة بسبعة ألوان: أبيض، أسود، رمادي فاتح، رمادي غامق، بيج خشبي، عسلي خشبي، وجوزي.']],
        ['@type' => 'Question', 'name' => 'كيف أؤكد طلبي؟', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'اختار المقاس واللون وخدمة التركيب ثم أرسل الطلب على واتساب، وبعد مراجعة التفاصيل والتكلفة والموعد يتم تأكيد الطلب.']],
    ];
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Product',
                '@id' => home_url('/tv-tables/#product'),
                'name' => 'طاولات TV معلقة من ماركوز هوم',
                'description' => 'طاولات تلفزيون معلقة بمقاس 1.5 أو 2 متر وسبعة ألوان، تبدأ من 40 د.ك داخل الكويت.',
                'image' => 'https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-white.webp',
                'brand' => ['@type' => 'Brand', 'name' => "Marco's Home"],
                'offers' => [
                    '@type' => 'AggregateOffer',
                    'url' => home_url('/tv-tables/'),
                    'priceCurrency' => 'KWD',
                    'lowPrice' => '40',
                    'highPrice' => '60',
                    'offerCount' => 4,
                    'availability' => 'https://schema.org/InStock',
                ],
            ],
            ['@type' => 'FAQPage', '@id' => home_url('/tv-tables/#faq'), 'mainEntity' => $faq],
        ],
    ];
    ?>
    <script type="application/ld+json" id="mh-tv-product-faq-schema"><?php echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
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
        var state={size:'1.5 متر',color:'أبيض',base:40,install:50,installed:0,installation:'بدون تركيب'};
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
        link.addEventListener('click',function(){
            window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'whatsapp_order_click',product:'tv-tables',value:Number(price.textContent),currency:'KWD'});
        });
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


/**
 * Fire Blaze perfumed diffuser product — roadmap step 2, product 3 of 6.
 * The WooCommerce product and fire.marcohom.com app remain untouched.
 */
function mh_control_is_fire_diffuser_page(): bool {
    return (
        function_exists('is_product')
        && is_singular('product')
        && get_queried_object_id() === 6445
    ) || mh_control_request_path() === '/fire-blaze/';
}

function mh_control_fire_diffuser_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=';
    $initial_message = rawurlencode('مرحباً ماركوز هوم، أريد طلب جهاز الفير المعطر مقاس 40 سم، السعر 85 د.ك.');
    ob_start();
    ?>
    <main class="mh-fire" dir="rtl">
        <section class="mhf-hero">
            <div class="mhf-shell mhf-hero__grid">
                <div class="mhf-hero__copy">
                    <span class="mhf-eyebrow">Fire Blaze — لهب مائي ثلاثي الأبعاد</span>
                    <h1>دفء بصري.<br>وعطر يملأ المكان.</h1>
                    <p>جهاز ديكور يعمل بالماء والكهرباء ليصنع لهبًا مائيًا واقعيًا، ويمكن إضافة الزيت العطري المفضل لديك.</p>
                    <div class="mhf-badges"><span>تأثير مائي بلا حرارة</span><span>ماء + كهرباء</span><span>5 مقاسات</span></div>
                    <div class="mhf-actions">
                        <a class="mhf-btn mhf-btn--green" href="#mhf-order">اختار المقاس والسعر</a>
                        <a class="mhf-btn mhf-btn--outline" href="https://fire.marcohom.com/" target="_blank" rel="noopener">افتح تطبيق الفير</a>
                    </div>
                </div>
                <figure class="mhf-hero__visual">
                    <img src="https://marcohom.com/wp-content/uploads/2025/11/Art-Fireplace-AFW230-3D-Water-Vapor-Fireplace-product.webp" alt="جهاز الفير المعطر من ماركوز هوم">
                </figure>
            </div>
        </section>

        <section class="mhf-builder" id="mhf-order">
            <div class="mhf-shell mhf-builder__grid">
                <div>
                    <span class="mhf-eyebrow mhf-eyebrow--blue">كوّن طلبك</span>
                    <h2>اختار المقاس المناسب</h2>
                    <div class="mhf-size-list" role="group" aria-label="مقاس جهاز الفير المعطر">
                        <button type="button" class="mhf-size is-active" data-mhf-size="40 سم" data-mhf-price="85" aria-pressed="true"><b>40 سم</b><span>85 د.ك</span></button>
                        <button type="button" class="mhf-size" data-mhf-size="70 سم" data-mhf-price="135" aria-pressed="false"><b>70 سم</b><span>135 د.ك</span></button>
                        <button type="button" class="mhf-size" data-mhf-size="1 متر" data-mhf-price="180" aria-pressed="false"><b>1 متر</b><span>180 د.ك</span></button>
                        <button type="button" class="mhf-size" data-mhf-size="1.20 متر" data-mhf-price="220" aria-pressed="false"><b>1.20 متر</b><span>220 د.ك</span></button>
                        <button type="button" class="mhf-size" data-mhf-size="1.50 متر" data-mhf-price="270" aria-pressed="false"><b>1.50 متر</b><span>270 د.ك</span></button>
                    </div>
                    <div class="mhf-note">
                        <b>قبل الطلب</b>
                        <p>حدد مكان الجهاز والمقاس المتاح، وسنراجع معك متطلبات التجهيز قبل تأكيد الطلب.</p>
                    </div>
                </div>
                <aside class="mhf-summary">
                    <span>اختيارك الحالي</span>
                    <strong><em id="mhf-price">85</em> <small>د.ك</small></strong>
                    <ul><li><span>المنتج</span><b>جهاز الفير المعطر</b></li><li><span>المقاس</span><b id="mhf-size-summary">40 سم</b></li><li><span>التشغيل</span><b>ماء + كهرباء</b></li></ul>
                    <a id="mhf-whatsapp" class="mhf-btn mhf-btn--green mhf-btn--full" href="<?php echo esc_url($whatsapp . $initial_message); ?>" target="_blank" rel="noopener">اطلب على واتساب</a>
                    <a class="mhf-app-link" href="https://fire.marcohom.com/" target="_blank" rel="noopener">أو أكمل الطلب من التطبيق</a>
                </aside>
            </div>
        </section>

        <section class="mhf-features">
            <div class="mhf-shell">
                <div class="mhf-heading">
                    <span class="mhf-eyebrow mhf-eyebrow--blue">تجربة مختلفة</span>
                    <h2>لهب بلا حرارة أو دخان</h2>
                    <p>تأثير بصري مائي يضيف حركة وهدوءًا للمكان، مع إمكانية تعطير الجو حسب رغبتك.</p>
                </div>
                <div class="mhf-features__grid">
                    <div><b>3D</b><h3>لهب مائي واقعي</h3><p>بخار ماء وإضاءة يصنعان تأثير لهب ثلاثي الأبعاد.</p></div>
                    <div><b>H₂O</b><h3>تشغيل بالماء</h3><p>يعمل بخزان ماء مع توصيل كهربائي مناسب.</p></div>
                    <div><b>عطر</b><h3>زيوت اختيارية</h3><p>أضف الزيت العطري المفضل لتحويله إلى معطر ديكوري.</p></div>
                    <div><b>5×</b><h3>خمسة مقاسات</h3><p>خيارات تبدأ من 40 سم وتصل إلى 1.50 متر.</p></div>
                </div>
            </div>
        </section>

        <section class="mhf-gallery-section">
            <div class="mhf-shell">
                <div class="mhf-heading">
                    <span class="mhf-eyebrow mhf-eyebrow--blue">داخل الديكور</span>
                    <h2>يندمج مع تصميم المساحة</h2>
                    <p>يمكن دمجه في وحدة تلفزيون أو كونسول أو تصميم جداري مخصص.</p>
                </div>
                <div class="mhf-gallery">
                    <figure class="mhf-gallery__wide"><img src="https://marcohom.com/wp-content/uploads/2025/11/AWA40_withflame_web.webp" alt="جهاز فير مائي داخل وحدة ديكور" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/electric-water-vapor-steam-fireplace46030373802.webp" alt="لهب مائي ثلاثي الأبعاد" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/electric-water-vapor-steam-fireplace46170687047.webp" alt="جهاز فير معطر للمساحات الداخلية" loading="lazy"></figure>
                </div>
            </div>
        </section>

        <section class="mhf-prices">
            <div class="mhf-shell">
                <div class="mhf-heading mhf-heading--light">
                    <span class="mhf-eyebrow">أسعار واضحة</span>
                    <h2>خمسة مقاسات لكل مساحة</h2>
                </div>
                <div class="mhf-prices__grid">
                    <div><span>40 سم</span><strong>85 <small>د.ك</small></strong></div>
                    <div><span>70 سم</span><strong>135 <small>د.ك</small></strong></div>
                    <div><span>1 متر</span><strong>180 <small>د.ك</small></strong></div>
                    <div><span>1.20 متر</span><strong>220 <small>د.ك</small></strong></div>
                    <div class="is-featured"><i>الأكبر</i><span>1.50 متر</span><strong>270 <small>د.ك</small></strong></div>
                </div>
            </div>
        </section>

        <section class="mhf-support">
            <div class="mhf-shell mhf-support__box">
                <div><span class="mhf-eyebrow mhf-eyebrow--blue">خدمة ما بعد البيع</span><h2>مركز صيانة وقطع غيار</h2><p>دعم من ماركوز هوم لمراجعة التشغيل والصيانة وتوفير قطع الغيار المتاحة.</p></div>
                <a class="mhf-btn mhf-btn--dark" href="https://wa.me/96550204320?text=<?php echo rawurlencode('مرحباً ماركوز هوم، أريد الاستفسار عن صيانة أو قطع غيار جهاز الفير المعطر.'); ?>" target="_blank" rel="noopener">تواصل مع الصيانة</a>
            </div>
        </section>

        <section class="mhf-faq" aria-labelledby="mhf-faq-title">
            <div class="mhf-shell">
                <div class="mhf-heading">
                    <span class="mhf-eyebrow mhf-eyebrow--blue">قبل الطلب</span>
                    <h2 id="mhf-faq-title">أسئلة مهمة عن جهاز Fire Blaze</h2>
                </div>
                <div class="mhf-faq__grid">
                    <details open><summary>هل الجهاز مصدر تدفئة؟</summary><p>لا. الجهاز يقدم تأثير لهب بصري ببخار الماء والإضاءة، وليس مدفأة أو مصدرًا للحرارة.</p></details>
                    <details><summary>كيف يعمل الجهاز؟</summary><p>يعمل بالماء مع توصيل كهربائي مناسب. نوضح تعليمات التشغيل والتجهيز قبل تأكيد الطلب.</p></details>
                    <details><summary>هل يمكن استخدام زيت عطري؟</summary><p>تتوفر إمكانية التعطير الاختيارية وفق تعليمات التشغيل الخاصة بالجهاز.</p></details>
                    <details><summary>كيف يتم التوصيل والتركيب؟</summary><p>يتم تأكيد المقاس ومكان الاستخدام والمنطقة وموعد التوصيل أو التركيب قبل إتمام الطلب.</p></details>
                </div>
                <nav class="mhf-policies" aria-label="سياسات المتجر">
                    <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">الخصوصية</a>
                    <a href="<?php echo esc_url(home_url('/terms-and-conditions/')); ?>">الشروط والأحكام</a>
                    <a href="<?php echo esc_url(home_url('/shipping-and-installation/')); ?>">التوصيل والتركيب</a>
                    <a href="<?php echo esc_url(home_url('/returns-and-refunds/')); ?>">الاستبدال والاسترجاع</a>
                </nav>
            </div>
        </section>
        <a class="mhf-mobile-order" href="#mhf-order">اختار جهاز الفير — يبدأ من 85 د.ك</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_fire_diffuser_page(): void {
    if (!mh_control_is_fire_diffuser_page()) {
        return;
    }
    mh_control_prepare_virtual_page();
    get_header();
    echo mh_control_fire_diffuser_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_fire_diffuser_page', 31);

function mh_control_fire_diffuser_title(string $title): string {
    return mh_control_is_fire_diffuser_page() ? 'جهاز Fire Blaze المائي | يبدأ من 85 د.ك — ماركوز هوم' : $title;
}
add_filter('pre_get_document_title', 'mh_control_fire_diffuser_title', 121);

function mh_control_fire_diffuser_description(string $description): string {
    return mh_control_is_fire_diffuser_page()
        ? 'جهاز فير مائي ثلاثي الأبعاد يعمل بالماء والكهرباء مع زيوت عطرية اختيارية. خمسة مقاسات من 40 سم إلى 1.50 متر وأسعار من 85 إلى 270 د.ك.'
        : $description;
}
add_filter('aioseo_title', 'mh_control_fire_diffuser_title', 1210);
add_filter('aioseo_description', 'mh_control_fire_diffuser_description', 1210);
add_filter('wpseo_title', 'mh_control_fire_diffuser_title', 1210);
add_filter('wpseo_metadesc', 'mh_control_fire_diffuser_description', 1210);
add_filter('rank_math/frontend/title', 'mh_control_fire_diffuser_title', 1210);
add_filter('rank_math/frontend/description', 'mh_control_fire_diffuser_description', 1210);

function mh_control_fire_diffuser_head(): void {
    if (!mh_control_is_fire_diffuser_page()) {
        return;
    }
    ?>
    <meta name="description" content="جهاز فير مائي ثلاثي الأبعاد بخمسة مقاسات من 40 سم إلى 1.50 متر، يعمل بالماء والكهرباء، ويبدأ من 85 د.ك.">
    <style id="mh-fire-styles">
    :root{--mhf-blue:#1266d6;--mhf-navy:#071a33;--mhf-ink:#15263a;--mhf-soft:#f2f6fa;--mhf-gold:#d6aa62;--mhf-green:#20b95a}
    html:has(.mh-fire),body:has(.mh-fire){overflow-x:clip}.mh-fire{font-family:Tahoma,Arial,sans-serif;color:var(--mhf-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-fire *{box-sizing:border-box}.mhf-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhf-hero{padding:72px 0;background:radial-gradient(circle at 20% 30%,#273855 0,#101c2d 32%,#07121f 72%);color:#fff}.mhf-hero__grid{display:grid;grid-template-columns:.82fr 1.18fr;gap:58px;align-items:center}.mhf-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#c7d4e5;font-size:14px;font-weight:800;margin-bottom:15px}.mhf-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhf-gold)}.mhf-eyebrow--blue{color:var(--mhf-blue)}.mhf-hero h1{font-size:clamp(44px,6vw,72px);line-height:1.12;color:#fff;margin:0 0 20px;font-weight:900}.mhf-hero p{font-size:18px;line-height:1.9;color:#c5d1df;margin:0 0 24px}.mhf-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}.mhf-badges span{padding:8px 13px;border:1px solid rgba(255,255,255,.25);border-radius:999px;font-size:12px;font-weight:700}.mhf-actions{display:flex;gap:12px;flex-wrap:wrap}.mhf-hero__visual{margin:0;border-radius:20px;overflow:hidden;background:#101820;box-shadow:0 28px 70px rgba(0,0,0,.34)}.mhf-hero__visual img{display:block;width:100%;height:520px;object-fit:cover}
    .mhf-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 23px;border-radius:8px;text-decoration:none!important;font-weight:900;transition:.2s}.mhf-btn:hover{transform:translateY(-2px)}.mhf-btn--green{background:var(--mhf-green);color:#fff!important}.mhf-btn--outline{border:1px solid rgba(255,255,255,.45);color:#fff!important}.mhf-btn--dark{background:var(--mhf-navy);color:#fff!important}.mhf-btn--full{width:100%}
    .mhf-builder{padding:88px 0}.mhf-builder__grid{display:grid;grid-template-columns:1.25fr .75fr;gap:38px;align-items:start}.mhf-builder h2,.mhf-heading h2{font-size:clamp(34px,5vw,52px);color:var(--mhf-navy);margin:0 0 30px}.mhf-size-list{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.mhf-size{font:inherit;text-align:right;border:1px solid #d9e2eb;border-radius:13px;background:#fff;padding:20px;cursor:pointer;color:var(--mhf-ink)}.mhf-size b,.mhf-size span{display:block}.mhf-size b{font-size:17px}.mhf-size span{color:#697b90;font-size:13px;margin-top:7px}.mhf-size.is-active{border:2px solid var(--mhf-blue);background:#f3f8ff}.mhf-note{margin-top:22px;padding:20px 22px;border-radius:13px;background:#f2f6fa}.mhf-note b{color:var(--mhf-navy)}.mhf-note p{margin:7px 0 0;color:#6a7a8d;line-height:1.7;font-size:13px}
    .mhf-summary{position:sticky;top:25px;border-radius:18px;padding:32px;background:var(--mhf-navy);color:#fff;box-shadow:0 20px 45px rgba(7,26,51,.18)}.mhf-summary>span{color:#aebed0;font-size:14px}.mhf-summary>strong{display:block;font-size:60px;line-height:1;margin:14px 0 25px}.mhf-summary>strong em{font-style:normal}.mhf-summary>strong small{font-size:18px}.mhf-summary ul{list-style:none;padding:0;margin:0 0 24px;border-block:1px solid rgba(255,255,255,.13)}.mhf-summary li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;color:#b8c6d6;font-size:13px}.mhf-summary li+li{border-top:1px solid rgba(255,255,255,.09)}.mhf-summary li b{color:#fff}.mhf-app-link{display:block;text-align:center;color:#c3d4ea!important;font-size:12px;margin-top:14px}
    .mhf-features{padding:88px 0;background:var(--mhf-soft)}.mhf-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mhf-heading p{color:#68798d;line-height:1.8;margin:0}.mhf-features__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mhf-features__grid>div{background:#fff;border:1px solid #e1e8ef;border-radius:15px;padding:27px}.mhf-features__grid b{font-size:27px;color:var(--mhf-blue)}.mhf-features__grid h3{font-size:18px;color:var(--mhf-navy);margin:10px 0}.mhf-features__grid p{font-size:13px;line-height:1.75;color:#6b7b8e;margin:0}
    .mhf-gallery-section{padding:90px 0}.mhf-gallery{display:grid;grid-template-columns:repeat(2,1fr);gap:17px}.mhf-gallery figure{margin:0;border-radius:16px;overflow:hidden;background:#0a1018}.mhf-gallery__wide{grid-column:span 2}.mhf-gallery img{width:100%;height:350px;object-fit:cover;display:block;transition:transform .4s}.mhf-gallery__wide img{height:480px}.mhf-gallery figure:hover img{transform:scale(1.02)}
    .mhf-prices{padding:88px 0;background:var(--mhf-navy);color:#fff}.mhf-heading--light h2{color:#fff}.mhf-prices__grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.mhf-prices__grid>div{position:relative;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);border-radius:15px;padding:27px 20px}.mhf-prices__grid>div.is-featured{border:2px solid #4f9cff}.mhf-prices__grid i{position:absolute;top:-12px;right:17px;background:var(--mhf-blue);padding:5px 10px;border-radius:999px;font-size:10px;font-style:normal}.mhf-prices__grid span{display:block;color:#b9c6d5;font-weight:800}.mhf-prices__grid strong{display:block;font-size:34px;margin-top:12px}.mhf-prices__grid strong small{font-size:13px}
    .mhf-support{padding:70px 0;background:#eaf1f7}.mhf-support__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhf-support h2{font-size:clamp(29px,4vw,43px);color:var(--mhf-navy);margin:0 0 10px}.mhf-support p{margin:0;color:#68798e;line-height:1.8}.mhf-faq{padding:82px 0;background:#fff}.mhf-faq__grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.mhf-faq details{border:1px solid #dfe7ef;border-radius:14px;padding:20px 22px;background:#f8fafc}.mhf-faq summary{cursor:pointer;font-weight:900;color:var(--mhf-navy)}.mhf-faq details p{color:#617286;line-height:1.8;margin:14px 0 0}.mhf-policies{display:flex;justify-content:center;gap:12px 22px;flex-wrap:wrap;margin-top:30px}.mhf-policies a{color:var(--mhf-blue)!important;font-size:13px;font-weight:800}.mhf-mobile-order{display:none}
    @media(max-width:950px){.mhf-hero__grid,.mhf-builder__grid{grid-template-columns:1fr}.mhf-summary{position:static}.mhf-features__grid{grid-template-columns:1fr 1fr}.mhf-prices__grid{grid-template-columns:repeat(2,1fr)}.mhf-support__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mhf-shell{width:min(100% - 28px,1180px)}.mhf-hero{padding:52px 0}.mhf-hero h1{font-size:42px}.mhf-hero__visual img{height:330px}.mhf-actions{display:grid}.mhf-btn{width:100%}.mhf-builder,.mhf-features,.mhf-gallery-section,.mhf-prices,.mhf-faq{padding:64px 0}.mhf-size-list,.mhf-features__grid,.mhf-gallery,.mhf-prices__grid,.mhf-faq__grid{grid-template-columns:1fr}.mhf-gallery__wide{grid-column:auto}.mhf-gallery img,.mhf-gallery__wide img{height:290px}.mhf-support{padding:48px 0 90px}.mhf-support__box{padding:30px 24px}.mhf-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mhf-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_fire_diffuser_head', 102);

function mh_control_fire_diffuser_script(): void {
    if (!mh_control_is_fire_diffuser_page()) {
        return;
    }
    ?>
    <script id="mh-fire-builder">
    document.addEventListener('DOMContentLoaded',function(){
        var state={size:'40 سم',price:85};
        var price=document.getElementById('mhf-price');
        var size=document.getElementById('mhf-size-summary');
        var link=document.getElementById('mhf-whatsapp');
        function update(){
            price.textContent=String(state.price);
            size.textContent=state.size;
            var msg='مرحباً ماركوز هوم، أريد طلب جهاز الفير المعطر مقاس '+state.size+'، السعر '+state.price+' د.ك.';
            var params=new URLSearchParams(window.location.search);
            var source=params.get('utm_source');
            var campaign=params.get('utm_campaign');
            if(source){msg+=' مصدر الزيارة: '+source+(campaign?' / '+campaign:'')+'.';}
            link.href='https://wa.me/96550204320?text='+encodeURIComponent(msg);
        }
        document.querySelectorAll('[data-mhf-size]').forEach(function(button){
            button.addEventListener('click',function(){
                document.querySelectorAll('[data-mhf-size]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});
                button.classList.add('is-active');
                button.setAttribute('aria-pressed','true');
                state.size=button.dataset.mhfSize;
                state.price=Number(button.dataset.mhfPrice);
                update();
            });
        });
        update();
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_fire_diffuser_script', 101);


/**
 * WPC divider columns — roadmap step 2, product 4 of 6.
 */
function mh_control_is_wpc_divider_page(): bool {
    return function_exists('is_product') && is_singular('product') && get_queried_object_id() === 6643;
}

function mh_control_wpc_divider_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=';
    $initial_message = rawurlencode('مرحباً ماركوز هوم، أريد طلب 8 أعمدة WPC درجة WPC3-1 بدون تركيب، الإجمالي 40 د.ك.');
    ob_start();
    ?>
    <main class="mh-wpc" dir="rtl">
        <section class="mhw-hero">
            <div class="mhw-shell mhw-hero__grid">
                <div class="mhw-hero__copy">
                    <span class="mhw-eyebrow">فواصل أعمدة WPC بديل الخشب</span>
                    <h1>قسّم المساحة.<br>وحافظ على اتساعها.</h1>
                    <p>أعمدة ديكورية أنيقة تساعدك في فصل الصالة أو المدخل بدون بناء جدار مغلق، وبدرجات تناسب الأثاث والديكور.</p>
                    <div class="mhw-badges"><span>6 درجات</span><span>سعر بالعمود</span><span>تركيب اختياري</span></div>
                    <a class="mhw-btn mhw-btn--green" href="#mhw-order">احسب العدد والسعر</a>
                </div>
                <figure class="mhw-hero__visual"><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_125uc4125uc4125u-Copy.jpg" alt="فواصل أعمدة WPC بديل الخشب من ماركوز هوم"></figure>
            </div>
        </section>

        <section class="mhw-builder" id="mhw-order">
            <div class="mhw-shell mhw-builder__grid">
                <div>
                    <span class="mhw-eyebrow mhw-eyebrow--blue">كوّن طلبك</span>
                    <h2>حدد العدد والدرجة</h2>
                    <fieldset><legend>1. عدد الأعمدة</legend>
                        <div class="mhw-quantity">
                            <button type="button" id="mhw-minus" aria-label="تقليل العدد">−</button>
                            <input id="mhw-quantity" type="number" min="1" max="100" value="8" inputmode="numeric" aria-label="عدد الأعمدة">
                            <button type="button" id="mhw-plus" aria-label="زيادة العدد">+</button>
                        </div>
                    </fieldset>
                    <fieldset><legend>2. درجة WPC</legend>
                        <div class="mhw-colors" role="group" aria-label="درجة لون WPC">
                            <button type="button" class="mhw-color is-active" data-mhw-color="WPC3-1" aria-pressed="true"><i style="--swatch:#d8c6a6"></i><span>WPC3-1</span></button>
                            <button type="button" class="mhw-color" data-mhw-color="WPC3-10" aria-pressed="false"><i style="--swatch:#463326"></i><span>WPC3-10</span></button>
                            <button type="button" class="mhw-color" data-mhw-color="WPC3-3" aria-pressed="false"><i style="--swatch:#9f7a50"></i><span>WPC3-3</span></button>
                            <button type="button" class="mhw-color" data-mhw-color="WPC3-7" aria-pressed="false"><i style="--swatch:#e4e3de"></i><span>WPC3-7</span></button>
                            <button type="button" class="mhw-color" data-mhw-color="WPC3-8" aria-pressed="false"><i style="--swatch:#6d7074"></i><span>WPC3-8</span></button>
                            <button type="button" class="mhw-color" data-mhw-color="WPC3-9" aria-pressed="false"><i style="--swatch:#222427"></i><span>WPC3-9</span></button>
                        </div>
                    </fieldset>
                    <fieldset><legend>3. التركيب</legend>
                        <div class="mhw-install" role="group" aria-label="خدمة التركيب">
                            <button type="button" class="mhw-install-choice is-active" data-mhw-unit="5" data-mhw-service="بدون تركيب" aria-pressed="true"><b>بدون تركيب</b><small>5 د.ك للعمود</small></button>
                            <button type="button" class="mhw-install-choice" data-mhw-unit="7" data-mhw-service="مع التركيب" aria-pressed="false"><b>مع التركيب</b><small>7 د.ك للعمود</small></button>
                        </div>
                    </fieldset>
                    <div class="mhw-note"><b>العدد المناسب للمكان</b><p>أرسل عرض الفتحة وصورة المكان، وسنراجع معك العدد والتوزيع قبل تأكيد الطلب.</p></div>
                </div>
                <aside class="mhw-summary">
                    <span>الإجمالي التقريبي</span>
                    <strong><em id="mhw-total">40</em> <small>د.ك</small></strong>
                    <ul>
                        <li><span>العدد</span><b id="mhw-count-summary">8 أعمدة</b></li>
                        <li><span>الدرجة</span><b id="mhw-color-summary">WPC3-1</b></li>
                        <li><span>الخدمة</span><b id="mhw-service-summary">بدون تركيب</b></li>
                        <li><span>سعر العمود</span><b id="mhw-unit-summary">5 د.ك</b></li>
                    </ul>
                    <a id="mhw-whatsapp" class="mhw-btn mhw-btn--green mhw-btn--full" href="<?php echo esc_url($whatsapp . $initial_message); ?>" target="_blank" rel="noopener">أرسل الطلب على واتساب</a>
                    <p>السعر النهائي بعد مراجعة العدد ومكان التنفيذ.</p>
                </aside>
            </div>
        </section>

        <section class="mhw-features">
            <div class="mhw-shell">
                <div class="mhw-heading"><span class="mhw-eyebrow mhw-eyebrow--blue">حل ديكوري عملي</span><h2>فصل بصري بدون جدار</h2><p>يحافظ على مرور الضوء ويحدد المساحات بطريقة مرتبة وعصرية.</p></div>
                <div class="mhw-features__grid">
                    <div><b>01</b><h3>تصميم مفتوح</h3><p>يفصل بين المساحات بدون إغلاق الرؤية بالكامل.</p></div>
                    <div><b>02</b><h3>درجات متعددة</h3><p>ست درجات WPC للاختيار بما يناسب لون الأثاث.</p></div>
                    <div><b>03</b><h3>عدد مرن</h3><p>يُحدد عدد الأعمدة حسب عرض الفتحة والتوزيع المطلوب.</p></div>
                    <div><b>04</b><h3>تركيب اختياري</h3><p>اطلب الأعمدة فقط أو مع خدمة التركيب.</p></div>
                </div>
            </div>
        </section>

        <section class="mhw-gallery-section">
            <div class="mhw-shell">
                <div class="mhw-heading"><span class="mhw-eyebrow mhw-eyebrow--blue">أفكار للمساحة</span><h2>نماذج فواصل بديل الخشب</h2></div>
                <div class="mhw-gallery">
                    <figure class="mhw-gallery__wide"><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_ek5mvcek5mvcek5m-Copy.jpg" alt="فاصل أعمدة WPC داخل الصالة" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/12/walnut-wood-slat-room-divider_1080x_44056404-55a1-4657-a48b-57b3980c2e1a.webp" alt="فاصل أعمدة WPC خشبي" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/12/ChatGPT-Image-21-ديسمبر-2025،-12_54_00-م.png" alt="قاطع أعمدة بديل الخشب" loading="lazy"></figure>
                </div>
            </div>
        </section>

        <section class="mhw-pricing">
            <div class="mhw-shell">
                <div class="mhw-heading mhw-heading--light"><span class="mhw-eyebrow">تسعير واضح</span><h2>اختار الخدمة المناسبة</h2></div>
                <div class="mhw-pricing__grid">
                    <div><span>بدون تركيب</span><strong>5 <small>د.ك</small></strong><p>للعمود الواحد</p></div>
                    <div class="is-featured"><i>خدمة كاملة</i><span>مع التركيب</span><strong>7 <small>د.ك</small></strong><p>للعمود الواحد</p></div>
                </div>
            </div>
        </section>

        <section class="mhw-cta"><div class="mhw-shell mhw-cta__box"><div><span class="mhw-eyebrow mhw-eyebrow--blue">جاهز تقسم المساحة؟</span><h2>أرسل صورة المكان وعرض الفتحة</h2><p>نساعدك في تحديد العدد والدرجة المناسبة قبل التنفيذ.</p></div><a class="mhw-btn mhw-btn--dark" href="#mhw-order">احسب طلبك الآن</a></div></section>
        <a class="mhw-mobile-order" href="#mhw-order">احسب أعمدة WPC — من 5 د.ك</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_wpc_divider_page(): void {
    if (!mh_control_is_wpc_divider_page()) return;
    status_header(200);
    get_header();
    echo mh_control_wpc_divider_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_wpc_divider_page', 32);

function mh_control_wpc_divider_title(string $title): string {
    return mh_control_is_wpc_divider_page() ? 'فواصل أعمدة WPC بديل الخشب | تبدأ من 5 د.ك' : $title;
}
add_filter('pre_get_document_title', 'mh_control_wpc_divider_title', 122);

function mh_control_wpc_divider_description(string $description): string {
    return mh_control_is_wpc_divider_page() ? 'فواصل أعمدة WPC بديل الخشب بست درجات، تبدأ من 5 د.ك للعمود بدون تركيب و7 د.ك مع التركيب. احسب العدد والسعر وأرسل الطلب عبر واتساب.' : $description;
}
add_filter('aioseo_title', 'mh_control_wpc_divider_title', 1220);
add_filter('aioseo_description', 'mh_control_wpc_divider_description', 1220);
add_filter('wpseo_title', 'mh_control_wpc_divider_title', 1220);
add_filter('wpseo_metadesc', 'mh_control_wpc_divider_description', 1220);
add_filter('rank_math/frontend/title', 'mh_control_wpc_divider_title', 1220);
add_filter('rank_math/frontend/description', 'mh_control_wpc_divider_description', 1220);

function mh_control_wpc_divider_head(): void {
    if (!mh_control_is_wpc_divider_page()) return;
    ?>
    <meta name="description" content="فواصل أعمدة WPC بديل الخشب بست درجات، من 5 د.ك للعمود بدون تركيب و7 د.ك مع التركيب.">
    <style id="mh-wpc-styles">
    :root{--mhw-blue:#1266d6;--mhw-navy:#071a33;--mhw-ink:#15263a;--mhw-soft:#f2f6fa;--mhw-gold:#d6aa62;--mhw-green:#20b95a}
    html:has(.mh-wpc),body:has(.mh-wpc){overflow-x:clip}.mh-wpc{font-family:Tahoma,Arial,sans-serif;color:var(--mhw-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-wpc *{box-sizing:border-box}.mhw-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhw-hero{padding:72px 0;background:linear-gradient(135deg,#f7f4ef,#e8dfd1)}.mhw-hero__grid{display:grid;grid-template-columns:.78fr 1.22fr;gap:58px;align-items:center}.mhw-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#635749;font-size:14px;font-weight:800;margin-bottom:15px}.mhw-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhw-gold)}.mhw-eyebrow--blue{color:var(--mhw-blue)}.mhw-hero h1{font-size:clamp(44px,6vw,72px);line-height:1.12;color:var(--mhw-navy);margin:0 0 20px;font-weight:900}.mhw-hero p{font-size:18px;line-height:1.9;color:#655d54;margin:0 0 24px}.mhw-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}.mhw-badges span{padding:8px 13px;border:1px solid #cfc2b0;border-radius:999px;font-size:12px;font-weight:700}.mhw-hero__visual{margin:0;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 28px 70px rgba(68,50,31,.18)}.mhw-hero__visual img{display:block;width:100%;height:520px;object-fit:cover}
    .mhw-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 23px;border-radius:8px;text-decoration:none!important;font-weight:900;transition:.2s}.mhw-btn:hover{transform:translateY(-2px)}.mhw-btn--green{background:var(--mhw-green);color:#fff!important}.mhw-btn--dark{background:var(--mhw-navy);color:#fff!important}.mhw-btn--full{width:100%}
    .mhw-builder{padding:88px 0}.mhw-builder__grid{display:grid;grid-template-columns:1.25fr .75fr;gap:38px;align-items:start}.mhw-builder h2,.mhw-heading h2{font-size:clamp(34px,5vw,52px);color:var(--mhw-navy);margin:0 0 30px}.mhw-builder fieldset{border:0;padding:0;margin:0 0 27px}.mhw-builder legend{font-size:17px;color:var(--mhw-navy);font-weight:900;margin-bottom:13px}.mhw-quantity{display:flex;align-items:center;max-width:330px;border:1px solid #d9e2eb;border-radius:13px;overflow:hidden}.mhw-quantity button{width:64px;height:58px;border:0;background:#eef4fa;color:var(--mhw-navy);font-size:26px;cursor:pointer}.mhw-quantity input{flex:1;width:100px;height:58px;border:0;text-align:center;font:900 20px Tahoma,Arial;color:var(--mhw-navy);-moz-appearance:textfield}.mhw-quantity input::-webkit-outer-spin-button,.mhw-quantity input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
    .mhw-colors{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.mhw-color{font:inherit;border:1px solid #dde5ed;border-radius:11px;background:#fff;padding:12px 8px;cursor:pointer;color:#44576c}.mhw-color i{display:block;width:34px;height:34px;border-radius:50%;margin:0 auto 7px;background:var(--swatch);border:1px solid rgba(0,0,0,.12)}.mhw-color span{font-size:11px;font-weight:800}.mhw-color.is-active{outline:2px solid var(--mhw-blue);outline-offset:1px}.mhw-install{display:grid;grid-template-columns:1fr 1fr;gap:12px}.mhw-install-choice{font:inherit;text-align:right;border:1px solid #d9e2eb;border-radius:12px;background:#fff;padding:18px 20px;cursor:pointer;color:var(--mhw-ink)}.mhw-install-choice b,.mhw-install-choice small{display:block}.mhw-install-choice small{color:#718096;margin-top:6px}.mhw-install-choice.is-active{border:2px solid var(--mhw-blue);background:#f5f9ff}.mhw-note{padding:20px 22px;border-radius:13px;background:#f2f6fa}.mhw-note p{margin:7px 0 0;color:#6a7a8d;line-height:1.7;font-size:13px}
    .mhw-summary{position:sticky;top:25px;border-radius:18px;padding:32px;background:var(--mhw-navy);color:#fff;box-shadow:0 20px 45px rgba(7,26,51,.18)}.mhw-summary>span{color:#aebed0;font-size:14px}.mhw-summary>strong{display:block;font-size:60px;line-height:1;margin:14px 0 25px}.mhw-summary>strong em{font-style:normal}.mhw-summary>strong small{font-size:18px}.mhw-summary ul{list-style:none;padding:0;margin:0 0 24px;border-block:1px solid rgba(255,255,255,.13)}.mhw-summary li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;color:#b8c6d6;font-size:13px}.mhw-summary li+li{border-top:1px solid rgba(255,255,255,.09)}.mhw-summary li b{color:#fff}.mhw-summary>p{text-align:center;color:#9eafc2;font-size:11px;margin:13px 0 0}
    .mhw-features{padding:88px 0;background:var(--mhw-soft)}.mhw-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mhw-heading p{color:#68798d;line-height:1.8;margin:0}.mhw-features__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mhw-features__grid>div{background:#fff;border:1px solid #e1e8ef;border-radius:15px;padding:27px}.mhw-features__grid b{font-size:27px;color:var(--mhw-blue)}.mhw-features__grid h3{font-size:18px;color:var(--mhw-navy);margin:10px 0}.mhw-features__grid p{font-size:13px;line-height:1.75;color:#6b7b8e;margin:0}
    .mhw-gallery-section{padding:90px 0}.mhw-gallery{display:grid;grid-template-columns:repeat(2,1fr);gap:17px}.mhw-gallery figure{margin:0;border-radius:16px;overflow:hidden;background:#eee}.mhw-gallery__wide{grid-column:span 2}.mhw-gallery img{width:100%;height:350px;object-fit:cover;display:block;transition:transform .4s}.mhw-gallery__wide img{height:480px}.mhw-gallery figure:hover img{transform:scale(1.02)}
    .mhw-pricing{padding:88px 0;background:var(--mhw-navy);color:#fff}.mhw-heading--light h2{color:#fff}.mhw-pricing__grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:760px;margin:auto}.mhw-pricing__grid>div{position:relative;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);border-radius:16px;padding:31px}.mhw-pricing__grid>div.is-featured{border:2px solid #4f9cff}.mhw-pricing__grid i{position:absolute;top:-13px;right:20px;background:var(--mhw-blue);padding:5px 11px;border-radius:999px;font-size:11px;font-style:normal}.mhw-pricing__grid span{display:block;color:#b9c6d5;font-weight:800}.mhw-pricing__grid strong{display:block;font-size:52px;margin:13px 0 8px}.mhw-pricing__grid strong small{font-size:17px}.mhw-pricing__grid p{margin:0;color:#aebdce}
    .mhw-cta{padding:70px 0;background:#eaf1f7}.mhw-cta__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhw-cta h2{font-size:clamp(29px,4vw,43px);color:var(--mhw-navy);margin:0 0 10px}.mhw-cta p{margin:0;color:#68798e}.mhw-mobile-order{display:none}
    @media(max-width:900px){.mhw-hero__grid,.mhw-builder__grid{grid-template-columns:1fr}.mhw-summary{position:static}.mhw-features__grid{grid-template-columns:1fr 1fr}.mhw-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mhw-shell{width:min(100% - 28px,1180px)}.mhw-hero{padding:52px 0}.mhw-hero h1{font-size:42px}.mhw-hero__visual img{height:330px}.mhw-builder,.mhw-features,.mhw-gallery-section,.mhw-pricing{padding:64px 0}.mhw-colors,.mhw-features__grid,.mhw-gallery,.mhw-pricing__grid{grid-template-columns:1fr}.mhw-gallery__wide{grid-column:auto}.mhw-gallery img,.mhw-gallery__wide img{height:290px}.mhw-install{grid-template-columns:1fr}.mhw-cta{padding:48px 0 90px}.mhw-cta__box{padding:30px 24px}.mhw-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mhw-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_wpc_divider_head', 103);

function mh_control_wpc_divider_script(): void {
    if (!mh_control_is_wpc_divider_page()) return;
    ?>
    <script id="mh-wpc-builder">
    document.addEventListener('DOMContentLoaded',function(){
        var state={count:8,color:'WPC3-1',unit:5,service:'بدون تركيب'};
        var input=document.getElementById('mhw-quantity');
        function clamp(value){value=parseInt(value,10)||1;return Math.min(100,Math.max(1,value));}
        function update(){
            state.count=clamp(input.value);input.value=state.count;
            var total=state.count*state.unit;
            document.getElementById('mhw-total').textContent=String(total);
            document.getElementById('mhw-count-summary').textContent=state.count+' أعمدة';
            document.getElementById('mhw-color-summary').textContent=state.color;
            document.getElementById('mhw-service-summary').textContent=state.service;
            document.getElementById('mhw-unit-summary').textContent=state.unit+' د.ك';
            var msg='مرحباً ماركوز هوم، أريد طلب '+state.count+' أعمدة WPC درجة '+state.color+' '+state.service+'، الإجمالي التقريبي '+total+' د.ك.';
            document.getElementById('mhw-whatsapp').href='https://wa.me/96550204320?text='+encodeURIComponent(msg);
        }
        document.getElementById('mhw-minus').addEventListener('click',function(){input.value=clamp(input.value)-1;update();});
        document.getElementById('mhw-plus').addEventListener('click',function(){input.value=clamp(input.value)+1;update();});
        input.addEventListener('input',update);
        input.addEventListener('change',update);
        document.querySelectorAll('[data-mhw-color]').forEach(function(button){button.addEventListener('click',function(){document.querySelectorAll('[data-mhw-color]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});button.classList.add('is-active');button.setAttribute('aria-pressed','true');state.color=button.dataset.mhwColor;update();});});
        document.querySelectorAll('[data-mhw-unit]').forEach(function(button){button.addEventListener('click',function(){document.querySelectorAll('[data-mhw-unit]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});button.classList.add('is-active');button.setAttribute('aria-pressed','true');state.unit=Number(button.dataset.mhwUnit);state.service=button.dataset.mhwService;update();});});
        update();
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_wpc_divider_script', 102);


/**
 * 9 mm parquet flooring — roadmap step 2, product 5 of 6.
 */
function mh_control_is_parquet_page(): bool {
    return function_exists('is_product') && is_singular('product') && get_queried_object_id() === 6545;
}

function mh_control_parquet_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=';
    $initial_message = rawurlencode('مرحباً ماركوز هوم، أريد باركيه خشب 9 مم درجة K9188 لمساحة 20 م². الكمية المقترحة مع 10% احتياط 22 م²، تكلفة الخامة التقريبية 44 د.ك. أريد مراجعة القياس وسعر التركيب.');
    ob_start();
    ?>
    <main class="mh-parquet" dir="rtl">
        <section class="mhpq-hero">
            <div class="mhpq-shell mhpq-hero__grid">
                <div class="mhpq-hero__copy">
                    <span class="mhpq-eyebrow">باركيه خشب 9 مم</span>
                    <h1>دفء الخشب.<br>وشكل يغيّر الغرفة.</h1>
                    <p>سبع درجات خشبية تناسب الأثاث العصري، مع حاسبة تساعدك في تقدير المساحة وكمية الخامة المطلوبة.</p>
                    <div class="mhpq-badges"><span>سُمك 9 مم</span><span>7 درجات</span><span>السعر الحالي 2 د.ك</span></div>
                    <a class="mhpq-btn mhpq-btn--green" href="#mhpq-order">احسب مساحة غرفتك</a>
                </div>
                <figure class="mhpq-hero__visual"><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_4g3pw4g3pw4g3pw4-Copy.jpg" alt="أرضيات باركيه خشب من ماركوز هوم"></figure>
            </div>
        </section>

        <section class="mhpq-builder" id="mhpq-order">
            <div class="mhpq-shell mhpq-builder__grid">
                <div>
                    <span class="mhpq-eyebrow mhpq-eyebrow--blue">حاسبة تقديرية</span>
                    <h2>أدخل طول وعرض المكان</h2>
                    <div class="mhpq-measures">
                        <label><span>الطول بالمتر</span><input id="mhpq-length" type="number" min="0.5" max="100" step="0.1" value="4" inputmode="decimal"></label>
                        <label><span>العرض بالمتر</span><input id="mhpq-width" type="number" min="0.5" max="100" step="0.1" value="5" inputmode="decimal"></label>
                    </div>
                    <fieldset><legend>اختار درجة الخشب</legend>
                        <div class="mhpq-colors" role="group" aria-label="درجة الباركيه">
                            <button type="button" class="mhpq-color" data-mhpq-color="K2050" aria-pressed="false"><i style="--swatch:#c7a271"></i><span>K2050</span></button>
                            <button type="button" class="mhpq-color" data-mhpq-color="K2132" aria-pressed="false"><i style="--swatch:#a97f52"></i><span>K2132</span></button>
                            <button type="button" class="mhpq-color" data-mhpq-color="K306" aria-pressed="false"><i style="--swatch:#d9be91"></i><span>K306</span></button>
                            <button type="button" class="mhpq-color" data-mhpq-color="K761" aria-pressed="false"><i style="--swatch:#7e5b3f"></i><span>K761</span></button>
                            <button type="button" class="mhpq-color" data-mhpq-color="K8026" aria-pressed="false"><i style="--swatch:#b49a76"></i><span>K8026</span></button>
                            <button type="button" class="mhpq-color is-active" data-mhpq-color="K9188" aria-pressed="true"><i style="--swatch:#8c694c"></i><span>K9188</span></button>
                            <button type="button" class="mhpq-color" data-mhpq-color="K9876" aria-pressed="false"><i style="--swatch:#5b4536"></i><span>K9876</span></button>
                        </div>
                    </fieldset>
                    <div class="mhpq-note"><b>أضفنا 10% احتياط</b><p>الحاسبة تضيف كمية احتياط للقص والزوايا. القياس النهائي وسعر التركيب يتم تأكيدهما بعد مراجعة المكان.</p></div>
                </div>
                <aside class="mhpq-summary">
                    <span>تكلفة الخامة التقريبية</span>
                    <strong><em id="mhpq-total">44</em> <small>د.ك</small></strong>
                    <ul>
                        <li><span>مساحة المكان</span><b id="mhpq-area">20 م²</b></li>
                        <li><span>الكمية مع الاحتياط</span><b id="mhpq-needed">22 م²</b></li>
                        <li><span>الدرجة</span><b id="mhpq-color-summary">K9188</b></li>
                        <li><span>سعر الخامة</span><b>2 د.ك / م²</b></li>
                    </ul>
                    <a id="mhpq-whatsapp" class="mhpq-btn mhpq-btn--green mhpq-btn--full" href="<?php echo esc_url($whatsapp . $initial_message); ?>" target="_blank" rel="noopener">أرسل القياس على واتساب</a>
                    <p>السعر النهائي بعد القياس وتحديد متطلبات التركيب.</p>
                </aside>
            </div>
        </section>

        <section class="mhpq-features">
            <div class="mhpq-shell">
                <div class="mhpq-heading"><span class="mhpq-eyebrow mhpq-eyebrow--blue">اختيار عملي للبيت</span><h2>مظهر خشبي بتفاصيل هادئة</h2></div>
                <div class="mhpq-features__grid">
                    <div><b>9 مم</b><h3>سُمك الخامة</h3><p>باركيه خشب بالسُمك المسجل للمنتج.</p></div>
                    <div><b>7</b><h3>درجات متاحة</h3><p>أكواد متعددة من الفاتح إلى الداكن.</p></div>
                    <div><b>10%</b><h3>احتياط محسوب</h3><p>تقدير إضافي للقص والزوايا أثناء التنفيذ.</p></div>
                    <div><b>قياس</b><h3>مراجعة قبل التنفيذ</h3><p>نراجع أبعاد المكان والاتجاه الأنسب للتركيب.</p></div>
                </div>
            </div>
        </section>

        <section class="mhpq-gallery-section">
            <div class="mhpq-shell">
                <div class="mhpq-heading"><span class="mhpq-eyebrow mhpq-eyebrow--blue">شكل الأرضية داخل المكان</span><h2>دفء وأناقة في كل غرفة</h2></div>
                <div class="mhpq-gallery">
                    <figure class="mhpq-gallery__wide"><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_f4ev3pf4ev3pf4ev-Copy.jpg" alt="أرضية باركيه خشبية داخل غرفة معيشة" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_4g3pw4g3pw4g3pw4-Copy.jpg" alt="باركيه خشب بدرجة دافئة" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_3ofg6o3ofg6o3ofg.jpg" alt="أرضيات باركيه من ماركوز هوم" loading="lazy"></figure>
                </div>
            </div>
        </section>

        <section class="mhpq-price">
            <div class="mhpq-shell">
                <div class="mhpq-heading mhpq-heading--light"><span class="mhpq-eyebrow">عرض المنتج الحالي</span><h2>2 د.ك بدل 3.5 د.ك</h2><p>تكلفة الخامة محسوبة حسب المساحة، وسعر التركيب يُحدد بعد مراجعة المكان.</p></div>
            </div>
        </section>

        <section class="mhpq-cta"><div class="mhpq-shell mhpq-cta__box"><div><span class="mhpq-eyebrow mhpq-eyebrow--blue">جاهز تغيّر الأرضية؟</span><h2>أرسل مساحة المكان وصورته</h2><p>نراجع معك الدرجة والكمية وتكلفة التركيب.</p></div><a class="mhpq-btn mhpq-btn--dark" href="#mhpq-order">احسب المساحة الآن</a></div></section>
        <a class="mhpq-mobile-order" href="#mhpq-order">احسب الباركيه — 2 د.ك / م²</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_parquet_page(): void {
    if (!mh_control_is_parquet_page()) return;
    status_header(200);get_header();
    echo mh_control_parquet_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();exit;
}
add_action('template_redirect', 'mh_control_render_parquet_page', 33);

function mh_control_parquet_title(string $title): string {
    return mh_control_is_parquet_page() ? 'باركيه خشب 9 مم من ماركوز هوم | 2 د.ك' : $title;
}
add_filter('pre_get_document_title', 'mh_control_parquet_title', 123);
function mh_control_parquet_description(string $description): string {
    return mh_control_is_parquet_page() ? 'باركيه خشب 9 مم بسبع درجات. احسب مساحة المكان والكمية المطلوبة مع 10% احتياط. السعر الحالي 2 د.ك بدل 3.5 د.ك.' : $description;
}
add_filter('aioseo_title', 'mh_control_parquet_title', 1230);
add_filter('aioseo_description', 'mh_control_parquet_description', 1230);
add_filter('wpseo_title', 'mh_control_parquet_title', 1230);
add_filter('wpseo_metadesc', 'mh_control_parquet_description', 1230);
add_filter('rank_math/frontend/title', 'mh_control_parquet_title', 1230);
add_filter('rank_math/frontend/description', 'mh_control_parquet_description', 1230);

function mh_control_parquet_head(): void {
    if (!mh_control_is_parquet_page()) return;
    ?>
    <meta name="description" content="باركيه خشب 9 مم بسبع درجات، السعر الحالي 2 د.ك بدل 3.5 د.ك، مع حاسبة مساحة وكمية.">
    <style id="mh-parquet-styles">
    :root{--mhpq-blue:#1266d6;--mhpq-navy:#071a33;--mhpq-ink:#15263a;--mhpq-soft:#f2f6fa;--mhpq-gold:#d6aa62;--mhpq-green:#20b95a}
    html:has(.mh-parquet),body:has(.mh-parquet){overflow-x:clip}.mh-parquet{font-family:Tahoma,Arial,sans-serif;color:var(--mhpq-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-parquet *{box-sizing:border-box}.mhpq-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhpq-hero{padding:72px 0;background:linear-gradient(135deg,#f8f4ed,#e5d5bf)}.mhpq-hero__grid{display:grid;grid-template-columns:.78fr 1.22fr;gap:58px;align-items:center}.mhpq-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#6e5a43;font-size:14px;font-weight:800;margin-bottom:15px}.mhpq-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhpq-gold)}.mhpq-eyebrow--blue{color:var(--mhpq-blue)}.mhpq-hero h1{font-size:clamp(44px,6vw,72px);line-height:1.12;color:var(--mhpq-navy);margin:0 0 20px;font-weight:900}.mhpq-hero p{font-size:18px;line-height:1.9;color:#655d54;margin:0 0 24px}.mhpq-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}.mhpq-badges span{padding:8px 13px;border:1px solid #cdbb9f;border-radius:999px;font-size:12px;font-weight:700}.mhpq-hero__visual{margin:0;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 28px 70px rgba(68,50,31,.18)}.mhpq-hero__visual img{display:block;width:100%;height:520px;object-fit:cover}
    .mhpq-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 23px;border-radius:8px;text-decoration:none!important;font-weight:900;transition:.2s}.mhpq-btn:hover{transform:translateY(-2px)}.mhpq-btn--green{background:var(--mhpq-green);color:#fff!important}.mhpq-btn--dark{background:var(--mhpq-navy);color:#fff!important}.mhpq-btn--full{width:100%}
    .mhpq-builder{padding:88px 0}.mhpq-builder__grid{display:grid;grid-template-columns:1.25fr .75fr;gap:38px;align-items:start}.mhpq-builder h2,.mhpq-heading h2{font-size:clamp(34px,5vw,52px);color:var(--mhpq-navy);margin:0 0 30px}.mhpq-measures{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:28px}.mhpq-measures label span{display:block;font-size:13px;font-weight:800;color:var(--mhpq-navy);margin-bottom:8px}.mhpq-measures input{width:100%;height:58px;border:1px solid #d9e2eb;border-radius:12px;padding:0 16px;font:900 19px Tahoma,Arial;color:var(--mhpq-navy)}.mhpq-builder fieldset{border:0;padding:0;margin:0 0 27px}.mhpq-builder legend{font-size:17px;color:var(--mhpq-navy);font-weight:900;margin-bottom:13px}.mhpq-colors{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.mhpq-color{font:inherit;border:1px solid #dde5ed;border-radius:11px;background:#fff;padding:12px 8px;cursor:pointer;color:#44576c}.mhpq-color i{display:block;width:34px;height:34px;border-radius:50%;margin:0 auto 7px;background:var(--swatch);border:1px solid rgba(0,0,0,.12)}.mhpq-color span{font-size:11px;font-weight:800}.mhpq-color.is-active{outline:2px solid var(--mhpq-blue);outline-offset:1px}.mhpq-note{padding:20px 22px;border-radius:13px;background:#f2f6fa}.mhpq-note p{margin:7px 0 0;color:#6a7a8d;line-height:1.7;font-size:13px}
    .mhpq-summary{position:sticky;top:25px;border-radius:18px;padding:32px;background:var(--mhpq-navy);color:#fff;box-shadow:0 20px 45px rgba(7,26,51,.18)}.mhpq-summary>span{color:#aebed0;font-size:14px}.mhpq-summary>strong{display:block;font-size:60px;line-height:1;margin:14px 0 25px}.mhpq-summary>strong em{font-style:normal}.mhpq-summary>strong small{font-size:18px}.mhpq-summary ul{list-style:none;padding:0;margin:0 0 24px;border-block:1px solid rgba(255,255,255,.13)}.mhpq-summary li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;color:#b8c6d6;font-size:13px}.mhpq-summary li+li{border-top:1px solid rgba(255,255,255,.09)}.mhpq-summary li b{color:#fff}.mhpq-summary>p{text-align:center;color:#9eafc2;font-size:11px;margin:13px 0 0}
    .mhpq-features{padding:88px 0;background:var(--mhpq-soft)}.mhpq-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mhpq-heading p{color:#68798d;line-height:1.8;margin:0}.mhpq-features__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mhpq-features__grid>div{background:#fff;border:1px solid #e1e8ef;border-radius:15px;padding:27px}.mhpq-features__grid b{font-size:27px;color:var(--mhpq-blue)}.mhpq-features__grid h3{font-size:18px;color:var(--mhpq-navy);margin:10px 0}.mhpq-features__grid p{font-size:13px;line-height:1.75;color:#6b7b8e;margin:0}
    .mhpq-gallery-section{padding:90px 0}.mhpq-gallery{display:grid;grid-template-columns:repeat(2,1fr);gap:17px}.mhpq-gallery figure{margin:0;border-radius:16px;overflow:hidden;background:#eee}.mhpq-gallery__wide{grid-column:span 2}.mhpq-gallery img{width:100%;height:350px;object-fit:cover;display:block;transition:transform .4s}.mhpq-gallery__wide img{height:480px}.mhpq-gallery figure:hover img{transform:scale(1.02)}
    .mhpq-price{padding:88px 0;background:var(--mhpq-navy);color:#fff}.mhpq-heading--light h2{color:#fff}.mhpq-heading--light p{color:#b9c6d5}.mhpq-cta{padding:70px 0;background:#eaf1f7}.mhpq-cta__box{display:flex;align-items:center;justify-content:space-between;gap:30px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhpq-cta h2{font-size:clamp(29px,4vw,43px);color:var(--mhpq-navy);margin:0 0 10px}.mhpq-cta p{margin:0;color:#68798e}.mhpq-mobile-order{display:none}
    @media(max-width:900px){.mhpq-hero__grid,.mhpq-builder__grid{grid-template-columns:1fr}.mhpq-summary{position:static}.mhpq-features__grid{grid-template-columns:1fr 1fr}.mhpq-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mhpq-shell{width:min(100% - 28px,1180px)}.mhpq-hero{padding:52px 0}.mhpq-hero h1{font-size:42px}.mhpq-hero__visual img{height:330px}.mhpq-builder,.mhpq-features,.mhpq-gallery-section,.mhpq-price{padding:64px 0}.mhpq-measures,.mhpq-colors,.mhpq-features__grid,.mhpq-gallery{grid-template-columns:1fr}.mhpq-gallery__wide{grid-column:auto}.mhpq-gallery img,.mhpq-gallery__wide img{height:290px}.mhpq-cta{padding:48px 0 90px}.mhpq-cta__box{padding:30px 24px}.mhpq-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mhpq-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_parquet_head', 104);

function mh_control_parquet_script(): void {
    if (!mh_control_is_parquet_page()) return;
    ?>
    <script id="mh-parquet-builder">
    document.addEventListener('DOMContentLoaded',function(){
        var state={color:'K9188',unit:2};
        var length=document.getElementById('mhpq-length'),width=document.getElementById('mhpq-width');
        function num(v){v=parseFloat(v);return isFinite(v)&&v>0?v:0;}
        function fmt(v){return Number.isInteger(v)?String(v):v.toFixed(1);}
        function update(){
            var area=Math.round(num(length.value)*num(width.value)*10)/10;
            var needed=Math.ceil(area*1.10);
            var total=needed*state.unit;
            document.getElementById('mhpq-area').textContent=fmt(area)+' م²';
            document.getElementById('mhpq-needed').textContent=needed+' م²';
            document.getElementById('mhpq-total').textContent=fmt(total);
            document.getElementById('mhpq-color-summary').textContent=state.color;
            var msg='مرحباً ماركوز هوم، أريد باركيه خشب 9 مم درجة '+state.color+' لمساحة '+fmt(area)+' م². الكمية المقترحة مع 10% احتياط '+needed+' م²، تكلفة الخامة التقريبية '+fmt(total)+' د.ك. أريد مراجعة القياس وسعر التركيب.';
            document.getElementById('mhpq-whatsapp').href='https://wa.me/96550204320?text='+encodeURIComponent(msg);
        }
        length.addEventListener('input',update);width.addEventListener('input',update);
        document.querySelectorAll('[data-mhpq-color]').forEach(function(button){button.addEventListener('click',function(){document.querySelectorAll('[data-mhpq-color]').forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});button.classList.add('is-active');button.setAttribute('aria-pressed','true');state.color=button.dataset.mhpqColor;update();});});
        update();
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_parquet_script', 103);


/**
 * Design 198 — The Pyramid Wood advertising and sales page.
 */
function mh_control_is_design_198_page(): bool {
    return mh_control_request_path() === '/design-198/';
}

function mh_control_design_198_asset(string $name): string {
    return plugins_url('assets/design-198/' . ltrim($name, '/'), __FILE__);
}

function mh_control_design_198_markup(): string {
    $beige = mh_control_design_198_asset('design-198-beige-wood.webp');
    $white = mh_control_design_198_asset('design-198-white.webp');
    $charcoal = mh_control_design_198_asset('design-198-charcoal.webp');
    $dimensions = mh_control_design_198_asset('design-198-dimensions.webp');
    ob_start();
    ?>
    <main class="mh198" dir="rtl" data-design="198">
        <section class="mh198-hero">
            <div class="mh198-shell mh198-hero__grid">
                <div class="mh198-hero__copy">
                    <span class="mh198-kicker">تنفيذ داخل الكويت</span>
                    <h1>تصميم 198<br><em>الخشب الهرمي</em></h1>
                    <p>خلفية شاشة متكاملة بتكوين هندسي، طاولة معلّقة وكابينة أرفف بإضاءة داخلية. الباقات تبدأ من <strong>130 د.ك بدون تركيب</strong> أو <strong>170 د.ك مع التركيب</strong>.</p>
                    <div class="mh198-badges"><span>تبدأ من 130 د.ك</span><span>خيار مع أو بدون تركيب</span><span>تنفيذ داخل الكويت</span></div>
                    <a class="mh198-btn mh198-btn--primary" href="#mh198-calculator">احسب سعر تصميمك</a>
                </div>
                <figure class="mh198-hero__visual">
                    <img id="mh198-main-image" src="<?php echo esc_url($beige); ?>" alt="تصميم 198 الخشب الهرمي باللون البيج الخشبي من ماركوز هوم" width="1440" height="1080" fetchpriority="high">
                    <figcaption id="mh198-main-caption">بيج خشبي — الاختيار الافتراضي</figcaption>
                </figure>
            </div>
        </section>

        <section class="mh198-gallery" aria-label="صور تصميم 198">
            <div class="mh198-shell">
                <div class="mh198-heading"><span class="mh198-kicker mh198-kicker--blue">الألوان والمقاسات</span><h2>شاهد التصميم الحقيقي قبل الاختيار</h2><p>اضغط على اللون لتحديث الصورة الرئيسية مباشرة.</p></div>
                <div class="mh198-gallery__grid">
                    <button type="button" class="mh198-thumb is-active" data-mh198-color="بيج خشبي" data-mh198-image="<?php echo esc_url($beige); ?>" aria-pressed="true"><img src="<?php echo esc_url($beige); ?>" alt="تصميم 198 باللون البيج الخشبي" loading="lazy"><span>بيج خشبي</span></button>
                    <button type="button" class="mh198-thumb" data-mh198-color="أبيض" data-mh198-image="<?php echo esc_url($white); ?>" aria-pressed="false"><img src="<?php echo esc_url($white); ?>" alt="تصميم 198 باللون الأبيض" loading="lazy"><span>أبيض</span></button>
                    <button type="button" class="mh198-thumb" data-mh198-color="رمادي غامق" data-mh198-image="<?php echo esc_url($charcoal); ?>" aria-pressed="false"><img src="<?php echo esc_url($charcoal); ?>" alt="تصميم 198 باللون الرمادي الغامق" loading="lazy"><span>رمادي غامق</span></button>
                    <button type="button" class="mh198-thumb mh198-thumb--dimensions" data-mh198-color="المقاسات" data-mh198-image="<?php echo esc_url($dimensions); ?>" aria-pressed="false"><img src="<?php echo esc_url($dimensions); ?>" alt="مقاسات تصميم 198 الخشب الهرمي" loading="lazy"><span>صورة المقاسات</span></button>
                </div>
            </div>
        </section>

        <section class="mh198-calculator" id="mh198-calculator">
            <div class="mh198-shell mh198-calculator__grid">
                <div class="mh198-form">
                    <span class="mh198-kicker mh198-kicker--blue">حاسبة السعر المبدئي</span>
                    <h2>اختر باقتك في 3 خطوات</h2>
                    <fieldset><legend><b>1</b> اختر عرض الحائط</legend><div class="mh198-options mh198-options--walls">
                        <button type="button" class="is-active" data-mh198-wall="3 إلى أقل من 3.5 متر" data-mh198-wall-min="3" data-mh198-wall-max="3.49" data-mh198-no-install="130" data-mh198-with-install="170" data-mh198-included="طاولة 2.5 متر + كابينة + 3 ألواح فوم بورد" aria-pressed="true"><strong>3 — أقل من 3.5 م</strong><span>130 بدون تركيب · 170 مع التركيب</span><small>طاولة 2.5 م + كابينة + 3 ألواح فوم بورد</small></button>
                        <button type="button" data-mh198-wall="3.5 إلى 4.5 متر" data-mh198-wall-min="3.5" data-mh198-wall-max="4.5" data-mh198-no-install="150" data-mh198-with-install="198" data-mh198-included="طاولة 3 متر + كابينة + 4 ألواح فوم بورد" aria-pressed="false"><strong>3.5 — 4.5 م</strong><span>150 بدون تركيب · 198 مع التركيب</span><small>طاولة 3 م + كابينة + 4 ألواح فوم بورد</small></button>
                        <button type="button" data-mh198-wall="4.60 إلى 5.5 متر" data-mh198-wall-min="4.6" data-mh198-wall-max="5.5" data-mh198-no-install="160" data-mh198-with-install="210" data-mh198-included="تفاصيل الباقة تُراجع حسب مقاس الحائط" aria-pressed="false"><strong>4.60 — 5.5 م</strong><span>160 بدون تركيب · 210 مع التركيب</span><small>تفاصيل الباقة تُراجع حسب المقاس</small></button>
                        <button type="button" data-mh198-wall="مقاس خاص: أكثر من 5.5 متر" data-mh198-wall-min="5.51" data-mh198-wall-max="0" data-mh198-no-install="0" data-mh198-with-install="0" data-mh198-included="طلب تسعير خاص على واتساب" data-mh198-custom="1" aria-pressed="false"><strong>أكثر من 5.5 م</strong><span>اطلب حساب التكلفة</span><small>تسعير خاص عبر واتساب</small></button>
                    </div></fieldset>

                    <fieldset><legend><b>2</b> اختر طريقة الاستلام</legend><div class="mh198-options mh198-options--service">
                        <button type="button" class="is-active" data-mh198-install="0" aria-pressed="true"><strong>بدون تركيب</strong><span>استلام مكونات الباقة بدون خدمة التركيب</span></button>
                        <button type="button" data-mh198-install="1" aria-pressed="false"><strong>مع التركيب</strong><span>السعر يشمل خدمة التركيب داخل الكويت</span></button>
                    </div></fieldset>

                    <fieldset><legend><b>3</b> اختر اللون</legend><div class="mh198-colors">
                        <button type="button" class="is-active" data-mh198-color-choice="بيج خشبي" data-mh198-color-image="<?php echo esc_url($beige); ?>" aria-pressed="true"><i style="--mh198-swatch:#c9a87c"></i><span>بيج خشبي</span></button>
                        <button type="button" data-mh198-color-choice="أبيض" data-mh198-color-image="<?php echo esc_url($white); ?>" aria-pressed="false"><i style="--mh198-swatch:#f4f4f1"></i><span>أبيض</span></button>
                        <button type="button" data-mh198-color-choice="رمادي غامق" data-mh198-color-image="<?php echo esc_url($charcoal); ?>" aria-pressed="false"><i style="--mh198-swatch:#3e434a"></i><span>رمادي غامق</span></button>
                    </div></fieldset>
                </div>

                <aside class="mh198-summary" aria-live="polite">
                    <span>الإجمالي المبدئي</span>
                    <strong id="mh198-total">130 <small>د.ك</small></strong>
                    <div id="mh198-special" class="mh198-special" hidden>مقاس خاص — نراجع الصورة والمقاس ونرسل لك السعر.</div>
                    <ul>
                        <li><span>فئة الحائط</span><b id="mh198-wall-summary">3 إلى أقل من 3.5 متر</b></li>
                        <li><span>طريقة الاستلام</span><b id="mh198-service-summary">بدون تركيب</b></li>
                        <li><span>سعر الباقة</span><b id="mh198-wall-price">130 د.ك</b></li>
                        <li><span>تشمل الباقة</span><b id="mh198-included-summary">طاولة 2.5 متر + كابينة + 3 ألواح فوم بورد</b></li>
                        <li><span>اللون</span><b id="mh198-color-summary">بيج خشبي</b></li>
                    </ul>
                    <a id="mh198-whatsapp" class="mh198-btn mh198-btn--whatsapp" href="https://wa.me/96550204320" target="_blank" rel="noopener">أرسل الاختيار على واتساب</a>
                    <p>السعر مبدئي ويُعتمد بعد مراجعة صورة الحائط والمقاسات وموقع التنفيذ داخل الكويت.</p>
                </aside>
            </div>
        </section>

        <section class="mh198-specs"><div class="mh198-shell"><div class="mh198-heading"><span class="mh198-kicker mh198-kicker--blue">المواصفات القياسية</span><h2>تفاصيل واضحة قبل التنفيذ</h2></div><div class="mh198-specs__grid">
            <div><strong>2.5–3 م</strong><h3>الطاولة المعلّقة</h3><p>المقاس المشمول يختلف حسب شريحة عرض الحائط.</p></div>
            <div><strong>2 م</strong><h3>كابينة الأرفف</h3><p>مع إضاءة داخلية وتكوين جانبي عملي.</p></div>
            <div><strong>2 × 1.20 م</strong><h3>مساحة التلفزيون</h3><p>عرض مترين وارتفاع 1.20 متر.</p></div>
            <div><strong>2.90 م</strong><h3>الارتفاع الكلي</h3><p>مقاس اللوح 1.20 × 2.90 متر.</p></div>
            <div><strong>32 سم</strong><h3>عمق الطاولة</h3><p>عمق عملي بتصميم معلّق ونظيف.</p></div>
            <div><strong>الكويت</strong><h3>نطاق التنفيذ</h3><p>مراجعة المقاس والتجهيز والتركيب قبل الاعتماد.</p></div>
        </div></div></section>

        <section class="mh198-trust" aria-labelledby="mh198-trust-title">
            <div class="mh198-shell">
                <div class="mh198-heading"><span class="mh198-kicker mh198-kicker--blue">معلومات واضحة قبل الطلب</span><h2 id="mh198-trust-title">اطلب من Marco's Home بثقة</h2><p>صفحة رسمية لتصميم 198 على نطاق marcohom.com، والتنفيذ داخل الكويت فقط.</p></div>
                <div class="mh198-trust__grid">
                    <article><span class="mh198-trust__icon" aria-hidden="true">MH</span><h3>هوية النشاط</h3><strong>Marco's Home — ماركوز هوم</strong><p>تصميم وتوريد وتركيب حلول الديكور الداخلي وخلفيات الشاشات داخل الكويت.</p></article>
                    <article><span class="mh198-trust__icon" aria-hidden="true">1</span><h3>طريقة الطلب</h3><p>اختر شريحة الحائط، وحدد مع أو بدون تركيب، ثم اختر اللون وأرسل الباقة على واتساب. لا يتم تحصيل أي دفعة من هذه الصفحة.</p></article>
                    <article><span class="mh198-trust__icon" aria-hidden="true">✓</span><h3>السعر والتنفيذ</h3><p>الإجمالي المعروض مبدئي ويُعتمد بعد مراجعة صورة الموقع والمقاسات وتأكيد تفاصيل التنفيذ والتركيب.</p></article>
                </div>
                <div class="mh198-trust__actions">
                    <a href="https://wa.me/96550204320" target="_blank" rel="noopener">واتساب: +965 5020 4320</a>
                    <a href="https://maps.app.goo.gl/GMPEmTXtd66YkdpY6?g_st=iwb" target="_blank" rel="noopener">موقع النشاط على خرائط Google</a>
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>">تواصل معنا</a>
                    <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">سياسة الخصوصية</a>
                    <a href="<?php echo esc_url(home_url('/terms-and-conditions/')); ?>">الشروط والأحكام</a>
                    <a href="<?php echo esc_url(home_url('/shipping-and-installation/')); ?>">التوصيل والتركيب</a>
                    <a href="<?php echo esc_url(home_url('/returns-and-refunds/')); ?>">الاستبدال والاسترجاع</a>
                </div>
            </div>
        </section>
        <a id="mh198-mobile-whatsapp" class="mh198-mobile-whatsapp" href="https://wa.me/96550204320" target="_blank" rel="noopener">اطلب تصميم 198 على واتساب</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_design_198_page(): void {
    if (!mh_control_is_design_198_page()) return;
    mh_control_prepare_virtual_page();
    get_header();
    echo mh_control_design_198_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_design_198_page', 22);

function mh_control_design_198_title(string $title): string {
    return mh_control_is_design_198_page() ? 'تصميم 198 الخشب الهرمي | خلفية شاشة في الكويت — ماركوز هوم' : $title;
}
add_filter('pre_get_document_title', 'mh_control_design_198_title', 128);

function mh_control_design_198_head(): void {
    if (!mh_control_is_design_198_page()) return;
    ?>
    <style id="mh-design-198-styles">
    :root{--mh198-blue:#1266d6;--mh198-navy:#071a33;--mh198-ink:#14263b;--mh198-soft:#f1f5f9;--mh198-line:#dce5ee;--mh198-green:#20b95a;--mh198-gold:#d6aa62}.mh198{font-family:Tahoma,Arial,sans-serif;color:var(--mh198-ink);background:#fff;overflow:hidden}.mh198 *{box-sizing:border-box}.mh198-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}.mh198-hero{padding:74px 0;background:linear-gradient(135deg,#f8fafc,#e7eef6)}.mh198-hero__grid{display:grid;grid-template-columns:.82fr 1.18fr;gap:54px;align-items:center}.mh198-kicker{display:inline-flex;align-items:center;gap:10px;color:#60738a;font-size:14px;font-weight:900;margin-bottom:15px}.mh198-kicker:before{content:"";width:34px;height:2px;background:var(--mh198-gold)}.mh198-kicker--blue{color:var(--mh198-blue)}.mh198-hero h1{font-size:clamp(44px,6vw,74px);line-height:1.08;margin:0 0 20px;color:var(--mh198-navy);font-weight:900}.mh198-hero h1 em{font-style:normal;color:var(--mh198-blue)}.mh198-hero p{font-size:18px;line-height:1.9;color:#617389;margin:0 0 24px}.mh198-hero p strong{color:var(--mh198-navy)}.mh198-badges{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:27px}.mh198-badges span{background:#fff;border:1px solid var(--mh198-line);border-radius:999px;padding:9px 13px;font-size:12px;font-weight:800}.mh198-btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 23px;border-radius:9px;text-decoration:none!important;font-weight:900}.mh198-btn--primary{background:var(--mh198-navy);color:#fff!important}.mh198-btn--whatsapp{width:100%;background:var(--mh198-green);color:#fff!important}.mh198-hero__visual{margin:0;border-radius:20px;overflow:hidden;background:#e6ebef;box-shadow:0 24px 55px rgba(7,26,51,.16);position:relative}.mh198-hero__visual img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}.mh198-hero__visual figcaption{position:absolute;inset-inline:18px;bottom:16px;background:rgba(7,26,51,.88);color:#fff;border-radius:8px;padding:10px 14px;font-size:12px;font-weight:800}.mh198-gallery{padding:86px 0}.mh198-heading{text-align:center;max-width:750px;margin:0 auto 40px}.mh198-heading h2,.mh198-form h2{font-size:clamp(32px,5vw,50px);color:var(--mh198-navy);line-height:1.2;margin:0 0 13px}.mh198-heading p{color:#687a8e;margin:0}.mh198-gallery__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.mh198-thumb{border:1px solid var(--mh198-line);background:#fff;border-radius:14px;padding:0;overflow:hidden;cursor:pointer;font:800 13px Tahoma,Arial;color:var(--mh198-navy);text-align:right}.mh198-thumb img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}.mh198-thumb span{display:block;padding:13px 15px}.mh198-thumb.is-active{outline:3px solid var(--mh198-blue);outline-offset:2px}.mh198-calculator{padding:88px 0;background:var(--mh198-soft)}.mh198-calculator__grid{display:grid;grid-template-columns:1.25fr .75fr;gap:36px;align-items:start}.mh198-form fieldset{border:0;padding:0;margin:0 0 30px}.mh198-form legend{font-size:17px;font-weight:900;color:var(--mh198-navy);margin-bottom:13px}.mh198-form legend b{display:inline-grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--mh198-blue);color:#fff;margin-left:7px}.mh198-options{display:grid;gap:10px}.mh198-options--walls,.mh198-options--service{grid-template-columns:repeat(2,1fr)}.mh198-options button,.mh198-colors button{border:1px solid var(--mh198-line);background:#fff;border-radius:11px;cursor:pointer;font:inherit;color:#44586e}.mh198-options button{padding:16px 13px;text-align:right}.mh198-options strong,.mh198-options span,.mh198-options small{display:block}.mh198-options strong{font-size:13px;color:var(--mh198-navy)}.mh198-options span{font-size:12px;font-weight:900;color:var(--mh198-blue);margin-top:7px}.mh198-options small{font-size:10px;line-height:1.6;color:#6b7d91;margin-top:6px}.mh198-options button.is-active,.mh198-colors button.is-active{outline:2px solid var(--mh198-blue);outline-offset:1px;background:#f6f9ff}.mh198-colors{display:flex;gap:10px;flex-wrap:wrap}.mh198-colors button{display:flex;align-items:center;gap:8px;padding:10px 14px;font-weight:800}.mh198-colors i{width:27px;height:27px;border-radius:50%;background:var(--mh198-swatch);border:1px solid rgba(0,0,0,.16)}.mh198-summary{position:sticky;top:24px;background:var(--mh198-navy);color:#fff;border-radius:18px;padding:30px;box-shadow:0 20px 50px rgba(7,26,51,.18)}.mh198-summary>span{color:#b1c0d0;font-size:13px}.mh198-summary>strong{display:block;font-size:56px;line-height:1;margin:12px 0 20px}.mh198-summary>strong small{font-size:17px}.mh198-summary ul{list-style:none;padding:0;margin:0 0 20px;border-block:1px solid rgba(255,255,255,.13)}.mh198-summary li{display:flex;justify-content:space-between;gap:12px;padding:11px 0;font-size:12px;color:#aebed0}.mh198-summary li+li{border-top:1px solid rgba(255,255,255,.08)}.mh198-summary li b{color:#fff;text-align:left;max-width:65%}.mh198-special{background:#e6f1ff;color:#0b417e;border-radius:9px;padding:12px;font-size:12px;line-height:1.65;margin-bottom:16px}.mh198-summary>p{color:#98aabd;font-size:11px;line-height:1.7;text-align:center;margin:13px 0 0}.mh198-specs{padding:88px 0}.mh198-specs__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.mh198-specs__grid>div{border:1px solid var(--mh198-line);border-radius:15px;padding:25px;background:#fff}.mh198-specs strong{font-size:27px;color:var(--mh198-blue)}.mh198-specs h3{color:var(--mh198-navy);margin:10px 0 7px}.mh198-specs p{color:#6a7c90;font-size:13px;line-height:1.7;margin:0}.mh198-mobile-whatsapp{display:none}
    .mh198-trust{padding:88px 0;background:#f1f5f9;border-top:1px solid var(--mh198-line)}.mh198-trust__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.mh198-trust__grid article{background:#fff;border:1px solid var(--mh198-line);border-radius:15px;padding:25px}.mh198-trust__grid h3{color:var(--mh198-navy);margin:12px 0 8px}.mh198-trust__grid strong{display:block;color:var(--mh198-blue);font-size:14px;margin-bottom:8px}.mh198-trust__grid p{color:#63758a;font-size:13px;line-height:1.8;margin:0}.mh198-trust__icon{display:inline-grid;place-items:center;width:42px;height:42px;border-radius:12px;background:var(--mh198-navy);color:#fff;font-weight:900}.mh198-trust__actions{display:flex;justify-content:center;gap:10px 18px;flex-wrap:wrap;margin-top:24px}.mh198-trust__actions a{color:var(--mh198-blue)!important;font-size:12px;font-weight:900;text-decoration:none!important;border-bottom:1px solid currentColor;padding-bottom:2px}
    @media(max-width:950px){.mh198-hero__grid,.mh198-calculator__grid{grid-template-columns:1fr}.mh198-summary{position:static}.mh198-gallery__grid{grid-template-columns:repeat(2,1fr)}.mh198-trust__grid{grid-template-columns:1fr}}
    @media(max-width:600px){.mh198-shell{width:min(100% - 28px,1180px)}.mh198-hero{padding:50px 0}.mh198-hero h1{font-size:42px}.mh198-hero p{font-size:15px}.mh198-gallery,.mh198-calculator,.mh198-specs,.mh198-trust{padding:60px 0}.mh198-gallery__grid{grid-template-columns:1fr 1fr;gap:10px}.mh198-options--walls,.mh198-options--service{grid-template-columns:1fr}.mh198-specs__grid{grid-template-columns:1fr}.mh198-trust__actions{align-items:stretch;flex-direction:column;text-align:center}.mh198-mobile-whatsapp{display:flex;position:fixed;z-index:9999;left:12px;right:12px;bottom:10px;min-height:54px;align-items:center;justify-content:center;border-radius:10px;background:var(--mh198-green);color:#fff!important;text-decoration:none!important;font-weight:900;box-shadow:0 14px 35px rgba(0,0,0,.28)}body:has(.mh198){padding-bottom:72px}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_design_198_head', 109);

function mh_control_design_198_script(): void {
    if (!mh_control_is_design_198_page()) return;
    ?>
    <script id="mh-design-198-script">
    document.addEventListener('DOMContentLoaded',function(){
        var root=document.querySelector('.mh198');if(!root)return;
        var state={wall:'3 إلى أقل من 3.5 متر',wallMin:3,wallMax:3.49,noInstall:130,withInstall:170,included:'طاولة 2.5 متر + كابينة + 3 ألواح فوم بورد',install:false,custom:false,color:'بيج خشبي'};
        var main=document.getElementById('mh198-main-image'),caption=document.getElementById('mh198-main-caption');
        var wallSummary=document.getElementById('mh198-wall-summary'),wallPrice=document.getElementById('mh198-wall-price'),serviceSummary=document.getElementById('mh198-service-summary'),includedSummary=document.getElementById('mh198-included-summary'),colorSummary=document.getElementById('mh198-color-summary'),total=document.getElementById('mh198-total'),special=document.getElementById('mh198-special');
        var wa=document.getElementById('mh198-whatsapp'),mobileWa=document.getElementById('mh198-mobile-whatsapp');
        function campaign(){try{return JSON.parse(localStorage.getItem('mh_campaign_params')||'{}');}catch(e){return {};}}
        function selectedPrice(){return state.install?state.withInstall:state.noInstall;}
        function serviceLabel(){return state.install?'مع التركيب':'بدون تركيب';}
        function update(){
            var price=selectedPrice(),track=campaign();
            wallSummary.textContent=state.wall;serviceSummary.textContent=serviceLabel();wallPrice.textContent=state.custom?'تسعير خاص':price+' د.ك';includedSummary.textContent=state.included;colorSummary.textContent=state.color;
            total.innerHTML=state.custom?'حسب الطلب':price+' <small>د.ك</small>';special.hidden=!state.custom;
            var lines=['مرحباً ماركوز هوم، أريد الاستفسار عن تصميم 198 — الخشب الهرمي.','اللون: '+state.color,'فئة الحائط: '+state.wall,'طريقة الاستلام: '+serviceLabel(),'تشمل الباقة: '+state.included,'سعر الباقة: '+(state.custom?'يحتاج حساب تكلفة ومراجعة':price+' د.ك'),'الإجمالي المبدئي: '+(state.custom?'تسعير خاص عبر واتساب':price+' د.ك'),'التنفيذ: داخل الكويت','مصدر الإعلان: '+(track.utm_source||'direct'),'وسيط الحملة: '+(track.utm_medium||'—'),'اسم الحملة: '+(track.utm_campaign||'—'),'محتوى الإعلان: '+(track.utm_content||'—'),'كلمة الإعلان: '+(track.utm_term||'—'),'fbclid: '+(track.fbclid||'—')];
            var href='https://wa.me/96550204320?text='+encodeURIComponent(lines.join('\n'));
            var detail='تصميم 198 | '+state.wall+' | '+serviceLabel()+' | '+state.color+' | '+(state.custom?'تسعير خاص':price+' د.ك')+' | '+state.included;
            wa.href=href;mobileWa.href=href;wa.dataset.mhAdDetails=detail;mobileWa.dataset.mhAdDetails=detail;
        }
        function activate(group,button){root.querySelectorAll(group).forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});button.classList.add('is-active');button.setAttribute('aria-pressed','true');}
        root.querySelectorAll('[data-mh198-wall]').forEach(function(button){button.addEventListener('click',function(){activate('[data-mh198-wall]',button);state.wall=button.dataset.mh198Wall;state.wallMin=Number(button.dataset.mh198WallMin);state.wallMax=Number(button.dataset.mh198WallMax);state.noInstall=Number(button.dataset.mh198NoInstall);state.withInstall=Number(button.dataset.mh198WithInstall);state.included=button.dataset.mh198Included;state.custom=button.dataset.mh198Custom==='1';update();});});
        root.querySelectorAll('[data-mh198-install]').forEach(function(button){button.addEventListener('click',function(){activate('[data-mh198-install]',button);state.install=button.dataset.mh198Install==='1';update();});});
        root.querySelectorAll('[data-mh198-color-choice]').forEach(function(button){button.addEventListener('click',function(){activate('[data-mh198-color-choice]',button);state.color=button.dataset.mh198ColorChoice;main.src=button.dataset.mh198ColorImage;main.alt='تصميم 198 الخشب الهرمي باللون '+state.color;caption.textContent=state.color;root.querySelectorAll('[data-mh198-color]').forEach(function(b){b.classList.toggle('is-active',b.dataset.mh198Color===state.color);b.setAttribute('aria-pressed',b.dataset.mh198Color===state.color?'true':'false');});update();});});
        root.querySelectorAll('[data-mh198-color]').forEach(function(button){button.addEventListener('click',function(){activate('[data-mh198-color]',button);main.src=button.dataset.mh198Image;main.alt=button.querySelector('img').alt;caption.textContent=button.dataset.mh198Color;if(button.dataset.mh198Color!=='المقاسات'){state.color=button.dataset.mh198Color;root.querySelectorAll('[data-mh198-color-choice]').forEach(function(b){b.classList.toggle('is-active',b.dataset.mh198ColorChoice===state.color);b.setAttribute('aria-pressed',b.dataset.mh198ColorChoice===state.color?'true':'false');});update();}});});
        [wa,mobileWa].forEach(function(link){link.addEventListener('click',function(){if(typeof window.fbq==='function')window.fbq('track','Contact',{content_ids:['design-198'],content_name:'Design 198 Pyramid Wood'});if(typeof window.snaptr==='function')window.snaptr('track','START_CHECKOUT',{item_ids:['design-198'],item_category:'TV Wall Design',price:state.custom?0:selectedPrice(),currency:'KWD'});});});
        update();
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_design_198_script', 105);


/**
 * TV wall backgrounds and integrated decor — roadmap step 2, product 6 of 6.
 */
function mh_control_is_tv_wall_archive(): bool {
    return function_exists('is_product_category') && is_product_category() && get_queried_object_id() === 50;
}

function mh_control_tv_wall_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=';
    $initial = rawurlencode('مرحباً ماركوز هوم، أريد تصميم خلفية شاشة. عرض الحائط 4 م وارتفاعه 2.90 م. أفضّل تصميم 130، طاولة 2 م، لون خشبي، ولوح بدون فواصل. أريد تأكيد التصميم والسعر والمعاينة.');
    ob_start();
    ?>
    <main class="mh-tvwall" dir="rtl">
        <section class="mhtw-hero">
            <div class="mhtw-hero__shade"></div>
            <div class="mhtw-shell mhtw-hero__content">
                <span class="mhtw-eyebrow">تصميم وتوريد وتركيب داخل الكويت</span>
                <h1>خلفية شاشة تخفي الأسلاك<br>وتغيّر شكل الصالة</h1>
                <p>تصميم متكامل بلوح شاشة، طاولة معلقة وتشطيبات دافئة تناسب مساحة الحائط وذوق البيت.</p>
                <div class="mhtw-hero__price"><small>العرض يبدأ من</small><strong>98 <em>د.ك</em></strong><span>شامل التوريد والتركيب</span></div>
                <a class="mhtw-btn mhtw-btn--green" href="#mhtw-order">كوّن تصميمك الآن</a>
            </div>
        </section>

        <section class="mhtw-trust"><div class="mhtw-shell mhtw-trust__grid">
            <div><b>إخفاء الأسلاك</b><span>تمديد مرتب خلف لوح الشاشة</span></div>
            <div><b>مقاس مخصص</b><span>التصميم يتظبط على مساحة الحائط</span></div>
            <div><b>توريد وتركيب</b><span>تنفيذ احترافي وتسليم جاهز</span></div>
        </div></section>

        <section class="mhtw-builder" id="mhtw-order">
            <div class="mhtw-shell mhtw-builder__grid">
                <div class="mhtw-builder__form">
                    <span class="mhtw-eyebrow mhtw-eyebrow--blue">طلب مبدئي في دقيقة</span>
                    <h2>اختار تفاصيل الخلفية</h2>
                    <div class="mhtw-measures">
                        <label><span>عرض الحائط بالمتر</span><input id="mhtw-width" type="number" min="1.5" max="12" step="0.1" value="4" inputmode="decimal"></label>
                        <label><span>ارتفاع الحائط بالمتر</span><input id="mhtw-height" type="number" min="2" max="5" step="0.1" value="2.9" inputmode="decimal"></label>
                    </div>

                    <fieldset><legend>شكل التصميم</legend><div class="mhtw-options">
                        <button type="button" class="mhtw-option is-active" data-mhtw-design="تصميم 130" aria-pressed="true"><b>تصميم 130</b><small>لوح شاشة + طاولة + لوح رأسي</small></button>
                        <button type="button" class="mhtw-option" data-mhtw-design="تصميم حائط متكامل" aria-pressed="false"><b>حائط متكامل</b><small>تكوين ممتد يناسب الحوائط الكبيرة</small></button>
                        <button type="button" class="mhtw-option" data-mhtw-design="تصميم مع أرفف" aria-pressed="false"><b>مع أرفف</b><small>أرفف جانبية وإضاءة دافئة</small></button>
                    </div></fieldset>

                    <fieldset><legend>عرض الطاولة المعلقة</legend><div class="mhtw-chips">
                        <button type="button" data-mhtw-console="1.5 متر" aria-pressed="false">1.5 متر</button>
                        <button type="button" class="is-active" data-mhtw-console="2 متر" aria-pressed="true">2 متر</button>
                        <button type="button" data-mhtw-console="3 متر" aria-pressed="false">3 متر</button>
                    </div></fieldset>

                    <fieldset><legend>تفاصيل لوح الشاشة</legend><div class="mhtw-chips">
                        <button type="button" class="is-active" data-mhtw-panel="بدون فواصل" aria-pressed="true">بدون فواصل</button>
                        <button type="button" data-mhtw-panel="فاصلان رأسيان" aria-pressed="false">فاصلان رأسيان</button>
                    </div></fieldset>

                    <fieldset><legend>لون الطاولة</legend><div class="mhtw-colors">
                        <button type="button" class="is-active" data-mhtw-color="خشبي" aria-pressed="true"><i style="--sw:#9a6c45"></i>خشبي</button>
                        <button type="button" data-mhtw-color="أبيض" aria-pressed="false"><i style="--sw:#f2f2ed"></i>أبيض</button>
                        <button type="button" data-mhtw-color="رمادي" aria-pressed="false"><i style="--sw:#8e9399"></i>رمادي</button>
                        <button type="button" data-mhtw-color="فحمي" aria-pressed="false"><i style="--sw:#34383d"></i>فحمي</button>
                    </div></fieldset>
                </div>

                <aside class="mhtw-summary">
                    <span>العرض المبدئي يبدأ من</span>
                    <strong>98 <small>د.ك</small></strong>
                    <em>شامل التوريد والتركيب</em>
                    <ul>
                        <li><span>الحائط</span><b id="mhtw-wall-summary">4 × 2.9 م</b></li>
                        <li><span>التصميم</span><b id="mhtw-design-summary">تصميم 130</b></li>
                        <li><span>الطاولة</span><b id="mhtw-console-summary">2 متر</b></li>
                        <li><span>اللوح</span><b id="mhtw-panel-summary">بدون فواصل</b></li>
                        <li><span>اللون</span><b id="mhtw-color-summary">خشبي</b></li>
                    </ul>
                    <a id="mhtw-whatsapp" class="mhtw-btn mhtw-btn--green mhtw-btn--full" href="<?php echo esc_url($whatsapp . $initial); ?>" target="_blank" rel="noopener">أرسل التفاصيل على واتساب</a>
                    <p>السعر النهائي يُؤكد بعد مراجعة صورة الحائط والمقاس واختيار الخامات.</p>
                </aside>
            </div>
        </section>

        <section class="mhtw-model">
            <div class="mhtw-shell mhtw-model__grid">
                <div><span class="mhtw-eyebrow mhtw-eyebrow--blue">النموذج الأساسي 130</span><h2>تكوين متوازن للصالة</h2><p>لوح شاشة بإضاءة خلفية خفيفة مع طاولة معلقة وعُنصر رأسي من السقف إلى الأرض، بتفاصيل قابلة للتخصيص.</p>
                    <ul><li><b>لوح الشاشة:</b> 2 × 1.20 م</li><li><b>الطاولة:</b> عرض 2 م، أربعة أبواب متساوية</li><li><b>اللوح الرأسي:</b> 2.90 × 1.22 م</li><li><b>التخصيص:</b> بدون فواصل أو بفاصلين رأسيين</li></ul>
                </div>
                <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/Generated-Image-November-04-2025-9_02PM-580x387.png" alt="تصميم خلفية شاشة متكامل من ماركوز هوم" loading="lazy"></figure>
            </div>
        </section>

        <section class="mhtw-gallery-section">
            <div class="mhtw-shell">
                <div class="mhtw-heading"><span class="mhtw-eyebrow mhtw-eyebrow--blue">تصميمات وأفكار</span><h2>اختار الشكل الأقرب لمساحتك</h2><p>أرسل لنا صورة الحائط وسنرشح لك التكوين والمقاسات الأنسب.</p></div>
                <a class="mhtw-featured-198" href="<?php echo esc_url(home_url('/design-198/')); ?>">
                    <img src="<?php echo esc_url(mh_control_design_198_asset('design-198-beige-wood.webp')); ?>" alt="تصميم 198 الخشب الهرمي باللون البيج الخشبي" loading="lazy">
                    <span><small>جديد — حاسبة سعر فورية</small><strong>تصميم 198 — الخشب الهرمي</strong><em>يبدأ من 130 د.ك بدون تركيب أو 170 د.ك مع التركيب</em><b>شاهد التصميم واحسب السعر</b></span>
                </a>
                <div class="mhtw-gallery">
                    <figure class="mhtw-gallery__tall"><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0012-580x879.jpg" alt="خلفية شاشة خشبية مع طاولة معلقة" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/Generated-Image-November-04-2025-9_02PM-580x387.png" alt="تصميم خلفية شاشة مودرن" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011-580x866.jpg" alt="خلفية شاشة بألوان محايدة" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0014-1-580x869.jpg" alt="تصميم حائط تلفزيون من ماركوز هوم" loading="lazy"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/11/Generated-Image-November-02-2025-4_58PM-580x657.png" alt="خلفية شاشة مع ديكور متكامل" loading="lazy"></figure>
                </div>
            </div>
        </section>

        <section class="mhtw-steps"><div class="mhtw-shell"><div class="mhtw-heading mhtw-heading--light"><span class="mhtw-eyebrow">من الصورة إلى التنفيذ</span><h2>ثلاث خطوات واضحة</h2></div><div class="mhtw-steps__grid">
            <div><b>01</b><h3>أرسل صورة الحائط</h3><p>مع العرض والارتفاع ومكان الشاشة.</p></div>
            <div><b>02</b><h3>اعتمد التصميم</h3><p>نراجع المقاس واللون وتفاصيل الطاولة.</p></div>
            <div><b>03</b><h3>التوريد والتركيب</h3><p>تنفيذ مرتب مع إخفاء الأسلاك والتشطيب.</p></div>
        </div></div></section>

        <section class="mhtw-cta"><div class="mhtw-shell mhtw-cta__box"><div><span class="mhtw-eyebrow mhtw-eyebrow--blue">جاهز تغيّر شكل الصالة؟</span><h2>أرسل صورة الحائط وخد اقتراح مناسب</h2><p>ابدأ من 98 د.ك شامل التوريد والتركيب.</p></div><a class="mhtw-btn mhtw-btn--dark" href="#mhtw-order">ابدأ الاختيار</a></div></section>
        <a class="mhtw-mobile-order" href="#mhtw-order">كوّن خلفية الشاشة — تبدأ من 98 د.ك</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_tv_wall_archive(): void {
    if (!mh_control_is_tv_wall_archive()) return;
    status_header(200); get_header();
    echo mh_control_tv_wall_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer(); exit;
}
add_action('template_redirect', 'mh_control_render_tv_wall_archive', 34);

function mh_control_tv_wall_title(string $title): string {
    return mh_control_is_tv_wall_archive() ? 'خلفيات شاشة وديكور متكامل في الكويت | ماركوز هوم' : $title;
}
add_filter('pre_get_document_title', 'mh_control_tv_wall_title', 124);

function mh_control_tv_wall_description(string $description): string {
    return mh_control_is_tv_wall_archive() ? 'خلفيات شاشة مخصصة مع طاولة معلقة وإخفاء أسلاك. العرض يبدأ من 98 د.ك شامل التوريد والتركيب داخل الكويت.' : $description;
}
add_filter('aioseo_title', 'mh_control_tv_wall_title', 1240);
add_filter('aioseo_description', 'mh_control_tv_wall_description', 1240);
add_filter('wpseo_title', 'mh_control_tv_wall_title', 1240);
add_filter('wpseo_metadesc', 'mh_control_tv_wall_description', 1240);
add_filter('rank_math/frontend/title', 'mh_control_tv_wall_title', 1240);
add_filter('rank_math/frontend/description', 'mh_control_tv_wall_description', 1240);

function mh_control_tv_wall_head(): void {
    if (!mh_control_is_tv_wall_archive()) return;
    ?>
    <meta name="description" content="خلفيات شاشة مخصصة مع طاولة معلقة وإخفاء أسلاك. يبدأ من 98 د.ك شامل التوريد والتركيب في الكويت.">
    <style id="mh-tv-wall-styles">
    :root{--mhtw-blue:#1266d6;--mhtw-navy:#071a33;--mhtw-ink:#15263a;--mhtw-soft:#f2f6fa;--mhtw-gold:#d6aa62;--mhtw-green:#20b95a}
    html:has(.mh-tvwall),body:has(.mh-tvwall){overflow-x:clip}.mh-tvwall{font-family:Tahoma,Arial,sans-serif;color:var(--mhtw-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-tvwall *{box-sizing:border-box}.mhtw-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhtw-hero{min-height:670px;display:flex;align-items:center;position:relative;background:url('https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0109-580x387.jpg') center/cover no-repeat}.mhtw-hero__shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(5,17,34,.24),rgba(5,17,34,.91))}.mhtw-hero__content{position:relative;z-index:1;color:#fff;padding-block:90px}.mhtw-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#d9e8fa;font-size:14px;font-weight:800;margin-bottom:15px}.mhtw-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhtw-gold)}.mhtw-eyebrow--blue{color:var(--mhtw-blue)}.mhtw-hero h1{font-size:clamp(43px,6vw,73px);line-height:1.12;color:#fff;margin:0 0 20px;font-weight:900}.mhtw-hero p{max-width:680px;font-size:18px;line-height:1.9;color:#e7eff8;margin:0 0 22px}.mhtw-hero__price{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 28px}.mhtw-hero__price small,.mhtw-hero__price span{font-size:13px;color:#dce8f5}.mhtw-hero__price strong{font-size:48px;line-height:1;color:#fff}.mhtw-hero__price em{font-style:normal;font-size:16px}
    .mhtw-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 23px;border-radius:8px;text-decoration:none!important;font-weight:900;transition:.2s}.mhtw-btn:hover{transform:translateY(-2px)}.mhtw-btn--green{background:var(--mhtw-green);color:#fff!important}.mhtw-btn--dark{background:var(--mhtw-navy);color:#fff!important}.mhtw-btn--full{width:100%}
    .mhtw-trust{background:var(--mhtw-navy);color:#fff}.mhtw-trust__grid{display:grid;grid-template-columns:repeat(3,1fr)}.mhtw-trust__grid>div{padding:25px 32px;border-inline-start:1px solid rgba(255,255,255,.13)}.mhtw-trust b,.mhtw-trust span{display:block}.mhtw-trust span{font-size:12px;color:#aebed0;margin-top:5px}
    .mhtw-builder{padding:88px 0}.mhtw-builder__grid{display:grid;grid-template-columns:1.22fr .78fr;gap:38px;align-items:start}.mhtw-builder h2,.mhtw-heading h2,.mhtw-model h2{font-size:clamp(34px,5vw,52px);color:var(--mhtw-navy);margin:0 0 30px}.mhtw-measures{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:27px}.mhtw-measures label span{display:block;font-size:13px;font-weight:800;margin-bottom:8px}.mhtw-measures input{width:100%;height:57px;border:1px solid #dbe3eb;border-radius:11px;padding:0 15px;font:900 18px Tahoma,Arial;color:var(--mhtw-navy)}.mhtw-builder fieldset{border:0;padding:0;margin:0 0 27px}.mhtw-builder legend{font-size:16px;font-weight:900;color:var(--mhtw-navy);margin-bottom:12px}.mhtw-options{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.mhtw-option,.mhtw-chips button,.mhtw-colors button{font:inherit;border:1px solid #dce4ec;background:#fff;border-radius:11px;cursor:pointer;color:#43566a}.mhtw-option{padding:17px 13px;text-align:right}.mhtw-option b,.mhtw-option small{display:block}.mhtw-option small{font-size:11px;line-height:1.6;color:#718195;margin-top:5px}.mhtw-chips,.mhtw-colors{display:flex;gap:9px;flex-wrap:wrap}.mhtw-chips button{padding:11px 17px;font-weight:800}.mhtw-colors button{padding:9px 14px;display:flex;align-items:center;gap:7px;font-weight:800}.mhtw-colors i{width:23px;height:23px;border-radius:50%;background:var(--sw);border:1px solid rgba(0,0,0,.15)}.mhtw-option.is-active,.mhtw-chips button.is-active,.mhtw-colors button.is-active{outline:2px solid var(--mhtw-blue);outline-offset:1px;background:#f5f9ff}
    .mhtw-summary{position:sticky;top:25px;border-radius:18px;padding:31px;background:var(--mhtw-navy);color:#fff;box-shadow:0 20px 45px rgba(7,26,51,.18)}.mhtw-summary>span{color:#acbdcf;font-size:13px}.mhtw-summary>strong{display:block;font-size:61px;line-height:1;margin:12px 0 7px}.mhtw-summary>strong small{font-size:17px}.mhtw-summary>em{display:block;font-style:normal;color:#d6e2ef;font-size:12px;margin-bottom:22px}.mhtw-summary ul{list-style:none;padding:0;margin:0 0 23px;border-block:1px solid rgba(255,255,255,.13)}.mhtw-summary li{display:flex;justify-content:space-between;gap:14px;padding:11px 0;color:#aebed0;font-size:12px}.mhtw-summary li+li{border-top:1px solid rgba(255,255,255,.09)}.mhtw-summary li b{color:#fff}.mhtw-summary>p{text-align:center;color:#98aabd;font-size:11px;line-height:1.6;margin:13px 0 0}
    .mhtw-model{padding:88px 0;background:var(--mhtw-soft)}.mhtw-model__grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:45px;align-items:center}.mhtw-model p{color:#687a8d;line-height:1.9}.mhtw-model ul{list-style:none;padding:0;margin:25px 0 0}.mhtw-model li{padding:11px 0;border-bottom:1px solid #d9e2ea;color:#526478}.mhtw-model figure{margin:0;border-radius:18px;overflow:hidden;box-shadow:0 20px 55px rgba(7,26,51,.13)}.mhtw-model img{width:100%;height:440px;object-fit:cover;display:block}
    .mhtw-featured-198{display:grid;grid-template-columns:1.15fr .85fr;align-items:center;gap:0;overflow:hidden;margin:0 0 25px;border:2px solid var(--mhtw-blue);border-radius:18px;background:#f5f9ff;text-decoration:none!important}.mhtw-featured-198>img{width:100%;height:390px;object-fit:cover;display:block}.mhtw-featured-198>span{display:block;padding:35px}.mhtw-featured-198 small,.mhtw-featured-198 strong,.mhtw-featured-198 em,.mhtw-featured-198 b{display:block}.mhtw-featured-198 small{color:var(--mhtw-blue);font-weight:900}.mhtw-featured-198 strong{font-size:30px;line-height:1.3;color:var(--mhtw-navy);margin:10px 0}.mhtw-featured-198 em{font-style:normal;color:#66788c;line-height:1.7}.mhtw-featured-198 b{margin-top:20px;color:var(--mhtw-blue)}
    .mhtw-gallery-section{padding:90px 0}.mhtw-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mhtw-heading p{color:#68798e;line-height:1.8}.mhtw-gallery{display:grid;grid-template-columns:repeat(3,1fr);grid-auto-rows:285px;gap:15px}.mhtw-gallery figure{margin:0;border-radius:15px;overflow:hidden;background:#eee}.mhtw-gallery__tall{grid-row:span 2}.mhtw-gallery img{width:100%;height:100%;object-fit:cover;display:block;transition:.4s}.mhtw-gallery figure:hover img{transform:scale(1.03)}
    .mhtw-steps{padding:88px 0;background:var(--mhtw-navy);color:#fff}.mhtw-heading--light h2{color:#fff}.mhtw-steps__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.mhtw-steps__grid>div{padding:28px;border:1px solid rgba(255,255,255,.14);border-radius:14px;background:rgba(255,255,255,.04)}.mhtw-steps__grid b{color:var(--mhtw-gold)}.mhtw-steps__grid h3{color:#fff;font-size:20px;margin:13px 0 8px}.mhtw-steps__grid p{margin:0;color:#aebed0}.mhtw-cta{padding:70px 0;background:#eaf1f7}.mhtw-cta__box{display:flex;align-items:center;justify-content:space-between;gap:28px;background:#fff;padding:44px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhtw-cta h2{font-size:clamp(29px,4vw,43px);margin:0 0 10px;color:var(--mhtw-navy)}.mhtw-cta p{margin:0;color:#68798e}.mhtw-mobile-order{display:none}
    @media(max-width:900px){.mhtw-builder__grid,.mhtw-model__grid,.mhtw-featured-198{grid-template-columns:1fr}.mhtw-summary{position:static}.mhtw-options{grid-template-columns:1fr}.mhtw-steps__grid{grid-template-columns:1fr}.mhtw-cta__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mhtw-shell{width:min(100% - 28px,1180px)}.mhtw-hero{min-height:610px;background-position:35% center}.mhtw-hero__content{padding-block:60px}.mhtw-hero h1{font-size:41px}.mhtw-builder,.mhtw-model,.mhtw-gallery-section,.mhtw-steps{padding:64px 0}.mhtw-measures{grid-template-columns:1fr}.mhtw-gallery{grid-template-columns:1fr;grid-auto-rows:290px}.mhtw-gallery__tall{grid-row:auto}.mhtw-model img{height:330px}.mhtw-trust__grid{grid-template-columns:1fr}.mhtw-trust__grid>div{border-inline-start:0;border-bottom:1px solid rgba(255,255,255,.11);padding:18px}.mhtw-cta{padding:48px 0 90px}.mhtw-cta__box{padding:30px 24px}.mhtw-mobile-order{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mhtw-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_tv_wall_head', 105);

function mh_control_tv_wall_script(): void {
    if (!mh_control_is_tv_wall_archive()) return;
    ?>
    <script id="mh-tv-wall-builder">
    document.addEventListener('DOMContentLoaded',function(){
        var state={design:'تصميم 130',console:'2 متر',panel:'بدون فواصل',color:'خشبي'};
        var width=document.getElementById('mhtw-width'),height=document.getElementById('mhtw-height');
        function clean(v,fallback){v=parseFloat(v);return isFinite(v)&&v>0?v:fallback;}
        function fmt(v){return Number.isInteger(v)?String(v):v.toFixed(1);}
        function choose(selector,key){
            document.querySelectorAll(selector).forEach(function(button){button.addEventListener('click',function(){
                document.querySelectorAll(selector).forEach(function(b){b.classList.remove('is-active');b.setAttribute('aria-pressed','false');});
                button.classList.add('is-active');button.setAttribute('aria-pressed','true');state[key]=button.dataset['mhtw'+key.charAt(0).toUpperCase()+key.slice(1)];update();
            });});
        }
        function update(){
            var w=clean(width.value,4),h=clean(height.value,2.9);
            document.getElementById('mhtw-wall-summary').textContent=fmt(w)+' × '+fmt(h)+' م';
            document.getElementById('mhtw-design-summary').textContent=state.design;
            document.getElementById('mhtw-console-summary').textContent=state.console;
            document.getElementById('mhtw-panel-summary').textContent=state.panel;
            document.getElementById('mhtw-color-summary').textContent=state.color;
            var msg='مرحباً ماركوز هوم، أريد تصميم خلفية شاشة. عرض الحائط '+fmt(w)+' م وارتفاعه '+fmt(h)+' م. أفضّل '+state.design+'، طاولة '+state.console+'، لون '+state.color+'، ولوح '+state.panel+'. أريد تأكيد التصميم والسعر والمعاينة.';
            document.getElementById('mhtw-whatsapp').href='https://wa.me/96550204320?text='+encodeURIComponent(msg);
        }
        width.addEventListener('input',update);height.addEventListener('input',update);
        choose('[data-mhtw-design]','design');choose('[data-mhtw-console]','console');choose('[data-mhtw-panel]','panel');choose('[data-mhtw-color]','color');update();
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_tv_wall_script', 104);


function mh_control_purge_tv_wall_cache_once(): void {
    if (get_option('mh_tv_wall_cache_version') === '0.9.1') return;
    $url = home_url('/product-category/%d9%86%d9%85%d8%a7%d8%b0%d8%ac-%d9%88%d8%aa%d8%b5%d9%85%d9%8a%d9%85%d8%a7%d8%aa/');
    if (has_action('litespeed_purge_url')) {
        do_action('litespeed_purge_url', $url);
    }
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    update_option('mh_tv_wall_cache_version', '0.9.1', false);
}
add_action('init', 'mh_control_purge_tv_wall_cache_once', 99);


/**
 * Arabic About page — roadmap step 3.
 * Replaces the imported demo content without modifying the original Elementor document.
 */
function mh_control_is_about_page(): bool {
    return is_page(6142) || is_page('about');
}

function mh_control_about_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=' . rawurlencode('مرحباً ماركوز هوم، شاهدت صفحة عن الشركة وأريد مناقشة تصميم مناسب لمساحتي. هذه صورة المكان:');
    ob_start();
    ?>
    <main class="mh-about" dir="rtl">
        <section class="mhab-hero">
            <div class="mhab-hero__shade"></div>
            <div class="mhab-shell mhab-hero__content">
                <span class="mhab-eyebrow">ماركوز هوم — الكويت</span>
                <h1>نصمم الحائط<br>حول حياتك</h1>
                <p>حلول ديكور داخلية عملية ومخصصة تجمع بين الشكل الهادئ، المقاس المناسب والتنفيذ المرتب.</p>
                <div class="mhab-actions">
                    <a class="mhab-btn mhab-btn--green" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">أرسل صورة المكان</a>
                    <a class="mhab-btn mhab-btn--ghost" href="https://marcohom.com/portfolio/">شاهد أعمالنا</a>
                </div>
            </div>
        </section>

        <section class="mhab-intro">
            <div class="mhab-shell mhab-intro__grid">
                <div>
                    <span class="mhab-eyebrow mhab-eyebrow--blue">من نحن</span>
                    <h2>ماركوز هوم للديكور الداخلي وحلول الحوائط</h2>
                    <p>نعمل في الكويت على تصميم وتنفيذ تفاصيل البيت التي تغيّر شكل المساحة وتسهّل استخدامها: من خلفيات الشاشة والطاولات المعلقة إلى أركان القهوة والباركيه والفواصل والديكور المخصص.</p>
                    <p>نبدأ بصورة المكان والمقاس، نراجع احتياج العميل، ثم نرشح الشكل والخامة واللون المناسبين قبل التوريد والتركيب.</p>
                    <div class="mhab-intro__points"><span>تصميم حسب المقاس</span><span>خامات وألوان متعددة</span><span>توريد وتركيب</span></div>
                </div>
                <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg" alt="تصميم داخلي من أعمال ماركوز هوم في الكويت"></figure>
            </div>
        </section>

        <section class="mhab-services">
            <div class="mhab-shell">
                <div class="mhab-heading"><span class="mhab-eyebrow mhab-eyebrow--blue">ما الذي نقدمه؟</span><h2>حلول متكاملة لمساحات البيت</h2><p>كل خدمة قابلة للتخصيص حسب المقاس واللون وطبيعة المكان.</p></div>
                <div class="mhab-services__grid">
                    <a href="https://marcohom.com/product-category/%d9%86%d9%85%d8%a7%d8%b0%d8%ac-%d9%88%d8%aa%d8%b5%d9%85%d9%8a%d9%85%d8%a7%d8%aa/?service=tv-wall"><span>01</span><h3>خلفيات الشاشة</h3><p>تصميمات متكاملة مع طاولة معلقة وإخفاء الأسلاك.</p></a>
                    <a href="https://marcohom.com/coffee-corner/"><span>02</span><h3>أركان القهوة</h3><p>حلول جاهزة ومخصصة لتنظيم ركن القهوة.</p></a>
                    <a href="https://marcohom.com/tv-tables/"><span>03</span><h3>طاولات TV</h3><p>مقاسات وألوان متعددة مع تركيب اختياري.</p></a>
                    <a href="https://marcohom.com/product/%d8%a8%d8%a7%d8%b1%d9%83%d9%8a%d8%a9-%d8%ae%d8%b4%d8%a8-k9188/"><span>04</span><h3>أرضيات الباركيه</h3><p>درجات خشبية دافئة وقياس للكمية المطلوبة.</p></a>
                    <a href="https://marcohom.com/product/%d9%82%d8%a7%d8%b7%d8%b9-%d8%a7%d9%84%d8%a7%d8%b9%d9%85%d8%af%d8%a9/"><span>05</span><h3>فواصل بديل الخشب</h3><p>فصل أنيق للمساحات بأعمدة WPC.</p></a>
                    <a href="https://marcohom.com/product/%d8%a7%d9%84%d9%81%d9%8a%d8%b1-%d8%a7%d9%84%d9%85%d8%b9%d8%b7%d8%b1/"><span>06</span><h3>جهاز الفير المعطر</h3><p>ديكور بخار مائي بمقاسات متعددة.</p></a>
                </div>
            </div>
        </section>

        <section class="mhab-philosophy" id="mhab-philosophy">
            <div class="mhab-shell mhab-philosophy__grid">
                <div class="mhab-philosophy__image"><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0109-580x387.jpg" alt="خلفية شاشة وطاولة معلقة من ماركوز هوم"></div>
                <div>
                    <span class="mhab-eyebrow">طريقتنا</span>
                    <h2>التصميم الجميل يبدأ من فهم المكان</h2>
                    <p>لا نبدأ باختيار شكل فقط. نراجع أبعاد الحائط، استخدام المساحة، أماكن الكهرباء والألوان الموجودة، ثم نرتب التفاصيل لتخرج النتيجة متناسقة وعملية.</p>
                    <ul><li><b>وضوح قبل التنفيذ</b><span>المقاس والخامة والتفاصيل تُراجع مع العميل.</span></li><li><b>اختيارات مناسبة</b><span>ألوان وتكوينات تناسب الأثاث والمساحة.</span></li><li><b>تنفيذ مرتب</b><span>توريد وتركيب وتشطيب نهائي جاهز للاستخدام.</span></li></ul>
                </div>
            </div>
        </section>

        <section class="mhab-process">
            <div class="mhab-shell">
                <div class="mhab-heading"><span class="mhab-eyebrow mhab-eyebrow--blue">كيف نبدأ؟</span><h2>من صورة المكان إلى التسليم</h2></div>
                <div class="mhab-process__grid">
                    <div><b>01</b><h3>صورة ومقاس</h3><p>أرسل صورة واضحة وعرض وارتفاع المساحة عبر واتساب.</p></div>
                    <div><b>02</b><h3>اختيار الحل</h3><p>نحدد الخدمة والتصميم والخامة واللون الأنسب.</p></div>
                    <div><b>03</b><h3>تأكيد التفاصيل</h3><p>نراجع المقاس والسعر ومتطلبات التركيب.</p></div>
                    <div><b>04</b><h3>التوريد والتركيب</h3><p>تنفيذ وتشطيب وتسليم المساحة جاهزة.</p></div>
                </div>
            </div>
        </section>

        <section class="mhab-work" id="mhab-work">
            <div class="mhab-shell">
                <div class="mhab-heading mhab-heading--light"><span class="mhab-eyebrow">نماذج من أعمالنا</span><h2>تفاصيل دافئة لمساحات عصرية</h2></div>
                <div class="mhab-work__grid">
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0012-580x879.jpg" alt="خلفية شاشة خشبية من ماركوز هوم"></figure>
                    <figure><img src="https://coffee.marcohom.com/coffee/brown-travertine.webp" alt="ركن قهوة من ماركوز هوم"></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/12/Gemini_Generated_Image_4g3pw4g3pw4g3pw4-Copy.jpg" alt="أرضية باركيه من ماركوز هوم"></figure>
                </div>
                <a class="mhab-text-link" href="https://marcohom.com/portfolio/">شاهد معرض الأعمال بالكامل ←</a>
            </div>
        </section>

        <section class="mhab-contact">
            <div class="mhab-shell mhab-contact__box">
                <div><span class="mhab-eyebrow mhab-eyebrow--blue">ابدأ معنا</span><h2>أرسل صورة المساحة وفكرتك</h2><p>نتواصل معك لمراجعة المقاس والتصميم والخامة المناسبة.</p></div>
                <div class="mhab-contact__info">
                    <span><small>واتساب</small><b dir="ltr">+965 5020 4320</b></span>
                    <span><small>الموقع</small><b>حولي — شارع نادي القادسية</b></span>
                    <a class="mhab-btn mhab-btn--green" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">تواصل على واتساب</a>
                </div>
            </div>
        </section>
        <a class="mhab-mobile-wa" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">تواصل مع ماركوز هوم</a>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_about_page(): void {
    if (!mh_control_is_about_page()) return;
    status_header(200); get_header();
    echo mh_control_about_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer(); exit;
}
add_action('template_redirect', 'mh_control_render_about_page', 35);

function mh_control_about_title(string $title): string {
    return mh_control_is_about_page() ? 'عن ماركوز هوم | ديكور داخلي وحلول حوائط في الكويت' : $title;
}
add_filter('pre_get_document_title', 'mh_control_about_title', 125);

function mh_control_about_description(string $description): string {
    return mh_control_is_about_page() ? 'تعرف على ماركوز هوم لخلفيات الشاشة وأركان القهوة والباركيه وطاولات التلفزيون وفواصل بديل الخشب والديكور الداخلي في الكويت.' : $description;
}
add_filter('aioseo_title', 'mh_control_about_title', 1250);
add_filter('aioseo_description', 'mh_control_about_description', 1250);
add_filter('wpseo_title', 'mh_control_about_title', 1250);
add_filter('wpseo_metadesc', 'mh_control_about_description', 1250);
add_filter('rank_math/frontend/title', 'mh_control_about_title', 1250);
add_filter('rank_math/frontend/description', 'mh_control_about_description', 1250);

function mh_control_add_about_menu_link(string $items, $args): string {
    if (is_admin() || strpos($items, 'company=marcos-home') !== false || strpos($items, '/portfolio/') === false) return $items;
    $items .= '<li class="menu-item mh-about-menu-item"><a href="' . esc_url(home_url('/about/?company=marcos-home')) . '">عن ماركوز هوم</a></li>';
    return $items;
}
add_filter('wp_nav_menu_items', 'mh_control_add_about_menu_link', 40, 2);

function mh_control_about_head(): void {
    if (!mh_control_is_about_page()) return;
    ?>
    <meta name="description" content="ماركوز هوم للديكور الداخلي وحلول الحوائط وخلفيات الشاشة والباركيه وأركان القهوة في الكويت.">
    <style id="mh-about-styles">
    :root{--mhab-blue:#1266d6;--mhab-navy:#071a33;--mhab-ink:#15263a;--mhab-soft:#f2f6fa;--mhab-gold:#d6aa62;--mhab-green:#20b95a}
    html:has(.mh-about),body:has(.mh-about){overflow-x:clip}.mh-about{font-family:Tahoma,Arial,sans-serif;color:var(--mhab-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mh-about *{box-sizing:border-box}.mhab-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
    .mhab-hero{min-height:650px;display:flex;align-items:center;position:relative;background:url('https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg') center/cover no-repeat}.mhab-hero__shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(5,17,34,.2),rgba(5,17,34,.9))}.mhab-hero__content{position:relative;z-index:1;color:#fff;padding-block:90px}.mhab-eyebrow{display:inline-flex;align-items:center;gap:10px;color:#d8e7f8;font-size:14px;font-weight:800;margin-bottom:15px}.mhab-eyebrow:before{content:"";width:32px;height:2px;background:var(--mhab-gold)}.mhab-eyebrow--blue{color:var(--mhab-blue)}.mhab-hero h1{font-size:clamp(46px,7vw,78px);line-height:1.08;color:#fff;margin:0 0 22px;font-weight:900}.mhab-hero p{font-size:19px;line-height:1.9;color:#e7eff8;max-width:670px;margin:0 0 30px}.mhab-actions{display:flex;gap:12px;flex-wrap:wrap}
    .mhab-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 23px;border-radius:8px;text-decoration:none!important;font-weight:900;transition:.2s}.mhab-btn:hover{transform:translateY(-2px)}.mhab-btn--green{background:var(--mhab-green);color:#fff!important}.mhab-btn--ghost{border:1px solid rgba(255,255,255,.65);background:rgba(255,255,255,.08);color:#fff!important}
    .mhab-intro{padding:92px 0}.mhab-intro__grid{display:grid;grid-template-columns:.88fr 1.12fr;gap:55px;align-items:center}.mhab-intro h2,.mhab-heading h2,.mhab-philosophy h2,.mhab-contact h2{font-size:clamp(34px,5vw,52px);line-height:1.2;color:var(--mhab-navy);margin:0 0 24px}.mhab-intro p,.mhab-philosophy p{color:#617286;line-height:1.9;font-size:16px}.mhab-intro figure{margin:0;border-radius:19px;overflow:hidden;box-shadow:0 22px 58px rgba(7,26,51,.14)}.mhab-intro figure img{width:100%;height:530px;object-fit:cover;display:block}.mhab-intro__points{display:flex;gap:8px;flex-wrap:wrap;margin-top:25px}.mhab-intro__points span{padding:9px 13px;border-radius:999px;background:#edf4fb;color:var(--mhab-blue);font-size:12px;font-weight:800}
    .mhab-services{padding:90px 0;background:var(--mhab-soft)}.mhab-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mhab-heading p{color:#68798e;line-height:1.8;margin:0}.mhab-services__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px}.mhab-services__grid a{display:block;padding:28px;border:1px solid #dfe7ef;border-radius:15px;background:#fff;text-decoration:none!important;color:inherit;transition:.2s}.mhab-services__grid a:hover{transform:translateY(-4px);box-shadow:0 16px 35px rgba(7,26,51,.08)}.mhab-services__grid span{font-size:12px;color:var(--mhab-blue);font-weight:900}.mhab-services__grid h3{font-size:20px;color:var(--mhab-navy);margin:12px 0 9px}.mhab-services__grid p{font-size:13px;line-height:1.7;color:#6a7b8e;margin:0}
    .mhab-philosophy{padding:92px 0;background:var(--mhab-navy);color:#fff}.mhab-philosophy__grid{display:grid;grid-template-columns:1.05fr .95fr;gap:50px;align-items:center}.mhab-philosophy h2{color:#fff}.mhab-philosophy p{color:#b5c4d4}.mhab-philosophy__image{border-radius:17px;overflow:hidden}.mhab-philosophy__image img{width:100%;height:470px;object-fit:cover;display:block}.mhab-philosophy ul{list-style:none;padding:0;margin:25px 0 0}.mhab-philosophy li{display:grid;gap:5px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.12)}.mhab-philosophy li b{color:#fff}.mhab-philosophy li span{color:#9fb1c4;font-size:13px}
    .mhab-process{padding:90px 0}.mhab-process__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}.mhab-process__grid>div{padding:27px;border:1px solid #dfe7ef;border-radius:14px;background:#fff}.mhab-process__grid b{color:var(--mhab-blue);font-size:13px}.mhab-process__grid h3{font-size:19px;color:var(--mhab-navy);margin:13px 0 9px}.mhab-process__grid p{font-size:13px;line-height:1.7;color:#68798c;margin:0}
    .mhab-work{padding:90px 0;background:var(--mhab-navy);text-align:center}.mhab-heading--light h2{color:#fff}.mhab-work__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:30px}.mhab-work figure{margin:0;border-radius:16px;overflow:hidden}.mhab-work img{width:100%;height:420px;object-fit:cover;display:block;transition:.4s}.mhab-work figure:hover img{transform:scale(1.03)}.mhab-text-link{display:inline-flex;color:#fff!important;text-decoration:none!important;font-weight:900;border-bottom:1px solid var(--mhab-gold);padding-bottom:7px}
    .mhab-contact{padding:75px 0;background:#eaf1f7}.mhab-contact__box{display:grid;grid-template-columns:1.15fr .85fr;gap:35px;align-items:center;background:#fff;border-radius:18px;padding:46px 52px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhab-contact h2{margin-bottom:12px}.mhab-contact p{margin:0;color:#68798c}.mhab-contact__info{display:grid;gap:12px}.mhab-contact__info span{display:grid;gap:3px;padding:12px 0;border-bottom:1px solid #e4eaf0}.mhab-contact__info small{color:#7d8b9a}.mhab-contact__info b{color:var(--mhab-navy)}.mhab-mobile-wa{display:none}
    @media(max-width:900px){.mhab-intro__grid,.mhab-philosophy__grid,.mhab-contact__box{grid-template-columns:1fr}.mhab-services__grid{grid-template-columns:repeat(2,1fr)}.mhab-process__grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:600px){.mhab-shell{width:min(100% - 28px,1180px)}.mhab-hero{min-height:600px;background-position:42% center}.mhab-hero__content{padding-block:60px}.mhab-hero h1{font-size:43px}.mhab-actions{display:grid}.mhab-btn{width:100%}.mhab-intro,.mhab-services,.mhab-philosophy,.mhab-process,.mhab-work{padding:64px 0}.mhab-intro figure img,.mhab-philosophy__image img{height:330px}.mhab-services__grid,.mhab-process__grid,.mhab-work__grid{grid-template-columns:1fr}.mhab-work img{height:330px}.mhab-contact{padding:48px 0 90px}.mhab-contact__box{padding:30px 24px}.mhab-mobile-wa{display:flex;position:fixed;z-index:9999;left:14px;right:14px;bottom:12px;min-height:52px;align-items:center;justify-content:center;border-radius:10px;background:var(--mhab-green);color:#fff!important;font-weight:900;text-decoration:none!important;box-shadow:0 12px 32px rgba(0,0,0,.25)}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_about_head', 106);


/**
 * Crawlable local-services hub for search engines and AI search.
 */
function mh_control_is_services_page(): bool {
    return mh_control_request_path() === '/services/';
}

function mh_control_services_data(): array {
    return [
        [
            'name' => 'خلفيات شاشة وديكور TV',
            'summary' => 'تصميم خلفية شاشة حسب مقاس الحائط مع طاولة معلقة، توزيع مناسب للتلفزيون وإمكانية إخفاء الأسلاك.',
            'details' => 'تبدأ التصميمات المختارة من 98 د.ك شامل التوريد والتركيب داخل الكويت، بعد مراجعة صورة المكان والمقاسات ومتطلبات الحائط.',
            'url' => home_url('/product-category/نماذج-وتصميمات/?service=tv-wall'),
        ],
        [
            'name' => 'طاولات تلفزيون معلقة',
            'summary' => 'طاولات TV معلقة بمقاسات 150 أو 200 سم، وسبعة ألوان تناسب الأثاث والديكور.',
            'details' => 'يظهر في صفحة الطاولات المقاس والعمق والارتفاع وصور الألوان والسعر وخيار التركيب قبل إرسال الطلب على واتساب.',
            'url' => home_url('/tv-tables/'),
        ],
        [
            'name' => 'أركان قهوة',
            'summary' => 'ركن قهوة عملي للمنازل والمكاتب يجمع بين اللوح الديكوري والطاولة المعلقة والتخزين المنظم.',
            'details' => 'تتوفر تصميمات وألوان متعددة مع أسعار واضحة وخيار التوريد أو التوريد والتركيب داخل الكويت.',
            'url' => home_url('/coffee-corner/'),
        ],
        [
            'name' => 'أرضيات باركيه',
            'summary' => 'باركيه بدرجات خشبية متعددة لإضافة الدفء للمساحة، مع حساب الكمية المطلوبة حسب الطول والعرض.',
            'details' => 'نساعد في اختيار الدرجة المناسبة للأثاث، وحساب المساحة والكمية مع نسبة احتياط قبل تأكيد الطلب.',
            'url' => home_url('/product/باركية-خشب-k9188/'),
        ],
        [
            'name' => 'فواصل بديل الخشب WPC',
            'summary' => 'أعمدة بديل الخشب لتقسيم المساحات بشكل أنيق مع الحفاظ على الضوء والإحساس بالاتساع.',
            'details' => 'تتوفر عدة درجات، ويمكن حساب عدد الأعمدة والسعر مع أو بدون تركيب قبل التواصل.',
            'url' => home_url('/product/قاطع-الاعمدة/'),
        ],
        [
            'name' => 'جهاز الفير المائي',
            'summary' => 'ديكور لهب ثلاثي الأبعاد يعمل ببخار الماء والكهرباء ويضيف نقطة مميزة للحائط أو الوحدة.',
            'details' => 'متوفر بخمسة مقاسات تبدأ من 40 سم حتى 150 سم، مع عرض الأسعار والمواصفات وطريقة الطلب.',
            'url' => home_url('/fire-blaze/'),
        ],
    ];
}

function mh_control_services_markup(): string {
    $whatsapp = 'https://wa.me/96550204320?text=' . rawurlencode('مرحباً ماركوز هوم، شاهدت صفحة خدمات الديكور وأريد اقتراحاً مناسباً لمساحتي. هذه صورة المكان والمقاسات:');
    ob_start();
    ?>
    <main class="mh-services-page" dir="rtl">
        <section class="mhsvc-hero">
            <div class="mhsvc-shell">
                <span>Marco's Home — الكويت</span>
                <h1>خدمات الديكور الداخلي<br>وحلول الحوائط</h1>
                <p>تصميم وتنفيذ خلفيات شاشة، طاولات تلفزيون معلقة، أركان قهوة، باركيه وفواصل بديل الخشب حسب المساحة والاحتياج داخل الكويت.</p>
                <div class="mhsvc-actions"><a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">أرسل صورة المكان</a><a href="<?php echo esc_url(home_url('/portfolio/')); ?>">شاهد أعمالنا</a></div>
            </div>
        </section>

        <section class="mhsvc-intro">
            <div class="mhsvc-shell mhsvc-intro__grid">
                <div><span>اختيار واضح قبل التنفيذ</span><h2>حلول مصممة على مقاس بيتك</h2></div>
                <p>نراجع صورة المكان والمقاسات ومواقع الكهرباء والألوان الموجودة، ثم نوضح التصميم والخامة والسعر وما يشمله التوريد والتركيب. جميع الخدمات متاحة للعملاء داخل الكويت، ويختلف موعد التنفيذ حسب المنتج والمقاس وتجهيزات الموقع.</p>
            </div>
        </section>

        <section class="mhsvc-list" aria-label="خدمات ماركوز هوم">
            <div class="mhsvc-shell">
                <?php foreach (mh_control_services_data() as $index => $service) : ?>
                    <article class="mhsvc-card">
                        <div class="mhsvc-card__number"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></div>
                        <div><h2><?php echo esc_html($service['name']); ?></h2><p><?php echo esc_html($service['summary']); ?></p><small><?php echo esc_html($service['details']); ?></small></div>
                        <a href="<?php echo esc_url($service['url']); ?>">التفاصيل والمقاسات والأسعار ←</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mhsvc-areas">
            <div class="mhsvc-shell mhsvc-areas__grid">
                <div><span>نطاق الخدمة</span><h2>معاينة وقياس وتركيب داخل الكويت</h2><p>يقع نشاط ماركوز هوم في حولي — شارع نادي القادسية، ونخدم مناطق الكويت وفق نوع المنتج ومتطلبات القياس والتركيب.</p></div>
                <div class="mhsvc-areas__steps"><div><b>1</b><p>أرسل صورة واضحة للحائط أو المساحة.</p></div><div><b>2</b><p>أرسل العرض والارتفاع وموقع الكهرباء.</p></div><div><b>3</b><p>نراجع التصميم والخامة والسعر والتركيب.</p></div></div>
            </div>
        </section>

        <section class="mhsvc-faq" id="faq">
            <div class="mhsvc-shell"><div class="mhsvc-heading"><span>أسئلة متكررة</span><h2>معلومات تساعدك قبل الطلب</h2></div>
                <div class="mhsvc-faq__list">
                    <details><summary>ما الخدمات التي تقدمها ماركوز هوم؟</summary><p>نقدم خلفيات شاشة وديكور TV، طاولات تلفزيون معلقة، أركان قهوة، أرضيات باركيه، فواصل بديل الخشب وأجهزة فير مائي، مع تصميمات قابلة للتخصيص داخل الكويت.</p></details>
                    <details><summary>هل يتوفر القياس والتركيب داخل الكويت؟</summary><p>نعم، يتوفر القياس والتركيب وفق نوع الخدمة والمنطقة وتجهيزات الموقع. يتم تأكيد المطلوب والسعر والموعد قبل بدء التنفيذ.</p></details>
                    <details><summary>كيف أحصل على اقتراح وسعر مناسب؟</summary><p>أرسل صورة المكان والمقاسات المتاحة على واتساب، وحدد الخدمة المطلوبة. نراجع المساحة ونوضح الاختيارات والسعر وما يشمله التركيب.</p></details>
                    <details><summary>هل يمكن تغيير المقاس أو اللون؟</summary><p>تتوفر خيارات متعددة حسب المنتج، وبعض التصميمات تُنفذ حسب المقاس. يجب تأكيد المقاس واللون والخامة قبل التصنيع أو التوريد.</p></details>
                </div>
            </div>
        </section>

        <section class="mhsvc-cta"><div class="mhsvc-shell"><div><span>ابدأ بصورة</span><h2>خلّي اختيار التصميم أسهل</h2><p>أرسل صورة المكان والمقاسات وسنراجع معك الحل الأنسب.</p></div><a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">تواصل على واتساب</a></div></section>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_services_page(): void {
    if (!mh_control_is_services_page()) return;
    mh_control_prepare_virtual_page();
    get_header();
    echo mh_control_services_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_services_page', 23);

function mh_control_services_title(string $title): string {
    return mh_control_is_services_page() ? 'خدمات الديكور الداخلي في الكويت | ماركوز هوم' : $title;
}
function mh_control_services_description(string $description): string {
    return mh_control_is_services_page() ? 'خدمات ماركوز هوم في الكويت: خلفيات شاشة، طاولات TV معلقة، أركان قهوة، باركيه، فواصل بديل الخشب وفير مائي مع خيارات القياس والتركيب.' : $description;
}
function mh_control_services_canonical(string $url): string {
    return mh_control_is_services_page() ? home_url('/services/') : $url;
}
add_filter('pre_get_document_title', 'mh_control_services_title', 127);
add_filter('aioseo_title', 'mh_control_services_title', 1270);
add_filter('aioseo_description', 'mh_control_services_description', 1270);
add_filter('aioseo_canonical_url', 'mh_control_services_canonical', 1270);
add_filter('wpseo_title', 'mh_control_services_title', 1270);
add_filter('wpseo_metadesc', 'mh_control_services_description', 1270);
add_filter('rank_math/frontend/title', 'mh_control_services_title', 1270);
add_filter('rank_math/frontend/description', 'mh_control_services_description', 1270);

function mh_control_services_head(): void {
    if (!mh_control_is_services_page()) return;
    ?>
    <style id="mh-services-page-styles">
    :root{--mhsvc-blue:#1266d6;--mhsvc-navy:#071a33;--mhsvc-ink:#15263a;--mhsvc-soft:#f2f6fa;--mhsvc-gold:#d6aa62;--mhsvc-green:#20b95a}.mh-services-page{font-family:Tahoma,Arial,sans-serif;color:var(--mhsvc-ink);background:#fff}.mh-services-page *{box-sizing:border-box}.mhsvc-shell{width:min(1120px,calc(100% - 40px));margin-inline:auto}
    .mhsvc-hero{padding:105px 0;background:linear-gradient(120deg,rgba(7,26,51,.97),rgba(18,65,112,.9)),url('https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg') center/cover;color:#fff}.mhsvc-hero span,.mhsvc-intro span,.mhsvc-areas span,.mhsvc-heading span,.mhsvc-cta span{display:inline-block;color:#9bc5f8;font-size:14px;font-weight:900;margin-bottom:14px}.mhsvc-hero h1{font-size:clamp(43px,7vw,72px);line-height:1.12;color:#fff;margin:0 0 20px}.mhsvc-hero p{max-width:760px;color:#e6eef7;font-size:18px;line-height:1.9;margin:0 0 30px}.mhsvc-actions{display:flex;gap:12px;flex-wrap:wrap}.mhsvc-actions a,.mhsvc-cta a{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 24px;border-radius:8px;background:var(--mhsvc-green);color:#fff!important;font-weight:900;text-decoration:none!important}.mhsvc-actions a+ a{background:transparent;border:1px solid rgba(255,255,255,.65)}
    .mhsvc-intro{padding:75px 0}.mhsvc-intro__grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:55px;align-items:end}.mhsvc-intro span,.mhsvc-areas span,.mhsvc-heading span,.mhsvc-cta span{color:var(--mhsvc-blue)}.mhsvc-intro h2,.mhsvc-areas h2,.mhsvc-heading h2,.mhsvc-cta h2{font-size:clamp(31px,5vw,47px);line-height:1.25;color:var(--mhsvc-navy);margin:0}.mhsvc-intro p,.mhsvc-areas p{color:#607287;line-height:1.95;margin:0}
    .mhsvc-list{padding:10px 0 85px}.mhsvc-card{display:grid;grid-template-columns:70px 1fr auto;gap:24px;align-items:center;padding:29px 0;border-bottom:1px solid #dde6ef}.mhsvc-card__number{font-size:13px;color:var(--mhsvc-blue);font-weight:900}.mhsvc-card h2{font-size:25px;color:var(--mhsvc-navy);margin:0 0 8px}.mhsvc-card p{margin:0 0 8px;color:#52667c;line-height:1.8}.mhsvc-card small{display:block;color:#7b8998;line-height:1.7}.mhsvc-card>a{white-space:nowrap;color:var(--mhsvc-blue)!important;font-weight:900;text-decoration:none!important}
    .mhsvc-areas{padding:85px 0;background:var(--mhsvc-navy);color:#fff}.mhsvc-areas__grid{display:grid;grid-template-columns:1fr 1fr;gap:55px;align-items:center}.mhsvc-areas h2{color:#fff;margin-bottom:16px}.mhsvc-areas p{color:#b3c3d4}.mhsvc-areas__steps{display:grid;gap:12px}.mhsvc-areas__steps>div{display:grid;grid-template-columns:42px 1fr;gap:14px;align-items:center;padding:18px;border:1px solid rgba(255,255,255,.13);border-radius:12px}.mhsvc-areas__steps b{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:var(--mhsvc-blue);color:#fff}.mhsvc-areas__steps p{margin:0}
    .mhsvc-faq{padding:85px 0}.mhsvc-heading{text-align:center;margin-bottom:38px}.mhsvc-faq__list{max-width:880px;margin:auto;display:grid;gap:12px}.mhsvc-faq details{border:1px solid #dce5ee;border-radius:12px;padding:0 21px}.mhsvc-faq summary{cursor:pointer;list-style:none;padding:20px 0;color:var(--mhsvc-navy);font-weight:900}.mhsvc-faq summary::-webkit-details-marker{display:none}.mhsvc-faq details p{margin:0;padding:0 0 20px;color:#607287;line-height:1.9}
    .mhsvc-cta{padding:68px 0;background:var(--mhsvc-soft)}.mhsvc-cta .mhsvc-shell{display:flex;align-items:center;justify-content:space-between;gap:28px;background:#fff;padding:40px 45px;border-radius:17px;box-shadow:0 16px 45px rgba(7,26,51,.08)}.mhsvc-cta h2{font-size:clamp(28px,4vw,40px);margin-bottom:10px}.mhsvc-cta p{margin:0;color:#68798c}
    @media(max-width:760px){.mhsvc-shell{width:min(100% - 28px,1120px)}.mhsvc-hero{padding:72px 0}.mhsvc-intro__grid,.mhsvc-areas__grid{grid-template-columns:1fr;gap:24px}.mhsvc-card{grid-template-columns:44px 1fr}.mhsvc-card>a{grid-column:2;white-space:normal}.mhsvc-cta .mhsvc-shell{align-items:flex-start;flex-direction:column;padding:30px 24px}.mhsvc-actions{display:grid}.mhsvc-actions a{width:100%}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_services_head', 107);


/**
 * Simplified site-wide header and mobile navigation — roadmap step 4.
 */
function mh_control_render_global_header(): void {
    if (is_admin()) return;
    $whatsapp = 'https://wa.me/96550204320?text=' . rawurlencode('مرحباً ماركوز هوم، أريد الاستفسار عن تصميم مناسب لمساحتي. هذه صورة المكان:');
    ?>
    <header class="mh-global-header" dir="rtl" aria-label="رأس الموقع">
        <div class="mh-global-header__shell">
            <a class="mh-global-logo" href="https://marcohom.com/?site=mh" aria-label="ماركوز هوم — الرئيسية">
                <img src="https://marcohom.com/wp-content/uploads/2025/03/صورة-واتساب-بتاريخ-2025-03-14-في-04.03.49_157c93e1-480x360.jpg" alt="ماركوز هوم">
            </a>

            <nav class="mh-global-nav" id="mh-global-nav" aria-label="القائمة الرئيسية">
                <a href="https://marcohom.com/?site=mh">الرئيسية</a>
                <a href="https://marcohom.com/services/">خدماتنا</a>
                <a href="https://marcohom.com/portfolio/?site=mh">أعمالنا</a>
                <a href="https://marcohom.com/about/?company=marcos-home">عن ماركوز هوم</a>
                <a class="mh-global-nav__instagram" href="https://www.instagram.com/marcoshomekw?igsh=MWk0bXh1a2duYnVoMA%3D%3D&utm_source=qr" target="_blank" rel="noopener">Instagram</a>
            </nav>

            <div class="mh-global-header__actions">
                <a class="mh-global-whatsapp" href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.8 9.8 0 0 0-8.46 14.73L2 22l5.42-1.42A10 10 0 1 0 12 2Zm0 17.9a7.8 7.8 0 0 1-3.98-1.09l-.29-.17-3.22.84.86-3.14-.19-.31A7.8 7.8 0 1 1 12 19.9Zm4.28-5.84c-.23-.12-1.39-.69-1.61-.76-.21-.08-.37-.12-.53.12-.15.23-.6.76-.74.92-.13.15-.27.17-.5.05-.24-.12-.99-.36-1.88-1.16a7.1 7.1 0 0 1-1.3-1.62c-.13-.24-.01-.36.1-.48.1-.1.23-.27.35-.41.11-.14.15-.24.23-.39.08-.16.04-.29-.02-.41-.06-.12-.53-1.27-.72-1.74-.19-.46-.38-.4-.53-.41h-.45c-.16 0-.41.06-.63.29-.21.23-.82.8-.82 1.96 0 1.15.84 2.27.96 2.43.12.15 1.65 2.52 4 3.54.56.24.99.38 1.33.49.56.17 1.07.15 1.47.09.45-.07 1.39-.57 1.58-1.12.2-.55.2-1.02.14-1.12-.05-.1-.21-.16-.45-.28Z"/></svg>
                    <span>واتساب</span>
                </a>
                <button class="mh-global-toggle" type="button" aria-controls="mh-global-nav" aria-expanded="false" aria-label="فتح القائمة">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </header>
    <?php
}
add_action('wp_body_open', 'mh_control_render_global_header', 1);

function mh_control_global_header_styles(): void {
    if (is_admin()) return;
    ?>
    <style id="mh-global-header-styles">
    #masthead{display:none!important}
    .mh-global-header{position:sticky;top:0;z-index:9998;background:rgba(255,255,255,.97);border-bottom:1px solid #e5ebf1;box-shadow:0 5px 24px rgba(7,26,51,.06);backdrop-filter:blur(12px);font-family:Tahoma,Arial,sans-serif;direction:rtl}
    body.admin-bar .mh-global-header{top:32px}
    .mh-global-header *{box-sizing:border-box}.mh-global-header__shell{width:min(1220px,calc(100% - 36px));height:88px;margin-inline:auto;display:flex;align-items:center;gap:26px}
    .mh-global-logo{width:118px;height:76px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;text-decoration:none!important;overflow:hidden}
    .mh-global-logo img{width:112px!important;height:76px!important;display:block;object-fit:contain;mix-blend-mode:multiply}
    .mh-global-nav{display:flex;align-items:center;gap:5px;margin-inline-start:auto}.mh-global-nav>a{display:inline-flex;align-items:center;min-height:42px;padding:9px 13px;border-radius:8px;color:#14263a!important;text-decoration:none!important;font-size:14px;font-weight:800;white-space:nowrap;transition:.2s}.mh-global-nav>a:hover,.mh-global-nav>a:focus{color:#1266d6!important;background:#eef5fd}
    .mh-global-nav__instagram{color:#1266d6!important}.mh-global-header__actions{display:flex;align-items:center;gap:9px;flex:0 0 auto}
    .mh-global-whatsapp{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:10px 17px;border-radius:9px;background:#20b95a;color:#fff!important;text-decoration:none!important;font-size:14px;font-weight:900;box-shadow:0 9px 22px rgba(32,185,90,.2);transition:.2s}.mh-global-whatsapp:hover{background:#159a48;color:#fff!important;transform:translateY(-1px)}.mh-global-whatsapp svg{width:20px;height:20px;fill:currentColor}
    .mh-global-toggle{display:none;width:46px;height:46px;border:1px solid #d9e2eb;border-radius:9px;background:#fff;padding:11px;cursor:pointer}.mh-global-toggle span{display:block;height:2px;background:#0b213a;border-radius:2px;transition:.2s}.mh-global-toggle span+span{margin-top:5px}
    @media(max-width:980px){.mh-global-header__shell{height:76px;gap:12px}.mh-global-logo{width:91px;height:65px}.mh-global-logo img{width:91px!important;height:65px!important}.mh-global-toggle{display:block}.mh-global-whatsapp{padding:9px 12px}.mh-global-nav{position:absolute;top:100%;inset-inline:0;display:grid;gap:4px;margin:0;padding:12px 18px 18px;background:#fff;border-bottom:1px solid #dce5ee;box-shadow:0 16px 30px rgba(7,26,51,.12);opacity:0;visibility:hidden;transform:translateY(-8px);pointer-events:none;transition:.2s}.mh-global-nav.is-open{opacity:1;visibility:visible;transform:none;pointer-events:auto}.mh-global-nav>a{width:100%;min-height:46px;padding:11px 14px}.mh-global-nav__instagram{border-top:1px solid #e5ebf1;margin-top:5px;padding-top:15px!important}}
    @media(max-width:782px){body.admin-bar .mh-global-header{top:46px}}
    @media(max-width:520px){.mh-global-header__shell{width:calc(100% - 24px);height:70px}.mh-global-logo{width:78px;height:58px}.mh-global-logo img{width:78px!important;height:58px!important}.mh-global-whatsapp{min-height:43px;padding:8px 10px;font-size:12px}.mh-global-whatsapp svg{width:18px;height:18px}.mh-global-toggle{width:43px;height:43px}.mh-global-header__actions{gap:7px}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_global_header_styles', 200);

function mh_control_global_header_script(): void {
    if (is_admin()) return;
    ?>
    <script id="mh-global-header-script">
    document.addEventListener('DOMContentLoaded',function(){
        var button=document.querySelector('.mh-global-toggle'),nav=document.getElementById('mh-global-nav');
        if(!button||!nav)return;
        function closeMenu(){nav.classList.remove('is-open');button.setAttribute('aria-expanded','false');button.setAttribute('aria-label','فتح القائمة');}
        button.addEventListener('click',function(){var open=!nav.classList.contains('is-open');nav.classList.toggle('is-open',open);button.setAttribute('aria-expanded',open?'true':'false');button.setAttribute('aria-label',open?'إغلاق القائمة':'فتح القائمة');});
        nav.querySelectorAll('a').forEach(function(link){link.addEventListener('click',closeMenu);});
        document.addEventListener('keydown',function(event){if(event.key==='Escape')closeMenu();});
        window.addEventListener('resize',function(){if(window.innerWidth>980)closeMenu();});
    });
    </script>
    <?php
}
add_action('wp_footer', 'mh_control_global_header_script', 220);

/**
 * Preserve advertising attribution while visitors move between Marco's Home pages.
 */
function mh_control_campaign_attribution_script(): void {
    if (is_admin()) return;
    ?>
    <script id="mh-campaign-attribution">
    (function(){
        var keys=['utm_source','utm_medium','utm_campaign','utm_content','utm_term','fbclid'],params=new URLSearchParams(location.search),stored={};
        try{stored=JSON.parse(localStorage.getItem('mh_campaign_params')||'{}');}catch(e){stored={};}
        keys.forEach(function(key){var value=params.get(key);if(value)stored[key]=value;});
        if(Object.keys(stored).length){try{localStorage.setItem('mh_campaign_params',JSON.stringify(stored));}catch(e){}}
        document.addEventListener('DOMContentLoaded',function(){
            document.querySelectorAll('a[href]').forEach(function(link){
                try{var url=new URL(link.href,location.href);if(url.hostname!==location.hostname||url.protocol.indexOf('http')!==0)return;keys.forEach(function(key){if(stored[key]&&!url.searchParams.has(key))url.searchParams.set(key,stored[key]);});link.href=url.toString();}catch(e){}
            });
        });
    }());
    </script>
    <?php
}
add_action('wp_head', 'mh_control_campaign_attribution_script', 3);


function mh_control_refresh_site_cache_once(): void {
    if (get_option('mh_global_cache_version') === '1.1.2') return;
    do_action('litespeed_purge_all');
    if (class_exists('LiteSpeed_Cache_API') && is_callable(['LiteSpeed_Cache_API', 'purge_all'])) {
        LiteSpeed_Cache_API::purge_all();
    }
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    wp_cache_flush();
    update_option('mh_global_cache_version', '1.1.2', false);
}
add_action('init', 'mh_control_refresh_site_cache_once', 999);


/**
 * Verified trust elements and customer-feedback collection — roadmap step 5.
 */
function mh_control_home_trust_markup(): string {
    ob_start();
    ?>
    <section class="mhtrust-home" id="mh-trust">
        <div class="mhtrust-shell">
            <div class="mhtrust-heading">
                <span class="mhtrust-eyebrow">الثقة تبدأ من التفاصيل</span>
                <h2>تشوف الخطوات قبل ما نبدأ</h2>
                <p>صورة المكان والمقاس والتصميم والخامة والسعر ومتطلبات التركيب تُراجع قبل التنفيذ.</p>
            </div>
            <div class="mhtrust-proof">
                <div class="mhtrust-proof__steps">
                    <article><b>01</b><h3>صورة ومقاس واضح</h3><p>نراجع عرض وارتفاع المساحة ومكان الكهرباء قبل اعتماد الحل.</p></article>
                    <article><b>02</b><h3>تفاصيل متفق عليها</h3><p>نحدد المقاس واللون والخامة وما يشمله التوريد والتركيب.</p></article>
                    <article><b>03</b><h3>تنفيذ وتشطيب</h3><p>تركيب مرتب ثم مراجعة الشكل النهائي وتسليم المساحة جاهزة.</p></article>
                    <article><b>04</b><h3>تواصل مباشر</h3><p>كل تفاصيل الطلب تُرسل على واتساب لتظل واضحة وسهلة الرجوع.</p></article>
                </div>
                <div class="mhtrust-proof__gallery">
                    <figure class="mhtrust-proof__main"><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011.jpg" alt="طاولة تلفزيون وخلفية شاشة من أعمال ماركوز هوم"><figcaption>خلفية شاشة وطاولة معلقة</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.21-PM-1.jpeg" alt="خلفية سرير من أعمال ماركوز هوم"><figcaption>خلفية سرير بتفاصيل هادئة</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM.jpeg" alt="تنفيذ غرفة بدرجات خشبية من ماركوز هوم"><figcaption>تنفيذ بدرجات خشبية دافئة</figcaption></figure>
                </div>
            </div>
            <div class="mhtrust-actions">
                <a class="mhtrust-link" href="https://marcohom.com/portfolio/?site=mh">شاهد كل الأعمال</a>
                <a class="mhtrust-link mhtrust-link--google" href="<?php echo esc_url(mh_control_google_maps_url()); ?>" target="_blank" rel="noopener">شاهد تقييماتنا على Google</a>
                <a class="mhtrust-link mhtrust-link--soft" href="https://marcohom.com/testimonials/?trust=verified">كيف نتحقق من آراء العملاء؟</a>
            </div>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
}

function mh_control_add_home_trust_section(string $content): string {
    if (is_admin() || !is_front_page() || !in_the_loop() || !is_main_query() || strpos($content, 'mhtrust-home') !== false) return $content;
    return str_replace('<section class="mh-process">', mh_control_home_trust_markup() . '<section class="mh-process">', $content);
}
add_filter('the_content', 'mh_control_add_home_trust_section', 1001);

function mh_control_is_trust_page(): bool {
    return is_page(16) || is_page('testimonials');
}

function mh_control_trust_page_markup(): string {
    $review_message = rawurlencode('مرحباً ماركوز هوم، نفذتم لي مشروعاً وأريد إرسال تقييمي. نوع المشروع: _____. التقييم: _____. الاسم الذي أسمح بنشره: _____. أوافق على نشر هذا التقييم في موقع ماركوز هوم: نعم / لا.');
    ob_start();
    ?>
    <main class="mhtrust-page" dir="rtl">
        <section class="mhtrust-hero">
            <div class="mhtrust-hero__shade"></div>
            <div class="mhtrust-shell mhtrust-hero__content">
                <span class="mhtrust-eyebrow mhtrust-eyebrow--light">ثقة مبنية على الوضوح</span>
                <h1>من أول مقاس<br>لآخر تفصيلة</h1>
                <p>نعرض الأعمال المنشورة ونشرح خطوات التنفيذ بوضوح، وننشر آراء العملاء فقط بعد استلامها منهم والموافقة على عرضها.</p>
                <a class="mhtrust-btn mhtrust-btn--green" href="https://wa.me/96550204320?text=<?php echo esc_attr($review_message); ?>" target="_blank" rel="noopener">أرسل تقييم تجربتك</a>
            </div>
        </section>

        <section class="mhtrust-commitments">
            <div class="mhtrust-shell">
                <div class="mhtrust-heading"><span class="mhtrust-eyebrow">ما الذي يتم تأكيده؟</span><h2>تفاصيل واضحة قبل التنفيذ</h2></div>
                <div class="mhtrust-commitments__grid">
                    <article><span>المقاس</span><h3>أبعاد المكان والمنتج</h3><p>مراجعة العرض والارتفاع والعمق قبل اعتماد الطلب.</p></article>
                    <article><span>التصميم</span><h3>الشكل واللون والخامة</h3><p>تحديد الاختيارات الأساسية في رسالة يمكن الرجوع إليها.</p></article>
                    <article><span>الخدمة</span><h3>ما يشمله السعر</h3><p>توضيح التوريد والتركيب وأي متطلبات إضافية قبل البدء.</p></article>
                    <article><span>التسليم</span><h3>مراجعة النتيجة</h3><p>التأكد من التفاصيل والتشطيب بعد انتهاء التنفيذ.</p></article>
                </div>
            </div>
        </section>

        <section class="mhtrust-real-work" id="mhtrust-real-work">
            <div class="mhtrust-shell">
                <div class="mhtrust-heading mhtrust-heading--light"><span class="mhtrust-eyebrow mhtrust-eyebrow--light">صور منشورة من أعمالنا</span><h2>نماذج تساعدك تختار</h2><p>شاهد التفاصيل والألوان والمقاسات، ثم أرسل صورة مساحتك للحصول على اقتراح مناسب.</p></div>
                <div class="mhtrust-real-work__grid">
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011.jpg" alt="خلفية شاشة وطاولة معلقة من ماركوز هوم"><figcaption>خلفية شاشة وطاولة TV</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.21-PM-1.jpeg" alt="خلفية سرير من ماركوز هوم"><figcaption>خلفية سرير</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg" alt="تشطيب رمادي من ماركوز هوم"><figcaption>تشطيب رمادي هادئ</figcaption></figure>
                    <figure><img src="https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg" alt="تصميم غرفة بإضاءة دافئة من ماركوز هوم"><figcaption>إضاءة دافئة ودرجات محايدة</figcaption></figure>
                </div>
                <a class="mhtrust-text-link" href="https://marcohom.com/portfolio/?site=mh">افتح معرض أعمالنا بالكامل ←</a>
            </div>
        </section>

        <section class="mhtrust-flow">
            <div class="mhtrust-shell">
                <div class="mhtrust-heading"><span class="mhtrust-eyebrow">رحلة التنفيذ</span><h2>أربع نقاط يمكنك متابعتها</h2></div>
                <div class="mhtrust-flow__grid">
                    <article><b>01</b><h3>القياس</h3><p>صورة واضحة ومقاسات مبدئية للمكان.</p></article>
                    <article><b>02</b><h3>الاعتماد</h3><p>اختيار التصميم والخامة واللون.</p></article>
                    <article><b>03</b><h3>التنفيذ</h3><p>تجهيز وتوريد وتركيب حسب الاتفاق.</p></article>
                    <article><b>04</b><h3>التسليم</h3><p>مراجعة التشطيب والتفاصيل النهائية.</p></article>
                </div>
            </div>
        </section>

        <section class="mhtrust-review">
            <div class="mhtrust-shell mhtrust-review__box">
                <div><span class="mhtrust-eyebrow">نفذنا لك مشروعاً؟</span><h2>شاركنا رأيك بصراحة</h2><p>اكتب تجربتك الحقيقية على Google لمساعدة العملاء على اتخاذ قرارهم. لا نطلب تقييمًا محددًا ولا نقدم مقابلاً للتقييم.</p></div>
                <div class="mhtrust-review__actions">
                    <a class="mhtrust-btn mhtrust-btn--google" href="<?php echo esc_url(mh_control_google_maps_url()); ?>" target="_blank" rel="noopener">قيّم تجربتك على Google</a>
                    <a class="mhtrust-btn mhtrust-btn--soft" href="https://wa.me/96550204320?text=<?php echo esc_attr($review_message); ?>" target="_blank" rel="noopener">أرسل ملاحظتك لنا</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    $html = (string) ob_get_clean();
    return (string) preg_replace('/<img(?![^>]*\\bloading=)/i', '<img loading="lazy" decoding="async"', $html);
}

function mh_control_render_trust_page(): void {
    if (!mh_control_is_trust_page()) return;
    status_header(200); get_header();
    echo mh_control_trust_page_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer(); exit;
}
add_action('template_redirect', 'mh_control_render_trust_page', 36);

function mh_control_trust_title(string $title): string {
    return mh_control_is_trust_page() ? 'ثقة العملاء وطريقة تنفيذ ماركوز هوم | الكويت' : $title;
}
add_filter('pre_get_document_title', 'mh_control_trust_title', 126);

function mh_control_trust_description(string $description): string {
    return mh_control_is_trust_page() ? 'تعرف على خطوات القياس والتصميم والتوريد والتركيب في ماركوز هوم، وشاهد صور أعمال منشورة وأرسل تقييم تجربتك بموافقة واضحة.' : $description;
}
add_filter('aioseo_title', 'mh_control_trust_title', 1260);
add_filter('aioseo_description', 'mh_control_trust_description', 1260);
add_filter('wpseo_title', 'mh_control_trust_title', 1260);
add_filter('wpseo_metadesc', 'mh_control_trust_description', 1260);
add_filter('rank_math/frontend/title', 'mh_control_trust_title', 1260);
add_filter('rank_math/frontend/description', 'mh_control_trust_description', 1260);

function mh_control_trust_styles(): void {
    if (!is_front_page() && !mh_control_is_trust_page()) return;
    ?>
    <style id="mh-trust-styles">
    :root{--mht-blue:#1266d6;--mht-navy:#071a33;--mht-ink:#15263a;--mht-soft:#f2f6fa;--mht-gold:#d6aa62;--mht-green:#20b95a}.mhtrust-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}.mhtrust-heading{text-align:center;max-width:760px;margin:0 auto 42px}.mhtrust-heading h2{font-size:clamp(33px,5vw,51px);line-height:1.2;color:var(--mht-navy);margin:0 0 14px}.mhtrust-heading p{color:#68798d;line-height:1.8;margin:0}.mhtrust-eyebrow{display:inline-flex;align-items:center;gap:10px;color:var(--mht-blue);font-size:14px;font-weight:900;margin-bottom:14px}.mhtrust-eyebrow:before{content:"";width:32px;height:2px;background:var(--mht-gold)}.mhtrust-eyebrow--light{color:#dce9f7}
    .mhtrust-home{font-family:Tahoma,Arial,sans-serif;padding:92px 0;background:#fff}.mhtrust-proof{display:grid;grid-template-columns:.88fr 1.12fr;gap:28px;align-items:stretch}.mhtrust-proof__steps{display:grid;grid-template-columns:1fr 1fr;gap:12px}.mhtrust-proof__steps article{padding:23px;border:1px solid #dfe7ef;border-radius:14px;background:#f8fafc}.mhtrust-proof__steps b{font-size:12px;color:var(--mht-blue)}.mhtrust-proof__steps h3{font-size:18px;color:var(--mht-navy);margin:11px 0 8px}.mhtrust-proof__steps p{font-size:12px;line-height:1.7;color:#697a8c;margin:0}.mhtrust-proof__gallery{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:10px}.mhtrust-proof__gallery figure{position:relative;margin:0;border-radius:14px;overflow:hidden;min-height:185px;background:#e8edf2}.mhtrust-proof__main{grid-row:span 2}.mhtrust-proof__gallery img{width:100%;height:100%;object-fit:cover;display:block}.mhtrust-proof__gallery figcaption{position:absolute;inset-inline:12px;bottom:10px;padding:7px 9px;border-radius:7px;background:rgba(7,26,51,.78);color:#fff;font-size:11px}.mhtrust-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:28px}.mhtrust-link{display:inline-flex;padding:12px 18px;border-radius:8px;background:var(--mht-navy);color:#fff!important;text-decoration:none!important;font-weight:900}.mhtrust-link--google{background:#fff;color:#26354a!important;border:1px solid #cfd9e4}.mhtrust-link--soft{background:#eaf2fb;color:var(--mht-blue)!important}
    .mhtrust-page{font-family:Tahoma,Arial,sans-serif;color:var(--mht-ink);background:#fff;width:100%;margin-inline:0;overflow:hidden}.mhtrust-page *{box-sizing:border-box}.mhtrust-hero{min-height:620px;display:flex;align-items:center;position:relative;background:url('https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011.jpg') center/cover no-repeat}.mhtrust-hero__shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(4,16,32,.26),rgba(4,16,32,.92))}.mhtrust-hero__content{position:relative;z-index:1;color:#fff;padding-block:80px}.mhtrust-hero h1{font-size:clamp(46px,7vw,76px);line-height:1.08;color:#fff;margin:0 0 20px}.mhtrust-hero p{max-width:690px;font-size:18px;line-height:1.9;color:#e6eef7;margin:0 0 28px}.mhtrust-btn{display:inline-flex;justify-content:center;align-items:center;min-height:52px;padding:12px 22px;border-radius:8px;text-decoration:none!important;font-weight:900}.mhtrust-btn--green{background:var(--mht-green);color:#fff!important}
    .mhtrust-commitments{padding:90px 0}.mhtrust-commitments__grid,.mhtrust-flow__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}.mhtrust-commitments article,.mhtrust-flow article{padding:27px;border:1px solid #dfe7ef;border-radius:14px;background:#fff}.mhtrust-commitments span,.mhtrust-flow b{font-size:12px;color:var(--mht-blue);font-weight:900}.mhtrust-commitments h3,.mhtrust-flow h3{font-size:19px;color:var(--mht-navy);margin:12px 0 8px}.mhtrust-commitments p,.mhtrust-flow p{font-size:13px;line-height:1.7;color:#68798c;margin:0}
    .mhtrust-real-work{padding:90px 0;background:var(--mht-navy);text-align:center}.mhtrust-heading--light h2{color:#fff}.mhtrust-heading--light p{color:#b4c3d3}.mhtrust-real-work__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}.mhtrust-real-work figure{position:relative;margin:0;border-radius:15px;overflow:hidden;background:#1a2f47}.mhtrust-real-work img{width:100%;height:390px;object-fit:cover;display:block}.mhtrust-real-work figcaption{position:absolute;inset-inline:12px;bottom:12px;padding:8px 10px;border-radius:7px;background:rgba(7,26,51,.78);color:#fff;font-size:12px}.mhtrust-text-link{display:inline-flex;color:#fff!important;text-decoration:none!important;font-weight:900;border-bottom:1px solid var(--mht-gold);padding-bottom:6px}
    .mhtrust-flow{padding:90px 0;background:var(--mht-soft)}.mhtrust-review{padding:74px 0;background:#e8f0f8}.mhtrust-review__box{display:flex;align-items:center;justify-content:space-between;gap:35px;background:#fff;padding:45px 50px;border-radius:18px;box-shadow:0 18px 50px rgba(7,26,51,.09)}.mhtrust-review h2{font-size:clamp(30px,4vw,45px);color:var(--mht-navy);margin:0 0 11px}.mhtrust-review p{color:#68798c;line-height:1.8;margin:0;max-width:690px}.mhtrust-review__actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.mhtrust-btn--google{background:#1266d6;color:#fff!important}.mhtrust-btn--soft{background:#edf3f9;color:var(--mht-navy)!important}
    @media(max-width:900px){.mhtrust-proof{grid-template-columns:1fr}.mhtrust-commitments__grid,.mhtrust-flow__grid,.mhtrust-real-work__grid{grid-template-columns:1fr 1fr}.mhtrust-review__box{align-items:flex-start;flex-direction:column}}
    @media(max-width:600px){.mhtrust-shell{width:min(100% - 28px,1180px)}.mhtrust-home,.mhtrust-commitments,.mhtrust-real-work,.mhtrust-flow{padding:64px 0}.mhtrust-proof__steps,.mhtrust-proof__gallery,.mhtrust-commitments__grid,.mhtrust-flow__grid,.mhtrust-real-work__grid{grid-template-columns:1fr}.mhtrust-proof__gallery{grid-template-rows:auto}.mhtrust-proof__main{grid-row:auto}.mhtrust-proof__gallery figure{height:280px}.mhtrust-hero{min-height:590px;background-position:40% center}.mhtrust-hero h1{font-size:43px}.mhtrust-real-work img{height:320px}.mhtrust-review{padding:48px 0}.mhtrust-review__box{padding:30px 24px}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_trust_styles', 207);

/**
 * Trust and policy pages required by advertising platforms and customers.
 * They are rendered as stable, direct 200 URLs without depending on editor content.
 */
function mh_control_policy_pages(): array {
    return [
        'privacy-policy' => [
            'title' => 'سياسة الخصوصية | ماركوز هوم',
            'description' => 'تعرف على طريقة تعامل ماركوز هوم مع بيانات التواصل والطلبات وملفات الارتباط عند استخدام الموقع داخل الكويت.',
            'eyebrow' => 'خصوصيتك مهمة لنا',
            'heading' => 'سياسة الخصوصية',
            'intro' => 'توضح هذه الصفحة البيانات التي قد نجمعها عند زيارة موقع ماركوز هوم أو التواصل معنا، ولماذا نستخدمها وكيف يمكنك الاستفسار عنها.',
            'sections' => [
                ['البيانات التي نتعامل معها', ['قد نستلم اسمك ورقم الهاتف والعنوان وصورة المكان والمقاسات وتفاصيل الطلب عندما ترسلها لنا بنفسك عبر واتساب أو نماذج الموقع.', 'قد تسجل أدوات القياس بيانات تقنية عامة مثل نوع الجهاز والصفحات التي تمت زيارتها ومصدر الزيارة، بهدف تحسين الموقع وقياس أداء الحملات.']],
                ['كيف نستخدم البيانات', ['نستخدم البيانات للرد على الاستفسار، تجهيز عرض مناسب، تأكيد تفاصيل الطلب والتوصيل أو التركيب، وتحسين تجربة الموقع والإعلانات.', 'لا نبيع بيانات العملاء. وقد نستعين بمقدمي خدمات الاستضافة والتحليلات والإعلانات بالقدر اللازم لتشغيل الموقع وقياس الأداء.']],
                ['ملفات الارتباط والإعلانات', ['قد يستخدم الموقع ملفات ارتباط وتقنيات قياس من منصات مثل Google وMeta وSnapchat إذا تم تفعيلها. يمكنك التحكم في ملفات الارتباط من إعدادات المتصفح أو الجهاز.']],
                ['الاحتفاظ والحماية', ['نحتفظ بمعلومات الطلب والتواصل للمدة اللازمة لخدمة العميل والالتزامات التشغيلية والقانونية، ونتخذ إجراءات معقولة لحمايتها من الوصول غير المصرح به.']],
                ['حقوقك والتواصل', ['يمكنك طلب تصحيح بياناتك أو الاستفسار عن استخدامها عبر واتساب على الرقم +965 5020 4320. سنراجع الطلب ونتعامل معه وفق القوانين المعمول بها في الكويت.']],
            ],
        ],
        'terms-and-conditions' => [
            'title' => 'الشروط والأحكام | ماركوز هوم',
            'description' => 'شروط طلب منتجات وخدمات ماركوز هوم والأسعار والمقاسات والتأكيد والتوريد والتركيب داخل الكويت.',
            'eyebrow' => 'اتفاق واضح قبل التنفيذ',
            'heading' => 'الشروط والأحكام',
            'intro' => 'استخدامك للموقع أو إرسال طلب يعني موافقتك على مراجعة تفاصيل المنتج والخدمة قبل التأكيد النهائي. المعلومات التالية تساعد على منع أي اختلاف في المقاس أو اللون أو السعر.',
            'sections' => [
                ['الأسعار والعروض', ['الأسعار المعروضة بالدينار الكويتي وتشمل فقط البنود الموضحة بجوار كل منتج. يتم توضيح أي تكلفة إضافية للتوصيل أو التركيب قبل اعتماد الطلب.', 'لا يصبح الطلب مؤكدًا إلا بعد مراجعة المنتج والمقاس واللون والسعر والعنوان والموعد مع العميل.']],
                ['المقاسات والألوان', ['يتحمل العميل مسؤولية إرسال مقاسات وصور واضحة للمكان، ونراجعها معه قبل التنفيذ. قد تختلف درجة اللون الظاهرة قليلًا حسب الشاشة والإضاءة، لذلك يتم تأكيد الاختيار قبل التجهيز.']],
                ['المنتجات المخصصة', ['بعض المنتجات تُجهّز حسب المقاس أو اللون المختار. سنوضح للعميل متى يبدأ التجهيز وما إذا كان التعديل أو الإلغاء متاحًا قبل تأكيد الطلب.']],
                ['الاستخدام والمسؤولية', ['يجب تركيب المنتجات بالطريقة المناسبة لنوع الحائط والاستخدام المقصود. يتم توضيح أي متطلبات خاصة قبل التركيب، ويجب إبلاغنا بأي ملاحظة فور الاستلام.']],
                ['التواصل', ['للاستفسار عن أي شرط قبل الطلب تواصل معنا عبر واتساب على +965 5020 4320. وتظل حقوق المستهلك المقررة في الكويت محفوظة.']],
            ],
        ],
        'shipping-and-installation' => [
            'title' => 'سياسة التوصيل والتركيب | ماركوز هوم الكويت',
            'description' => 'تفاصيل التوصيل والتركيب ومراجعة العنوان والحائط والموعد لطلبات ماركوز هوم داخل الكويت.',
            'eyebrow' => 'من التجهيز حتى التسليم',
            'heading' => 'التوصيل والتركيب',
            'intro' => 'نخدم العملاء داخل الكويت، ويتم تحديد موعد وطريقة التوصيل أو التركيب قبل تأكيد الطلب حسب المنتج والعنوان وجاهزية المكان.',
            'sections' => [
                ['نطاق الخدمة', ['يتم التوصيل والتركيب داخل الكويت بعد مراجعة المنطقة والعنوان وإمكانية الوصول إلى الموقع. قد تختلف الرسوم حسب المنتج والخدمة المطلوبة، ويتم إبلاغك بها قبل التأكيد.']],
                ['موعد التنفيذ', ['مدة التجهيز والتسليم تعتمد على المقاس واللون وتوفر الخامة وجدول التنفيذ. نرسل المدة المتوقعة للعميل قبل اعتماد الطلب ونبلغه إذا طرأ تغيير خارج عن الإرادة.']],
                ['جاهزية المكان', ['على العميل التأكد من خلو مساحة العمل وتوفر حائط مناسب ومصدر كهرباء عند الحاجة. نراجع الصور والمقاسات المبدئية قبل الزيارة، وقد يتطلب بعض العمل معاينة.']],
                ['فحص الطلب', ['يرجى فحص المنتج والتشطيب عند الاستلام أو بعد التركيب وإبلاغ فريقنا فورًا بأي ملاحظة واضحة مع صور تساعد على مراجعة الحالة.']],
            ],
        ],
        'returns-and-refunds' => [
            'title' => 'سياسة الاستبدال والاسترجاع | ماركوز هوم',
            'description' => 'طريقة الإبلاغ عن التلف أو اختلاف الطلب وشروط مراجعة الاستبدال والاسترجاع لمنتجات ماركوز هوم.',
            'eyebrow' => 'حل واضح عند وجود مشكلة',
            'heading' => 'الاستبدال والاسترجاع',
            'intro' => 'نراجع كل حالة بصورة عادلة وفق حالة المنتج وطبيعة الطلب وما تم الاتفاق عليه، مع الحفاظ على حقوق المستهلك المقررة في الكويت.',
            'sections' => [
                ['التلف أو اختلاف الطلب', ['إذا وصل المنتج تالفًا أو مختلفًا بوضوح عن المقاس أو اللون المؤكد، تواصل معنا فور الاستلام على واتساب وأرسل رقم الطلب وصورًا واضحة. سنراجع الحالة ونوضح حل الإصلاح أو الاستبدال أو الاسترجاع المناسب.']],
                ['المنتجات المخصصة', ['المنتج الذي بدأ تصنيعه خصيصًا حسب مقاس أو لون اختاره العميل قد لا يقبل الإلغاء أو الاسترجاع لمجرد تغيير الرأي، ما لم يوجد عيب أو اختلاف عن الاتفاق. نوضح هذه النقطة قبل تأكيد الطلب.']],
                ['حالة المنتج', ['يجب الحفاظ على المنتج وملحقاته وعدم استخدامه أو تركيبه بطريقة تسبب تلفًا إضافيًا أثناء مراجعة الطلب. لا يشمل الضمان الضرر الناتج عن سوء الاستخدام أو تعديل المنتج بواسطة طرف آخر.']],
                ['طريقة التواصل', ['أرسل تفاصيل الحالة إلى واتساب +965 5020 4320. يبدأ فحص الطلب بعد استلام المعلومات والصور المطلوبة، ثم نبلغك بالإجراء والمدة المتوقعة.']],
            ],
        ],
    ];
}

function mh_control_policy_slug(): string {
    $slug = trim(mh_control_request_path(), '/');
    return array_key_exists($slug, mh_control_policy_pages()) ? $slug : '';
}

function mh_control_is_policy_page(): bool {
    return mh_control_policy_slug() !== '';
}

function mh_control_policy_markup(): string {
    $pages = mh_control_policy_pages();
    $page = $pages[mh_control_policy_slug()];
    ob_start();
    ?>
    <main class="mh-policy" dir="rtl">
        <section class="mhpcy-hero"><div class="mhpcy-shell"><span><?php echo esc_html($page['eyebrow']); ?></span><h1><?php echo esc_html($page['heading']); ?></h1><p><?php echo esc_html($page['intro']); ?></p></div></section>
        <section class="mhpcy-content"><div class="mhpcy-shell mhpcy-grid">
            <article>
                <?php foreach ($page['sections'] as $section) : ?>
                    <section><h2><?php echo esc_html($section[0]); ?></h2><?php foreach ($section[1] as $paragraph) : ?><p><?php echo esc_html($paragraph); ?></p><?php endforeach; ?></section>
                <?php endforeach; ?>
                <p class="mhpcy-updated">آخر تحديث: 8 أغسطس 2026</p>
            </article>
            <aside><h2>بيانات النشاط</h2><b>Marco's Home — ماركوز هوم</b><p>ديكور وحلول منزلية داخل الكويت</p><p>حولي — شارع نادي القادسية</p><a href="https://wa.me/96550204320" target="_blank" rel="noopener">واتساب: +965 5020 4320</a><a href="https://marcohom.com/contact/">صفحة التواصل</a></aside>
        </div></section>
    </main>
    <?php
    return (string) ob_get_clean();
}

function mh_control_render_policy_page(): void {
    if (!mh_control_is_policy_page()) return;
    mh_control_prepare_virtual_page();
    get_header();
    echo mh_control_policy_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_control_render_policy_page', 24);

function mh_control_policy_title(string $title): string {
    if (!mh_control_is_policy_page()) return $title;
    $pages = mh_control_policy_pages();
    return $pages[mh_control_policy_slug()]['title'];
}

function mh_control_policy_description(string $description): string {
    if (!mh_control_is_policy_page()) return $description;
    $pages = mh_control_policy_pages();
    return $pages[mh_control_policy_slug()]['description'];
}

function mh_control_policy_canonical(string $url): string {
    return mh_control_is_policy_page() ? home_url('/' . mh_control_policy_slug() . '/') : $url;
}
add_filter('pre_get_document_title', 'mh_control_policy_title', 130);
add_filter('aioseo_title', 'mh_control_policy_title', 1300);
add_filter('aioseo_description', 'mh_control_policy_description', 1300);
add_filter('aioseo_canonical_url', 'mh_control_policy_canonical', 1300);
add_filter('wpseo_title', 'mh_control_policy_title', 1300);
add_filter('wpseo_metadesc', 'mh_control_policy_description', 1300);
add_filter('rank_math/frontend/title', 'mh_control_policy_title', 1300);
add_filter('rank_math/frontend/description', 'mh_control_policy_description', 1300);

function mh_control_policy_styles(): void {
    if (!mh_control_is_policy_page()) return;
    ?>
    <style id="mh-policy-styles">
    .mh-policy{font-family:Tahoma,Arial,sans-serif;color:#15263a;background:#fff}.mh-policy *{box-sizing:border-box}.mhpcy-shell{width:min(1080px,calc(100% - 40px));margin-inline:auto}.mhpcy-hero{padding:86px 0;background:linear-gradient(135deg,#071a33,#12345d);color:#fff}.mhpcy-hero span{display:inline-block;color:#a9c9ef;font-weight:900;margin-bottom:14px}.mhpcy-hero h1{font-size:clamp(40px,6vw,64px);color:#fff;margin:0 0 20px}.mhpcy-hero p{max-width:800px;color:#dce7f3;font-size:17px;line-height:1.9;margin:0}.mhpcy-content{padding:76px 0}.mhpcy-grid{display:grid;grid-template-columns:1fr 310px;gap:48px;align-items:start}.mhpcy-grid article>section{padding-bottom:27px;margin-bottom:27px;border-bottom:1px solid #e1e8ef}.mhpcy-grid h2{font-size:24px;color:#071a33;margin:0 0 15px}.mhpcy-grid p{color:#5f7085;line-height:1.95;margin:0 0 11px}.mhpcy-grid aside{position:sticky;top:25px;padding:27px;border-radius:15px;background:#f2f6fa;border:1px solid #dce5ee}.mhpcy-grid aside b,.mhpcy-grid aside a{display:block}.mhpcy-grid aside a{margin-top:12px;color:#1266d6;font-weight:900;text-decoration:none}.mhpcy-updated{font-size:12px;color:#7a8999!important}@media(max-width:760px){.mhpcy-shell{width:min(100% - 28px,1080px)}.mhpcy-hero{padding:62px 0}.mhpcy-content{padding:55px 0}.mhpcy-grid{grid-template-columns:1fr;gap:28px}.mhpcy-grid aside{position:static}}
    </style>
    <?php
}
add_action('wp_head', 'mh_control_policy_styles', 208);

function mh_control_trust_footer_links(): void {
    if (is_admin()) return;
    ?>
    <nav class="mh-trust-footer" dir="rtl" aria-label="روابط الثقة والسياسات">
        <a href="https://marcohom.com/services/">خدماتنا</a>
        <a href="https://marcohom.com/about/">عن ماركوز هوم</a>
        <a href="https://marcohom.com/contact/">تواصل معنا</a>
        <a href="https://marcohom.com/privacy-policy/">سياسة الخصوصية</a>
        <a href="https://marcohom.com/terms-and-conditions/">الشروط والأحكام</a>
        <a href="https://marcohom.com/shipping-and-installation/">التوصيل والتركيب</a>
        <a href="https://marcohom.com/returns-and-refunds/">الاستبدال والاسترجاع</a>
    </nav>
    <style id="mh-trust-footer-styles">.mh-trust-footer{display:flex;justify-content:center;gap:14px 24px;flex-wrap:wrap;padding:20px 24px;background:#071a33;border-top:1px solid rgba(255,255,255,.12);font-family:Tahoma,Arial,sans-serif}.mh-trust-footer a{color:#e6eef7!important;font-size:12px;font-weight:800;text-decoration:none!important}.mh-trust-footer a:hover{text-decoration:underline!important}</style>
    <?php
}
add_action('wp_footer', 'mh_control_trust_footer_links', 5);

function mh_control_render_custom_sitemap(): void {
    if (mh_control_request_path() !== '/snap-ready-pages/') return;
    $urls = [
        home_url('/'), home_url('/services/'), home_url('/portfolio/'), home_url('/about/'), home_url('/contact/'),
        home_url('/tv-tables/'), home_url('/design-198/'), home_url('/coffee-corner/'),
        home_url('/product-category/نماذج-وتصميمات/'),
        home_url('/product/باركية-خشب-k9188/'), home_url('/product/قاطع-الاعمدة/'), home_url('/fire-blaze/'),
        home_url('/privacy-policy/'), home_url('/terms-and-conditions/'), home_url('/shipping-and-installation/'), home_url('/returns-and-refunds/'),
    ];
    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    $lastmod = gmdate('c', (int) filemtime(__FILE__));
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) {
        echo '<url><loc>' . esc_url($url) . '</loc><lastmod>' . esc_html($lastmod) . '</lastmod></url>';
    }
    echo '</urlset>';
    exit;
}
add_action('template_redirect', 'mh_control_render_custom_sitemap', 2);

function mh_control_add_custom_sitemap_to_robots(string $output): string {
    $line = 'Sitemap: ' . home_url('/snap-ready-pages/');
    return str_contains($output, $line) ? $output : rtrim($output) . "\n" . $line . "\n";
}
add_filter('robots_txt', 'mh_control_add_custom_sitemap_to_robots', 99);

function mh_control_allow_search_crawlers(string $output): string {
    $groups = [
        'User-agent: OAI-SearchBot' . "\n" . 'Allow: /',
        'User-agent: ChatGPT-User' . "\n" . 'Allow: /',
        'User-agent: Googlebot' . "\n" . 'Allow: /',
        'User-agent: Bingbot' . "\n" . 'Allow: /',
    ];
    foreach ($groups as $group) {
        $agent = strtok($group, "\n");
        if ($agent !== false && !str_contains($output, $agent)) {
            $output = rtrim($output) . "\n\n" . $group . "\n";
        }
    }
    return $output;
}
add_filter('robots_txt', 'mh_control_allow_search_crawlers', 100);

function mh_control_add_custom_sitemap_index(array $indexes): array {
    $indexes[] = [
        'loc' => home_url('/snap-ready-pages/'),
        'lastmod' => gmdate('c', (int) filemtime(__FILE__)),
        'count' => 16,
    ];
    return $indexes;
}
add_filter('aioseo_sitemap_indexes', 'mh_control_add_custom_sitemap_index', 99);

/**
 * Speed and technical SEO layer for Marco's Home custom pages.
 */
function mh_control_is_lightweight_page(): bool {
    return is_front_page()
        || mh_control_is_portfolio_page()
        || mh_control_is_coffee_page()
        || mh_control_is_tv_console_page()
        || mh_control_is_fire_diffuser_page()
        || mh_control_is_wpc_divider_page()
        || mh_control_is_parquet_page()
        || mh_control_is_tv_wall_archive()
        || mh_control_is_design_198_page()
        || mh_control_is_about_page()
        || mh_control_is_services_page()
        || mh_control_is_trust_page()
        || mh_control_is_policy_page();
}

function mh_control_trim_unused_assets(): void {
    if (!mh_control_is_lightweight_page()) {
        return;
    }

    $styles = [
        'wp-block-library',
        'wp-block-library-theme',
        'wc-blocks-style',
        'wc-blocks-vendors-style',
        'wc-blocks-packages-style',
        'woocommerce-general',
        'woocommerce-layout',
        'woocommerce-smallscreen',
        'global-styles',
        'classic-theme-styles',
    ];
    foreach ($styles as $handle) {
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
    }

    $scripts = [
        'wc-cart-fragments',
        'woocommerce',
        'wc-add-to-cart',
        'jquery-blockui',
        'js-cookie',
        'wp-embed',
    ];
    foreach ($scripts as $handle) {
        wp_dequeue_script($handle);
        wp_deregister_script($handle);
    }
}
add_action('wp_enqueue_scripts', 'mh_control_trim_unused_assets', 999);

function mh_control_remove_emoji_assets(): void {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}
add_action('init', 'mh_control_remove_emoji_assets');

function mh_control_preload_hero_image(): void {
    $image = '';
    if (is_front_page()) {
        $image = 'https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg';
    } elseif (mh_control_is_about_page()) {
        $image = 'https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.20-PM-2.jpeg';
    } elseif (mh_control_is_trust_page()) {
        $image = 'https://marcohom.com/wp-content/uploads/2025/10/IMG-20251031-WA0011.jpg';
    } elseif (mh_control_is_design_198_page()) {
        $image = mh_control_design_198_asset('design-198-beige-wood.webp');
    }
    if ($image !== '') {
        printf(
            '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
            esc_url($image)
        );
    }
}
add_action('wp_head', 'mh_control_preload_hero_image', 2);

/**
 * SureRank and AIOSEO both output metadata. Keep a single, accurate set on the
 * advertising landing and policy pages, then add one local-business entity.
 */
function mh_control_capture_head_for_schema_cleanup(): void {
    ob_start();
}
add_action('wp_head', 'mh_control_capture_head_for_schema_cleanup', -999999);

function mh_control_local_business_schema(): string {
    $catalog_items = [];
    foreach (mh_control_services_data() as $position => $service) {
        $catalog_items[] = [
            '@type' => 'Offer',
            'position' => $position + 1,
            'url' => $service['url'],
            'itemOffered' => [
                '@type' => 'Service',
                'name' => $service['name'],
                'description' => $service['summary'],
                'areaServed' => 'Kuwait',
                'provider' => ['@id' => home_url('/#marcos-home')],
            ],
        ];
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'HomeAndConstructionBusiness',
        '@id' => home_url('/#marcos-home'),
        'name' => "Marco's Home",
        'alternateName' => ['ماركوز هوم', 'ماركو هوم'],
        'description' => 'ماركوز هوم لتصميم وتنفيذ الديكور الداخلي وحلول الحوائط وخلفيات الشاشة والطاولات المعلقة وأركان القهوة والباركيه وفواصل بديل الخشب داخل الكويت.',
        'url' => home_url('/'),
        'logo' => 'https://marcohom.com/wp-content/uploads/2025/03/صورة-واتساب-بتاريخ-2025-03-14-في-04.03.49_157c93e1-480x360.jpg',
        'image' => 'https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg',
        'telephone' => '+96550204320',
        'priceRange' => 'د.ك',
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+96550204320',
            'contactType' => 'customer service',
            'availableLanguage' => ['ar', 'en'],
            'areaServed' => 'KW',
        ],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'شارع نادي القادسية',
            'addressLocality' => 'حولي',
            'addressCountry' => 'KW',
        ],
        'areaServed' => [
            '@type' => 'Country',
            'name' => 'Kuwait',
        ],
        'sameAs' => [
            'https://www.instagram.com/marcoshomekw/',
            mh_control_google_maps_url(),
        ],
        'knowsAbout' => [
            'التصميم الداخلي', 'خلفيات الشاشة', 'طاولات التلفزيون المعلقة', 'أركان القهوة',
            'أرضيات الباركيه', 'فواصل بديل الخشب WPC', 'الديكور المنزلي في الكويت',
        ],
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name' => 'خدمات ماركوز هوم للديكور الداخلي في الكويت',
            'itemListElement' => $catalog_items,
        ],
    ];

    return '<script type="application/ld+json" id="mh-local-business-schema">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>' . "\n";
}

function mh_control_ad_page_seo_data(): array {
    $image = 'https://marcohom.com/wp-content/uploads/2025/09/WhatsApp-Image-2025-09-28-at-4.31.19-PM.jpeg';
    if (mh_control_is_services_page()) {
        return [
            'canonical' => home_url('/services/'),
            'title' => 'خدمات الديكور الداخلي في الكويت | ماركوز هوم',
            'description' => 'خدمات ماركوز هوم في الكويت: خلفيات شاشة، طاولات TV معلقة، أركان قهوة، باركيه، فواصل بديل الخشب وفير مائي مع خيارات القياس والتركيب.',
            'image' => $image,
            'type' => 'website',
        ];
    }
    if (mh_control_is_tv_console_page()) {
        return [
            'canonical' => home_url('/tv-tables/'),
            'title' => 'طاولات TV معلقة من ماركوز هوم | تبدأ من 40 د.ك',
            'description' => 'طاولات تلفزيون معلقة بمقاس 1.5 أو 2 متر، ارتفاع 25 سم وعمق 32 سم وسبعة ألوان. تبدأ من 40 د.ك، والتركيب داخل الكويت 10 د.ك.',
            'image' => 'https://marcohom.com/wp-content/plugins/marcos-home-control/assets/tables/table-wall-unit-white.webp',
            'type' => 'product',
        ];
    }
    if (mh_control_is_design_198_page()) {
        return [
            'canonical' => home_url('/design-198/'),
            'title' => 'تصميم 198 الخشب الهرمي | خلفية شاشة في الكويت — ماركوز هوم',
            'description' => 'تصميم 198 الخشب الهرمي بخلفية شاشة وطاولة معلقة وكابينة أرفف. ثلاث شرائح للحائط تبدأ من 130 د.ك بدون تركيب أو 170 د.ك مع التركيب داخل الكويت.',
            'image' => mh_control_design_198_asset('design-198-beige-wood.webp'),
            'type' => 'product',
        ];
    }
    if (mh_control_is_fire_diffuser_page()) {
        return [
            'canonical' => home_url('/fire-blaze/'),
            'title' => 'جهاز Fire Blaze المائي | يبدأ من 85 د.ك — ماركوز هوم',
            'description' => 'جهاز Fire Blaze ديكوري بتأثير لهب مائي بلا حرارة أو دخان، يعمل بالماء والكهرباء. خمسة مقاسات من 40 سم إلى 1.50 متر وأسعار من 85 إلى 270 د.ك داخل الكويت.',
            'image' => 'https://marcohom.com/wp-content/uploads/2025/11/Art-Fireplace-AFW230-3D-Water-Vapor-Fireplace-product.webp',
            'type' => 'product',
        ];
    }
    if (mh_control_is_policy_page()) {
        $pages = mh_control_policy_pages();
        $page = $pages[mh_control_policy_slug()];
        return [
            'canonical' => home_url('/' . mh_control_policy_slug() . '/'),
            'title' => $page['title'],
            'description' => $page['description'],
            'image' => $image,
            'type' => 'website',
        ];
    }
    return [];
}

function mh_control_special_page_schema(array $seo): string {
    if ($seo === []) return '';
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $seo['canonical'] . '#webpage',
        'url' => $seo['canonical'],
        'name' => $seo['title'],
        'description' => $seo['description'],
        'inLanguage' => 'ar',
        'isPartOf' => ['@id' => home_url('/#website')],
        'about' => ['@id' => home_url('/#marcos-home')],
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $seo['image']],
    ];
    return '<script type="application/ld+json" id="mh-special-webpage-schema">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>' . "\n";
}

function mh_control_services_structured_data(): string {
    if (!mh_control_is_services_page()) return '';
    $items = [];
    foreach (mh_control_services_data() as $position => $service) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position + 1,
            'url' => $service['url'],
            'name' => $service['name'],
        ];
    }
    $questions = [
        ['ما الخدمات التي تقدمها ماركوز هوم؟', 'نقدم خلفيات شاشة وديكور TV، طاولات تلفزيون معلقة، أركان قهوة، أرضيات باركيه، فواصل بديل الخشب وأجهزة فير مائي، مع تصميمات قابلة للتخصيص داخل الكويت.'],
        ['هل يتوفر القياس والتركيب داخل الكويت؟', 'نعم، يتوفر القياس والتركيب وفق نوع الخدمة والمنطقة وتجهيزات الموقع. يتم تأكيد المطلوب والسعر والموعد قبل بدء التنفيذ.'],
        ['كيف أحصل على اقتراح وسعر مناسب؟', 'أرسل صورة المكان والمقاسات المتاحة على واتساب، وحدد الخدمة المطلوبة. نراجع المساحة ونوضح الاختيارات والسعر وما يشمله التركيب.'],
        ['هل يمكن تغيير المقاس أو اللون؟', 'تتوفر خيارات متعددة حسب المنتج، وبعض التصميمات تنفذ حسب المقاس. يجب تأكيد المقاس واللون والخامة قبل التصنيع أو التوريد.'],
    ];
    $faq = [];
    foreach ($questions as $question) {
        $faq[] = [
            '@type' => 'Question',
            'name' => $question[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $question[1]],
        ];
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'ItemList',
                '@id' => home_url('/services/#service-list'),
                'name' => 'خدمات ماركوز هوم للديكور الداخلي في الكويت',
                'numberOfItems' => count($items),
                'itemListElement' => $items,
            ],
            [
                '@type' => 'FAQPage',
                '@id' => home_url('/services/#faq'),
                'mainEntity' => $faq,
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => home_url('/services/#breadcrumb'),
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'الرئيسية', 'item' => home_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'خدماتنا', 'item' => home_url('/services/')],
                ],
            ],
        ],
    ];
    return '<script type="application/ld+json" id="mh-services-schema">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>' . "\n";
}

function mh_control_fire_structured_data(): string {
    if (!mh_control_is_fire_diffuser_page()) return '';
    $canonical = home_url('/fire-blaze/');
    $questions = [
        ['هل جهاز Fire Blaze مصدر تدفئة؟', 'لا. الجهاز يقدم تأثير لهب بصري ببخار الماء والإضاءة، وليس مدفأة أو مصدرًا للحرارة.'],
        ['كيف يعمل جهاز Fire Blaze؟', 'يعمل بالماء مع توصيل كهربائي مناسب، ويتم توضيح تعليمات التشغيل والتجهيز قبل تأكيد الطلب.'],
        ['ما المقاسات والأسعار المتوفرة؟', 'تتوفر خمسة مقاسات: 40 سم بسعر 85 د.ك، 70 سم بسعر 135 د.ك، متر بسعر 180 د.ك، 1.20 متر بسعر 220 د.ك، و1.50 متر بسعر 270 د.ك.'],
    ];
    $faq = [];
    foreach ($questions as $question) {
        $faq[] = ['@type' => 'Question', 'name' => $question[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $question[1]]];
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Product',
                '@id' => $canonical . '#product',
                'name' => 'جهاز Fire Blaze المائي',
                'description' => 'جهاز ديكوري بتأثير لهب مائي ثلاثي الأبعاد بلا حرارة أو دخان، متوفر بخمسة مقاسات داخل الكويت.',
                'image' => ['https://marcohom.com/wp-content/uploads/2025/11/Art-Fireplace-AFW230-3D-Water-Vapor-Fireplace-product.webp'],
                'brand' => ['@type' => 'Brand', 'name' => "Marco's Home"],
                'offers' => [
                    '@type' => 'AggregateOffer',
                    'url' => $canonical,
                    'priceCurrency' => 'KWD',
                    'lowPrice' => '85',
                    'highPrice' => '270',
                    'offerCount' => '5',
                    'availability' => 'https://schema.org/InStock',
                ],
            ],
            ['@type' => 'FAQPage', '@id' => $canonical . '#faq', 'mainEntity' => $faq],
            [
                '@type' => 'BreadcrumbList',
                '@id' => $canonical . '#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'الرئيسية', 'item' => home_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'جهاز Fire Blaze', 'item' => $canonical],
                ],
            ],
        ],
    ];
    return '<script type="application/ld+json" id="mh-fire-schema">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>' . "\n";
}

function mh_control_design_198_structured_data(): string {
    if (!mh_control_is_design_198_page()) return '';
    $canonical = home_url('/design-198/');
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Product',
                '@id' => $canonical . '#product',
                'name' => 'تصميم 198 — الخشب الهرمي',
                    'description' => 'خلفية شاشة متكاملة بتكوين هندسي وطاولة معلقة وكابينة أرفف بإضاءة داخلية، مع خيارات سعر منفصلة بدون تركيب ومع التركيب داخل الكويت.',
                'image' => [
                    mh_control_design_198_asset('design-198-beige-wood.webp'),
                    mh_control_design_198_asset('design-198-white.webp'),
                    mh_control_design_198_asset('design-198-charcoal.webp'),
                ],
                'brand' => ['@type' => 'Brand', 'name' => "Marco's Home"],
                'offers' => [
                    '@type' => 'AggregateOffer',
                    'url' => $canonical,
                    'priceCurrency' => 'KWD',
                    'lowPrice' => '130',
                    'highPrice' => '210',
                    'offerCount' => '6',
                    'availability' => 'https://schema.org/InStock',
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => $canonical . '#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'الرئيسية', 'item' => home_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'خلفيات الشاشة', 'item' => home_url('/product-category/%d9%86%d9%85%d8%a7%d8%b0%d8%ac-%d9%88%d8%aa%d8%b5%d9%85%d9%8a%d9%85%d8%a7%d8%aa/')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => 'تصميم 198', 'item' => $canonical],
                ],
            ],
        ],
    ];
    return '<script type="application/ld+json" id="mh-design-198-schema">'
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . '</script>' . "\n";
}

function mh_control_finish_head_schema_cleanup(): void {
    $head = (string) ob_get_clean();
    $head = (string) preg_replace(
        '#<!--\s*SureRank Meta Data\s*-->.*?<!--\s*/SureRank Meta Data\s*-->#is',
        '',
        $head
    );
    $head = (string) preg_replace(
        '#<script[^>]*id=(["\\\'])surerank-schema\\1[^>]*>.*?</script>#is',
        '',
        $head
    );

    $seo = mh_control_ad_page_seo_data();
    if ($seo !== []) {
        $head = (string) preg_replace('#<script[^>]*class=(["\\\'])[^"\\\']*aioseo-schema[^"\\\']*\\1[^>]*>.*?</script>#is', '', $head);
        $head = (string) preg_replace('#<link\\b[^>]*\\brel=(["\\\'])canonical\\1[^>]*>\\s*#i', '', $head);
        $head = (string) preg_replace(
            '#<meta\\b[^>]*(?:name|property)=(["\\\'])(?:description|robots|og:title|og:description|og:url|og:type|og:image|og:image:secure_url|og:image:width|og:image:height|twitter:card|twitter:title|twitter:description|twitter:image|twitter:label1|twitter:data1|twitter:label2|twitter:data2|product:price:amount|product:price:currency|product:availability)\\1[^>]*>\\s*#i',
            '',
            $head
        );
        $head .= '<meta name="description" content="' . esc_attr($seo['description']) . '">' . "\n";
        $head .= '<meta name="robots" content="index, follow, max-image-preview:large">' . "\n";
        $head .= '<link rel="canonical" href="' . esc_url($seo['canonical']) . '">' . "\n";
        $head .= '<meta property="og:locale" content="ar_AR">' . "\n";
        $head .= '<meta property="og:site_name" content="Marco\'s Home | ماركوز هوم">' . "\n";
        $head .= '<meta property="og:type" content="' . esc_attr($seo['type']) . '">' . "\n";
        $head .= '<meta property="og:title" content="' . esc_attr($seo['title']) . '">' . "\n";
        $head .= '<meta property="og:description" content="' . esc_attr($seo['description']) . '">' . "\n";
        $head .= '<meta property="og:url" content="' . esc_url($seo['canonical']) . '">' . "\n";
        $head .= '<meta property="og:image" content="' . esc_url($seo['image']) . '">' . "\n";
        $head .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $head .= '<meta name="twitter:title" content="' . esc_attr($seo['title']) . '">' . "\n";
        $head .= '<meta name="twitter:description" content="' . esc_attr($seo['description']) . '">' . "\n";
        $head .= '<meta name="twitter:image" content="' . esc_url($seo['image']) . '">' . "\n";
    }
    $head .= '<meta name="google-site-verification" content="SjWnIYjrIaKvrg76WMbxQ-BSOPVUjb1EASMCIIARO4k">' . "\n";
    echo $head; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo mh_control_local_business_schema(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo mh_control_special_page_schema($seo); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo mh_control_services_structured_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo mh_control_fire_structured_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo mh_control_design_198_structured_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('wp_head', 'mh_control_finish_head_schema_cleanup', 999999);

/**
 * Keep the custom TV console page out of page/CDN cache so new gallery
 * releases remain visible when visitors return through the normal URL.
 */
function mh_control_disable_tv_console_cache(): void {
    if (!mh_control_is_tv_console_page() && !mh_control_is_design_198_page() && !mh_control_is_tv_wall_archive()) {
        return;
    }

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!defined('DONOTCACHEOBJECT')) {
        define('DONOTCACHEOBJECT', true);
    }

    nocache_headers();
    if (!headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
        header('X-LiteSpeed-Cache-Control: no-cache', true);
    }
    do_action('litespeed_control_set_nocache', 'Marco Home product gallery release');
}
add_action('template_redirect', 'mh_control_disable_tv_console_cache', 1);

/**
 * Purge LiteSpeed once after a newly deployed plugin version.
 */
function mh_control_purge_cache_after_deploy(): void {
    $deployed_version = (string) get_option('mh_control_deployed_version', '');
    if ($deployed_version === MH_CONTROL_VERSION) {
        return;
    }

    if (!headers_sent()) {
        header('X-LiteSpeed-Purge: *', false);
    }

    do_action('litespeed_purge_all');
    do_action('litespeed_purge_all_object');

    $tv_console_url = get_permalink(6455);
    if (is_string($tv_console_url) && $tv_console_url !== '') {
        do_action('litespeed_purge_url', $tv_console_url);
    }

    do_action('litespeed_purge_url', home_url('/design-198/'));
    do_action('litespeed_purge_url', home_url('/product-category/نماذج-وتصميمات/'));

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    update_option('mh_control_deployed_version', MH_CONTROL_VERSION, false);
}
add_action('wp_loaded', 'mh_control_purge_cache_after_deploy', 999);
