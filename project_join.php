<?php
require_once __DIR__.'/includes/bootstrap.php';$u=login_user();
?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?=h(page_title('プロジェクトに参加'))?></title><link rel="stylesheet" href="css/style.css"></head><body><div class="card narrow"><h1>プロジェクトに参加</h1><p>現在の仕様では、管理者があなたのメールアドレスをメンバー管理から許可すると参加済みになります。</p><p>あなたのメールアドレス：</p><strong><?=h($u)?></strong><p><a class="btn" href="MyPage.php">マイページへ戻る</a></p></div></body></html>
