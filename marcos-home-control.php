<?php
/**
 * Plugin Name: Marco's Home Control
 * Plugin URI: https://marcohom.com/
 * Description: قناة آمنة لإدارة تعديلات موقع Marco's Home المنشورة من فرع WordPress المخصص.
 * Version: 0.1.2
 * Author: Marco's Home
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MH_CONTROL_VERSION', '0.1.2');

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
