<?php

add_action('rest_api_init', function () {
    // Kullanıcı engelleme
    register_rest_route('bp-api/v1', '/user/block', [
        'methods'  => 'POST',
        'callback' => 'block_user_handler',
        'permission_callback' => 'is_user_logged_in',
    ]);

    // Kullanıcı engellemeyi kaldırma
    register_rest_route('bp-api/v1', '/user/unblock', [
        'methods'  => 'POST',
        'callback' => 'unblock_user_handler',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

function block_user_handler($request) {
    global $wpdb;
    $blocker_user_id = get_current_user_id();
    $blocked_user_id = intval($request->get_param('user_id'));

    if (!$blocker_user_id || !$blocked_user_id || $blocker_user_id === $blocked_user_id) {
        return new WP_Error('invalid_request', 'Geçersiz kullanıcı bilgileri.', ['status' => 400]);
    }

    // Veritabanına ekle
    $result = $wpdb->insert(
        'wp_bp_api_blocked_users',
        [
            'blocker_user_id' => $blocker_user_id,
            'blocked_user_id' => $blocked_user_id,
            'created_at'      => current_time('mysql'),
        ],
        ['%d', '%d', '%s']
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Engelleme işlemi başarısız oldu.', ['status' => 500]);
    }

    return ['success' => true, 'message' => 'Kullanıcı başarıyla engellendi.'];
}

function unblock_user_handler($request) {
    global $wpdb;
    $blocker_user_id = get_current_user_id();
    $blocked_user_id = intval($request->get_param('blocked_user_id'));

    if (!$blocker_user_id || !$blocked_user_id) {
        return new WP_Error('invalid_request', 'Geçersiz kullanıcı bilgileri.', ['status' => 400]);
    }

    // Veritabanından kaldır
    $result = $wpdb->delete(
        'wp_bp_api_blocked_users',
        [
            'blocker_user_id' => $blocker_user_id,
            'blocked_user_id' => $blocked_user_id,
        ],
        ['%d', '%d']
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Engellemeyi kaldırma işlemi başarısız oldu.', ['status' => 500]);
    }

    return ['success' => true, 'message' => 'Kullanıcı engellemesi kaldırıldı.'];
}
