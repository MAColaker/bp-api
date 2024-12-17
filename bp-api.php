<?php
/**
 * Plugin Name: BuddyPress API
 * Description: Daha fazla özellik barındıran servis eklentisi.
 * Version: 1.0
 * Author: Muhammed Ali Çolaker
 */

// Güvenlik için doğrudan erişimi engelle
defined('ABSPATH') || exit;

// Gerekli dosyaları dahil et
require_once plugin_dir_path(__FILE__) . 'includes/api-activity.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-member.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-report.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-user.php';

require_once plugin_dir_path(__FILE__) . 'includes/db-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/filters.php';

require_once plugin_dir_path(__FILE__) . 'admin/report.php';

// Aktifleşme ve devre dışı bırakma kancaları
register_activation_hook(__FILE__, 'bp_api_activate');
register_deactivation_hook(__FILE__, 'bp_api_deactivate');

// Aktifleştirme işlevi
function bp_api_activate() {
    bp_api_create_tables(); // Tabloları oluştur
}

// Devre dışı bırakma işlevi
function bp_api_deactivate() {
    // İsterseniz bir şey yapabilirsiniz
}

// Diğer başlangıç ayarlarını burada yapabilirsiniz