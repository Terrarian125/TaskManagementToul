<?php
require_once __DIR__.'/includes/bootstrap.php';$u=login_user();$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$name=trim($_POST['name']??'');$desc=trim($_POST['description']??'');
 if($name==='')$err='プロジェクト名は必須です。';else{$pdo=db();$pdo->beginTransaction();
  $pdo->prepare("INSERT INTO projects(name,description,owner_user_account,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())")->execute([$name,$desc,$u]);$id=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO project_members(project_id,user_account,role,joined_at) VALUES(?,?, 'owner',NOW())")->execute([$id,$u]);
  $pdo->prepare("INSERT INTO project_storage(project_id,storage_type,base_path,config_json,created_at,updated_at) VALUES(?,'local','', '{}',NOW(),NOW())")->execute([$id]);
  $pdo->commit();activity($id,$u,'プロジェクトを作成');header("Location: index.php?project_id=$id");exit;
 }}
?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?=h(page_title('プロジェクト作成'))?></title><link rel="stylesheet" href="css/style.css"></head><body><div class="card narrow"><h1>プロジェクトを作成</h1><?php if($err):?><div class="error"><?=h($err)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><label>プロジェクト名<input name="name" required></label><label>説明<textarea name="description"></textarea></label><button class="primary">作成</button> <a class="btn" href="MyPage.php">戻る</a></form></div></body></html>
