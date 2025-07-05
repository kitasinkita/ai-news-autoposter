# AI News AutoPoster

🤖 **Claude Sonnet 4を使用してAIニュースを自動生成・投稿するWordPressプラグイン**

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)
[![Claude AI](https://img.shields.io/badge/AI-Claude%20Sonnet%204-orange.svg)](https://anthropic.com)
[![Version](https://img.shields.io/badge/Version-1.2.15-green.svg)](https://github.com/kitasinkita/ai-news-autoposter/releases)

## 📖 概要

AI News AutoPosterは、最新の**Claude Sonnet 4**を活用してAI関連ニュースを自動生成・投稿するWordPressプラグインです。RSS依存を排除し、Claudeの知識ベースを活用して高品質な記事を1時間間隔で自動投稿します。多言語対応、カスタムプロンプト、SEO最適化機能を備えた完全自動システムです。

## ✨ 主な機能

### 🤖 AI記事自動生成
- **Claudeモデル選択** - Haiku (高速・低コスト) / Sonnet 3.5 (バランス) / Sonnet 4 (最高品質)
- **多言語対応** (日本語・英語・中国語)
- **RSS不要** - Claudeの内蔵知識ベース活用
- **カスタムプロンプト** 対応
- **自動参考リンク** 生成とクリック可能化

### ⏰ 完全自動スケジューリング
- **1時間間隔** 自動投稿システム
- **開始時刻指定** + 最大投稿数設定
- **WordPress Cron** による確実な実行
- **手動実行・テスト** 機能完備

### 🚀 SEO・コンテンツ最適化
- **カスタマイズ可能な文字数** (デフォルト500文字)
- **カテゴリ・タグ** 自動設定
- **Yoast SEO / RankMath** 対応
- **プレースホルダー画像** 自動生成
- **免責事項** 自動追加機能

### 📊 包括的な管理機能
- **リアルタイム統計** ダッシュボード
- **詳細ログ機能** とエラー追跡
- **API接続状況** 監視
- **Cron実行テスト** 機能

### 🌐 多言語・カスタマイズ機能
- **ニュース収集言語** 選択 (日本語・英語・中国語)
- **出力言語** カスタマイズ
- **文体・スタイル** 設定 (デフォルト：夏目漱石風)
- **カスタムプロンプト** 対応

## 🚀 クイックスタート

### 1. インストール

```bash
# WordPressプラグインディレクトリに配置
cd /wp-content/plugins/
git clone https://github.com/kitasinkita/ai-news-autoposter.git
```

または[リリースページ](https://github.com/kitasinkita/ai-news-autoposter/releases)からZIPをダウンロード

### 2. 有効化

WordPress管理画面の「プラグイン」でAI News AutoPosterを有効化

### 3. Claude API設定

1. [Anthropic Console](https://console.anthropic.com/)でAPIキー取得
2. WordPress管理画面「AI News AutoPoster」→「設定」
3. APIキーを入力・保存

### 4. 基本設定

```
✅ 自動投稿: 有効
✅ 開始時刻: 06:00
✅ 最大投稿数: 1時間間隔で自動調整
✅ ニュース収集言語: 日本語・英語
✅ 出力言語: 日本語
✅ 文体: 夏目漱石風
✅ 記事文字数: 500文字
```

### 5. 動作確認

「API接続テスト」→「テスト記事生成」で動作確認後、自動投稿を開始

## 📋 システム要件

- **WordPress**: 5.0以上 (WordPress 6.4まで対応確認済み)
- **PHP**: 7.4以上  
- **Claude API**: Anthropicアカウント必須 (Sonnet 4対応)
- **メモリ**: 最低256MB推奨
- **ネットワーク**: 安定したインターネット接続

## 💰 費用

### プラグイン
- **無料** (GPL v2ライセンス)

### Claude Sonnet 4 API利用料
- **入力**: $3/百万トークン
- **出力**: $15/百万トークン

### 月間コスト例（1時間間隔投稿）
- **1日24記事**: ~$120-150
- **12時間運用**: ~$60-75
- **6時間運用**: ~$30-40

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

### 管理機能

**ダッシュボード統計**
- 今日の投稿数
- 総投稿数  
- 最終実行時刻
- 次回実行予定

**手動操作**
- API接続テスト
- テスト記事生成
- 今すぐ投稿
- Cron実行テスト

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

### 詳細設定

**プロンプトのカスタマイズ**
- カスタムプロンプトで記事の方向性を調整
- プレースホルダー (`{keyword}`, `{language}`, `{style}`) 対応
- 2025年の最新情報を重視する設定

**免責事項の管理**
- カスタマイズ可能な免責事項テキスト
- 自動追加の有効/無効切り替え

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

[Issues](https://github.com/kitasinkita/ai-news-autoposter/issues)で以下を含めて報告：

- 再現手順の詳細
- エラーログ（ログ機能でエクスポート可能）
- 環境情報（WordPress/PHP/プラグインバージョン）
- Claude API接続状況

### 機能要望

[Issues](https://github.com/kitasinkita/ai-news-autoposter/issues)で提案：

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
原因: APIキー設定ミス、または Claude Sonnet 4 モデルへのアクセス権限
解決: Anthropicコンソールで有効なAPIキーを再取得し、モデルアクセスを確認
```

**記事が生成されない**
```bash
原因: Cron設定問題、またはネットワークタイムアウト
解決: Cron実行テストボタンで診断、タイムアウト設定を6分に調整済み
```

**ネットワークエラー**
```bash
原因: 長時間のAPI処理によるタイムアウト
解決: プログレス表示機能でユーザーへの待機時間案内、リトライ機能
```

詳細は[トラブルシューティングガイド](docs/troubleshooting.md)を参照

## 🔄 バージョン履歴

### v1.2.15 (現在) ✅
- ✅ **タイトル解析機能向上** - 参考情報源のタイトル適切処理
- ✅ **参考情報源表示改善** - HTMLタグ形式での表示
- ✅ **LLMベースタイトル生成** - 機械的短縮から適切なタイトル生成へ
- ✅ **Google Search Grounding対応** - 実URLでの記事統合
- ✅ **UI/UX大幅改善** - 管理画面の使いやすさ向上
- ✅ **Claudeモデル選択機能** - Haiku/Sonnet 3.5/Sonnet 4から選択可能
- ✅ **多言語ニュース収集** (日本語・英語・中国語)
- ✅ **RSS依存排除** - Claude知識ベース活用
- ✅ **カスタムプロンプト機能**
- ✅ **ネットワークタイムアウト対策** (360秒)
- ✅ **参考リンク自動生成・クリック可能化**

### v1.2.14
- 参考情報源のHTMLタグ表示に戻す

### v1.2.13  
- HTMLタグをMarkdown形式に変更

### v1.2.12
- タイトル長さ25-30文字に調整
- 参考情報源のURL処理簡素化

### v1.2.11
- 機械的タイトル短縮を削除
- LLMによる適切なタイトル生成に変更

### v1.2.10
- タイトルとHTML問題修正

### v1.2.9
- 主要なUI/UX改善

### 今後の予定
- [ ] 外部ニュースAPI統合（複雑性を避け現在見送り中）
- [ ] SNS自動投稿機能
- [ ] より高度な画像生成オプション

## 📄 ライセンス

GPL v2 or later - 詳細は[LICENSE](LICENSE)ファイルを参照

## 👥 貢献者

- [@kitasinkita](https://github.com/kitasinkita) - 開発者

## 🙏 謝辞

- [Anthropic](https://anthropic.com) - Claude Sonnet 4 AI提供
- [WordPress](https://wordpress.org) - 素晴らしいCMSプラットフォーム
- 夏目漱石 - デフォルト文体インスピレーション

## 📞 サポート

- 🐛 **Issues**: [GitHub Issues](https://github.com/kitasinkita/ai-news-autoposter/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/kitasinkita/ai-news-autoposter/discussions)
- 📖 **リリース**: [GitHub Releases](https://github.com/kitasinkita/ai-news-autoposter/releases)

---

<div align="center">

**⭐ このプロジェクトが役に立ったら、スターをお願いします！**

[⬆ Back to top](#ai-news-autoposter)

</div>