<?php
/**
 * Plugin Name: AI News AutoPoster
 * Plugin URI: https://github.com/kitasinkita/ai-news-autoposter
 * Description: 完全自動でAIニュースを生成・投稿するプラグイン。Claude API対応、スケジューリング機能、SEO最適化機能付き。
 * Version: 1.0.0
 * Author: kitasinkita
 * License: GPL v2 or later
 * Text Domain: ai-news-autoposter
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの基本定数
define('AI_NEWS_AUTOPOSTER_VERSION', '1.0.0');
define('AI_NEWS_AUTOPOSTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_NEWS_AUTOPOSTER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * メインプラグインクラス
 */
class AINewsAutoPoster {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_generate_test_article', array($this, 'generate_test_article'));
        add_action('wp_ajax_get_stats', array($this, 'get_stats'));
        add_action('wp_ajax_clear_logs', array($this, 'clear_logs'));
        add_action('wp_ajax_autosave_setting', array($this, 'autosave_setting'));
        add_action('wp_ajax_manual_post_now', array($this, 'manual_post_now'));
        add_action('wp_ajax_test_cron_execution', array($this, 'test_cron_execution'));
        
        // Cronフック
        add_action('ai_news_autoposter_daily_cron', array($this, 'execute_daily_post_generation'));
        
        // プラグイン有効化・無効化
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * プラグイン初期化
     */
    public function init() {
        load_plugin_textdomain('ai-news-autoposter', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * プラグイン有効化
     */
    public function activate() {
        // デフォルト設定を追加
        if (!get_option('ai_news_autoposter_settings')) {
            $default_settings = array(
                'claude_api_key' => '',
                'auto_publish' => false,
                'schedule_time' => '06:00',
                'max_posts_per_day' => 1,
                'post_category' => get_option('default_category'),
                'seo_focus_keyword' => 'AI ニュース',
                'meta_description_template' => '最新のAI業界ニュースをお届けします。{title}について詳しく解説。',
                'enable_featured_image' => true,
                'post_status' => 'publish',
                'enable_tags' => true,
                'search_keywords' => 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI',
                'news_sources' => array(
                    'https://www.artificialintelligence-news.com/feed/',
                    'https://ai.googleblog.com/feeds/posts/default',
                    'https://openai.com/blog/rss/'
                )
            );
            update_option('ai_news_autoposter_settings', $default_settings);
        }
        
        // Cronスケジュールを設定
        if (!wp_next_scheduled('ai_news_autoposter_daily_cron')) {
            $settings = get_option('ai_news_autoposter_settings', array());
            $schedule_time = $settings['schedule_time'] ?? '06:00';
            $timestamp = strtotime('today ' . $schedule_time);
            if ($timestamp < time()) {
                $timestamp += DAY_IN_SECONDS;
            }
            wp_schedule_event($timestamp, 'daily', 'ai_news_autoposter_daily_cron');
        }
    }
    
    /**
     * プラグイン無効化
     */
    public function deactivate() {
        wp_clear_scheduled_hook('ai_news_autoposter_daily_cron');
    }
    
    /**
     * 管理画面メニュー追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'AI News AutoPoster',
            'AI News AutoPoster',
            'manage_options',
            'ai-news-autoposter',
            array($this, 'admin_page'),
            'dashicons-megaphone',
            30
        );
        
        add_submenu_page(
            'ai-news-autoposter',
            '設定',
            '設定',
            'manage_options',
            'ai-news-autoposter-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ai-news-autoposter',
            'ログ',
            'ログ',
            'manage_options',
            'ai-news-autoposter-logs',
            array($this, 'logs_page')
        );
    }
    
    /**
     * 管理画面初期化
     */
    public function admin_init() {
        register_setting('ai_news_autoposter_settings', 'ai_news_autoposter_settings');
        wp_enqueue_script('jquery');
        wp_enqueue_script('ai-news-autoposter-admin', AI_NEWS_AUTOPOSTER_PLUGIN_URL . 'assets/admin.js', array('jquery'), AI_NEWS_AUTOPOSTER_VERSION);
        wp_enqueue_style('ai-news-autoposter-admin', AI_NEWS_AUTOPOSTER_PLUGIN_URL . 'assets/admin.css', array(), AI_NEWS_AUTOPOSTER_VERSION);
        wp_localize_script('ai-news-autoposter-admin', 'ai_news_autoposter_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_news_autoposter_nonce')
        ));
    }
    
