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

/**
 * BuddyPress REST API yanıtına sabitlenmiş aktivitelerin bilgilerini ekler
 */
function add_sticky_activities_to_rest_response($response, $handler, $request) {
    if ($request->get_method() !== 'GET') {
        return $response;
    }

    if ($request->get_route() !== '/buddypress/v1/activity') {
        return $response;
    }

    $page = (int) $request->get_param('page');
    $user = $request->get_param('user_id');

    if (!empty($user) || ($page > 1)) {
        return $response;
    }

    $sticky_posts = get_option('youzify_activity_sticky_posts', []);
    if (empty($sticky_posts) || !is_array($sticky_posts)) {
        return $response;
    }

    $response_data = $response->get_data();

    foreach ($sticky_posts as $activity_id) {
        $activity = bp_activity_get_specific(['activity_ids' => [$activity_id]]);
        if (empty($activity['activities'][0])) {
            continue;
        }

        $activity_data = $activity['activities'][0];
        $user_id = (int) $activity_data->user_id;
        $user_api_link = rest_url('buddypress/v1/members/' . $user_id);
        $avatar_full = bp_core_fetch_avatar([
            'item_id' => $user_id,
            'type' => 'full',
            'html' => false
        ]);

        $sticky_activity = [
            'id' => (int) $activity_data->id,
            'primary_item_id' => (int) $activity_data->item_id,
            'secondary_item_id' => (int) $activity_data->secondary_item_id,
            'user_id' => $user_id,
            'component' => $activity_data->component,
            'type' => $activity_data->type,
            'content' => [
                'rendered' => apply_filters('bp_get_activity_content', $activity_data->content)
            ],
            'date' => bp_rest_prepare_date_response($activity_data->date_recorded),
            'favorited' => in_array($activity_id, bp_activity_get_user_favorites(get_current_user_id())),
            'pinned' => true,
            'user_avatar' => ['full' => $avatar_full],
            '_links' => ['user' => [['href' => $user_api_link]]]
        ];

        array_unshift($response_data, $sticky_activity);
    }

    $response->set_data($response_data);
    return $response;
}

add_filter('rest_request_after_callbacks', 'add_sticky_activities_to_rest_response', 10, 3);

// BuddyPress get activity için yorum ve beğeni sayısını ekler
function custom_add_fields_to_activity_json( $response, $request, $activity ) {
	global $wpdb;
	
    // Yorum sayısını ekleyin
    $response->data['comment_count'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$wpdb->prefix}bp_activity WHERE item_id = %d AND type = 'activity_comment'",
        $activity->id
    ));

    // Beğeni sayısını ekleyin
    $response->data['favorite_count'] = bp_activity_get_meta($activity->id, 'favorite_count');

    return $response;
}

add_filter( 'bp_rest_activity_prepare_value', 'custom_add_fields_to_activity_json', 10, 3 );
