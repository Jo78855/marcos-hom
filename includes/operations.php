<?php
/**
 * Marco's Home operations foundation.
 *
 * WordPress remains the public marketing site and owns the operational records.
 * This module adds a unified admin dashboard plus installable customer/team shells.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MH_OPS_DB_VERSION', '1.5.0');

function mh_ops_table(string $name): string {
    global $wpdb;
    return $wpdb->prefix . 'mh_' . $name;
}

function mh_ops_install_schema(): void {
    if ((string) get_option('mh_ops_db_version', '') === MH_OPS_DB_VERSION) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $customers = mh_ops_table('customers');
    $orders = mh_ops_table('orders');
    $job_media = mh_ops_table('job_media');

    dbDelta("CREATE TABLE {$customers} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        name varchar(120) NOT NULL,
        phone varchar(24) NOT NULL,
        area varchar(120) NOT NULL DEFAULT '',
        address text NOT NULL,
        notes text NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY phone (phone),
        KEY created_at (created_at)
    ) {$charset};");

    dbDelta("CREATE TABLE {$orders} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        order_code varchar(24) NOT NULL,
        customer_id bigint(20) unsigned NOT NULL,
        lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
        product varchar(100) NOT NULL,
        design varchar(120) NOT NULL DEFAULT '',
        wall_width decimal(8,2) NOT NULL DEFAULT 0,
        table_width decimal(8,2) NOT NULL DEFAULT 0,
        color varchar(80) NOT NULL DEFAULT '',
        installation varchar(30) NOT NULL DEFAULT '',
        total decimal(10,3) NOT NULL DEFAULT 0,
        deposit decimal(10,3) NOT NULL DEFAULT 0,
        balance decimal(10,3) NOT NULL DEFAULT 0,
        payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
        paid_at datetime NULL,
        source varchar(80) NOT NULL DEFAULT 'direct',
        medium varchar(80) NOT NULL DEFAULT '',
        campaign varchar(120) NOT NULL DEFAULT '',
        status varchar(40) NOT NULL DEFAULT 'new',
        technician_id bigint(20) unsigned NOT NULL DEFAULT 0,
        technician_name varchar(120) NOT NULL DEFAULT '',
        technician_phone varchar(24) NOT NULL DEFAULT '',
        scheduled_at datetime NULL,
        completed_at datetime NULL,
        technician_note text NOT NULL,
        client_received_at datetime NULL,
        client_rating tinyint(3) unsigned NOT NULL DEFAULT 0,
        client_note text NOT NULL,
        notes text NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_code (order_code),
        KEY lead_id (lead_id),
        KEY customer_id (customer_id),
        KEY status (status),
        KEY payment_status (payment_status),
        KEY scheduled_at (scheduled_at),
        KEY technician_id (technician_id)
    ) {$charset};");

    dbDelta("CREATE TABLE {$job_media} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id bigint(20) unsigned NOT NULL,
        attachment_id bigint(20) unsigned NOT NULL,
        media_type varchar(20) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY media_type (media_type)
    ) {$charset};");

    // Technicians never need a WordPress account. Access is granted per job
    // through a signed, revocable link sent by the administrator on WhatsApp.
    if (get_role('mh_technician')) remove_role('mh_technician');
    update_option('mh_ops_db_version', MH_OPS_DB_VERSION, false);
}
add_action('plugins_loaded', 'mh_ops_install_schema', 30);

function mh_ops_statuses(): array {
    return [
        'new' => 'طلب جديد',
        'contacted' => 'تم التواصل',
        'survey' => 'معاينة',
        'quoted' => 'عرض سعر',
        'confirmed' => 'تم التأكيد',
        'preparing' => 'جاري التجهيز',
        'scheduled' => 'محدد للتركيب',
        'enroute' => 'الفني في الطريق',
        'working' => 'بدأ التنفيذ',
        'issue' => 'توجد مشكلة',
        'completed' => 'تم التنفيذ',
        'received' => 'استلم العميل الأعمال',
        'collected' => 'تم التحصيل',
        'cancelled' => 'ملغي',
    ];
}

function mh_ops_payment_statuses(): array {
    return [
        'unpaid' => 'غير مدفوع',
        'paid' => 'مدفوع بالكامل',
    ];
}

function mh_ops_phone_digits(string $phone): string {
    $digits = (string) preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 8) $digits = '965' . $digits;
    return $digits;
}

function mh_ops_job_access_key(array $order): string {
    $payload = absint($order['id'] ?? 0) . '|' . mh_ops_phone_digits((string) ($order['technician_phone'] ?? ''));
    return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function mh_ops_job_url(array $order): string {
    if (absint($order['id'] ?? 0) < 1 || mh_ops_phone_digits((string) ($order['technician_phone'] ?? '')) === '') return home_url('/marcos-team/');
    return add_query_arg(['job' => absint($order['id']), 'access' => mh_ops_job_access_key($order)], home_url('/marcos-team/'));
}

function mh_ops_customer_access_key(array $order): string {
    $payload = absint($order['id'] ?? 0) . '|' . mh_ops_phone_digits((string) ($order['customer_phone'] ?? '')) . '|customer';
    return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function mh_ops_customer_url(array $order): string {
    if (absint($order['id'] ?? 0) < 1 || mh_ops_phone_digits((string) ($order['customer_phone'] ?? '')) === '') return home_url('/marcos-app/');
    return add_query_arg(['order' => absint($order['id']), 'access' => mh_ops_customer_access_key($order)], home_url('/marcos-app/'));
}

function mh_ops_status_from_lead(string $status): string {
    return [
        'new' => 'new',
        'contacted' => 'contacted',
        'quoted' => 'quoted',
        'won' => 'confirmed',
        'closed' => 'cancelled',
    ][$status] ?? 'new';
}

function mh_ops_product_label(string $product): string {
    return [
        'fire-blaze' => 'جهاز Fire Blaze',
        'tv-tables' => 'طاولة TV معلقة',
        'design-198' => 'تصميم 198 — الخشب الهرمي',
    ][$product] ?? $product;
}

function mh_ops_sync_lead(array $lead): int {
    $lead_id = absint($lead['id'] ?? 0);
    $phone = sanitize_text_field((string) ($lead['phone'] ?? ''));
    if ($lead_id < 1 || $phone === '') return 0;

    global $wpdb;
    mh_ops_install_schema();
    $customers = mh_ops_table('customers');
    $orders = mh_ops_table('orders');
    $now = current_time('mysql');
    $customer_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$customers} WHERE phone=%s LIMIT 1", $phone));
    $customer_data = [
        'updated_at' => $now,
        'name' => sanitize_text_field((string) ($lead['name'] ?? 'عميل Marco’s Home')),
        'phone' => $phone,
        'area' => sanitize_text_field((string) ($lead['area'] ?? '')),
    ];
    if ($customer_id > 0) {
        $wpdb->update($customers, $customer_data, ['id' => $customer_id], ['%s', '%s', '%s', '%s'], ['%d']);
    } else {
        $customer_data['created_at'] = (string) ($lead['created_at'] ?? $now);
        $customer_data['address'] = '';
        $customer_data['notes'] = '';
        $wpdb->insert($customers, $customer_data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        $customer_id = (int) $wpdb->insert_id;
    }
    if ($customer_id < 1) return 0;

    $existing_order = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$orders} WHERE lead_id=%d LIMIT 1", $lead_id));
    $order_data = [
        'updated_at' => $now,
        'customer_id' => $customer_id,
        'product' => mh_ops_product_label(sanitize_key((string) ($lead['product'] ?? ''))),
        'source' => sanitize_text_field((string) ($lead['source'] ?? 'direct')),
        'medium' => sanitize_text_field((string) ($lead['medium'] ?? '')),
        'campaign' => sanitize_text_field((string) ($lead['campaign'] ?? '')),
        'status' => mh_ops_status_from_lead(sanitize_key((string) ($lead['status'] ?? 'new'))),
        'notes' => sanitize_textarea_field((string) ($lead['details'] ?? '')),
    ];
    if ($existing_order > 0) {
        $wpdb->update($orders, $order_data, ['id' => $existing_order], ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'], ['%d']);
        return $existing_order;
    }

    $order_data += [
        'created_at' => (string) ($lead['created_at'] ?? $now),
        'order_code' => 'MH-' . str_pad((string) $lead_id, 6, '0', STR_PAD_LEFT),
        'lead_id' => $lead_id,
        'design' => '',
        'wall_width' => 0,
        'table_width' => 0,
        'color' => '',
        'installation' => '',
        'total' => 0,
        'deposit' => 0,
        'balance' => 0,
        'payment_status' => 'unpaid',
        'paid_at' => null,
        'technician_id' => 0,
        'technician_name' => '',
        'technician_phone' => '',
        'scheduled_at' => null,
        'completed_at' => null,
        'technician_note' => '',
        'client_received_at' => null,
        'client_rating' => 0,
        'client_note' => '',
    ];
    $inserted = $wpdb->insert($orders, $order_data);
    return $inserted === false ? 0 : (int) $wpdb->insert_id;
}
add_action('mh_control_lead_created', 'mh_ops_sync_lead');

function mh_ops_sync_lead_status(int $lead_id, string $status): void {
    global $wpdb;
    $wpdb->update(
        mh_ops_table('orders'),
        ['status' => mh_ops_status_from_lead($status), 'updated_at' => current_time('mysql')],
        ['lead_id' => $lead_id],
        ['%s', '%s'],
        ['%d']
    );
}
add_action('mh_control_lead_status_updated', 'mh_ops_sync_lead_status', 10, 2);

function mh_ops_backfill_existing_leads(): void {
    if (!current_user_can('manage_options') || !function_exists('mh_control_leads_table')) return;
    global $wpdb;
    $leads = $wpdb->get_results('SELECT * FROM ' . mh_control_leads_table() . ' ORDER BY id ASC LIMIT 500', ARRAY_A);
    if (!is_array($leads)) return;
    foreach ($leads as $lead) mh_ops_sync_lead($lead);
}
add_action('admin_init', 'mh_ops_backfill_existing_leads', 30);

function mh_ops_menu(): void {
    add_menu_page(
        'إدارة ماركوز هوم',
        'ماركوز هوم',
        'manage_options',
        'mh-operations',
        'mh_ops_render_dashboard',
        'dashicons-admin-home',
        3
    );
    add_submenu_page('mh-operations', 'لوحة التشغيل', 'لوحة التشغيل', 'manage_options', 'mh-operations', 'mh_ops_render_dashboard');
    add_submenu_page('mh-operations', 'الطلبات', 'الطلبات', 'manage_options', 'mh-orders', 'mh_ops_render_orders');
    add_submenu_page('mh-operations', 'العملاء', 'العملاء', 'manage_options', 'mh-customers', 'mh_ops_render_customers');
    add_submenu_page('mh-operations', 'التركيبات', 'التركيبات', 'manage_options', 'mh-installations', 'mh_ops_render_installations');
}
add_action('admin_menu', 'mh_ops_menu');

function mh_ops_admin_save_order(): void {
    if (!current_user_can('manage_options')) wp_die('غير مسموح.');
    check_admin_referer('mh_ops_save_order');
    $order_id = absint($_POST['order_id'] ?? 0);
    if ($order_id < 1) {
        wp_safe_redirect(admin_url('admin.php?page=mh-orders'));
        exit;
    }

    $statuses = mh_ops_statuses();
    $payments = mh_ops_payment_statuses();
    $status = sanitize_key((string) ($_POST['status'] ?? 'new'));
    $payment_status = sanitize_key((string) ($_POST['payment_status'] ?? 'unpaid'));
    if (!isset($statuses[$status])) $status = 'new';
    if (!isset($payments[$payment_status])) $payment_status = 'unpaid';
    $scheduled = sanitize_text_field((string) ($_POST['scheduled_at'] ?? ''));
    $scheduled_at = $scheduled !== '' ? str_replace('T', ' ', $scheduled) . (strlen($scheduled) === 16 ? ':00' : '') : null;
    $total = max(0, (float) ($_POST['total'] ?? 0));
    $paid_at = $payment_status === 'paid' ? current_time('mysql') : null;

    global $wpdb;
    $wpdb->update(mh_ops_table('orders'), [
        'updated_at' => current_time('mysql'),
        'status' => $status,
        'total' => $total,
        'deposit' => 0,
        'balance' => $payment_status === 'paid' ? 0 : $total,
        'payment_status' => $payment_status,
        'paid_at' => $paid_at,
        'technician_id' => 0,
        'technician_name' => sanitize_text_field((string) ($_POST['technician_name'] ?? '')),
        'technician_phone' => sanitize_text_field((string) ($_POST['technician_phone'] ?? '')),
        'scheduled_at' => $scheduled_at,
        'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
    ], ['id' => $order_id]);
    wp_safe_redirect(add_query_arg(['page' => 'mh-orders', 'order_id' => $order_id, 'updated' => 1], admin_url('admin.php')));
    exit;
}
add_action('admin_post_mh_ops_save_order', 'mh_ops_admin_save_order');

function mh_ops_technician_update(): void {
    $order_id = absint($_POST['order_id'] ?? 0);
    $access = sanitize_text_field((string) ($_POST['access'] ?? ''));
    $status = sanitize_key((string) ($_POST['status'] ?? ''));
    $allowed = ['enroute', 'working', 'completed', 'issue'];
    if ($order_id < 1 || !in_array($status, $allowed, true)) {
        wp_safe_redirect(home_url('/marcos-team/'));
        exit;
    }
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare('SELECT id, technician_phone FROM ' . mh_ops_table('orders') . ' WHERE id=%d LIMIT 1', $order_id), ARRAY_A);
    if (!is_array($order) || $access === '' || !hash_equals(mh_ops_job_access_key($order), $access)) wp_die('رابط المهمة غير صالح.');
    check_admin_referer('mh_ops_job_update_' . $order_id . '_' . $access);
    $note = sanitize_textarea_field(wp_unslash((string) ($_POST['technician_note'] ?? '')));
    $data = ['status' => $status, 'updated_at' => current_time('mysql'), 'technician_note' => $note];
    if ($status === 'completed') $data['completed_at'] = current_time('mysql');
    $wpdb->update(mh_ops_table('orders'), $data, ['id' => $order_id]);
    $upload_result = mh_ops_save_job_photos($order_id);
    $redirect_args = ['job_updated' => 1];
    if (is_wp_error($upload_result)) $redirect_args['photo_error'] = $upload_result->get_error_code();
    elseif ($upload_result > 0) $redirect_args['photos_added'] = $upload_result;
    wp_safe_redirect(add_query_arg($redirect_args, mh_ops_job_url($order)));
    exit;
}
add_action('admin_post_mh_ops_technician_update', 'mh_ops_technician_update');
add_action('admin_post_nopriv_mh_ops_technician_update', 'mh_ops_technician_update');

function mh_ops_customer_feedback(): void {
    $order_id = absint($_POST['order_id'] ?? 0);
    $access = sanitize_text_field((string) ($_POST['access'] ?? ''));
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare(
        'SELECT o.*, c.phone customer_phone FROM ' . mh_ops_table('orders') . ' o INNER JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id WHERE o.id=%d LIMIT 1',
        $order_id
    ), ARRAY_A);
    if (!is_array($order) || $access === '' || !hash_equals(mh_ops_customer_access_key($order), $access)) wp_die('رابط الطلب غير صالح.');
    check_admin_referer('mh_ops_customer_feedback_' . $order_id . '_' . $access);
    if (!in_array((string) ($order['status'] ?? ''), ['completed', 'received', 'collected'], true)) {
        wp_safe_redirect(add_query_arg('feedback_error', 'not_completed', mh_ops_customer_url($order)));
        exit;
    }
    $rating = max(1, min(5, absint($_POST['client_rating'] ?? 0)));
    $note = sanitize_textarea_field(wp_unslash((string) ($_POST['client_note'] ?? '')));
    $data = [
        'updated_at' => current_time('mysql'),
        'client_received_at' => current_time('mysql'),
        'client_rating' => $rating,
        'client_note' => $note,
    ];
    if ((string) ($order['status'] ?? '') !== 'collected') $data['status'] = 'received';
    $wpdb->update(mh_ops_table('orders'), $data, ['id' => $order_id]);
    $upload_result = mh_ops_save_client_photo($order_id);
    $args = ['feedback_saved' => 1];
    if (is_wp_error($upload_result)) $args['client_photo_error'] = $upload_result->get_error_code();
    wp_safe_redirect(add_query_arg($args, mh_ops_customer_url($order)));
    exit;
}
add_action('admin_post_mh_ops_customer_feedback', 'mh_ops_customer_feedback');
add_action('admin_post_nopriv_mh_ops_customer_feedback', 'mh_ops_customer_feedback');

function mh_ops_save_client_photo(int $order_id) {
    if (empty($_FILES['client_photo']) || (int) ($_FILES['client_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return 0;
    if ((int) ($_FILES['client_photo']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return new WP_Error('upload_failed');
    if ((int) ($_FILES['client_photo']['size'] ?? 0) > 8 * MB_IN_BYTES) return new WP_Error('too_large');
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $file = [
        'name' => sanitize_file_name((string) ($_FILES['client_photo']['name'] ?? 'receipt.jpg')),
        'type' => sanitize_mime_type((string) ($_FILES['client_photo']['type'] ?? '')),
        'tmp_name' => (string) ($_FILES['client_photo']['tmp_name'] ?? ''),
        'error' => (int) ($_FILES['client_photo']['error'] ?? UPLOAD_ERR_NO_FILE),
        'size' => (int) ($_FILES['client_photo']['size'] ?? 0),
    ];
    $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], [
        'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    ]);
    if (empty($checked['type']) || !str_starts_with((string) $checked['type'], 'image/')) return new WP_Error('invalid_type');
    $handled = wp_handle_upload($file, ['test_form' => false, 'mimes' => [
        'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    ]]);
    if (!empty($handled['error']) || empty($handled['file'])) return new WP_Error('upload_failed');
    $attachment_id = wp_insert_attachment([
        'post_mime_type' => (string) $handled['type'],
        'post_title' => 'MH ' . $order_id . ' client receipt',
        'post_content' => '',
        'post_status' => 'inherit',
    ], (string) $handled['file']);
    if (is_wp_error($attachment_id) || $attachment_id < 1) return new WP_Error('upload_failed');
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, (string) $handled['file']));
    global $wpdb;
    $wpdb->insert(mh_ops_table('job_media'), [
        'order_id' => $order_id,
        'attachment_id' => $attachment_id,
        'media_type' => 'client',
        'created_at' => current_time('mysql'),
    ], ['%d', '%d', '%s', '%s']);
    return 1;
}

function mh_ops_save_job_photos(int $order_id) {
    if (empty($_FILES['job_photos']) || !is_array($_FILES['job_photos']['name'] ?? null)) return 0;
    $type = sanitize_key((string) ($_POST['photo_type'] ?? 'before'));
    if (!in_array($type, ['before', 'after', 'issue'], true)) $type = 'before';
    $names = (array) $_FILES['job_photos']['name'];
    $count = min(5, count($names));
    $saved = 0;
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    for ($i = 0; $i < $count; $i++) {
        if ((int) ($_FILES['job_photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ((int) ($_FILES['job_photos']['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return new WP_Error('upload_failed');
        if ((int) ($_FILES['job_photos']['size'][$i] ?? 0) > 8 * MB_IN_BYTES) return new WP_Error('too_large');
        $file = [
            'name' => sanitize_file_name((string) ($_FILES['job_photos']['name'][$i] ?? 'photo.jpg')),
            'type' => sanitize_mime_type((string) ($_FILES['job_photos']['type'][$i] ?? '')),
            'tmp_name' => (string) ($_FILES['job_photos']['tmp_name'][$i] ?? ''),
            'error' => (int) ($_FILES['job_photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($_FILES['job_photos']['size'][$i] ?? 0),
        ];
        $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ]);
        if (empty($checked['type']) || !str_starts_with((string) $checked['type'], 'image/')) return new WP_Error('invalid_type');
        $handled = wp_handle_upload($file, ['test_form' => false, 'mimes' => [
            'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        ]]);
        if (!empty($handled['error']) || empty($handled['file'])) return new WP_Error('upload_failed');
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => (string) $handled['type'],
            'post_title' => 'MH ' . $order_id . ' ' . $type,
            'post_content' => '',
            'post_status' => 'inherit',
        ], (string) $handled['file']);
        if (is_wp_error($attachment_id) || $attachment_id < 1) return new WP_Error('upload_failed');
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, (string) $handled['file']));
        global $wpdb;
        $wpdb->insert(mh_ops_table('job_media'), [
            'order_id' => $order_id,
            'attachment_id' => $attachment_id,
            'media_type' => $type,
            'created_at' => current_time('mysql'),
        ], ['%d', '%d', '%s', '%s']);
        $saved++;
    }
    return $saved;
}

function mh_ops_get_job_media(int $order_id, array $types = []): array {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . mh_ops_table('job_media') . ' WHERE order_id=%d ORDER BY id ASC', $order_id), ARRAY_A);
    if (!is_array($rows)) return [];
    if ($types === []) return $rows;
    return array_values(array_filter($rows, static fn($row) => in_array((string) ($row['media_type'] ?? ''), $types, true)));
}

function mh_ops_media_type_label(string $type): string {
    return ['before' => 'قبل التنفيذ', 'after' => 'بعد التنفيذ', 'issue' => 'مشكلة في التنفيذ', 'client' => 'صورة من العميل'][$type] ?? 'صورة تنفيذ';
}

function mh_ops_render_job_media(int $order_id, bool $admin = false, array $types = []): void {
    $media = mh_ops_get_job_media($order_id, $types);
    if ($media === []) {
        if ($admin) echo '<p class="mhops-empty">لم يرفع الفني صورًا لهذه المهمة بعد.</p>';
        return;
    }
    echo '<div class="' . ($admin ? 'mhops-media' : 'mhop-media') . '">';
    foreach ($media as $item) {
        $image = wp_get_attachment_image(absint($item['attachment_id'] ?? 0), 'medium', false, ['loading' => 'lazy']);
        if ($image === '') continue;
        echo '<figure>' . $image . '<figcaption>' . esc_html(mh_ops_media_type_label((string) ($item['media_type'] ?? ''))) . '</figcaption></figure>';
    }
    echo '</div>';
}

function mh_ops_admin_header(string $title, string $subtitle): void {
    ?>
    <div class="wrap mhops" dir="rtl">
        <div class="mhops-title"><div><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($subtitle); ?></p></div><div class="mhops-links"><a class="button" href="<?php echo esc_url(home_url('/marcos-app/')); ?>" target="_blank">بوابة العميل</a><a class="button" href="<?php echo esc_url(home_url('/marcos-team/')); ?>" target="_blank">بوابة الفني</a></div></div>
    <?php
}

function mh_ops_admin_footer(): void {
    ?>
    </div>
    <style>
    .mhops{max-width:1400px}.mhops-title{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:22px 0}.mhops-title h1{font-size:30px;font-weight:900}.mhops-title p{color:#646970}.mhops-links{display:flex;gap:8px}.mhops-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin:22px 0}.mhops-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:22px}.mhops-card span{display:block;color:#646970;font-weight:700}.mhops-card strong{display:block;margin-top:8px;font-size:31px;color:#071a33}.mhops-panel{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:20px;margin-top:18px}.mhops-table{width:100%;border-collapse:collapse}.mhops-table th,.mhops-table td{text-align:right;padding:12px;border-bottom:1px solid #eee}.mhops-empty{text-align:center;padding:38px;color:#646970}.mhops-badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#eaf3ff;color:#0764c7;font-weight:800}.mhops-paid{background:#e9f8ef;color:#16783b}.mhops-unpaid{background:#fff1e8;color:#a94700}.mhops-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.mhops-form label{font-weight:800}.mhops-form input,.mhops-form select,.mhops-form textarea{display:block;width:100%;box-sizing:border-box;margin-top:7px}.mhops-form textarea{min-height:110px}.mhops-form .wide{grid-column:1/-1}.mhops-actions{display:flex;gap:10px;align-items:center;margin-top:18px}.mhops-media{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mhops-media figure{margin:0;border:1px solid #dcdcde;border-radius:12px;overflow:hidden}.mhops-media img{display:block;width:100%;height:180px;object-fit:cover}.mhops-media figcaption{padding:9px;font-weight:800}@media(max-width:1000px){.mhops-cards{grid-template-columns:repeat(2,1fr)}.mhops-media{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.mhops-title{align-items:flex-start;flex-direction:column}.mhops-cards,.mhops-form,.mhops-media{grid-template-columns:1fr}.mhops-form .wide{grid-column:auto}}
    </style>
    <?php
}

function mh_ops_counts(): array {
    global $wpdb;
    $orders = mh_ops_table('orders');
    $customers = mh_ops_table('customers');
    $today = current_time('Y-m-d');
    return [
        'new' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders} WHERE status = %s", 'new')),
        'survey' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders} WHERE status = %s", 'survey')),
        'scheduled' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$orders} WHERE DATE(scheduled_at) = %s", $today)),
        'customers' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$customers}"),
        'unpaid' => (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$orders} WHERE payment_status='unpaid' AND status != 'cancelled'"),
    ];
}

function mh_ops_render_dashboard(): void {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $counts = mh_ops_counts();
    $orders = $wpdb->get_results('SELECT o.*, c.name customer_name, c.phone customer_phone FROM ' . mh_ops_table('orders') . ' o LEFT JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id ORDER BY o.created_at DESC LIMIT 12', ARRAY_A);
    $statuses = mh_ops_statuses();
    mh_ops_admin_header('لوحة تشغيل Marco’s Home', 'نظرة سريعة على الطلبات والعملاء والتركيبات والتحصيل.');
    ?>
    <div class="mhops-cards">
        <div class="mhops-card"><span>طلبات جديدة</span><strong><?php echo esc_html(number_format_i18n($counts['new'])); ?></strong></div>
        <div class="mhops-card"><span>معاينات</span><strong><?php echo esc_html(number_format_i18n($counts['survey'])); ?></strong></div>
        <div class="mhops-card"><span>تركيبات اليوم</span><strong><?php echo esc_html(number_format_i18n($counts['scheduled'])); ?></strong></div>
        <div class="mhops-card"><span>العملاء</span><strong><?php echo esc_html(number_format_i18n($counts['customers'])); ?></strong></div>
        <div class="mhops-card"><span>غير مدفوع</span><strong><?php echo esc_html(number_format_i18n($counts['unpaid'], 3)); ?> د.ك</strong></div>
    </div>
    <div class="mhops-panel"><h2>آخر الطلبات</h2><?php mh_ops_orders_table(is_array($orders) ? $orders : [], $statuses); ?></div>
    <?php mh_ops_admin_footer();
}

function mh_ops_orders_table(array $orders, array $statuses): void {
    if ($orders === []) { echo '<div class="mhops-empty">لا توجد طلبات تشغيلية بعد. ستظهر هنا عند تحويل أول طلب عميل إلى أمر شغل.</div>'; return; }
    $payments = mh_ops_payment_statuses();
    echo '<div style="overflow:auto"><table class="mhops-table"><thead><tr><th>الكود</th><th>العميل</th><th>المنتج</th><th>الحالة</th><th>الموعد</th><th>الإجمالي</th><th>الدفع</th><th>إدارة</th><th>تواصل</th></tr></thead><tbody>';
    foreach ($orders as $order) {
        $phone = preg_replace('/\D+/', '', (string) ($order['customer_phone'] ?? ''));
        $pay = (string) ($order['payment_status'] ?? 'unpaid');
        $edit = add_query_arg(['page' => 'mh-orders', 'order_id' => absint($order['id'] ?? 0)], admin_url('admin.php'));
        echo '<tr><td><strong>' . esc_html((string) ($order['order_code'] ?? '')) . '</strong></td><td>' . esc_html((string) ($order['customer_name'] ?? '—')) . '</td><td>' . esc_html((string) ($order['product'] ?? '—')) . '</td><td><span class="mhops-badge">' . esc_html($statuses[(string) ($order['status'] ?? '')] ?? (string) ($order['status'] ?? '')) . '</span></td><td>' . esc_html((string) ($order['scheduled_at'] ?? '—')) . '</td><td>' . esc_html(number_format_i18n((float) ($order['total'] ?? 0), 3)) . ' د.ك</td><td><span class="mhops-badge ' . ($pay === 'paid' ? 'mhops-paid' : 'mhops-unpaid') . '">' . esc_html($payments[$pay] ?? $payments['unpaid']) . '</span></td><td><a class="button" href="' . esc_url($edit) . '">تعديل</a></td><td><a class="button" target="_blank" rel="noopener" href="https://wa.me/' . esc_attr($phone) . '">واتساب</a></td></tr>';
    }
    echo '</tbody></table></div>';
}

function mh_ops_render_orders(): void {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $orders = $wpdb->get_results('SELECT o.*, c.name customer_name, c.phone customer_phone FROM ' . mh_ops_table('orders') . ' o LEFT JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id ORDER BY o.created_at DESC LIMIT 300', ARRAY_A);
    mh_ops_admin_header('الطلبات', 'إدارة الطلب والسعر الكامل والفني وموعد التركيب.');
    $edit_id = absint($_GET['order_id'] ?? 0);
    if ($edit_id > 0) mh_ops_render_order_editor($edit_id);
    echo '<div class="mhops-panel">';
    mh_ops_orders_table(is_array($orders) ? $orders : [], mh_ops_statuses());
    echo '</div>';
    mh_ops_admin_footer();
}

function mh_ops_render_order_editor(int $order_id): void {
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare('SELECT o.*, c.name customer_name, c.phone customer_phone FROM ' . mh_ops_table('orders') . ' o LEFT JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id WHERE o.id=%d LIMIT 1', $order_id), ARRAY_A);
    if (!is_array($order)) return;
    $scheduled = !empty($order['scheduled_at']) ? str_replace(' ', 'T', substr((string) $order['scheduled_at'], 0, 16)) : '';
    if (isset($_GET['updated'])) echo '<div class="notice notice-success is-dismissible"><p>تم حفظ الطلب بنجاح.</p></div>';
    ?>
    <div class="mhops-panel"><h2>تعديل <?php echo esc_html((string) $order['order_code']); ?> — <?php echo esc_html((string) ($order['customer_name'] ?? '')); ?></h2>
    <form class="mhops-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="mh_ops_save_order"><input type="hidden" name="order_id" value="<?php echo esc_attr((string) $order_id); ?>"><?php wp_nonce_field('mh_ops_save_order'); ?>
        <label>حالة الطلب<select name="status"><?php foreach (mh_ops_statuses() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) $order['status'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label>إجمالي السعر (د.ك)<input type="number" name="total" min="0" step="0.001" value="<?php echo esc_attr((string) $order['total']); ?>"></label>
        <label>الدفع<select name="payment_status"><?php foreach (mh_ops_payment_statuses() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($order['payment_status'] ?? 'unpaid'), $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label>اسم الفني<input type="text" name="technician_name" value="<?php echo esc_attr((string) ($order['technician_name'] ?? '')); ?>" placeholder="اسم الفني"></label>
        <label>رقم واتساب الفني<input type="tel" name="technician_phone" value="<?php echo esc_attr((string) ($order['technician_phone'] ?? '')); ?>" placeholder="965XXXXXXXX"></label>
        <label>موعد التركيب<input type="datetime-local" name="scheduled_at" value="<?php echo esc_attr($scheduled); ?>"></label>
        <label>هاتف العميل<input type="text" value="<?php echo esc_attr((string) ($order['customer_phone'] ?? '')); ?>" readonly></label>
        <label class="wide">ملاحظات الطلب<textarea name="notes"><?php echo esc_textarea((string) $order['notes']); ?></textarea></label>
        <?php if ((string) ($order['technician_note'] ?? '') !== ''): ?><div class="wide"><strong>آخر ملاحظة من الفني</strong><div class="mhops-card" style="margin-top:7px"><?php echo nl2br(esc_html((string) $order['technician_note'])); ?></div></div><?php endif; ?>
        <?php if (!empty($order['client_received_at'])): ?><div class="wide"><strong>تأكيد استلام العميل</strong><div class="mhops-card" style="margin-top:7px"><p>تم الاستلام: <?php echo esc_html((string) $order['client_received_at']); ?></p><p>التقييم: <?php echo esc_html(str_repeat('★', max(0, min(5, absint($order['client_rating'] ?? 0))))); ?></p><?php if ((string) ($order['client_note'] ?? '') !== ''): ?><p><?php echo nl2br(esc_html((string) $order['client_note'])); ?></p><?php endif; ?></div></div><?php endif; ?>
        <div class="wide mhops-actions">
            <button class="button button-primary" type="submit">حفظ التعديلات</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mh-orders')); ?>">إغلاق</a>
            <?php if (mh_ops_phone_digits((string) ($order['customer_phone'] ?? '')) !== ''): $customer_url = mh_ops_customer_url($order); $customer_message = rawurlencode('مرحبًا ' . (string) ($order['customer_name'] ?? '') . "\n" . 'يمكنك متابعة طلبك لدى Marco’s Home وتأكيد الاستلام من الرابط الخاص: ' . $customer_url); ?><a class="button button-secondary" target="_blank" rel="noopener" href="https://wa.me/<?php echo esc_attr(mh_ops_phone_digits((string) $order['customer_phone'])); ?>?text=<?php echo esc_attr($customer_message); ?>">إرسال بوابة العميل</a><?php endif; ?>
            <?php if (mh_ops_phone_digits((string) ($order['technician_phone'] ?? '')) !== ''): $job_url = mh_ops_job_url($order); $job_message = rawurlencode('مهمة تركيب من Marco’s Home — ' . (string) $order['order_code'] . "\n" . 'العميل: ' . (string) ($order['customer_name'] ?? '') . "\n" . 'الموعد: ' . (string) ($order['scheduled_at'] ?? 'غير محدد') . "\n" . 'افتح تفاصيل المهمة وحدّث الحالة من الرابط: ' . $job_url); ?><a class="button button-secondary" target="_blank" rel="noopener" href="https://wa.me/<?php echo esc_attr(mh_ops_phone_digits((string) $order['technician_phone'])); ?>?text=<?php echo esc_attr($job_message); ?>">إرسال المهمة للفني</a><?php endif; ?>
        </div>
    </form><hr><h3>صور التنفيذ وتأكيد العميل</h3><?php mh_ops_render_job_media($order_id, true); ?></div>
    <?php
}

function mh_ops_render_customers(): void {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $customers = $wpdb->get_results('SELECT c.*, COUNT(o.id) orders_count FROM ' . mh_ops_table('customers') . ' c LEFT JOIN ' . mh_ops_table('orders') . ' o ON o.customer_id=c.id GROUP BY c.id ORDER BY c.created_at DESC LIMIT 300', ARRAY_A);
    mh_ops_admin_header('العملاء', 'ملف واحد لكل عميل يجمع بياناته وطلباته.');
    echo '<div class="mhops-panel"><div style="overflow:auto"><table class="mhops-table"><thead><tr><th>العميل</th><th>الهاتف</th><th>المنطقة</th><th>عدد الطلبات</th><th>آخر تحديث</th></tr></thead><tbody>';
    if (!is_array($customers) || $customers === []) echo '<tr><td colspan="5" class="mhops-empty">لا يوجد عملاء بعد.</td></tr>';
    else foreach ($customers as $customer) echo '<tr><td><strong>' . esc_html((string) $customer['name']) . '</strong></td><td dir="ltr">' . esc_html((string) $customer['phone']) . '</td><td>' . esc_html((string) $customer['area']) . '</td><td>' . esc_html(number_format_i18n((int) $customer['orders_count'])) . '</td><td>' . esc_html((string) $customer['updated_at']) . '</td></tr>';
    echo '</tbody></table></div></div>';
    mh_ops_admin_footer();
}

function mh_ops_render_installations(): void {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $orders = $wpdb->get_results("SELECT o.*, c.name customer_name, c.phone customer_phone FROM " . mh_ops_table('orders') . " o LEFT JOIN " . mh_ops_table('customers') . " c ON c.id=o.customer_id WHERE o.scheduled_at IS NOT NULL ORDER BY o.scheduled_at ASC LIMIT 300", ARRAY_A);
    mh_ops_admin_header('التركيبات', 'جدول التنفيذ والفني المسؤول وصور ما قبل وما بعد التنفيذ.');
    echo '<div class="mhops-panel">';
    mh_ops_orders_table(is_array($orders) ? $orders : [], mh_ops_statuses());
    echo '</div>';
    mh_ops_admin_footer();
}

function mh_ops_is_portal(string $portal): bool {
    return mh_control_request_path() === '/' . trim($portal, '/') . '/';
}

function mh_ops_portal_head(): void {
    $portal = mh_ops_is_portal('marcos-team') ? 'team' : (mh_ops_is_portal('marcos-app') ? 'customer' : '');
    if ($portal === '') return;
    $manifest = $portal === 'team' ? '/marcos-team.webmanifest' : '/marcos-app.webmanifest';
    echo '<link rel="manifest" href="' . esc_url(home_url($manifest)) . '"><meta name="theme-color" content="#0878d1"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">';
}
add_action('wp_head', 'mh_ops_portal_head', 2);

function mh_ops_portal_shell(string $type): string {
    $is_team = $type === 'team';
    $title = $is_team ? 'تطبيق فريق Marco’s Home' : 'طلبات Marco’s Home';
    $subtitle = $is_team ? 'افتح رابط المهمة المرسل لك على واتساب وحدّث حالة التنفيذ مباشرة.' : 'تابع طلبك وموعد المعاينة أو التركيب من هاتفك.';
    ob_start(); ?>
    <main class="mhop" dir="rtl"><section class="mhop-card"><div class="mhop-logo">MH</div><span class="mhop-kicker"><?php echo $is_team ? 'بوابة الفني' : 'بوابة العميل'; ?></span><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($subtitle); ?></p>
    <?php if ($is_team): ?>
        <?php echo mh_ops_team_jobs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php else: ?>
        <?php echo mh_ops_customer_portal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>
    <button class="mhop-install" type="button" hidden>ثبّت التطبيق على الهاتف</button><a class="mhop-home" href="<?php echo esc_url(home_url('/')); ?>">العودة إلى موقع ماركوز هوم</a></section></main>
    <style>body{background:#eef5fb!important}.mhop{min-height:75vh;display:grid;place-items:center;padding:36px 18px;font-family:Tahoma,Arial,sans-serif}.mhop-card{width:min(100%,680px);background:#fff;border-radius:24px;padding:38px;box-shadow:0 24px 70px rgba(7,26,51,.12);text-align:right}.mhop-logo{width:66px;height:66px;border-radius:18px;background:#071a33;color:#fff;display:grid;place-items:center;font-size:24px;font-weight:900;border-bottom:6px solid #0878d1}.mhop-kicker{display:block;color:#0878d1;font-weight:900;margin-top:24px}.mhop h1{color:#071a33;font-size:34px;margin:9px 0}.mhop p{color:#627087;line-height:1.9}.mhop-form{display:grid;gap:14px;margin:26px 0}.mhop-form label{font-weight:800;color:#071a33}.mhop-form input,.mhop-form select,.mhop-form textarea{display:block;width:100%;box-sizing:border-box;margin-top:7px;border:1px solid #cad5e2;border-radius:12px;padding:14px;font-size:16px}.mhop-btn,.mhop-install{display:block;width:100%;border:0;border-radius:12px;background:#0878d1;color:#fff!important;padding:14px;text-align:center;text-decoration:none;font-weight:900;font-size:17px}.mhop-install{margin-top:12px;background:#071a33}.mhop-home{display:block;text-align:center;margin-top:18px;color:#516176}.mhop-note{margin:22px 0;padding:16px;border-radius:12px;background:#eef5fb;color:#071a33;line-height:1.8}.mhop-result{margin-top:18px;padding:18px;border-radius:14px;background:#f5fbf7;border:1px solid #cce9d5}.mhop-result b{color:#071a33}.mhop-job{border:1px solid #dbe5ef;border-radius:16px;padding:18px;margin:14px 0;background:#fbfdff}.mhop-job h3{margin:0 0 10px;color:#071a33}.mhop-job-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;color:#516176;margin-bottom:14px}.mhop-job form{display:grid;gap:12px}.mhop-job select,.mhop-job textarea,.mhop-job input[type=file]{width:100%;box-sizing:border-box;border:1px solid #cad5e2;border-radius:10px;padding:10px;background:#fff}.mhop-job button{border:0;border-radius:10px;padding:12px 16px;background:#0878d1;color:#fff;font-weight:900}.mhop-upload-help{font-size:13px;color:#627087}.mhop-media{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:16px 0}.mhop-media figure{margin:0;border-radius:10px;overflow:hidden;background:#fff;border:1px solid #dbe5ef}.mhop-media img{display:block;width:100%;height:130px;object-fit:cover}.mhop-media figcaption{padding:7px;font-size:12px;font-weight:800}.mhop-order-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:16px 0}.mhop-order-grid span{padding:12px;border-radius:10px;background:#eef5fb;color:#34465d}.mhop-stars{color:#e6a600;font-size:24px;letter-spacing:3px}.mhop-whatsapp{display:block;margin-top:14px;padding:13px;border-radius:12px;text-align:center;text-decoration:none;background:#1faf5d;color:#fff!important;font-weight:900}@media(max-width:620px){.mhop-card{padding:24px}.mhop-job-meta,.mhop-media,.mhop-order-grid{grid-template-columns:1fr}}</style>
    <script>(function(){if('serviceWorker' in navigator)navigator.serviceWorker.register('<?php echo esc_url(home_url('/marcos-app-sw.js')); ?>');var prompt;var button=document.querySelector('.mhop-install');window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();prompt=e;button.hidden=false});if(button)button.addEventListener('click',function(){if(prompt){prompt.prompt();prompt=null;button.hidden=true}})})();</script>
    <?php return (string) ob_get_clean();
}

function mh_ops_team_jobs(): string {
    global $wpdb;
    $order_id = absint($_GET['job'] ?? 0);
    $access = sanitize_text_field((string) ($_GET['access'] ?? ''));
    if ($order_id < 1 || $access === '') return '<div class="mhop-note">لا تحتاج إلى اسم مستخدم أو كلمة مرور. افتح رابط المهمة الخاص الذي وصلك على واتساب.</div>';
    $job = $wpdb->get_row($wpdb->prepare('SELECT o.*, c.name customer_name, c.phone customer_phone, c.area customer_area, c.address customer_address FROM ' . mh_ops_table('orders') . ' o LEFT JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id WHERE o.id=%d LIMIT 1', $order_id), ARRAY_A);
    if (!is_array($job) || !hash_equals(mh_ops_job_access_key($job), $access)) return '<div class="mhop-note">رابط المهمة غير صالح أو تم تغييره. اطلب رابطًا جديدًا من الإدارة.</div>';
    ob_start();
    if (isset($_GET['job_updated'])) echo '<div class="mhop-note">تم تحديث المهمة بنجاح.</div>';
    if (isset($_GET['photos_added'])) echo '<div class="mhop-note">تم رفع ' . esc_html(number_format_i18n(absint($_GET['photos_added']))) . ' صورة بنجاح.</div>';
    if (isset($_GET['photo_error'])) {
        $errors = ['too_large' => 'حجم إحدى الصور أكبر من 8 ميجابايت.', 'invalid_type' => 'صيغة الصورة غير مدعومة. استخدم JPG أو PNG أو WebP.', 'upload_failed' => 'تعذر رفع إحدى الصور. حاول مرة أخرى.'];
        $error = sanitize_key((string) $_GET['photo_error']);
        echo '<div class="mhop-note">' . esc_html($errors[$error] ?? 'تعذر رفع الصور. حاول مرة أخرى.') . '</div>';
    }
    $phone = preg_replace('/\D+/', '', (string) ($job['customer_phone'] ?? ''));
        ?>
        <article class="mhop-job"><h3><?php echo esc_html((string) $job['order_code']); ?> — <?php echo esc_html((string) ($job['customer_name'] ?? '')); ?></h3>
            <div class="mhop-job-meta"><span>المنتج: <?php echo esc_html((string) $job['product']); ?></span><span>الموعد: <?php echo esc_html((string) ($job['scheduled_at'] ?: 'غير محدد')); ?></span><span>المنطقة: <?php echo esc_html((string) ($job['customer_area'] ?? '—')); ?></span><span>الحالة: <?php echo esc_html(mh_ops_statuses()[(string) $job['status']] ?? (string) $job['status']); ?></span></div>
            <?php if ($phone !== ''): ?><p><a href="https://wa.me/<?php echo esc_attr($phone); ?>" target="_blank" rel="noopener">فتح واتساب العميل</a></p><?php endif; ?>
            <?php mh_ops_render_job_media(absint($job['id']), false, ['before', 'after', 'issue']); ?>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mh_ops_technician_update"><input type="hidden" name="order_id" value="<?php echo esc_attr((string) $job['id']); ?>"><input type="hidden" name="access" value="<?php echo esc_attr($access); ?>"><?php wp_nonce_field('mh_ops_job_update_' . (string) $job['id'] . '_' . $access); ?>
                <label>حالة التنفيذ<select name="status"><option value="enroute" <?php selected((string) $job['status'], 'enroute'); ?>>في الطريق</option><option value="working" <?php selected((string) $job['status'], 'working'); ?>>بدأ التنفيذ</option><option value="completed" <?php selected((string) $job['status'], 'completed'); ?>>تم التنفيذ</option><option value="issue" <?php selected((string) $job['status'], 'issue'); ?>>توجد مشكلة</option></select></label>
                <label>نوع الصور<select name="photo_type"><option value="before">قبل التنفيذ</option><option value="after">بعد التنفيذ</option><option value="issue">مشكلة في التنفيذ</option></select></label>
                <label>التقاط أو اختيار الصور<input type="file" name="job_photos[]" accept="image/jpeg,image/png,image/webp" multiple></label><div class="mhop-upload-help">يمكن رفع حتى 5 صور في المرة، وبحد أقصى 8 ميجابايت للصورة.</div>
                <label>ملاحظة للإدارة<textarea name="technician_note" rows="3" placeholder="اكتب أي ملاحظة عن التنفيذ أو المشكلة"><?php echo esc_textarea((string) ($job['technician_note'] ?? '')); ?></textarea></label>
                <button type="submit">حفظ الحالة والصور</button>
            </form>
        </article>
    <?php
    return (string) ob_get_clean();
}

function mh_ops_customer_portal(): string {
    $order_id = absint($_GET['order'] ?? 0);
    $access = sanitize_text_field((string) ($_GET['access'] ?? ''));
    if ($order_id > 0 || $access !== '') {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            'SELECT o.*, c.name customer_name, c.phone customer_phone, c.area customer_area, c.address customer_address FROM ' . mh_ops_table('orders') . ' o INNER JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id WHERE o.id=%d LIMIT 1',
            $order_id
        ), ARRAY_A);
        if (!is_array($order) || $access === '' || !hash_equals(mh_ops_customer_access_key($order), $access)) {
            return '<div class="mhop-note">رابط الطلب غير صالح أو تم تغييره. اطلب رابطًا جديدًا من إدارة Marco’s Home.</div>';
        }
        return mh_ops_customer_order_card($order, $access);
    }

    ob_start(); ?>
    <form class="mhop-form" method="post">
        <label>رقم الطلب<input type="text" name="order_code" inputmode="text" required placeholder="مثال: MH-1024"></label>
        <label>رقم الهاتف<input type="tel" name="phone" inputmode="tel" required placeholder="965XXXXXXXX"></label>
        <button class="mhop-btn" type="submit">عرض الطلب</button>
    </form>
    <?php echo mh_ops_customer_lookup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return (string) ob_get_clean();
}

function mh_ops_customer_lookup(): string {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') return '';
    $code = isset($_POST['order_code']) ? sanitize_text_field(wp_unslash((string) $_POST['order_code'])) : '';
    $phone = isset($_POST['phone']) ? mh_ops_phone_digits(wp_unslash((string) $_POST['phone'])) : '';
    if ($code === '' || strlen($phone) < 8) return '<div class="mhop-note">تأكد من رقم الطلب ورقم الهاتف.</div>';
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare(
        'SELECT o.*, c.name customer_name, c.phone customer_phone, c.area customer_area, c.address customer_address FROM ' . mh_ops_table('orders') . ' o INNER JOIN ' . mh_ops_table('customers') . ' c ON c.id=o.customer_id WHERE o.order_code=%s LIMIT 1',
        $code
    ), ARRAY_A);
    if (!is_array($order) || !hash_equals(mh_ops_phone_digits((string) ($order['customer_phone'] ?? '')), $phone)) {
        return '<div class="mhop-note">لم نعثر على طلب مطابق. أرسل لنا على واتساب للمساعدة.</div>';
    }
    return mh_ops_customer_order_card($order, mh_ops_customer_access_key($order));
}

function mh_ops_customer_order_card(array $order, string $access): string {
    $order_id = absint($order['id'] ?? 0);
    $status = mh_ops_statuses()[(string) ($order['status'] ?? '')] ?? (string) ($order['status'] ?? '');
    $payment = mh_ops_payment_statuses()[(string) ($order['payment_status'] ?? 'unpaid')] ?? 'غير مدفوع';
    $installation = (string) ($order['installation'] ?? '') === 'with' ? 'مع التركيب' : ((string) ($order['installation'] ?? '') === 'without' ? 'بدون تركيب' : 'حسب الطلب');
    $can_receive = in_array((string) ($order['status'] ?? ''), ['completed', 'received', 'collected'], true);
    $received = !empty($order['client_received_at']);
    $rating = max(0, min(5, absint($order['client_rating'] ?? 0)));
    $whatsapp_message = rawurlencode('مرحبًا Marco’s Home، أحتاج مساعدة بخصوص الطلب ' . (string) ($order['order_code'] ?? ''));
    ob_start();
    if (isset($_GET['feedback_saved'])): ?><div class="mhop-note">تم تأكيد استلام الأعمال وحفظ تقييمك. شكرًا لاختيارك Marco’s Home.</div><?php endif;
    if (isset($_GET['feedback_error'])): ?><div class="mhop-note">لا يمكن تأكيد الاستلام قبل اكتمال التنفيذ.</div><?php endif;
    if (isset($_GET['client_photo_error'])):
        $errors = ['too_large' => 'حجم الصورة أكبر من 8 ميجابايت.', 'invalid_type' => 'صيغة الصورة غير مدعومة. استخدم JPG أو PNG أو WebP.', 'upload_failed' => 'تعذر رفع الصورة. حاول مرة أخرى.'];
        $error = sanitize_key((string) $_GET['client_photo_error']); ?><div class="mhop-note"><?php echo esc_html($errors[$error] ?? 'تعذر رفع الصورة. حاول مرة أخرى.'); ?></div><?php endif; ?>
    <article class="mhop-job">
        <h3><?php echo esc_html((string) ($order['order_code'] ?? '')); ?> — <?php echo esc_html((string) ($order['customer_name'] ?? '')); ?></h3>
        <div class="mhop-order-grid">
            <span><b>الحالة</b><br><?php echo esc_html($status); ?></span>
            <span><b>المنتج</b><br><?php echo esc_html(mh_ops_product_label((string) ($order['product'] ?? ''))); ?></span>
            <?php if (!empty($order['design'])): ?><span><b>التصميم</b><br><?php echo esc_html((string) $order['design']); ?></span><?php endif; ?>
            <?php if (!empty($order['color'])): ?><span><b>اللون</b><br><?php echo esc_html((string) $order['color']); ?></span><?php endif; ?>
            <?php if ((float) ($order['wall_width'] ?? 0) > 0): ?><span><b>عرض الحائط</b><br><?php echo esc_html(number_format_i18n((float) $order['wall_width'], 2)); ?> م</span><?php endif; ?>
            <?php if ((float) ($order['table_width'] ?? 0) > 0): ?><span><b>عرض الطاولة</b><br><?php echo esc_html(number_format_i18n((float) $order['table_width'], 2)); ?> م</span><?php endif; ?>
            <span><b>التنفيذ</b><br><?php echo esc_html($installation); ?></span>
            <span><b>الموعد</b><br><?php echo esc_html((string) (($order['scheduled_at'] ?? '') ?: 'لم يحدد بعد')); ?></span>
            <span><b>الإجمالي الكامل</b><br><?php echo esc_html(number_format_i18n((float) ($order['total'] ?? 0), 3)); ?> د.ك</span>
            <span><b>الدفع</b><br><?php echo esc_html($payment); ?></span>
        </div>
        <?php if ($received): ?>
            <div class="mhop-result"><b>تم تأكيد الاستلام</b><br><span class="mhop-stars"><?php echo esc_html(str_repeat('★', $rating)); ?></span><?php if ((string) ($order['client_note'] ?? '') !== ''): ?><p><?php echo nl2br(esc_html((string) $order['client_note'])); ?></p><?php endif; ?></div>
            <?php mh_ops_render_job_media($order_id, false, ['client']); ?>
        <?php elseif ($can_receive): ?>
            <form class="mhop-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_ops_customer_feedback"><input type="hidden" name="order_id" value="<?php echo esc_attr((string) $order_id); ?>"><input type="hidden" name="access" value="<?php echo esc_attr($access); ?>"><?php wp_nonce_field('mh_ops_customer_feedback_' . $order_id . '_' . $access); ?>
                <label>تقييم التنفيذ<select name="client_rating" required><option value="5">5 — ممتاز</option><option value="4">4 — جيد جدًا</option><option value="3">3 — جيد</option><option value="2">2 — مقبول</option><option value="1">1 — يحتاج تحسين</option></select></label>
                <label>ملاحظة اختيارية<textarea name="client_note" rows="3" placeholder="اكتب ملاحظتك للإدارة"></textarea></label>
                <label>صورة اختيارية بعد الاستلام<input type="file" name="client_photo" accept="image/jpeg,image/png,image/webp"></label>
                <button class="mhop-btn" type="submit">تأكيد استلام الأعمال</button>
            </form>
        <?php else: ?>
            <div class="mhop-note">سيظهر زر تأكيد الاستلام هنا بعد اكتمال التنفيذ.</div>
        <?php endif; ?>
        <a class="mhop-whatsapp" href="https://wa.me/96550204320?text=<?php echo esc_attr($whatsapp_message); ?>" target="_blank" rel="noopener">تواصل مع الإدارة عبر واتساب</a>
    </article>
    <?php return (string) ob_get_clean();
}

function mh_ops_render_portals(): void {
    if (!mh_ops_is_portal('marcos-app') && !mh_ops_is_portal('marcos-team')) return;
    mh_control_prepare_virtual_page();
    get_header();
    echo mh_ops_portal_shell(mh_ops_is_portal('marcos-team') ? 'team' : 'customer'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    get_footer();
    exit;
}
add_action('template_redirect', 'mh_ops_render_portals', 18);

function mh_ops_virtual_assets(): void {
    $path = mh_control_request_path();
    if ($path === '/marcos-app.webmanifest/' || $path === '/marcos-team.webmanifest/') {
        $team = $path === '/marcos-team.webmanifest/';
        nocache_headers(); header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode(['name' => $team ? 'فني Marco’s Home' : 'طلبات Marco’s Home', 'short_name' => $team ? 'فني MH' : 'Marco’s Home', 'start_url' => home_url($team ? '/marcos-team/' : '/marcos-app/'), 'display' => 'standalone', 'background_color' => '#eef5fb', 'theme_color' => '#0878d1', 'icons' => [['src' => home_url('/marcos-app-icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
    }
    if ($path === '/marcos-app-icon.svg/') { header('Content-Type: image/svg+xml; charset=utf-8'); echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect width="512" height="512" rx="112" fill="#071a33"/><path d="M90 230 256 95l166 135v190H90z" fill="#fff"/><path d="M150 405V218l106 86 106-86v187h-66V345l-40 33-40-33v60z" fill="#0878d1"/></svg>'; exit; }
    if ($path === '/marcos-app-sw.js/') { header('Content-Type: application/javascript; charset=utf-8'); echo "self.addEventListener('install',e=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(clients.claim()));"; exit; }
}
add_action('template_redirect', 'mh_ops_virtual_assets', 1);

function mh_ops_portal_titles(string $title): string {
    if (mh_ops_is_portal('marcos-app')) return 'متابعة طلبك | Marco’s Home';
    if (mh_ops_is_portal('marcos-team')) return 'بوابة الفني | Marco’s Home';
    return $title;
}
add_filter('pre_get_document_title', 'mh_ops_portal_titles', 130);