    /**
     * メイン管理画面
     */
    public function admin_page() {
        $settings = get_option('ai_news_autoposter_settings', array());
        $last_run = get_option('ai_news_autoposter_last_run', 'まだ実行されていません');
        $posts_today = $this->get_posts_count_today();
        $next_scheduled = wp_next_scheduled('ai_news_autoposter_daily_cron');
        
        ?>
        <div class="wrap">
            <h1>AI News AutoPoster ダッシュボード</h1>
            
            <div class="notice notice-info">
                <p><strong>プラグインの状態:</strong> 
                    <span id="auto-publish-status" class="<?php echo $settings['auto_publish'] ? 'ai-news-status-enabled' : 'ai-news-status-disabled'; ?>">
                        <?php echo $settings['auto_publish'] ? '自動投稿有効' : '自動投稿無効'; ?>
                    </span>
                </p>
            </div>
            
            <div class="ai-news-autoposter-dashboard">
                <div class="ai-news-autoposter-main">
                    <div class="ai-news-status-card">
                        <h3>統計情報</h3>
                        <div class="ai-news-stats-grid">
                            <div class="ai-news-stat-item">
                                <span class="ai-news-stat-value" id="posts-today-count"><?php echo esc_html($posts_today); ?></span>
                                <div class="ai-news-stat-label">今日の投稿数</div>
                            </div>
                            <div class="ai-news-stat-item">
                                <span class="ai-news-stat-value" id="total-posts-count"><?php echo esc_html($this->get_total_posts_count()); ?></span>
                                <div class="ai-news-stat-label">総投稿数</div>
                            </div>
                            <div class="ai-news-stat-item">
                                <span class="ai-news-stat-value" id="api-status" class="<?php echo !empty($settings['claude_api_key']) ? 'ai-news-status-enabled' : 'ai-news-status-disabled'; ?>">
                                    <?php echo !empty($settings['claude_api_key']) ? '設定済み' : '未設定'; ?>
                                </span>
                                <div class="ai-news-stat-label">API状態</div>
                            </div>
                        </div>
                        <table class="widefat">
                            <tr>
                                <td><strong>最後の実行:</strong></td>
                                <td id="last-run-time"><?php echo esc_html($last_run); ?></td>
                            </tr>
                            <tr>
                                <td><strong>次回実行予定:</strong></td>
                                <td id="next-run-time"><?php echo $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : '未設定'; ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="ai-news-status-card">
                        <h3>手動実行</h3>
                        <p>テスト用に記事を手動生成できます。</p>
                        <div class="ai-news-button-group">
                            <button type="button" class="ai-news-button-primary" id="generate-test-article">テスト記事生成</button>
                            <button type="button" class="ai-news-button-primary" id="manual-post-now">今すぐ投稿</button>
                            <button type="button" class="ai-news-button-secondary" id="test-api-connection">API接続テスト</button>
                            <button type="button" class="ai-news-button-secondary" id="test-cron-execution">Cron実行テスト</button>
                        </div>
                        <div id="test-results" style="margin-top: 15px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 設定画面
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $settings = array(
                'claude_api_key' => sanitize_text_field($_POST['claude_api_key']),
                'auto_publish' => isset($_POST['auto_publish']),
                'schedule_time' => sanitize_text_field($_POST['schedule_time']),
                'max_posts_per_day' => intval($_POST['max_posts_per_day']),
                'post_category' => intval($_POST['post_category']),
                'seo_focus_keyword' => sanitize_text_field($_POST['seo_focus_keyword']),
                'meta_description_template' => sanitize_textarea_field($_POST['meta_description_template']),
                'enable_featured_image' => isset($_POST['enable_featured_image']),
                'post_status' => sanitize_text_field($_POST['post_status']),
                'enable_tags' => isset($_POST['enable_tags']),
                'search_keywords' => sanitize_text_field($_POST['search_keywords']),
                'news_sources' => array_filter(array_map('esc_url_raw', explode("\n", $_POST['news_sources'])))
            );
            
            update_option('ai_news_autoposter_settings', $settings);
            
            // Cronスケジュール更新
            wp_clear_scheduled_hook('ai_news_autoposter_daily_cron');
            $timestamp = strtotime('today ' . $settings['schedule_time']);
            if ($timestamp < time()) {
                $timestamp += DAY_IN_SECONDS;
            }
            wp_schedule_event($timestamp, 'daily', 'ai_news_autoposter_daily_cron');
            
            echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
        }
        
