<?php

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_account'])) {
    header('Location: MyPage.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (function_exists('mb_convert_kana')) {

        $username = trim(
            mb_convert_kana(
                $username,
                'as',
                'UTF-8'
            )
        );

        $password = trim(
            mb_convert_kana(
                $password,
                'as',
                'UTF-8'
            )
        );
    }

    if ($username === '') {

        $error = 'メールアドレスが未入力です。';

    } elseif ($password === '') {

        $error = 'パスワードが未入力です。';

    } else {

        $s = db()->prepare(
            "SELECT user_password
             FROM user_tbl
             WHERE user_account = ?"
        );

        $s->execute([$username]);

        $hash = $s->fetchColumn();

        if (
            $hash !== false &&
            password_verify($password, $hash)
        ) {

            session_regenerate_id(true);

            $_SESSION['user_account'] = $username;

            if (isset($_POST['remember_me'])) {

                $token = bin2hex(
                    random_bytes(32)
                );

                $expires = time() + 60 * 60 * 24 * 14;

                db()->prepare(
                    "INSERT INTO remember_token_tbl(
                        rt_user_account,
                        rt_remember_token,
                        rt_expires_at
                    )
                    VALUES(
                        ?,
                        ?,
                        FROM_UNIXTIME(?)
                    )"
                )->execute([
                    $username,
                    $token,
                    $expires
                ]);

                setcookie(
                    'remember_token',
                    $token,
                    $expires,
                    '/',
                    '',
                    SESSION_SECURE,
                    true
                );
            }

            header('Location: MyPage.php');
            exit;

        } else {

            $error = 'アカウント名またはパスワードが間違っています。';
        }
    }
}

?>

<!doctype html>
<html lang="ja">

<head>

    <meta charset="UTF-8">

    <title>
        <?= h(page_title('ログイン')) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="login-card">

    <h1>
        <?= h(APP_NAME) ?>：ログイン
    </h1>

    <?php if ($error): ?>

        <div class="error">
            <?= h($error) ?>
        </div>

    <?php endif; ?>

    <form method="post">

        <input
            type="hidden"
            name="csrf"
            value="<?= h(csrf()) ?>"
        >

        <label>
            メールアドレス

            <input
                type="email"
                name="username"
                value="<?= h($username) ?>"
                required
            >
        </label>

        <label>
            パスワード

            <input
                type="password"
                name="password"
                required
            >
        </label>

        <label class="check">

            <input
                type="checkbox"
                name="remember_me"
            >

            次回から自動ログイン

        </label>

        <button class="primary full">
            ログイン
        </button>

    </form>

    <a href="AddUser.php">
        新規ユーザー登録
    </a>

</div>

</body>
</html>