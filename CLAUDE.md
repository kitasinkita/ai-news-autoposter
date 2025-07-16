# AI News AutoPoster - 開発状況メモ

## プロジェクト概要
WordPress用のAIニュース自動投稿プラグインの開発・改良

## 現在のバージョン
- **v2.6.1** (2025年07月16日)
- Git リポジトリ: https://github.com/kitasinkita/ai-news-autoposter

## 最近の主要な改良点

### 1. 設定画面の3タブ構成への再設計 (v2.6.1)
- 設定画面を3タブ構成に再構成（共通設定・定型プロンプト設定・フリープロンプト設定）
- AIモデル・スケジュール設定を共通設定タブに移動
- 定型プロンプト固有設定（キーワード、記事生成設定、ニュースソース）を定型プロンプトタブに配置
- より直感的で論理的な設定項目の分類を実現

### 2. システム安定性の向上 (v2.6.1)
- Unsplash画像生成の信頼性向上（3回連続テスト成功率100%確認）
- 参考情報源表示の最適化（単一リスト形式で安定した表示）
- エラーハンドリング改善による安定した記事生成プロセス

### 3. フリープロンプトモードの実装 (v2.6.0)
- 設定画面にタブ機能を追加（通常設定/フリープロンプト設定）
- プロンプトモード選択機能（通常モード/フリープロンプトモード）
- フリープロンプトモードでは設定値を無視して自由なプロンプトで記事生成可能
- タイトルは最初のH1タグまたは最初の行から自動抽出

### 2. 動的文字数計算の実装 (v2.5.20)
- 問題: ハードコードされた文字数制限（1,700文字、600文字）
- 解決: 管理画面の設定に基づいて動的に計算
- 計算式:
  - `min_chars_per_article = ceil(total_word_count / article_count)`
  - `min_chars_per_section = ceil(min_chars_per_article / 3)`

### 2. 実際のプロンプト表示機能 (v2.5.21)
- 設定画面に「実際に送信されるプロンプトを表示」ボタンを追加
- プレースホルダー置換後の実際のプロンプトを確認可能
- 緑色（実際）とグレー（テンプレート）の色分け表示

### 3. プロンプト説明文の改善
- デフォルトプロンプトの特徴を明確に説明
- 新しいプレースホルダー `{セクション文字数}` を追加
- 動的計算の仕組みを説明

## 現在の設定状況
- 記事文字数: 3,000文字
- 記事数: 3個
- 1記事あたり: 1,000文字
- セクションあたり: 334文字

## 動作確認済み機能
- 動的文字数計算が正常に動作
- 実際の投稿で6,602文字生成（220.1%達成率）
- プロンプト表示機能が正常に動作

## 技術的な詳細

### 主要ファイル
- `ai-news-autoposter.php` - メインプラグインファイル

### 重要な関数
- `generate_dynamic_prompt()` - 動的プロンプト生成
- `get_actual_prompt()` - AJAX用実際プロンプト取得
- `build_gemini_simple_prompt_template()` - デフォルトテンプレート生成

### 開発環境
- Docker WordPress環境
- ローカル: http://localhost:8090
- 管理画面: admin/admin
- 専用ディレクトリ: `/Users/sk/Documents/dev/ai-news-autoposter-dev/`

## 今後の課題・改善点

### 検討中の項目
1. フリープロンプトモードでのタイトル抽出精度向上
2. フリープロンプトモード専用のプレビュー機能
3. プロンプトテンプレート機能（よく使うプロンプトの保存）
4. エラーハンドリングの強化

### 完了した作業
- ✅ 動的文字数計算の実装
- ✅ 実際プロンプト表示機能
- ✅ 設定画面UI改善
- ✅ プレースホルダー説明の充実
- ✅ フリープロンプトモードの実装
- ✅ タブ切り替え機能の実装

## 開発ログ・やりとり記録

### 2025年07月16日 - 設定画面3タブ構成への再設計 (v2.6.1)

**ユーザー要求:**
> 設定画面を3タブ構成に再構成。共通設定・定型プロンプト設定・フリープロンプト設定の論理的分類。

**実装内容:**
1. **設定画面の3タブ構成** - 共通設定・定型プロンプト設定・フリープロンプト設定
2. **設定項目の再配置** - AIモデル・スケジュール設定を共通設定に移動
3. **定型プロンプト固有設定** - キーワード、記事生成設定、ニュースソース等を定型プロンプトタブに配置
4. **カスタムプロンプト機能削除** - フリープロンプトと重複のため削除
5. **参考情報源表示の最適化** - 単一リスト形式で安定した表示

**システム安定性向上:**
- **Unsplash画像生成テスト** - 3回連続テスト実行、成功率100%確認
- **実行時間**: 92-122秒の安定した動作
- **エラーログ**: 0件（警告ログも0件）
- **全機能正常動作確認**: 記事生成、画像生成、参考情報源表示、投稿公開

**テスト結果:**
- 投稿ID 55: 18,849文字、アイキャッチ画像設定済み
- 投稿ID 60: 13,160文字、アイキャッチ画像設定済み  
- 投稿ID 65: 11,237文字、アイキャッチ画像設定済み

**ファイル変更:**
- `ai-news-autoposter.php`: 設定画面の3タブ構成実装、バージョン更新
- `README.md`: v2.6.1の内容を反映
- `CLAUDE.md`: 開発記録の更新

### 2025年07月09日 - フリープロンプトモード実装

