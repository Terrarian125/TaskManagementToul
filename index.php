<?php
// ==========================================
// 1. 環境設定・タイムゾーン
// ==========================================
date_default_timezone_set('Asia/Tokyo');

// 曜日の日本語配列定義
$weeks = ["日", "月", "火", "水", "木", "金", "土"];
$display_date = date('Y年m月d日') . '(' . $weeks[date('w')] . ') ' . date('H:i');

// ==========================================
// 2. CSVファイルの読み込み（純粋なUTF-8としてストレートに読み込む）
// ==========================================
$csv_path = 'Data/task_list.csv';
$task_list = [];

if (file_exists($csv_path)) {
    if (($handle = fopen($csv_path, "r")) !== FALSE) {
        // 1行目のヘッダー（列名）をスキップ
        fgetcsv($handle, 0, ",", '"', "\\");
        
        // 2行目以降のデータを1行ずつ読み込む（余計なiconv変換はすべて撤去！）
        while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== FALSE) {
            if (count($data) < 7) continue;

            $task_list[] = [
                'id'          => $data[0],
                'creator_id'  => $data[1],
                'name'        => $data[2],
                'summary'     => $data[3],
                'create_date' => $data[4],
                'update_date' => $data[5],
                'status'      => $data[6]
            ];
        }
        fclose($handle);
    }
}

// ==========================================
// 3. 検索機能 & ソート機能のロジック
// ==========================================
$sort_key = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// 検索キーワードの処理（UTF-8同士なので、正規表現で確実にヒットします）
if ($search_query !== '') {
    $filtered_list = [];
    foreach ($task_list as $task) {
        if (preg_match('/' . preg_quote($search_query, '/') . '/ui', $task['name']) || 
            preg_match('/' . preg_quote($search_query, '/') . '/ui', $task['summary']) ||
            preg_match('/' . preg_quote($search_query, '/') . '/ui', $task['id'])) {
            $filtered_list[] = $task;
        }
    }
    $task_list = $filtered_list;
}

// ソートの実行処理
if (in_array($sort_key, ['id', 'creator_id', 'name', 'summary', 'create_date', 'update_date', 'status'])) {
    usort($task_list, function($a, $b) use ($sort_key) {
        return strcmp($a[$sort_key], $b[$sort_key]);
    });
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>おだやかタスク管理</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .sort-link {
            color: #615c51;
            text-decoration: none;
            display: inline-block;
            position: relative;
            padding-right: 12px;
        }
        .sort-link::after {
            content: ' ↕';
            font-size: 0.75rem;
            color: #b0aba0;
        }
        .sort-link:hover {
            color: #a3d2ca;
        }
    </style>
</head>
<body>

<div class="app-container">

    <header class="header-area">
        <div class="user-info">
            <strong><?php echo htmlspecialchars($display_date); ?></strong> ｜ ユーザー: 管理者 [ <a href="logout.php">ログアウト</a> ]
        </div>
        <div>
            <form action="index.php" method="GET" style="display: inline-block;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="キーワードで検索..." style="padding: 8px 15px; border-radius: 12px; border: 1px solid #ded9cc; margin-right: 10px; background-color: #fbfbfa;">
                <?php if ($sort_key): ?><input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_key); ?>"><?php endif; ?>
            </form>
            <a href="create.php" class="btn btn-primary">「タスク作成」</a>
        </div>
    </header>

    <table class="task-table">
        <thead>
            <tr>
                <th>サムネイル</th>
                <th><a class="sort-link" href="?sort=status&search=<?php echo urlencode($search_query); ?>">状態</a></th>
                <th><a class="sort-link" href="?sort=id&search=<?php echo urlencode($search_query); ?>">タスクID</a></th>
                <th><a class="sort-link" href="?sort=creator_id&search=<?php echo urlencode($search_query); ?>">作成者ID</a></th>
                <th><a class="sort-link" href="?sort=name&search=<?php echo urlencode($search_query); ?>">タスク名</a></th>
                <th><a class="sort-link" href="?sort=summary&search=<?php echo urlencode($search_query); ?>">ざっくり内容</a></th>
                <th><a class="sort-link" href="?sort=create_date&search=<?php echo urlencode($search_query); ?>">作成日時</a></th>
                <th><a class="sort-link" href="?sort=update_date&search=<?php echo urlencode($search_query); ?>">更新日時</a></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($task_list)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: #999;">該当するタスクが見つかりません。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($task_list as $task): ?>
                    <?php 
                        $thumb_path = "Data/" . $task['id'] . "/thumb.png";
                        
                        $status_class = 'status-todo';
                        if ($task['status'] === '進行中' || $task['status'] === 'doing') $status_class = 'status-doing';
                        if ($task['status'] === '完了' || $task['status'] === 'done')   $status_class = 'status-done';
                    ?>
                    <tr class="task-row" onclick="location.href='detail.php?id=<?php echo urlencode($task['id']); ?>'" style="cursor: pointer;">
                        <td>
                            <div class="thumb-box">
                                <?php if (file_exists($thumb_path)): ?>
                                    <img src="<?php echo htmlspecialchars($thumb_path); ?>?v=<?php echo time(); ?>" alt="サムネ">
                                <?php else: ?>
                                    <span style="font-size: 11px; color: #b0aba0;">No Image</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($task['status']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($task['id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($task['creator_id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($task['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($task['summary']); ?></td>
                        <td><?php echo htmlspecialchars($task['create_date']); ?></td>
                        <td><?php echo htmlspecialchars($task['update_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>