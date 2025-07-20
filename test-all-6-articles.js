const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ 
    headless: false,
    slowMo: 1000
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  try {
    console.log('🎯 6記事生成テスト開始（タブ1×2, タブ2×2, タブ3×2）');
    
    // WordPressログイン
    await page.goto('http://localhost:8090/wp-admin');
    await page.fill('input[name="log"]', 'admin');
    await page.fill('input[name="pwd"]', 'admin123');
    await page.click('input[type="submit"]');
    await page.waitForLoadState('networkidle');
    console.log('✅ ログイン成功');

    // 初期記事数確認
    await page.goto('http://localhost:8090/wp-admin/edit.php?post_status=all');
    await page.waitForLoadState('networkidle');
    const initialCount = await page.locator('#the-list tr').count();
    console.log(`📊 テスト開始時の記事数: ${initialCount}件`);

    const testResults = [];
    let successCount = 0;

    // テスト1: タブ1（キーワード記事）- 1回目
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📝 テスト1: タブ1（キーワード記事）- 1回目');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    const result1 = await testTab1Generation(page, 'AI技術', 1);
    testResults.push(result1);
    if (result1.success) successCount++;

    // テスト2: タブ1（キーワード記事）- 2回目
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📝 テスト2: タブ1（キーワード記事）- 2回目');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    const result2 = await testTab1Generation(page, 'テクノロジー', 2);
    testResults.push(result2);
    if (result2.success) successCount++;

    // テスト3: タブ2（フリープロンプト）- 1回目
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📝 テスト3: タブ2（フリープロンプト）- 1回目');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    const result3 = await testTab2Generation(page, '環境問題について詳しく解説してください', 3);
    testResults.push(result3);
    if (result3.success) successCount++;

    // テスト4: タブ2（フリープロンプト）- 2回目
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📝 テスト4: タブ2（フリープロンプト）- 2回目');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    const result4 = await testTab2Generation(page, 'スマートフォンの最新動向について記事を書いてください', 4);
    testResults.push(result4);
    if (result4.success) successCount++;

    // テスト5: タブ3（URL記事）- 1回目
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📝 テスト5: タブ3（URL記事）- 1回目');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    const result5 = await testTab3Generation(page, 'キャンプ', 5);
    testResults.push(result5);
    if (result5.success) successCount++;

    // テスト6: タブ3（URL記事）- 2回目
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📝 テスト6: タブ3（URL記事）- 2回目');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    
    const result6 = await testTab3Generation(page, '電気自動車', 6);
    testResults.push(result6);
    if (result6.success) successCount++;

    // 最終記事数確認
    await page.goto('http://localhost:8090/wp-admin/edit.php?post_status=all');
    await page.waitForLoadState('networkidle');
    const finalCount = await page.locator('#the-list tr').count();
    const newArticles = finalCount - initialCount;

    console.log('\n\n🎯 6記事生成テスト最終結果');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log(`✅ 成功: ${successCount}/6件`);
    console.log(`📈 成功率: ${Math.round(successCount / 6 * 100)}%`);
    console.log(`📊 初期記事数: ${initialCount}件`);
    console.log(`📊 最終記事数: ${finalCount}件`);
    console.log(`📊 生成された記事: ${newArticles}件`);

    // 詳細結果
    testResults.forEach((result, index) => {
      console.log(`  テスト${index+1} (${result.testName}): ${result.success ? '✅ 成功' : `❌ 失敗 (${result.reason})`}`);
    });

    if (newArticles === 6) {
      console.log('\n🌟 完璧！6記事すべてがWordPressに保存されました！');
    } else if (newArticles >= 4) {
      console.log('\n✨ 良好！大部分の記事が生成されています。');
    } else {
      console.log('\n⚠️ 問題あり。多くの記事で保存に失敗しています。');
    }

    console.log('\n✅ 6記事生成テスト完了');

  } catch (error) {
    console.error('❌ テスト中にエラー:', error);
  } finally {
    await browser.close();
  }
})();

