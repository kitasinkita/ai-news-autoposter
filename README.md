# AI News AutoPoster

🤖 **Claude Sonnet 4とGemini 2.5を使用して任意キーワードニュースを自動生成・投稿するWordPressプラグイン**

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)
[![Claude AI](https://img.shields.io/badge/AI-Claude%20Sonnet%204-orange.svg)](https://anthropic.com)
[![Version](https://img.shields.io/badge/Version-1.2.27-green.svg)](https://github.com/kitasinkita/ai-news-autoposter/releases)

## 📖 概要

AI News AutoPosterは、**Claude Sonnet 4**と**Gemini 2.5 Flash**を活用して**任意のキーワード**に関するニュースを自動生成・投稿するWordPressプラグインです。Google Search Grounding機能により最新情報を取り込み、アウトドア、テクノロジー、ビジネス、スポーツなど任意のジャンルで高品質な記事を1時間間隔で自動投稿します。多言語対応、カスタムプロンプト、SEO最適化機能を備えた完全自動システムです。

## ✨ 主な機能

### 🤖 AI記事自動生成
- **AIモデル選択** - Claude (Haiku/Sonnet 3.5/Sonnet 4) / Gemini (1.5 Flash/2.0 Flash/2.5 Flash)
- **任意キーワード対応** - アウトドア、テクノロジー、ビジネス、スポーツなど自由設定
- **Google Search Grounding** - 最新ニュース情報の自動取り込み
- **多言語対応** (日本語・英語・中国語)
- **ハイブリッド検索システム** - Google Search Grounding + RSSフォールバック
- **カスタムプロンプト** 対応
- **完璧な参考リンク** - 実際のページタイトル取得、100%動作リンク

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

### 3. AIモデル設定

**Gemini使用の場合（推奨）:**
1. [Google AI Studio](https://aistudio.google.com/)でGemini APIキー取得
2. WordPress管理画面「AI News AutoPoster」→「設定」
3. Gemini APIキーを入力・保存
4. モデルでGemini 2.5 Flashを選択（Google Search Grounding対応）

**Claude使用の場合:**
1. [Anthropic Console](https://console.anthropic.com/)でAPIキー取得
2. WordPress管理画面「AI News AutoPoster」→「設定」
3. Claude APIキーを入力・保存

### 4. 基本設定

```
✅ 自動投稿: 有効
✅ 開始時刻: 06:00
✅ 最大投稿数: 1時間間隔で自動調整
✅ ニュース収集言語: 日本語・英語
✅ 出力言語: 日本語
✅ 文体: 夏目漱石風
✅ 記事文字数: 500文字
✅ キーワード: あなたの専門分野（例：アウトドア、キャンプ、テント、ギア）
```

### 5. 動作確認

「API接続テスト」→「テスト記事生成」で動作確認後、自動投稿を開始

## 🆕 v1.2.27の新機能（最新）

### 🎯 参考情報源の完全改善
- **✅ 重複セクション問題解決** - 単一の参考情報源セクションのみ生成
- **✅ タイトル100%品質** - 実際のページタイトル取得とクリーンアップ
- **✅ URL完全安定化** - Grounding APIリダイレクト解決、100%動作リンク
- **✅ HTML corruption完全防止** - タグ・エンティティの完全除去
- **✅ 実ページタイトル自動取得** - ドメイン名から実際の記事タイトルに変換

### 🔄 ハイブリッド検索システム
- **Google Search Grounding優先** - 最新ニュース情報の自動取り込み
- **RSSフォールバック** - Google制限時の自動切り替え
- **Claude APIフォールバック** - Gemini失敗時の自動復旧

### 🛠 技術的改善
- **文字エンコーディング完全修正** - UTF-8処理最適化
- **データベースエラー完全解決** - 文字列クリーニング強化
- **長文記事対応** - 10,000文字まで対応
- **メモリ使用量最適化** - 大容量コンテンツ処理改善

## 📋 システム要件

- **WordPress**: 5.0以上 (WordPress 6.4まで対応確認済み)
- **PHP**: 7.4以上  
- **AIモデル**: Claude API (Anthropic) または Gemini API (Google) 必須
- **メモリ**: 最低256MB推奨
- **ネットワーク**: 安定したインターネット接続

## 💰 費用

### プラグイン
- **無料** (GPL v2ライセンス)

### AIモデル利用料

**Gemini 2.5 Flash API（推奨）:**
- **無料枠**: 1日15リクエスト
- **有料**: $0.35/100万文字（入力・出力）
- **Google Search Grounding**: 最新ニュース取り込み

**Claude Sonnet 4 API:**
- **入力**: $3/百万トークン
- **出力**: $15/百万トークン

### 月間コスト例（1時間間隔投稿）

**Gemini使用時（推奨）:**
- **1日24記事**: ~$5-8（無料枠超過時）
- **12時間運用**: ~$2-4
- **6時間運用**: ~$1-2（無料枠内の可能性）

**Claude使用時:**
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
- AIモデル接続状況

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

**参考情報源の問題**
```bash
原因: 重複セクション、404リンク、HTMLタグ混入
解決: v1.2.27で完全修正済み（100%動作リンク、単一セクション）
```

**API接続エラー**
```bash
原因: APIキー設定ミス、またはアクセス権限
解決: Google AI StudioまたはAnthropic Consoleで有効なAPIキーを再取得
```

**記事が生成されない**
```bash
原因: Cron設定問題、またはネットワークタイムアウト
解決: Cron実行テストボタンで診断、ハイブリッドフォールバック機能
```

**文字化け**
```bash
原因: 文字エンコーディング処理
解決: v1.2.27でUTF-8処理を完全最適化、文字化け完全防止
```

詳細は[トラブルシューティングガイド](docs/troubleshooting.md)を参照

## 🔄 バージョン履歴

### v1.2.27 (最新) 🎉
- **🎯 参考情報源の完全改善**
  - ✅ 重複セクション問題解決（単一セクションのみ生成）
  - ✅ タイトル100%品質達成（実際のページタイトル取得）
  - ✅ URL100%安定化（Grounding APIリダイレクト解決）
  - ✅ HTML corruption完全防止（タグ・エンティティ除去）
- **🔄 ハイブリッド検索システム**
  - ✅ Google Search Grounding優先 + RSSフォールバック
  - ✅ Claude APIフォールバック（Gemini失敗時）
- **🛠 技術的改善**
  - ✅ 文字エンコーディング完全修正
  - ✅ データベースエラー完全解決
  - ✅ 長文記事対応（10,000文字）
  - ✅ メモリ使用量最適化

### v1.2.26
- ✅ **任意キーワード完全対応** - ハードコーディングされたAIキーワード削除
- ✅ **RSSベース実ニュース検索** - Google News、朝日新聞、NHK、Yahoo!ニュース統合
- ✅ **キーワード非依存フィルタリング** - AI偏重から汎用的な関連性判定へ
- ✅ **アウトドア・ライフスタイル対応** - テクノロジー以外のジャンル完全サポート
- ✅ **エラー処理改善** - 正規表現・配列アクセス問題修正

### v1.2.25
- ✅ **文字エンコーディング修正** - 二重UTF-8変換による文字化け解決
- ✅ **引用元表示改善** - Markdownリンク形式統一

### v1.2.15
- ✅ **Gemini 2.5 Flash対応** - Google Search Grounding機能付き
- ✅ **最新ニュース取り込み** - 2024年末までの最新情報対応
- ✅ **AIモデル選択機能** - Claude (Haiku/Sonnet 3.5/Sonnet 4) / Gemini (1.5/2.0/2.5 Flash)
- ✅ **多言語ニュース収集** (日本語・英語・中国語)
- ✅ **カスタムプロンプト機能**

### 今後の予定
- [ ] SNS自動投稿機能
- [ ] 高度な画像生成オプション（DALL-E統合）
- [ ] マルチサイト対応

## 📄 ライセンス

GPL v2 or later - 詳細は[LICENSE](LICENSE)ファイルを参照

## 👥 貢献者

- [@kitasinkita](https://github.com/kitasinkita) - 開発者

## 🙏 謝辞

- [Anthropic](https://anthropic.com) - Claude Sonnet 4 AI提供
- [Google](https://ai.google.dev) - Gemini 2.5 Flash AI提供
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