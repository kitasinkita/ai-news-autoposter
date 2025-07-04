/**
 * AI News AutoPoster - Admin JavaScript
 */

(function($) {
    'use strict';

    // プラグイン初期化
    const AINewsAutoPoster = {
        
        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.checkStatus();
            this.createNotificationContainer();
        },
        
        bindEvents: function() {
            // API接続テストボタン
            $(document).on('click', '#test-api-connection', this.testApiConnection);
            
            // テスト記事生成ボタン
            $(document).on('click', '#generate-test-article', this.generateTestArticle);
            
            // 設定保存時の検証
            $(document).on('submit', '#ai-news-settings-form', this.validateSettings);
            
            // ログクリアボタン
            $(document).on('click', '#clear-logs', this.clearLogs);
            
            // 手動記事生成（管理バー）
            $(document).on('click', '#wp-admin-bar-ai-news-generate a', this.generateFromAdminBar);
            
            // 自動更新トグル
            $(document).on('change', '#auto-publish-toggle', this.toggleAutoPublish);
            
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
        },
        
        createNotificationContainer: function() {
            if ($('#ai-news-notifications').length === 0) {
                $('<div id="ai-news-notifications" class="ai-news-notifications"></div>')
                    .appendTo('body');
            }
        },
        
        testApiConnection: function(e) {
            e.preventDefault();
            
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
                           .removeClass('ai-news-loading');
                }
            });
        },
        
        generateTestArticle: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // 確認ダイアログ
            if (!confirm('テスト記事を生成しますか？この処理には時間がかかる場合があります。')) {
                return;
            }
            
            // プログレスバー表示
            AINewsAutoPoster.showProgress('記事を生成中...', 0);
            
            // ボタン状態変更
            $button.prop('disabled', true)
                   .html('<span class="ai-news-spinner"></span> 生成中...')
                   .addClass('ai-news-loading');
            
            // プログレス更新シミュレーション
            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                AINewsAutoPoster.updateProgress(progress, '記事を生成中...');
            }, 1000);
            
            // Ajax リクエスト
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_test_article',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                timeout: 120000, // 2分
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
                           .removeClass('ai-news-loading');
                    
                    // プログレスバー非表示
                    setTimeout(function() {
                        AINewsAutoPoster.hideProgress();
                    }, 2000);
                }
            });
        },
        
        validateSettings: function(e) {
            const apiKey = $('#claude_api_key').val().trim();
            const scheduleTime = $('#schedule_time').val();
            const maxPosts = parseInt($('#max_posts_per_day').val());
            
            let errors = [];
            
            // API キー検証
            if (!apiKey) {
                errors.push('Claude API キーは必須です。');
            } else if (!apiKey.startsWith('sk-ant-')) {
                errors.push('Claude API キーの形式が正しくありません。');
            }
            
            // 投稿数検証
            if (isNaN(maxPosts) || maxPosts < 1 || maxPosts > 24) {
                errors.push('1日の最大投稿数は1-24の範囲で設定してください。');
            }
            
            // 時刻検証
            if (!scheduleTime) {
                errors.push('投稿時刻を設定してください。');
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
                       .text('有効');
                AINewsAutoPoster.showNotification('success', '自動投稿を有効にしました。');
            } else {
                $status.removeClass('ai-news-status-enabled')
                       .addClass('ai-news-status-disabled')
                       .text('無効');
                AINewsAutoPoster.showNotification('warning', '自動投稿を無効にしました。');
            }
        },
        
        updateStats: function() {
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
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
                                                   .text('有効');
                        } else {
                            $('#auto-publish-status').removeClass('ai-news-status-enabled')
                                                   .addClass('ai-news-status-disabled')
                                                   .text('無効');
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
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('すべてのログを削除しますか？')) {
                return;
            }
            
            $.ajax({
                url: ai_news_autoposter_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'clear_logs',
                    nonce: ai_news_autoposter_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#log-entries').empty().append('<tr><td colspan="3">ログがありません。</td></tr>');
                        AINewsAutoPoster.showNotification('success', 'ログを削除しました。');
                    }
                }
            });
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
                    $({ value: currentValue }).animate({ value: targetValue }, {
                        duration: 1000,
                        easing: 'swing',
                        step: function() {
                            $stat.text(Math.round(this.value));
                        },
                        complete: function() {
                            $stat.text(targetValue);
                        }
                    });
                }
            });
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
        }
    };

    // DOM Ready
    $(document).ready(function() {
        AINewsAutoPoster.init();
    });

    // グローバル公開
    window.AINewsAutoPoster = AINewsAutoPoster;

})(jQuery);