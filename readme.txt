=== AI News AutoPoster ===
Contributors: yourname
Tags: ai, automation, news, claude, seo, auto-post, artificial intelligence
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Claude AIを使用してAIニュースを自動生成・投稿するプラグイン。完全自動化、スケジューリング、SEO最適化機能付き。

== Description ==

AI News AutoPosterは、Claude AIを使用して最新のAIニュースを自動収集・生成し、WordPressサイトに定期投稿する包括的なプラグインです。村上春樹風の文学的な文体で、高品質なAI記事を毎日自動生成します。

= 主な機能 =

**🤖 AI記事自動生成**
* Claude AI（Anthropic）による高品質記事生成
* 村上春樹風の文学的文体対応
* 複数のRSSソースからニュース収集
* 自動タグ・カテゴリ設定

**⏰ 高度なスケジューリング**
* 毎日指定時刻の自動投稿
* 投稿数制限（1日最大24記事）
* Cronジョブによる確実な実行
* 手動実行・テスト機能

**🚀 SEO最適化**
* フォーカスキーワード自動挿入
* メタディスクリプション自動生成
* Yoast SEO / RankMath 対応
* アイキャッチ画像自動生成

**📊 包括的な管理機能**
* リアルタイム統計ダッシュボード
* 詳細ログ機能
* API接続状況監視
* 記事生成状況追跡

**🔧 開発者向け機能**
* REST API エンドポイント
* ウィジェット・ショートコード
* カスタムフック・フィルター
* 多言語対応

= システム要件 =

* WordPress 5.8以上
* PHP 7.4以上
* Claude API キー（Anthropicアカウント必須）
* 最低256MB メモリ推奨

= 使用方法 =

1. プラグインをインストール・有効化
2. AnthropicでClaude APIキーを取得
3. 設定画面でAPIキーを入力
4. 投稿時刻・カテゴリ等を設定
5. 自動投稿を有効化

= ショートコード =

`[ai-news-list]` - AI生成記事リストを表示
`[ai-news-list number="10" show_date="true"]` - カスタマイズ例

= フック =

`ai_news_autoposter_before_generation` - 記事生成前
`ai_news_autoposter_after_generation` - 記事生成後
`ai_news_autoposter_prompt` - プロンプトカスタマイズ

== Installation ==

= 自動インストール =

1. WordPress管理画面の「プラグイン」→「新規追加」
2. 「AI News AutoPoster」を検索
3. 「今すぐインストール」→「有効化」

= 手動インストール =

1. プラグインファイルをダウンロード
2. `/wp-content/plugins/ai-news-autoposter/`にアップロード
3. 管理画面でプラグインを有効化

= 初期設定 =

1. 「AI News AutoPoster」→「設定」に移動
2. Claude APIキーを入力
3. 投稿設定を完了
4. 「API接続テスト」で動作確認

== Frequently Asked Questions ==

= 無料で使用できますか？ =

プラグイン自体は無料ですが、Claude APIの利用料金が発生します。月$5-50程度（使用量による）

= 他のAIサービスに対応していますか？ =

現在はClaude専用ですが、将来的にOpenAI GPTにも対応予定です。

= マルチサイトで使用できますか？ =

現在は単一サイト専用です。v2.0でマルチサイト対応予定です。

= SEOプラグインとの互換性は？ =

Yoast SEO、RankMath、All in One SEOに対応済みです。

= 記事の品質はどうですか？ =

Claude 3の高度な言語能力により、人間レベルの自然な記事を生成できます。

= サーバー要件はありますか？ =

一般的な共有ホスティングで動作しますが、専用サーバーを推奨します。

== Screenshots ==

1. ダッシュボード - 統計情報とクイック操作
2. 設定画面 - API設定とスケジューリング
3. ログ画面 - 詳細な実行ログ
4. ウィジェット - サイドバーでの最新記事表示
5. 生成された記事例 - 村上春樹風文体

== Changelog ==

= 1.0.0 =
* 初期リリース
* Claude AI統合
* 自動投稿機能
* SEO最適化
* 管理ダッシュボード
* ログ機能
* ウィジェット・ショートコード
* REST API

== Upgrade Notice ==

= 1.0.0 =
初期リリース。Claude APIキーの設定が必要です。

== Additional Info ==

= ライセンス =

このプラグインはGPL v2ライセンスの下で配布されています。

= サポート =

* GitHub: https://github.com/yourname/ai-news-autoposter
* サポートフォーラム: WordPress.org
* メール: support@example.com

= 貢献 =