<?php

add_action('admin_menu', function() {
    add_menu_page(
        'Raporlanan İçerikler', // Sayfa başlığı
        'Raporlar', // Menüde görünen isim
        'manage_options', // Yetki kontrolü
        'reported-content', // Slug
        'render_reported_content_page', // Görüntüleme fonksiyonu
        'dashicons-flag', // Menü ikonu
        50 // Menü sırası
    );
});

function render_reported_content_page() {
    echo '<div class="wrap">';
    echo '<h1>Raporlanan İçerikler</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>İçerik ID</th>';
    echo '<th>Tür</th>';
    echo '<th>Rapor Sebebi</th>';
    echo '<th>Raporlayan</th>';
    echo '<th>Tarih</th>';
    echo '<th>Aksiyon</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Raporlanan içerikleri al ve listele
    $reports = fetch_reported_content();
    if (!empty($reports)) {
        foreach ($reports as $report) {
            echo '<tr>';
            echo '<td>' . $report->id . '</td>';
            echo '<td>' . $report->content_id . '</td>';
            echo '<td>' . ucfirst($report->content_type) . '</td>';
            echo '<td>' . esc_html($report->reason) . '</td>';
            echo '<td>' . esc_html($report->reported_by) . '</td>';
            echo '<td>' . esc_html($report->reported_at) . '</td>';
            echo '<td>';
            echo '<a href="?page=reported-content&action=remove&id=' . $report->id . '" class="button button-danger">Kaldır</a> ';
            echo '<a href="?page=reported-content&action=ignore&id=' . $report->id . '" class="button">Yoksay</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">Hiçbir rapor bulunamadı.</td></tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

function fetch_reported_content() {
    global $wpdb;
    $results = $wpdb->get_results("
        SELECT fc.id, fc.content_id, fc.content_type, fc.reason, fc.status, fc.reported_at, u.user_login AS reported_by
        FROM wp_bp_api_flagged_content fc
        LEFT JOIN wp_users u ON fc.reported_by = u.ID
        WHERE fc.status = 'pending'
        ORDER BY fc.reported_at DESC
    ");

    return $results;
}

add_action('admin_init', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'reported-content') {
        return;
    }

    global $wpdb;

    if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['remove', 'ignore'])) {
        $report_id = intval($_GET['id']);
        $admin_id = get_current_user_id();

        // Rapor verilerini al
        $report = $wpdb->get_row("
            SELECT fc.content_id, fc.content_type, fc.reason, u.user_email 
            FROM wp_bp_api_flagged_content fc
            LEFT JOIN wp_users u ON fc.reported_by = u.ID
            WHERE fc.id = $report_id
        ");

        if (!$report) {
            return;
        }

        // E-posta bilgisi
        $email_to = $report->user_email;
        $email_subject = '';
        $email_message = '';

        if ($_GET['action'] === 'remove') {
            // İçeriği kaldır ve raporu güncelle
            $wpdb->delete('wp_bp_activity', ['id' => $report->content_id]); // BuddyPress tablosundan sil
            $wpdb->update('wp_bp_api_flagged_content', ['status' => 'removed', 'handled_by' => $admin_id, 'handled_at' => current_time('mysql')], ['id' => $report_id]);

            // E-posta içeriği
            $email_subject = 'Raporladığınız içerik kaldırıldı';
            $email_message = "Merhaba,\n\nRaporladığınız içerik kaldırılmıştır. İşte raporunuzla ilgili bilgiler:\n\n"
                . "İçerik Türü: " . ucfirst($report->content_type) . "\n"
                . "Rapor Sebebi: " . esc_html($report->reason) . "\n\n"
                . "Desteğiniz için teşekkür ederiz.\n\nSaygılarımızla,\nKatre Ekibi";
        }

        if ($_GET['action'] === 'ignore') {
            // Raporu yoksay
            $wpdb->update('wp_bp_api_flagged_content', ['status' => 'ignored', 'handled_by' => $admin_id, 'handled_at' => current_time('mysql')], ['id' => $report_id]);

            // E-posta içeriği
            $email_subject = 'Raporladığınız içerik yoksayıldı';
            $email_message = "Merhaba,\n\nRaporladığınız içerik yönetici tarafından incelenmiş ve herhangi bir ihlal bulunamamıştır. İşte raporunuzla ilgili bilgiler:\n\n"
                . "İçerik Türü: " . ucfirst($report->content_type) . "\n"
                . "Rapor Sebebi: " . esc_html($report->reason) . "\n\n"
                . "Desteğiniz için teşekkür ederiz.\n\nSaygılarımızla,\nKatre Ekibi";
        }

        // E-posta gönderme
        if (!empty($email_to)) {
            wp_mail($email_to, $email_subject, $email_message);
        }

        // Yönlendirme
        wp_redirect(admin_url('admin.php?page=reported-content'));
        exit;
    }
});


