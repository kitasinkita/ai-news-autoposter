const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ 
    headless: false,
    slowMo: 1000
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    console.log('📊 WordPress記事確認開始...');
    
    // WordPressログイン
    await page.goto('http://localhost:8090/wp-admin');
    await page.fill('input[name="log"]', 'admin');
    await page.fill('input[name="pwd"]', 'admin123');
    await page.click('input[type="submit"]');
    await page.waitForLoadState('networkidle');
    console.log('✅ ログイン成功');

    // 投稿一覧を確認
    await page.goto('http://localhost:8090/wp-admin/edit.php?post_status=all');
    await page.waitForLoadState('networkidle');
    
    // 記事リストを取得
    const articles = await page.evaluate(() => {
      const rows = document.querySelectorAll('#the-list tr');
      const articleList = [];
      
      rows.forEach((row, index) => {
        const titleElement = row.querySelector('.row-title');
        const statusElement = row.querySelector('.post-state') || row.querySelector('.post-status');
        const dateElement = row.querySelector('.date');
        
        if (titleElement) {
          articleList.push({
            id: index + 1,
            title: titleElement.textContent.trim(),
            status: statusElement ? statusElement.textContent.trim() : '公開済み',
            date: dateElement ? dateElement.textContent.trim() : '不明',
            editUrl: titleElement.href
          });
        }
      });
      
      return articleList;
    });

    console.log(`\n📊 現在のWordPress記事一覧（${articles.length}件）`);
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    if (articles.length === 0) {
      console.log('❌ 記事が1件も見つかりませんでした。');
    } else {
      articles.forEach((article, index) => {
        console.log(`${index + 1}. タイトル: "${article.title}"`);
        console.log(`   ステータス: ${article.status}`);
        console.log(`   日時: ${article.date}`);
        console.log('');
      });
    }

    // 最新の記事を詳しく確認
    if (articles.length > 0) {
      console.log('📄 最新記事の詳細確認...');
      const latestArticle = articles[0];
      
      if (latestArticle.editUrl) {
        await page.goto(latestArticle.editUrl);
        await page.waitForLoadState('networkidle');
        
        const articleDetails = await page.evaluate(() => {
          const title = document.querySelector('#title')?.value || '';
          const content = document.querySelector('#content')?.value || '';
          
          return {
            title,
            contentLength: content.length,
            hasContent: content.length > 100,
            contentPreview: content.substring(0, 200) + (content.length > 200 ? '...' : '')
          };
        });
        
        console.log(`タイトル: "${articleDetails.title}"`);
        console.log(`コンテンツ長: ${articleDetails.contentLength}文字`);
        console.log(`有効なコンテンツ: ${articleDetails.hasContent ? '✅' : '❌'}`);
        if (articleDetails.contentLength > 0) {
          console.log(`コンテンツプレビュー: "${articleDetails.contentPreview}"`);
        }
      }
    }

    // 下書きと公開済みの内訳
    const draftCount = articles.filter(a => a.status.includes('下書き') || a.status.includes('Draft')).length;
    const publishedCount = articles.filter(a => !a.status.includes('下書き') && !a.status.includes('Draft')).length;
    
    console.log('\n📊 記事ステータス内訳');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log(`📝 下書き: ${draftCount}件`);
    console.log(`📰 公開済み: ${publishedCount}件`);
    console.log(`📊 合計: ${articles.length}件`);

    console.log('\n✅ WordPress記事確認完了');

  } catch (error) {
    console.error('❌ エラー:', error);
  } finally {
    await browser.close();
  }
})();