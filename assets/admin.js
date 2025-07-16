/**
 * AI News AutoPoster - Admin JavaScript
 */

(function($) {
    'use strict';

    // プラグイン初期化
    const AINewsAutoPoster = {
        
        init: function() {
            console.log('AINewsAutoPoster init開始');
            this.bindEvents();
            this.initializeComponents();
            this.checkStatus();
            this.createNotificationContainer();
            console.log('AINewsAutoPoster init完了');
            
            // ボタンの存在確認
            console.log('API接続テストボタン:', $('#test-api-connection').length);
            console.log('テスト記事生成ボタン:', $('#generate-test-article').length);
            console.log('今すぐ投稿ボタン:', $('#manual-post-now').length);
        },
        
        bindEvents: function() {
            // API接続テストボタン
            $(document).on('click', '#test-api-connection', this.testApiConnection);
            
            // テスト記事生成ボタン
            $(document).on('click', '#generate-test-article', this.generateTestArticle);
            
            // 今すぐ投稿ボタン
            $(document).on('click', '#manual-post-now', this.manualPostNow);
            
            // Cron実行テストボタン
            $(document).on('click', '#test-cron-execution', this.testCronExecution);
            
            // サーバー情報表示ボタン
            $(document).on('click', '#show-server-info', this.showServerInfo);
            
            // 設定保存時の検証
            $(document).on('submit', '#ai-news-settings-form', this.validateSettings);
            
            // ログクリアボタン
            $(document).on('click', '#clear-logs', this.clearLogs);
            
            // ログコピーボタン
            $(document).on('click', '#copy-logs', this.copyAllLogs);
            $(document).on('click', '#copy-latest-logs', this.copyLatestLogs);
            
            // 手動記事生成（管理バー）
            $(document).on('click', '#wp-admin-bar-ai-news-generate a', this.generateFromAdminBar);
            
            // 自動更新トグル
            $(document).on('change', '#auto-publish-toggle', this.toggleAutoPublish);
            
            // 画像生成方式の変更
            $(document).on('change', 'select[name="image_generation_type"]', this.toggleImageSettings);
            
            // タブ切り替え
            $(document).on('click', '.ai-news-tab-button', this.switchTab);
            
            // プロンプトモード切り替え
            $(document).on('change', 'input[name="prompt_mode"]', this.togglePromptMode);
            
            // リアルタイム統計更新
            setInterval(this.updateStats, 30000); // 30秒ごと
            
            // フォーム入力の自動保存
            $(document).on('input', '.ai-news-autosave', this.autoSave);
            
            // 通知クリックで閉じる
            $(document).on('click', '.ai-news-notification', function() {
                $(this).slideUp(300, function() {
                    $(this).remove();
                });
            });
        },
        
        initializeComponents: function() {
            // ツールチップ初期化
            this.initTooltips();
            
            // プログレスバー初期化
            this.initProgressBars();
            
            // ダッシュボード統計更新
            this.updateDashboardStats();
            
            // タブの初期化
            this.initializeTabs();
            
            // プロンプトモードの初期化
            this.initializePromptMode();
        },
        
        createNotificationContainer: function() {
            if ($('#ai-news-notifications').length === 0) {
                $('<div id="ai-news-notifications" class="ai-news-notifications"></div>')
                    .appendTo('body');
            }
        },
        
        testApiConnection: function(e) {
            e.preventDefault();
            console.log('API接続テストボタンがクリックされました');
            
            const $button = $(this);
            const originalText = $button.text();
            
            // ボタン状態変更
            $button.prop('disabled', true)
                   .html('<span class="ai-news-spinner"></span> テスト中...')
                   .addClass('ai-news-loading');
            
            // Ajax リクエスト
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // JSONレスポンスを明示的に指定
                data: {
                    action: 'test_api_connection',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        AINewsAutoPoster.showNotification('success', 'API接続が正常に動作しています！');
                        $('#api-status').removeClass('ai-news-status-disabled ai-news-status-warning')
                                       .addClass('ai-news-status-enabled')
                                       .text('接続済み');
                    } else {
                        AINewsAutoPoster.showNotification('error', 'API接続に失敗しました: ' + response.data);
                        $('#api-status').removeClass('ai-news-status-enabled ai-news-status-warning')
                                       .addClass('ai-news-status-disabled')
                                       .text('接続失敗');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'ネットワークエラーが発生しました。';
                    
                    if (status === 'timeout') {
                        errorMessage = 'リクエストがタイムアウトしました。';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    }
                    
                    AINewsAutoPoster.showNotification('error', errorMessage);
                    $('#api-status').removeClass('ai-news-status-enabled ai-news-status-warning')
                                   .addClass('ai-news-status-disabled')
                                   .text('接続エラー');
                },
                complete: function() {
                    // ボタン状態復元
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-news-loading')
                           .data('processing', false); // 処理中フラグをクリア
                }
            });
        },
        
        generateTestArticle: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // 確認ダイアログ
            if (!confirm('下書き記事を生成しますか？この処理には時間がかかる場合があります。')) {
                return;
            }
            
            // プログレスバー表示
            const initialMessage = 'AIで記事を生成中...';
            AINewsAutoPoster.showProgress(initialMessage, 0);
            
            // ボタン状態変更
            $button.prop('disabled', true)
                   .html('<span class="ai-news-spinner"></span> 生成中...')
                   .addClass('ai-news-loading');
            
            // プログレス更新シミュレーション
            let progress = 0;
            const progressMessage = '記事を生成中...';
            
            const progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                AINewsAutoPoster.updateProgress(progress, progressMessage);
            }, 1000);
            
            // Ajax リクエスト
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // JSONレスポンスを明示的に指定
                data: {
                    action: 'generate_test_article',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                timeout: 360000, // 6分
                success: function(response) {
                    clearInterval(progressInterval);
                    AINewsAutoPoster.updateProgress(100, '完了！');
                    
                    if (response.success) {
                        AINewsAutoPoster.showNotification('success', 
                            '記事を正常に生成しました！ <a href="' + response.data.edit_url + '" target="_blank">編集画面で確認</a>');
                        
                        // 統計更新
                        AINewsAutoPoster.updateStats();
                        
                    } else {
                        AINewsAutoPoster.showNotification('error', '記事生成に失敗しました: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    
                    let errorMessage = 'ネットワークエラーが発生しました。';
                    if (status === 'timeout') {
                        errorMessage = '処理がタイムアウトしました。APIの応答が遅い可能性があります。';
                    }
                    
                    AINewsAutoPoster.showNotification('error', errorMessage);
                },
                complete: function() {
                    // ボタン状態復元
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-news-loading')
                           .data('processing', false); // 処理中フラグをクリア
                    
                    // プログレスバー非表示
                    setTimeout(function() {
                        AINewsAutoPoster.hideProgress();
                    }, 2000);
                }
            });
        },
        
        validateSettings: function(e) {
            const selectedModel = $('select[name="claude_model"]').val();
            const claudeApiKey = $('#claude_api_key').val().trim();
            const geminiApiKey = $('#gemini_api_key').val().trim();
            const maxPosts = parseInt($('#max_posts_per_day').val());
            
            let errors = [];
            
            // API キー検証（選択モデルに応じて）
            if (selectedModel && selectedModel.startsWith('claude-')) {
                // Claudeモデル選択時はClaude APIキーが必要
                if (!claudeApiKey) {
                    errors.push('Claude API キーは必須です。');
                } else if (!claudeApiKey.startsWith('sk-ant-')) {
                    errors.push('Claude API キーの形式が正しくありません。');
                }
            } else if (selectedModel && selectedModel.startsWith('gemini-')) {
                // Geminiモデル選択時はGemini APIキーが必要
                if (!geminiApiKey) {
                    errors.push('Gemini API キーは必須です。');
                }
            }
            
            // 投稿数検証
            if (isNaN(maxPosts) || maxPosts < 1 || maxPosts > 5) {
                errors.push('1日の最大投稿数は1-5の範囲で設定してください。');
            }
            
            // 開始時刻検証
            const scheduleTime = $('#schedule_time').val();
            if (!scheduleTime) {
                errors.push('投稿開始時刻を設定してください。');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                AINewsAutoPoster.showNotification('error', 'エラー:<br>' + errors.join('<br>'));
                return false;
            }
            
            // 保存確認
            AINewsAutoPoster.showNotification('info', '設定を保存中...');
            
            return true;
        },
        
        toggleAutoPublish: function() {
            const isEnabled = $(this).is(':checked');
            const $status = $('#auto-publish-status');
            
            if (isEnabled) {
                $status.removeClass('ai-news-status-disabled')
                       .addClass('ai-news-status-enabled')
                       .text('自動投稿有効');
                AINewsAutoPoster.showNotification('success', '自動投稿を有効にしました。');
            } else {
                $status.removeClass('ai-news-status-enabled')
                       .addClass('ai-news-status-disabled')
                       .text('自動投稿無効');
                AINewsAutoPoster.showNotification('warning', '自動投稿を無効にしました。');
            }
        },
        
        updateStats: function() {
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // JSONレスポンスを明示的に指定
                data: {
                    action: 'get_stats',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        
                        // 統計更新
                        $('#posts-today-count').text(stats.posts_today || 0);
                        $('#total-posts-count').text(stats.total_posts || 0);
                        $('#last-run-time').text(stats.last_run || 'まだ実行されていません');
                        $('#next-run-time').text(stats.next_run || '未設定');
                        
                        // ステータス更新
                        if (stats.auto_publish_enabled) {
                            $('#auto-publish-status').removeClass('ai-news-status-disabled')
                                                   .addClass('ai-news-status-enabled')
                                                   .text('自動投稿有効');
                        } else {
                            $('#auto-publish-status').removeClass('ai-news-status-enabled')
                                                   .addClass('ai-news-status-disabled')
                                                   .text('自動投稿無効');
                        }
                    }
                }
            });
        },
        
        checkStatus: function() {
            // APIキーの存在確認
            const apiKey = $('#claude_api_key').val();
            if (!apiKey) {
                AINewsAutoPoster.showNotification('warning', 'Claude API キーが設定されていません。設定画面で入力してください。');
            }
            
            // 初回統計取得
            this.updateStats();
            
            // 画像生成設定の初期化
            this.initImageSettings();
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('すべてのログを削除しますか？')) {
                return;
            }
            
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // JSONレスポンスを明示的に指定
                data: {
                    action: 'clear_logs',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#log-entries').empty().append('<tr><td colspan="3">ログがありません。</td></tr>');
                        $('#log-data-all, #log-data-latest').val(''); // コピー用データもクリア
                        AINewsAutoPoster.showNotification('success', 'ログを削除しました。');
                    }
                }
            });
        },
        
        copyAllLogs: function(e) {
            e.preventDefault();
            
            const logData = $('#log-data-all').val();
            
            if (!logData.trim()) {
                AINewsAutoPoster.showNotification('warning', 'コピーするログがありません。');
                return;
            }
            
            AINewsAutoPoster.copyToClipboard(logData, '全ログ');
        },
        
        copyLatestLogs: function(e) {
            e.preventDefault();
            
            const logData = $('#log-data-latest').val();
            
            if (!logData.trim()) {
                AINewsAutoPoster.showNotification('warning', '最新投稿のログがありません。');
                return;
            }
            
            AINewsAutoPoster.copyToClipboard(logData, '最新投稿ログ');
        },
        
        copyToClipboard: function(text, description) {
            // モダンブラウザ対応
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    AINewsAutoPoster.showNotification('success', description + 'をクリップボードにコピーしました。');
                }).catch(function(err) {
                    AINewsAutoPoster.fallbackCopy(text, description);
                });
            } else {
                // フォールバック方式
                AINewsAutoPoster.fallbackCopy(text, description);
            }
        },
        
        fallbackCopy: function(text, description) {
            // 一時的なテキストエリアを作成
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            
            try {
                textArea.focus();
                textArea.select();
                const successful = document.execCommand('copy');
                
                if (successful) {
                    AINewsAutoPoster.showNotification('success', description + 'をクリップボードにコピーしました。');
                } else {
                    AINewsAutoPoster.showNotification('error', 'コピーに失敗しました。手動で選択してコピーしてください。');
                }
            } catch (err) {
                AINewsAutoPoster.showNotification('error', 'コピー機能がサポートされていません。');
            } finally {
                document.body.removeChild(textArea);
            }
        },
        
        autoSave: function() {
            const $field = $(this);
            const fieldName = $field.attr('name');
            const fieldValue = $field.val();
            
            // デバウンス処理
            clearTimeout($field.data('autosave-timer'));
            
            $field.data('autosave-timer', setTimeout(function() {
                $.ajax({
                    url: ai_news_autoposter_ajax.ajax_url,
                    type: 'POST',
                    dataType: 'json', // JSONレスポンスを明示的に指定
                    data: {
                        action: 'autosave_setting',
                        nonce: ai_news_autoposter_ajax.nonce,
                        field: fieldName,
                        value: fieldValue
                    },
                    success: function(response) {
                        if (response.success) {
                            $field.addClass('ai-news-autosaved');
                            setTimeout(function() {
                                $field.removeClass('ai-news-autosaved');
                            }, 2000);
                        }
                    }
                });
            }, 1000));
        },
        
        showNotification: function(type, message) {
            const $notification = $('<div class="ai-news-notification ai-news-notification-' + type + '">')
                .html('<span class="ai-news-notification-text">' + message + '</span>')
                .hide()
                .appendTo('#ai-news-notifications');
            
            $notification.slideDown(300);
            
            // 自動消去（エラー以外）
            if (type !== 'error') {
                setTimeout(function() {
                    $notification.slideUp(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        showProgress: function(message, progress) {
            if ($('#ai-news-progress-container').length === 0) {
                const $progressContainer = $('<div id="ai-news-progress-container" class="ai-news-progress-container">')
                    .append('<div class="ai-news-progress-message"></div>')
                    .append('<div class="ai-news-progress"><div class="ai-news-progress-bar"><span class="ai-news-progress-text">0%</span></div></div>')
                    .hide()
                    .appendTo('#ai-news-notifications');
                
                $progressContainer.fadeIn(300);
            }
            
            this.updateProgress(progress, message);
        },
        
        updateProgress: function(progress, message) {
            $('#ai-news-progress-container .ai-news-progress-message').text(message);
            $('#ai-news-progress-container .ai-news-progress-bar').css('width', progress + '%');
            $('#ai-news-progress-container .ai-news-progress-text').text(Math.round(progress) + '%');
        },
        
        hideProgress: function() {
            $('#ai-news-progress-container').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        initTooltips: function() {
            $('[data-tooltip]').hover(
                function() {
                    $(this).addClass('ai-news-tooltip-active');
                },
                function() {
                    $(this).removeClass('ai-news-tooltip-active');
                }
            );
        },
        
        initProgressBars: function() {
            $('.ai-news-progress-bar').each(function() {
                const $bar = $(this);
                const targetWidth = $bar.data('progress') || 0;
                
                $bar.animate({
                    width: targetWidth + '%'
                }, 1000);
            });
        },
        
        updateDashboardStats: function() {
            // アニメーション付き数値更新
            $('.ai-news-stat-value').each(function() {
                const $stat = $(this);
                const targetValue = parseInt($stat.data('value')) || 0;
                const currentValue = parseInt($stat.text()) || 0;
                
                if (targetValue !== currentValue) {
                    // CSP対応: animate()の代わりにsetIntervalを使用
                    let current = currentValue;
                    const increment = (targetValue - currentValue) / 20;
                    const updateStat = setInterval(function() {
                        current += increment;
                        if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                            $stat.text(targetValue);
                            clearInterval(updateStat);
                        } else {
                            $stat.text(Math.round(current));
                        }
                    }, 50);
                }
            });
        },
        
        manualPostNow: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // 既に処理中の場合は無視
            if ($button.data('processing')) {
                console.log('Already processing, ignoring click');
                return;
            }
            
            // 確認ダイアログ
            if (!confirm('記事を今すぐ投稿しますか？この処理には時間がかかる場合があります。')) {
                return;
            }
            
            // 処理中フラグを設定
            $button.data('processing', true);
            
            // プログレスバー表示
            const initialMessage = 'AIで記事を生成・投稿中...';
            AINewsAutoPoster.showProgress(initialMessage, 0);
            
            // ボタン状態変更
            $button.prop('disabled', true)
                   .html('<span class="ai-news-spinner"></span> 投稿中...')
                   .addClass('ai-news-loading');
            
            // プログレス更新シミュレーション
            let progress = 0;
            let progressMessages = [
                '記事を生成・投稿中...',
                'AIで記事作成中...',
                '高品質な記事を生成中...',
                '記事の最終調整中...',
                '投稿準備中...'
            ];
            let messageIndex = 0;
            
            const progressInterval = setInterval(function() {
                progress += Math.random() * 8;
                if (progress > 85) progress = 85;
                
                if (progress > messageIndex * 20 && messageIndex < progressMessages.length - 1) {
                    messageIndex++;
                }
                
                AINewsAutoPoster.updateProgress(progress, progressMessages[messageIndex]);
            }, 2000);
            
            // Ajax リクエスト
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // JSONレスポンスを明示的に指定
                data: {
                    action: 'manual_post_now',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                timeout: 600000, // 10分（PHPの実行時間に合わせる）
                success: function(response) {
                    clearInterval(progressInterval);
                    AINewsAutoPoster.updateProgress(100, '投稿完了！');
                    
                    console.log('AJAX Response:', response);
                    console.log('Response type:', typeof response);
                    console.log('Response.success:', response.success);
                    console.log('Response.data:', response.data);
                    
                    if (response.success) {
                        AINewsAutoPoster.showNotification('success', 
                            '記事を正常に投稿しました！ <a href="' + response.data.edit_url + '" target="_blank">編集画面で確認</a> | <a href="' + response.data.view_url + '" target="_blank">表示</a>');
                        
                        // 統計更新
                        AINewsAutoPoster.updateStats();
                        
                    } else {
                        console.log('Response data:', response.data);
                        console.log('Full response object:', JSON.stringify(response, null, 2));
                        const errorMessage = response.data || 'エラーが発生しました';
                        AINewsAutoPoster.showNotification('error', '記事投稿に失敗しました: ' + errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    
                    console.log('AJAX Error - Status:', status, 'Error:', error);
                    console.log('XHR Response:', xhr.responseText);
                    
                    let errorMessage = 'ネットワークエラーが発生しました。';
                    
                    // JSONレスポンスの解析を試行
                    try {
                        if (xhr.responseText) {
                            let response = JSON.parse(xhr.responseText);
                            if (response && response.success === true) {
                                // 実際は成功だが、タイムアウト等で error になった場合
                                console.log('Success response in error callback:', response);
                                AINewsAutoPoster.showNotification('success', 
                                    '記事を正常に投稿しました！ <a href="' + response.data.edit_url + '" target="_blank">編集画面で確認</a> | <a href="' + response.data.view_url + '" target="_blank">表示</a>');
                                AINewsAutoPoster.updateStats();
                                return;
                            } else if (response && response.data) {
                                errorMessage = '記事投稿に失敗しました: ' + response.data;
                            }
                        }
                    } catch (e) {
                        console.log('JSON parse failed:', e);
                    }
                    
                    if (status === 'timeout') {
                        errorMessage = '処理がタイムアウトしました。記事が作成されている可能性があります。管理画面で確認してください。';
                    }
                    
                    AINewsAutoPoster.showNotification('error', errorMessage);
                },
                complete: function() {
                    // ボタン状態復元
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-news-loading')
                           .data('processing', false); // 処理中フラグをクリア
                    
                    // プログレスバー非表示
                    setTimeout(function() {
                        AINewsAutoPoster.hideProgress();
                    }, 2000);
                }
            });
        },
        
        testCronExecution: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // 確認ダイアログ
            if (!confirm('Cron実行のテストを行いますか？実際の投稿処理が実行されます。')) {
                return;
            }
            
            // ボタン状態変更
            $button.prop('disabled', true)
                   .html('<span class="ai-news-spinner"></span> テスト中...')
                   .addClass('ai-news-loading');
            
            // Ajax リクエスト
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // JSONレスポンスを明示的に指定
                data: {
                    action: 'test_cron_execution',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                timeout: 360000, // 6分
                success: function(response) {
                    if (response.success) {
                        AINewsAutoPoster.showNotification('success', 'Cron実行テストが完了しました。ログを確認してください。');
                        
                        // 統計更新
                        AINewsAutoPoster.updateStats();
                        
                    } else {
                        AINewsAutoPoster.showNotification('error', 'Cron実行テストに失敗しました: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'ネットワークエラーが発生しました。';
                    if (status === 'timeout') {
                        errorMessage = '処理がタイムアウトしました。';
                    }
                    
                    AINewsAutoPoster.showNotification('error', errorMessage);
                },
                complete: function() {
                    // ボタン状態復元
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-news-loading')
                           .data('processing', false); // 処理中フラグをクリア
                }
            });
        },
        
        showServerInfo: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // ボタン状態変更
            $button.prop('disabled', true)
                   .html('<span class="ai-news-spinner"></span> 取得中...')
                   .addClass('ai-news-loading');
            
            // Ajax リクエスト
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_server_info',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        // サーバー情報を整理して表示
                        let infoHtml = '<div class="ai-news-server-info">';
                        infoHtml += '<h4>PHP・サーバー情報</h4>';
                        infoHtml += '<table class="ai-news-info-table">';
                        infoHtml += '<tr><td>PHP Version</td><td>' + data.php_version + '</td></tr>';
                        infoHtml += '<tr><td>Memory Limit</td><td>' + data.memory_limit + '</td></tr>';
                        infoHtml += '<tr><td>Max Execution Time</td><td>' + data.max_execution_time + 's</td></tr>';
                        infoHtml += '<tr><td>Max Input Time</td><td>' + data.max_input_time + 's</td></tr>';
                        infoHtml += '<tr><td>Post Max Size</td><td>' + data.post_max_size + '</td></tr>';
                        infoHtml += '<tr><td>Upload Max Filesize</td><td>' + data.upload_max_filesize + '</td></tr>';
                        infoHtml += '<tr><td>Default Socket Timeout</td><td>' + data.default_socket_timeout + 's</td></tr>';
                        infoHtml += '<tr><td>現在のメモリ使用量</td><td>' + data.memory_usage + '</td></tr>';
                        infoHtml += '<tr><td>ピークメモリ使用量</td><td>' + data.memory_peak_usage + '</td></tr>';
                        infoHtml += '</table>';
                        
                        infoHtml += '<h4>環境情報</h4>';
                        infoHtml += '<table class="ai-news-info-table">';
                        infoHtml += '<tr><td>Server Software</td><td>' + data.server_software + '</td></tr>';
                        infoHtml += '<tr><td>PHP SAPI</td><td>' + data.php_sapi + '</td></tr>';
                        infoHtml += '<tr><td>WordPress Version</td><td>' + data.wordpress_version + '</td></tr>';
                        infoHtml += '<tr><td>MySQL Version</td><td>' + data.mysql_version + '</td></tr>';
                        infoHtml += '<tr><td>cURL Version</td><td>' + data.curl_version + '</td></tr>';
                        infoHtml += '<tr><td>OpenSSL Version</td><td>' + data.openssl_version + '</td></tr>';
                        infoHtml += '<tr><td>Timezone</td><td>' + data.timezone + '</td></tr>';
                        infoHtml += '</table>';
                        
                        if (data.disk_space && !data.disk_space.error) {
                            infoHtml += '<h4>ディスク容量</h4>';
                            infoHtml += '<table class="ai-news-info-table">';
                            infoHtml += '<tr><td>合計容量</td><td>' + data.disk_space.total + '</td></tr>';
                            infoHtml += '<tr><td>使用容量</td><td>' + data.disk_space.used + '</td></tr>';
                            infoHtml += '<tr><td>空き容量</td><td>' + data.disk_space.free + '</td></tr>';
                            infoHtml += '<tr><td>使用率</td><td>' + data.disk_space.usage_percent + '</td></tr>';
                            infoHtml += '</table>';
                        }
                        
                        infoHtml += '</div>';
                        
                        // 結果を表示
                        $('#test-results').html(infoHtml).show();
                        
                        AINewsAutoPoster.showNotification('success', 'サーバー情報を取得しました。');
                        
                    } else {
                        AINewsAutoPoster.showNotification('error', 'サーバー情報の取得に失敗しました: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'ネットワークエラーが発生しました。';
                    if (status === 'timeout') {
                        errorMessage = '処理がタイムアウトしました。';
                    }
                    
                    AINewsAutoPoster.showNotification('error', errorMessage);
                },
                complete: function() {
                    // ボタン状態復元
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-news-loading');
                }
            });
        },
        
        initImageSettings: function() {
            // ページ読み込み時の画像生成設定を初期化
            const currentType = $('select[name="image_generation_type"]').val();
            this.showImageSettingsForType(currentType);
        },
        
        showImageSettingsForType: function(type) {
            // すべての API キー行を非表示
            $('#dalle-api-key-row, #unsplash-access-key-row').hide();
            
            // 選択された方式に応じて表示
            if (type === 'dalle') {
                $('#dalle-api-key-row').show();
            } else if (type === 'unsplash') {
                $('#unsplash-access-key-row').show();
            }
        },
        
        toggleImageSettings: function() {
            const selectedType = $(this).val();
            AINewsAutoPoster.showImageSettingsForType(selectedType);
        },
        
        generateFromAdminBar: function(e) {
            e.preventDefault();
            
            if (!confirm('記事を生成しますか？')) {
                return;
            }
            
            // REST API呼び出し
            fetch(wpApiSettings.root + 'ai-news-autoposter/v1/generate', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('記事を生成しました！');
                    if (data.edit_url) {
                        window.open(data.edit_url, '_blank');
                    }
                } else {
                    alert('エラー: ' + (data.message || '不明なエラー'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('ネットワークエラーが発生しました。');
            });
        },
        
        switchTab: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const tabId = $button.data('tab');
            
            // アクティブクラスの切り替え
            $('.ai-news-tab-button').removeClass('active');
            $button.addClass('active');
            
            // タブコンテンツの切り替え
            $('.ai-news-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
            
            // タブの状態を保存（オプション）
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('ai_news_active_tab', tabId);
            }
        },
        
        togglePromptMode: function() {
            const mode = $('input[name="prompt_mode"]:checked').val();
            
            if (mode === 'free') {
                // フリープロンプトモードの場合、定型プロンプト設定を非表示
                $('.ai-news-normal-settings').hide();
                $('.ai-news-free-prompt-container').show();
            } else {
                // 定型プロンプトモードの場合
                $('.ai-news-normal-settings').show();
                $('.ai-news-free-prompt-container').hide();
            }
        },
        
        initializeTabs: function() {
            // 保存されたタブの状態を復元
            if (typeof(Storage) !== "undefined") {
                const savedTab = localStorage.getItem('ai_news_active_tab');
                if (savedTab && $('#' + savedTab).length) {
                    $('.ai-news-tab-button[data-tab="' + savedTab + '"]').click();
                } else {
                    // デフォルトで最初のタブをアクティブに
                    $('.ai-news-tab-button:first').addClass('active');
                    $('.ai-news-tab-content:first').addClass('active');
                }
            } else {
                // LocalStorageが使えない場合
                $('.ai-news-tab-button:first').addClass('active');
                $('.ai-news-tab-content:first').addClass('active');
            }
        },
        
        initializePromptMode: function() {
            // 初期状態でプロンプトモードの表示を設定
            const mode = $('input[name="prompt_mode"]:checked').val();
            if (mode) {
                this.togglePromptMode();
            }
        },
        
    };

    // DOM Ready
    $(document).ready(function() {
        console.log('AI News AutoPoster: DOM Ready');
        console.log('Ajax URL:', ai_news_autoposter_ajax.ajax_url);
        console.log('Nonce:', ai_news_autoposter_ajax.nonce);
        AINewsAutoPoster.init();
        console.log('AI News AutoPoster: Initialized');
    });

    // グローバル公開
    window.AINewsAutoPoster = AINewsAutoPoster;

})(jQuery);