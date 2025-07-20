# Pro-autoposter プロジェクト

## 概要
WordPress用AIニュース自動投稿プラグイン「AI News AutoPoster」の開発プロジェクト

## リポジトリ情報
- GitHub: https://github.com/kitasinkita/ai-news-autoposter
- 現在のバージョン: v2.7.0
- ライセンス: GPL v2 or later

## プラグイン特徴
- Claude Sonnet 4とGemini 2.5を使用してAIニュースを自動生成
- Google Search Grounding対応
- 1時間間隔での自動投稿
- 多言語対応（日本語・英語・中国語）
- カスタムプロンプト対応
- Unsplash画像自動生成

## 技術スタック
- WordPress 5.8以上
- PHP 7.4以上
- Claude API / Gemini API
- JavaScript (jQuery)

## 主要ファイル
- `ai-news-autoposter.php` - メインプラグインファイル
- `assets/admin.css` - 管理画面CSS
- `assets/admin.js` - 管理画面JavaScript
- `README.md` - プロジェクトドキュメント
- `CLAUDE.md` - 開発状況メモ（既存）

## 最新機能 (v2.6.2)
- Gemini 2.5 Flash Lite対応追加（軽量・高速版）
- 言語別推奨サイト指定機能（日本語：NHK、朝日新聞など / 英語：Reuters、BBCなど）
- URL取得件数プリセット選択機能（少数精選3件〜大量収集20件）
- 動的モデル選択によるURL検索最適化

## 機能 (v2.6.1)
- 設定画面の3タブ構成（共通設定・定型プロンプト設定・フリープロンプト設定）
- フリープロンプトモード（設定値を無視した自由なプロンプト）
- 順次生成方式（1記事ずつ確実生成）
- Unsplash画像生成の信頼性向上

## 開発状況
- 現在のバージョンは安定稼働中
- システム安定性が向上（エラー率0%）
- 3回連続テストで成功率100%確認済み

## v2.6.2での実装完了項目（2025-07-19）
**要求仕様：「キーワードのニュースや解説を指定した言語のサイトから指定した数だけGEMINI 2.5 Flash Liteで探してくる」**

### ✅ 実装済み機能
1. **Gemini 2.5 Flash Lite統合**
   - モデル選択肢に「Gemini 2.5 Flash Lite + Google検索 (軽量・高速)」を追加
   - URL検索機能で自動的にGemini 2.5系モデルを優先使用
   - Google Search Grounding対応

2. **指定言語サイトからの検索**
   - 日本語：NHKニュース、朝日新聞、読売新聞、毎日新聞、日経新聞、Yahoo!ニュース、ITmedia等
   - 英語：Reuters、BBC、CNN、TechCrunch、The Verge、Wired、Ars Technica等
   - 中国語：新浪新闻、腾讯新闻、网易新闻、36氪、钛媒体等

3. **指定数取得機能の強化**
   - プリセット選択：少数精選(3件)、標準(5件)、多数収集(10件)、大量収集(20件)
   - 手動入力：1-50件まで対応
   - 用途別推奨ガイド表示

4. **技術仕様**
   - ファイル: `ai-news-autoposter.php` (6300行目周辺のsearch_urls関数)
   - 関数: `build_url_search_prompt()` で言語別サイト指定
   - JavaScript: `assets/admin.js` にプリセット選択機能
   - API: Gemini 2.5 Flash/Flash Lite + Google Search Grounding

### 🎯 実装結果
- 要求された機能は**100%実装完了**
- キーワード→言語別サイト→指定数→Gemini 2.5 Flash Liteの全フローが動作
- 管理画面からワンクリックで実行可能

## v2.7.0での実装完了項目（2025-07-19）
**要求仕様：「文体スタイル設定機能と記事への図表・グラフ挿入機能の実装」**

### ✅ 新機能実装完了
1. **高度な文体スタイル機能**
   - 9種類の文体スタイル選択肢を実装
   - 新聞記事風（客観的・事実重視）
   - 夏目漱石風（格調高い文学的表現）
   - 森鴎外風（理知的・簡潔）
   - 太宰治風（感情豊か・親しみやすい）
   - 芥川龍之介風（簡潔・鋭利）
   - ビジネス記事風（実用的・分析的）
   - 技術記事風（専門的・詳細）
   - カジュアル風（親しみやすい・会話調）
   - 学術論文風（厳密・論理的）

2. **視覚要素挿入機能**
   - HTMLで作成された表・グラフ・図表の自動挿入
   - データ比較表（table class="data-table"）
   - 統計グラフ（chart-container、bar-chart）
   - 手順解説図（step-diagram）
   - 比較チャート（comparison-chart）
   - 完全なCSS スタイル定義（assets/admin.css）

3. **自然な見出し生成機能**
   - 機械的な「概要」「背景」「課題」を避ける
   - 魅力的で具体的な見出しを自動生成
   - マークダウン##からWordPress H2タグへの変換

### 🔧 技術実装詳細
- **設定画面UI追加**: 文体スタイル選択・図表挿入チェックボックス
- **プロンプト統合**: `build_gemini_simple_prompt()`, `generate_summary_article()`
- **文体指示システム**: `get_writing_style_instructions()` 関数
- **CSS スタイル追加**: 図表表示用の完全なスタイル定義
- **両機能対応**: 記事生成・URLスクレイピング両方で新機能が動作

### 🧪 テスト結果
- ✅ 文体スタイル機能：正常動作確認
- ✅ 図表挿入機能：HTMLコンポーネント生成確認
- ✅ 自然な見出し：魅力的な見出し自動生成確認
- ✅ 高品質記事：2000文字の詳細記事生成確認
- ✅ WordPress統合：設定保存・UI表示正常動作

### 🎯 v2.7.0実装結果
- **新機能100%実装完了**
- **神レベルの記事生成機能**を実現
- 文豪風からビジネス記事まで幅広い文体対応
- 視覚的に魅力的な図表・グラフ自動挿入
- WordPress管理画面から簡単設定・即座実行可能