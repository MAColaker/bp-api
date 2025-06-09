<?php

add_action('rest_api_init', function () {
    register_rest_route('bp-api/v1', '/member/(?P<user_id>\d+)/activities-count', array(
        'methods' => 'GET',
        'callback' => 'get_user_activity_count_for_types',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/member/(?P<user_id>\d+)/comments-count', array(
        'methods' => 'GET',
        'callback' => 'get_user_comment_count',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));

    register_rest_route('bp-api/v1', '/member/(?P<user_id>\d+)/friends-count', array(
        'methods' => 'GET',
        'callback' => 'get_friends_count',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));
});

function get_user_activity_count_for_types( $request ) {
    global $wpdb;

    // İstekten user_id parametresini alıyoruz
    $user_id = (int) $request['user_id'];

    // Kullanıcının var olup olmadığını kontrol et
    if ( ! get_userdata( $user_id ) ) {
        return new WP_Error( 'no_user', 'Kullanıcı bulunamadı.', array( 'status' => 404 ) );
    }

    // Sabit olarak belirlediğimiz aktivite türleri
    $activity_types = array( 'activity_photo', 'activity_video', 'activity_status', 'activity_video', 'activity_audio', 'new_avatar', 'new_member', ); // Burada istediğiniz türleri ekleyebilirsiniz

    // wp_bp_activity tablosundan belirli bir kullanıcının bu türlerdeki aktivitelerini sayma sorgusu
    $table_name = $wpdb->prefix . 'bp_activity';
    $placeholders = implode(',', array_fill(0, count($activity_types), '%s'));

    // Veritabanı sorgusu: kullanıcı ID'sine ve belirttiğimiz aktivite türlerine göre
    $activity_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND type IN ($placeholders)",
        array_merge( array( $user_id ), $activity_types )
    ));

    // Sonuçları döndür
    return rest_ensure_response( array(
        'user_id'        => $user_id,
        'activity_types' => $activity_types,
        'count'          => (int) $activity_count,
    ));
}

function get_user_comment_count( $request ) {
    global $wpdb;

    // İstekten user_id parametresini alıyoruz
    $user_id = (int) $request['user_id'];

    // Kullanıcının var olup olmadığını kontrol et
    if ( ! get_userdata( $user_id ) ) {
        return new WP_Error( 'no_user', 'Kullanıcı bulunamadı.', array( 'status' => 404 ) );
    }

    // wp_bp_activity tablosunda type = 'activity_comment' olan kayıtları sayma
    $table_name = $wpdb->prefix . 'bp_activity';
    $comment_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND type = %s",
        $user_id, 'activity_comment'
    ));

    // Sonuçları döndür
    return rest_ensure_response( array(
        'user_id'   => $user_id,
        'count'     => (int) $comment_count,
    ));
}

function get_friends_count( $request ) {
    // İstekten user_id parametresini alıyoruz
    $user_id = (int) $request['user_id'];

    // Kullanıcının var olup olmadığını kontrol et
    if ( ! get_userdata( $user_id ) ) {
        return new WP_Error( 'no_user', 'Kullanıcı bulunamadı.', array( 'status' => 404 ) );
    }

    // Arkadaş ID'lerini al
    $friend_ids = friends_get_friend_user_ids( $user_id );

    // Arkadaş sayısını hesapla
    $friend_count = count( $friend_ids );

    // Yanıtı döndür
    return rest_ensure_response( array(
        'user_id'   => $user_id,
        'count'     => $friend_count,
    ));
}
