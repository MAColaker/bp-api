<?php

// Eklenti etkinleştirildiğinde tablo oluşturma işlemi
register_activation_hook(__FILE__, 'bp_api_create_tables');

function bp_api_create_tables() {
    global $wpdb;

    // Tabloların adları
    $table_hidden = $wpdb->prefix . 'bp_api_hidden_content';
    $table_flagged = $wpdb->prefix . 'bp_api_flagged_content';
    $table_blocked = $wpdb->prefix . 'bp_api_blocked_users';

    // Karakter seti ve sıralama ayarları
    $charset_collate = $wpdb->get_charset_collate();

    // SQL sorguları
    $sql_hidden_content = "CREATE TABLE $table_hidden (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content_id INT NOT NULL,
        hidden_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id, content_id)
    ) $charset_collate;";

    $sql_flagged_content = "CREATE TABLE $table_flagged (
        id INT AUTO_INCREMENT PRIMARY KEY,
        content_id INT NOT NULL, -- Raporlanan içerik ID'si (Activity ID)
        content_type VARCHAR(50) NOT NULL, -- Örneğin: 'activity', 'comment'
        reported_by INT NOT NULL, -- Raporlayan kullanıcı ID'si
        reason TEXT NOT NULL, -- Raporlama sebebi
        status ENUM('pending', 'ignored', 'removed') DEFAULT 'pending', -- Moderatör durumu
        handled_by INT DEFAULT NULL, -- Moderatör ID'si (resolved/dismissed durumunda)
        reported_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Raporlama zamanı
        handled_at DATETIME DEFAULT NULL, -- Moderatör aksiyon zamanı
        INDEX (content_id, content_type),
        INDEX (reported_by)
    ) $charset_collate;";

    $sql_blocked_users = "CREATE TABLE $table_blocked (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_user_id INT NOT NULL,
        blocked_user_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_user_id, blocked_user_id)
    ) $charset_collate;";

    // dbDelta kullanarak tabloları oluşturma
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_hidden_content);
    dbDelta($sql_flagged_content);
    dbDelta($sql_blocked_users);
}
