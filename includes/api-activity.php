<?php

add_action('rest_api_init', function () {
    register_rest_route('bp-api/v1', '/activity/', array(
        'methods' => 'POST',
        'callback' => 'custom_bp_activity_add',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/activity/(?P<activity_id>\d+)/file-url/', array(
        'methods' => 'GET',
        'callback' => 'get_file_url',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/activity/(?P<activity_id>\d+)/favorites/', array(
        'methods' => 'GET',
        'callback' => 'get_activity_favorites_count',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/activity/(?P<activity_id>\d+)/favorited-users/', array(
        'methods' => 'GET',
        'callback' => 'get_favorited_users',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/activity/(?P<activity_id>\d+)/comment-count/', array(
        'methods' => 'GET',
        'callback' => 'get_activity_comment_count',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/activity/(?P<activity_id>\d+)/comments/', array(
        'methods' => 'GET',
        'callback' => 'get_activity_comments',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));
});

function custom_bp_activity_add( $request ) {
    // Giriş yapmış kullanıcının ID'si
    $user_id = get_current_user_id();

    // İstekten gelen diğer veriler
    $content    = sanitize_text_field( $request['content'] );
    $media_id   = (int) sanitize_text_field( $request['file_id'] ); // Yüklenen dosyanın ID'si
    $media_type = sanitize_text_field( $request['file_type'] ); // 'photo', 'video', 'audio'

    $activity = '';
    if ($media_type == 'image') {
        $activity = 'activity_photo';
    } elseif ($media_type == 'video') {
        $activity = 'activity_video';
    } elseif ($media_type == 'audio') {
        $activity = 'activity_audio';
    }
    
    // 1. Aktiviteyi ekleme (bp_activity_add kullanarak)
    $activity_id = bp_activity_add( array(
        'user_id'   => $user_id,
        'action'    => '',
        'content'   => $content,
        'type'      => $activity,
        'component' => 'activity', // BuddyPress aktivite bileşeni
    ));

    if ( ! $activity_id ) {
        return new WP_Error( 'activity_add_failed', 'Aktivite eklenemedi.', array( 'status' => 500 ) );
    }

    // 2. Aktiviteye dosya bilgisi ekleme (bp_activity_add_meta kullanarak)
    bp_activity_add_meta( $activity_id, 'youzify_attachments', array( $media_id => 1 ) );

    // 3. Yüklenen dosya bilgilerini wp_youzify_media tablosuna ekleme
    global $wpdb;
    $table_name = $wpdb->prefix . 'youzify_media'; // Tablo adı
    $wpdb->insert( $table_name, array(
        'user_id'   => $user_id,
        'media_id'  => $media_id,
        'item_id'   => $activity_id, // Aktivitenin ID'si
        'type'      => $media_type, // 'photo', 'video', 'audio'
        'component' => 'activity',
        'source'    => $activity,
        'time'      => new DateTime(),
        'privacy'   => 'public',
    ));

    if ( ! $wpdb->insert_id ) {
        return new WP_Error( 'media_add_failed', 'Medya bilgisi eklenemedi.', array( 'status' => 500 ) );
    }

    // Başarılı yanıt
    return rest_ensure_response( array(
        'activity_id' => $activity_id,
        'media_id'    => $media_id,
        'status'      => 'success',
    ));
}


// Uç nokta için callback fonksiyonu
function get_file_url(WP_REST_Request $request) {
    global $wpdb;

    // Aktivite ID'yi al
    $activity_id = $request['activity_id'];

    // wp_youzify_media tablosundan media_id'yi al
    $media_id = $wpdb->get_var($wpdb->prepare(
        "SELECT media_id FROM wp_youzify_media WHERE item_id = %d",
        $activity_id
    ));

    // Eğer media_id yoksa hata döndür
    if (empty($media_id)) {
        return new WP_Error('no_media_found', 'No media found for this activity ID.', array('status' => 404));
    }

    // wp_posts tablosundan dosya bağlantısını al
    $file_link = $wpdb->get_var($wpdb->prepare(
        "SELECT guid FROM wp_posts WHERE ID = %d",
        $media_id
    ));

    // Eğer dosya bağlantısı yoksa hata döndür
    if (empty($file_link)) {
        return new WP_Error('no_file_found', 'No file found for this media ID.', array('status' => 404));
    }

    // Dosya bağlantısını döndür
    return new WP_REST_Response(array('file_link' => $file_link), 200);
}

