<?php
require_once __DIR__.'/includes/bootstrap.php';

$pid = (int)(
    $_GET['id']
    ?? $_POST['id']
    ?? 0
);

$u = require_owner($pid);

$msg = '';
$err = '';


/*
 * プロジェクト情報
 */
$s = db()->prepare("
    SELECT
        p.*,
        ps.storage_type,
        ps.base_path,
        ps.config_json
    FROM projects p
    JOIN project_storage ps
        ON ps.project_id = p.id
    WHERE p.id = ?
");

$s->execute([$pid]);

$p = $s->fetch();

if (!$p) {
    exit('プロジェクトがありません。');
}

$cfg = json_decode(
    $p['config_json'],
    true
) ?: [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $a = $_POST['action'] ?? '';


    /*
     * 設定保存
     */
    if ($a === 'save') {

        $name = trim(
            $_POST['name'] ?? ''
        );

        $desc = trim(
            $_POST['description'] ?? ''
        );

        $type = $_POST['storage_type'] ?? 'local';

        $base = trim(
            $_POST['base_path'] ?? ''
        );

        $url = trim(
            $_POST['server_url'] ?? ''
        );

        $token = trim(
            $_POST['server_token'] ?? ''
        );


        if ($name === '') {

            $err = 'プロジェクト名は必須です。';

        } else {

            db()->prepare("
                UPDATE projects
                SET
                    name = ?,
                    description = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $name,
                $desc,
                $pid
            ]);


            db()->prepare("
                UPDATE project_storage
                SET
                    storage_type = ?,
                    base_path = ?,
                    config_json = ?,
                    updated_at = NOW()
                WHERE project_id = ?
            ")->execute([
                $type,
                $base,
                json_encode(
                    [
                        'server_url' => $url,
                        'server_token' => $token
                    ],
                    JSON_UNESCAPED_SLASHES
                ),
                $pid
            ]);


            activity(
                $pid,
                $u,
                'プロジェクト設定を変更'
            );

            $msg = '保存しました。';
        }
    }


    /*
     * プロジェクト削除
     */
    elseif ($a === 'delete') {

        db()->prepare("
            DELETE FROM projects
            WHERE id = ?
        ")->execute([
            $pid
        ]);

        header(
            'Location: MyPage.php'
        );

        exit;
    }


    /*
     * 管理者が新管理者を指定して脱退
     */
    elseif ($a === 'leave') {

        $new = trim(
            $_POST['new_owner'] ?? ''
        );


        if ($new === '') {

            $err = '新しい管理者を選択してください。';

        } else {

            $s = db()->prepare("
                SELECT 1
                FROM project_members
                WHERE project_id = ?
                  AND user_account = ?
                  AND role = 'member'
            ");

            $s->execute([
                $pid,
                $new
            ]);


            if (!$s->fetchColumn()) {

                $err = '新しい管理者を選択してください。';

            } else {

                $pdo = db();

                try {

                    $pdo->beginTransaction();


                    /*
                     * 新しい管理者をownerに変更
                     */
                    $pdo->prepare("
                        UPDATE project_members
                        SET role = 'owner'
                        WHERE project_id = ?
                          AND user_account = ?
                    ")->execute([
                        $pid,
                        $new
                    ]);


                    /*
                     * projects側の管理者も変更
                     */
                    $pdo->prepare("
                        UPDATE projects
                        SET
                            owner_user_account = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        $new,
                        $pid
                    ]);


                    /*
                     * 旧管理者をメンバー一覧から完全に削除
                     *
                     * これが今回の重要な修正。
                     */
                    $pdo->prepare("
                        DELETE FROM project_members
                        WHERE project_id = ?
                          AND user_account = ?
                    ")->execute([
                        $pid,
                        $u
                    ]);


                    $pdo->commit();


                    /*
                     * 脱退処理後に活動ログを残す
                     */
                    activity(
                        $pid,
                        $u,
                        '管理者変更後に脱退',
                        'project',
                        "new_owner=$new"
                    );


                    /*
                     * マイページへ
                     */
                    header(
                        'Location: MyPage.php'
                    );

                    exit;

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $err = '管理者変更と脱退に失敗しました。';
                }
            }
        }
    }
}


