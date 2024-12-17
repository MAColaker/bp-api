<?php

// REST API yanıtları için cache kontrolü
add_filter('rest_pre_serve_request', function ($value) {
    // Sadece JWT endpoint'lerini kontrol et
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '/wp-json/jwt-auth/v1/') !== false) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    return $value;
});

// Kullanıcının gizlediği içerikleri filtrele
add_filter('bp_activity_get_where_conditions', function ($where_conditions, $args) {
    global $wpdb;

    // Sadece oturum açmış kullanıcı için çalışır
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $hidden_content = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT content_id FROM wp_bp_api_hidden_content WHERE user_id = %d",
                $user_id
            )
        );

        if (!empty($hidden_content)) {
            $hidden_content_ids = implode(',', array_map('intval', $hidden_content));
            $where_conditions[] = "a.id NOT IN ($hidden_content_ids)";
        }
    }

    return $where_conditions;
}, 10, 2);

//Engelli kullanıcının filtreleri
add_filter('bp_activity_get_where_conditions', function ($where_conditions) {
    global $wpdb;
    $current_user_id = get_current_user_id();

    if ($current_user_id) {
        // Engellenen kullanıcıları al
        $blocked_users = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT blocked_user_id FROM {$wpdb->prefix}bp_api_blocked_users WHERE blocker_user_id = %d",
                $current_user_id
            )
        );

        if (!empty($blocked_users)) {
            $blocked_user_ids = implode(',', array_map('intval', $blocked_users));
            $where_conditions[] = "a.user_id NOT IN ($blocked_user_ids)";
        }
    }

    return $where_conditions;
});

add_filter('bp_pre_user_query', function ($query) {
    global $wpdb;
    $current_user_id = get_current_user_id();

    if ($current_user_id) {
        // Engellenen kullanıcıları al
        $blocked_users = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT blocked_user_id FROM {$wpdb->prefix}bp_api_blocked_users WHERE blocker_user_id = %d",
                $current_user_id
            )
        );

        if (!empty($blocked_users)) {
            // Kullanıcı sorgusundan engellenen kullanıcıları hariç tut
            $query->query_vars['exclude'] = array_merge(
                $query->query_vars['exclude'] ?? [],
                $blocked_users
            );
        }
    }
});