        $settings = get_option('ai_news_autoposter_settings', array());
        $categories = get_categories();
        ?>
        
        <div class="wrap">
            <h1>AI News AutoPoster 設定</h1>
            
            <form method="post" action="" id="ai-news-settings-form">
                <?php wp_nonce_field('ai_news_autoposter_settings', 'ai_news_autoposter_nonce'); ?>
                
                <table class="ai-news-form-table">
                    <tr>
                        <th scope="row">Claude API キー</th>
                        <td>
                            <input type="password" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>" class="regular-text" />
                            <p class="ai-news-form-description">AnthropicのClaude APIキーを入力してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">自動投稿</th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto-publish-toggle" name="auto_publish" <?php checked($settings['auto_publish'] ?? false); ?> />
                                自動投稿を有効にする
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">投稿時刻</th>
                        <td>
                            <input type="time" id="schedule_time" name="schedule_time" value="<?php echo esc_attr($settings['schedule_time'] ?? '06:00'); ?>" />
                            <p class="ai-news-form-description">毎日の投稿時刻を設定してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">1日の最大投稿数</th>
                        <td>
                            <input type="number" id="max_posts_per_day" name="max_posts_per_day" value="<?php echo esc_attr($settings['max_posts_per_day'] ?? 1); ?>" min="1" max="24" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">投稿カテゴリ</th>
                        <td>
                            <select name="post_category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->term_id; ?>" <?php selected($settings['post_category'] ?? '', $category->term_id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">SEO フォーカスキーワード</th>
                        <td>
                            <input type="text" name="seo_focus_keyword" value="<?php echo esc_attr($settings['seo_focus_keyword'] ?? 'AI ニュース'); ?>" class="regular-text ai-news-autosave" />
                            <p class="ai-news-form-description">記事のSEO対策で重要視するキーワードを設定してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">メタディスクリプションテンプレート</th>
                        <td>
                            <textarea name="meta_description_template" rows="3" class="large-text ai-news-autosave"><?php echo esc_textarea($settings['meta_description_template'] ?? ''); ?></textarea>
                            <p class="ai-news-form-description">{title} はタイトルに置換されます。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">アイキャッチ画像</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_featured_image" <?php checked($settings['enable_featured_image'] ?? true); ?> />
                                自動でアイキャッチ画像を生成する
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">投稿ステータス</th>
                        <td>
                            <select name="post_status">
                                <option value="publish" <?php selected($settings['post_status'] ?? 'publish', 'publish'); ?>>公開</option>
                                <option value="draft" <?php selected($settings['post_status'] ?? 'publish', 'draft'); ?>>下書き</option>
                                <option value="pending" <?php selected($settings['post_status'] ?? 'publish', 'pending'); ?>>承認待ち</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">タグ自動生成</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_tags" <?php checked($settings['enable_tags'] ?? true); ?> />
                                AIが関連タグを自動生成する
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">検索キーワード</th>
                        <td>
                            <input type="text" name="search_keywords" value="<?php echo esc_attr($settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI'); ?>" class="large-text ai-news-autosave" />
                            <p class="ai-news-form-description">記事生成時に検索するキーワードをカンマ区切りで入力してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ニュースソース（RSS）</th>
                        <td>
                            <textarea name="news_sources" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", $settings['news_sources'] ?? array())); ?></textarea>
                            <p class="ai-news-form-description">1行につき1つのRSSフィードURLを入力してください。</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存', 'primary', 'submit', true, array('class' => 'ai-news-button-primary')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * ログ画面
     */
    public function logs_page() {
        $logs = get_option('ai_news_autoposter_logs', array());
        $logs = array_reverse(array_slice($logs, -50)); // 最新50件
        
        ?>
        <div class="wrap">
            <h1>AI News AutoPoster ログ</h1>
            
            <div class="ai-news-status-card">
                <div style="margin-bottom: 15px;">
                    <button type="button" class="ai-news-button-secondary" id="clear-logs">ログをクリア</button>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 180px;">日時</th>
                            <th style="width: 100px;">レベル</th>
                            <th>メッセージ</th>
                        </tr>
                    </thead>
                    <tbody id="log-entries">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="3">ログがありません。</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="ai-news-log-entry">
                                    <td class="ai-news-log-timestamp"><?php echo esc_html($log['timestamp']); ?></td>
                                    <td>
                                        <span class="ai-news-log-level ai-news-log-level-<?php echo esc_attr($log['level']); ?>">
                                            <?php echo esc_html(strtoupper($log['level'])); ?>
                                        </span>
                                    </td>
                                    <td class="ai-news-log-message"><?php echo esc_html($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * API接続テスト（Ajax）
     */
    public function test_api_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        $settings = get_option('ai_news_autoposter_settings', array());
        $api_key = $settings['claude_api_key'] ?? '';
        
        if (empty($api_key)) {
            wp_send_json_error('API キーが設定されていません。');
            return;
        }
        
        $response = $this->call_claude_api('こんにちは。これはAPIテストです。', $api_key);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success('API接続が正常に動作しています。');
        }
    }
    
    /**
     * テスト記事生成（Ajax）
     */
    public function generate_test_article() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        $result = $this->generate_and_publish_article(true);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'post_id' => $result,
                'edit_url' => admin_url('post.php?post=' . $result . '&action=edit')
            ));
        }
    }
    
    /**
     * 統計取得（Ajax）
     */
    public function get_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        $settings = get_option('ai_news_autoposter_settings', array());
        $stats = array(
            'posts_today' => $this->get_posts_count_today(),
            'total_posts' => $this->get_total_posts_count(),
            'last_run' => get_option('ai_news_autoposter_last_run', 'まだ実行されていません'),
            'next_run' => wp_next_scheduled('ai_news_autoposter_daily_cron') ? date('Y-m-d H:i:s', wp_next_scheduled('ai_news_autoposter_daily_cron')) : '未設定',
            'auto_publish_enabled' => $settings['auto_publish'] ?? false
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * ログクリア（Ajax）
     */
    public function clear_logs() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        update_option('ai_news_autoposter_logs', array());
        wp_send_json_success('ログを削除しました。');
    }
    
    /**
     * 設定自動保存（Ajax）
     */
    public function autosave_setting() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        $field = sanitize_text_field($_POST['field']);
        $value = sanitize_text_field($_POST['value']);
        
        $settings = get_option('ai_news_autoposter_settings', array());
        $settings[$field] = $value;
        update_option('ai_news_autoposter_settings', $settings);
        
        wp_send_json_success('設定を自動保存しました。');
    }
    
    /**
     * 今すぐ投稿（Ajax）
     */
    public function manual_post_now() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        $result = $this->generate_and_publish_article(false);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'post_id' => $result,
                'edit_url' => admin_url('post.php?post=' . $result . '&action=edit'),
                'view_url' => get_permalink($result)
            ));
        }
    }
    
    /**
     * Cron実行テスト（Ajax）
     */
    public function test_cron_execution() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        // 実際のCron処理を実行
        $this->execute_daily_post_generation();
        
        wp_send_json_success('Cron実行テストが完了しました。ログを確認してください。');
    }
    
    /**
     * 毎日のCron実行
     */
    public function execute_daily_post_generation() {
        $settings = get_option('ai_news_autoposter_settings', array());
        
        if (!$settings['auto_publish']) {
            $this->log('info', '自動投稿が無効のため、スキップしました。');
            return;
        }
        
        $posts_today = $this->get_posts_count_today();
        $max_posts = $settings['max_posts_per_day'] ?? 1;
        
        if ($posts_today >= $max_posts) {
            $this->log('info', '本日の投稿上限に達しているため、スキップしました。');
            return;
        }
        
        $result = $this->generate_and_publish_article();
        
        if (is_wp_error($result)) {
            $this->log('error', '記事生成に失敗しました: ' . $result->get_error_message());
        } else {
            $this->log('success', '記事を正常に生成しました。投稿ID: ' . $result);
            update_option('ai_news_autoposter_last_run', current_time('Y-m-d H:i:s'));
        }
    }
    
    /**
     * 記事生成・投稿
     */
    private function generate_and_publish_article($is_test = false) {
        $settings = get_option('ai_news_autoposter_settings', array());
        $api_key = $settings['claude_api_key'] ?? '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Claude API キーが設定されていません。');
        }
        
        // 最新ニュースを取得
        $news_topics = $this->fetch_latest_news();
        
        // 記事生成プロンプト
        $prompt = $this->build_article_prompt($news_topics, $settings);
        
        // Claude APIを呼び出し
        $ai_response = $this->call_claude_api($prompt, $api_key);
        
        if (is_wp_error($ai_response)) {
            return $ai_response;
        }
        
        // AIレスポンスを解析
        $article_data = $this->parse_ai_response($ai_response);
        
        // 投稿データを準備
        $post_data = array(
            'post_title' => $article_data['title'],
            'post_content' => $article_data['content'],
            'post_status' => $is_test ? 'draft' : ($settings['post_status'] ?? 'publish'),
            'post_category' => array($settings['post_category'] ?? get_option('default_category')),
            'meta_input' => array(
                '_ai_generated' => true,
                '_seo_focus_keyword' => $settings['seo_focus_keyword'] ?? '',
                '_meta_description' => $this->generate_meta_description($article_data['title'], $settings)
            )
        );
        
        // 投稿作成
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // タグを追加
        if ($settings['enable_tags'] && !empty($article_data['tags'])) {
            wp_set_post_tags($post_id, $article_data['tags']);
        }
        
        // アイキャッチ画像を生成
        if ($settings['enable_featured_image']) {
            $this->generate_featured_image($post_id, $article_data['title']);
        }
        
        // SEO設定
        $this->set_seo_data($post_id, $settings['seo_focus_keyword'], $this->generate_meta_description($article_data['title'], $settings));
        
        return $post_id;
    }
    
    /**
     * 最新ニュース取得
     */
    private function fetch_latest_news() {
        $settings = get_option('ai_news_autoposter_settings', array());
        $sources = $settings['news_sources'] ?? array();
        $news_items = array();
        
        foreach ($sources as $rss_url) {
            $feed = fetch_feed($rss_url);
            if (!is_wp_error($feed)) {
                $items = $feed->get_items(0, 3); // 最新3件
                foreach ($items as $item) {
                    $news_items[] = array(
                        'title' => $item->get_title(),
                        'description' => wp_strip_all_tags($item->get_description()),
                        'link' => $item->get_link(),
                        'date' => $item->get_date('Y-m-d H:i:s')
                    );
                }
            }
        }
        
        return array_slice($news_items, 0, 5); // 最新5件に限定
    }
    
    /**
     * 記事生成プロンプト構築
     */
    private function build_article_prompt($news_topics, $settings) {
        $focus_keyword = $settings['seo_focus_keyword'] ?? 'AI ニュース';
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI';
        
        $prompt = "以下のキーワードに関連する最新ニュースから1つの記事を村上春樹風の文体で作成してください。\n";
        $prompt .= "検索キーワード: {$search_keywords}\n\n";
        $prompt .= "以下のニュースを参考にしてください：\n";
        
        foreach ($news_topics as $news) {
            $prompt .= "- {$news['title']}: {$news['description']}\n";
        }
        
        $prompt .= "\n記事の要件：\n";
        $prompt .= "- SEOキーワード「{$focus_keyword}」を自然に含める\n";
        $prompt .= "- 検索キーワード「{$search_keywords}」に関連する内容\n";
        $prompt .= "- 1500-2000文字程度\n";
        $prompt .= "- 魅力的なタイトル\n";
        $prompt .= "- 村上春樹風の文学的表現\n";
        $prompt .= "- 読者にとって有益で興味深い内容\n\n";
        
        $prompt .= "以下の形式で回答してください：\n";
        $prompt .= "TITLE: [記事タイトル]\n";
        $prompt .= "TAGS: [関連タグ,カンマ区切り]\n";
        $prompt .= "CONTENT:\n[記事本文]";
        
        return $prompt;
    }
    
    /**
     * Claude API呼び出し
     */
    private function call_claude_api($prompt, $api_key) {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        );
        
        $body = array(
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 4000,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }
        
        return $data['content'][0]['text'] ?? '';
    }
    
    /**
     * AIレスポンス解析
     */
    private function parse_ai_response($response) {
        $lines = explode("\n", $response);
        $title = '';
        $tags = array();
        $content = '';
        $in_content = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'TITLE:') === 0) {
                $title = trim(substr($line, 6));
            } elseif (strpos($line, 'TAGS:') === 0) {
                $tags_str = trim(substr($line, 5));
                $tags = array_map('trim', explode(',', $tags_str));
            } elseif (strpos($line, 'CONTENT:') === 0) {
                $in_content = true;
                continue;
            } elseif ($in_content) {
                $content .= $line . "\n";
            }
        }
        
        return array(
            'title' => $title ?: '最新AIニュース: ' . date('Y年m月d日'),
            'content' => trim($content),
            'tags' => array_filter($tags)
        );
    }
    
    /**
     * メタディスクリプション生成
     */
    private function generate_meta_description($title, $settings) {
        $template = $settings['meta_description_template'] ?? '最新のAI業界ニュースをお届けします。{title}について詳しく解説。';
        return str_replace('{title}', $title, $template);
    }
    
    /**
     * アイキャッチ画像生成
     */
    private function generate_featured_image($post_id, $title) {
        // プレースホルダー画像URL
        $default_image_url = 'https://via.placeholder.com/1200x630/0073aa/ffffff?text=' . urlencode('AI News');
        
        // 画像をダウンロードしてWordPressメディアライブラリに追加
        $upload_dir = wp_upload_dir();
        $image_data = wp_remote_get($default_image_url);
        
        if (!is_wp_error($image_data)) {
            $filename = 'ai-news-' . $post_id . '.jpg';
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            file_put_contents($file_path, wp_remote_retrieve_body($image_data));
            
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title' => sanitize_file_name($title),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
    }
    
    /**
     * 今日の投稿数を取得
     */
    private function get_posts_count_today() {
        $today = date('Y-m-d');
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'pending'),
            'meta_query' => array(
                array(
                    'key' => '_ai_generated',
                    'value' => true,
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => $today,
                    'before' => $today . ' 23:59:59',
                    'inclusive' => true,
                )
            ),
            'posts_per_page' => -1
        ));
        
        return $query->found_posts;
    }
    
    /**
     * 総投稿数を取得
     */
    private function get_total_posts_count() {
        $query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'pending'),
            'meta_query' => array(
                array(
                    'key' => '_ai_generated',
                    'value' => true,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        ));
        
        return $query->found_posts;
    }
    
    /**
     * SEOデータ設定
     */
    private function set_seo_data($post_id, $focus_keyword, $meta_description) {
        // Yoast SEO対応
        if (class_exists('WPSEO_Meta')) {
            WPSEO_Meta::set_value('focuskw', $focus_keyword, $post_id);
            WPSEO_Meta::set_value('metadesc', $meta_description, $post_id);
            WPSEO_Meta::set_value('title', get_the_title($post_id), $post_id);
        }
        
        // RankMath SEO対応
        if (class_exists('RankMath')) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
            update_post_meta($post_id, 'rank_math_description', $meta_description);
            update_post_meta($post_id, 'rank_math_title', get_the_title($post_id));
        }
    }
    
    /**
     * ログ記録
     */
    private function log($level, $message) {
        $logs = get_option('ai_news_autoposter_logs', array());
        
        $logs[] = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message
        );
        
        // 最新100件のみ保持
        $logs = array_slice($logs, -100);
        
        update_option('ai_news_autoposter_logs', $logs);
    }
}