/*
 * 現在のメンバー一覧
 */
$members = db()->prepare("
    SELECT
        user_account,
        role
    FROM project_members
    WHERE project_id = ?
    ORDER BY user_account
");

$members->execute([
    $pid
]);

$members = $members->fetchAll();

?>
<!doctype html>
<html lang="ja">

<head>

    <meta charset="UTF-8">

    <title>
        <?= h(page_title('プロジェクト設定')) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="card narrow">

<h1>
    プロジェクト設定
</h1>


<?php if ($msg): ?>

    <div class="success">
        <?= h($msg) ?>
    </div>

<?php endif; ?>


<?php if ($err): ?>

    <div class="error">
        <?= h($err) ?>
    </div>

<?php endif; ?>


<form method="post">

    <input
        type="hidden"
        name="csrf"
        value="<?= h(csrf()) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= $pid ?>"
    >

    <input
        type="hidden"
        name="action"
        value="save"
    >


    <label>

        プロジェクト名

        <input
            name="name"
            value="<?= h($p['name']) ?>"
            required
        >

    </label>


    <label>

        説明

        <textarea
            name="description"
        ><?= h($p['description']) ?></textarea>

    </label>


    <label>

        保存先

        <select name="storage_type">

            <option
                value="local"
                <?= (
                    $p['storage_type'] === 'local'
                    ? 'selected'
                    : ''
                ) ?>
            >
                Webサーバーのローカル保存
            </option>

            <option
                value="http_storage"
                <?= (
                    $p['storage_type'] === 'http_storage'
                    ? 'selected'
                    : ''
                ) ?>
            >
                PP Task Storage Server
            </option>

        </select>

    </label>


    <label>

        保存先フォルダ

        <input
            name="base_path"
            value="<?= h($p['base_path']) ?>"
        >

    </label>


    <label>

        保存サーバーURL

        <input
            name="server_url"
            value="<?= h($cfg['server_url'] ?? '') ?>"
        >

    </label>


    <label>

        保存サーバー認証トークン

        <input
            type="password"
            name="server_token"
            value="<?= h($cfg['server_token'] ?? '') ?>"
        >

    </label>


    <button class="primary">
        保存
    </button>

</form>


<hr>


<h2>
    管理者として脱退
</h2>

<p>
    管理者は、新しい管理者を選択しない限り脱退できません。
</p>


<form method="post">

    <input
        type="hidden"
        name="csrf"
        value="<?= h(csrf()) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= $pid ?>"
    >

    <input
        type="hidden"
        name="action"
        value="leave"
    >


    <select
        name="new_owner"
        required
    >

        <option value="">
            新しい管理者を選択
        </option>


        <?php foreach ($members as $m): ?>

            <?php if ($m['role'] === 'member'): ?>

                <option
                    value="<?= h($m['user_account']) ?>"
                >
                    <?= h($m['user_account']) ?>
                </option>

            <?php endif; ?>

        <?php endforeach; ?>

    </select>


    <button>
        管理者を変更して脱退
    </button>

</form>


<hr>


<form
    method="post"
    onsubmit="return confirm('プロジェクトを削除します。よろしいですか？');"
>

    <input
        type="hidden"
        name="csrf"
        value="<?= h(csrf()) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= $pid ?>"
    >

    <input
        type="hidden"
        name="action"
        value="delete"
    >


    <button class="danger">
        プロジェクトを削除
    </button>

</form>


<p>

    <a
        href="index.php?project_id=<?= $pid ?>"
    >
        戻る
    </a>

</p>

</div>

</body>
</html>