<?php
/**
 * Plugin Name: AI News AutoPoster
 * Plugin URI: https://github.com/kitasinkita/ai-news-autoposter
 * Description: 完全自動でAIニュースを生成・投稿するプラグイン。Claude API対応、スケジューリング機能、SEO最適化機能付き。最新版は GitHub からダウンロードしてください。
 * Version: 1.0.4
 * Author: kitasinkita
 * Author URI: https://github.com/kitasinkita
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-news-autoposter
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの基本定数
define('AI_NEWS_AUTOPOSTER_VERSION', '1.0.4');
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
        
        // 動的Cronフックを登録
        $this->register_dynamic_cron_hooks();
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
                'schedule_times' => array('06:00'),
                'max_posts_per_day' => 1,
                'post_category' => get_option('default_category'),
                'seo_focus_keyword' => 'AI ニュース',
                'meta_description_template' => '最新の業界ニュースをお届けします。{title}について詳しく解説いたします。',
                'enable_featured_image' => true,
                'post_status' => 'publish',
                'enable_tags' => true,
                'search_keywords' => 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI',
                'writing_style' => '夏目漱石',
                'news_languages' => array('japanese', 'english'), // english, japanese, chinese
                'output_language' => 'japanese', // japanese, english, chinese
                'article_word_count' => 500,
                'enable_disclaimer' => true,
                'disclaimer_text' => '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。',
                'image_generation_type' => 'placeholder', // placeholder, dalle, unsplash
                'dalle_api_key' => '',
                'unsplash_access_key' => '',
                'news_sources' => array(
                    'japanese' => array(
                        'https://www.itmedia.co.jp/rss/2.0/news_ai.xml',
                        'https://japan.zdnet.com/rss/news/'
                    ),
                    'english' => array(
                        'https://www.artificialintelligence-news.com/feed/',
                        'https://ai.googleblog.com/feeds/posts/default',
                        'https://openai.com/blog/rss/'
                    ),
                    'chinese' => array(
                        'https://www.36kr.com/feed',
                        'https://www.ithome.com/rss/'
                    )
                )
            );
            update_option('ai_news_autoposter_settings', $default_settings);
        }
        
        // Cronスケジュールを設定
        if (!wp_next_scheduled('ai_news_autoposter_daily_cron')) {
            $settings = get_option('ai_news_autoposter_settings', array());
            $start_time = $settings['schedule_time'] ?? '06:00';
            $max_posts = $settings['max_posts_per_day'] ?? 1;
            $schedule_times = $this->generate_hourly_schedule($start_time, $max_posts);
            $this->setup_multiple_schedules($schedule_times);
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
                'writing_style' => sanitize_text_field($_POST['writing_style']),
                'news_languages' => isset($_POST['news_languages']) ? array_map('sanitize_text_field', $_POST['news_languages']) : array(),
                'output_language' => sanitize_text_field($_POST['output_language']),
                'article_word_count' => intval($_POST['article_word_count']),
                'enable_disclaimer' => isset($_POST['enable_disclaimer']),
                'disclaimer_text' => sanitize_textarea_field($_POST['disclaimer_text']),
                'image_generation_type' => sanitize_text_field($_POST['image_generation_type']),
                'dalle_api_key' => sanitize_text_field($_POST['dalle_api_key']),
                'unsplash_access_key' => sanitize_text_field($_POST['unsplash_access_key']),
                'news_sources' => $this->parse_news_sources($_POST)
            );
            
            // 開始時刻から1時間おきのスケジュールを自動生成
            $schedule_times = $this->generate_hourly_schedule($settings['schedule_time'], $settings['max_posts_per_day']);
            $settings['schedule_times'] = $schedule_times;
            
            update_option('ai_news_autoposter_settings', $settings);
            
            // Cronスケジュール更新
            $this->clear_all_cron_schedules();
            $this->setup_multiple_schedules($settings['schedule_times']);
            
            // 設定確認ログ
            $this->log('info', 'Cronスケジュールを更新しました。開始時刻: ' . $settings['schedule_time'] . ', 最大投稿数: ' . $settings['max_posts_per_day']);
            
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
                        <th scope="row">投稿開始時刻</th>
                        <td>
                            <input type="time" id="schedule_time" name="schedule_time" value="<?php echo esc_attr($settings['schedule_time'] ?? '06:00'); ?>" />
                            <p class="ai-news-form-description">
                                <strong>自動投稿の仕組み：</strong><br>
                                開始時刻から1時間おきに投稿されます。<br>
                                例：開始時刻06:00、最大投稿数3の場合 → 06:00、07:00、08:00に投稿
                            </p>
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
                        <th scope="row">文体スタイル</th>
                        <td>
                            <input type="text" name="writing_style" value="<?php echo esc_attr($settings['writing_style'] ?? '夏目漱石'); ?>" class="regular-text ai-news-autosave" />
                            <p class="ai-news-form-description">記事の文体スタイルを指定してください（例：夏目漱石、村上春樹、新聞記事風など）。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ニュース収集言語</th>
                        <td>
                            <label><input type="checkbox" name="news_languages[]" value="japanese" <?php checked(in_array('japanese', $settings['news_languages'] ?? array())); ?> /> 日本語</label><br>
                            <label><input type="checkbox" name="news_languages[]" value="english" <?php checked(in_array('english', $settings['news_languages'] ?? array())); ?> /> 英語</label><br>
                            <label><input type="checkbox" name="news_languages[]" value="chinese" <?php checked(in_array('chinese', $settings['news_languages'] ?? array())); ?> /> 中国語</label>
                            <p class="ai-news-form-description">収集するニュースの言語を選択してください。複数選択可能です。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">記事出力言語</th>
                        <td>
                            <select name="output_language">
                                <option value="japanese" <?php selected($settings['output_language'] ?? 'japanese', 'japanese'); ?>>日本語</option>
                                <option value="english" <?php selected($settings['output_language'] ?? 'japanese', 'english'); ?>>英語</option>
                                <option value="chinese" <?php selected($settings['output_language'] ?? 'japanese', 'chinese'); ?>>中国語</option>
                            </select>
                            <p class="ai-news-form-description">生成される記事の言語を選択してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">記事文字数</th>
                        <td>
                            <input type="number" name="article_word_count" value="<?php echo esc_attr($settings['article_word_count'] ?? 500); ?>" min="100" max="3000" step="50" class="small-text" />
                            <span>文字程度</span>
                            <p class="ai-news-form-description">生成する記事の目安文字数を設定してください（100〜3000文字）。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">免責事項</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_disclaimer" <?php checked($settings['enable_disclaimer'] ?? true); ?> />
                                記事末尾に免責事項を表示する
                            </label>
                            <div style="margin-top: 10px;">
                                <textarea name="disclaimer_text" rows="3" class="large-text"><?php echo esc_textarea($settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。'); ?></textarea>
                                <p class="ai-news-form-description">記事末尾に表示する免責事項の文言を設定してください。</p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">画像生成方式</th>
                        <td>
                            <select name="image_generation_type">
                                <option value="placeholder" <?php selected($settings['image_generation_type'] ?? 'placeholder', 'placeholder'); ?>>プレースホルダー画像</option>
                                <option value="dalle" <?php selected($settings['image_generation_type'] ?? 'placeholder', 'dalle'); ?>>DALL-E 3（OpenAI）</option>
                                <option value="unsplash" <?php selected($settings['image_generation_type'] ?? 'placeholder', 'unsplash'); ?>>Unsplash画像検索</option>
                            </select>
                            <p class="ai-news-form-description">アイキャッチ画像の生成方式を選択してください。</p>
                        </td>
                    </tr>
                    
                    <tr id="dalle-api-key-row" style="display: <?php echo ($settings['image_generation_type'] ?? '') === 'dalle' ? 'table-row' : 'none'; ?>;">
                        <th scope="row">DALL-E API キー</th>
                        <td>
                            <input type="password" name="dalle_api_key" value="<?php echo esc_attr($settings['dalle_api_key'] ?? ''); ?>" class="regular-text" />
                            <p class="ai-news-form-description">OpenAIのAPIキーを入力してください。</p>
                        </td>
                    </tr>
                    
                    <tr id="unsplash-access-key-row" style="display: <?php echo ($settings['image_generation_type'] ?? '') === 'unsplash' ? 'table-row' : 'none'; ?>;">
                        <th scope="row">Unsplash Access Key</th>
                        <td>
                            <input type="password" name="unsplash_access_key" value="<?php echo esc_attr($settings['unsplash_access_key'] ?? ''); ?>" class="regular-text" />
                            <p class="ai-news-form-description">UnsplashのAccess Keyを入力してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ニュースソース（RSS）</th>
                        <td>
                            <div class="ai-news-sources-container">
                                <div class="ai-news-source-group">
                                    <h4>日本語ニュースソース</h4>
                                    <textarea name="news_sources_japanese" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $settings['news_sources']['japanese'] ?? array())); ?></textarea>
                                </div>
                                
                                <div class="ai-news-source-group">
                                    <h4>英語ニュースソース</h4>
                                    <textarea name="news_sources_english" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $settings['news_sources']['english'] ?? array())); ?></textarea>
                                </div>
                                
                                <div class="ai-news-source-group">
                                    <h4>中国語ニュースソース</h4>
                                    <textarea name="news_sources_chinese" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $settings['news_sources']['chinese'] ?? array())); ?></textarea>
                                </div>
                            </div>
                            <p class="ai-news-form-description">各言語のRSSフィードURLを1行につき1つ入力してください。</p>
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
        
        // タイムアウトを延長
        set_time_limit(600); // 10分
        ini_set('max_execution_time', 600);
        
        $this->log('info', '手動投稿を開始します');
        
        $result = $this->generate_and_publish_article(false, 'manual');
        
        if (is_wp_error($result)) {
            $this->log('error', '手動投稿失敗: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        } else {
            $this->log('success', '手動投稿成功: 投稿ID ' . $result);
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
        
        try {
            // タイムアウトを延長
            set_time_limit(300); // 5分
            
            $this->log('info', 'Cron実行テストを開始しました。');
            
            // 実際のCron処理を実行
            $this->execute_daily_post_generation();
            
            wp_send_json_success('Cron実行テストが完了しました。ログを確認してください。');
            
        } catch (Exception $e) {
            $this->log('error', 'Cron実行テストでエラーが発生: ' . $e->getMessage());
            wp_send_json_error('Cron実行テストでエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * ニュースソース設定の解析
     */
    private function parse_news_sources($post_data) {
        return array(
            'japanese' => array_filter(array_map('esc_url_raw', explode("\n", $post_data['news_sources_japanese'] ?? ''))),
            'english' => array_filter(array_map('esc_url_raw', explode("\n", $post_data['news_sources_english'] ?? ''))),
            'chinese' => array_filter(array_map('esc_url_raw', explode("\n", $post_data['news_sources_chinese'] ?? '')))
        );
    }
    
    /**
     * 毎日のCron実行
     */
    public function execute_daily_post_generation() {
        $this->log('info', 'Cron実行を開始しました。現在時刻: ' . current_time('Y-m-d H:i:s'));
        
        $settings = get_option('ai_news_autoposter_settings', array());
        
        if (!$settings['auto_publish']) {
            $this->log('info', '自動投稿が無効のため、スキップしました。');
            return;
        }
        
        $posts_today = $this->get_posts_count_today();
        $max_posts = $settings['max_posts_per_day'] ?? 1;
        
        $this->log('info', "本日の投稿数: {$posts_today}, 最大投稿数: {$max_posts}");
        
        if ($posts_today >= $max_posts) {
            $this->log('info', '本日の投稿上限に達しているため、スキップしました。');
            return;
        }
        
        $this->log('info', '記事生成を開始します...');
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
    private function generate_and_publish_article($is_test = false, $post_type = 'auto') {
        $settings = get_option('ai_news_autoposter_settings', array());
        $api_key = $settings['claude_api_key'] ?? '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Claude API キーが設定されていません。');
        }
        
        $this->log('info', 'Claude AIに最新ニュース検索と記事生成を依頼します');
        
        // Claude AI に直接ニュース検索と記事生成を依頼
        $prompt = $this->build_direct_article_prompt($settings);
        
        // Claude APIを呼び出し
        $this->log('info', 'Claude APIを呼び出します...');
        $ai_response = $this->call_claude_api($prompt, $api_key);
        
        if (is_wp_error($ai_response)) {
            $this->log('error', 'Claude API呼び出しに失敗: ' . $ai_response->get_error_message());
            return $ai_response;
        }
        
        $this->log('info', 'Claude APIから正常にレスポンスを受信しました');
        
        // AIレスポンスを解析
        $this->log('info', 'AIレスポンスを解析中...');
        $article_data = $this->parse_ai_response($ai_response);
        $this->log('info', '記事データ解析完了。タイトル: ' . $article_data['title']);
        
        // 投稿データを準備
        $this->log('info', 'WordPress投稿データを準備中...');
        $post_data = array(
            'post_title' => $article_data['title'],
            'post_content' => $article_data['content'],
            'post_status' => $is_test ? 'draft' : ($settings['post_status'] ?? 'publish'),
            'post_category' => array($settings['post_category'] ?? get_option('default_category')),
            'meta_input' => array(
                '_ai_generated' => true,
                '_ai_post_type' => $is_test ? 'test' : $post_type, // 投稿タイプを記録
                '_seo_focus_keyword' => $settings['seo_focus_keyword'] ?? '',
                '_meta_description' => $this->generate_meta_description($article_data['title'], $settings)
            )
        );
        
        // 投稿作成
        $this->log('info', 'WordPressに投稿を作成中...');
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            $this->log('error', '投稿作成に失敗: ' . $post_id->get_error_message());
            return $post_id;
        }
        
        $this->log('info', '投稿作成成功。投稿ID: ' . $post_id);
        
        // タグを追加
        if ($settings['enable_tags'] && !empty($article_data['tags'])) {
            wp_set_post_tags($post_id, $article_data['tags']);
        }
        
        // アイキャッチ画像を生成
        if ($settings['enable_featured_image']) {
            $this->log('info', 'アイキャッチ画像生成を開始します');
            try {
                $this->generate_featured_image($post_id, $article_data['title'], $article_data['content'], $settings);
            } catch (Exception $e) {
                $this->log('warning', 'アイキャッチ画像生成をスキップしました: ' . $e->getMessage());
            }
        }
        
        // SEO設定
        $this->set_seo_data($post_id, $settings['seo_focus_keyword'], $this->generate_meta_description($article_data['title'], $settings));
        
        return $post_id;
    }
    
    /**
     * リンクの有効性を確認
     */
    private function validate_link($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->log('info', "無効なURL形式: {$url}");
            return false;
        }
        
        // URLが空または不正な場合
        if (empty($url) || strlen($url) < 10) {
            $this->log('info', "URLが短すぎます: {$url}");
            return false;
        }
        
        $response = wp_remote_head($url, array(
            'timeout' => 20,
            'redirection' => 5,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'ja,en-US;q=0.7,en;q=0.3',
                'Cache-Control' => 'no-cache',
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log('info', "リンクアクセスエラー: {$url} - " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // より厳密な検証
        if ($response_code >= 200 && $response_code < 300) {
            $this->log('info', "有効なリンク確認 (HTTP {$response_code}): {$url}");
            return true;
        } else {
            $this->log('info', "無効なリンク (HTTP {$response_code}): {$url}");
            return false;
        }
    }
    
    /**
     * 最新ニュース取得（多言語対応）
     */
    private function fetch_latest_news() {
        $settings = get_option('ai_news_autoposter_settings', array());
        $selected_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $all_sources = $settings['news_sources'] ?? array();
        $news_items = array();
        
        // 24時間以内の記事のみ取得
        $hours_ago_24 = time() - (24 * 60 * 60);
        
        $this->log('info', '新鮮なニュースを取得中...');
        
        foreach ($selected_languages as $language) {
            if (!isset($all_sources[$language])) continue;
            
            $sources = $all_sources[$language];
            foreach ($sources as $rss_url) {
                $this->log('info', "RSS取得中: {$rss_url}");
                $feed = fetch_feed($rss_url);
                if (!is_wp_error($feed)) {
                    $items = $feed->get_items(0, 5); // 各ソースから5件取得して選別
                    foreach ($items as $item) {
                        $item_date = $item->get_date('Y-m-d H:i:s');
                        $item_timestamp = strtotime($item_date);
                        $item_link = $item->get_link();
                        
                        // 24時間以内の記事のみ
                        if ($item_timestamp < $hours_ago_24) {
                            continue;
                        }
                        
                        // 全てのリンクの有効性を厳密に確認
                        if (!$this->validate_link($item_link)) {
                            continue; // 無効なリンクは完全に除外
                        }
                        
                        $news_items[] = array(
                            'title' => $item->get_title(),
                            'description' => wp_strip_all_tags($item->get_description()),
                            'link' => $item_link,
                            'date' => $item_date,
                            'language' => $language,
                            'language_name' => $this->get_language_name($language)
                        );
                    }
                } else {
                    $this->log('error', "RSS取得失敗: {$rss_url}");
                }
            }
        }
        
        // 日付順でソート
        usort($news_items, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        $final_news = array_slice($news_items, 0, 8); // 最新8件に限定
        $this->log('info', count($final_news) . '件の新鮮なニュースを取得しました');
        
        return $final_news;
    }
    
    /**
     * 言語名取得
     */
    private function get_language_name($language) {
        $names = array(
            'japanese' => '日本語',
            'english' => '英語',
            'chinese' => '中国語'
        );
        return $names[$language] ?? $language;
    }
    
    /**
     * 直接記事生成プロンプト構築（Claude AIがニュースを検索）
     */
    private function build_direct_article_prompt($settings) {
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI';
        $selected_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $word_count = $settings['article_word_count'] ?? 500;
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        
        // 言語指定を作成
        $language_names = array_map(array($this, 'get_language_name'), $selected_languages);
        $language_text = implode('と', $language_names);
        
        $current_date = current_time('Y年n月j日');
        
        $prompt = "【{$language_text}】のニュースから、【{$search_keywords}】に関する{$current_date}の最新ニュース（できるだけ今日や昨日の新しいニュース）を優先して送ってください。5本ぐらいが理想です。\n";
        $prompt .= "ニュースの背景や文脈を簡単にまとめ、なぜ今、これが起こっているのか、という背景情報を踏まえて、今後どのような影響をあたえるのか、推察もしてください。\n";
        $prompt .= "全部で【{$word_count}文字】程度にまとめてください。充実した内容で。\n";
        $prompt .= "文体は{$writing_style}風でお願いします。\n\n";
        
        $prompt .= "以下の構造で出力してください：\n\n";
        $prompt .= "TITLE: [記事タイトル]\n";
        $prompt .= "TAGS: [関連タグ,カンマ区切り]\n";
        $prompt .= "CONTENT:\n";
        $prompt .= "[リード文]\n";
        $prompt .= "<h2>[見出し1]</h2>\n";
        $prompt .= "[本文1（適時、参照元を（記事タイトル - メディア名）の形式で本文中に記載してください）]\n";
        $prompt .= "<h2>[見出し2]</h2>\n";
        $prompt .= "[本文2（適時、参照元を（記事タイトル - メディア名）の形式で本文中に記載してください）]\n";
        $prompt .= "...\n\n";
        $prompt .= "## 参考情報源\n";
        $prompt .= "[参考にした最新記事の情報を以下の安全なリンクで記載してください]\n";
        $prompt .= "[形式: <a href=\"メディアトップページURL\" target=\"_blank\">「具体的な記事タイトル」({$current_date}) - メディア名</a>]\n";
        $prompt .= "[安全なリンク先例]\n";
        $prompt .= "- 日本語: https://www.nikkei.com/ (日経新聞)\n";
        $prompt .= "- 英語: https://techcrunch.com/ (TechCrunch)\n";
        $prompt .= "- AI専門: https://www.artificialintelligence-news.com/ (AI News)\n";
        $prompt .= "[例: <a href=\"https://www.nikkei.com/\" target=\"_blank\">「ChatGPT-4の新機能発表」(2024年7月4日) - 日経新聞</a>]\n\n";
        
        $prompt .= "記事を作成してください。";
        
        return $prompt;
    }
    
    /**
     * 記事生成プロンプト構築
     */
    private function build_article_prompt($news_topics, $settings) {
        $focus_keyword = $settings['seo_focus_keyword'] ?? 'AI ニュース';
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI';
        
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        $selected_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $output_language = $settings['output_language'] ?? 'japanese';
        $word_count = $settings['article_word_count'] ?? 500;
        
        // 出力言語の指定
        $language_instructions = array(
            'japanese' => '日本語で',
            'english' => 'in English',
            'chinese' => '用中文'
        );
        
        $prompt = "以下のキーワードに関連する最新ニュースから1つの記事を{$writing_style}風の文体で{$language_instructions[$output_language]}作成してください。\n";
        $prompt .= "検索キーワード: {$search_keywords}\n";
        
        if (count($selected_languages) > 1) {
            $language_names = array_map(array($this, 'get_language_name'), $selected_languages);
            $prompt .= "対象言語圏: " . implode('、', $language_names) . "のニュースを統合して分析\n";
        }
        
        $prompt .= "\n以下の多言語ニュースを参考にしてください（全てのリンクは有効性を確認済み）：\n";
        
        foreach ($news_topics as $news) {
            $prompt .= "- 【{$news['language_name']}】{$news['title']}\n";
            $prompt .= "  説明: {$news['description']}\n";
            $prompt .= "  リンク: {$news['link']} ✓検証済み\n";
            $prompt .= "  日時: {$news['date']}\n\n";
        }
        
        $prompt .= "\n記事の要件：\n";
        $prompt .= "- SEOキーワード「{$focus_keyword}」を自然に含める\n";
        $prompt .= "- 検索キーワード「{$search_keywords}」に関連する内容\n";
        $prompt .= "- {$word_count}文字程度\n";
        $prompt .= "- 魅力的なタイトル\n";
        $prompt .= "- 記事全体を{$language_instructions[$output_language]}執筆\n";
        $prompt .= "- {$writing_style}風の文学的表現\n";
        $prompt .= "- 読者にとって有益で興味深い内容\n";
        $prompt .= "- 適切な見出し（H2、H3タグ）を使用\n";
        
        if (count($selected_languages) > 1) {
            $prompt .= "- 複数言語圏の情報を統合し、グローバルな視点で分析\n";
            $prompt .= "- 各地域の動向の違いや共通点に言及\n";
        }
        
        $prompt .= "- 記事の最後に参考ニュースの引用元とリンクを言語別に記載\n";
        $prompt .= "- 参考ニュースのリンクは上記の検証済みリンクのみ使用し、target=\"_blank\"を必ず指定\n";
        $prompt .= "- 検証済みマーク(✓)は除外し、リンクのみを記載してください\n\n";
        
        $prompt .= "以下の形式で回答してください：\n";
        $prompt .= "TITLE: [記事タイトル]\n";
        $prompt .= "TAGS: [関連タグ,カンマ区切り]\n";
        $prompt .= "CONTENT:\n";
        $prompt .= "[記事本文（HTMLタグ使用可、見出しはH2・H3タグを使用）]\n\n";
        $prompt .= "## 参考ニュース\n";
        $prompt .= "[引用したニュースソースのタイトルとリンクをHTML形式のリンクで記載。必ずtarget=\"_blank\"を付けてください。例: <a href=\"URL\" target=\"_blank\">タイトル</a>]";
        
        return $prompt;
    }
    
    /**
     * Claude API呼び出し
     */
    private function call_claude_api($prompt, $api_key) {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $this->log('info', 'Claude API呼び出しを開始します。プロンプト長: ' . strlen($prompt) . '文字');
        
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        );
        
        $body = array(
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 2000, // トークン数を削減して処理時間短縮
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        $start_time = microtime(true);
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 300 // 5分に延長
        ));
        
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        if (is_wp_error($response)) {
            $this->log('error', 'Claude API呼び出しエラー (' . $duration . '秒): ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $this->log('info', 'Claude API呼び出し完了 (' . $duration . '秒) - HTTP ' . $response_code);
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $this->log('error', 'Claude APIエラー: ' . $data['error']['message']);
            return new WP_Error('api_error', $data['error']['message']);
        }
        
        $response_text = $data['content'][0]['text'] ?? '';
        $response_length = strlen($response_text);
        $this->log('info', 'Claude APIレスポンス取得完了。レスポンス長: ' . $response_length . '文字');
        
        // デバッグ用：Claude APIの生レスポンスをログに出力
        $this->log('info', '=== Claude API 生レスポンス開始 ===');
        $this->log('info', $response_text);
        $this->log('info', '=== Claude API 生レスポンス終了 ===');
        
        return $response_text;
    }
    
    /**
     * 記事内の全リンクを検証して無効なものを削除
     */
    private function validate_and_clean_links($content) {
        $this->log('info', '記事内のリンク検証を開始します');
        
        // <a>タグを抽出
        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER);
        
        $removed_count = 0;
        $total_links = count($matches);
        
        foreach ($matches as $match) {
            $full_link_tag = $match[0];
            $url = $match[1];
            $link_text = $match[2];
            
            // リンクの有効性を確認
            if (!$this->validate_link($url)) {
                // 無効なリンクをテキストのみに置換
                $content = str_replace($full_link_tag, strip_tags($link_text), $content);
                $removed_count++;
                $this->log('info', "無効なリンクを削除: {$url}");
            } else {
                $this->log('info', "有効なリンクを確認: {$url}");
            }
        }
        
        $this->log('info', "リンク検証完了: {$total_links}件中{$removed_count}件を削除");
        
        return $content;
    }
    
    /**
     * AIレスポンス解析
     */
    private function parse_ai_response($response) {
        $lines = explode("\n", $response);
        $title = '';
        $tags = array();
        $sources = array();
        $content = '';
        $in_content = false;
        $in_references = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'SOURCES:') === 0) {
                $sources_str = trim(substr($line, 8));
                $sources = array_map('trim', explode(',', $sources_str));
            } elseif (strpos($line, 'TITLE:') === 0) {
                $title = trim(substr($line, 6));
            } elseif (strpos($line, 'TAGS:') === 0) {
                $tags_str = trim(substr($line, 5));
                $tags = array_map('trim', explode(',', $tags_str));
            } elseif (strpos($line, 'CONTENT:') === 0) {
                $in_content = true;
                $in_references = false;
                continue;
            } elseif (strpos($line, '## 参考情報源') === 0 || strpos($line, '参考情報源') !== false) {
                $in_content = true; // 参考情報源もcontentに含める
                $in_references = true;
                $content .= "\n<h2>参考情報源</h2>\n";
                continue;
            } elseif ($in_content) {
                $content .= $line . "\n";
            }
        }
        
        // ソース情報をログに記録
        if (!empty($sources)) {
            $this->log('info', '参考情報源: ' . implode(', ', $sources));
        }
        
        // リンク検証処理は削除（タイムアウト原因のため）
        
        // 免責事項を追加
        $settings = get_option('ai_news_autoposter_settings', array());
        $enable_disclaimer = $settings['enable_disclaimer'] ?? true;
        $disclaimer_text = $settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。';
        
        if ($enable_disclaimer && !empty($disclaimer_text)) {
            $content = trim($content) . "\n\n<div style=\"margin-top: 20px; padding: 10px; background-color: #f0f0f0; border-left: 4px solid #ccc; font-size: 14px; color: #666;\">" . esc_html($disclaimer_text) . "</div>";
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
        $template = $settings['meta_description_template'] ?? '最新の業界ニュースをお届けします。{title}について詳しく解説いたします。';
        return str_replace('{title}', $title, $template);
    }
    
    /**
     * アイキャッチ画像生成
     */
    private function generate_featured_image($post_id, $title, $content, $settings) {
        // ネットワークエラー対策: プレースホルダー画像のみ使用
        $this->log('info', 'プレースホルダー画像を生成中...');
        return $this->generate_placeholder_image($post_id, $title);
        
        // DALL-E/Unsplashは一時的に無効化
        /*
        $image_type = $settings['image_generation_type'] ?? 'placeholder';
        
        switch ($image_type) {
            case 'dalle':
                return $this->generate_dalle_image($post_id, $title, $content, $settings);
            case 'unsplash':
                return $this->generate_unsplash_image($post_id, $title, $content, $settings);
            default:
                return $this->generate_placeholder_image($post_id, $title);
        }
        */
    }
    
    /**
     * DALL-E画像生成
     */
    private function generate_dalle_image($post_id, $title, $content, $settings) {
        $api_key = $settings['dalle_api_key'] ?? '';
        if (empty($api_key)) {
            $this->log('error', 'DALL-E APIキーが設定されていません');
            return $this->generate_placeholder_image($post_id, $title);
        }
        
        try {
            // 記事内容から画像プロンプトを生成
            $image_prompt = $this->create_image_prompt($title, $content);
            
            $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => 'dall-e-3',
                    'prompt' => $image_prompt,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                    'n' => 1
                )),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                $this->log('error', 'DALL-E API呼び出しエラー: ' . $response->get_error_message());
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                $this->log('error', 'DALL-E APIエラー: ' . $body['error']['message']);
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            $image_url = $body['data'][0]['url'] ?? '';
            if (empty($image_url)) {
                $this->log('error', 'DALL-E画像URLが取得できませんでした');
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            return $this->download_and_set_image($post_id, $image_url, $title, 'dalle');
            
        } catch (Exception $e) {
            $this->log('error', 'DALL-E画像生成エラー: ' . $e->getMessage());
            return $this->generate_placeholder_image($post_id, $title);
        }
    }
    
    /**
     * Unsplash画像検索
     */
    private function generate_unsplash_image($post_id, $title, $content, $settings) {
        $access_key = $settings['unsplash_access_key'] ?? '';
        if (empty($access_key)) {
            $this->log('error', 'Unsplash Access Keyが設定されていません');
            return $this->generate_placeholder_image($post_id, $title);
        }
        
        try {
            // 検索キーワードを生成
            $search_query = $this->create_search_keywords($title, $content);
            
            $response = wp_remote_get('https://api.unsplash.com/search/photos?' . http_build_query(array(
                'query' => $search_query,
                'orientation' => 'landscape',
                'per_page' => 1,
                'order_by' => 'relevant'
            )), array(
                'headers' => array(
                    'Authorization' => 'Client-ID ' . $access_key
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                $this->log('error', 'Unsplash API呼び出しエラー: ' . $response->get_error_message());
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (empty($body['results'])) {
                $this->log('warning', 'Unsplashで画像が見つかりませんでした: ' . $search_query);
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            $image_url = $body['results'][0]['urls']['regular'] ?? '';
            if (empty($image_url)) {
                $this->log('error', 'Unsplash画像URLが取得できませんでした');
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            return $this->download_and_set_image($post_id, $image_url, $title, 'unsplash');
            
        } catch (Exception $e) {
            $this->log('error', 'Unsplash画像検索エラー: ' . $e->getMessage());
            return $this->generate_placeholder_image($post_id, $title);
        }
    }
    
    /**
     * 画像プロンプト作成（DALL-E用）
     */
    private function create_image_prompt($title, $content) {
        // 記事内容から重要なキーワードを抽出
        $keywords = array('AI', 'artificial intelligence', 'technology', 'digital', 'future', 'innovation', 'robot', 'machine learning', 'deep learning');
        
        $prompt = "A professional, modern illustration representing: " . $title . ". ";
        $prompt .= "Style: clean, minimalist, tech-focused, business-appropriate. ";
        $prompt .= "Colors: blue, white, grey tones. No text or words in the image. ";
        $prompt .= "Suitable for a news article header.";
        
        return $prompt;
    }
    
    /**
     * 検索キーワード作成（Unsplash用）
     */
    private function create_search_keywords($title, $content) {
        // タイトルから重要なキーワードを抽出
        $keywords = array('technology', 'artificial intelligence', 'digital', 'computer', 'innovation', 'future');
        
        // AI関連のキーワードを優先
        if (stripos($title, 'AI') !== false || stripos($title, '人工知能') !== false) {
            return 'artificial intelligence technology';
        }
        if (stripos($title, 'robot') !== false || stripos($title, 'ロボット') !== false) {
            return 'robot technology';
        }
        
        return 'technology innovation';
    }
    
    /**
     * 画像ダウンロードと設定
     */
    private function download_and_set_image($post_id, $image_url, $title, $source = '') {
        try {
            // 画像をダウンロード
            $image_data = wp_remote_get($image_url, array(
                'timeout' => 30,
                'user-agent' => 'WordPress/' . get_bloginfo('version')
            ));
            
            if (is_wp_error($image_data)) {
                $this->log('error', $source . '画像ダウンロードに失敗: ' . $image_data->get_error_message());
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            $response_code = wp_remote_retrieve_response_code($image_data);
            if ($response_code !== 200) {
                $this->log('error', $source . '画像ダウンロードに失敗: HTTPエラー ' . $response_code);
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            // アップロードディレクトリ準備
            $upload_dir = wp_upload_dir();
            if (!is_dir($upload_dir['path'])) {
                wp_mkdir_p($upload_dir['path']);
            }
            
            $file_extension = $source === 'dalle' ? 'png' : 'jpg';
            $filename = 'ai-news-' . $source . '-' . $post_id . '-' . time() . '.' . $file_extension;
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            // ファイルを保存
            $image_content = wp_remote_retrieve_body($image_data);
            $file_saved = file_put_contents($file_path, $image_content);
            
            if ($file_saved === false) {
                $this->log('error', $source . '画像ファイル保存に失敗: ' . $file_path);
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            // WordPressメディアライブラリに追加
            $mime_type = $source === 'dalle' ? 'image/png' : 'image/jpeg';
            $attachment = array(
                'post_mime_type' => $mime_type,
                'post_title' => sanitize_text_field($title . ' (' . $source . ')'),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
            
            if (is_wp_error($attachment_id)) {
                $this->log('error', $source . 'アタッチメント登録に失敗: ' . $attachment_id->get_error_message());
                unlink($file_path);
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            // メタデータ生成
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            
            // アイキャッチ画像として設定
            $thumbnail_set = set_post_thumbnail($post_id, $attachment_id);
            
            if ($thumbnail_set) {
                $this->log('success', $source . 'アイキャッチ画像を設定しました: ID ' . $attachment_id);
                return $attachment_id;
            } else {
                $this->log('error', $source . 'アイキャッチ画像の設定に失敗');
                return $this->generate_placeholder_image($post_id, $title);
            }
            
        } catch (Exception $e) {
            $this->log('error', $source . '画像処理でエラー: ' . $e->getMessage());
            return $this->generate_placeholder_image($post_id, $title);
        }
    }
    
    /**
     * プレースホルダー画像生成
     */
    private function generate_placeholder_image($post_id, $title) {
        try {
            // プレースホルダー画像URL（より確実なサービスを使用）
            $image_text = urlencode('AI News');
            $default_image_url = "https://placehold.co/1200x630/0073aa/ffffff/png?text={$image_text}";
            
            // 画像をダウンロード
            $image_data = wp_remote_get($default_image_url, array(
                'timeout' => 5, // タイムアウトをさらに短縮
                'user-agent' => 'WordPress/' . get_bloginfo('version')
            ));
            
            if (is_wp_error($image_data)) {
                $this->log('error', 'アイキャッチ画像ダウンロードに失敗: ' . $image_data->get_error_message());
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($image_data);
            if ($response_code !== 200) {
                $this->log('error', 'アイキャッチ画像ダウンロードに失敗: HTTPエラー ' . $response_code);
                return false;
            }
            
            // アップロードディレクトリ準備
            $upload_dir = wp_upload_dir();
            if (!is_dir($upload_dir['path'])) {
                wp_mkdir_p($upload_dir['path']);
            }
            
            $filename = 'ai-news-' . $post_id . '-' . time() . '.png';
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            // ファイルを保存
            $image_content = wp_remote_retrieve_body($image_data);
            $file_saved = file_put_contents($file_path, $image_content);
            
            if ($file_saved === false) {
                $this->log('error', 'アイキャッチ画像ファイル保存に失敗: ' . $file_path);
                return false;
            }
            
            // WordPressメディアライブラリに追加
            $attachment = array(
                'post_mime_type' => 'image/png',
                'post_title' => sanitize_text_field($title),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
            
            if (is_wp_error($attachment_id)) {
                $this->log('error', 'アタッチメント登録に失敗: ' . $attachment_id->get_error_message());
                unlink($file_path); // ファイル削除
                return false;
            }
            
            // メタデータ生成
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            
            // アイキャッチ画像として設定
            $thumbnail_set = set_post_thumbnail($post_id, $attachment_id);
            
            if ($thumbnail_set) {
                $this->log('success', 'アイキャッチ画像を設定しました: ID ' . $attachment_id);
                return $attachment_id;
            } else {
                $this->log('error', 'アイキャッチ画像の設定に失敗');
                return false;
            }
            
        } catch (Exception $e) {
            $this->log('error', 'アイキャッチ画像生成でエラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 動的Cronフックを登録
     */
    private function register_dynamic_cron_hooks() {
        $settings = get_option('ai_news_autoposter_settings', array());
        $start_time = $settings['schedule_time'] ?? '06:00';
        $max_posts = $settings['max_posts_per_day'] ?? 1;
        $schedule_times = $this->generate_hourly_schedule($start_time, $max_posts);
        
        foreach ($schedule_times as $index => $time) {
            $hook_name = 'ai_news_autoposter_daily_cron' . ($index > 0 ? '_' . $index : '');
            if (!has_action($hook_name, array($this, 'execute_daily_post_generation'))) {
                add_action($hook_name, array($this, 'execute_daily_post_generation'));
            }
        }
    }
    
    /**
     * 1時間おきのスケジュールを生成
     */
    private function generate_hourly_schedule($start_time, $max_posts) {
        $schedule_times = array();
        
        // 開始時刻をパース
        $start_hour = intval(substr($start_time, 0, 2));
        $start_minute = intval(substr($start_time, 3, 2));
        
        for ($i = 0; $i < $max_posts; $i++) {
            $hour = ($start_hour + $i) % 24; // 24時間を超えたら0時から
            $time = sprintf('%02d:%02d', $hour, $start_minute);
            $schedule_times[] = $time;
        }
        
        return $schedule_times;
    }
    
    /**
     * 全Cronスケジュールをクリア
     */
    private function clear_all_cron_schedules() {
        // メインフックをクリア
        wp_clear_scheduled_hook('ai_news_autoposter_daily_cron');
        
        // 追加フックもクリア（最大24個まで）
        for ($i = 1; $i < 24; $i++) {
            $hook_name = 'ai_news_autoposter_daily_cron_' . $i;
            wp_clear_scheduled_hook($hook_name);
        }
    }
    
    /**
     * 複数スケジュールを設定
     */
    private function setup_multiple_schedules($schedule_times) {
        // 既存のスケジュールをクリア
        $this->clear_all_cron_schedules();
        
        foreach ($schedule_times as $index => $time) {
            if (empty($time)) continue;
            
            $hook_name = 'ai_news_autoposter_daily_cron' . ($index > 0 ? '_' . $index : '');
            $timestamp = strtotime('today ' . $time);
            
            if ($timestamp < time()) {
                $timestamp += DAY_IN_SECONDS;
            }
            
            $result = wp_schedule_event($timestamp, 'daily', $hook_name);
            
            // スケジュール設定ログ
            $next_run = date('Y-m-d H:i:s', $timestamp);
            if ($result === false) {
                $this->log('error', "Cronスケジュール設定失敗: {$hook_name} ({$time})");
            } else {
                $this->log('info', "Cronスケジュール設定: {$hook_name} - 次回実行: {$next_run}");
            }
            
            // 各スケジュールのフックを追加（動的フック登録）
            if (!has_action($hook_name, array($this, 'execute_daily_post_generation'))) {
                add_action($hook_name, array($this, 'execute_daily_post_generation'));
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
                ),
                array(
                    'key' => '_ai_post_type',
                    'value' => 'auto',
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
    
    // AI生成記事のメタデータ削除
    $posts = get_posts(array(
        'post_type' => 'post',
        'numberposts' => -1,
        'meta_key' => '_ai_generated',
        'meta_value' => true
    ));
    
    foreach ($posts as $post) {
        delete_post_meta($post->ID, '_ai_generated');
        delete_post_meta($post->ID, '_seo_focus_keyword');
        delete_post_meta($post->ID, '_meta_description');
    }
}
register_uninstall_hook(__FILE__, 'ai_news_autoposter_uninstall');

?>