<?php
require_once __DIR__ . '/../config.php';
date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => SESSION_SECURE,
        'cookie_samesite' => 'Lax'
    ]);
}
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(DSN, DBUSER, DBPASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    return $pdo;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('不正なリクエストです。');
    }
}
function login_user(): string {
    if (empty($_SESSION['user_account'])) { header('Location: login.php'); exit; }
    return $_SESSION['user_account'];
}
function require_member(int $projectId): string {
    $u=login_user();
    $s=db()->prepare("SELECT 1 FROM project_members WHERE project_id=? AND user_account=?");
    $s->execute([$projectId,$u]);
    if (!$s->fetchColumn()) { http_response_code(403); exit('このプロジェクトへのアクセス権がありません。'); }
    return $u;
}
function require_owner(int $projectId): string {
    $u=require_member($projectId);
    $s=db()->prepare("SELECT 1 FROM projects WHERE id=? AND owner_user_account=?");
    $s->execute([$projectId,$u]);
    if (!$s->fetchColumn()) { http_response_code(403); exit('管理者のみ実行できます。'); }
    return $u;
}
function user_display_name(string $email): string {
    $s=db()->prepare("SELECT display_name FROM user_tbl WHERE user_account=?");
    $s->execute([$email]);
    $v=$s->fetchColumn();
    return ($v!==false && trim((string)$v)!=='') ? (string)$v : $email;
}
function save_display_name(string $email,string $name): void {
    db()->prepare("UPDATE user_tbl SET display_name=? WHERE user_account=?")->execute([$name,$email]);
}
function activity(int $pid,string $user,string $action,string $target='',string $details=''): void {
    db()->prepare("INSERT INTO activity_logs(project_id,user_account,action,target_type,details,created_at) VALUES(?,?,?,?,?,NOW())")
      ->execute([$pid,$user,$action,$target,$details]);
}
function project_name(int $id): string {
    $s=db()->prepare("SELECT name FROM projects WHERE id=?");$s->execute([$id]);
    return (string)$s->fetchColumn();
}
function page_title(string $suffix): string { return APP_NAME."：".$suffix; }
