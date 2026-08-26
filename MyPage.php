<?php
require_once __DIR__.'/includes/bootstrap.php';

$u = login_user();

$s = db()->prepare("
    SELECT
        p.*,
        pm.role
    FROM projects p
    JOIN project_members pm
        ON pm.project_id = p.id
    WHERE pm.user_account = ?
    ORDER BY p.updated_at DESC
");

$s->execute([$u]);
$projects = $s->fetchAll();
?>
<!doctype html>
<html lang="ja">

<head>

    <meta charset="UTF-8">

    <title>
        <?= h(page_title('マイページ')) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="app">

<header>

    <div>

        <h1>
            <?= h(APP_NAME) ?>：マイページ
        </h1>

        <div>
            <?= h(user_display_name($u)) ?>
            [<?= h($u) ?>]
        </div>

    </div>

    <a
        class="btn"
        href="logout.php"
    >
        ログアウト
    </a>

</header>


<div class="toolbar">

    <a
        class="btn primary"
        href="project_create.php"
    >
        プロジェクトを作成
    </a>

    <a
        class="btn"
        href="project_join.php"
    >
        プロジェクトに参加
    </a>

    <a
        class="btn"
        href="MyPage.php"
        title="一覧を更新"
    >
        ⟳
    </a>

</div>


<h2>
    許可されているプロジェクト
</h2>


<div class="project-grid">

<?php foreach ($projects as $p): ?>

    <a
        class="project-card"
        href="index.php?project_id=<?= (int)$p['id'] ?>"
    >

        <strong>
            <?= h($p['name']) ?>
        </strong>

        <span>
            <?= h(
                $p['role'] === 'owner'
                    ? '管理者'
                    : 'メンバー'
            ) ?>
        </span>

        <small>
            <?= h($p['description']) ?>
        </small>

    </a>

<?php endforeach; ?>


<?php if (!$projects): ?>

    <div class="empty">
        参加しているプロジェクトはありません。
    </div>

<?php endif; ?>

</div>

</div>

</body>
</html>