// Aktivite beğeni (favori) sayısını dönen callback fonksiyonu
function get_activity_favorites_count(WP_REST_Request $request) {
    $activity_id = intval($request['activity_id']);
    
    if (!$activity_id) {
        return new WP_REST_Response(array('status' => 'invalid_activity_id'), 400);
    }
    
    // Favorited meta key kullanılarak beğeni sayısını bulma (örnek meta key: 'favorite_count')
    $favorite_count = bp_activity_get_meta($activity_id, 'favorite_count', true);

    // Beğeni sayısı yoksa 0 döndür
    if (!$favorite_count) {
        $favorite_count = 0;
    }

    // Sonuç JSON formatında döndürülür
    return new WP_REST_Response(array('activity_id' => $activity_id, 'favorites' => $favorite_count), 200);
}

// Favori eden kullanıcıların listesini dönen callback fonksiyonu
function get_favorited_users(WP_REST_Request $request) {
    $activity_id = intval($request['activity_id']);
    
    if (!$activity_id) {
        return new WP_REST_Response(array('status' => 'invalid_activity_id'), 400);
    }

    // Favori eden kullanıcıların listesini al (örnek meta key: 'favorited_users')
    $favorited_users = bp_activity_get_meta($activity_id, 'favorited_users', true);

    $favorited_users_data = array();
    // Favorited_users meta veri boş değilse user bilgilerini doldur
    if ($favorited_users) {
        foreach ($favorited_users as $user_id) {
            $user_info = get_userdata($user_id);
            if ($user_info) {
                $favorited_users_data[] = array(
                    'ID' => $user_info->ID,
                    'username' => $user_info->user_login,
                    'display_name' => $user_info->display_name,
                    'avatar_url' => get_avatar_url($user_id),
                );
            }
        }
    }

    // Kullanıcıların ID'lerini içeren bir dizi döndür
    return new WP_REST_Response(array('activity_id' => $activity_id, 'favorited_users' => $favorited_users_data), 200);
}

// Aktiviteye yapılan yorum sayısını dönen callback fonksiyonu
function get_activity_comment_count(WP_REST_Request $request) {
    global $wpdb;

    $activity_id = intval($request['activity_id']);
    
    if (!$activity_id) {
        return new WP_REST_Response(array('status' => 'invalid_activity_id'), 400);
    }

    // Veritabanı sorgusu ile aktiviteye yapılan yorumları say
    $comment_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$wpdb->prefix}bp_activity WHERE item_id = %d AND type = 'activity_comment'",
        $activity_id
    ));

    // Sonuç JSON formatında döndürülür
    return new WP_REST_Response(array('activity_id' => $activity_id, 'count' => intval($comment_count)), 200);
}

// Aktiviteye yapılan yorumları dönen callback fonksiyonu
function get_activity_comments(WP_REST_Request $request) {
    global $wpdb;

    $activity_id = intval($request['activity_id']);
    
    if (!$activity_id) {
        return new WP_REST_Response(array('status' => 'invalid_activity_id'), 400);
    }

    // Aktiviteye yapılan yorumları al
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, content, date_recorded FROM {$wpdb->prefix}bp_activity WHERE item_id = %d AND type = 'activity_comment' ORDER BY date_recorded ASC",
        $activity_id
    ));

    $comments_with_user_data = array();

    // Eğer yorum varsa user bilgilerini doldur
    if (!empty($comments)) {
        foreach ($comments as $comment) {
            $user_info = get_userdata($comment->user_id);
            if ($user_info) {
                $comments_with_user_data[] = array(
                    'id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'user_name' => $user_info->user_login,
                    'display_name' => $user_info->display_name,
                    'avatar_url' => get_avatar_url($comment->user_id),
                    'comment' => $comment->content,
                    'date' => $comment->date_recorded
                );
            }
        }
    }

    // Yorumları JSON formatında döndür
    return new WP_REST_Response(array('activity_id' => $activity_id, 'comments' => $comments_with_user_data), 200);
}