// タブ1のテスト関数
async function testTab1Generation(page, keyword, testNum) {
  try {
    await page.goto('http://localhost:8090/wp-admin/admin.php?page=ai-news-autoposter-settings');
    await page.waitForLoadState('networkidle');
    
    // タブ1を選択
    await page.click('button[data-tab="tab-keyword-articles"]');
    await page.waitForTimeout(2000);
    
    // キーワード設定
    await page.fill('input[name="search_keywords"]', keyword);
    console.log(`⚙️ キーワード設定: ${keyword}`);
    
    // 記事生成実行
    await page.click('#generate-keyword-article');
    console.log('🚀 キーワード記事生成開始...');
    
    // 成功通知を待機
    const startTime = Date.now();
    for (let i = 0; i < 72; i++) { // 6分間待機
      await page.waitForTimeout(5000);
      
      const hasSuccess = await page.isVisible('.notice-success, .updated');
      const hasError = await page.isVisible('.notice-error, .error');
      
      if (hasSuccess) {
        const elapsedTime = Math.round((Date.now() - startTime) / 1000);
        console.log(`✅ テスト${testNum}: タブ1記事生成成功！（${elapsedTime}秒）`);
        return { success: true, testName: `タブ1-${keyword}`, elapsedTime };
      }
      
      if (hasError) {
        const errorMsg = await page.textContent('.notice-error, .error');
        console.log(`❌ テスト${testNum}: タブ1記事生成エラー - ${errorMsg}`);
        return { success: false, testName: `タブ1-${keyword}`, reason: errorMsg };
      }
      
      if (i % 12 === 11) { // 1分ごとに進捗表示
        const elapsed = Math.round((Date.now() - startTime) / 1000);
        console.log(`  ⏳ 記事生成中... (${elapsed}秒経過)`);
      }
    }
    
    const elapsedTime = Math.round((Date.now() - startTime) / 1000);
    console.log(`⏰ テスト${testNum}: タブ1記事生成タイムアウト（${elapsedTime}秒）`);
    return { success: false, testName: `タブ1-${keyword}`, reason: 'タイムアウト' };
    
  } catch (error) {
    console.error(`❌ テスト${testNum}: タブ1テスト中にエラー`, error.message);
    return { success: false, testName: `タブ1-${keyword}`, reason: error.message };
  }
}

// タブ2のテスト関数
async function testTab2Generation(page, prompt, testNum) {
  try {
    await page.goto('http://localhost:8090/wp-admin/admin.php?page=ai-news-autoposter-settings');
    await page.waitForLoadState('networkidle');
    
    // タブ2を選択
    await page.click('button[data-tab="tab-free-prompt"]');
    await page.waitForTimeout(2000);
    
    // フリープロンプト設定
    await page.fill('textarea[name="free_prompt"]', prompt);
    console.log(`⚙️ フリープロンプト設定: ${prompt.substring(0, 30)}...`);
    
    // 記事生成実行
    await page.click('#generate-free-prompt-article');
    console.log('🚀 フリープロンプト記事生成開始...');
    
    // 成功通知を待機
    const startTime = Date.now();
    for (let i = 0; i < 72; i++) { // 6分間待機
      await page.waitForTimeout(5000);
      
      const hasSuccess = await page.isVisible('.notice-success, .updated');
      const hasError = await page.isVisible('.notice-error, .error');
      
      if (hasSuccess) {
        const elapsedTime = Math.round((Date.now() - startTime) / 1000);
        console.log(`✅ テスト${testNum}: タブ2記事生成成功！（${elapsedTime}秒）`);
        return { success: true, testName: `タブ2-フリープロンプト`, elapsedTime };
      }
      
      if (hasError) {
        const errorMsg = await page.textContent('.notice-error, .error');
        console.log(`❌ テスト${testNum}: タブ2記事生成エラー - ${errorMsg}`);
        return { success: false, testName: `タブ2-フリープロンプト`, reason: errorMsg };
      }
      
      if (i % 12 === 11) { // 1分ごとに進捗表示
        const elapsed = Math.round((Date.now() - startTime) / 1000);
        console.log(`  ⏳ 記事生成中... (${elapsed}秒経過)`);
      }
    }
    
    const elapsedTime = Math.round((Date.now() - startTime) / 1000);
    console.log(`⏰ テスト${testNum}: タブ2記事生成タイムアウト（${elapsedTime}秒）`);
    return { success: false, testName: `タブ2-フリープロンプト`, reason: 'タイムアウト' };
    
  } catch (error) {
    console.error(`❌ テスト${testNum}: タブ2テスト中にエラー`, error.message);
    return { success: false, testName: `タブ2-フリープロンプト`, reason: error.message };
  }
}