// プラグインの初期化
new AINewsAutoPoster();

/**
 * カスタム投稿タイプ: AIニュース履歴
 */
function ai_news_autoposter_register_post_type() {
    register_post_type('ai_news_history', array(
        'labels' => array(
            'name' => 'AI記事履歴',
            'singular_name' => 'AI記事',
            'menu_name' => 'AI記事履歴',
            'add_new' => '新規追加',
            'add_new_item' => '新しいAI記事を追加',
            'edit_item' => 'AI記事を編集',
            'new_item' => '新しいAI記事',
            'view_item' => 'AI記事を表示',
            'search_items' => 'AI記事を検索',
            'not_found' => 'AI記事が見つかりませんでした',
            'not_found_in_trash' => 'ゴミ箱にAI記事がありませんでした'
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'ai-news-autoposter',
        'capability_type' => 'post',
        'supports' => array('title', 'editor', 'custom-fields'),
        'has_archive' => false,
        'rewrite' => false
    ));
}
add_action('init', 'ai_news_autoposter_register_post_type');

/**
 * ウィジェット: 最新AI記事
 */
class AI_News_Latest_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'ai_news_latest_widget',
            'AI News: 最新記事',
            array('description' => 'AIが生成した最新記事を表示します。')
        );
    }
    
    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        $posts = get_posts(array(
            'numberposts' => $instance['number'] ?? 5,
            'meta_key' => '_ai_generated',
            'meta_value' => true
        ));
        
        if ($posts) {
            echo '<ul class="ai-news-widget-list">';
            foreach ($posts as $post) {
                echo '<li>';
                echo '<a href="' . get_permalink($post->ID) . '">' . get_the_title($post->ID) . '</a>';
                echo '<span class="ai-news-date">' . get_the_date('m/d', $post->ID) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>AI生成記事がありません。</p>';
        }
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '最新AIニュース';
        $number = !empty($instance['number']) ? $instance['number'] : 5;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">タイトル:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('number'); ?>">表示件数:</label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="number" value="<?php echo esc_attr($number); ?>" size="3">
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['number'] = (!empty($new_instance['number'])) ? absint($new_instance['number']) : 5;
        return $instance;
    }
}

