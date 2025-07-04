# AI News AutoPoster

🤖 **Claude AIを使用してAIニュースを自動生成・投稿するWordPressプラグイン**

[![WordPress](https://img.shields.io/badge/WordPress-5.8+-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)
[![Claude AI](https://img.shields.io/badge/AI-Claude%203-orange.svg)](https://anthropic.com)

## 📖 概要

AI News AutoPosterは、Anthropic Claude AIを活用して最新のAIニュースを自動収集・生成し、WordPressサイトに定期投稿する包括的なプラグインです。村上春樹風の文学的な文体で、高品質なAI記事を毎日自動生成します。

## ✨ 主な機能

### 🤖 AI記事自動生成
- **Claude 3 Sonnet** による高品質記事生成
- **村上春樹風文体** での文学的表現
- **複数RSSソース** からのニュース自動収集
- **自動タグ・カテゴリ** 設定

### ⏰ 高度なスケジューリング
- **毎日指定時刻** での自動投稿
- **投稿数制限** (1日最大24記事)
- **Cronジョブ** による確実な実行
- **手動実行・テスト** 機能

### 🚀 SEO最適化
- **フォーカスキーワード** 自動挿入
- **メタディスクリプション** 自動生成
- **Yoast SEO / RankMath** 対応
- **アイキャッチ画像** 自動生成

### 📊 包括的な管理機能
- **リアルタイム統計** ダッシュボード
- **詳細ログ機能**
- **API接続状況** 監視
- **記事生成状況** 追跡

### 🔧 開発者向け機能
- **REST API** エンドポイント
- **ウィジェット・ショートコード**
- **カスタムフック・フィルター**
- **多言語対応**

## 🚀 クイックスタート

### 1. インストール

```bash
# WordPressプラグインディレクトリに配置
cd /wp-content/plugins/
git clone https://github.com/YOUR_USERNAME/ai-news-autoposter.git
```

または[リリースページ](https://github.com/YOUR_USERNAME/ai-news-autoposter/releases)からZIPをダウンロード

### 2. 有効化

WordPress管理画面の「プラグイン」でAI News AutoPosterを有効化

### 3. Claude API設定

1. [Anthropic Console](https://console.anthropic.com/)でAPIキー取得
2. WordPress管理画面「AI News AutoPoster」→「設定」
3. APIキーを入力・保存

### 4. 基本設定

```
✅ 自動投稿: 有効
✅ 投稿時刻: 06:00
✅ 投稿数: 1日1記事
✅ カテゴリ: 任意選択
✅ SEOキーワード: AI ニュース
```

### 5. 動作確認

「API接続テスト」→「テスト記事生成」で動作確認後、自動投稿を開始

## 📋 システム要件

- **WordPress**: 5.8以上
- **PHP**: 7.4以上  
- **MySQL**: 5.7以上
- **Claude API**: Anthropicアカウント必須
- **メモリ**: 最低256MB推奨

## 💰 費用

### プラグイン
- **無料** (GPL v2ライセンス)

### Claude API利用料
- **入力**: $3/百万トークン
- **出力**: $15/百万トークン

### 月間コスト例
- **1日1記事**: ~$5
- **1日3記事**: ~$15
- **1日10記事**: ~$50

## 🎯 使用方法

### ショートコード

```php
// 基本表示（最新5件）
[ai-news-list]

// カスタマイズ例
[ai-news-list number="10" category="ai-tech" show_date="true" show_excerpt="true"]
```

### ウィジェット

「外観」→「ウィジェット」→「AI News: 最新記事」を追加

### REST API

```bash
# 記事生成
curl -X POST https://yoursite.com/wp-json/ai-news-autoposter/v1/generate \
  -H "X-WP-Nonce: YOUR_NONCE"

# ステータス確認  
curl https://yoursite.com/wp-json/ai-news-autoposter/v1/status
```

## 🔧 カスタマイズ

### フック・フィルター

```php
// 記事生成前
add_action('ai_news_autoposter_before_generation', function($settings) {
    // カスタム処理
});

// 記事生成後
add_action('ai_news_autoposter_after_generation', function($post_id, $article_data) {
    // 追加処理（SNS投稿など）
});

// プロンプトカスタマイズ
add_filter('ai_news_autoposter_prompt', function($prompt, $news_topics, $settings) {
    return $custom_prompt;
}, 10, 3);
```

### 設定カスタマイズ

```php
// wp-config.phpに追加
define('AI_NEWS_AUTOPOSTER_DEBUG', true);
define('AI_NEWS_AUTOPOSTER_MAX_RETRIES', 3);
```

## 📁 プロジェクト構成

```
ai-news-autoposter/
├── ai-news-autoposter.php     # メインプラグインファイル
├── readme.txt                 # WordPress用README
├── uninstall.php             # アンインストール処理
├── assets/
│   ├── admin.css              # 管理画面CSS
│   ├── admin.js               # 管理画面JavaScript
│   └── index.php              # セキュリティファイル
├── languages/                 # 翻訳ファイル
├── tests/                     # テストファイル
├── docs/                      # ドキュメント
├── README.md                  # GitHub用README
├── CHANGELOG.md               # 変更履歴
├── CONTRIBUTING.md            # 貢献ガイド
└── LICENSE                    # ライセンス
```

## 🤝 貢献

プロジェクトへの貢献を歓迎します！

### 開発参加

1. このリポジトリをフォーク
2. 機能ブランチを作成 (`git checkout -b feature/amazing-feature`)
3. 変更をコミット (`git commit -m 'Add amazing feature'`)
4. ブランチにプッシュ (`git push origin feature/amazing-feature`)
5. プルリクエストを作成

### バグ報告

[Issues](https://github.com/YOUR_USERNAME/ai-news-autoposter/issues)で以下を含めて報告：

- 再現手順の詳細
- エラーログ
- 環境情報（WordPress/PHP/プラグインバージョン）

### 機能要望

[Issues](https://github.com/YOUR_USERNAME/ai-news-autoposter/issues)で提案：

- 機能の説明
- 使用ケース
- 期待される動作

## 📚 ドキュメント

- [📖 ユーザーガイド](docs/user-guide.md)
- [🔧 開発者ガイド](docs/developer-guide.md)
- [🚀 デプロイメント](docs/deployment.md)
- [❓ FAQ](docs/faq.md)

## 🐛 トラブルシューティング

### よくある問題

**API接続エラー**
```bash
原因: APIキー設定ミス
解決: Anthropicコンソールで有効なAPIキーを再取得
```

**記事が生成されない**
```bash
原因: Cron設定問題
解決: wp-config.phpでWP_CRON設定を確認
```

**管理画面エラー**
```bash
原因: ファイル権限問題
解決: プラグインファイルの権限を644に設定
```

詳細は[トラブルシューティングガイド](docs/troubleshooting.md)を参照

## 🔄 ロードマップ

### v1.1.0 (予定)
- [ ] OpenAI GPT対応
- [ ] 多言語記事生成
- [ ] 高度な画像生成

### v1.2.0 (予定)  
- [ ] SNS自動投稿機能
- [ ] カスタム投稿タイプ対応
- [ ] 詳細分析ダッシュボード

### v2.0.0 (予定)
- [ ] マルチサイト対応
- [ ] エンタープライズ機能
- [ ] 高度なワークフロー

## 📄 ライセンス

GPL v2 or later - 詳細は[LICENSE](LICENSE)ファイルを参照

## 👥 貢献者

- [@YOUR_USERNAME](https://github.com/YOUR_USERNAME) - 開発者

## 🙏 謝辞

- [Anthropic](https://anthropic.com) - Claude AI提供
- [WordPress](https://wordpress.org) - 素晴らしいCMSプラットフォーム
- 村上春樹氏 - 文体インスピレーション

## 📞 サポート

- 📧 **Email**: support@example.com
- 🐛 **Issues**: [GitHub Issues](https://github.com/YOUR_USERNAME/ai-news-autoposter/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/YOUR_USERNAME/ai-news-autoposter/discussions)
- 📖 **WordPress.org**: [サポートフォーラム](https://wordpress.org/support/plugin/ai-news-autoposter/)

---

<div align="center">

**⭐ このプロジェクトが役に立ったら、スターをお願いします！**

[⬆ Back to top](#ai-news-autoposter)

</div>