// タブ3のテスト関数
async function testTab3Generation(page, keyword, testNum) {
  try {
    await page.goto('http://localhost:8090/wp-admin/admin.php?page=ai-news-autoposter-settings');
    await page.waitForLoadState('networkidle');
    
    // タブ3を選択
    await page.click('button[data-tab="tab-url-articles"]');
    await page.waitForTimeout(2000);
    
    // キーワード設定
    await page.fill('#scraping_keyword', keyword);
    console.log(`⚙️ URL検索キーワード設定: ${keyword}`);
    
    // URL検索開始
    await page.click('#search-urls-btn');
    console.log('🔍 URL検索開始...');
    
    // URL検索完了を待機
    let urlsFound = false;
    for (let i = 0; i < 20; i++) { // 100秒待機
      await page.waitForTimeout(5000);
      
      const urlCount = await page.locator('#found-urls-list .url-item').count();
      if (urlCount > 0) {
        console.log(`✅ URL検索完了: ${urlCount}件のURLを発見`);
        urlsFound = true;
        break;
      }
      
      if (i % 4 === 3) { // 20秒ごとに進捗表示
        console.log(`  ⏳ URL検索中... (${(i + 1) * 5}秒経過)`);
      }
    }
    
    if (!urlsFound) {
      console.log(`❌ テスト${testNum}: URL検索失敗 - ${keyword}`);
      return { success: false, testName: `タブ3-${keyword}`, reason: 'URL検索タイムアウト' };
    }
    
    // URLを選択
    const checkboxes = await page.locator('#found-urls-list input[type="checkbox"]');
    await checkboxes.first().check();
    console.log(`📋 1件のURLを選択`);
    
    // コンテンツ取得
    await page.click('#scrape-selected-urls-btn');
    console.log('🔄 コンテンツ取得中...');
    
    // まとめ記事生成ボタンが表示されるまで待機
    await page.waitForSelector('#generate-summary-btn', { state: 'visible', timeout: 60000 });
    console.log('✅ コンテンツ取得完了');
    
    // まとめ記事生成
    await page.click('#generate-summary-btn');
    console.log('🚀 まとめ記事生成開始...');
    
    // 記事生成完了を待機
    const startTime = Date.now();
    for (let i = 0; i < 60; i++) { // 5分間待機
      await page.waitForTimeout(5000);
      
      const hasSuccess = await page.isVisible('.notice-success, .updated');
      const hasError = await page.isVisible('.notice-error, .error');
      
      if (hasSuccess) {
        const elapsedTime = Math.round((Date.now() - startTime) / 1000);
        console.log(`✅ テスト${testNum}: タブ3記事生成成功！（${elapsedTime}秒）`);
        return { success: true, testName: `タブ3-${keyword}`, elapsedTime };
      }
      
      if (hasError) {
        const errorMsg = await page.textContent('.notice-error, .error');
        console.log(`❌ テスト${testNum}: タブ3記事生成エラー - ${errorMsg}`);
        return { success: false, testName: `タブ3-${keyword}`, reason: errorMsg };
      }
      
      if (i % 6 === 5) { // 30秒ごとに進捗表示
        const elapsed = Math.round((Date.now() - startTime) / 1000);
        console.log(`  ⏳ 記事生成中... (${elapsed}秒経過)`);
      }
    }
    
    const elapsedTime = Math.round((Date.now() - startTime) / 1000);
    console.log(`⏰ テスト${testNum}: タブ3記事生成タイムアウト（${elapsedTime}秒）`);
    return { success: false, testName: `タブ3-${keyword}`, reason: 'タイムアウト' };
    
  } catch (error) {
    console.error(`❌ テスト${testNum}: タブ3テスト中にエラー`, error.message);
    return { success: false, testName: `タブ3-${keyword}`, reason: error.message };
  }
}