<?php

require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

add_action('rest_api_init', function () {
    register_rest_route('bp-api/v1', '/file/upload', [
        'methods' => 'POST',
        'callback' => 'custom_file_upload',
        'permission_callback' => function () {
            return is_user_logged_in(); // Sadece giriş yapan kullanıcılar yükleyebilir
        }
    ]);
});

function custom_file_upload(WP_REST_Request $request) {
    if (empty($_FILES['file'])) {
        return new WP_REST_Response(['message' => 'Dosya yüklenmedi!'], 400);
    }

    $file = $_FILES['file'];
    $file_name = sanitize_file_name($file['name']);

    // Yalnızca belirli dosya türlerine izin verelim
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'audio/mpeg'];

    if (!in_array($file['type'], $allowed_mime_types)) {
        return new WP_REST_Response(['message' => 'Bu dosya türüne izin verilmiyor!'], 403);
    }

    // Özel dosya yolu oluştur
    $user_id = get_current_user_id(); // Giriş yapan kullanıcının ID'sini al
    $year = date('Y'); // Yıl
    $month = date('m'); // Ay
    $custom_dir = WP_CONTENT_DIR . "/uploads/bp-api/members/{$user_id}/{$year}/{$month}";

    // Dizin yoksa oluştur
    if (!file_exists($custom_dir)) {
        wp_mkdir_p($custom_dir);
    }

    // Dosyayı özel dizine taşı
    $file_path = $custom_dir . '/' . $file_name;
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return new WP_REST_Response(['message' => 'Dosya taşınırken hata oluştu!'], 500);
    }

    // Dosya URL'sini oluştur
    $file_url = content_url("/uploads/bp-api/members/{$user_id}/{$year}/{$month}/{$file_name}");

    // Dosyayı wp_posts tablosuna ekle
    $post_data = [
        'post_title'   => $file_name,
        'post_status'  => 'inherit',
        'post_type'    => 'attachment',
        'post_mime_type' => $file['type'],
        'guid'         => $file_url
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['message' => 'Dosya veritabanına eklenemedi!'], 500);
    }

    // Medya dosyası için gerekli meta verileri oluştur
    $attachment_metadata = wp_generate_attachment_metadata($post_id, $file_path);
    wp_update_attachment_metadata($post_id, $attachment_metadata);

    // Dosya yolunu attachment meta verisi olarak kaydet
    update_post_meta($post_id, '_wp_attached_file', "bp-api/members/{$user_id}/{$year}/{$month}/{$file_name}");
	
	// Dosyayı özel bir meta veri ile işaretle
    update_post_meta($post_id, '_bp_hidden_file', true);

    // Yüklenen dosyanın URL'sini ve ID'sini döndür
    return new WP_REST_Response([
        'message' => 'Dosya başarıyla yüklendi!',
        'url' => $file_url,
        'id' => $post_id
    ], 200);
}

// Ortam Kütüphanesi'nde _bp_hidden_file meta verisine göre filtrele
add_action('pre_get_posts', 'hide_bp_hidden_files_from_media_library');

function hide_bp_hidden_files_from_media_library($query) {
    if (is_admin() && $query->get('post_type') === 'attachment') {
        $query->set('meta_query', [
            [
                'key'     => '_bp_hidden_file',
                'compare' => 'NOT EXISTS', // Eğer bu meta anahtarı varsa dosyayı gösterme
            ]
        ]);
    }
}