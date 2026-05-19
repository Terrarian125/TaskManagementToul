<?php
date_default_timezone_set('Asia/Tokyo');

$error = '';
$success = false;

// フォームが送信されたときの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id     = trim($_POST['task_id']);
    $task_name   = trim($_POST['task_name']);
    $creator_id  = trim($_POST['creator_id']);
    $summary     = trim($_POST['summary']);
    $status      = $_POST['status'];

    // 簡単な入力チェック
    if ($task_id === '' || $task_name === '' || $creator_id === '') {
        $error = 'タスクID、作成者ID、タスク名は必須入力項目です。';
    } else {
        // アルファベットと数字、アンダーバー以外のフォルダ名トラブルを防ぐバリデーション
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $task_id)) {
            $error = 'タスクIDには半角英数字、ハイフン、アンダーバーのみ使用できます。';
        } else {
            $csv_path = 'Data/task_list.csv';
            
            // 既存のタスクIDと重複していないかチェック
            $id_exists = false;
            if (file_exists($csv_path)) {
                if (($handle = fopen($csv_path, "r")) !== FALSE) {
                    fgetcsv($handle, 0, ",", '"', "\\"); // ヘッダー
                    while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== FALSE) {
                        if ($data[0] === $task_id) {
                            $id_exists = true;
                            break;
                        }
                    }
                    fclose($handle);
                }
            }

            if ($id_exists) {
                $error = 'そのタスクIDは既に存在します。別のIDを指定してください。';
            } else {
                // 1. タスクごとの個別フォルダを自動生成 (Data/Task_XXXX/)
                $task_dir = "Data/" . $task_id;
                if (!file_exists($task_dir)) {
                    mkdir($task_dir, 0777, true);
                    mkdir($task_dir . "/attachments", 0777, true); // 添付ファイル用
                }

                // 2. 空の詳細メモファイル (memo.txt) をあらかじめ作っておく
                file_put_contents($task_dir . "/memo.txt", "");

                // 3. サムネイル画像がアップロードされていれば保存
                if (isset($_FILES['thumb']) && $_FILES['thumb']['error'] === UPLOAD_ERR_OK) {
                    // 【修正点】mime_content_type を使わず、拡張子（小文字に統一）で判定
                    $extension = strtolower(pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION));
                    
                    if ($extension === 'png' || $extension === 'jpg' || $extension === 'jpeg') {
                        move_uploaded_file($_FILES['thumb']['tmp_name'], $task_dir . "/thumb.png");
                    }
                }

                // 4. CSVへの追記 (UTF-8で書き込み)
                $now = date('Y/m/d');
                $new_line = [$task_id, $creator_id, $task_name, $summary, $now, $now, $status];

                $handle = fopen($csv_path, 'a');
                fputcsv($handle, $new_line, ",", '"', "\\");
                fclose($handle);

                $success = true;
                // メイン画面へ自動で戻る
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規タスク作成</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #615c51; }
        .form-control { width: 100%; padding: 10px; border-radius: 12px; border: 1px solid #ded9cc; background-color: #fbfbfa; box-sizing: border-box; font-size: 1rem; }
        .error-msg { background-color: #ffd8cc; color: #803b26; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: bold; }
    </style>
</head>
<body>

<div class="app-container" style="max-width: 600px;">
    <header class="header-area">
        <h2>✨ 新しいタスクの作成</h2>
    </header>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="create.php" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label for="task_id">タスクID (半角英数字 例: Task_00002)</label>
            <input type="text" id="task_id" name="task_id" class="form-control" placeholder="Task_00002" required>
        </div>

        <div class="form-group">
            <label for="creator_id">作成者ID</label>
            <input type="text" id="creator_id" name="creator_id" class="form-control" placeholder="User_A" required>
        </div>

        <div class="form-group">
            <label for="task_name">タスク名</label>
            <input type="text" id="task_name" name="task_name" class="form-control" placeholder="例: データ構造の設計" required>
        </div>

        <div class="form-group">
            <label for="summary">ざっくり内容</label>
            <input type="text" id="summary" name="summary" class="form-control" placeholder="例: メモを個別ファイル化する設計の落とし込み">
        </div>

        <div class="form-group">
            <label for="status">最初の状態</label>
            <select id="status" name="status" class="form-control" style="height: 45px;">
                <option value="未着手">未着手</option>
                <option value="進行中">進行中</option>
                <option value="完了">完了</option>
            </select>
        </div>

        <div class="form-group">
            <label for="thumb">サムネイル画像 (PNG または JPG)</label>
            <input type="file" id="thumb" name="thumb" accept="image/png, image/jpeg" style="font-size: 1rem;">
        </div>

        <div style="margin-top: 30px; display: flex; gap: 15px;">
            <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px;">この内容で作成する</button>
            <a href="index.php" class="btn btn-secondary" style="flex: 1; padding: 12px; line-height: 20px;">戻る</a>
        </div>
    </form>
</div>

</body>
</html>