<?php

// Rapor edilen içerikleri kaydetmek için REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('bp-api/v1', '/report', array(
        'methods' => 'POST',
        'callback' => 'handle_report',
        'permission_callback' => '__return_true', // Kullanıcı doğrulaması eklenebilir
    ));
});

function handle_report($request) {
    global $wpdb;
    $content_id = $request->get_param('content_id');
    $content_type = $request->get_param('content_type');
    $reason = $request->get_param('reason');
    $user_id = get_current_user_id();

    // Raporu kaydet
    $wpdb->insert('wp_bp_api_flagged_content', array(
        'content_id' => $content_id,
        'content_type' => $content_type,
        'reported_by' => $user_id,
        'reason' => $reason,
        'status' => 'pending',
        'reported_at' => current_time('mysql'),
    ));

    // Kullanıcı için içeriği gizle
    $wpdb->insert('wp_bp_api_hidden_content', array(
        'content_id' => $content_id,
        'user_id' => $user_id,
    ));

    return new WP_REST_Response(['status' => 'success', 'message' => 'Content reported and hidden for the user.'], 200);
}

