<?php
require_once __DIR__.'/includes/bootstrap.php';

$pid = (int)(
    $_GET['project_id']
    ?? $_POST['project_id']
    ?? 0
);

$id = trim(
    $_GET['id']
    ?? $_POST['id']
    ?? ''
);

$u = require_member($pid);


/*
 * タスク取得
 */
$s = db()->prepare("
    SELECT
        t.*,
        p.name project_name,
        COALESCE(
            NULLIF(u.display_name, ''),
            u.user_account
        ) creator_display
    FROM tasks t
    JOIN projects p
        ON p.id = t.project_id
    LEFT JOIN user_tbl u
        ON u.user_account = t.creator_user_account
    WHERE t.project_id = ?
      AND t.id = ?
");

$s->execute([
    $pid,
    $id
]);

$task = $s->fetch();

if (!$task) {
    exit('タスクがありません。');
}


$dir = "Data/" . $pid . "/" . $id;
$att = $dir . "/attachments";


/*
 * 添付ファイル用フォルダ
 */
if (!is_dir($att)) {
    mkdir($att, 0777, true);
}


$msg = '';
$err = '';


/*
 * POST処理
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $action = $_POST['action'] ?? '';


    /*
     * タスク保存
     */
    if ($action === 'save') {

        db()->prepare("
            UPDATE tasks
            SET
                name = ?,
                summary = ?,
                status = ?,
                memo = ?,
                updated_at = NOW()
            WHERE project_id = ?
              AND id = ?
        ")->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['summary'] ?? ''),
            $_POST['status'] ?? '未着手',
            $_POST['memo'] ?? '',
            $pid,
            $id
        ]);


        file_put_contents(
            $dir . "/memo.txt",
            $_POST['memo'] ?? ''
        );


        activity(
            $pid,
            $u,
            'タスクを更新',
            'task',
            "task_id=$id"
        );


        /*
         * PRG
         * POST → Redirect → GET
         */
        header(
            "Location: detail.php?project_id=$pid&id="
            . urlencode($id)
        );

        exit;
    }


    /*
     * コメント投稿
     */
    elseif ($action === 'comment') {

        $body = trim(
            $_POST['comment'] ?? ''
        );

        $dn = trim(
            $_POST['display_name'] ?? ''
        );


        /*
         * 表示名を保存
         */
        if ($dn !== '') {
            save_display_name(
                $u,
                $dn
            );
        }


        if ($body !== '') {

            /*
             * コメント保存
             */
            db()->prepare("
                INSERT INTO comments
                (
                    project_id,
                    task_id,
                    user_account,
                    display_name,
                    body,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([
                $pid,
                $id,
                $u,
                user_display_name($u),
                $body
            ]);


            /*
             * コメントもタスクの更新として扱う
             */
            db()->prepare("
                UPDATE tasks
                SET updated_at = NOW()
                WHERE project_id = ?
                  AND id = ?
            ")->execute([
                $pid,
                $id
            ]);


            /*
             * 活動ログ
             */
            activity(
                $pid,
                $u,
                'コメントを投稿',
                'comment',
                "task_id=$id"
            );
        }


        /*
         * コメント投稿後は必ずGETへリダイレクト
         *
         * これでF5しても同じコメントを再投稿しない。
         */
        header(
            "Location: detail.php?project_id=$pid&id="
            . urlencode($id)
        );

        exit;
    }


    /*
     * 添付ファイル
     */
    elseif ($action === 'upload') {

        if (
            isset($_FILES['attachment_file']) &&
            $_FILES['attachment_file']['error']
                === UPLOAD_ERR_OK
        ) {

            $name = basename(
                $_FILES['attachment_file']['name']
            );


            $name = preg_replace(
                '/[^A-Za-z0-9._ -]/u',
                '_',
                $name
            );


            if (
                move_uploaded_file(
                    $_FILES['attachment_file']['tmp_name'],
                    $att . "/" . $name
                )
            ) {

                db()->prepare("
                    INSERT INTO attachments
                    (
                        project_id,
                        task_id,
                        uploaded_by,
                        original_name,
                        storage_path,
                        created_at
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $pid,
                    $id,
                    $u,
                    $name,
                    $att . "/" . $name
                ]);


                db()->prepare("
                    UPDATE tasks
                    SET updated_at = NOW()
                    WHERE project_id = ?
                      AND id = ?
                ")->execute([
                    $pid,
                    $id
                ]);


                activity(
                    $pid,
                    $u,
                    '添付ファイルを追加',
                    'attachment',
                    "task_id=$id / file=$name"
                );
            }
        }


        header(
            "Location: detail.php?project_id=$pid&id="
            . urlencode($id)
        );

        exit;
    }


    /*
     * タスク削除
     */
    elseif ($action === 'delete') {

        db()->prepare("
            DELETE FROM tasks
            WHERE project_id = ?
              AND id = ?
        ")->execute([
            $pid,
            $id
        ]);


        activity(
            $pid,
            $u,
            'タスクを削除',
            'task',
            "task_id=$id"
        );


        /*
         * Dataフォルダ削除
         */
        if (is_dir($dir)) {

            foreach (
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $dir,
                        RecursiveDirectoryIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::CHILD_FIRST
                ) as $f
            ) {

                if ($f->isDir()) {
                    rmdir($f->getPathname());
                } else {
                    unlink($f->getPathname());
                }
            }

            rmdir($dir);
        }


        header(
            "Location: index.php?project_id=$pid"
        );

        exit;
    }
}


