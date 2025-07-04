<?php
/**
 * AI News AutoPoster Uninstall
 * 
 * プラグイン削除時に実行される処理
 */

// WordPressのアンインストール処理からの呼び出しでない場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 設定オプションの削除
delete_option('ai_news_autoposter_settings');
delete_option('ai_news_autoposter_logs');
delete_option('ai_news_autoposter_last_run');

// Cronイベントの削除
wp_clear_scheduled_hook('ai_news_autoposter_daily_cron');

// カスタム投稿タイプの記事削除
$posts = get_posts(array(
    'post_type' => 'ai_news_history',
    'numberposts' => -1,
    'post_status' => 'any'
));

foreach ($posts as $post) {
    wp_delete_post($post->ID, true);
}

// AI生成記事のメタデータ削除
global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_ai_generated'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_seo_focus_keyword'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_meta_description'");

// データベースの最適化
$wpdb->query("OPTIMIZE TABLE {$wpdb->posts}");
$wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");
$wpdb->query("OPTIMIZE TABLE {$wpdb->options}");

// ユーザーメタデータの削除（プラグイン関連）
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ai_news_autoposter_%'");

// トランジェントの削除
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_news_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_news_%'");

// ログファイルの削除（存在する場合）
$upload_dir = wp_upload_dir();
$log_file = $upload_dir['basedir'] . '/ai-news-autoposter.log';
if (file_exists($log_file)) {
    unlink($log_file);
}

// アップロードされたプラグイン関連画像の削除
$attachment_query = new WP_Query(array(
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'meta_query' => array(
        array(
            'key' => '_ai_news_generated',
            'value' => true,
            'compare' => '='
        )
    ),
    'posts_per_page' => -1
));

if ($attachment_query->have_posts()) {
    while ($attachment_query->have_posts()) {
        $attachment_query->the_post();
        wp_delete_attachment(get_the_ID(), true);
    }
}
wp_reset_postdata();

?>