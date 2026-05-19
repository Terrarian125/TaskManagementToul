<?php
date_default_timezone_set('Asia/Tokyo');

// 1. URLからパラメータ（タスクID）を取得
$task_id = isset($_GET['id']) ? trim($_GET['id']) : '';
$task_dir = "Data/" . $task_id;
$csv_path = 'Data/task_list.csv';

// --- 【追加機能】タスク削除処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    if (file_exists($csv_path)) {
        $rows = [];
        if (($handle = fopen($csv_path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== FALSE) {
                // 削除対象のタスクID以外の行だけを残す
                if (count($data) >= 1 && $data[0] === $task_id) {
                    continue; 
                }
                $rows[] = $data;
            }
            fclose($handle);
        }
        // CSVを上書き（対象の行が消える）
        $handle = fopen($csv_path, "w");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ",", '"', "\\");
        }
        fclose($handle);
    }

    // フォルダとその中身（memo.txtや添付ファイル）をまるごと削除する関数
    function deleteDir($dirPath) {
        if (!is_dir($dirPath)) return;
        $objects = scandir($dirPath);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dirPath . "/" . $object) && !is_link($dirPath . "/" . $object)) {
                    deleteDir($dirPath . "/" . $object);
                } else {
                    unlink($dirPath . "/" . $object);
                }
            }
        }
        rmdir($dirPath);
    }
    deleteDir($task_dir);

    // 一覧画面へ戻る
    header('Location: index.php');
    exit;
}

// タスクIDが空、またはフォルダが存在しない場合は一覧へ戻す
if ($task_id === '' || !file_exists($task_dir)) {
    header('Location: index.php');
    exit;
}

// 2. CSVからこのタスクの基本情報を検索して取得
$task_info = null;
if (file_exists($csv_path)) {
    if (($handle = fopen($csv_path, "r")) !== FALSE) {
        fgetcsv($handle, 0, ",", '"', "\\"); // ヘッダーをスキップ
        while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== FALSE) {
            if (count($data) < 7) continue;
            if ($data[0] === $task_id) {
                $task_info = [
                    'id'          => $data[0],
                    'creator_id'  => $data[1],
                    'name'        => $data[2],
                    'summary'     => $data[3],
                    'create_date' => $data[4],
                    'update_date' => $data[5],
                    'status'      => $data[6]
                ];
                break;
            }
        }
        fclose($handle);
    }
}

if (!$task_info) {
    header('Location: index.php');
    exit;
}

$memo_path = $task_dir . "/memo.txt";
$error_msg = "";
$success_msg = "";

// --- 【修正・機能追加】詳細メモ ＆ 状態（ステータス）の保存処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_memo') {
    $memo_content = $_POST['memo'];
    $new_status   = $_POST['status']; // 画面から送られてきた新しい状態

    // メモファイルの保存
    if (file_put_contents($memo_path, $memo_content) !== FALSE) {
        $success_msg = "変更を優しく保存しました✨";
        
        // CSV側を上書きして「状態」と「更新日時」を更新する
        $rows = [];
        if (($handle = fopen($csv_path, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== FALSE) {
                if (count($data) >= 7 && $data[0] === $task_id) {
                    $data[5] = date('Y/m/d'); // 更新日時
                    $data[6] = $new_status;   // 新しい状態に書き換え
                }
                $rows[] = $data;
            }
            fclose($handle);
        }
        
        $handle = fopen($csv_path, "w");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ",", '"', "\\");
        }
        fclose($handle);
        
        // 表示用データも更新
        $task_info['update_date'] = date('Y/m/d');
        $task_info['status'] = $new_status;
    } else {
        $error_msg = "保存に失敗しちゃいました。";
    }
}

// 3. 添付ファイルのアップロード処理
$attachments_dir = $task_dir . "/attachments";
if (!file_exists($attachments_dir)) {
    mkdir($attachments_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['attachment_file']['name']);
        if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $attachments_dir . "/" . $file_name)) {
            $success_msg = "ファイルを無事に追加しました！📎";
        } else {
            $error_msg = "ファイルの追加に失敗しました。";
        }
    }
}