**ユーザー要求:**
> いまは設定画面に設定値をいれると、プロンプトに対して操作をするようにしてくれていますが、まったく設定をいれずにフリーでプロンプトを入力すると、そのプロンプトの結果を記事本文にいれるようにするモードを追加できますか。設定画面がタブで別れて、設定１と設定２みたいな感じでどっちを優先するか、選択できるといいんだけど。

**実装内容:**
1. **設定画面のタブ化** - 「通常設定」と「フリープロンプト設定」の2タブ
2. **プロンプトモード選択** - ラジオボタンでモード切り替え
3. **フリープロンプト機能** - 設定値を無視して自由なプロンプトで記事生成
4. **自動タイトル抽出** - H1タグまたは最初の行からタイトルを抽出
5. **UI/UX改善** - タブ切り替えとモード切り替えのJavaScript実装

**発生した問題と解決:**
- **問題**: Gemini APIが配列レスポンスを返すのに文字列として処理してエラー
- **解決**: レスポンス形式を判定して適切にテキストを抽出する処理を追加

**テスト結果:**
- 3回のテスト投稿すべて成功
- 投稿ID 1258, 1263, 1268で記事生成確認
- ページ表示も正常動作確認

**ファイル変更:**
- `ai-news-autoposter.php`: メイン機能実装（+142行）
- `assets/admin.css`: タブUI追加（+119行）
- `assets/admin.js`: タブ切り替え機能（+70行）
- `CLAUDE.md`: ドキュメント作成

**Git操作:**
```bash
git add .
git commit -m "Add free prompt mode with tab-based settings interface (v2.6.0)"
git push
```

### 技術的詳細

**タブ切り替え実装:**
```javascript
switchTab: function(e) {
    const $button = $(this);
    const tabId = $button.data('tab');
    
    $('.ai-news-tab-button').removeClass('active');
    $button.addClass('active');
    
    $('.ai-news-tab-content').removeClass('active');
    $('#' + tabId).addClass('active');
}
```

**フリープロンプトモード処理:**
```php
if (($settings['prompt_mode'] ?? 'normal') === 'free') {
    // Gemini APIの場合は配列レスポンスからテキストを抽出
    if (is_array($response) && isset($response['text'])) {
        $article_content = $response['text'];
    } else {
        $article_content = $response;
    }
    
    // タイトル自動抽出
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $article_content, $matches)) {
        $title = strip_tags($matches[1]);
    }
}
```

## 次回作業時の参考

### 再開手順
1. Docker環境を起動
2. プラグイン設定画面で現在の設定を確認
3. 「実際に送信されるプロンプトを表示」で動作確認

### テストコマンド
```bash
# 投稿テスト
docker exec wp-local-wordpress-1 php /var/www/html/test_post_with_debug.php

# 記事確認
docker exec wp-local-wordpress-1 php /var/www/html/check_generated_articles.php

# フリープロンプトモードテスト
docker exec wp-local-wordpress-1 php -r "
require_once('/var/www/html/wp-config.php');
require_once('/var/www/html/wp-content/plugins/ai-news-autoposter/ai-news-autoposter.php');
\$plugin = new AINewsAutoPoster();
\$reflection = new ReflectionClass(\$plugin);
\$method = \$reflection->getMethod('generate_and_publish_article');
\$method->setAccessible(true);
\$result = \$method->invoke(\$plugin, true, 'test');
echo 'Result: ' . (\$result instanceof WP_Error ? \$result->get_error_message() : \$result) . PHP_EOL;
"
```

### Git操作
```bash
# 状況確認
git status
git log --oneline -5

# 作業後
git add .
git commit -m "作業内容"
git push
```

## 開発者向けメモ
- 順次生成方式：1記事ずつ生成して最終的に複数記事を組み合わせ
- Google Search Grounding機能対応
- Claude/Gemini API両対応
- 文字数は「最小値」指定のため実際は超過することが多い
- 設定変更は「保存」後に反映される

## ローカル開発環境セットアップ

### 2025年07月14日 - ローカル開発環境構築

**環境構築手順:**
1. **GitHubリポジトリのクローン**
   ```bash
   git clone https://github.com/kitasinkita/ai-news-autoposter.git
   ```

2. **専用ディレクトリの作成**
   ```bash
   mkdir ai-news-autoposter-dev
   mv docker-compose.yml setup-wordpress.php ai-news-autoposter ai-news-autoposter-dev/
   ```

3. **Docker環境の起動**
   ```bash
   cd ai-news-autoposter-dev
   docker-compose up -d
   ```

**環境情報:**
- **プロジェクトディレクトリ**: `/Users/sk/Documents/dev/ai-news-autoposter-dev/`
- **WordPress**: http://localhost:8090
- **phpMyAdmin**: http://localhost:8081
- **管理画面**: http://localhost:8090/wp-admin

**Docker設定:**
- MySQL 8.0
- WordPress最新版
- phpMyAdmin
- プラグインは自動マウント

**環境管理:**
```bash
# 環境停止
cd ai-news-autoposter-dev && docker-compose down

# 環境再開
cd ai-news-autoposter-dev && docker-compose up -d

# コンテナ状況確認
docker-compose ps
```

**初期設定完了事項:**
- ✅ Docker環境構築
- ✅ WordPressコンテナ起動
- ✅ プラグインファイル配置
- ✅ 専用ディレクトリで他プロジェクトと分離

**今後の開発:**
- WordPress初期設定（サイトタイトル、管理者アカウント等）
- プラグイン有効化
- APIキー設定（Claude または Gemini）