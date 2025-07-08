<?php
/**
 * Plugin Name: AI News AutoPoster
 * Plugin URI: https://github.com/kitasinkita/ai-news-autoposter
 * Description: 任意のキーワードでニュースを自動生成・投稿するプラグイン。v2.0：プロンプト結果に任せる方式で高品質記事生成。Claude/Gemini API対応、文字数制限なし、自然なレイアウト。最新版は GitHub からダウンロードしてください。
 * Version: 2.4.0
 * Author: IT OPTIMIZATION CO.,LTD.
 * Author URI: https://github.com/kitasinkita
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-news-autoposter
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの基本定数
define('AI_NEWS_AUTOPOSTER_VERSION', '2.4.0');
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
        add_action('wp_ajax_get_default_prompt', array($this, 'get_default_prompt'));
        
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
                'post_status' => 'draft',
                'enable_tags' => true,
                'search_keywords' => 'アウトドアギア, キャンプ用品, 登山用品, ハイキング用品, テント, 寝袋, バックパック',
                'writing_style' => '夏目漱石',
                'news_languages' => array('japanese', 'english'), // english, japanese, chinese
                'output_language' => 'japanese', // japanese, english, chinese
                'article_word_count' => 1500,
                'article_count' => 3,
                'impact_analysis_length' => 500,
                'enable_disclaimer' => true,
                'disclaimer_text' => '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。',
                'enable_excerpt' => true,
                'custom_prompt' => '',
                'sources_section_title' => '{date}の{keyword}関連のニュース',
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
                                <td id="next-run-time"><?php 
                                    if (!($settings['auto_publish'] ?? false)) {
                                        echo '予定されていません（自動投稿が無効）';
                                    } elseif ($next_scheduled) {
                                        echo date('Y-m-d H:i:s', $next_scheduled);
                                    } else {
                                        echo '未設定';
                                    }
                                ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="ai-news-status-card">
                        <h3>手動実行</h3>
                        <p>記事を手動生成できます。自動投稿は「設定」で登録ください。</p>
                        <div class="ai-news-button-group">
                            <button type="button" class="ai-news-button-primary" id="generate-test-article">下書き記事生成</button>
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
            // 選択されたモデルに応じたAPIキーのバリデーション
            $selected_model = sanitize_text_field($_POST['claude_model']);
            $claude_api_key = sanitize_text_field($_POST['claude_api_key']);
            $gemini_api_key = sanitize_text_field($_POST['gemini_api_key']);
            
            $validation_error = false;
            $error_message = '';
            
            // モデルがGeminiかClaudeかを判定してバリデーション
            if (strpos($selected_model, 'gemini') === 0) {
                // Geminiモデルが選択された場合、Gemini APIキーが必須
                if (empty($gemini_api_key)) {
                    $validation_error = true;
                    $error_message = 'Geminiモデルを選択した場合、Gemini APIキーの入力が必要です。';
                }
            } else {
                // Claudeモデルが選択された場合、Claude APIキーが必須
                if (empty($claude_api_key)) {
                    $validation_error = true;
                    $error_message = 'Claudeモデルを選択した場合、Claude APIキーの入力が必要です。';
                }
            }
            
            if ($validation_error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
            } else {
                // バリデーションが通った場合のみ設定を保存
                $settings = array(
                    'claude_api_key' => $claude_api_key,
                    'claude_model' => $selected_model,
                    'gemini_api_key' => $gemini_api_key,
                    'auto_publish' => isset($_POST['auto_publish']),
                    'schedule_time' => sanitize_text_field($_POST['schedule_time']),
                    'max_posts_per_day' => intval($_POST['max_posts_per_day']),
                    'post_category' => intval($_POST['post_category']),
                    'seo_focus_keyword' => sanitize_text_field($_POST['seo_focus_keyword']),
                    'meta_description_template' => sanitize_textarea_field($_POST['meta_description_template']),
                    'post_status' => sanitize_text_field($_POST['post_status']),
                    'enable_tags' => isset($_POST['enable_tags']),
                    'search_keywords' => sanitize_text_field($_POST['search_keywords']),
                    'writing_style' => sanitize_text_field($_POST['writing_style']),
                    'news_languages' => isset($_POST['news_languages']) ? array_map('sanitize_text_field', $_POST['news_languages']) : array(),
                    'output_language' => sanitize_text_field($_POST['output_language']),
                    'article_word_count' => intval($_POST['article_word_count']),
                    'enable_disclaimer' => isset($_POST['enable_disclaimer']),
                    'enable_excerpt' => isset($_POST['enable_excerpt']),
                    'disclaimer_text' => sanitize_textarea_field($_POST['disclaimer_text']),
                    'sources_section_title' => sanitize_text_field($_POST['sources_section_title']),
                    'image_generation_type' => sanitize_text_field($_POST['image_generation_type']),
                    'dalle_api_key' => sanitize_text_field($_POST['dalle_api_key']),
                    'unsplash_access_key' => sanitize_text_field($_POST['unsplash_access_key']),
                    'news_sources' => $this->parse_news_sources($_POST),
                    'custom_prompt' => sanitize_textarea_field($_POST['custom_prompt'] ?? '')
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
            <h1>AI News AutoPoster 設定 <span style="font-size: 14px; color: #666; margin-left: 10px;">v<?php echo AI_NEWS_AUTOPOSTER_VERSION; ?></span></h1>
            
            <form method="post" action="" id="ai-news-settings-form">
                <?php wp_nonce_field('ai_news_autoposter_settings', 'ai_news_autoposter_nonce'); ?>
                
                <!-- ===== AI・モデル設定 ===== -->
                <h2 class="ai-news-section-title">🤖 AI・モデル設定</h2>
                <table class="ai-news-form-table">
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
                        <th scope="row">Claude API キー</th>
                        <td>
                            <input type="password" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>" class="regular-text" />
                            <p class="ai-news-form-description">AnthropicのClaude APIキーを入力してください。Claudeモデル使用時に必要です。</p>
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
                        <th scope="row">画像生成方式</th>
                        <td>
                            <select name="image_generation_type">
                                <option value="none" <?php selected($settings['image_generation_type'] ?? 'placeholder', 'none'); ?>>なし（アイキャッチ画像を生成しない）</option>
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
                </table>
                
                <!-- ===== キーワード・SEO設定 ===== -->
                <h2 class="ai-news-section-title">🎯 キーワード・SEO設定</h2>
                <table class="ai-news-form-table">
                    <tr>
                        <th scope="row">検索キーワード</th>
                        <td>
                            <input type="text" name="search_keywords" value="<?php echo esc_attr($settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習, ChatGPT, OpenAI'); ?>" class="large-text ai-news-autosave" />
                            <p class="ai-news-form-description">記事生成時に検索するキーワードをカンマ区切りで入力してください。</p>
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
                        <th scope="row">タグ自動生成</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_tags" <?php checked($settings['enable_tags'] ?? true); ?> />
                                AIが関連タグを自動生成する
                            </label>
                        </td>
                    </tr>
                </table>
                
                <!-- ===== 記事生成設定 ===== -->
                <h2 class="ai-news-section-title">📝 記事生成設定</h2>
                <table class="ai-news-form-table">
                    <tr>
                        <th scope="row">文体スタイル</th>
                        <td>
                            <input type="text" name="writing_style" value="<?php echo esc_attr($settings['writing_style'] ?? '夏目漱石'); ?>" class="regular-text ai-news-autosave" />
                            <p class="ai-news-form-description">記事の文体スタイルを指定してください（例：夏目漱石、森鴎外、新聞記事風など）。</p>
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
                        <th scope="row">抜粋自動生成</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_excerpt" <?php checked($settings['enable_excerpt'] ?? true); ?> />
                                投稿の抜粋を自動生成する（20文字程度の簡潔な要約）
                            </label>
                            <p class="ai-news-form-description">記事タイトルから簡潔な抜粋を自動生成します。無効にすると抜粋は空になります。</p>
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
                                <button type="button" id="show-default-prompt" class="button">現在のデフォルトプロンプトを表示</button>
                                <div id="default-prompt-display" style="display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                                    <strong>現在のデフォルトプロンプト：</strong><br>
                                    <div id="default-prompt-content" style="white-space: pre-wrap; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; margin-top: 5px;"></div>
                                    <p><small>文字数: <span id="default-prompt-length"></span> 文字</small></p>
                                </div>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- 参考情報源セクションタイトル設定は削除（修正により不要）
                    <tr>
                        <th scope="row">参考情報源セクションタイトル</th>
                        <td>
                            <input type="text" name="sources_section_title" value="<?php echo esc_attr($settings['sources_section_title'] ?? '{date}の{keyword}関連のニュース'); ?>" class="regular-text ai-news-autosave" />
                            <p class="ai-news-form-description">
                                記事冒頭の参考情報源セクションのタイトルを設定してください。<br>
                                <strong>利用可能なプレースホルダー:</strong><br>
                                <code>{keyword}</code> - 検索キーワード<br>
                                <code>{date}</code> - 2025年7月6日<br>
                                <code>{date_short}</code> - 7月6日<br>
                                <code>{date_en}</code> - July 6, 2025<br>
                                <code>{date_iso}</code> - 2025-07-06<br>
                                <code>{today}</code> - 今日<br>
                                <code>{year}</code> - 2025<br>
                                <code>{month}</code> - 7月<br>
                                <code>{day}</code> - 6日<br>
                                <small>例: 「{date}の{keyword}関連のニュース」→「2025年7月6日のアウトドア関連のニュース」</small>
                            </p>
                        </td>
                    </tr>
                    -->
                </table>
                
                <!-- ===== スケジュール・投稿設定 ===== -->
                <h2 class="ai-news-section-title">⏰ スケジュール・投稿設定</h2>
                <table class="ai-news-form-table">
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
                            <input type="number" id="max_posts_per_day" name="max_posts_per_day" value="<?php echo esc_attr($settings['max_posts_per_day'] ?? 1); ?>" min="1" max="5" />
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
                        <th scope="row">投稿ステータス</th>
                        <td>
                            <select name="post_status">
                                <option value="publish" <?php selected($settings['post_status'] ?? 'publish', 'publish'); ?>>公開</option>
                                <option value="draft" <?php selected($settings['post_status'] ?? 'publish', 'draft'); ?>>下書き</option>
                                <option value="pending" <?php selected($settings['post_status'] ?? 'publish', 'pending'); ?>>承認待ち</option>
                            </select>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- ===== その他設定 ===== -->
                <h2 class="ai-news-section-title">⚙️ その他設定</h2>
                <table class="ai-news-form-table">
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
                            <p class="ai-news-form-description">
                                <strong>実ニュース取得用のRSSフィードURLを設定します。</strong><br>
                                各言語のRSSフィードURLを1行につき1つ入力してください。<br>
                                これらのRSSフィードからニュースを取得し、AIが関連記事を生成します。<br>
                                <small>例：ITメディア、ZDNet、AI関連ニュースサイトなど。デフォルトでAI関連のRSSフィードが設定されています。</small>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存', 'primary', 'submit', true, array('class' => 'ai-news-button-primary')); ?>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // デフォルトプロンプト表示ボタンのクリックイベント
            $('#show-default-prompt').click(function() {
                var button = $(this);
                var displayDiv = $('#default-prompt-display');
                
                if (displayDiv.is(':visible')) {
                    displayDiv.hide();
                    button.text('現在のデフォルトプロンプトを表示');
                    return;
                }
                
                button.text('読み込み中...');
                button.prop('disabled', true);
                
                $.ajax({
                    url: ai_news_autoposter_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_default_prompt',
                        nonce: ai_news_autoposter_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#default-prompt-content').text(response.data.prompt);
                            $('#default-prompt-length').text(response.data.length);
                            displayDiv.show();
                            button.text('デフォルトプロンプトを非表示');
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('通信エラーが発生しました。');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
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
        // 次回実行予定の表示ロジック
        $auto_publish_enabled = $settings['auto_publish'] ?? false;
        $next_scheduled = wp_next_scheduled('ai_news_autoposter_daily_cron');
        
        if (!$auto_publish_enabled) {
            $next_run_text = '予定されていません（自動投稿が無効）';
        } elseif ($next_scheduled) {
            $next_run_text = date('Y-m-d H:i:s', $next_scheduled);
        } else {
            $next_run_text = '未設定';
        }
        
        $stats = array(
            'posts_today' => $this->get_posts_count_today(),
            'total_posts' => $this->get_total_posts_count(),
            'last_run' => get_option('ai_news_autoposter_last_run', 'まだ実行されていません'),
            'next_run' => $next_run_text,
            'auto_publish_enabled' => $auto_publish_enabled
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
        // 出力バッファリングをクリア（JSONレスポンスを汚染しないため）
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_news_autoposter_nonce')) {
            $this->log('error', '手動投稿: Nonce検証失敗');
            wp_send_json_error('セキュリティチェックに失敗しました。');
            return;
        }
        
        // タイムアウトを延長
        set_time_limit(600); // 10分
        ini_set('max_execution_time', 600);
        
        $this->log('info', '手動投稿を開始します');
        
        // 既存の出力バッファを全てクリア
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 新しい出力バッファリングを開始してログ出力をキャプチャ
        ob_start();
        
        try {
            $result = $this->generate_and_publish_article(false, 'manual');
            
            // バッファをクリア（ログ出力を破棄）
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            if (is_wp_error($result)) {
                $this->log('error', '手動投稿失敗: ' . $result->get_error_message());
                
                // 最終的なクリーンアップ
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                wp_send_json_error($result->get_error_message());
            } else {
                $this->log('success', '手動投稿成功: 投稿ID ' . $result);
                $response_data = array(
                    'post_id' => $result,
                    'edit_url' => admin_url('post.php?post=' . $result . '&action=edit'),
                    'view_url' => get_permalink($result)
                );
                $this->log('info', 'JSON レスポンスデータ: ' . json_encode($response_data));
                
                // 最終的なクリーンアップ
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                wp_send_json_success($response_data);
            }
        } catch (Exception $e) {
            // バッファをクリア
            while (ob_get_level()) {
                ob_end_clean();
            }
            $this->log('error', '手動投稿例外: ' . $e->getMessage());
            
            // 最終的なクリーンアップ
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            wp_send_json_error('処理中にエラーが発生しました: ' . $e->getMessage());
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
     * デフォルトプロンプトを取得するAJAXハンドラー
     */
    public function get_default_prompt() {
        if (!wp_verify_nonce($_POST['nonce'], 'ai_news_autoposter_nonce')) {
            wp_die('Security check failed');
        }
        
        try {
            // プレースホルダー形式のデフォルトプロンプトを生成
            $default_prompt = $this->build_gemini_simple_prompt_template();
            
            wp_send_json_success(array(
                'prompt' => $default_prompt,
                'length' => mb_strlen($default_prompt)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('デフォルトプロンプトの取得に失敗しました: ' . $e->getMessage());
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
        
        // モデルに応じてAPIキーをチェック
        $model = $settings['claude_model'] ?? 'claude-3-5-haiku-20241022';
        $is_gemini = strpos($model, 'gemini') === 0;
        
        if ($is_gemini) {
            $api_key = $settings['gemini_api_key'] ?? '';
            if (empty($api_key)) {
                return new WP_Error('no_api_key', 'Gemini API キーが設定されていません。');
            }
        } else {
            $api_key = $settings['claude_api_key'] ?? '';
            if (empty($api_key)) {
                return new WP_Error('no_api_key', 'Claude API キーが設定されていません。');
            }
        }
        
        // まず最新ニュースを検索
        $this->log('info', '最新ニュース検索を開始します...');
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース';
        $news_data = $this->search_latest_news($search_keywords, 5);
        
        // AI APIを呼び出し
        $api_name = $is_gemini ? 'Gemini' : 'Claude';
        
        if (!empty($news_data)) {
            $this->log('info', count($news_data) . '件のニュースを取得しました。' . $api_name . ' AIで記事を生成します');
        } else {
            $this->log('warning', 'ニュース検索結果が空です。' . $api_name . ' AIの直接検索にフォールバックします');
        }
        
        if ($is_gemini) {
            $this->log('info', 'Gemini APIを呼び出します...');
            
            // Gemini 2.0/2.5でGoogle Search Groundingを使用（シンプル1段階プロセス）
            if ($model === 'gemini-2.5-flash' || $model === 'gemini-2.0-flash-exp') {
                $this->log('info', $model . ' - Google Search Groundingでシンプル1段階記事生成開始');
                $gemini_prompt = $this->build_gemini_simple_prompt($settings);
                $ai_response = $this->call_gemini_api($gemini_prompt, $settings['gemini_api_key'] ?? '', $model);
            } else {
                // 他のGeminiモデルはRSSベース
                if (!empty($news_data)) {
                    $gemini_prompt = $this->build_news_based_prompt($settings, $news_data, 'gemini');
                } else {
                    $gemini_prompt = $this->build_gemini_news_search_prompt($settings);
                }
                $ai_response = $this->call_gemini_api($gemini_prompt, $settings['gemini_api_key'] ?? '', $model);
            }
        } else {
            $this->log('info', 'Claude APIを呼び出します...');
            if (!empty($news_data)) {
                // ニュースデータを基にプロンプトを構築
                $prompt = $this->build_news_based_prompt($settings, $news_data);
                // Claude API用にもgrounding_sourcesを準備
                $grounding_sources = array();
                foreach ($news_data as $news) {
                    $grounding_sources[] = array(
                        'title' => $news['title'] ?? 'ニュース記事',
                        'url' => $news['url'] ?? '#'
                    );
                }
                $this->log('info', 'Claude API用に' . count($grounding_sources) . '件のニュースソースを準備');
            } else {
                // 直接ニュース検索と記事生成を依頼
                $prompt = $this->build_direct_article_prompt($settings);
                $grounding_sources = array();
            }
            $ai_response = $this->call_claude_api($prompt, $api_key, $settings);
            
            // Claude APIレスポンスにもgrounding_sourcesを追加
            if (!is_wp_error($ai_response) && !empty($grounding_sources)) {
                if (is_array($ai_response)) {
                    $ai_response['grounding_sources'] = $grounding_sources;
                } else {
                    $ai_response = array(
                        'text' => $ai_response,
                        'grounding_sources' => $grounding_sources
                    );
                }
            }
        }
        
        if (is_wp_error($ai_response)) {
            $this->log('error', $api_name . ' API呼び出しに失敗: ' . $ai_response->get_error_message());
            
            // Gemini API失敗時のClaude APIフォールバック
            if ($is_gemini && !empty($settings['claude_api_key'])) {
                $this->log('warning', 'Gemini API失敗、Claude APIにフォールバック中...');
                
                if (!empty($news_data)) {
                    $claude_prompt = $this->build_news_based_prompt($settings, $news_data);
                } else {
                    $claude_prompt = $this->build_direct_article_prompt($settings);
                }
                
                $ai_response = $this->call_claude_api($claude_prompt, $settings['claude_api_key'], $settings);
                
                if (is_wp_error($ai_response)) {
                    $this->log('error', 'Claude APIフォールバックも失敗: ' . $ai_response->get_error_message());
                    return $ai_response;
                } else {
                    $this->log('info', 'Claude APIフォールバック成功');
                    $api_name = 'Claude (フォールバック)';
                    $is_gemini = false; // Claude処理に切り替え
                }
            } else {
                return $ai_response;
            }
        }
        
        $this->log('info', $api_name . ' APIから正常にレスポンスを受信しました');
        
        // AIレスポンスを解析
        $this->log('info', 'AIレスポンスを解析中...');
        
        // Grounding Sourcesを初期化
        $grounding_sources = array();
        
        // Gemini APIからの構造化レスポンス処理
        if ($is_gemini && is_array($ai_response) && isset($ai_response['text'])) {
            $this->log('info', 'Gemini API構造化レスポンスを処理中...');
            $grounding_sources = $ai_response['grounding_sources'] ?? array();
            $article_data = $this->parse_ai_response($ai_response['text']);
            
            // プロンプト結果に任せる方式: グラウンディングソース追加処理も無効化
            // Geminiの生回答をそのまま使用（メタデータとしてのみ保存）
            if (!empty($grounding_sources)) {
                $this->log('info', '📝 グラウンディングソース' . count($grounding_sources) . '件をメタデータとしてのみ保存（記事内容は変更せず）');
                // メタデータとして保存するが、記事内容は変更しない
            }
        } else {
            // Claude APIまたは古いGemini形式の場合
            $article_data = $this->parse_ai_response($ai_response);
        }
        
        $this->log('info', '記事データ解析完了。タイトル: ' . $article_data['title']);
        
        // 最終的なコンテンツ処理（文字数制限のみ、参考情報源・免責事項は後で追加）
        $this->log('info', '最終コンテンツ処理を実行中...');
        $article_data['content'] = $this->post_process_content($article_data['content'], $settings, false); // 免責事項追加を無効化
        
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
        
        // タイトルクリーニング（「タイトル: 」プレフィックスを除去）
        $clean_title = $article_data['title'];
        if (strpos($clean_title, 'タイトル: ') === 0) {
            $clean_title = mb_substr($clean_title, 5); // 「タイトル: 」をマルチバイト対応で除去
            $this->log('info', 'タイトルから「タイトル: 」プレフィックスを除去: ' . mb_substr($clean_title, 0, 50));
        }
        
        // タイトルの基本的なクリーニングのみ実行（文字エンコーディング変換は削除）
        $clean_title = trim($clean_title);
        
        // 空のタイトルの場合はデフォルトタイトルを設定
        if (empty($clean_title)) {
            $clean_title = 'AI生成記事 - ' . date('Y年m月d日');
            $this->log('warning', 'タイトルが空のためデフォルトタイトルを設定: ' . $clean_title);
        }
        
        // コンテンツの最小限検証のみ実行（Gemini APIからは正しいUTF-8で受信）
        $clean_content = $article_data['content'];
        
        // 明らかな文字化けパターンのみ検出してログ出力（修正はしない）
        if (preg_match('/[^\x00-\x7F\x80-\xFF]{3,}/', $clean_content)) {
            $this->log('warning', '特殊文字パターンを検出しましたが、そのまま使用します');
        }
        
        // データベース互換性のための追加クリーニング
        $this->log('info', 'clean_title_for_database実行前のタイトル: ' . mb_substr($clean_title, 0, 100));
        $clean_title = $this->clean_title_for_database($clean_title);
        $this->log('info', 'clean_title_for_database実行後のタイトル: ' . mb_substr($clean_title, 0, 100));
        $clean_content = $this->clean_text_for_database($clean_content);
        
        // 基本的な投稿データを作成（メタデータは後で追加）
        $post_data = array(
            'post_title' => $clean_title,
            'post_content' => $clean_content,
            'post_status' => $is_test ? 'draft' : ($settings['post_status'] ?? 'publish'),
            'post_category' => array($category),
            'post_type' => 'post',
            'post_author' => get_current_user_id() ?: 1,
            'post_excerpt' => $this->generate_excerpt($clean_content), // 記事内容から20文字程度の抜粋を生成
            'comment_status' => 'open', // コメントステータスを明示的に設定
            'ping_status' => 'open', // ピングステータスを明示的に設定
            'post_date' => current_time('mysql'), // 現在時刻を明示的に設定
            'post_date_gmt' => current_time('mysql', 1) // GMT時刻を明示的に設定
        );

        // メタデータは別途用意（問題がある場合は無効化できるように）
        $meta_data = array(
            '_ai_generated' => true,
            '_ai_post_type' => $is_test ? 'test' : $post_type
        );
        
        // SEO関連のメタデータは後で安全に追加
        if (!empty($settings['seo_focus_keyword'])) {
            $meta_data['_seo_focus_keyword'] = $settings['seo_focus_keyword'];
        }
        
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
        
        // 文字数制限を無効化（プロンプト結果に任せる）
        // Geminiの判断に任せて自然な長さの記事を生成
        $this->log('info', '📝 プロンプト結果に任せる方式: 文字数制限を無効化');
        
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
        // 安全なテストのため、コンテンツを大幅に短縮
        $safe_content = mb_substr($post_data['post_content'], 0, 500) . "\n\n[テスト用短縮版]";
        $test_post_data_real = array(
            'post_title' => $post_data['post_title'],
            'post_content' => $safe_content,
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
        
        // 緊急短縮も無効化（プロンプト結果に任せる）
        $final_content_length = mb_strlen($post_data['post_content']);
        $this->log('info', '📝 プロンプト結果に任せる方式: 緊急短縮も無効化。最終コンテンツ長: ' . $final_content_length . '文字');
        
        // wp_insert_post実行直前の最終ログ
        $memory_usage = memory_get_usage(true) / 1024 / 1024; // MB
        $memory_peak = memory_get_peak_usage(true) / 1024 / 1024; // MB
        $this->log('info', 'wp_insert_postを実行します。メモリ使用量: ' . round($memory_usage, 2) . 'MB, ピーク: ' . round($memory_peak, 2) . 'MB');
        $this->log('info', 'データサイズ: ' . strlen(serialize($post_data)) . ' bytes');
        $this->log('info', 'コンテンツサイズ: ' . mb_strlen($post_data['post_content']) . '文字');
        
        // wp_insert_post実行直前のデータ内容をログ出力（文字化けデバッグ用）
        $this->log('info', 'wp_insert_post実行直前のタイトル: "' . mb_substr($post_data['post_title'], 0, 50) . '"');
        $this->log('info', 'wp_insert_post実行直前のコンテンツ先頭100文字: "' . mb_substr($post_data['post_content'], 0, 100) . '"');
        
        // 抜粋の自動生成
        $settings = get_option('ai_news_autoposter_settings', array());
        if ($settings['enable_excerpt'] ?? true) {
            $post_data['post_excerpt'] = $this->generate_post_excerpt($post_data['post_title']);
            $this->log('info', '抜粋を自動生成しました: "' . $post_data['post_excerpt'] . '"');
        } else {
            $post_data['post_excerpt'] = '';
            $this->log('info', '抜粋生成は無効に設定されています');
        }
        
        // 最終的な文字クリーニング（データベースエラー防止）
        $this->log('info', '最終クリーニング前のタイトル: ' . mb_substr($post_data['post_title'], 0, 100));
        $post_data['post_title'] = $this->clean_title_for_database($post_data['post_title']);
        $this->log('info', '最終クリーニング後のタイトル: ' . mb_substr($post_data['post_title'], 0, 100));
        $content_before_clean = $post_data['post_content'];
        $post_data['post_content'] = $this->clean_text_for_database($post_data['post_content']);
        
        // 抜粋も同様にクリーニング
        if (isset($post_data['post_excerpt'])) {
            $post_data['post_excerpt'] = $this->clean_text_for_database($post_data['post_excerpt']);
        }
        
        // 免責事項チェック
        $has_disclaimer_before = strpos($content_before_clean, '注：この記事は') !== false;
        $has_disclaimer_after = strpos($post_data['post_content'], '注：この記事は') !== false;
        $this->log('info', 'クリーニング前の免責事項: ' . ($has_disclaimer_before ? '有り' : '無し'));
        $this->log('info', 'クリーニング後の免責事項: ' . ($has_disclaimer_after ? '有り' : '無し'));
        
        // プロンプト結果に任せる方式: 10000文字制限も無効化
        $content_length = mb_strlen($post_data['post_content']);
        $this->log('info', '📝 プロンプト結果に任せる方式: 文字数制限なし。コンテンツ長: ' . $content_length . '文字');
        
        // PHPエラーをキャッチするためのoutput buffering開始
        ob_start();
        
        // MySQLエラーを直接監視
        global $wpdb;
        $wpdb->flush();
        $wpdb->last_error = '';
        
        try {
            $this->log('info', 'wp_insert_post直前のMySQL接続確認: ' . ($wpdb->check_connection() ? '正常' : '異常'));
            
            // データベースの文字セットを確認
            $charset = $wpdb->get_var("SELECT @@character_set_database");
            $collation = $wpdb->get_var("SELECT @@collation_database");
            $this->log('info', 'データベース文字セット: ' . $charset . ', 照合順序: ' . $collation);
            
            $post_id = wp_insert_post($post_data, true); // true: より詳細なエラー情報
            
            // MySQL エラーを即座にチェック
            if ($wpdb->last_error) {
                $this->log('error', 'MySQL直接エラー: ' . $wpdb->last_error);
                $this->log('error', 'データベース文字セット問題の可能性があります。charset=' . $charset);
            }
            
            $this->log('info', 'wp_insert_post実行完了。結果: ' . (is_wp_error($post_id) ? 'WP_Error' : (is_numeric($post_id) ? 'ID=' . $post_id : '0')));
        } catch (Exception $e) {
            $this->log('error', 'wp_insert_postでPHP例外が発生: ' . $e->getMessage());
            if ($wpdb->last_error) {
                $this->log('error', '例外時のMySQLエラー: ' . $wpdb->last_error);
            }
            $post_id = new WP_Error('php_exception', $e->getMessage());
        } catch (Error $e) {
            $this->log('error', 'wp_insert_postでPHPエラーが発生: ' . $e->getMessage());
            if ($wpdb->last_error) {
                $this->log('error', 'PHPエラー時のMySQLエラー: ' . $wpdb->last_error);
            }
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
        
        // 後処理は最小限に（免責事項はGrounding Sourcesと一緒に追加されるため、ここでは無効化）
        $this->log('info', '📝 プロンプト結果に任せる方式: 最小限の後処理のみ実行');
        
        // メタデータを個別に追加（投稿作成後に安全に処理）
        foreach ($meta_data as $meta_key => $meta_value) {
            if (!empty($meta_value)) {
                $meta_result = update_post_meta($post_id, $meta_key, $meta_value);
                $this->log('info', 'メタデータ追加: ' . $meta_key . ' = ' . ($meta_result ? '成功' : '失敗'));
            }
        }
        
        // 文字化けデバッグ：データベースから実際に保存された内容を確認
        $saved_post = get_post($post_id);
        if ($saved_post) {
            $this->log('info', '保存後のタイトル: "' . mb_substr($saved_post->post_title, 0, 50) . '"');
            $this->log('info', '保存後のコンテンツ先頭100文字: "' . mb_substr($saved_post->post_content, 0, 100) . '"');
            
            // 免責事項が保存されているかチェック
            $saved_has_disclaimer = strpos($saved_post->post_content, '注：この記事は') !== false;
            $this->log('info', '保存後の免責事項: ' . ($saved_has_disclaimer ? '有り' : '無し'));
            if (!$saved_has_disclaimer) {
                $this->log('warning', '免責事項が保存されていません。コンテンツ末尾100文字: "' . mb_substr($saved_post->post_content, -100) . '"');
            }
        }
        
        // タグを追加
        if ($settings['enable_tags'] && !empty($article_data['tags'])) {
            wp_set_post_tags($post_id, $article_data['tags']);
        }
        
        // Grounding Sourcesの追加と免責事項（常に免責事項は追加）
        if (isset($grounding_sources) && !empty($grounding_sources)) {
            $this->log('info', 'Grounding Sources セクションを記事末尾に追加します: ' . count($grounding_sources) . '件');
            $this->add_grounding_sources_list($post_id, $grounding_sources, $settings);
        } else {
            // Grounding Sourcesがない場合でも免責事項のみ追加
            $this->log('info', 'Grounding Sourcesなし。免責事項のみ追加します');
            $this->add_minimal_disclaimer($post_id, $settings);
        }
        
        // アイキャッチ画像を生成（画像生成方式が「なし」以外の場合）
        if (($settings['image_generation_type'] ?? 'placeholder') !== 'none') {
            $this->log('info', 'アイキャッチ画像生成を開始します（方式: ' . ($settings['image_generation_type'] ?? 'placeholder') . '）');
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
        $article_count = $settings['article_count'] ?? 3;
        $impact_length = $settings['impact_analysis_length'] ?? 500;
        
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
        $prompt .= "【{$language_text}】のニュースから、【{$search_keywords}】に関する{$current_year}年の最新ニュース（特に直近数ヶ月の新しい情報）を送ってください。{$article_count}本ぐらいが理想です。\n";
        $prompt .= "ニュースの背景や文脈を簡単にまとめ、なぜ今、これが起こっているのか、という背景情報を踏まえて、今後どのような影響をあたえるのか、推察もしてください。\n";
        $prompt .= "\n【重要な注意事項】\n";
        $prompt .= "- 記事本文中にはURLアドレスを一切含めないでください\n";
        $prompt .= "- 「URL:」「URL」などのURLラベルも記載しないでください\n";
        $prompt .= "- ニュースタイトルのみを記載し、そのURLアドレスは省略してください\n";
        $prompt .= "- 参考情報は記事の末尾にGoogle Search Groundingが自動で追加するので不要です\n";
        $prompt .= "全部で【{$word_count}文字以内】に必ずまとめてください。この文字数制限は厳守してください。充実した内容で。\n";
        if ($writing_style !== '標準') {
            $prompt .= "文体は{$writing_style}風でお願いします。\n";
        }
        $prompt .= "\n";
        
        $prompt .= "構成は以下のようなイメージです。適切なHTMLタグを使用してください\n";
        $prompt .= "---------------------------------\n";
        $prompt .= "記事タイトル\n";
        $prompt .= "リード文\n";
        $prompt .= "各ニュース記事ごとに：\n";
        $prompt .= "<h2>ニュースタイトル</h2>\n";
        $prompt .= "<h3>概要と要約</h3>\n";
        $prompt .= "本文（URLアドレスは一切含めない）\n";
        $prompt .= "<h3>背景・文脈</h3>\n";
        $prompt .= "本文\n";
        $prompt .= "<h3>今後の影響</h3>\n";
        $prompt .= "本文（{$impact_length}文字程度の考察）\n";
        $prompt .= "---------------------------------\n\n";
        
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
            // Gemini 2.5 Google Search 特化プロンプト（日本語で明示的に検索指示）
            $prompt = "現在のAI関連ニュースを日本語で検索してください。{$current_date}時点での最新情報を探してください。\n\n";
            $prompt .= "検索キーワード: {$search_keywords} {$current_year}年 最新\n\n";
            $prompt .= "Google検索を使用して以下を実行してください：\n";
            $prompt .= "1. 「{$search_keywords} ニュース {$current_year}年」で検索\n";
            $prompt .= "2. 「AI 人工知能 最新 {$current_year}」で追加検索\n";
            $prompt .= "3. 実際のニュースサイトのURLを取得\n";
            $prompt .= "4. 検索結果を基に日本語記事を作成\n\n";
            $prompt .= "記事要件:\n";
            $prompt .= "- 全て日本語で執筆\n";
            $prompt .= "- {$word_count}文字程度\n";
            $prompt .= "- 見出し構造を含む\n";
            $prompt .= "- 実際のニュースソースを参照\n\n";
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
        // 入力検証
        if (empty($custom_prompt) || !is_string($custom_prompt)) {
            $this->log('error', 'カスタムプロンプトが無効です');
            return '';
        }
        
        $this->log('info', 'カスタムプロンプト構築開始: 入力長=' . mb_strlen($custom_prompt) . '文字');
        
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース';
        $selected_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $word_count = $settings['article_word_count'] ?? 500;
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        $article_count = $settings['article_count'] ?? 3;
        $impact_length = $settings['impact_analysis_length'] ?? 500;
        
        $this->log('info', 'プレースホルダー値: キーワード=' . $search_keywords . ', 言語=' . implode(',', $selected_languages) . ', 文字数=' . $word_count . ', 文体=' . $writing_style . ', 記事数=' . $article_count . ', 影響分析=' . $impact_length . '文字');
        
        // 言語指定を作成
        $language_names = array_map(array($this, 'get_language_name'), $selected_languages);
        $language_text = implode('と', $language_names);
        
        $this->log('info', '言語変換結果: ' . $language_text);
        
        // プレースホルダーを置換
        $prompt = str_replace('{言語}', $language_text, $custom_prompt);
        $prompt = str_replace('{キーワード}', $search_keywords, $prompt);
        $prompt = str_replace('{文字数}', $word_count, $prompt);
        $prompt = str_replace('{文体}', $writing_style, $prompt);
        $prompt = str_replace('{記事数}', $article_count, $prompt);
        $prompt = str_replace('{影響分析文字数}', $impact_length, $prompt);
        
        $this->log('info', 'プレースホルダー置換後: 出力長=' . mb_strlen($prompt) . '文字');
        
        // 最終検証
        if (empty(trim($prompt))) {
            $this->log('error', 'プロンプト処理後に空になりました');
            return '';
        }
        
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
        
        // プロンプト内容をログに出力（デバッグ用）
        $this->log('info', '=== Claude APIプロンプト内容 ===');
        $this->log('info', $prompt);
        $this->log('info', '=== プロンプト終了 ===');
        
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        );
        
        // Claude APIでは常にClaudeモデルを使用（Geminiモデルが設定されていても）
        $claude_model = $settings['claude_model'] ?? 'claude-3-5-haiku-20241022';
        if (strpos($claude_model, 'gemini') === 0) {
            // Geminiモデルが設定されている場合はデフォルトのClaudeモデルを使用
            $claude_model = 'claude-3-5-haiku-20241022';
            $this->log('info', 'Claude API: Geminiモデルが設定されているため、デフォルトClaudeモデル（' . $claude_model . '）を使用');
        }
        
        $body = array(
            'model' => $claude_model,
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
        // プロンプト結果に任せる方式: 生回答を最小限の処理で使用
        $this->log('info', '📝 プロンプト結果に任せる方式: 生回答を最小限処理で使用');
        
        $lines = explode("\n", trim($response));
        $content = trim($response);
        
        // より良いタイトル抽出を実装
        $title = $this->extract_better_title($response, $lines);
        
        // 抽出したタイトルがコンテンツの最初にある場合は除去
        if (!empty($title) && $title !== '最新ニュース: ' . date('Y年m月d日')) {
            // タイトル行を除いたコンテンツを作成
            $title_pattern = preg_quote($title, '/');
            $content = preg_replace('/^' . $title_pattern . '\s*\n?/u', '', $content, 1);
            $content = trim($content);
            $this->log('info', 'タイトル抽出成功: ' . $title);
        }
        
        // コンテンツが空になった場合は元の回答を使用
        if (empty($content)) {
            $content = trim($response);
        }
        
        // 記事本文中の非クリッカブルURLを削除（末尾に完璧な参考情報源があるため）
        $content = $this->remove_plain_urls_from_content($content);
        
        return array(
            'title' => $title,
            'content' => $content, // Geminiの判断に任せて生回答を使用（タイトルとURLを除去）
            'tags' => array('アウトドア', 'ニュース', 'AI生成')
        );
    }
    
    /**
     * 記事本文中の非クリッカブルURLを削除
     */
    private function remove_plain_urls_from_content($content) {
        $this->log('info', 'URL削除処理を開始します');
        
        // まず、<a>タグ内のURLを一時的に保護
        $protected_urls = array();
        $protection_counter = 0;
        
        $content = preg_replace_callback('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>.*?<\/a>/i', function($matches) use (&$protected_urls, &$protection_counter) {
            $placeholder = "{{PROTECTED_URL_" . $protection_counter . "}}";
            $protected_urls[$placeholder] = $matches[0];
            $protection_counter++;
            return $placeholder;
        }, $content);
        
        // 1. "URL:" のバリエーションを削除
        // - "URL: https://..." の形式
        $content = preg_replace('/^\s*URL:\s*https?:\/\/[^\s]+\s*$/m', '', $content);
        
        // - すべてのURL関連パターンを包括的に削除
        // <li>タグ内のURL関連パターン（あらゆるバリエーション対応）
        $content = preg_replace('/<li[^>]*>.*?URL.*?<\/li>/i', '', $content);
        
        // 単体のURL関連要素
        $content = preg_replace('/^\s*-\s*URL.*$/m', '', $content);
        $content = preg_replace('/^\s*\*\s*URL.*$/m', '', $content);
        $content = preg_replace('/^\s*URL.*$/m', '', $content);
        $content = preg_replace('/<strong[^>]*>.*?URL.*?<\/strong>/i', '', $content);
        
        // 2. プレーンテキストのHTTP/HTTPSフルURLを削除
        $content = preg_replace('/https?:\/\/[^\s<>"\')，]+/i', '', $content);
        
        // 3. 保護されたURLを復元
        foreach ($protected_urls as $placeholder => $original) {
            $content = str_replace($placeholder, $original, $content);
        }
        
        // 4. 空のリストアイテムや空行を整理
        $content = preg_replace('/<li>\s*<\/li>/i', '', $content);
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        $content = trim($content);
        
        $this->log('info', 'URL削除処理が完了しました');
        return $content;
    }
    
    /**
     * コンテンツからより良いタイトルを抽出
     */
    private function extract_better_title($response, $lines) {
        $default_title = '最新ニュース: ' . date('Y年m月d日');
        
        // 1. 最初の行をチェック
        if (!empty($lines)) {
            $first_line = trim($lines[0]);
            $clean_first_line = strip_tags($first_line);
            
            // 最初の行が適切なタイトルかチェック
            if (mb_strlen($clean_first_line) >= 8 && mb_strlen($clean_first_line) <= 50) {
                // 不適切なパターンを除外
                if (!preg_match('/^(アウトドアギア|各記事|以下|まず|その後|1本目|2本目|3本目|http|www|タイトル:|URL:|概要)/u', $clean_first_line)) {
                    return $clean_first_line;
                }
            }
        }
        
        // 2. "タイトル:" パターンを探す
        if (preg_match('/^タイトル:\s*(.+)$/m', $response, $matches)) {
            $title_candidate = trim($matches[1]);
            if (mb_strlen($title_candidate) >= 8 && mb_strlen($title_candidate) <= 50) {
                return $title_candidate;
            }
        }
        
        // 3. 1本目の記事のタイトルを探す
        if (preg_match('/1本目の記事.*?タイトル:\s*(.+?)$/m', $response, $matches)) {
            $title_candidate = trim($matches[1]);
            if (mb_strlen($title_candidate) >= 8 && mb_strlen($title_candidate) <= 50) {
                return $title_candidate . ' 他2本';
            }
        }
        
        // 4. 検索キーワードから生成
        $settings = get_option('ai_news_autoposter_settings', array());
        $search_keywords = $settings['search_keywords'] ?? 'アウトドア';
        $keywords = explode(',', $search_keywords);
        $first_keyword = trim($keywords[0]);
        
        return $first_keyword . '最新ニュース' . date('m月d日');
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
     * 記事内容から20文字程度の抜粋を生成
     */
    private function generate_excerpt($content) {
        // HTMLタグを除去してプレーンテキストに
        $text = strip_tags($content);
        
        // 改行や余分な空白を除去
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // 参考情報源などの不要な部分を除去
        $text = preg_replace('/^(参考|出典|ソース|URL|https?:\/\/)[^\n]*/m', '', $text);
        $text = trim($text);
        
        // 20文字程度で切り詰め
        if (mb_strlen($text) <= 20) {
            return $text;
        }
        
        // 句読点で自然に切る
        $punctuation = array('。', '！', '？', '、');
        for ($i = 15; $i <= 25; $i++) {
            $char = mb_substr($text, $i, 1);
            if (in_array($char, $punctuation)) {
                return mb_substr($text, 0, $i + 1);
            }
        }
        
        // 句読点がない場合は20文字で切り詰め
        return mb_substr($text, 0, 20);
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
        $this->log('debug', 'parse_simple_response: 入力レスポンス長=' . mb_strlen($response));
        
        // 新しい出力形式（タイトル: [タイトル]）に対応
        if (preg_match('/^タイトル:\s*(.+?)$/m', $response, $matches)) {
            $title = trim($matches[1]);
            $this->log('debug', 'parse_simple_response: タイトル抽出成功=' . $title);
            
            // 構造化されたコンテンツを解析
            $content = $this->parse_structured_content($response);
            
            $this->log('debug', 'parse_simple_response: 構造化解析後コンテンツ長=' . mb_strlen($content));
            
            // コンテンツが空の場合、別の方法で抽出
            if (empty($content) || mb_strlen($content) < 50) {
                $this->log('debug', 'parse_simple_response: コンテンツが短い、全体から再抽出');
                // タイトル行を除去して残りを全て取得
                $content = preg_replace('/^タイトル:\s*.+$/m', '', $response, 1);
                $content = trim($content);
            }
            
            // コンテンツのクリーンアップ
            $content = $this->clean_content($content);
            
            $this->log('debug', 'parse_simple_response: 最終コンテンツ長=' . mb_strlen($content));
            $this->log('debug', 'parse_simple_response: コンテンツプレビュー=' . mb_substr($content, 0, 100) . '...');
            
            // タイトルからタグを生成
            $tags = array();
            if (stripos($title . $content, 'AI') !== false) $tags[] = 'AI';
            if (stripos($title . $content, '人工知能') !== false) $tags[] = '人工知能';
            if (stripos($title . $content, 'ChatGPT') !== false) $tags[] = 'ChatGPT';
            if (stripos($title . $content, 'OpenAI') !== false) $tags[] = 'OpenAI';
            if (stripos($title . $content, 'Google') !== false) $tags[] = 'Google';
            if (stripos($title . $content, 'アウトドア') !== false) $tags[] = 'アウトドア';
            if (stripos($title . $content, 'キャンプ') !== false) $tags[] = 'キャンプ';
            if (stripos($title . $content, 'ハイキング') !== false) $tags[] = 'ハイキング';
            if (stripos($title . $content, 'クライミング') !== false) $tags[] = 'クライミング';
            if (stripos($title . $content, 'ギア') !== false) $tags[] = 'ギア';
            
            return array(
                'title' => $title,
                'content' => trim($content),
                'tags' => array_filter($tags)
            );
        }
        
        // 旧形式のフォールバック処理
        $this->log('debug', 'parse_simple_response: 旧形式で処理');
        
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
        if (stripos($title . $content, 'アウトドア') !== false) $tags[] = 'アウトドア';
        if (stripos($title . $content, 'キャンプ') !== false) $tags[] = 'キャンプ';
        if (stripos($title . $content, 'ハイキング') !== false) $tags[] = 'ハイキング';
        if (stripos($title . $content, 'クライミング') !== false) $tags[] = 'クライミング';
        if (stripos($title . $content, 'ギア') !== false) $tags[] = 'ギア';
        
        // タイトルが空の場合はデフォルトを設定
        $title = $title ?: '最新AIニュース: ' . date('Y年m月d日');
        
        return array(
            'title' => $title,
            'content' => trim($content),
            'tags' => array_filter($tags)
        );
    }
    
    /**
     * 構造化されたコンテンツを解析
     */
    private function parse_structured_content($response) {
        $this->log('debug', 'parse_structured_content: 構造化コンテンツの解析開始');
        
        // タイトル行を除去してコンテンツ部分を取得
        $content = preg_replace('/^タイトル:\s*.+$/m', '', $response, 1);
        $content = trim($content);
        
        // 各セクションを抽出
        $sections = array();
        
        // ニュース一覧部分は記事に含めない（削除）
        
        // 新しいシンプル形式に対応（Markdownヘッダーなし）
        // タイトル行の後の全コンテンツを取得
        if (preg_match('/^タイトル:\s*.+?\n\n(.+)/s', $response, $matches)) {
            $main_content = trim($matches[1]);
            $this->log('debug', 'parse_structured_content: シンプル形式のコンテンツを抽出: ' . mb_strlen($main_content) . '文字');
            return $main_content;
        }
        
        // フォールバック：元のコンテンツを使用
        $this->log('debug', 'parse_structured_content: パターンマッチ失敗、元のコンテンツを使用');
        return $content;
    }
    
    /**
     * タイトルをクリーンアップ
     */
    private function clean_title($title) {
        // 「タイトル: 」の重複を除去
        $title = preg_replace('/^タイトル:\s*/', '', $title);
        
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
        // 文字化けチェック
        if (!mb_check_encoding($content, 'UTF-8')) {
            $this->log('warning', 'clean_content: 文字化けが検出されました');
            // 基本的なクリーニングのみ
            return str_replace(array("\r\n", "\r"), "\n", trim($content));
        }
        
        // Markdownヘッダーを完全に削除
        $content = preg_replace('/^#{1,6}\s+.*$/m', '', $content);
        // **太字**記法も削除
        $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
        // 日付ヘッダー（##2025年7月7日など）も削除
        $content = preg_replace('/^#{1,6}\s*\d{4}年\d{1,2}月\d{1,2}日.*$/m', '', $content);
        
        // 構造化された見出しラベルを除去
        $content = preg_replace('/^簡潔なリード文[:：]\s*/m', '', $content);
        $content = preg_replace('/^背景[・･]文脈[:：]\s*/m', '', $content);
        $content = preg_replace('/^影響[・･]考察[:：]\s*/m', '', $content);
        $content = preg_replace('/^推察[・･]今後の展望[:：]\s*/m', '', $content);
        $content = preg_replace('/^まとめ[:：]\s*/m', '', $content);
        
        // 余分な空行を除去（UTF-8フラグ追加）
        $content = preg_replace('/\n\s*\n\s*\n/u', "\n\n", $content);
        
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
        // 文字化けチェック
        if (!mb_check_encoding($content, 'UTF-8')) {
            $this->log('warning', 'fix_incomplete_ending: 文字化けが検出されたため処理をスキップ');
            return $content;
        }
        
        // 最後の文字を確認
        $last_char = mb_substr($content, -1);
        
        // 不完全な終わり方のパターンを検出（UTF-8フラグ追加）
        $incomplete_patterns = array(
            // 助詞で終わっている場合（「また、大規模」など）
            '/[、が、は、を、に、で、の、と、も、から、まで、より、について、に関して、として]$/u',
            // 「〜など、」「〜等、」のパターン
            '/(?:など|等)[、,]$/u',
            // 数字や英字で終わっている場合
            '/[0-9a-zA-Z]$/u',
            // カンマで終わっている場合
            '/[、,]$/u',
            // 接続詞で終わっている場合
            '/(?:しかし|また|さらに|一方|このため|その結果|つまり|なお|ちなみに|ただし)$/u',
            // 動詞の連用形で終わっている場合
            '/(?:開始|実施|展開|推進|発表|導入|提供|支援|対応|実現)(?:する|し|した)(?:など|等)[、,]?$/u',
            // 「〜するなど、」のような不完全な列挙
            '/[ぁ-ん](?:する|した|している)(?:など|等)[、,]?$/u',
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
            // 最後の完全な文を見つける（UTF-8フラグ追加）
            $sentences = preg_split('/[。！？]/u', $content);
            
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
     * グラウンディングソースを記事末尾に追加（本文は保持）
     */
    private function append_grounding_sources($article_data, $grounding_sources, $keyword = '') {
        if (empty($grounding_sources)) {
            return $article_data;
        }
        
        $this->log('info', 'グラウンディングソースを記事末尾に追加開始');
        
        // 記事本文は完全に保持
        $content = $article_data['content'];
        
        // 参考情報源リストを生成
        $sources_list = "\n\n**参考情報源：**\n\n";
        $valid_sources = 0;
        
        // 最大10件に制限してパフォーマンス向上
        $limited_sources = array_slice($grounding_sources, 0, 10);
        
        foreach ($limited_sources as $index => $source) {
            $raw_title = $source['title'] ?? '';
            $url = $source['url'] ?? '';
            
            if (!empty($raw_title) && !empty($url)) {
                // タイトルクリーンアップ（外部アクセスなし）
                $title = strip_tags($raw_title);
                $title = preg_replace('/\s+/', ' ', trim($title));
                $title = mb_substr($title, 0, 80); // 80文字に制限
                
                // URLの基本クリーンアップ（外部アクセスなし）
                $clean_url = $url;
                
                // Googleリダイレクトの場合のみ簡易処理
                if (strpos($url, 'vertexaisearch.cloud.google.com') !== false) {
                    // ドメイン名をタイトルに追加
                    if (preg_match('/grounding-api-redirect\/[^\/]+$/', $url)) {
                        $domain_info = ' (Googleソース)';
                    } else {
                        $domain_info = '';
                    }
                    $title .= $domain_info;
                }
                
                if (!empty($title) && !empty($clean_url)) {
                    $sources_list .= "- [" . $title . "](" . $clean_url . ")\n";
                    $valid_sources++;
                }
            }
        }
        
        // 参考情報源が有効な場合のみ追加
        if ($valid_sources > 0) {
            $content .= $sources_list;
            $this->log('info', $valid_sources . '件の参考情報源を記事末尾に追加しました');
        } else {
            $this->log('warning', '有効な参考情報源がありませんでした');
        }
        
        $article_data['content'] = $content;
        return $article_data;
    }
    
    /**
     * グラウンディングソースを記事に統合
     */
    private function integrate_grounding_sources($article_data, $grounding_sources, $keyword = '') {
        if (empty($grounding_sources)) {
            return $article_data;
        }
        
        $this->log('info', 'グラウンディングソースを記事に統合開始');
        
        // 設定から参考情報源セクションタイトルを取得
        $settings = get_option('ai_news_autoposter_settings', array());
        $section_title = $settings['sources_section_title'] ?? '今日の{keyword}関連のニュース';
        
        // {keyword}プレースホルダーを実際のキーワードで置換
        if (!empty($keyword)) {
            $section_title = str_replace('{keyword}', $keyword, $section_title);
        } else {
            // キーワードが空の場合は{keyword}を削除
            $section_title = str_replace('{keyword}', '', $section_title);
            $section_title = preg_replace('/\s+/', ' ', trim($section_title));
        }
        
        // 日付プレースホルダーも置換
        $section_title = $this->replace_date_placeholders($section_title);
        
        // 記事内容の既存参考情報源セクションを完全削除
        $content = $article_data['content'];
        
        // 既存の参考情報源セクションを全て削除（設定可能なタイトルにも対応）
        $content = preg_replace('/(?:##?\s*参考情報源|参考情報源).*$/uis', '', $content);
        // 設定されたセクションタイトルも削除対象に含める
        $section_title_pattern = preg_quote($section_title, '/');
        $content = preg_replace('/##?\s*' . $section_title_pattern . '.*$/uis', '', $content);
        $content = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '', $content); // Markdownリンク削除
        $content = preg_replace('/<a[^>]*>.*?<\/a>/uis', '', $content); // HTMLリンク削除
        $content = trim($content);
        
        $this->log('info', "既存の参考情報源セクションを完全削除");
        
        // 単一の参考情報源セクションを生成
        $sources_section = "\n\n## " . $section_title . "\n\n";
        $valid_sources = 0;
        
        foreach ($grounding_sources as $index => $source) {
            $raw_title = $source['title'] ?? '';
            $url = $source['url'] ?? '';
            
            if (!empty($raw_title) && !empty($url)) {
                // 実際のページタイトル取得を試行
                $title = $this->improve_source_title($raw_title, $url);
                
                // 完全クリーンアップ
                $title = strip_tags($title);
                $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = preg_replace('/[<>]/', '', $title); // < > 完全除去
                $title = preg_replace('/\s+/', ' ', trim($title));
                
                // 実際のURLを取得（Grounding APIリダイレクトを解決）
                $actual_url = $this->get_actual_url_from_grounding($url);
                if (empty($actual_url)) {
                    $actual_url = $url; // フォールバック
                }
                
                // タイトル長制限
                if (mb_strlen($title) > 60) {
                    $title = mb_substr($title, 0, 57) . '...';
                }
                
                // 有効なソースのみ追加
                if (!empty($title) && !preg_match('/^[a-z0-9.-]+\.(com|net|jp|co\.jp|org)$/i', $title)) {
                    $sources_section .= sprintf(
                        "- <a href=\"%s\" target=\"_blank\">%s</a>\n",
                        $actual_url,
                        $title
                    );
                    $valid_sources++;
                }
            }
        }
        
        // 有効なソースがある場合のみ追加（記事の冒頭に配置）
        if ($valid_sources > 0) {
            $content = $sources_section . "\n" . $content;
            $this->log('info', "{$valid_sources}件の有効なソースで「{$section_title}」セクションを記事の冒頭に生成");
        } else {
            $this->log('warning', '有効なソースが見つからず、参考情報源セクションを生成できませんでした');
        }
        
        $article_data['content'] = $content;
        $this->log('info', 'グラウンディングソース統合完了');
        
        return $article_data;
    }
    
    /**
     * Grounding APIリダイレクトから実際のURLを取得
     */
    private function get_actual_url_from_grounding($grounding_url) {
        if (strpos($grounding_url, 'vertexaisearch.cloud.google.com') === false) {
            return $grounding_url;
        }
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $grounding_url);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AI-News-Bot/1.0)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // リダイレクトを手動で処理
            curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && ($http_code == 302 || $http_code == 301)) {
                // Locationヘッダーから実際のURLを抽出
                if (preg_match('/Location:\s*(.+)/i', $response, $matches)) {
                    $actual_url = trim($matches[1]);
                    $this->log('info', "実際のURL取得成功: {$grounding_url} -> {$actual_url}");
                    return $actual_url;
                }
            }
            
        } catch (Exception $e) {
            $this->log('warning', 'URL解決エラー: ' . $e->getMessage());
        }
        
        return $grounding_url; // 失敗時は元のURLを返す
    }
    
    /**
     * ソースタイトルを改善（実際のページタイトルを取得）
     */
    private function improve_source_title($raw_title, $url) {
        // ドメイン名のみの場合は実際のページタイトルを取得
        if (preg_match('/^[a-z0-9.-]+\.(com|net|jp|co\.jp|org)$/i', $raw_title)) {
            $actual_title = $this->fetch_actual_page_title($url);
            if (!empty($actual_title) && $actual_title !== $raw_title) {
                $this->log('info', "実際のページタイトルを取得: {$raw_title} -> {$actual_title}");
                return $actual_title;
            }
            
            // 取得失敗時は主要ドメインマップを使用
            $domain_map = array(
                'bepal.net' => 'BE-PAL（アウトドア情報サイト）',
                'coleman.co.jp' => 'コールマン公式サイト',
                'snowpeak.co.jp' => 'スノーピーク公式サイト',
                'logos.ne.jp' => 'ロゴス公式サイト',
                'naturum.ne.jp' => 'ナチュラム（アウトドア用品店）',
                'youtube.com' => 'YouTube動画',
                'prtimes.jp' => 'PR TIMES（プレスリリース）',
                'yagai.life' => 'YAGAI（アウトドアメディア）',
                'goout.jp' => 'GO OUT（アウトドア雑誌）',
                'note.com' => 'Note記事',
                'campballoon.com' => 'キャンプバルーン',
                'outdoorpark.jp' => 'アウトドアパーク',
                'netsea.jp' => 'NETSEA（卸・仕入れサイト）',
                'fjallraven.jp' => 'フェールラーベン公式サイト',
                'lantern.camp' => 'ランタン（キャンプ情報サイト）'
            );
            
            return $domain_map[$raw_title] ?? $raw_title . '（アウトドア関連サイト）';
        }
        
        // タイトルが適切な場合はそのまま使用
        return $raw_title;
    }
    
    /**
     * 実際のページタイトルを取得
     */
    private function fetch_actual_page_title($url) {
        if (strpos($url, 'vertexaisearch.cloud.google.com') === false) {
            return '';
        }
        
        try {
            // リダイレクト先URLを取得
            $redirect_url = $this->get_redirect_url($url);
            if (empty($redirect_url)) {
                return '';
            }
            
            // ページコンテンツを取得してタイトルを抽出
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $redirect_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AI-News-Bot/1.0)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            
            $content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($content && $http_code == 200) {
                // タイトルタグを抽出
                if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
                    $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
                    
                    // タイトルをクリーンアップ
                    $title = preg_replace('/\s+/', ' ', $title);
                    $title = str_replace(array(' | ', ' - ', ' ｜ ', ' － '), ' - ', $title);
                    
                    // 長すぎる場合は短縮
                    if (mb_strlen($title) > 80) {
                        $title = mb_substr($title, 0, 77) . '...';
                    }
                    
                    return $title;
                }
            }
            
        } catch (Exception $e) {
            $this->log('warning', 'ページタイトル取得エラー: ' . $e->getMessage());
        }
        
        return '';
    }
    
    /**
     * リダイレクト先URLを取得
     */
    private function get_redirect_url($url) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AI-News-Bot/1.0)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $http_code == 302) {
                if (preg_match('/Location:\s*([^\r\n]+)/i', $response, $matches)) {
                    return trim($matches[1]);
                }
            }
            
        } catch (Exception $e) {
            $this->log('warning', 'リダイレクトURL取得エラー: ' . $e->getMessage());
        }
        
        return '';
    }
    
    /**
     * ソースURLの安定性を向上
     */
    private function improve_source_url($url, $title) {
        // Grounding APIリンクの400エラーが多いため、フォールバック方式を採用
        if (strpos($url, 'vertexaisearch.cloud.google.com') !== false) {
            // まずGrounding APIリンクを試し、失敗時はGoogle検索にフォールバック
            // ユーザーのブラウザで直接アクセスは成功する可能性が高い
            return $url;
        }
        
        // 直接URLの場合はそのまま使用
        return $url;
    }
    
    /**
     * 記事内容の後処理（Markdown変換、免責事項追加など）
     */
    private function post_process_content($content, $settings = null, $add_disclaimer = true) {
        // 文字化けチェック - 無効なUTF-8の場合は基本処理のみ
        if (!mb_check_encoding($content, 'UTF-8')) {
            $this->log('warning', '文字化けが検出されたため、基本処理のみ実行');
            // 基本的なクリーニングのみ
            $content = str_replace(array("\r\n", "\r"), "\n", $content);
        } else {
            // 通常の処理
            // コンテンツの最終クリーンアップ（不完全な文末を修正）
            $content = $this->fix_incomplete_ending($content);
            
            // MarkdownをHTMLに変換
            $content = $this->convert_markdown_to_html($content);
        }
        
        // 免責事項を追加（後処理で追加する場合はスキップ）
        if ($add_disclaimer) {
            if ($settings === null) {
                $settings = get_option('ai_news_autoposter_settings', array());
            }
            $enable_disclaimer = $settings['enable_disclaimer'] ?? true;
            $disclaimer_text = $settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。';
            
            $this->log('info', 'post_process_content開始 - 免責事項処理');
            $this->log('info', '免責事項設定: 有効=' . ($enable_disclaimer ? 'true' : 'false') . ', テキスト長=' . mb_strlen($disclaimer_text));
            $this->log('info', 'コンテンツ処理前の長さ: ' . mb_strlen($content) . '文字');
            
            if ($enable_disclaimer && !empty($disclaimer_text)) {
                $before_length = mb_strlen($content);
                $content = trim($content) . "\n\n<div style=\"margin-top: 20px; padding: 10px; background-color: #f0f0f0; border-left: 4px solid #ccc; font-size: 14px; color: #666;\">" . $disclaimer_text . "</div>";
                $after_length = mb_strlen($content);
                $this->log('success', '免責事項を追加しました。追加前: ' . $before_length . '文字 → 追加後: ' . $after_length . '文字');
            } else {
                $this->log('warning', '免責事項が追加されませんでした。有効: ' . ($enable_disclaimer ? 'true' : 'false') . ', テキスト長: ' . mb_strlen($disclaimer_text));
            }
        } else {
            $this->log('info', 'post_process_content: 免責事項追加をスキップ（後処理で追加予定）');
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
        
        // 見出し変換（UTF-8フラグ追加）
        $content = preg_replace('/^### (.+)$/mu', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/mu', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/mu', '<h1>$1</h1>', $content);
        
        // 太字変換（既存のHTMLタグと競合しないよう調整、UTF-8フラグ追加）
        $content = preg_replace('/\*\*([^*<>]+?)\*\*/u', '<strong>$1</strong>', $content);
        $content = preg_replace('/__([^_<>]+?)__/u', '<strong>$1</strong>', $content);
        
        // 斜体変換（UTF-8フラグ追加）
        $content = preg_replace('/\*([^*<>]+?)\*/u', '<em>$1</em>', $content);
        $content = preg_replace('/_([^_<>]+?)_/u', '<em>$1</em>', $content);
        
        // リンク変換（UTF-8フラグ追加）
        $content = preg_replace('/\[([^\]]+?)\]\(([^)]+?)\)/u', '<a href="$2" target="_blank">$1</a>', $content);
        
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
        // 不適切な改行を修正（UTF-8フラグ追加）
        $content = preg_replace('/\n+/u', "\n", $content);
        $content = preg_replace('/>\s*\n\s*</u', '><', $content);
        
        // 基本的な段落構造を確保（UTF-8フラグ追加）
        if (!preg_match('/<p>|<div>|<h[1-6]>/u', $content)) {
            $lines = explode("\n", $content);
            $processed_lines = array();
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && !preg_match('/^</u', $line)) {
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
        
        // プロンプト検証とクリーニング
        if (empty($prompt) || !is_string($prompt)) {
            $this->log('error', 'Gemini API: 無効なプロンプトです。型: ' . gettype($prompt));
            return new WP_Error('invalid_prompt', 'プロンプトが無効です');
        }
        
        // プロンプトをUTF-8クリーニング（JSONエンコードエラー対策）
        $prompt = $this->clean_text_for_database($prompt);
        
        // プロンプト内容をログに出力（デバッグ用）
        $this->log('info', '=== Gemini APIプロンプト内容 ===');
        $this->log('info', $prompt);
        $this->log('info', '=== プロンプト終了 ===');
        
        // プロンプトの長さと設定に基づいてmaxOutputTokensを動的に設定
        $prompt_length = strlen($prompt);
        
        // 設定から期待文字数を取得
        $settings = get_option('ai_news_autoposter_settings', array());
        $expected_chars = $settings['article_word_count'] ?? 500;
        
        // Gemini 2.0/2.5でGoogle Search Groundingを使用する場合はトークンを調整
        if ($model === 'gemini-2.5-flash' || $model === 'gemini-2.0-flash-exp') {
            // プロンプト長に応じて動的に調整（実際のAPI制限: 出力65,536トークン）
            $input_tokens = intval($prompt_length / 4); // おおよその入力トークン数
            $max_output_tokens = 65536; // Gemini 2.0/2.5 Flash の実際の出力制限
            
            // 第1段階（ニュース検索）の場合は出力を制限
            if (strpos($prompt, '最新ニュース') !== false && strpos($prompt, '3〜5件') !== false) {
                $max_tokens = 2000; // 第1段階: ニュース検索用
                $this->log('info', $model . '第1段階用にmaxOutputTokensを' . $max_tokens . 'に設定（入力トークン概算: ' . $input_tokens . '）');
            } else {
                // 第2段階: 記事生成用（3記事完全生成のため大幅増加）
                $article_tokens = intval($expected_chars / 0.5); // 1トークン≈0.5文字として計算
                $max_tokens = min($article_tokens * 3, 20000); // 3記事分の余裕を持った設定
                $this->log('info', $model . '第2段階用にmaxOutputTokensを' . $max_tokens . 'に設定（入力トークン概算: ' . $input_tokens . '）');
            }
            
            // 最低限の出力を保証
            $max_tokens = max($max_tokens, strpos($prompt, '最新ニュース') !== false ? 800 : 2000);
        } else {
            // 文字数をトークン数に変換（1トークン ≈ 0.7文字として計算）
            $expected_tokens = intval($expected_chars / 0.5); // より余裕を持った計算
            
            // プロンプト長に応じた調整
            $max_tokens = $expected_tokens;
            
            if ($prompt_length > 2000) {
                $max_tokens = min($expected_tokens, 15000); // 3記事完全生成のため大幅増加
            } elseif ($prompt_length > 1500) {
                $max_tokens = min($expected_tokens, 18000); // 3記事完全生成のため大幅増加
            } else {
                $max_tokens = min($expected_tokens, 20000); // 3記事完全生成のため大幅増加
            }
            
            // 最低限の長さを保証
            $max_tokens = max($max_tokens, 8000); // 3記事分の最低値
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
        
        // Google Search Grounding - Gemini 2.0/2.5対応（エラー時はRSSフォールバック）
        if ($model === 'gemini-2.5-flash' || $model === 'gemini-2.0-flash-exp') {
            $body['tools'] = array(
                array(
                    'google_search' => new stdClass()
                )
            );
            $this->log('info', $model . 'でGoogle Search Grounding有効化（エラー時はRSSフォールバック）');
        } else {
            $this->log('info', 'Google Search Grounding非対応モデル、RSSベースニュース検索を使用');
        }
        
        $this->log('info', 'Google Search Grounding設定: ' . json_encode($body['tools'] ?? null));
        
        // JSON エンコード前の検証
        $json_body = json_encode($body);
        if ($json_body === false) {
            $this->log('error', 'Gemini API: JSONエンコードに失敗しました。エラー: ' . json_last_error_msg());
            return new WP_Error('json_encode_failed', 'リクエストデータのエンコードに失敗しました');
        }
        
        $this->log('info', 'Gemini API リクエストボディサイズ: ' . strlen($json_body) . ' bytes');
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => $json_body,
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
        
        // UTF-8エンコーディングチェック（変換処理は削除して文字化けを防止）
        if (!mb_check_encoding($response_body, 'UTF-8')) {
            $this->log('error', 'Gemini APIレスポンスがUTF-8ではありません');
            return new WP_Error('encoding_error', 'レスポンスのエンコーディングエラー');
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
        $image_type = $settings['image_generation_type'] ?? 'placeholder';
        
        switch ($image_type) {
            case 'dalle':
                // DALL-Eは一時的に無効化（ネットワークエラー対策）
                $this->log('warning', 'DALL-E機能は一時的に無効化されています。プレースホルダー画像を使用します。');
                return $this->generate_placeholder_image($post_id, $title);
            case 'unsplash':
                return $this->generate_unsplash_image($post_id, $title, $content, $settings);
            default:
                $this->log('info', 'プレースホルダー画像を生成中...');
                return $this->generate_placeholder_image($post_id, $title);
        }
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
            
            // 検索結果にランダム性を追加
            $per_page = 10; // 複数の候補から選択
            $response = wp_remote_get('https://api.unsplash.com/search/photos?' . http_build_query(array(
                'query' => $search_query,
                'orientation' => 'landscape',
                'per_page' => $per_page,
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
            
            // 複数の結果からランダムに選択
            $results = $body['results'];
            $random_index = array_rand($results);
            $selected_image = $results[$random_index];
            
            $image_url = $selected_image['urls']['regular'] ?? '';
            if (empty($image_url)) {
                $this->log('error', 'Unsplash画像URLが取得できませんでした');
                return $this->generate_placeholder_image($post_id, $title);
            }
            
            $this->log('info', 'Unsplash画像を選択: ' . ($random_index + 1) . '/' . count($results) . ' 件目 (ID: ' . ($selected_image['id'] ?? 'unknown') . ')');
            
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
        // バックアップ: 元の固定ロジック（必要に応じて戻せるように）
        /*
        $keywords = array('technology', 'artificial intelligence', 'digital', 'computer', 'innovation', 'future');
        if (stripos($title, 'AI') !== false || stripos($title, '人工知能') !== false) {
            return 'artificial intelligence technology';
        }
        if (stripos($title, 'robot') !== false || stripos($title, 'ロボット') !== false) {
            return 'robot technology';
        }
        return 'technology innovation';
        */
        
        // 新しい動的キーワード抽出ロジック
        $combined_text = $title . ' ' . strip_tags($content);
        
        // キーワードマッピング（日本語→英語）
        $keyword_map = array(
            // テクノロジー関連
            'AI|人工知能|機械学習|ディープラーニング' => array('artificial intelligence', 'machine learning', 'technology'),
            'ロボット|robot' => array('robot', 'robotics', 'automation'),
            'スマート|IoT|デジタル' => array('smart technology', 'digital', 'innovation'),
            
            // アウトドア・スポーツ関連
            'アウトドア|キャンプ|登山|ハイキング' => array('outdoor', 'camping', 'hiking', 'nature'),
            'ギア|装備|用品' => array('equipment', 'gear', 'tools'),
            'テント|寝袋|バックパック' => array('camping gear', 'outdoor equipment'),
            
            // ビジネス・経済関連
            'ビジネス|企業|会社|経済' => array('business', 'corporate', 'office'),
            '投資|金融|株式' => array('finance', 'investment', 'money'),
            'マーケティング|売上|販売' => array('marketing', 'sales', 'commerce'),
            
            // ライフスタイル関連
            '健康|医療|ヘルスケア' => array('health', 'medical', 'wellness'),
            '教育|学習|研修' => array('education', 'learning', 'study'),
            '料理|食事|グルメ' => array('food', 'cooking', 'culinary'),
            '旅行|観光|ホテル' => array('travel', 'tourism', 'vacation'),
            
            // 一般的なトピック
            '環境|エコ|持続可能' => array('environment', 'sustainability', 'eco friendly'),
            'デザイン|アート|クリエイティブ' => array('design', 'creative', 'art'),
            '音楽|エンターテイメント' => array('music', 'entertainment', 'performance')
        );
        
        // マッチしたキーワードを収集
        $matched_keywords = array();
        foreach ($keyword_map as $pattern => $english_keywords) {
            if (preg_match('/(' . $pattern . ')/iu', $combined_text)) {
                $matched_keywords = array_merge($matched_keywords, $english_keywords);
            }
        }
        
        // マッチしたキーワードがない場合のフォールバック
        if (empty($matched_keywords)) {
            $fallback_keywords = array('business', 'technology', 'modern', 'professional', 'innovation');
            $matched_keywords = $fallback_keywords;
        }
        
        // ランダム性を追加：複数のキーワードからランダムに選択
        $selected_keywords = array();
        $num_keywords = min(2, count($matched_keywords)); // 最大2つのキーワード
        $random_keys = array_rand($matched_keywords, $num_keywords);
        
        if (is_array($random_keys)) {
            foreach ($random_keys as $key) {
                $selected_keywords[] = $matched_keywords[$key];
            }
        } else {
            $selected_keywords[] = $matched_keywords[$random_keys];
        }
        
        $search_query = implode(' ', $selected_keywords);
        $this->log('info', 'Unsplash検索クエリ: "' . $search_query . '" （抽出元: "' . mb_substr($combined_text, 0, 100) . '..."）');
        
        return $search_query;
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
     * Gemini用ニュース検索プロンプト生成
     */
    private function build_gemini_news_search_prompt($settings) {
        $keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習';
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        $word_count = $settings['article_word_count'] ?? 800; // 文字数を増やす
        $output_language = $settings['output_language'] ?? 'japanese';
        
        $prompt = "あなたは{$writing_style}の文体で記事を書く優秀なジャーナリストです。\n\n";
        $prompt .= "以下のキーワードに関する最新ニュースを検索し、それに基づいて{$word_count}文字程度の記事を{$output_language}で作成してください：\n";
        $prompt .= "キーワード: {$keywords}\n\n";
        $prompt .= "記事の要件：\n";
        $prompt .= "1. 最新のニュース情報を必ず検索して参考にする\n";
        $prompt .= "2. タイトル、導入、本文（複数段落）、まとめの構成にする\n";
        $prompt .= "3. 読者にとって価値のある内容にする\n";
        $prompt .= "4. 記事は完結した内容にし、途中で終わらないようにする\n\n";
        $prompt .= "出力形式：\n";
        $prompt .= "タイトル: [記事タイトル]\n\n";
        $prompt .= "[記事本文（複数段落で構成）]\n\n";
        $prompt .= "\n記事を最後まで完成させてください。";
        
        $this->log('info', 'Geminiニュース検索プロンプト生成完了');
        return $prompt;
    }
    
    /**
     * Gemini 2.5 第1段階: ニュース検索のみ（URL・タイトル一覧取得用）
     */
    private function build_gemini_search_only_prompt($settings) {
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習';
        $news_languages = $settings['news_languages'] ?? array('japanese', 'english');
        
        // カスタムプロンプトがあればそれを使用（第1段階も対応）
        $custom_prompt = $settings['custom_prompt'] ?? '';
        $this->log('info', '第1段階カスタムプロンプト確認: 長さ=' . mb_strlen($custom_prompt) . ', 第一段階存在=' . (strpos($custom_prompt, '第一段階') !== false ? 'YES' : 'NO'));
        if (!empty($custom_prompt) && strpos($custom_prompt, '第一段階') !== false) {
            $this->log('info', '✅ カスタムプロンプト（第1段階）を使用します');
            // 第一段階部分のみ抽出
            if (preg_match('/第一段階[：:](.+?)(?:第二段階|$)/s', $custom_prompt, $matches)) {
                $first_stage_prompt = trim($matches[1]);
                if (!empty($first_stage_prompt)) {
                    $built_prompt = $this->build_custom_prompt($first_stage_prompt, $settings);
                    if (!empty($built_prompt)) {
                        $this->log('info', '第1段階カスタムプロンプト構築成功: ' . mb_strlen($built_prompt) . '文字');
                        return $built_prompt;
                    } else {
                        $this->log('error', '第1段階カスタムプロンプト構築後に空になりました');
                    }
                } else {
                    $this->log('error', '第1段階プロンプト抽出結果が空です');
                }
            } else {
                $this->log('error', '第1段階正規表現マッチに失敗');
            }
            $this->log('warning', '第一段階のプロンプト抽出に失敗、デフォルトを使用');
        } else {
            $this->log('info', '❌ カスタムプロンプト（第1段階）の条件不一致、デフォルトを使用');
        }
        
        // ニュース収集言語を文字列に変換
        $language_map = array(
            'japanese' => '日本語',
            'english' => '英語',
            'chinese' => '中国語'
        );
        $language_names = array();
        foreach ($news_languages as $lang) {
            if (isset($language_map[$lang])) {
                $language_names[] = $language_map[$lang];
            }
        }
        $news_collection_language = implode('、', $language_names);
        
        $this->log('info', 'Gemini第1段階ニュース検索プロンプト生成開始');
        
        $prompt = "【{$search_keywords}】について最新ニュースを【{$news_collection_language}】で検索し、関連記事を3〜5件取得してください。\n\n";
        
        $prompt .= "以下の形式で簡潔に：\n\n";
        $prompt .= "## 最新ニュース\n\n";
        $prompt .= "1. [タイトル1](URL1)\n";
        $prompt .= "2. [タイトル2](URL2)\n";
        $prompt .= "3. [タイトル3](URL3)\n\n";
        $prompt .= "条件: 過去1週間以内、重複除く、実アクセス可能URL\n";
        
        $this->log('info', '📝 Gemini第1段階デフォルトプロンプト生成完了: ' . mb_strlen($prompt) . '文字');
        return $prompt;
    }
    
    /**
     * Gemini 2.5 第2段階: 取得したURL群から記事生成
     */
    private function build_gemini_article_from_sources_prompt($settings, $grounding_sources) {
        $search_keywords = $settings['search_keywords'] ?? 'AI ニュース, 人工知能, 機械学習';
        $writing_style = $settings['writing_style'] ?? '夏目漱石';
        $article_word_count = $settings['article_word_count'] ?? 800;
        $output_language = $settings['output_language'] ?? 'japanese';
        
        // カスタムプロンプトがあればそれを使用（第2段階も対応）
        $custom_prompt = $settings['custom_prompt'] ?? '';
        $this->log('info', '第2段階カスタムプロンプト確認: 長さ=' . mb_strlen($custom_prompt) . ', 第二段階存在=' . (strpos($custom_prompt, '第二段階') !== false ? 'YES' : 'NO'));
        if (!empty($custom_prompt) && strpos($custom_prompt, '第二段階') !== false) {
            $this->log('info', '✅ カスタムプロンプト（第2段階）を使用します');
            // 第二段階部分を抽出（より柔軟な正規表現）
            if (preg_match('/第二段階[：:\s]*(.+?)(?=その上で|$)/s', $custom_prompt, $matches)) {
                $second_stage = trim($matches[1]);
                
                // 分析要素を強化（より強力な指示）
                $analysis_requirements = "\n\n**【絶対必須】記事構成要件:**\n";
                $analysis_requirements .= "記事には以下の要素を必ず含めてください：\n\n";
                $analysis_requirements .= "1. **背景・文脈分析セクション**: \"背景として\" または \"文脈として\" で始まる段落を作成し、なぜ今このニュースが重要なのか業界の背景を説明\n";
                $analysis_requirements .= "2. **影響・考察セクション**: \"影響として\" または \"考察すると\" で始まる段落を作成し、このニュースが今後どのような影響を与えるか専門的な考察を記述\n";
                $analysis_requirements .= "3. **推察セクション**: \"推察として\" または \"今後の展開として\" で始まる段落を作成し、根拠のある推察を述べる\n";
                $analysis_requirements .= "4. **具体的データの引用**: 数値、日付、企業名、統計データを記事全体に散りばめて引用\n\n";
                $analysis_requirements .= "**重要**: 上記の「背景・文脈」「影響・考察」「推察」の言葉を記事内で必ず使用してください。\n\n";
                
                // タイトル生成部分も含める
                $title_instruction = "";
                if (preg_match('/その上で(.+)/s', $custom_prompt, $title_matches)) {
                    $title_instruction = "\n\n**タイトル要件:**\n" . trim($title_matches[1]);
                }
                
                // ニュースソース情報を追加
                $news_info = "\n\n**取得したニュース情報:**\n";
                foreach ($grounding_sources as $index => $source) {
                    $news_info .= ($index + 1) . ". {$source['title']} ({$source['url']})\n";
                }
                
                // 構造化された最終プロンプトを作成
                $final_prompt = $second_stage . $analysis_requirements . $title_instruction . $news_info;
                $final_prompt .= "\n\n**【最重要指示】**: 記事内で「背景」「文脈」「影響」「考察」「推察」の言葉を必ず使用し、それぞれを独立した段落または文章で表現してください。これらの要素が含まれていない記事は不完全とみなされます。";
                
                if (!empty($final_prompt)) {
                    return $this->build_custom_prompt($final_prompt, $settings);
                }
            }
            $this->log('warning', '第二段階のプロンプト抽出に失敗、デフォルトを使用');
        } else {
            $this->log('info', '❌ カスタムプロンプト（第2段階）の条件不一致、デフォルトを使用');
        }
        
        $this->log('info', '📝 Gemini第2段階デフォルトプロンプト生成開始: ' . count($grounding_sources) . '件のソース');
        
        // Google NewsやRSSのURLをクリーンアップしてプロンプトサイズを削減
        $cleaned_sources = array();
        foreach ($grounding_sources as $index => $source) {
            $title = $source['title'] ?? "ニュース記事" . ($index + 1);
            $url = $this->clean_news_url($source['url'] ?? "#");
            $cleaned_sources[] = array('title' => $title, 'url' => $url);
        }
        
        $prompt = "あなたは{$writing_style}風の文体で記事を書く優秀なジャーナリストです。\n\n";
        $prompt .= "「{$search_keywords}」に関する包括的な記事を作成してください。\n\n";
        
        $prompt .= "以下の要件で包括的な記事を作成してください：\n\n";
        $prompt .= "**記事要件:**\n";
        $prompt .= "- 文字数: **厳密に{$article_word_count}文字以内**（これは絶対に守ってください）\n";
        $prompt .= "- 文体: {$writing_style}風\n";
        $prompt .= "- 言語: {$output_language}\n";
        $prompt .= "- 構成: タイトル、導入、本文（複数段落）、結論\n\n";
        
        $prompt .= "**記事構成（{$article_word_count}文字以内厳守）:**\n\n";
        $prompt .= "**重要: 以下のテンプレート通りの順序で記事を作成してください。[ ]内のプレースホルダーは実際の記事内容に置き換えてください。セクションヘッダー（##）は必ず含めてください：**\n\n";
        $prompt .= "```\n";
        $prompt .= "タイトル: [魅力的な見出し]\n\n";
        $prompt .= "## 今日の{$search_keywords}関連のニュース\n\n";
        foreach ($cleaned_sources as $index => $source) {
            $prompt .= "- [{$source['title']}]({$source['url']})\n";
        }
        $prompt .= "\n[ここに導入段落を記述: 上記ニュースソースに基づく簡潔な業界概要]\n\n";
        $prompt .= "[ここに本文段落1を記述: ニュースソースから読み取れる最新動向の詳細]\n\n";
        $prompt .= "[ここに本文段落2を記述: 関連企業・団体の具体的な取り組み内容]\n\n";
        $prompt .= "[ここに本文段落3を記述: 業界や社会への具体的な影響分析]\n\n";
        $prompt .= "[ここに結論段落を記述: 今後の展望と簡潔なまとめ]\n\n";
        $prompt .= "```\n\n";
        $prompt .= "**文字数管理**: 各段落の文字数を調整し、全体で{$article_word_count}文字以内に収めてください。\n\n";
        
        $prompt .= "**重要な指示（詳細分析）:**\n";
        $prompt .= "- **文字数制限を厳守**: {$article_word_count}文字を超えないよう、内容を適切に調整してください\n";
        $prompt .= "- **記事完結性**: 記事は指定文字数内で完結した内容にし、途中で切れないようにしてください\n";
        $prompt .= "- 各ニュースソースの具体的な内容を調査し、重要な数値、日付、人名、企業名、統計データを引用してください\n";
        $prompt .= "- 複数のニュースソースで報じられている共通点と相違点を分析してください\n";
        $prompt .= "- 各記事から得られる具体的事実を「〜によると」「〜が発表した」「〜のデータでは」という形で明確に引用してください\n";
        $prompt .= "- ニュースソースで言及されている専門家のコメントや見解があれば具体的に引用してください\n";
        $prompt .= "- 関連する企業・団体の具体的な取り組み内容、発表内容、計画などを記載してください\n";
        $prompt .= "- 現在の状況、過去の経緯、今後の展望を文字数制限内で整理してください\n";
        $prompt .= "- 業界や社会に与える具体的な影響を、ニュースソースの情報を基に分析してください\n";
        $prompt .= "- **絶対条件**: 記事は{$article_word_count}文字以内で完全に完成させてください\n";
        $prompt .= "- **必須**: 上記の記事構成テンプレートに完全に従ってください\n";
        $prompt .= "- **重要**: 「今日の{$search_keywords}関連のニュース」セクションは絶対に省略しないでください\n";
        $prompt .= "- **絶対必須**: 記事は必ずタイトルの後に「## 今日の{$search_keywords}関連のニュース」セクションを含めてください\n";
        $prompt .= "- **絶対必須**: 「今日の{$search_keywords}関連のニュース」セクションの直後に記事構成テンプレート内のニュースソースリストを表示してください\n";
        $prompt .= "- **重要**: 記事構成テンプレート内で指定されているニュースソースのタイトルとURLを正確に使用してください\n";
        $prompt .= "- **必須**: 記事構成テンプレート内のニュースソースの内容を詳細に調査・分析してください\n";
        $prompt .= "- 推測や一般論ではなく、ニュースソースから得られる具体的事実に基づいて記述してください\n";
        
        $this->log('info', 'Gemini第2段階記事生成プロンプト生成完了');
        return $prompt;
    }
    
    /**
     * Gemini用シンプル1段階プロンプトテンプレート（プレースホルダー版）
     */
    private function build_gemini_simple_prompt_template() {
        $this->log('info', 'プロンプト結果に任せる方式のテンプレート生成開始');
        
        // プロンプト結果に任せる方式（v2.0）- 明確な記事構造指定（URL除外）
        $prompt = "{検索キーワード}に関する{ニュース収集言語}のニュースを正確に{記事数}本選んで紹介してください。\n\n";
        $prompt .= "【重要】記事本文中にはURLアドレスやURLラベルを一切含めないでください。タイトルのみ記載してください。\n\n";
        $prompt .= "以下のHTMLタグ形式で{記事数}つの記事すべてを完全に書いてください：\n\n";
        $prompt .= "1本目の記事：\n<h2>タイトル（URLアドレスは記載しない）</h2>\n<h3>概要と要約</h3>\n本文\n<h3>背景・文脈</h3>\n本文\n<h3>今後の影響</h3>\n本文（{影響分析文字数}文字程度の考察）\n\n";
        $prompt .= "2本目の記事：\n<h2>タイトル（URLアドレスは記載しない）</h2>\n<h3>概要と要約</h3>\n本文\n<h3>背景・文脈</h3>\n本文\n<h3>今後の影響</h3>\n本文（{影響分析文字数}文字程度の考察）\n\n";
        $prompt .= "3本目の記事：\n<h2>タイトル（URLアドレスは記載しない）</h2>\n<h3>概要と要約</h3>\n本文\n<h3>背景・文脈</h3>\n本文\n<h3>今後の影響</h3>\n本文（{影響分析文字数}文字程度の考察）\n\n";
        $prompt .= "必ず{記事数}つの記事すべてを最後まで完全に書いてください。URLアドレスは一切記載しないでください。\n\n";
        
        $prompt .= "【プロンプト結果に任せる方式について】\n";
        $prompt .= "このプロンプトは、Geminiの自然な判断に任せて高品質な記事を生成する方式です。\n";
        $prompt .= "- 文字数制限なし（Geminiが適切な長さを判断）\n";
        $prompt .= "- 構造の強制変更なし（生成された内容をそのまま使用）\n";
        $prompt .= "- URL修正などの複雑な後処理なし\n\n";
        
        $prompt .= "【利用可能なプレースホルダー】\n";
        $prompt .= "{検索キーワード} - 記事のテーマとなるキーワード\n";
        $prompt .= "{ニュース収集言語} - 検索対象の言語（日本語と英語など）\n";
        $prompt .= "{出力言語} - 記事の出力言語\n";
        $prompt .= "{文体} - 記事の文体スタイル\n";
        $prompt .= "{記事数} - 生成する記事の数\n";
        $prompt .= "{影響分析文字数} - 今後の影響セクションの文字数\n\n";
        
        $prompt .= "※このプロンプトはv2.0方式（プロンプト結果に任せる）を採用しており、\n";
        $prompt .= "　従来の複雑な後処理を排除してGeminiの自然な判断を最大限活用します。";
        
        $this->log('info', 'プロンプト結果に任せる方式テンプレート生成完了: ' . mb_strlen($prompt) . '文字');
        return $prompt;
    }
    
    /**
     * Gemini用シンプル1段階プロンプト構築（Google Search Grounding使用）
     */
    private function build_gemini_simple_prompt($settings) {
        $search_keywords = $settings['search_keywords'] ?? 'アウトドアギア, キャンプ用品, 登山用品, ハイキング用品, テント, 寝袋, バックパック';
        $news_languages = $settings['news_languages'] ?? array('japanese', 'english');
        $output_language = $settings['output_language'] ?? 'japanese';
        $article_word_count = $settings['article_word_count'] ?? 1500;
        $writing_style = $settings['writing_style'] ?? '新聞記事';
        $article_count = $settings['article_count'] ?? 3;
        $impact_length = $settings['impact_analysis_length'] ?? 500;
        
        // カスタムプロンプトがあればそれを使用
        $custom_prompt = $settings['custom_prompt'] ?? '';
        if (!empty($custom_prompt)) {
            $this->log('info', '✅ カスタムプロンプトを使用します');
            $built_prompt = $this->build_custom_prompt($custom_prompt, $settings);
            if (!empty($built_prompt)) {
                $this->log('info', 'カスタムプロンプト構築成功: ' . mb_strlen($built_prompt) . '文字');
                return $built_prompt;
            }
        }
        
        // 言語設定を文字列に変換
        $language_map = array(
            'japanese' => '日本語',
            'english' => '英語',
            'chinese' => '中国語'
        );
        $language_names = array();
        foreach ($news_languages as $lang) {
            if (isset($language_map[$lang])) {
                $language_names[] = $language_map[$lang];
            }
        }
        $news_collection_language = implode('と', $language_names);
        $output_language_name = $language_map[$output_language] ?? '日本語';
        
        $this->log('info', 'Geminiシンプル1段階プロンプト生成開始');
        
        // 言語優先度を設定
        $language_priority = '';
        if ($output_language === 'japanese') {
            $language_priority = '日本語のニュースソースを優先し、次に英語、その他の言語の順で検索してください。';
        } else {
            $language_priority = '英語のニュースソースを優先し、次に現地語、その他の言語の順で検索してください。';
        }
        
        // 段落あたり文字数を事前計算
        $per_paragraph_chars = intval($article_word_count / 5); // 5段落で分割
        
        // 日付付きタイトル生成を含む明確な3記事指定プロンプト
        $today = date('Y年m月d日');
        // v2.0.0の正しいプロンプト（プロンプト結果に任せる方式、URL除外）
        $prompt = "{$search_keywords}に関する{$news_collection_language}のニュースを正確に{$article_count}本選んで紹介してください。\n\n";
        $prompt .= "【重要】記事本文中にはURLアドレスやURLラベルを一切含めないでください。タイトルのみ記載してください。\n\n";
        $prompt .= "【出力構成】\n";
        $prompt .= "1. まず最初に、{$search_keywords}について簡潔なリード文（2-3文）を書いてください\n";
        $prompt .= "2. その後、以下のHTMLタグ形式で{$article_count}つの記事すべてを完全に書いてください\n\n";
        $prompt .= "リード文の例：「{$search_keywords}の活用は、ビジネスや日常生活のさまざまな場面で注目を集めています。以下に、{$search_keywords}に関する最新のニュース記事を{$article_count}本ご紹介します。」\n\n";
        
        // 動的に記事数分の指示を生成
        for ($i = 1; $i <= $article_count; $i++) {
            $prompt .= "{$i}本目の記事：\n";
            $prompt .= "<h2>タイトル（URLアドレスは記載しない）</h2>\n";
            $prompt .= "<h3>概要と要約</h3>\n";
            $prompt .= "本文\n";
            $prompt .= "<h3>背景・文脈</h3>\n";
            $prompt .= "本文\n";
            $prompt .= "<h3>今後の影響</h3>\n";
            $prompt .= "本文（{$impact_length}文字程度の考察）\n\n";
        }
        
        $prompt .= "必ず{$article_count}つの記事すべてを最後まで完全に書いてください。URLアドレスは一切記載しないでください。";
        
        // プレースホルダーを実際の値に置換
        $prompt = str_replace('{文字数}', $per_paragraph_chars, $prompt);
        
        $this->log('info', 'Geminiシンプル1段階プロンプト生成完了: ' . mb_strlen($prompt) . '文字');
        $this->log('info', '段落あたり文字数: ' . $per_paragraph_chars . '文字');
        return $prompt;
    }
    
    /**
     * Google NewsやRSSのURLをクリーンアップしてプロンプトサイズを削減
     */
    private function clean_news_url($url) {
        // Google NewsのURLは非常に長いので短縮
        if (strpos($url, 'news.google.com') !== false) {
            // Google NewsのURLから実際のドメインを抽出
            if (preg_match('/url=([^&]+)/', $url, $matches)) {
                $decoded_url = urldecode($matches[1]);
                $parsed = parse_url($decoded_url);
                if ($parsed && isset($parsed['host'])) {
                    return 'https://' . $parsed['host'];
                }
            }
            return 'https://news.google.com';
        }
        
        // 長いRSSパラメータを削除
        if (strpos($url, '?') !== false) {
            $url = strtok($url, '?');
        }
        
        // HTMLエンティティをデコード
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 不要な文字を削除
        $url = preg_replace('/[<>"]/', '', $url);
        
        return $url;
    }
    
    /**
     * 日付プレースホルダーを現在の日付で置換
     */
    private function replace_date_placeholders($text) {
        $current_time = current_time('timestamp');
        
        // 日本語ロケール設定
        $year = date('Y', $current_time);
        $month = date('n', $current_time);
        $day = date('j', $current_time);
        
        // 月名の日本語変換
        $japanese_months = array(
            1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月', 5 => '5月', 6 => '6月',
            7 => '7月', 8 => '8月', 9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月'
        );
        
        // 英語月名
        $english_months = array(
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        );
        
        // プレースホルダー置換配列
        $replacements = array(
            '{date}' => $year . '年' . $month . '月' . $day . '日',
            '{date_short}' => $month . '月' . $day . '日',
            '{date_en}' => $english_months[$month] . ' ' . $day . ', ' . $year,
            '{date_iso}' => date('Y-m-d', $current_time),
            '{today}' => '今日',
            '{year}' => $year,
            '{month}' => $japanese_months[$month],
            '{day}' => $day . '日'
        );
        
        // プレースホルダーを置換
        $result = str_replace(array_keys($replacements), array_values($replacements), $text);
        
        $this->log('info', '日付プレースホルダー置換: ' . count($replacements) . '種類対応');
        return $result;
    }
    
    /**
     * ニュースベースのプロンプト生成
     */
    private function build_news_based_prompt($settings, $news_data, $api_type = 'claude') {
        $keywords = $settings['search_keywords'] ?? 'AI ニュース';
        $writing_style = $settings['writing_style'] ?? '記者';
        $word_count = $settings['article_word_count'] ?? 800; // 文字数を増やす
        $output_language = $settings['output_language'] ?? 'japanese';
        
        // ニュースデータを整理
        $news_summary = '';
        $citation_sources = array();
        
        foreach ($news_data as $index => $news) {
            $news_number = intval($index) + 1;
            $title = isset($news['title']) ? $news['title'] : '不明なタイトル';
            $description = isset($news['description']) ? $news['description'] : '概要なし';
            $url = isset($news['url']) ? $news['url'] : '#';
            $pubDate = isset($news['pubDate']) ? $news['pubDate'] : '日付不明';
            
            $news_summary .= $news_number . ". {$title}\n";
            $news_summary .= "   概要: {$description}\n";
            $news_summary .= "   URL: {$url}\n";
            $news_summary .= "   公開日: {$pubDate}\n\n";
            
            $citation_sources[] = "[{$title}]({$url})";
        }
        
        if ($api_type === 'gemini') {
            $prompt = "以下の最新ニュース情報を参考に、{$word_count}文字程度の完結した記事を{$output_language}で作成してください。\n\n";
            $prompt .= "参考ニュース:\n{$news_summary}\n";
            $prompt .= "記事の要件:\n";
            $prompt .= "- タイトル、本文、まとめを含む完結した構成\n";
            $prompt .= "- {$writing_style}のような文体\n";
            $prompt .= "- SEOを意識したキーワード「{$keywords}」を自然に含める\n";
            $prompt .= "- 情報の正確性を重視\n";
            $prompt .= "- 記事の最後に参考情報源を含める\n";
            $prompt .= "- 記事は必ず最後まで完成させる\n\n";
            $prompt .= "フォーマット:\n";
            $prompt .= "タイトル: [記事タイトル]\n\n";
            $prompt .= "[記事本文（必ず最後まで完成）]\n\n";
        } else {
            // Claude用プロンプト
            $prompt = "あなたは{$writing_style}として、以下の最新ニュース情報を参考に、読みやすく情報価値の高い記事を作成してください。\n\n";
            $prompt .= "## 参考ニュース情報\n{$news_summary}\n";
            $prompt .= "## 記事作成の指示\n";
            $prompt .= "- 文字数: 約{$word_count}文字\n";
            $prompt .= "- 言語: {$output_language}\n";
            $prompt .= "- キーワード: {$keywords}\n";
            $prompt .= "- 構成: タイトル、導入、本文（複数段落）、まとめ\n";
            $prompt .= "- 読者に価値を提供する内容にする\n";
            // 参考情報源セクションの指示を削除（修正により削除）
            $prompt .= "- 記事は必ず最後まで完成させる\n\n";
            $prompt .= "出力形式:\n";
            $prompt .= "タイトル: [記事タイトル]\n\n";
            $prompt .= "[記事本文（必ず最後まで完成）]\n\n";
        }
        
        $this->log('info', 'ニュースベースプロンプト生成完了 (ニュース件数: ' . count($news_data) . ')');
        return $prompt;
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
    
    /**
     * 最新ニュース検索・取得機能
     */
    private function search_latest_news($keywords, $limit = 5) {
        $this->log('info', "ニュース検索開始: キーワード「{$keywords}」");
        
        $news_sources = array();
        
        // Google News RSS
        $google_news = $this->fetch_google_news($keywords, $limit);
        if (!empty($google_news)) {
            $news_sources = array_merge($news_sources, $google_news);
        }
        
        // 日本語AIニュースサイトのRSS
        $japanese_tech_news = $this->fetch_japanese_tech_news($keywords, $limit);
        if (!empty($japanese_tech_news)) {
            $news_sources = array_merge($news_sources, $japanese_tech_news);
        }
        
        // 重複除去とソート
        $news_sources = $this->deduplicate_news($news_sources);
        $news_sources = array_slice($news_sources, 0, $limit);
        
        $this->log('info', '検索結果: ' . count($news_sources) . '件の記事を取得');
        
        return $news_sources;
    }
    
    /**
     * Google News RSSから検索（キーワード特化）
     */
    private function fetch_google_news($keywords, $limit = 3) {
        // キーワードを解析してGoogle News検索を最適化
        $keyword_array = array_map('trim', explode(',', $keywords));
        $main_keyword = $keyword_array[0] ?? $keywords;
        
        // 日本語キーワードの場合、より特化した検索クエリを作成
        $search_queries = array();
        
        // メイン検索クエリ
        $search_queries[] = $main_keyword . ' ニュース';
        
        // 関連キーワードがある場合は追加検索
        if (count($keyword_array) > 1) {
            $search_queries[] = implode(' OR ', array_slice($keyword_array, 0, 3));
        }
        
        $all_news = array();
        
        foreach ($search_queries as $query) {
            $encoded_keywords = urlencode($query);
            $rss_url = "https://news.google.com/rss/search?q={$encoded_keywords}&hl=ja&gl=JP&ceid=JP:ja";
            
            $this->log('info', "Google News検索: {$query}");
            
            $response = wp_remote_get($rss_url, array(
                'timeout' => 30,
                'user-agent' => 'Mozilla/5.0 (compatible; WordPress NewsBot)'
            ));
            
            if (is_wp_error($response)) {
                $this->log('warning', "Google News RSS取得エラー ({$query}): " . $response->get_error_message());
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            
            // SimpleXMLでRSSをパース
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            
            if ($xml === false) {
                $this->log('warning', "Google News RSSパースエラー ({$query})");
                continue;
            }
            
            if (isset($xml->channel->item)) {
                $count = 0;
                foreach ($xml->channel->item as $item) {
                    if ($count >= 2) break; // クエリあたり2件まで
                    
                    $title = (string)$item->title;
                    $description = (string)$item->description;
                    
                    // Google News検索結果は既にキーワードでフィルタ済みなので、緩い関連性チェック
                    if ($this->is_loosely_relevant_news($title . ' ' . $description, $keywords)) {
                        $all_news[] = array(
                            'title' => $title,
                            'url' => (string)$item->link,
                            'description' => $description,
                            'pub_date' => (string)$item->pubDate,
                            'source' => 'Google News'
                        );
                        $count++;
                    }
                }
            }
        }
        
        // 重複除去
        $unique_news = $this->deduplicate_news($all_news);
        $final_news = array_slice($unique_news, 0, $limit);
        
        $this->log('info', 'Google News: ' . count($final_news) . '件取得（検索クエリ: ' . count($search_queries) . '件）');
        return $final_news;
    }
    
    /**
     * 日本語技術ニュースサイトから検索
     */
    private function fetch_japanese_tech_news($keywords, $limit = 3) {
        $tech_rss_feeds = array(
            'ITmedia' => 'https://rss.itmedia.co.jp/rss/2.0/ait.xml',
            'TechCrunch Japan' => 'https://jp.techcrunch.com/feed/',
            'Engadget日本版' => 'https://feeds.feedburner.com/engadget/japanese',
            'CNET Japan' => 'https://feeds.japan.cnet.com/rss/cnet/all.rdf',
            '朝日新聞デジタル' => 'https://www.asahi.com/rss/index.rdf',
            'NHKニュース' => 'https://www.nhk.or.jp/rss/news/cat0.xml',
            'Yahoo!ニュース' => 'https://news.yahoo.co.jp/rss/topics/top-picks.xml'
        );
        
        $all_news = array();
        
        foreach ($tech_rss_feeds as $source_name => $rss_url) {
            $news_items = $this->fetch_rss_feed($rss_url, $source_name, $keywords);
            if (!empty($news_items)) {
                $all_news = array_merge($all_news, array_slice($news_items, 0, 2));
            }
        }
        
        return array_slice($all_news, 0, $limit);
    }
    
    /**
     * RSS/Atomフィードを取得・パース
     */
    private function fetch_rss_feed($rss_url, $source_name, $keywords) {
        $response = wp_remote_get($rss_url, array(
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress NewsBot)'
        ));
        
        if (is_wp_error($response)) {
            $this->log('warning', "{$source_name} RSS取得エラー: " . $response->get_error_message());
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $news_items = array();
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            $this->log('warning', "{$source_name} RSSパースエラー");
            return array();
        }
        
        // RSS 2.0 形式
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $title = (string)$item->title;
                $description = (string)($item->description ?? $item->summary ?? '');
                
                // キーワードに関連する記事のみフィルタ
                if ($this->is_relevant_news($title . ' ' . $description, $keywords)) {
                    $news_items[] = array(
                        'title' => $title,
                        'url' => (string)$item->link,
                        'description' => $description,
                        'pub_date' => (string)$item->pubDate,
                        'source' => $source_name
                    );
                }
            }
        }
        // Atom形式
        elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $title = (string)$entry->title;
                $description = (string)($entry->summary ?? $entry->content ?? '');
                
                if ($this->is_relevant_news($title . ' ' . $description, $keywords)) {
                    $news_items[] = array(
                        'title' => $title,
                        'url' => (string)$entry->link['href'],
                        'description' => $description,
                        'pub_date' => (string)$entry->published,
                        'source' => $source_name
                    );
                }
            }
        }
        
        $this->log('info', "{$source_name}: " . count($news_items) . "件の関連記事を取得");
        return $news_items;
    }
    
    /**
     * ニュースの関連性チェック
     */
    private function is_relevant_news($text, $keywords) {
        $text = strtolower($text);
        $keyword_array = explode(',', strtolower($keywords));
        
        // ユーザー設定キーワードとの関連性をチェック
        foreach ($keyword_array as $keyword) {
            if (strpos($text, trim($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 緩いニュース関連性チェック（Google News用）
     */
    private function is_loosely_relevant_news($text, $keywords) {
        $text = strtolower($text);
        $keyword_array = explode(',', strtolower($keywords));
        
        // より緩い条件：部分一致やカタカナ・ひらがな変換も考慮
        foreach ($keyword_array as $keyword) {
            $keyword = trim($keyword);
            
            // 直接一致
            if (strpos($text, $keyword) !== false) {
                return true;
            }
            
            // カタカナ・ひらがなの違いを考慮
            $keyword_variants = array($keyword);
            if ($keyword === 'アウトドア') {
                $keyword_variants[] = 'あうとどあ';
                $keyword_variants[] = 'outdoor';
            } elseif ($keyword === 'キャンプ') {
                $keyword_variants[] = 'きゃんぷ';
                $keyword_variants[] = 'camp';
            } elseif ($keyword === 'テント') {
                $keyword_variants[] = 'てんと';
                $keyword_variants[] = 'tent';
            }
            
            foreach ($keyword_variants as $variant) {
                if (strpos($text, $variant) !== false) {
                    return true;
                }
            }
        }
        
        // Google News検索の場合、検索クエリに引っかかった時点で関連性が高いと判断
        // あまりに厳格だと結果が0件になってしまうので、より緩い条件を設定
        return strlen($text) > 10; // 最低限の内容があれば通す
    }
    
    /**
     * ニュース記事の重複除去
     */
    private function deduplicate_news($news_sources) {
        $unique_news = array();
        $seen_titles = array();
        
        foreach ($news_sources as $news) {
            $title_key = strtolower(preg_replace('/[^a-zA-Z0-9ひらがなカタカナ漢字]/', '', $news['title']));
            
            if (!isset($seen_titles[$title_key])) {
                $seen_titles[$title_key] = true;
                $unique_news[] = $news;
            }
        }
        
        // 日付順にソート（新しい順）
        usort($unique_news, function($a, $b) {
            return strtotime($b['pub_date']) - strtotime($a['pub_date']);
        });
        
        return $unique_news;
    }
    
    /**
     * ニュース記事の内容を取得
     */
    private function fetch_article_content($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress NewsBot)'
        ));
        
        if (is_wp_error($response)) {
            $this->log('warning', "記事内容取得エラー: {$url}");
            return '';
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // HTMLから本文を抽出（簡易版）
        $content = $this->extract_article_text($html);
        
        return $content;
    }
    
    /**
     * HTMLから記事本文を抽出
     */
    private function extract_article_text($html) {
        // HTMLタグを除去
        $text = wp_strip_all_tags($html);
        
        // 余分な空白を削除
        $text = preg_replace('/\s+/', ' ', $text);
        
        // 最初の1000文字程度を取得
        return mb_substr(trim($text), 0, 1000);
    }
    
    /**
     * データベース挿入前の最小限文字クリーニング
     */
    private function clean_text_for_database($text) {
        // nullチェック
        if (empty($text)) {
            return $text;
        }
        
        // バイト順マーク（BOM）のみ除去（文字化け防止のため最小限の処理）
        $text = str_replace("\xEF\xBB\xBF", '', $text);
        
        // NULL文字のみ除去（データベースエラーの最大要因）
        $text = str_replace("\x00", '', $text);
        
        // 問題となる全角文字を半角に変換（データベースエラー対策）
        $text = str_replace(['＋', '－', '｜'], ['+', '-', '|'], $text);
        $text = str_replace(['【', '】'], ['[', ']'], $text);
        $text = str_replace(['（', '）'], ['(', ')'], $text);
        $text = str_replace('＆', '&', $text);
        
        // データベース特有の問題文字を除去
        $text = str_replace(['＃', '％', '＠'], ['#', '%', '@'], $text);
        // 問題のある正規表現を削除 - 英語タイトルが削除される原因を修正
        // $text = preg_replace('/[^\x00-\x7F\x80-\xFF\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FAF]/u', '', $text);
        
        // 制御文字を除去（改行・タブ以外）
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // 4バイトUTF-8文字（絵文字等）をエンコード
        if (function_exists('wp_encode_emoji')) {
            $text = wp_encode_emoji($text);
        } else {
            // 4バイトUTF-8文字を手動で除去（フォールバック）
            $text = preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', '', $text);
        }
        
        // MySQLで問題となる可能性のある文字をサニタイズ
        // タイトルに対してはHTMLサニタイゼーションを避ける（テキストが削除される原因）
        // $text = wp_kses_post($text); // WordPress標準のHTMLサニタイゼーション
        
        return $text;
    }
    
    /**
     * タイトル専用のクリーニング関数（英語テキストを削除しない）
     */
    private function clean_title_for_database($title) {
        // nullチェック
        if (empty($title)) {
            return $title;
        }
        
        // 最小限の処理のみ実行
        // バイト順マーク（BOM）除去
        $title = str_replace("\xEF\xBB\xBF", '', $title);
        
        // NULL文字除去
        $title = str_replace("\x00", '', $title);
        
        // 制御文字を除去（改行・タブ以外）
        $title = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $title);
        
        // 4バイトUTF-8文字（絵文字等）をエンコード
        if (function_exists('wp_encode_emoji')) {
            $title = wp_encode_emoji($title);
        }
        
        // 先頭と末尾の空白を除去
        $title = trim($title);
        
        return $title;
    }
    
    /**
     * URLからドメイン名を抽出
     */
    private function extract_domain_from_url($url) {
        if (empty($url)) {
            return '';
        }
        
        // URLをパース
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return '';
        }
        
        // ホスト名を取得
        $host = $parsed['host'];
        
        // www. プレフィックスを除去
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        
        return $host;
    }
    
    /**
     * 投稿の抜粋を自動生成
     */
    private function generate_post_excerpt($title) {
        // タイトルから20文字程度の簡潔な抜粋を生成
        $title = strip_tags($title); // HTMLタグを除去
        $title = trim($title);
        
        // タイトルが短い場合はそのまま使用
        if (mb_strlen($title) <= 25) {
            return $title;
        }
        
        // タイトルから重要な部分を抽出して短縮
        $patterns = array(
            '/^(.{0,20}[^、。！？]*)[、。！？].*$/u', // 句読点で区切る
            '/^(.{0,20}[^：]*)[：].*$/u',          // コロンで区切る
            '/^(.{0,20}[^「『]*)[「『].*$/u',       // 括弧で区切る
            '/^(.{0,20}[^\s]*)\s.*$/u',           // スペースで区切る
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches) && mb_strlen($matches[1]) >= 10) {
                return trim($matches[1]);
            }
        }
        
        // パターンが見つからない場合は単純に切り取り
        $excerpt = mb_substr($title, 0, 20);
        
        // 文字が途中で切れている場合は最後の文字を「...」にする
        if (mb_strlen($title) > 20) {
            $excerpt = mb_substr($excerpt, 0, 18) . '…';
        }
        
        return $excerpt;
    }
    
    /**
     * 投稿作成後に参考情報源と免責事項を追加
     */
    private function add_reference_sources_and_disclaimer($post_id, $grounding_sources, $settings) {
        $this->log('info', '参考情報源と免責事項の後処理を開始します。投稿ID: ' . $post_id);
        
        // 現在の投稿内容を取得
        $post = get_post($post_id);
        if (!$post) {
            $this->log('error', '投稿ID ' . $post_id . ' が見つかりません');
            return;
        }
        
        $content = $post->post_content;
        $original_length = mb_strlen($content);
        
        // 参考情報源をクリック可能なリンクに変換
        if (!empty($grounding_sources)) {
            // Markdownリンクがある場合はHTMLに変換
            if (strpos($content, '**参考情報源：**') !== false) {
                // Markdownリンクを見つけてHTMLに変換
                $content = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($matches) {
                    $title = $matches[1];
                    $url = $matches[2];
                    return '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($title) . '</a>';
                }, $content);
                
                // **太字**をHTMLに変換
                $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);
                
                $this->log('info', 'Markdownリンクを' . count($grounding_sources) . '件HTMLに変換しました');
            }
            // プレーンテキストの参考情報源をリンクに変換
            else if (strpos($content, '参考情報源:') !== false) {
                // より柔軟な正規表現でHTTPSURLを検出してリンクに変換
                $content = preg_replace_callback('/(<li>)([^<]+?)\s*\((https?:\/\/[^)]*)\)([^<]*)(<\/li>)/', function($matches) {
                    $title = trim($matches[2]);
                    $url = trim($matches[3]);
                    $additional_text = trim($matches[4]);
                    
                    // URLが有効かチェック
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        return $matches[1] . '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                    } else {
                        return $matches[0]; // 無効なURLの場合は元のまま
                    }
                }, $content);
                
                // grounding_sourcesがある場合、正しいURLに強制置換
                if (!empty($grounding_sources)) {
                    $this->log('info', 'Grounding Sourcesを使用してURL修正を開始します: ' . count($grounding_sources) . '件');
                    
                    // grounding_sourcesからタイトルとURLの正確なマッピングを作成
                    $title_url_map = array();
                    $domain_map = array();
                    foreach ($grounding_sources as $index => $source) {
                        if (isset($source['title']) && isset($source['link'])) {
                            $clean_title = trim(strip_tags($source['title']));
                            $correct_url = $source['link'];
                            
                            // タイトルベースのマッピング（より正確）
                            $title_url_map[$clean_title] = $correct_url;
                            
                            // ドメインベースのマッピング（フォールバック）
                            $parsed = parse_url($correct_url);
                            if (isset($parsed['host'])) {
                                $domain = $parsed['host'];
                                $domain = preg_replace('/^www\./', '', $domain); // www.を除去
                                $domain_map[$domain] = $correct_url;
                                $this->log('info', "URL マッピング: タイトル='{$clean_title}' ドメイン={$domain} -> {$correct_url}");
                            }
                        }
                    }
                    
                    // 記事内のURLを正しいGrounding APIのURLに置換
                    // 1. プレーンテキスト形式のURL置換
                    $content = preg_replace_callback('/(<li>)([^<]+?)\s*\((https?:\/\/[^)]*)\)([^<]*)(<\/li>)/', function($matches) use ($title_url_map, $domain_map) {
                        $title = trim($matches[2]);
                        $article_url = trim($matches[3]);
                        $additional_text = trim($matches[4]);
                        
                        // タイトル正規化関数
                        $normalize_title = function($t) {
                            // HTML エンティティをデコード
                            $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
                            // 余分な空白を削除
                            $t = preg_replace('/\s+/', ' ', trim($t));
                            // 特殊文字を削除
                            $t = preg_replace('/[|｜\\\\\/\[\]()（）「」『』【】<>]/', '', $t);
                            return $t;
                        };
                        
                        $normalized_title = $normalize_title($title);
                        
                        // 1. タイトルベースの完全一致チェック（最優先）
                        if (isset($title_url_map[$title])) {
                            $correct_url = $title_url_map[$title];
                            $this->log('info', "タイトル完全一致でURL置換: '{$title}' {$article_url} -> {$correct_url}");
                            return $matches[1] . '<a href="' . esc_url($correct_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                        }
                        
                        // 2. 正規化タイトルでの一致チェック
                        foreach ($title_url_map as $grounding_title => $grounding_url) {
                            $normalized_grounding = $normalize_title($grounding_title);
                            if ($normalized_title === $normalized_grounding) {
                                $this->log('info', "正規化タイトル一致でURL置換: '{$title}' <-> '{$grounding_title}' {$article_url} -> {$grounding_url}");
                                return $matches[1] . '<a href="' . esc_url($grounding_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                            }
                        }
                        
                        // 3. タイトル部分一致チェック（類似度60%以上に緩和）
                        foreach ($title_url_map as $grounding_title => $grounding_url) {
                            if (mb_strlen($normalized_title) >= 8 && mb_strlen($grounding_title) >= 8) {
                                $similarity = 0;
                                similar_text($normalized_title, $normalize_title($grounding_title), $similarity);
                                if ($similarity >= 60) {
                                    $this->log('info', "タイトル類似一致でURL置換: '{$title}' <-> '{$grounding_title}' ({$similarity}%) {$article_url} -> {$grounding_url}");
                                    return $matches[1] . '<a href="' . esc_url($grounding_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                                }
                            }
                        }
                        
                        // 4. ドメインベースの置換（フォールバック）
                        $parsed = parse_url($article_url);
                        if (isset($parsed['host'])) {
                            $article_domain = preg_replace('/^www\./', '', $parsed['host']);
                            
                            // ドメインが一致する正しいURLがあるかチェック
                            if (isset($domain_map[$article_domain])) {
                                $correct_url = $domain_map[$article_domain];
                                $this->log('info', "ドメイン一致でURL置換: {$article_url} -> {$correct_url}");
                                return $matches[1] . '<a href="' . esc_url($correct_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                            }
                        }
                        
                        // 5. マッチしない場合は通常のリンク化
                        if (filter_var($article_url, FILTER_VALIDATE_URL)) {
                            $this->log('info', "Grounding URL一致なし、元URLを使用: {$article_url}");
                            return $matches[1] . '<a href="' . esc_url($article_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                        }
                        
                        return $matches[0];
                    }, $content);
                    
                    // 2. HTMLリンク形式のURL置換
                    $content = preg_replace_callback('/(<li><a href=")([^"]+)("[^>]*>)([^<]+)(<\/a><\/li>)/', function($matches) use ($title_url_map, $domain_map) {
                        $url_start = $matches[1];
                        $current_url = $matches[2];
                        $link_attrs = $matches[3];
                        $title = $matches[4];
                        $url_end = $matches[5];
                        
                        // HTMLエンティティをデコード
                        $decoded_title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
                        
                        // タイトル正規化関数
                        $normalize_title = function($t) {
                            $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
                            $t = preg_replace('/\s+/', ' ', trim($t));
                            $t = preg_replace('/[|｜\\\\\/\[\]()（）「」『』【】<>]/', '', $t);
                            return $t;
                        };
                        
                        // 1. タイトル完全一致チェック
                        if (isset($title_url_map[$decoded_title])) {
                            $correct_url = $title_url_map[$decoded_title];
                            $this->log('info', "HTMLリンク完全一致でURL置換: '{$decoded_title}' {$current_url} -> {$correct_url}");
                            return $url_start . esc_url($correct_url) . $link_attrs . esc_html($decoded_title) . $url_end;
                        }
                        
                        // 2. 正規化タイトルでの一致チェック
                        $normalized_title = $normalize_title($decoded_title);
                        foreach ($title_url_map as $grounding_title => $grounding_url) {
                            $normalized_grounding = $normalize_title($grounding_title);
                            if ($normalized_title === $normalized_grounding) {
                                $this->log('info', "HTMLリンク正規化一致でURL置換: '{$decoded_title}' <-> '{$grounding_title}' {$current_url} -> {$grounding_url}");
                                return $url_start . esc_url($grounding_url) . $link_attrs . esc_html($decoded_title) . $url_end;
                            }
                        }
                        
                        // 3. 類似度チェック（60%以上）
                        foreach ($title_url_map as $grounding_title => $grounding_url) {
                            if (mb_strlen($normalized_title) >= 8 && mb_strlen($grounding_title) >= 8) {
                                $similarity = 0;
                                similar_text($normalized_title, $normalize_title($grounding_title), $similarity);
                                if ($similarity >= 60) {
                                    $this->log('info', "HTMLリンク類似一致でURL置換: '{$decoded_title}' <-> '{$grounding_title}' ({$similarity}%) {$current_url} -> {$grounding_url}");
                                    return $url_start . esc_url($grounding_url) . $link_attrs . esc_html($decoded_title) . $url_end;
                                }
                            }
                        }
                        
                        // マッチしない場合は元のまま
                        return $matches[0];
                    }, $content);
                    
                    // [参考リンク]の場合のフォールバック処理
                    $content = preg_replace_callback('/(<li>)([^<]+?)\s*\(([^)]*)\)([^<]*)(<\/li>)/', function($matches) use ($title_url_map) {
                        $title = trim($matches[2]);
                        $link_text = trim($matches[3]);
                        $additional_text = trim($matches[4]);
                        
                        // 既にリンク化済みの場合はスキップ
                        if (strpos($matches[0], '<a href=') !== false) {
                            return $matches[0];
                        }
                        
                        // [参考リンク]の場合、Grounding URLを優先使用
                        if (strpos($link_text, '参考リンク') !== false || !preg_match('/^https?:\/\//', $link_text)) {
                            // 1. タイトル完全一致チェック
                            if (isset($title_url_map[$title])) {
                                $correct_url = $title_url_map[$title];
                                $this->log('info', "[参考リンク] タイトル完全一致でURL置換: '{$title}' -> {$correct_url}");
                                return $matches[1] . '<a href="' . esc_url($correct_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                            }
                            
                            // 2. タイトル類似性チェック
                            foreach ($title_url_map as $grounding_title => $grounding_url) {
                                if (mb_strlen($title) >= 5 && mb_strlen($grounding_title) >= 5) {
                                    $similarity = 0;
                                    similar_text($title, $grounding_title, $similarity);
                                    if ($similarity > 60) { // 60%以上の類似度
                                        $this->log('info', "[参考リンク] タイトル類似性マッチ: '{$title}' <-> '{$grounding_title}' ({$similarity}%) -> {$grounding_url}");
                                        return $matches[1] . '<a href="' . esc_url($grounding_url) . '" target="_blank">' . esc_html($title) . '</a>' . $additional_text . $matches[5];
                                    }
                                }
                            }
                        }
                        
                        return $matches[0];
                    }, $content);
                }
                
                $this->log('info', 'プレーンテキストの参考情報源をHTMLリンクに変換しました');
            } else {
                $this->log('info', '参考情報源セクションが見つかりません');
            }
        }
        
        // 免責事項を追加
        if ($settings['enable_disclaimer'] ?? true) {
            $disclaimer_text = $settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。';
            
            $disclaimer_html = '<div style="margin-top: 20px;padding: 10px;background-color: #f0f0f0;border-left: 4px solid #ccc;font-size: 14px;color: #666">' . 
                              esc_html($disclaimer_text) . '</div>';
            
            $content .= "\n\n" . $disclaimer_html;
            $this->log('info', '免責事項を追加しました');
        } else {
            $this->log('info', '免責事項は無効に設定されているためスキップします');
        }
        
        // 投稿内容を更新
        $updated_post = array(
            'ID' => $post_id,
            'post_content' => $content
        );
        
        $update_result = wp_update_post($updated_post);
        if (is_wp_error($update_result)) {
            $this->log('error', '投稿更新に失敗: ' . $update_result->get_error_message());
        } else {
            $final_length = mb_strlen($content);
            $added_length = $final_length - $original_length;
            $this->log('info', '投稿内容を更新しました。追加文字数: ' . $added_length . '文字、最終文字数: ' . $final_length . '文字');
        }
    }
    
    /**
     * 記事末尾にGrounding URLリストを追加（比較テスト用）
     */
    private function add_grounding_sources_list($post_id, $grounding_sources, $settings) {
        $this->log('info', 'Grounding URLリストの追加を開始します。投稿ID: ' . $post_id);
        
        // 現在の投稿内容を取得
        $post = get_post($post_id);
        if (!$post) {
            $this->log('error', '投稿ID ' . $post_id . ' が見つかりません');
            return;
        }
        
        $content = $post->post_content;
        
        // Grounding Sourcesがある場合のみ処理
        if (!empty($grounding_sources)) {
            $this->log('info', 'Grounding Sources: ' . count($grounding_sources) . '件');
            
            // Grounding URLリストを作成
            $grounding_list = "\n\n<hr>\n\n<h3>🔗 参考情報源</h3>\n<p><em>この記事は以下のニュースソースを参考に作成されました：</em></p>\n<ul>\n";
            
            foreach ($grounding_sources as $index => $source) {
                // 'link'または'url'キーをチェック（Geminiの構造に対応）
                $url = $source['link'] ?? $source['url'] ?? null;
                if (isset($source['title']) && !empty($url)) {
                    $clean_title = trim(strip_tags($source['title']));
                    $grounding_url = $url;
                    
                    $grounding_list .= '<li><a href="' . esc_url($grounding_url) . '" target="_blank">' . esc_html($clean_title) . '</a></li>' . "\n";
                    $this->log('info', "Grounding URL追加: {$clean_title} -> {$grounding_url}");
                }
            }
            
            $grounding_list .= "</ul>\n";
            
            // 免責事項を追加
            if ($settings['enable_disclaimer'] ?? true) {
                $disclaimer_text = $settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。';
                
                $disclaimer_html = '<div style="margin-top: 20px;padding: 10px;background-color: #f0f0f0;border-left: 4px solid #ccc;font-size: 14px;color: #666">' . 
                                  esc_html($disclaimer_text) . '</div>';
                
                $grounding_list .= "\n" . $disclaimer_html;
                $this->log('info', '免責事項を追加しました');
            }
            
            // コンテンツに追加
            $updated_content = $content . $grounding_list;
            
            // 投稿内容を更新
            $updated_post = array(
                'ID' => $post_id,
                'post_content' => $updated_content
            );
            
            $update_result = wp_update_post($updated_post);
            if (is_wp_error($update_result)) {
                $this->log('error', '投稿更新に失敗: ' . $update_result->get_error_message());
            } else {
                $this->log('info', 'Grounding URLリストを記事末尾に追加しました');
                
                // メタデータとしてGrounding Sourcesも保存
                update_post_meta($post_id, '_grounding_sources', $grounding_sources);
                $this->log('info', 'Grounding Sourcesをメタデータに保存しました');
            }
        } else {
            $this->log('info', 'Grounding Sourcesが空のため、リスト追加をスキップします');
        }
    }
    
    /**
     * 最小限の免責事項追加（構造変更なし）
     */
    private function add_minimal_disclaimer($post_id, $settings) {
        $disclaimer_text = $settings['disclaimer_text'] ?? '注：この記事は、実際のニュースソースを参考にAIによって生成されたものです。最新の正確な情報については、元のニュースソースをご確認ください。';
        
        if (empty($disclaimer_text)) {
            $this->log('info', '免責事項が設定されていないため、追加をスキップします');
            return;
        }
        
        // 既存の投稿を取得
        $post = get_post($post_id);
        if (!$post) {
            $this->log('error', '投稿ID ' . $post_id . ' が見つかりません');
            return;
        }
        
        $content = $post->post_content;
        
        // 既に免責事項が含まれている場合はスキップ
        if (strpos($content, $disclaimer_text) !== false) {
            $this->log('info', '免責事項は既に含まれています');
            return;
        }
        
        // 末尾に免責事項を追加（HTMLタグ付き）
        $disclaimer_html = "\n\n<div class=\"ai-disclaimer\" style=\"margin-top: 2em; padding: 1em; background-color: #f9f9f9; border-left: 4px solid #ddd; font-size: 0.9em; color: #666;\">\n" . 
                          "<p>" . esc_html($disclaimer_text) . "</p>\n" . 
                          "</div>";
        
        $updated_content = $content . $disclaimer_html;
        
        // 投稿を更新
        $updated_post = array(
            'ID' => $post_id,
            'post_content' => $updated_content
        );
        
        $update_result = wp_update_post($updated_post);
        if (is_wp_error($update_result)) {
            $this->log('error', '免責事項追加に失敗: ' . $update_result->get_error_message());
        } else {
            $this->log('info', '最小限の免責事項を末尾に追加しました');
        }
    }
    
    /**
     * ログに記録されたGrounding URLを記事に強制適用
     */
    private function apply_logged_grounding_urls($post_id, $grounding_sources) {
        $this->log('info', 'ログ記録されたGrounding URLの強制適用を開始します。投稿ID: ' . $post_id);
        
        // 現在の投稿内容を取得
        $post = get_post($post_id);
        if (!$post) {
            $this->log('error', '投稿ID ' . $post_id . ' が見つかりません');
            return;
        }
        
        $content = $post->post_content;
        $original_content = $content;
        
        // Grounding Sourcesがある場合のみ処理
        if (!empty($grounding_sources)) {
            $this->log('info', 'Grounding Sources適用対象: ' . count($grounding_sources) . '件');
            
            // ドメイン→Grounding URLマッピングを作成
            $domain_to_grounding_url = array();
            foreach ($grounding_sources as $index => $source) {
                $url = $source['link'] ?? $source['url'] ?? null;
                $title = $source['title'] ?? '';
                
                if (!empty($url) && !empty($title)) {
                    // タイトルからドメインを抽出（例：netsea.jp）
                    $domain = $title;
                    $domain_to_grounding_url[$domain] = $url;
                    $this->log('info', "ドメインマッピング: {$domain} -> {$url}");
                }
            }
            
            // 記事内のすべてのリンクを確認し、対応するGrounding URLがあれば置換
            $content = preg_replace_callback('/<a href="([^"]+)"([^>]*)>([^<]+)<\/a>/', function($matches) use ($domain_to_grounding_url) {
                $current_url = $matches[1];
                $attributes = $matches[2];
                $link_text = $matches[3];
                
                // 現在のURLのドメインを抽出
                $parsed = parse_url($current_url);
                if (isset($parsed['host'])) {
                    $current_domain = preg_replace('/^www\./', '', $parsed['host']);
                    
                    // マッピングに一致するドメインがあるかチェック
                    foreach ($domain_to_grounding_url as $mapped_domain => $grounding_url) {
                        if (strpos($mapped_domain, $current_domain) !== false || strpos($current_domain, $mapped_domain) !== false) {
                            $this->log('info', "URL置換: {$current_url} -> {$grounding_url}");
                            return '<a href="' . esc_url($grounding_url) . '"' . $attributes . '>' . $link_text . '</a>';
                        }
                    }
                }
                
                // 一致しない場合は元のまま
                return $matches[0];
            }, $content);
            
            // 変更があった場合のみ更新
            if ($content !== $original_content) {
                $updated_post = array(
                    'ID' => $post_id,
                    'post_content' => $content
                );
                
                $update_result = wp_update_post($updated_post);
                if (is_wp_error($update_result)) {
                    $this->log('error', 'Grounding URL適用時の投稿更新に失敗: ' . $update_result->get_error_message());
                } else {
                    $this->log('info', 'ログ記録されたGrounding URLを記事に強制適用しました');
                }
            } else {
                $this->log('info', '適用対象のURLが見つからなかったため、変更はありませんでした');
            }
        } else {
            $this->log('info', 'Grounding Sourcesが空のため、Grounding URL適用をスキップします');
        }
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