// ウィジェット登録
function ai_news_autoposter_register_widget() {
    register_widget('AI_News_Latest_Widget');
}
add_action('widgets_init', 'ai_news_autoposter_register_widget');

/**
 * ショートコード: [ai-news-list]
 */
function ai_news_autoposter_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number' => 5,
        'category' => '',
        'show_date' => 'true',
        'show_excerpt' => 'false'
    ), $atts, 'ai-news-list');
    
    $args = array(
        'numberposts' => intval($atts['number']),
        'meta_key' => '_ai_generated',
        'meta_value' => true
    );
    
    if (!empty($atts['category'])) {
        $args['category_name'] = $atts['category'];
    }
    
    $posts = get_posts($args);
    
    if (!$posts) {
        return '<p>AI生成記事がありません。</p>';
    }
    
    $output = '<div class="ai-news-list">';
    
    foreach ($posts as $post) {
        $output .= '<div class="ai-news-item">';
        $output .= '<h3><a href="' . get_permalink($post->ID) . '">' . get_the_title($post->ID) . '</a></h3>';
        
        if ($atts['show_date'] === 'true') {
            $output .= '<span class="ai-news-date">' . get_the_date('Y年m月d日', $post->ID) . '</span>';
        }
        
        if ($atts['show_excerpt'] === 'true') {
            $output .= '<p class="ai-news-excerpt">' . get_the_excerpt($post->ID) . '</p>';
        }
        
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('ai-news-list', 'ai_news_autoposter_shortcode');

/**
 * REST API エンドポイント
 */
function ai_news_autoposter_rest_api_init() {
    register_rest_route('ai-news-autoposter/v1', '/generate', array(
        'methods' => 'POST',
        'callback' => 'ai_news_autoposter_api_generate',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    register_rest_route('ai-news-autoposter/v1', '/status', array(
        'methods' => 'GET',
        'callback' => 'ai_news_autoposter_api_status',
        'permission_callback' => function() {
            return current_user_can('read');
        }
    ));
}
add_action('rest_api_init', 'ai_news_autoposter_rest_api_init');

function ai_news_autoposter_api_generate($request) {
    $autoposter = new AINewsAutoPoster();
    $result = $autoposter->generate_and_publish_article();
    
    if (is_wp_error($result)) {
        return new WP_Error('generation_failed', $result->get_error_message(), array('status' => 500));
    }
    
    return array(
        'success' => true,
        'post_id' => $result,
        'edit_url' => admin_url('post.php?post=' . $result . '&action=edit')
    );
}

function ai_news_autoposter_api_status($request) {
    $settings = get_option('ai_news_autoposter_settings', array());
    $last_run = get_option('ai_news_autoposter_last_run', 'Never');
    $next_scheduled = wp_next_scheduled('ai_news_autoposter_daily_cron');
    
    return array(
        'auto_publish_enabled' => $settings['auto_publish'] ?? false,
        'last_run' => $last_run,
        'next_scheduled' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : null,
        'posts_today' => (new AINewsAutoPoster())->get_posts_count_today()
    );
}

/**
 * 管理バーへのクイックリンク追加
 */
function ai_news_autoposter_admin_bar_menu($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node(array(
        'id' => 'ai-news-autoposter',
        'title' => 'AI News',
        'href' => admin_url('admin.php?page=ai-news-autoposter'),
    ));
    
    $wp_admin_bar->add_node(array(
        'id' => 'ai-news-generate',
        'parent' => 'ai-news-autoposter',
        'title' => '記事生成',
        'href' => '#',
        'meta' => array(
            'onclick' => 'if(confirm("記事を生成しますか？")) { 
                fetch("' . rest_url('ai-news-autoposter/v1/generate') . '", {
                    method: "POST",
                    headers: {
                        "X-WP-Nonce": "' . wp_create_nonce('wp_rest') . '"
                    }
                }).then(response => response.json()).then(data => {
                    if(data.success) {
                        alert("記事を生成しました！");
                        window.open(data.edit_url, "_blank");
                    } else {
                        alert("エラー: " + data.message);
                    }
                });
            }'
        )
    ));
}
add_action('admin_bar_menu', 'ai_news_autoposter_admin_bar_menu', 100);

/**
 * アンインストール時のクリーンアップ
 */
function ai_news_autoposter_uninstall() {
    // 設定削除
    delete_option('ai_news_autoposter_settings');
    delete_option('ai_news_autoposter_logs');
    delete_option('ai_news_autoposter_last_run');
    
    // Cronイベント削除
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
}
register_uninstall_hook(__FILE__, 'ai_news_autoposter_uninstall');

?>