<?php

require_once __DIR__.'/includes/bootstrap.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

$pid = (int)(
    $_GET['project_id'] ?? 0
);

$taskId = trim(
    $_GET['task_id'] ?? ''
);


/*
 * プロジェクトメンバー以外はアクセス不可
 */
$u = require_member($pid);


/*
 * タスクの存在確認
 */
$s = db()->prepare("
    SELECT
        t.updated_at
    FROM tasks t
    WHERE t.project_id = ?
      AND t.id = ?
");

$s->execute([
    $pid,
    $taskId
]);

$task = $s->fetch();


if (!$task) {

    http_response_code(404);

    echo json_encode(
        [
            'success' => false,
            'message' => 'タスクがありません。'
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * コメント取得
 */
$s = db()->prepare("
    SELECT
        user_account,
        display_name,
        body,
        created_at
    FROM comments
    WHERE project_id = ?
      AND task_id = ?
    ORDER BY created_at ASC
");

$s->execute([
    $pid,
    $taskId
]);

$comments = $s->fetchAll();


echo json_encode(
    [
        'success' => true,
        'updated_at' => $task['updated_at'],
        'comments' => $comments
    ],
    JSON_UNESCAPED_UNICODE
);

exit;