/*
 * コメント一覧
 */
$s = db()->prepare("
    SELECT *
    FROM comments
    WHERE project_id = ?
      AND task_id = ?
    ORDER BY created_at ASC
");

$s->execute([
    $pid,
    $id
]);

$comments = $s->fetchAll();


/*
 * 添付ファイル一覧
 */
$files = is_dir($att)
    ? array_values(
        array_diff(
            scandir($att),
            ['.', '..']
        )
    )
    : [];

?>
<!doctype html>
<html lang="ja">

<head>

<meta charset="UTF-8">

<title>
<?= h(page_title($task['project_name'])) ?>
</title>

<link
    rel="stylesheet"
    href="css/style.css"
>

<style>

.comment-list {
    margin-top: 15px;
}

.comment {
    border-bottom: 1px solid #ddd;
    padding: 12px 0;
}

.comment:last-child {
    border-bottom: none;
}

.comment time {
    margin-left: 10px;
    color: #888;
    font-size: 0.85em;
}

.comment p {
    white-space: normal;
    word-break: break-word;
}

</style>

</head>

<body>

<div class="app">


<header>

<div>

<span class="badge">
<?= h($task['status']) ?>
</span>

<h1 class="inline-title">
<?= h($task['name']) ?>
</h1>

</div>


<a
    class="btn"
    href="index.php?project_id=<?= $pid ?>"
>
← 一覧に戻る
</a>

</header>


<div class="detail-grid">

<div>


<!-- 詳細メモ -->

<form
    method="post"
    class="card"
>

<input
    type="hidden"
    name="csrf"
    value="<?= h(csrf()) ?>"
>

<input
    type="hidden"
    name="project_id"
    value="<?= $pid ?>"
>

<input
    type="hidden"
    name="id"
    value="<?= h($id) ?>"
>

<input
    type="hidden"
    name="action"
    value="save"
>


<h2>
📝 詳細メモ
</h2>


<label>

タスク名

<input
    name="name"
    value="<?= h($task['name']) ?>"
    required
>

</label>


<label>

ざっくり内容

<input
    name="summary"
    value="<?= h($task['summary']) ?>"
>

</label>


<label>

現在の状態

<select name="status">

<?php foreach (
    ['未着手', '進行中', '完了']
    as $x
): ?>

<option
    <?= (
        $task['status'] === $x
        ? 'selected'
        : ''
    ) ?>
>
<?= h($x) ?>
</option>

<?php endforeach; ?>

</select>

</label>


<label>

詳細メモ

<textarea
    name="memo"
    rows="16"
><?= h($task['memo']) ?></textarea>

</label>


<button class="primary">
この内容で保存する
</button>

</form>


<!-- コメント -->

<div class="card">

<h2>
💬 コメント
</h2>


<form method="post">

<input
    type="hidden"
    name="csrf"
    value="<?= h(csrf()) ?>"
>

<input
    type="hidden"
    name="project_id"
    value="<?= $pid ?>"
>

<input
    type="hidden"
    name="id"
    value="<?= h($id) ?>"
>

<input
    type="hidden"
    name="action"
    value="comment"
>


<label>

表示名（前回の入力内容を保存）

<input
    name="display_name"
    value="<?= h(user_display_name($u)) ?>"
>

</label>


<div class="muted">

メールアドレス：
<?= h($u) ?>

</div>


<label>

コメント本文

<textarea
    name="comment"
    rows="5"
    required
></textarea>

</label>


<button class="primary">
コメントを投稿
</button>

</form>


<hr>


<div
    id="comment-list"
    class="comment-list"
>


<?php foreach ($comments as $c): ?>

<article class="comment">

<strong>
<?= h($c['display_name']) ?>
</strong>

<span>
&lt;<?= h($c['user_account']) ?>&gt;
</span>

<time>
<?= h($c['created_at']) ?>
</time>

<p>
<?= nl2br(h($c['body'])) ?>
</p>

</article>

<?php endforeach; ?>


<?php if (!$comments): ?>

<p
    class="muted"
    id="no-comments"
>
コメントはまだありません。
</p>

<?php endif; ?>

</div>

</div>

</div>


<aside>


<!-- 基本情報 -->

<div class="card">

<h2>
📊 基本情報
</h2>


<p>
<b>タスクID:</b>
<?= h($task['id']) ?>
</p>


<p>
<b>作成者:</b>
<?= h($task['creator_display']) ?>
&lt;<?= h($task['creator_user_account']) ?>&gt;
</p>


<p>
<b>作成日時:</b>
<?= h($task['created_at']) ?>
</p>


<p>
<b>更新日時:</b>
<span id="task-updated-at">
<?= h($task['updated_at']) ?>
</span>
</p>

</div>


<!-- 添付ファイル -->

<div class="card">

<h2>
📎 添付ファイル
</h2>


<form
    method="post"
    enctype="multipart/form-data"
>

<input
    type="hidden"
    name="csrf"
    value="<?= h(csrf()) ?>"
>

<input
    type="hidden"
    name="project_id"
    value="<?= $pid ?>"
>

<input
    type="hidden"
    name="id"
    value="<?= h($id) ?>"
>

<input
    type="hidden"
    name="action"
    value="upload"
>


<input
    type="file"
    name="attachment_file"
    required
>


<button>
このファイルを追加
</button>

</form>


<?php foreach ($files as $f): ?>

<p>

<a
    href="<?= h($att . '/' . $f) ?>"
    target="_blank"
>
📄 <?= h($f) ?>
</a>

</p>

<?php endforeach; ?>

</div>


<!-- 削除 -->

<div class="card">

<form
    method="post"
    onsubmit="return confirm('このタスクと添付ファイルをすべて削除します。よろしいですか？');"
>

<input
    type="hidden"
    name="csrf"
    value="<?= h(csrf()) ?>"
>

<input
    type="hidden"
    name="project_id"
    value="<?= $pid ?>"
>

<input
    type="hidden"
    name="id"
    value="<?= h($id) ?>"
>

<input
    type="hidden"
    name="action"
    value="delete"
>


<button class="danger">
⚠️ このタスクを削除する
</button>

</form>

</div>

</aside>

</div>

</div>


<script>

/*
 * コメントをHTMLに安全に表示
 */
function escapeHtml(value) {

    const div = document.createElement('div');

    div.textContent = value;

    return div.innerHTML;
}


/*
 * コメント本文の改行をHTMLへ変換
 */
function commentBodyHtml(value) {

    return escapeHtml(value)
        .replace(/\r?\n/g, '<br>');
}


/*
 * コメント一覧を取得
 */
async function updateComments() {

    try {

        const response = await fetch(
            'comments_api.php'
            + '?project_id=<?= $pid ?>'
            + '&task_id=<?= rawurlencode($id) ?>'
            + '&_=' + Date.now(),
            {
                cache: 'no-store'
            }
        );


        if (!response.ok) {
            return;
        }


        const data = await response.json();


        if (!data.success) {
            return;
        }


        const list =
            document.getElementById(
                'comment-list'
            );


        if (!list) {
            return;
        }


        /*
         * サーバー側のコメント一覧をそのまま再描画
         *
         * コメント数が少ない今回の用途なら、
         * この方法がシンプルで確実。
         */
        if (data.comments.length === 0) {

            list.innerHTML =
                '<p class="muted" id="no-comments">'
                + 'コメントはまだありません。'
                + '</p>';

        } else {

            list.innerHTML =
                data.comments.map(
                    function(comment) {

                        return `
<article class="comment">

<strong>
${escapeHtml(comment.display_name)}
</strong>

<span>
&lt;${escapeHtml(comment.user_account)}&gt;
</span>

<time>
${escapeHtml(comment.created_at)}
</time>

<p>
${commentBodyHtml(comment.body)}
</p>

</article>
`;

                    }
                ).join('');
        }


        /*
         * タスク最終更新日時も更新
         */
        const updated =
            document.getElementById(
                'task-updated-at'
            );


        if (
            updated &&
            data.updated_at
        ) {

            updated.textContent =
                data.updated_at;
        }

    } catch (error) {

    }
}


/*
 * 2秒ごとに確認
 */
setInterval(
    updateComments,
    2000
);

</script>

</body>

</html>