// ファイルからメモを読み込む
$memo_text = file_exists($memo_path) ? file_get_contents($memo_path) : "";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($task_info['name']); ?> - 詳細</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .detail-grid { display: flex; gap: 30px; margin-top: 20px; }
        .left-col { flex: 1.5; }
        .right-col { flex: 1; background-color: #fbfbfa; padding: 20px; border-radius: 18px; border: 1px solid #ded9cc; }
        .meta-list { list-style: none; padding: 0; margin: 0 0 20px 0; }
        .meta-list li { padding: 8px 0; border-bottom: 1px dashed #e0dbcd; font-size: 0.95rem; display: flex; align-items: center; }
        .meta-list strong { color: #615c51; width: 110px; display: inline-block; }
        .memo-area { width: 100%; height: 350px; padding: 15px; border-radius: 12px; border: 1px solid #ded9cc; background-color: #ffffff; box-sizing: border-box; font-size: 1rem; font-family: inherit; line-height: 1.6; resize: vertical; color: #4a4a4a; }
        .file-list { margin-top: 15px; }
        .file-item { display: flex; align-items: center; justify-content: space-between; padding: 10px; background: #ffffff; border: 1px solid #eef1f2; border-radius: 10px; margin-bottom: 8px; }
        .file-link { color: #5c574c; font-weight: bold; text-decoration: none; }
        .file-link:hover { color: #a3d2ca; text-decoration: underline; }
        .msg-box { padding: 12px; border-radius: 10px; margin-bottom: 15px; font-weight: bold; font-size: 0.9rem; }
        .msg-success { background-color: #d1ebd1; color: #2b542b; }
        .msg-error { background-color: #ffd8cc; color: #803b26; }
        
        /* 削除ボタン用のポップで警告色なスタイル */
        .btn-danger { background-color: #ffd8cc; color: #803b26; border: 1px solid #ecc0b3; }
        .btn-danger:hover { background-color: #fbc6b8; }
    </style>
</head>
<body>

<div class="app-container">

    <header class="header-area">
        <div>
            <span class="status-badge <?php 
                echo ($task_info['status'] === '進行中' || $task_info['status'] === 'doing') ? 'status-doing' : (($task_info['status'] === '完了' || $task_info['status'] === 'done') ? 'status-done' : 'status-todo'); 
            ?>"><?php echo htmlspecialchars($task_info['status']); ?></span>
            <h2 style="display: inline; margin-left: 10px; color: #4a4a4a;"><?php echo htmlspecialchars($task_info['name']); ?></h2>
        </div>
        <a href="index.php" class="btn btn-secondary">← 一覧に戻る</a>
    </header>

    <?php if ($success_msg): ?><div class="msg-box msg-success"><?php echo $success_msg; ?></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="msg-box msg-error"><?php echo $error_msg; ?></div><?php endif; ?>

    <div class="detail-grid">
        
        <div class="left-col">
            <form action="detail.php?id=<?php echo urlencode($task_id); ?>" method="POST">
                <input type="hidden" name="action" value="save_memo">
                
                <h3 style="color: #615c51; margin-top: 0;">📝 詳細メモ</h3>
                <p style="font-size: 0.85rem; color: #999; margin-top: -10px;">作業ログや手順などを自由に入力してください。</p>
                <textarea name="memo" class="memo-area" placeholder="ここに長文のメモを書き込めます..."><?php echo htmlspecialchars($memo_text); ?></textarea>
                
                <div style="margin-top: 20px; display: flex; align-items: center; gap: 15px; background: #fbfbfa; padding: 15px; border-radius: 12px; border: 1px solid #ded9cc;">
                    <label for="status" style="font-weight: bold; color: #615c51;">現在の状態を変更：</label>
                    <select id="status" name="status" style="padding: 8px 15px; border-radius: 8px; border: 1px solid #ded9cc; font-size: 0.95rem; background: #fff;">
                        <option value="未着手" <?php if($task_info['status'] === '未着手') echo 'selected'; ?>>未着手</option>
                        <option value="進行中" <?php if($task_info['status'] === '進行中' || $task_info['status'] === 'doing') echo 'selected'; ?>>進行中</option>
                        <option value="完了" <?php if($task_info['status'] === '完了' || $task_info['status'] === 'done') echo 'selected'; ?>>完了</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">この内容で保存する</button>
                </div>
            </form>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 2px dashed #e0dbcd;">
                <form action="detail.php?id=<?php echo urlencode($task_id); ?>" method="POST" onsubmit="return confirm('このタスクと、フォルダ内のファイル（メモや添付）をすべて消去します。本当によろしいですか？（元には戻せません）');">
                    <input type="hidden" name="action" value="delete_task">
                    <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-weight: bold; border-radius: 12px; cursor: pointer;">⚠️ このタスクを削除する</button>
                </form>
            </div>
        </div>

        <div class="right-col">
            <h3 style="color: #615c51; margin-top: 0;">📊 基本情報</h3>
            <ul class="meta-list">
                <li><strong>タスクID:</strong> <?php echo htmlspecialchars($task_info['id']); ?></li>
                <li><strong>作成者ID:</strong> <?php echo htmlspecialchars($task_info['creator_id']); ?></li>
                <li><strong>ざっくり内容:</strong> <?php echo htmlspecialchars($task_info['summary']); ?></li>
                <li><strong>作成日時:</strong> <?php echo htmlspecialchars($task_info['create_date']); ?></li>
                <li><strong>更新日時:</strong> <?php echo htmlspecialchars($task_info['update_date']); ?></li>
            </ul>

            <h3 style="color: #615c51; margin-top: 30px;">📎 添付ファイルの置き場</h3>
            
            <form action="detail.php?id=<?php echo urlencode($task_id); ?>" method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                <input type="hidden" name="action" value="upload_file">
                <input type="file" name="attachment_file" required style="font-size: 0.85rem; display: block; margin-bottom: 8px;">
                <button type="submit" class="btn btn-secondary" style="padding: 6px 15px; font-size: 0.85rem;">このファイルをフォルダに追加</button>
            </form>

            <div class="file-list">
                <strong>フォルダ内のファイル一覧:</strong>
                <?php
                $files = array_diff(scandir($attachments_dir), array('.', '..'));
                if (empty($files)):
                ?>
                    <p style="font-size: 0.85rem; color: #999; margin-top: 10px;">添付されたファイルはありません。</p>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <?php $file_url = $attachments_dir . "/" . $file; ?>
                        <div class="file-item">
                            <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="file-link">
                                📄 <?php echo htmlspecialchars($file); ?>
                            </a>
                            <span style="font-size: 0.75rem; color: #b0aba0;">
                                (<?php echo round(filesize($file_url) / 1024, 1); ?> KB)
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>