# AI News AutoPoster

🤖 **Claude Sonnet 4とGemini 2.5を使用してAIニュースを自動生成・投稿するWordPressプラグイン**

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)
[![Claude AI](https://img.shields.io/badge/AI-Claude%20Sonnet%204-orange.svg)](https://anthropic.com)
[![Version](https://img.shields.io/badge/Version-2.5.8-green.svg)](https://github.com/kitasinkita/ai-news-autoposter/releases)

## 📖 概要

AI News AutoPosterは、**Claude Sonnet 4**と**Gemini 2.5 Flash**を活用して**AIニュース**に関する記事を自動生成・投稿するWordPressプラグインです。**v2.5**では「順次生成方式」を採用し、1記事ずつ確実に生成して高品質な記事を保証。Google Search Grounding機能により最新情報を取り込み、完全な3記事構成で情報提供源付きの記事を生成します。

## 🆕 v2.5の最新機能

### 🎯 順次生成方式（Sequential Generation）
- **1記事ずつ確実生成** - 記事を順次生成して品質を保証
- **自動番号付けタイトル** - 1. 2. 3. の正しい番号を自動生成
- **完全な記事構造** - 3記事×3セクション（概要・背景・影響）を完備
- **Google Grounding Sources** - 記事末尾に参考情報源を自動追加
- **適切な文字数制御** - 5000-8000文字の理想的な記事生成

### 📈 大幅な品質向上
- **文字数最適化** - デフォルト5000文字、最大10000文字まで対応
- **500文字/セクション** - 各セクションの十分な文字数を保証
- **完全性100%** - 途中で切れない完全な記事生成
- **情報提供源完備** - クリック可能な外部リンク付き

## ✨ 主な機能

### 🤖 AI記事自動生成
- **AIモデル選択** - Claude (Haiku/Sonnet 3.5/Sonnet 4) / Gemini (1.5 Flash/2.0 Flash/2.5 Flash)
- **AIニュース特化** - AI・人工知能関連の最新ニュースに特化
- **Google Search Grounding** - 最新ニュース情報の自動取り込み
- **多言語対応** (日本語・英語・中国語)
- **ハイブリッド検索システム** - Google Search Grounding + RSSフォールバック
- **カスタムプロンプト** 対応（順次生成方式に対応）
- **豊富な参考リンク** - 自動HTML変換、target="_blank"対応、10+個のソース表示

### ⏰ 完全自動スケジューリング
- **1時間間隔** 自動投稿システム
- **開始時刻指定** + 最大投稿数設定
- **WordPress Cron** による確実な実行
- **手動実行・テスト** 機能完備

### 🚀 SEO・コンテンツ最適化
- **最適化された文字数** (デフォルト5000文字、最大10000文字)
- **カテゴリ・タグ** 自動設定
- **Yoast SEO / RankMath** 対応
- **プレースホルダー画像** 自動生成
- **構造化記事** - H2/H3タグによる明確な階層構造
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
✅ 記事文字数: 5000文字
✅ キーワード: AIニュース（AI・人工知能関連の最新ニュース）
```

### 5. 動作確認

「API接続テスト」→「テスト記事生成」で動作確認後、自動投稿を開始

## 🆕 v1.2.55の新機能（最新）

### 🎯 参考情報源の大幅改善
- **✅ クリック可能リンク自動変換** - プレーンテキストを自動でHTMLリンクに変換
- **✅ target="_blank"対応** - すべてのリンクが新しいタブで開く
- **✅ 日本語ソース優先検索** - 出力言語に基づく優先度設定
- **✅ 参考情報源数拡充** - 3個から3〜5個に増加
- **✅ 無効形式の排除** - [参考リンク]形式を禁止、完全なURL必須化

### 🛠 プロンプト最適化
- **言語優先度の明確化** - 日本語ソースを優先して検索
- **URL品質の向上** - 無効URLの場合は別ソースを自動選択
- **管理画面表示改善** - デフォルトプロンプト表示を実際の動作と一致

### 📊 改善効果
- **リンク成功率**: 64% → 67%に向上
- **日本語ソース比率**: 大幅増加
- **参考情報源数**: 平均2.3個 → 4.2個に拡充
- **無効リンク**: [参考リンク]形式を完全排除

## 📋 システム要件

- **WordPress**: 5.8以上 (WordPress 6.8まで対応確認済み)
- **PHP**: 7.4以上  
- **AIモデル**: Claude API (Anthropic) または Gemini API (Google) 必須
- **メモリ**: 最低256MB推奨
- **ネットワーク**: 安定したインターネット接続

## 💰 費用

### プラグイン
- **無料** (GPL v2ライセンス)

### AIモデル利用料（参考情報）

> ⚠️ **重要**: 以下はAIサービス提供者（Google・Anthropic）のAPI利用料金です。プラグイン自体は無料ですが、AIモデルの使用には別途API利用料が発生します。料金は各社によって変更される可能性があるため、最新の価格は各提供者の公式サイトでご確認ください。

**Gemini モデル（Google）:**
- **Gemini 1.5 Flash**: $0.075/100万文字（入力・出力）
- **Gemini 2.0 Flash**: $0.35/100万文字（入力・出力）
- **Gemini 2.5 Flash（推奨）**: $0.35/100万文字（入力・出力）+ Google Search Grounding
- **無料枠**: 1日15リクエスト（全モデル共通・2025年1月現在）
- **最新料金**: [Google AI Pricing](https://ai.google.dev/pricing)で確認

**Claude モデル（Anthropic）:**
- **Claude Haiku**: 入力 $0.25/百万トークン、出力 $1.25/百万トークン
- **Claude Sonnet 3.5**: 入力 $3/百万トークン、出力 $15/百万トークン
- **Claude Sonnet 4**: 入力 $3/百万トークン、出力 $15/百万トークン
- **最新料金**: [Anthropic Pricing](https://www.anthropic.com/pricing)で確認

### 月間コスト例（参考・2025年1月料金基準）

> 📝 **注意**: 実際の費用は記事の長さ、生成頻度、API料金の変動により異なります。正確な費用は各APIの使用量と最新料金を確認してください。

**Gemini モデル（1記事 = 約3,000文字想定）:**

*Gemini 1.5 Flash（最安）:*
- **1日1記事**: 無料枠内で運用可能
- **1日3記事**: 無料枠内で運用可能
- **1日5記事**: ~$0.30/月（無料枠超過時）

*Gemini 2.5 Flash（推奨・Google Search Grounding対応）:*
- **1日1記事**: 無料枠内で運用可能  
- **1日3記事**: ~$0.80/月（無料枠超過時）
- **1日5記事**: ~$1.50/月（無料枠超過時）

**Claude モデル（1記事 = 約2,000トークン想定）:**

*Claude Haiku（最安）:*
- **1日1記事**: ~$1.50/月
- **1日3記事**: ~$4.50/月
- **1日5記事**: ~$7.50/月

*Claude Sonnet 3.5・Sonnet 4:*
- **1日1記事**: ~$18/月
- **1日3記事**: ~$54/月
- **1日5記事**: ~$90/月

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
原因: [参考リンク]形式、プレーンテキストURL、言語優先度の問題
解決: v1.2.55で大幅改善（自動HTML変換、日本語優先、3〜5個のソース）
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

## 🔄 更新履歴

### v2.0.3 (2025-07-07) 🆕
- **クリッカブルURL自動生成** - プロンプトでHTMLリンク形式を指定、すべてのURLがクリック可能
- **target="_blank"対応** - 新しいタブで開くリンク形式を自動生成
- **リンク形式統一** - `<a href="URL" target="_blank">リンクテキスト</a>`形式で統一
- **プロンプト改善** - URLの記載方法を明確に指示して確実なリンク生成

### v2.0.2 (2025-07-07)
- **プロンプト主導タイトル生成** - AIが記事内容から20文字以内の自然なタイトルを生成
- **日付自動挿入** - プロンプトで今日の日付を指定、正確な日付情報を保証
- **タイトル抽出改善** - 回答の最初の行を自動でタイトルとして抽出
- **コンテンツ整理** - タイトル行を除去してクリーンな記事本文を生成

### v2.0.1 (2025-07-07)
- **タイトル時刻表示の修正** - デフォルトタイトルから時間（H時i分）を除去、日付のみ表示に変更
- **抜粋自動生成機能** - 記事内容から20文字程度の自然な抜粋を自動生成
- **可読性向上** - 句読点で適切に区切った簡潔な要約表示

### v2.0.0 (2025-07-07)
- **プロンプト結果に任せる方式** - 文字数制限や構造強制変更を無効化
- **3記事完全構造** - 明確な指定で3本の記事を確実に生成
- **maxOutputTokens増加** - 最大20,000トークンで完全な記事生成
- **自然なレイアウト** - HTMLタグの適切な開閉で美しい表示

### v1.2.55 (2025-07-07)
- **参考情報源の大幅改善**: クリック可能リンク自動変換、target="_blank"対応
- **日本語ソース優先**: 出力言語に基づく検索優先度設定で日本語コンテンツ増加  
- **参考情報源数拡充**: 3個から3〜5個に増加、情報量大幅アップ
- **無効形式排除**: [参考リンク]形式を禁止、完全なURL必須化
- **プロンプト最適化**: 管理画面表示と実際動作の一致、URL品質向上
- **リンク成功率向上**: 64%から67%に改善、自動HTML変換機能

### v1.2.35 (2025-07-07)
- **Gemini APIトークン制限完全解決**: 実際の制限（65,536出力トークン）に対応し「トークン上限」エラーを解消
- **URLクリーニング機能追加**: Google News URLの300-400文字の不要なエンコードを削除してプロンプトサイズを30-40%削減
- **動的トークン配分最適化**: 第1段階（ニュース検索）2,000トークン、第2段階（記事生成）8,000トークンに調整
- **プロンプト効率化**: clean_news_url()メソッドでAPIリクエストの安定性を大幅向上

### v1.2.34 (2025-07-07)
- **データベースエラー修正**: MySQL UTF-8文字処理とデータベース互換性の改善
- **文字クリーニング機能強化**: 全角文字の半角変換、制御文字除去、WordPressサニタイゼーション
- **参考情報源セクション修復**: RSSフォールバック時の「今日の○○ニュース」と「参考情報源」表示問題を解決
- **トークン使用量最適化**: Gemini第1段階と第2段階のトークン配分を調整してAPI制限エラーを改善
- **抜粋自動生成機能**: タイトルから20文字程度の簡潔な要約を自動生成する機能を追加

### v1.2.33 (2025-07-07)
- **第一段階プロンプト改善**: ニュース収集言語を明示的に指定するよう修正
- **投稿数制限変更**: 1日の最大投稿数を最大5件に制限（安全性向上）
- **UI改善**: 
  - 自動投稿無効時に「予定されていません」と表示
  - 「テスト記事生成」→「下書き記事生成」に文言変更
  - 手動実行説明文を「記事を手動生成できます。自動投稿は「設定」で登録ください。」に変更
- **Unsplash画像機能強化**:
  - 記事内容から動的キーワード抽出（12カテゴリー対応）
  - 画像選択にランダム性を追加（毎回異なる画像）
  - AI、テクノロジー、ビジネスなど内容に応じた適切な画像選定
- **バグ修正**:
  - Claude APIフォールバック時のモデル指定エラー修正
  - Gemini APIトークン制限エラーの動的調整
  - 免責事項が文字数制限で削除される問題を解決

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
- ✅ **AI・テクノロジー特化** - AI・人工知能関連ニュースに特化した高品質記事生成
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