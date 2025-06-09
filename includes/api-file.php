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
    
    // Dosya boyutunu kontrol et (örneğin 10 MB sınırı)
    $max_size = 1024 * 1024 * 1024; // 10 MB
    if ($file['size'] > $max_size) {
        return new WP_REST_Response(['message' => 'Dosya çok büyük!'], 413);
    }

    // Dosya adını temizle
    $file_name = sanitize_file_name($file['name']);

    // MIME türlerini kontrol et
    $allowed_mime_types = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/mp4',
        'audio/mpeg'
    ];

    // PHP tarafından tespit edilen MIME türünü al
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Hem PHP tarafından bildirilen hem de gerçekteki MIME türünü kontrol et
    if (!in_array($real_mime_type, $allowed_mime_types)) {
        return new WP_REST_Response(['message' => 'Bu dosya türüne izin verilmiyor!'], 403);
    }

    // Dosya uzantısını kontrol et
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3'];
    if (!in_array($file_ext, $allowed_extensions)) {
        return new WP_REST_Response(['message' => 'Bu dosya uzantısına izin verilmiyor!'], 403);
    }

    // Kullanıcı ID'si ve dizin yolu
    $user_id = get_current_user_id();
    $year = date('Y');
    $month = date('m');
    $custom_dir = WP_CONTENT_DIR . "/uploads/bp-api/members/{$user_id}/{$year}/{$month}";

    // Dizin yoksa oluştur
    if (!wp_mkdir_p($custom_dir)) {
        return new WP_REST_Response(['message' => 'Yükleme dizini oluşturulamadı!'], 500);
    }

    // Aynı ada sahip dosya varsa benzersiz bir isim ver
    $file_path = $custom_dir . '/' . wp_unique_filename($custom_dir, $file_name);

    // Dosyayı taşı
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return new WP_REST_Response(['message' => 'Dosya taşınırken hata oluştu!'], 500);
    }

    // Dosya URL'si
    $file_url = content_url(str_replace(WP_CONTENT_DIR, '', $file_path));

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

    // Özel meta veriler
    update_post_meta($post_id, '_wp_attached_file', "bp-api/members/{$user_id}/{$year}/{$month}/" . basename($file_path));
    update_post_meta($post_id, '_bp_hidden_file', true);

    return new WP_REST_Response([
        'message' => 'Dosya başarıyla yüklendi!',
        'url'     => $file_url,
        'id'      => $post_id
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