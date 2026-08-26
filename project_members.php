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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $a = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');

    /*
     * メンバー参加許可
     */
    if ($a === 'add') {

        $s = db()->prepare("
            SELECT 1
            FROM user_tbl
            WHERE user_account = ?
        ");

        $s->execute([$email]);

        if (!$s->fetchColumn()) {

            $err = '登録されていないメールアドレスです。';

        } else {

            db()->prepare("
                INSERT IGNORE INTO project_members
                (
                    project_id,
                    user_account,
                    role,
                    joined_at
                )
                VALUES (?, ?, 'member', NOW())
            ")->execute([
                $pid,
                $email
            ]);

            activity(
                $pid,
                $u,
                'メンバー参加を許可',
                'member',
                $email
            );

            $msg = '参加を許可しました。';
        }
    }


    /*
     * メンバー強制脱退
     */
    elseif (
        $a === 'remove' &&
        $email !== $u
    ) {

        $s = db()->prepare("
            DELETE FROM project_members
            WHERE project_id = ?
              AND user_account = ?
              AND role = 'member'
        ");

        $s->execute([
            $pid,
            $email
        ]);

        activity(
            $pid,
            $u,
            'メンバーを強制脱退',
            'member',
            $email
        );

        $msg = '脱退させました。';
    }


    /*
     * 管理者移譲
     */
    elseif (
        $a === 'transfer' &&
        $email !== $u
    ) {

        $s = db()->prepare("
            SELECT 1
            FROM project_members
            WHERE project_id = ?
              AND user_account = ?
              AND role = 'member'
        ");

        $s->execute([
            $pid,
            $email
        ]);

        if (!$s->fetchColumn()) {

            $err = 'そのユーザーは現在のメンバーではありません。';

        } else {

            $pdo = db();

            try {

                $pdo->beginTransaction();

                /*
                 * 現管理者 → メンバー
                 */
                $pdo->prepare("
                    UPDATE project_members
                    SET role = 'member'
                    WHERE project_id = ?
                      AND user_account = ?
                ")->execute([
                    $pid,
                    $u
                ]);

                /*
                 * 対象メンバー → 管理者
                 */
                $pdo->prepare("
                    UPDATE project_members
                    SET role = 'owner'
                    WHERE project_id = ?
                      AND user_account = ?
                ")->execute([
                    $pid,
                    $email
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
                    $email,
                    $pid
                ]);

                $pdo->commit();

                activity(
                    $pid,
                    $u,
                    '管理者を変更',
                    'member',
                    $email
                );

                /*
                 * project.phpではなく、
                 * 現在実際に存在するプロジェクトトップへ
                 */
                header(
                    "Location: index.php?project_id=$pid"
                );

                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $err = '管理者の変更に失敗しました。';
            }
        }
    }
}


/*
 * 現在のメンバー一覧
 */
$s = db()->prepare("
    SELECT
        pm.*,
        COALESCE(
            NULLIF(u.display_name, ''),
            u.user_account
        ) AS display_name
    FROM project_members pm
    LEFT JOIN user_tbl u
        ON u.user_account = pm.user_account
    WHERE pm.project_id = ?
    ORDER BY
        pm.role DESC,
        pm.user_account
");

$s->execute([$pid]);

$members = $s->fetchAll();

?>
<!doctype html>
<html lang="ja">

<head>

    <meta charset="UTF-8">

    <title>
        <?= h(page_title('メンバー管理')) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="app">

<header>

    <h1>
        メンバー管理
    </h1>

    <a
        class="btn"
        href="index.php?project_id=<?= $pid ?>"
    >
        戻る
    </a>

</header>


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


<div class="card">

    <h2>
        参加を許可
    </h2>

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
            value="add"
        >

        <input
            type="email"
            name="email"
            required
        >

        <button class="primary">
            追加
        </button>

    </form>

</div>


<table>

<tr>

    <th>
        表示名
    </th>

    <th>
        メールアドレス
    </th>

    <th>
        権限
    </th>

    <th>
        操作
    </th>

</tr>


<?php foreach ($members as $m): ?>

<tr>

    <td>
        <?= h($m['display_name']) ?>
    </td>

    <td>
        <?= h($m['user_account']) ?>
    </td>

    <td>
        <?= h(
            $m['role'] === 'owner'
                ? '管理者'
                : 'メンバー'
        ) ?>
    </td>

    <td>

    <?php if ($m['role'] === 'member'): ?>

        <!-- 強制脱退 -->

        <form
            class="inline"
            method="post"
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
                name="email"
                value="<?= h($m['user_account']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="remove"
            >

            <button class="danger">
                強制脱退
            </button>

        </form>


        <!-- 管理者にする -->

        <form
            class="inline"
            method="post"
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
                name="email"
                value="<?= h($m['user_account']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="transfer"
            >

            <button>
                管理者にする
            </button>

        </form>

    <?php endif; ?>

    </td>

</tr>

<?php endforeach; ?>

</table>

</div>

</body>
</html>