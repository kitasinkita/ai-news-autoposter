<?php
/**
 * Plugin Name: AI News AutoPoster
 * Plugin URI: https://github.com/kitasinkita/ai-news-autoposter
 * Description: 完全自動でAIニュースを生成・投稿するプラグイン。Claude API対応、スケジューリング機能、SEO最適化機能付き。最新版は GitHub からダウンロードしてください。
 * Version: 1.2.24
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
define('AI_NEWS_AUTOPOSTER_VERSION', '1.2.24');
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
                'claude_model' => 'claude-3-5-haiku-20241022',
                'gemini_api_key' => '',
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
                'custom_prompt' => '',
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
        
        // プラグインの管理画面でのみスクリプトを読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 管理画面スクリプト読み込み
     */
    public function enqueue_admin_scripts($hook) {
        // プラグインの管理画面でのみ読み込み
        if (strpos($hook, 'ai-news-autoposter') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('ai-news-autoposter-admin', AI_NEWS_AUTOPOSTER_PLUGIN_URL . 'assets/admin.js', array('jquery'), AI_NEWS_AUTOPOSTER_VERSION, true);
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
                    &nbsp;|&nbsp;
                    <strong>現在のモデル:</strong> 
                    <span class="ai-news-status-enabled">
                        <?php echo esc_html($settings['claude_model'] ?? 'claude-3-5-haiku-20241022'); ?>
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
                'claude_model' => sanitize_text_field($_POST['claude_model']),
                'gemini_api_key' => sanitize_text_field($_POST['gemini_api_key']),
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
        
        // カテゴリ取得とデバッグ
        $categories = get_categories(array('hide_empty' => false)); // 空のカテゴリも表示
        
        // フォールバック: カテゴリが取得できない場合はデフォルトカテゴリを追加
        if (empty($categories)) {
            $default_category = get_category(1); // 未分類カテゴリ
            if ($default_category) {
                $categories = array($default_category);
            } else {
                // 総てのカテゴリを直接取得
                global $wpdb;
                $categories = $wpdb->get_results("SELECT term_id, name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'category'");
                
                // それでも空の場合は緊急フォールバック
                if (empty($categories)) {
                    $categories = array((object) array('term_id' => 1, 'name' => '未分類'));
                }
            }
        }
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
                        <th scope="row">AIモデル</th>
                        <td>
                            <select name="claude_model" id="claude_model">
                                <option value="claude-3-5-haiku-20241022" <?php selected($settings['claude_model'] ?? 'claude-3-5-haiku-20241022', 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (高速・低コスト)</option>
                                <option value="claude-3-5-sonnet-20241022" <?php selected($settings['claude_model'] ?? 'claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (バランス)</option>
                                <option value="claude-sonnet-4-20250514" <?php selected($settings['claude_model'] ?? 'claude-3-5-haiku-20241022', 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4 (最高品質)</option>
                                <option value="gemini-1.5-flash-002" <?php selected($settings['claude_model'] ?? 'claude-3-5-haiku-20241022', 'gemini-1.5-flash-002'); ?>>Gemini 1.5 Flash (2024年末知識・高コスト)</option>
                                <option value="gemini-2.0-flash-exp" <?php selected($settings['claude_model'] ?? 'claude-3-5-haiku-20241022', 'gemini-2.0-flash-exp'); ?>>Gemini 2.0 Flash (最新・実験版)</option>
                                <option value="gemini-2.5-flash" <?php selected($settings['claude_model'] ?? 'claude-3-5-haiku-20241022', 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash + Google検索 (最新・推奨)</option>
                            </select>
                            <p class="ai-news-form-description">使用するAIモデルを選択してください。Geminiモデルは2024年末までの最新知識を活用しますが、高コストです（1,000クエリ$35）。<br><small>注：Gemini 2.5のみGoogle Search Grounding対応</small></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Gemini API キー</th>
                        <td>
                            <input type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>" class="regular-text" />
                            <p class="ai-news-form-description">Google AI StudioのGemini APIキーを入力してください。Geminiモデル使用時に必要です。</p>
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
                            <p class="ai-news-form-description">記事の文体スタイルを指定してください（例：夏目漱石、森鴎外、新聞記事風など）。</p>
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
                        <th scope="row">カスタムプロンプト</th>
                        <td>
                            <textarea name="custom_prompt" rows="8" class="large-text" placeholder="空白の場合はデフォルトプロンプトを使用します"><?php echo esc_textarea($settings['custom_prompt'] ?? ''); ?></textarea>
                            <p class="ai-news-form-description">
                                Claude AIに送信するカスタムプロンプトを設定できます。以下のプレースホルダーが使用可能です：<br>
                                <code>{言語}</code> - ニュース収集言語<br>
                                <code>{キーワード}</code> - 検索キーワード<br>
                                <code>{文字数}</code> - 記事文字数<br>
                                <code>{文体}</code> - 文体スタイル<br><br>
                                <strong>デフォルトプロンプト例：</strong><br>
                                【{言語}】のニュースから、【{キーワード}】に関する最新のニュースを送ってください。5本ぐらいが理想です。<br>
                                ニュースの背景や文脈を簡単にまとめ、かつ、上記の最新ニュースのリンク先を参考情報元として記事のタイトルとリンクを記載し、なぜ今、これが起こっているのか、という背景情報を踏まえて、今後どのような影響をあたえるのか、推察もしてください。<br>
                                全部で【{文字数}文字】程度にまとめてください。充実した内容で。<br>
                                文体は{文体}風でお願いします。
                            </p>
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
        $logs = array_reverse(array_slice($logs, -300)); // 最新300件
        
        ?>
        <div class="wrap">
            <h1>AI News AutoPoster ログ</h1>
            
            <div class="ai-news-status-card">
                <div style="margin-bottom: 15px;">
                    <button type="button" class="ai-news-button-secondary" id="clear-logs">ログをクリア</button>
                    <button type="button" class="ai-news-button-primary" id="copy-logs" style="margin-left: 10px;">全ログをコピー</button>
                    <button type="button" class="ai-news-button-primary" id="copy-latest-logs" style="margin-left: 10px;">最新投稿ログをコピー</button>
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
                
                <!-- ログデータをJavaScript用に準備 -->
                <textarea id="log-data-all" style="display: none;"><?php
                    foreach ($logs as $log) {
                        echo esc_html($log['timestamp']) . "\t" . esc_html(strtoupper($log['level'])) . "\t" . esc_html($log['message']) . "\n";
                    }
                ?></textarea>
                
                <textarea id="log-data-latest" style="display: none;"><?php
                    // 最新の投稿ログ（手動投稿開始から成功/失敗まで）を抽出
                    $latest_logs = array();
                    $collecting = false;
                    foreach (array_reverse($logs) as $log) {
                        if (strpos($log['message'], '手動投稿を開始します') !== false) {
                            $collecting = true;
                            $latest_logs = array($log);
                        } elseif ($collecting) {
                            array_unshift($latest_logs, $log);
                            if (strpos($log['message'], '手動投稿成功') !== false || 
                                strpos($log['message'], '手動投稿失敗') !== false) {
                                break;
                            }
                        }
                    }
                    foreach ($latest_logs as $log) {
                        echo esc_html($log['timestamp']) . "\t" . esc_html(strtoupper($log['level'])) . "\t" . esc_html($log['message']) . "\n";
                    }
                ?></textarea>
            </div>
        </div>
        <?php
    }
    
    /**
     * API接続テスト（Ajax）
     */
    public function test_api_connection() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_autoposter_nonce')) {
            $this->log('error', 'API接続テスト: Nonce検証失敗');
            wp_send_json_error('セキュリティチェックに失敗しました。');
            return;
        }
        
        $this->log('info', 'API接続テストを開始します');
        
        $settings = get_option('ai_news_autoposter_settings', array());
        $model = $settings['claude_model'] ?? 'claude-3-5-haiku-20241022';
        
        if (strpos($model, 'gemini') === 0) {
            $api_key = $settings['gemini_api_key'] ?? '';
            if (empty($api_key)) {
                wp_send_json_error('Gemini API キーが設定されていません。');
                return;
            }
            $response = $this->call_gemini_api('こんにちは。これはAPIテストです。', $api_key, $model);
        } else {
            $api_key = $settings['claude_api_key'] ?? '';
            if (empty($api_key)) {
                wp_send_json_error('Claude API キーが設定されていません。');
                return;
            }
            $response = $this->call_claude_api('こんにちは。これはAPIテストです。', $api_key, $settings);
        }
        
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
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_autoposter_nonce')) {
            $this->log('error', 'テスト記事生成: Nonce検証失敗');
            wp_send_json_error('セキュリティチェックに失敗しました。');
            return;
        }
        
        $this->log('info', 'テスト記事生成を開始します');
        
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
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_autoposter_nonce')) {
            $this->log('error', '手動投稿: Nonce検証失敗');
            wp_send_json_error('セキュリティチェックに失敗しました。');
            return;
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
        
        // AI APIを呼び出し
        $model = $settings['claude_model'] ?? 'claude-3-5-haiku-20241022';
        $is_gemini = strpos($model, 'gemini') === 0;
        $api_name = $is_gemini ? 'Gemini' : 'Claude';
        
        $this->log('info', $api_name . ' AIに最新ニュース検索と記事生成を依頼します');
        
        if ($is_gemini) {
            $this->log('info', 'Gemini APIを呼び出します...');
            // Gemini用にWeb検索特化プロンプトを使用
            $gemini_prompt = $this->build_gemini_search_prompt($settings, $model);
            $ai_response = $this->call_gemini_api($gemini_prompt, $settings['gemini_api_key'] ?? '', $model);
        } else {
            $this->log('info', 'Claude APIを呼び出します...');
            // Claude AI に直接ニュース検索と記事生成を依頼
            $prompt = $this->build_direct_article_prompt($settings);
            $ai_response = $this->call_claude_api($prompt, $api_key, $settings);
        }
        
        if (is_wp_error($ai_response)) {
            $this->log('error', $api_name . ' API呼び出しに失敗: ' . $ai_response->get_error_message());
            return $ai_response;
        }
        
        $this->log('info', $api_name . ' APIから正常にレスポンスを受信しました');
        
        // AIレスポンスを解析
        $this->log('info', 'AIレスポンスを解析中...');
        
        // Gemini APIからの構造化レスポンス処理
        if ($is_gemini && is_array($ai_response) && isset($ai_response['text'])) {
            $this->log('info', 'Gemini API構造化レスポンスを処理中...');
            $grounding_sources = $ai_response['grounding_sources'] ?? array();
            $article_data = $this->parse_ai_response($ai_response['text']);
            
            // 記事にグラウンディングソースを統合
            if (!empty($grounding_sources)) {
                $this->log('info', count($grounding_sources) . '件のグラウンディングソースを記事に統合中...');
                $article_data = $this->integrate_grounding_sources($article_data, $grounding_sources);
            }
        } else {
            // Claude APIまたは古いGemini形式の場合
            $article_data = $this->parse_ai_response($ai_response);
        }
        
        $this->log('info', '記事データ解析完了。タイトル: ' . $article_data['title']);
        
        // 最終的なコンテンツ処理（免責事項追加）
        $this->log('info', '最終コンテンツ処理を実行中...');
        $article_data['content'] = $this->post_process_content($article_data['content']);
        
        // 投稿データを準備
        $this->log('info', 'WordPress投稿データを準備中...');
        
        // カテゴリ設定を確認
        $this->log('info', 'カテゴリ設定を確認中...');
        
        $category = $settings['post_category'] ?? get_option('default_category');
        $this->log('info', '初期カテゴリ設定: ' . ($category ?? 'null'));
        
        if (empty($category)) {
            $this->log('info', 'カテゴリが空のためデフォルトカテゴリ(1)を使用');
            $category = 1;
        } else {
            // category_exists関数の代わりに安全なチェック
            $this->log('info', 'category_existsをチェック中: ' . $category);
            $cat_obj = get_category($category);
            if (!$cat_obj || is_wp_error($cat_obj)) {
                $this->log('warning', 'カテゴリ(' . $category . ')が存在しないため、デフォルトカテゴリ(1)を使用します');
                $category = 1;
            } else {
                $this->log('info', 'カテゴリ(' . $category . ')の存在を確認しました');
            }
        }
        
        $this->log('info', '投稿データ配列を作成中...');
        
        // メタディスクリプションを安全に生成
        $this->log('info', 'メタディスクリプションを生成中...');
        
        $post_data = array(
            'post_title' => $article_data['title'],
            'post_content' => $article_data['content'],
            'post_status' => $is_test ? 'draft' : ($settings['post_status'] ?? 'publish'),
            'post_category' => array($category),
            'post_type' => 'post', // 明示的に指定
            'meta_input' => array(
                '_ai_generated' => true,
                '_ai_post_type' => $is_test ? 'test' : $post_type, // 投稿タイプを記録
                '_seo_focus_keyword' => $settings['seo_focus_keyword'] ?? '',
                '_meta_description' => $this->safe_generate_meta_description($article_data['title'], $settings)
            )
        );
        
        $this->log('info', '投稿データ配列作成完了。コンテンツサイズチェックを開始します。');
        
        // 投稿データをデバッグログに記録
        $this->log('info', 'コンテンツ長を計算中...');
        
        try {
            $content_length = mb_strlen($post_data['post_content']);
            $this->log('info', 'コンテンツ長計算完了: ' . $content_length . '文字');
        } catch (Exception $e) {
            $this->log('error', 'コンテンツ長計算エラー: ' . $e->getMessage());
            $content_length = strlen($post_data['post_content']); // フォールバック
        }
        
        $this->log('info', '投稿データ詳細: タイトル=' . mb_strlen($post_data['post_title']) . '文字、コンテンツ=' . $content_length . '文字、ステータス=' . $post_data['post_status'] . '、カテゴリ=' . json_encode($post_data['post_category']));
        
        // コンテンツが長すぎる場合は短縮（データベースエラー回避）
        if ($content_length > 4000) {
            $this->log('warning', 'コンテンツが長すぎます(' . $content_length . '文字)。4,000文字に短縮します。');
            $post_data['post_content'] = mb_substr($post_data['post_content'], 0, 3800) . "\n\n※ 記事が長いため一部を省略して表示しています。";
            $this->log('info', '短縮後のコンテンツ長: ' . mb_strlen($post_data['post_content']) . '文字');
        }
        
        $this->log('info', 'コンテンツ長チェック完了。投稿作成処理を開始します。');
        
        // 処理継続を確保するための緊急処理
        if (function_exists('fastcgi_finish_request')) {
            // FastCGIバッファをクリアして処理を続行
            @fastcgi_finish_request();
        }
        @flush();
        @ob_flush();
        
        // 投稿作成
        $this->log('info', 'WordPressに投稿を作成中...');
        
        // WordPressエラーログを有効化
        $this->log('info', 'WordPressエラーログを有効化中...');
        $original_error_reporting = error_reporting();
        error_reporting(E_ALL);
        $this->log('info', 'WordPressエラーログ有効化完了');
        
        // 投稿データの詳細検証
        $this->log('info', '投稿データの必須フィールドを検証中...');
        $required_fields = ['post_title', 'post_content', 'post_status', 'post_type'];
        foreach ($required_fields as $field) {
            $this->log('info', 'フィールド検証中: ' . $field);
            if (empty($post_data[$field])) {
                $this->log('error', '必須フィールドが空です: ' . $field);
                return new WP_Error('missing_required_field', '必須フィールドが不足しています: ' . $field);
            }
            $this->log('info', 'フィールドOK: ' . $field);
        }
        $this->log('info', '必須フィールド検証完了');
        
        // データベース接続状態を確認
        global $wpdb;
        $this->log('info', 'データベース接続状態: ' . ($wpdb->check_connection() ? '正常' : '異常'));
        
        // データベース設定を確認
        $this->log('info', 'データベース設定を取得中...');
        $max_allowed_packet = $wpdb->get_var("SELECT @@max_allowed_packet");
        $this->log('info', 'max_allowed_packet: ' . number_format($max_allowed_packet) . ' bytes');
        
        // 文字セットを確認
        $this->log('info', '文字セットを取得中...');
        $charset = $wpdb->get_var("SELECT @@character_set_database");
        $collation = $wpdb->get_var("SELECT @@collation_database");
        $this->log('info', 'DB文字セット: ' . $charset . ', 照合順序: ' . $collation);
        
        // 投稿前にデータベースエラーをクリア
        $this->log('info', 'データベースエラーをクリア中...');
        $wpdb->flush();
        $wpdb->last_error = '';
        $this->log('info', 'データベースエラークリア完了');
        
        // 文字化け防止のため、WordPressデータに一切手を加えない
        $this->log('info', 'データ前処理完了（サニタイゼーション無し）');
        
        // 最小限の処理のみ実行（文字化け回避のため余計な処理を削除）
        $this->log('info', 'コンテンツクリーニング開始（最小限処理）');
        
        // 長いURLのみ短縮（これは必要）
        $post_data['post_content'] = preg_replace('/https:\/\/vertexaisearch\.cloud\.google\.com\/grounding-api-redirect\/[A-Za-z0-9_-]{50,}/', '[参考リンク]', $post_data['post_content']);
        
        $this->log('info', 'コンテンツクリーニング完了');
        
        $this->log('info', '最終処理後のコンテンツ長: ' . mb_strlen($post_data['post_content']) . '文字');
        
        // 実際のデータでテスト投稿を試行（メタデータなし）
        $test_post_data_real = array(
            'post_title' => $post_data['post_title'],
            'post_content' => $post_data['post_content'],
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_category' => array(1)
            // メタデータを除外
        );
        
        $this->log('info', '実際のコンテンツでメタデータなしテスト投稿を試行');
        $test_id_real = wp_insert_post($test_post_data_real, true);
        
        if (is_wp_error($test_id_real)) {
            $this->log('error', '実際のコンテンツでもテスト投稿失敗: ' . $test_id_real->get_error_message());
        } else if ($test_id_real === 0) {
            $this->log('error', '実際のコンテンツでもwp_insert_postが0を返しました');
        } else {
            $this->log('info', '実際のコンテンツテスト投稿成功。ID: ' . $test_id_real . ' - 削除します');
            wp_delete_post($test_id_real, true);
            
            // メタデータが原因の可能性が高いため、メタデータを一時的に無効化
            $this->log('warning', 'メタデータが原因の可能性があります。メタデータを無効化して投稿を試行します');
            unset($post_data['meta_input']);
        }
        
        // 最小限のテストデータで投稿を試行
        $test_post_data = array(
            'post_title' => 'テスト投稿',
            'post_content' => '<p>これはテスト投稿です。</p>',
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_category' => array(1)
        );
        
        $this->log('info', '最小限のテストデータで投稿を試行');
        $test_id = wp_insert_post($test_post_data, true);
        
        if (is_wp_error($test_id)) {
            $this->log('error', '最小限のテストデータでも投稿失敗: ' . $test_id->get_error_message());
        } else if ($test_id === 0) {
            $this->log('error', '最小限のテストデータでもwp_insert_postが0を返しました');
        } else {
            $this->log('info', 'テスト投稿成功。ID: ' . $test_id . ' - 削除します');
            wp_delete_post($test_id, true);
        }
        
        // カテゴリの検証
        if (isset($post_data['post_category']) && is_array($post_data['post_category'])) {
            $valid_categories = array();
            foreach ($post_data['post_category'] as $cat_id) {
                if (is_numeric($cat_id) && get_category($cat_id)) {
                    $valid_categories[] = intval($cat_id);
                }
            }
            $post_data['post_category'] = $valid_categories;
            
            // 有効なカテゴリがない場合はデフォルトカテゴリを使用
            if (empty($post_data['post_category'])) {
                $post_data['post_category'] = array(1);
                $this->log('warning', 'カテゴリが無効のため、デフォルトカテゴリ(1)を使用します');
            }
        }
        
        $this->log('info', 'カテゴリ処理完了。wp_insert_postの準備を開始します。');
        
        // メモリ不足やPHPエラーを防ぐための事前処理
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5分のタイムアウト
        
        // 最終コンテンツサイズチェックと緊急短縮
        $final_content_length = mb_strlen($post_data['post_content']);
        if ($final_content_length > 3000) {
            $this->log('warning', '最終コンテンツが長すぎます(' . $final_content_length . '文字)。3000文字に緊急短縮します。');
            $post_data['post_content'] = mb_substr($post_data['post_content'], 0, 2800) . "\n\n※ 記事が長いため緊急短縮しています。";
            $this->log('info', '緊急短縮後のコンテンツ長: ' . mb_strlen($post_data['post_content']) . '文字');
        }
        
        // wp_insert_post実行直前の最終ログ
        $memory_usage = memory_get_usage(true) / 1024 / 1024; // MB
        $memory_peak = memory_get_peak_usage(true) / 1024 / 1024; // MB
        $this->log('info', 'wp_insert_postを実行します。メモリ使用量: ' . round($memory_usage, 2) . 'MB, ピーク: ' . round($memory_peak, 2) . 'MB');
        $this->log('info', 'データサイズ: ' . strlen(serialize($post_data)) . ' bytes');
        $this->log('info', 'コンテンツサイズ: ' . mb_strlen($post_data['post_content']) . '文字');
        
        // PHPエラーをキャッチするためのoutput buffering開始
        ob_start();
        
        try {
            $post_id = wp_insert_post($post_data, true); // true: より詳細なエラー情報
            $this->log('info', 'wp_insert_post実行完了。結果: ' . (is_wp_error($post_id) ? 'WP_Error' : (is_numeric($post_id) ? 'ID=' . $post_id : '0')));
        } catch (Exception $e) {
            $this->log('error', 'wp_insert_postでPHP例外が発生: ' . $e->getMessage());
            $post_id = new WP_Error('php_exception', $e->getMessage());
        } catch (Error $e) {
            $this->log('error', 'wp_insert_postでPHPエラーが発生: ' . $e->getMessage());
            $post_id = new WP_Error('php_error', $e->getMessage());
        }
        
        // バッファの内容をログ出力
        $buffer_content = ob_get_clean();
        if (!empty($buffer_content)) {
            $this->log('error', 'wp_insert_post実行中の出力: ' . $buffer_content);
        }
        
        error_reporting($original_error_reporting);
        
        if (is_wp_error($post_id)) {
            $error_messages = array();
            foreach ($post_id->get_error_codes() as $code) {
                $error_messages[] = $code . ': ' . implode(', ', $post_id->get_error_messages($code));
            }
            $this->log('error', '投稿作成に失敗: ' . implode(' | ', $error_messages));
            
            // 追加のデバッグ情報
            $this->log('error', 'カテゴリ詳細: ' . json_encode($post_data['post_category'], JSON_UNESCAPED_UNICODE));
            if (isset($post_data['meta_input'])) {
                $this->log('error', 'メタ情報: ' . json_encode($post_data['meta_input'], JSON_UNESCAPED_UNICODE));
            }
            
            // データベースエラーもチェック
            if ($wpdb->last_error) {
                $this->log('error', 'データベースエラー詳細: ' . $wpdb->last_error);
            }
            
            return $post_id;
        }
        
        // 投稿ID: 0も失敗として扱う
        if ($post_id === 0 || empty($post_id)) {
            $this->log('error', '投稿作成に失敗: wp_insert_post returned 0');
            $this->log('error', '投稿データデバッグ: タイトル="' . mb_substr($post_data['post_title'], 0, 100) . '"');
            $this->log('error', 'コンテンツ先頭100文字: "' . mb_substr(strip_tags($post_data['post_content']), 0, 100) . '"');
            
            // 最後の WordPress エラーを取得
            global $wpdb;
            if ($wpdb->last_error) {
                $this->log('error', 'WordPress DB エラー: ' . $wpdb->last_error);
            }
            
            return new WP_Error('post_creation_failed', 'WordPressへの投稿作成に失敗しました。');
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
        
        // カスタムプロンプトがあればそれを使用
        $custom_prompt = $settings['custom_prompt'] ?? '';
        if (!empty($custom_prompt)) {
            return $this->build_custom_prompt($custom_prompt, $settings);
        }
        
        // 言語指定を作成
        $language_names = array_map(array($this, 'get_language_name'), $selected_languages);
        $language_text = implode('と', $language_names);
        
        $current_date = current_time('Y年n月j日');
        $current_year = current_time('Y');
        
        // シンプルで効果的なプロンプト
        $prompt = "現在は{$current_date}（{$current_year}年）です。\n\n";
        $prompt .= "【重要】: 2024年以前の古い情報ではなく、{$current_year}年の最新情報のみを使用してください。\n\n";
        $prompt .= "【{$language_text}】のニュースから、【{$search_keywords}】に関する{$current_year}年の最新ニュース（特に直近数ヶ月の新しい情報）を送ってください。5本ぐらいが理想です。\n";
        $prompt .= "ニュースの背景や文脈を簡単にまとめ、かつ、上記の最新ニュースのリンク先を参考情報元として記事のタイトルとリンクを記載し、なぜ今、これが起こっているのか、という背景情報を踏まえて、今後どのような影響をあたえるのか、推察もしてください。\n";
        $prompt .= "全部で【{$word_count}文字】程度にまとめてください。充実した内容で。\n";
        if ($writing_style !== '標準') {
            $prompt .= "文体は{$writing_style}風でお願いします。\n";
        }
        $prompt .= "\n";
        
        $prompt .= "構成は以下のようなイメージです。見出しは【３】個ぐらいにしてください\n";
        $prompt .= "---------------------------------\n";
        $prompt .= "記事タイトル\n";
        $prompt .= "リード文\n";
        $prompt .= "見出しH2\n";
        $prompt .= "本文\n";
        $prompt .= "見出しH2\n";
        $prompt .= "本文\n";
        $prompt .= "・・・\n";
        $prompt .= "---------------------------------\n";
        $prompt .= "適時、参照元リンクを本文中にいれてください。\n\n";
        
        $prompt .= "**重要**: 記事の最後に必ず参考情報源セクションを含めてください：\n";
        $prompt .= "## 参考情報源\n";
        $prompt .= "- {$current_year}年の実際のニュース記事のタイトルとリンクを記載\n";
        $prompt .= "- 2024年以前の古い記事は使用禁止\n";
        $prompt .= "- 例: [OpenAI、{$current_year}年新機能発表]({$current_year}年のURL)\n";
        $prompt .= "- 例: [Google AI、{$current_year}年最新技術開発]({$current_year}年のURL)\n\n";
        
        return $prompt;
    }
    
    /**
     * Gemini用Web検索特化プロンプト構築
     */
    private function build_gemini_search_prompt($settings, $model = '') {
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI';
        $selected_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $word_count = $settings['article_word_count'] ?? 500;
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        
        // カスタムプロンプトがあればそれを使用
        $custom_prompt = $settings['custom_prompt'] ?? '';
        if (!empty($custom_prompt)) {
            return $this->build_custom_prompt($custom_prompt, $settings);
        }
        
        $current_date = current_time('Y年n月j日');
        $current_year = current_time('Y');
        
        // モデルに応じてプロンプトを最適化
        if ($model === 'gemini-2.5-flash') {
            // Gemini 2.5 Google Search 特化プロンプト
            $prompt = "I need you to search Google for current news about {$search_keywords}. ";
            $prompt .= "Please find recent news articles from {$current_year} and write a comprehensive article in Japanese.\n\n";
            $prompt .= "Search for: \"{$search_keywords} ニュース {$current_year}\"\n\n";
            $prompt .= "Requirements:\n";
            $prompt .= "- Search Google for real news articles\n";
            $prompt .= "- Include actual URLs from news websites\n";
            $prompt .= "- Write entire article in Japanese\n";
            $prompt .= "- Use approximately {$word_count} characters\n";
            $prompt .= "- Include proper headings and structure\n\n";
        } else {
            // その他のGeminiモデル用プロンプト
            $prompt = "{$current_year}年の【{$search_keywords}】に関する最新動向記事を作成してください。\n\n";
        }
        
        
        $prompt .= "要件: {$word_count}文字程度、タイトル25文字以内";
        if ($writing_style !== '標準') {
            $prompt .= "、{$writing_style}風の文体";
        }
        $prompt .= "。";
        
        return $prompt;
    }
    
    /**
     * カスタムプロンプト構築
     */
    private function build_custom_prompt($custom_prompt, $settings) {
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース';
        $selected_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $word_count = $settings['article_word_count'] ?? 500;
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        
        // 言語指定を作成
        $language_names = array_map(array($this, 'get_language_name'), $selected_languages);
        $language_text = implode('と', $language_names);
        
        // プレースホルダーを置換
        $prompt = str_replace('{言語}', $language_text, $custom_prompt);
        $prompt = str_replace('{キーワード}', $search_keywords, $prompt);
        $prompt = str_replace('{文字数}', $word_count, $prompt);
        $prompt = str_replace('{文体}', $writing_style, $prompt);
        
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
    private function call_claude_api($prompt, $api_key, $settings = null) {
        if ($settings === null) {
            $settings = get_option('ai_news_autoposter_settings', array());
        }
        $url = 'https://api.anthropic.com/v1/messages';
        
        $this->log('info', 'Claude API呼び出しを開始します。プロンプト長: ' . strlen($prompt) . '文字');
        
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        );
        
        $body = array(
            'model' => $settings['claude_model'] ?? 'claude-3-5-haiku-20241022',
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
        // 構造化レスポンス（TITLE:, TAGS:, CONTENT:）の場合
        if (strpos($response, 'TITLE:') !== false && strpos($response, 'CONTENT:') !== false) {
            return $this->parse_structured_response($response);
        }
        
        // シンプルなレスポンス（タイトルと本文のみ）の場合
        return $this->parse_simple_response($response);
    }
    
    /**
     * タイトルを適切な長さに短縮
     */
    private function shorten_title($title, $max_length = 30) {
        if (mb_strlen($title) <= $max_length) {
            return $title;
        }
        
        // 意味のある区切り点で短縮（より長い最小長を保証）
        $good_punctuation = array('。', '！', '!', '？', '?', '）', ')');
        foreach ($good_punctuation as $punct) {
            $pos = mb_strpos($title, $punct);
            if ($pos !== false && $pos >= 15 && $pos <= $max_length) { // 最低15文字は保証
                return mb_substr($title, 0, $pos + 1);
            }
        }
        
        // 「、」「：」「:」は意味が続く場合があるので、より慎重に処理
        $continue_punctuation = array('、', '：', ':');
        foreach ($continue_punctuation as $punct) {
            $pos = mb_strpos($title, $punct);
            if ($pos !== false && $pos >= 20 && $pos <= $max_length) { // より長い最小長を要求
                return mb_substr($title, 0, $pos + 1);
            }
        }
        
        // 句読点による良い切り位置がない場合は、単語境界で切り詰め
        $words = preg_split('/[\s　]+/u', $title); // 空白で分割
        $result = '';
        foreach ($words as $word) {
            if (mb_strlen($result . $word) > $max_length - 3) {
                break;
            }
            $result .= ($result ? ' ' : '') . $word;
        }
        
        return $result ? $result . '...' : mb_substr($title, 0, $max_length - 3) . '...';
    }
    
    /**
     * 構造化レスポンス解析
     */
    private function parse_structured_response($response) {
        $lines = explode("\n", $response);
        $title = '';
        $tags = array();
        $sources = array();
        $content = '';
        $in_content = false;
        
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
                continue;
            } elseif ($in_content) {
                $content .= $line . "\n";
            }
        }
        
        // タイトルはLLMが適切な長さで生成するため、短縮処理は無効化
        $title = $title ?: '最新AIニュース: ' . date('Y年m月d日');
        // $title = $this->shorten_title($title); // 無効化
        
        return array(
            'title' => $title,
            'content' => trim($content), // post_process_contentは後で呼ぶ
            'tags' => array_filter($tags)
        );
    }
    
    /**
     * シンプルレスポンス解析
     */
    private function parse_simple_response($response) {
        // マークダウンの見出しを除去
        $response = preg_replace('/^#{1,6}\s+/', '', $response, 1);
        $response = preg_replace('/^#{1,6}\s+/m', '', $response);
        
        $lines = explode("\n", $response);
        $title = '';
        $content = '';
        $tags = array();
        
        // タイトル抽出の改善
        foreach ($lines as $index => $line) {
            $line = trim($line);
            
            // 空行やマークダウン記法をスキップ
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '**') === 0) {
                continue;
            }
            
            // 最初の有効な行をタイトルとして使用
            if (empty($title)) {
                // タイトルをクリーンアップ
                $title = $this->clean_title($line);
                
                // タイトル以降をコンテンツとして取得
                $remaining_lines = array_slice($lines, $index + 1);
                $content = implode("\n", $remaining_lines);
                break;
            }
        }
        
        // コンテンツのクリーンアップ
        $content = $this->clean_content($content);
        
        // タイトルからタグを生成
        if (stripos($title . $content, 'AI') !== false) $tags[] = 'AI';
        if (stripos($title . $content, '人工知能') !== false) $tags[] = '人工知能';
        if (stripos($title . $content, 'ChatGPT') !== false) $tags[] = 'ChatGPT';
        if (stripos($title . $content, 'OpenAI') !== false) $tags[] = 'OpenAI';
        if (stripos($title . $content, 'Google') !== false) $tags[] = 'Google';
        
        // タイトルが空の場合はデフォルトを設定
        $title = $title ?: '最新AIニュース: ' . date('Y年m月d日');
        
        return array(
            'title' => $title,
            'content' => trim($content),
            'tags' => array_filter($tags)
        );
    }
    
    /**
     * タイトルをクリーンアップ
     */
    private function clean_title($title) {
        // マークダウン記法を除去
        $title = preg_replace('/^#{1,6}\s+/', '', $title);
        $title = preg_replace('/\*\*(.+?)\*\*/', '$1', $title);
        $title = preg_replace('/__(.+?)__/', '$1', $title);
        
        // 不適切な終わり方を修正
        if (preg_match('/^(.+?)(?:と|が|は|を|に|で|の)$/', $title, $matches)) {
            $title = $matches[1];
        }
        
        // 長すぎる場合は適切に短縮
        if (mb_strlen($title) > 50) {
            $title = mb_substr($title, 0, 47) . '...';
        }
        
        return trim($title);
    }
    
    /**
     * コンテンツをクリーンアップ
     */
    private function clean_content($content) {
        // 余分な空行を除去
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        
        // 不完全な文章で終わっている場合は適切に処理
        $content = trim($content);
        
        // 文章の終わり方をチェックして修正
        $content = $this->fix_incomplete_ending($content);
        
        // 最低限の文字数チェック
        if (mb_strlen($content) < 100) {
            $content .= "\n\n※ この記事は途中で生成が中断されました。より詳細な情報については、関連するニュースソースをご確認ください。";
        }
        
        return $content;
    }
    
    /**
     * 不完全な文末を修正
     */
    private function fix_incomplete_ending($content) {
        // 最後の文字を確認
        $last_char = mb_substr($content, -1);
        
        // 不完全な終わり方のパターンを検出
        $incomplete_patterns = array(
            // 助詞で終わっている場合（「また、大規模」など）
            '/[、が、は、を、に、で、の、と、も、から、まで、より、について、に関して、として]$/',
            // 「〜など、」「〜等、」のパターン
            '/(?:など|等)[、,]$/',
            // 数字や英字で終わっている場合
            '/[0-9a-zA-Z]$/',
            // カンマで終わっている場合
            '/[、,]$/',
            // 接続詞で終わっている場合
            '/(?:しかし|また|さらに|一方|このため|その結果|つまり|なお|ちなみに|ただし)$/',
            // 動詞の連用形で終わっている場合
            '/(?:開始|実施|展開|推進|発表|導入|提供|支援|対応|実現)(?:する|し|した)(?:など|等)[、,]?$/',
            // 「〜するなど、」のような不完全な列挙
            '/[ぁ-ん](?:する|した|している)(?:など|等)[、,]?$/',
        );
        
        $is_incomplete = false;
        foreach ($incomplete_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $is_incomplete = true;
                break;
            }
        }
        
        // 適切な句読点で終わっていない場合
        if (!in_array($last_char, array('。', '！', '？', '」', '』', ')', '）'))) {
            $is_incomplete = true;
        }
        
        // 不完全な場合は適切に修正
        if ($is_incomplete) {
            // 最後の完全な文を見つける
            $sentences = preg_split('/[。！？]/', $content);
            
            if (count($sentences) > 1) {
                // 最後の不完全な文を除去し、その前の完全な文で終わらせる
                array_pop($sentences); // 最後の不完全な部分を削除
                $last_complete = array_pop($sentences);
                
                if (!empty($last_complete)) {
                    $complete_content = implode('。', $sentences);
                    if (!empty($complete_content)) {
                        $complete_content .= '。' . $last_complete . '。';
                    } else {
                        $complete_content = $last_complete . '。';
                    }
                    
                    // 継続感を示す文を追加
                    $complete_content .= "\n\n※ この分野の最新動向については、引き続き注目が集まっています。";
                    
                    return $complete_content;
                }
            } else {
                // 文全体が不完全な場合
                $content = rtrim($content, '、。！？');
                $content .= "。";
                $content .= "\n\n※ この分野の詳細については、関連するニュースソースをご確認ください。";
            }
        }
        
        return $content;
    }
    
    /**
     * 部分的なコンテンツが記事として成り立つかチェック
     */
    private function is_viable_partial_content($content) {
        $content_length = mb_strlen($content);
        $this->log('info', 'Partial content 評価: 長さ=' . $content_length . '文字');
        
        // 最低限の条件を緩和（100文字から受け入れ）
        if ($content_length < 100) {
            $this->log('info', 'Partial content 拒否: 長さ不足 (' . $content_length . '文字 < 100文字)');
            return false;
        }
        
        // タイトルらしき行があるかチェック（条件を緩和）
        $lines = explode("\n", $content);
        $has_title = false;
        foreach ($lines as $index => $line) {
            $line = trim($line);
            $line_length = mb_strlen($line);
            // 条件を緩和: 5文字以上、150文字未満（より柔軟に）
            if (!empty($line) && $line_length >= 5 && $line_length < 150) {
                $has_title = true;
                $this->log('info', 'Partial content タイトル候補[' . $index . ']: "' . mb_substr($line, 0, 50) . '..." (' . $line_length . '文字)');
                break;
            }
        }
        
        if (!$has_title) {
            $this->log('info', 'Partial content 拒否: タイトル候補なし');
            // デバッグ用に最初の3行を表示
            for ($i = 0; $i < min(3, count($lines)); $i++) {
                $line = trim($lines[$i]);
                if (!empty($line)) {
                    $this->log('info', 'Line[' . $i . ']: "' . mb_substr($line, 0, 100) . '..." (' . mb_strlen($line) . '文字)');
                }
            }
        } else {
            $this->log('info', 'Partial content 承認: 利用可能');
        }
        
        return $has_title;
    }
    
    /**
     * グラウンディングソースを記事に統合
     */
    private function integrate_grounding_sources($article_data, $grounding_sources) {
        if (empty($grounding_sources)) {
            return $article_data;
        }
        
        $this->log('info', 'グラウンディングソースを記事に統合開始');
        
        // 記事内容に参考情報源セクションを追加または置換
        $content = $article_data['content'];
        
        // 複数の形式の参考情報源セクションを段階的に削除
        $reference_patterns = array(
            // Markdownスタイル（# ## ###）
            '/#+\s*参考.*?(?=\n#+|\n\n|\Z)/is',
            // HTMLスタイル（<h2> <h3>など）
            '/<h[2-6][^>]*>\s*参考.*?<\/h[2-6]>.*?(?=<h[2-6]|\Z)/is',
            // 太字スタイル（**参考情報源**）
            '/\*\*\s*参考.*?\*\*.*?(?=\n\n|\*\*|\Z)/is',
            // リスト形式で参考情報が続く場合
            '/参考情報.*?(?:\n-.*?(?:https?:\/\/[^\s\)]+|vertexaisearch\.cloud\.google\.com[^\s\)]+).*?)*(?=\n\n|\Z)/is'
        );
        
        $removed_sections = 0;
        foreach ($reference_patterns as $pattern) {
            $matches = preg_match_all($pattern, $content);
            if ($matches > 0) {
                $content = preg_replace($pattern, '', $content);
                $removed_sections += $matches;
                $this->log('info', "参考情報源パターンで{$matches}件のセクションを削除");
            }
        }
        
        // Google リダイレクトURLを含む行を削除
        $redirect_patterns = array(
            '/.*vertexaisearch\.cloud\.google\.com.*\n?/i',
            '/.*https?:\/\/[^\s]*redirect[^\s]*.*\n?/i'
        );
        
        foreach ($redirect_patterns as $pattern) {
            $matches = preg_match_all($pattern, $content);
            if ($matches > 0) {
                $content = preg_replace($pattern, '', $content);
                $this->log('info', "リダイレクトURLで{$matches}件の行を削除");
            }
        }
        
        // サンプルURLやダミーURLを削除
        $dummy_patterns = array(
            '/https?:\/\/[^\/]*sample[^\/\s)]*[^\s)]*/',
            '/https?:\/\/[^\/]*example[^\/\s)]*[^\s)]*/',
            '/https?:\/\/[^\/]*dummy[^\/\s)]*[^\s)]*/',
            '/https?:\/\/[^\/]*test[^\/\s)]*[^\s)]*/',
            '/https?:\/\/[^\/]*placeholder[^\/\s)]*[^\s)]*/'
        );
        
        foreach ($dummy_patterns as $pattern) {
            $content = preg_replace($pattern, '[参考URL]', $content);
        }
        
        // 連続する空行を整理
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);
        
        // 新しい統合された参考情報源セクションを生成
        $sources_section = "\n\n## 参考情報源\n";
        foreach ($grounding_sources as $index => $source) {
            // デバッグ: ソースの完全な内容をログ出力
            $this->log('info', "ソース[" . ($index + 1) . "]の内容: " . json_encode($source, JSON_UNESCAPED_UNICODE));
            
            $original_title = $source['title'] ?? 'AI関連記事';
            $original_url = $source['url'] ?? '';
            
            // タイトルとURLを基本的にそのまま使用（過度な処理を避ける）
            $display_title = $original_title;
            
            // タイトルが明らかにドメイン名のみの場合のみ、より具体的なタイトルを生成
            if (preg_match('/^[a-zA-Z0-9\-\.]+\.(com|co\.jp|jp|net|org)$/i', $original_title)) {
                $domain_to_name = array(
                    'itmedia.co.jp' => 'ITmedia：AI技術の最新動向',
                    'techcrunch.com' => 'TechCrunch：AIスタートアップニュース',
                    'wired.com' => 'Wired：AI技術の未来展望',
                    'note.com' => 'Note：AI開発者による解説記事',
                    'microsoft.com' => 'Microsoft：AI製品・サービス発表',
                    'ibm.com' => 'IBM：企業向けAIソリューション',
                    'qiita.com' => 'Qiita：AI開発技術情報',
                    'brainpad.co.jp' => 'ブレインパッド：AIデータ分析事例',
                    'gartner.co.jp' => 'ガートナー：AI市場分析レポート',
                    'hp.com' => 'HP：AI活用ビジネス事例',
                    'sotatek.com' => 'SotaTek：AI開発サービス紹介',
                    'agentec.jp' => 'エージェンテック：AIエージェント技術',
                    'atarayo.co.jp' => 'あたらよ：AI業界ニュース',
                    'shift-ai.co.jp' => 'SHIFT AI：AI品質保証技術',
                    'kimini.online' => 'Kimini：AI教育サービス',
                    'ai-kenkyujo.com' => 'AI研究所：AI技術解説',
                    'lion.co.jp' => 'ライオン：AI活用製品開発'
                );
                
                $display_title = $domain_to_name[$original_title] ?? ($original_title . '：AI関連記事');
            }
            
            // タイトルが長すぎる場合は短縮
            if (mb_strlen($display_title) > 50) {
                $display_title = mb_substr($display_title, 0, 47) . '...';
            }
            
            $title = esc_html($display_title);
            
            // 長いグラウンディングURLを簡潔に置換（データベース負荷軽減）
            if (strpos($original_url, 'vertexaisearch.cloud.google.com') !== false) {
                // グラウンディングURLは検索リンクに置換
                $url = esc_url('https://google.com/search?q=' . urlencode($original_title));
            } else {
                $url = esc_url($original_url);
            }
            
            // URLが空の場合のみ、検索URLを生成
            if (empty($url) || $url === 'https://') {
                $url = esc_url('https://google.com/search?q=' . urlencode($display_title));
            }
            
            $sources_section .= "- <a href=\"{$url}\" target=\"_blank\">{$title}</a>\n";
            $this->log('info', "統合URL[" . ($index + 1) . "]: {$title} - {$url}");
        }
        
        // 最終的な統合セクションを追加
        $content .= $sources_section;
        $this->log('info', "{$removed_sections}件の既存参考情報源セクションを削除し、統合セクションを追加しました");
        
        $article_data['content'] = $content;
        $this->log('info', 'グラウンディングソース統合完了');
        
        return $article_data;
    }
    
    /**
     * 記事内容の後処理（Markdown変換、免責事項追加など）
     */
    private function post_process_content($content) {
        // コンテンツの最終クリーンアップ（不完全な文末を修正）
        $content = $this->fix_incomplete_ending($content);
        
        // MarkdownをHTMLに変換
        $content = $this->convert_markdown_to_html($content);
        
        // 免責事項を追加
        $settings = get_option('ai_news_autoposter_settings', array());
        $enable_disclaimer = $settings['enable_disclaimer'] ?? true;
        $disclaimer_text = $settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。';
        
        if ($enable_disclaimer && !empty($disclaimer_text)) {
            $content = trim($content) . "\n\n<div style=\"margin-top: 20px; padding: 10px; background-color: #f0f0f0; border-left: 4px solid #ccc; font-size: 14px; color: #666;\">" . esc_html($disclaimer_text) . "</div>";
        }
        
        return trim($content);
    }
    
    /**
     * MarkdownをHTMLに変換
     */
    private function convert_markdown_to_html($content) {
        // 既にHTMLタグが含まれている場合は軽微な処理のみ
        if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
            // 基本的なクリーニングのみ
            return $this->clean_html_content($content);
        }
        
        // 見出し変換
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
        
        // 太字変換（既存のHTMLタグと競合しないよう調整）
        $content = preg_replace('/\*\*([^*<>]+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/__([^_<>]+?)__/', '<strong>$1</strong>', $content);
        
        // 斜体変換
        $content = preg_replace('/\*([^*<>]+?)\*/', '<em>$1</em>', $content);
        $content = preg_replace('/_([^_<>]+?)_/', '<em>$1</em>', $content);
        
        // リンク変換
        $content = preg_replace('/\[([^\]]+?)\]\(([^)]+?)\)/', '<a href="$2" target="_blank">$1</a>', $content);
        
        // リスト変換を改善
        $content = $this->convert_lists($content);
        
        // 段落変換を改善
        $content = $this->convert_paragraphs($content);
        
        return $content;
    }
    
    /**
     * HTMLコンテンツをクリーニング
     */
    private function clean_html_content($content) {
        // 不適切な改行を修正
        $content = preg_replace('/\n+/', "\n", $content);
        $content = preg_replace('/>\s*\n\s*</', '><', $content);
        
        // 基本的な段落構造を確保
        if (!preg_match('/<p>|<div>|<h[1-6]>/', $content)) {
            $lines = explode("\n", $content);
            $processed_lines = array();
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && !preg_match('/^</', $line)) {
                    $line = '<p>' . $line . '</p>';
                }
                if (!empty($line)) {
                    $processed_lines[] = $line;
                }
            }
            $content = implode("\n", $processed_lines);
        }
        
        return $content;
    }
    
    /**
     * リスト変換を改善
     */
    private function convert_lists($content) {
        $lines = explode("\n", $content);
        $result = array();
        $in_list = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (preg_match('/^[-*] (.+)/', $trimmed, $matches)) {
                if (!$in_list) {
                    $result[] = '<ul>';
                    $in_list = true;
                }
                $result[] = '<li>' . $matches[1] . '</li>';
            } else {
                if ($in_list) {
                    $result[] = '</ul>';
                    $in_list = false;
                }
                $result[] = $line;
            }
        }
        
        if ($in_list) {
            $result[] = '</ul>';
        }
        
        return implode("\n", $result);
    }
    
    /**
     * 段落変換を改善
     */
    private function convert_paragraphs($content) {
        // 段落変換（2つ以上の改行を段落区切りとする）
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $processed_paragraphs = array();
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // 見出し、リスト、divタグでない場合のみpタグで囲む
                if (!preg_match('/^<(h[1-6]|ul|ol|li|div|p)/', $paragraph)) {
                    // 単一行の場合のみpタグで囲む（複数行は既にタグ構造がある可能性）
                    if (strpos($paragraph, "\n") === false) {
                        $paragraph = '<p>' . $paragraph . '</p>';
                    } else {
                        // 複数行の場合は行単位でpタグ処理
                        $lines = explode("\n", $paragraph);
                        $tagged_lines = array();
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line) && !preg_match('/^</', $line)) {
                                $line = '<p>' . $line . '</p>';
                            }
                            if (!empty($line)) {
                                $tagged_lines[] = $line;
                            }
                        }
                        $paragraph = implode("\n", $tagged_lines);
                    }
                }
                $processed_paragraphs[] = $paragraph;
            }
        }
        
        return implode("\n\n", $processed_paragraphs);
    }
    
    /**
     * Gemini API呼び出し（Google Search Grounding付き）
     */
    private function call_gemini_api($prompt, $api_key, $model = 'gemini-1.5-flash') {
        if (empty($api_key)) {
            return new WP_Error('gemini_api_error', 'Gemini APIキーが設定されていません。');
        }
        
        // Gemini 2.0モデルはそのまま使用
        // if ($model === 'gemini-2.0-flash-exp') {
        //     $model = 'gemini-1.5-flash-002';
        // }
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
        
        $this->log('info', 'Gemini API呼び出しを開始します。モデル: ' . $model);
        
        // プロンプトの長さと設定に基づいてmaxOutputTokensを動的に設定
        $prompt_length = strlen($prompt);
        
        // 設定から期待文字数を取得
        $settings = get_option('ai_news_autoposter_settings', array());
        $expected_chars = $settings['article_word_count'] ?? 500;
        
        // Gemini 2.5でGoogle Search Groundingを使用する場合はトークンを調整
        if ($model === 'gemini-2.5-flash') {
            // Google Search Groundingのトークン消費を考慮し、余裕を持たせて設定
            $max_tokens = 2000; // MAX_TOKENSエラー回避のため減らす
            $this->log('info', 'Gemini 2.5 + Grounding用にmaxOutputTokensを2000に設定（MAX_TOKENSエラー回避）');
        } else {
            // 文字数をトークン数に変換（1トークン ≈ 0.7文字として計算）
            $expected_tokens = intval($expected_chars / 0.7);
            
            // プロンプト長に応じた調整
            $max_tokens = $expected_tokens;
            
            if ($prompt_length > 2000) {
                $max_tokens = min($expected_tokens, 2000); // 制限を緩和
            } elseif ($prompt_length > 1500) {
                $max_tokens = min($expected_tokens, 2500);
            } else {
                $max_tokens = min($expected_tokens, 3000); // 最大値制限を緩和
            }
            
            // 最低限の長さを保証
            $max_tokens = max($max_tokens, 1500); // 最低値を上げる
        }
        
        $this->log('info', '設定文字数: ' . $expected_chars . '、プロンプト長: ' . $prompt_length . '文字、maxOutputTokens: ' . $max_tokens);
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'maxOutputTokens' => $max_tokens,
                'temperature' => 0.7
            )
        );
        
        // Google Search Grounding - Gemini 2.5のみ対応
        if ($model === 'gemini-2.5-flash') {
            $body['tools'] = array(
                array(
                    'google_search' => new stdClass()
                )
            );
            $this->log('info', 'Gemini 2.5でGoogle Search Grounding有効化');
        }
        
        $this->log('info', 'Google Search Grounding設定: ' . json_encode($body['tools'] ?? null));
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 360 // 6分
        ));
        
        if (is_wp_error($response)) {
            $this->log('error', 'Gemini API リクエストエラー: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log('info', 'Gemini API レスポンスコード: ' . $response_code);
        
        if ($response_code !== 200) {
            $this->log('error', 'Gemini API エラーレスポンス: ' . $response_body);
            return new WP_Error('gemini_api_error', 'Gemini API エラー: ' . $response_body);
        }
        
        // UTF-8エンコーディングを確保してからJSON解析
        if (!mb_check_encoding($response_body, 'UTF-8')) {
            $this->log('warning', 'Gemini APIレスポンスのエンコーディングを修正');
            $response_body = mb_convert_encoding($response_body, 'UTF-8', 'UTF-8//IGNORE');
        }
        
        $response_data = json_decode($response_body, true);
        
        // レスポンス形式の柔軟な解析
        $generated_text = '';
        
        if (!empty($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            // 通常のGeminiレスポンス形式
            $generated_text = $response_data['candidates'][0]['content']['parts'][0]['text'];
        } elseif (!empty($response_data['candidates'][0]['content']['text'])) {
            // 代替形式1
            $generated_text = $response_data['candidates'][0]['content']['text'];
        } elseif (!empty($response_data['candidates'][0]['text'])) {
            // 代替形式2
            $generated_text = $response_data['candidates'][0]['text'];
        } else {
            // finishReasonをチェック
            $finish_reason = $response_data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            $this->log('info', 'Gemini API finishReason: ' . $finish_reason);
            
            if ($finish_reason === 'MAX_TOKENS') {
                // トークン制限に達した場合でも、部分的なコンテンツが取得できるかチェック
                $potential_content_paths = [
                    $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '',
                    $response_data['candidates'][0]['content']['text'] ?? '',
                    $response_data['candidates'][0]['text'] ?? ''
                ];
                
                foreach ($potential_content_paths as $index => $content) {
                    $this->log('info', 'MAX_TOKENS パス[' . $index . ']: ' . (empty($content) ? '空' : strlen($content) . '文字'));
                    
                    if (!empty($content) && strlen($content) > 50) { // 閾値を50文字に緩和
                        $this->log('info', 'MAX_TOKENS コンテンツ確認中: "' . mb_substr($content, 0, 200) . '..."');
                        
                        // 部分的なコンテンツでも記事として成り立つかチェック
                        if ($this->is_viable_partial_content($content)) {
                            $this->log('warning', 'Gemini API: トークン制限に達しましたが、部分的なコンテンツ(' . strlen($content) . '文字)を返します。');
                            
                            // 部分的なコンテンツでもGrounding情報を含む構造化レスポンスを返す
                            $result = array(
                                'text' => $content,
                                'grounding_sources' => array()
                            );
                            
                            // Grounding情報を抽出
                            if (isset($response_data['candidates'][0]['groundingMetadata']['groundingChunks'])) {
                                $grounding_chunks = $response_data['candidates'][0]['groundingMetadata']['groundingChunks'];
                                foreach ($grounding_chunks as $chunk) {
                                    if (isset($chunk['web']['uri']) && isset($chunk['web']['title'])) {
                                        $result['grounding_sources'][] = array(
                                            'title' => $chunk['web']['title'],
                                            'url' => $chunk['web']['uri']
                                        );
                                    }
                                }
                                $this->log('info', 'MAX_TOKENS状態で' . count($result['grounding_sources']) . '件のGrounding情報を抽出しました');
                            }
                            
                            return $result;
                        } else {
                            $this->log('info', 'パス[' . $index . ']のコンテンツは viable_partial_content チェックを通過しませんでした');
                        }
                    } else {
                        $this->log('info', 'パス[' . $index . ']のコンテンツは長さ不足です');
                    }
                }
                
                // 全てのパスが空の場合、Grounding情報だけでも最小限の記事を作成
                if (isset($response_data['candidates'][0]['groundingMetadata']['groundingChunks'])) {
                    $grounding_chunks = $response_data['candidates'][0]['groundingMetadata']['groundingChunks'];
                    if (!empty($grounding_chunks)) {
                        $this->log('warning', 'コンテンツは空ですが、Grounding情報(' . count($grounding_chunks) . '件)から最小限の記事を生成します');
                        
                        // Grounding情報から最小限のコンテンツを生成
                        $minimal_content = "2025年のAI業界では、以下のような最新動向が注目されています：\n\n";
                        $grounding_sources = array();
                        
                        foreach ($grounding_chunks as $index => $chunk) {
                            if (isset($chunk['web']['uri']) && isset($chunk['web']['title'])) {
                                $minimal_content .= "• " . $chunk['web']['title'] . "\n";
                                $grounding_sources[] = array(
                                    'title' => $chunk['web']['title'],
                                    'url' => $chunk['web']['uri']
                                );
                            }
                        }
                        
                        $minimal_content .= "\n※ 詳細については、以下の参考情報源をご確認ください。";
                        
                        return array(
                            'text' => $minimal_content,
                            'grounding_sources' => $grounding_sources
                        );
                    }
                }
                
                $this->log('error', 'Gemini API: トークン上限に達し、利用可能なコンテンツがありません。');
                return new WP_Error('gemini_api_error', 'Gemini API: トークン上限に達しました。プロンプトを短くしてください。');
            }
            
            $this->log('error', 'Gemini API レスポンスの形式が不正です: ' . $response_body);
            return new WP_Error('gemini_api_error', 'Gemini API レスポンスの形式が不正です。finishReason: ' . $finish_reason);
        }
        
        // Grounding情報を詳細にログ記録
        if (isset($response_data['candidates'][0]['groundingMetadata'])) {
            $grounding_metadata = $response_data['candidates'][0]['groundingMetadata'];
            
            // 新しい形式: groundingChunks
            $grounding_chunks = $grounding_metadata['groundingChunks'] ?? array();
            if (!empty($grounding_chunks)) {
                $this->log('info', 'Web検索ソース数: ' . count($grounding_chunks) . '件');
                foreach ($grounding_chunks as $index => $chunk) {
                    if (isset($chunk['web']['uri']) && isset($chunk['web']['title'])) {
                        $this->log('info', '参考URL[' . ($index + 1) . ']: ' . $chunk['web']['title'] . ' - ' . $chunk['web']['uri']);
                    }
                }
            } else {
                // 古い形式: groundingSources（念のため）
                $grounding_sources = $grounding_metadata['groundingSources'] ?? array();
                $this->log('info', 'Web検索ソース数: ' . count($grounding_sources) . '件');
                foreach ($grounding_sources as $index => $source) {
                    if (isset($source['uri'])) {
                        $this->log('info', '参考URL[' . ($index + 1) . ']: ' . $source['uri']);
                    }
                }
            }
            
            // Web検索クエリもログに記録
            if (isset($grounding_metadata['webSearchQueries'])) {
                $this->log('info', 'Web検索クエリ: ' . json_encode($grounding_metadata['webSearchQueries']));
            }
        } else {
            $this->log('warning', 'Grounding Metadataが見つかりません。Google Search Groundingが動作していない可能性があります。');
        }
        
        $this->log('info', 'Gemini API呼び出し完了。生成文字数: ' . mb_strlen($generated_text));
        
        // Grounding情報も含めて返す
        $result = array(
            'text' => $generated_text,
            'grounding_sources' => array()
        );
        
        // Grounding情報を抽出
        if (isset($response_data['candidates'][0]['groundingMetadata']['groundingChunks'])) {
            $grounding_chunks = $response_data['candidates'][0]['groundingMetadata']['groundingChunks'];
            foreach ($grounding_chunks as $chunk) {
                if (isset($chunk['web']['uri']) && isset($chunk['web']['title'])) {
                    $result['grounding_sources'][] = array(
                        'title' => $chunk['web']['title'],
                        'url' => $chunk['web']['uri']
                    );
                }
            }
        }
        
        return $result;
    }
    
    /**
     * メタディスクリプション生成
     */
    private function generate_meta_description($title, $settings) {
        $template = $settings['meta_description_template'] ?? '最新の業界ニュースをお届けします。{title}について詳しく解説いたします。';
        return str_replace('{title}', $title, $template);
    }
    
    /**
     * 安全なメタディスクリプション生成
     */
    private function safe_generate_meta_description($title, $settings) {
        try {
            $this->log('info', 'メタディスクリプション生成開始');
            $result = $this->generate_meta_description($title, $settings);
            $this->log('info', 'メタディスクリプション生成完了');
            return $result;
        } catch (Exception $e) {
            $this->log('error', 'メタディスクリプション生成エラー: ' . $e->getMessage());
            return '最新の業界ニュースをお届けします。';
        }
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
        
        // 最新300件のみ保持
        $logs = array_slice($logs, -300);
        
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