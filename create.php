<?php
require_once __DIR__.'/includes/bootstrap.php';$pid=(int)($_GET['project_id']??$_POST['project_id']??0);$u=require_member($pid);$pname=project_name($pid);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();$id=trim($_POST['task_id']??'');$name=trim($_POST['task_name']??'');$summary=trim($_POST['summary']??'');$status=$_POST['status']??'未着手';
 if(!preg_match('/^[a-zA-Z0-9_-]+$/',$id))$error='タスクIDには半角英数字、ハイフン、アンダーバーのみ使用できます。';
 elseif($name==='')$error='タスク名は必須です。';
 else{$s=db()->prepare("SELECT COUNT(*) FROM tasks WHERE project_id=? AND id=?");$s->execute([$pid,$id]);
  if($s->fetchColumn())$error='そのタスクIDは既に存在します。';else{
   $dir="Data/".$pid."/".$id;$att=$dir."/attachments";mkdir($att,0777,true);file_put_contents($dir."/memo.txt",'');
   if(isset($_FILES['thumb'])&&$_FILES['thumb']['error']===UPLOAD_ERR_OK){$ext=strtolower(pathinfo($_FILES['thumb']['name'],PATHINFO_EXTENSION));if(in_array($ext,['png','jpg','jpeg'],true))move_uploaded_file($_FILES['thumb']['tmp_name'],$dir."/thumb.png");}
   db()->prepare("INSERT INTO tasks(id,project_id,creator_user_account,name,summary,status,memo,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())")->execute([$id,$pid,$u,$name,$summary,$status,'']);
   activity($pid,$u,'タスクを作成','task',"task_id=$id / name=$name");header("Location: index.php?project_id=$pid");exit;
  }
 }}
?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?=h(page_title($pname))?></title><link rel="stylesheet" href="css/style.css"></head><body><div class="card narrow"><h1>✨ 新しいタスクの作成</h1><p>作成者：<?=h($u)?></p><?php if($error):?><div class="error"><?=h($error)?></div><?php endif;?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="project_id" value="<?=$pid?>">
<label>タスクID<input name="task_id" placeholder="Task_00002" required></label><label>タスク名<input name="task_name" required></label><label>ざっくり内容<input name="summary"></label><label>最初の状態<select name="status"><option>未着手</option><option>進行中</option><option>完了</option></select></label><label>サムネイル画像<input type="file" name="thumb" accept="image/png,image/jpeg"></label><button class="primary">この内容で作成する</button> <a class="btn" href="index.php?project_id=<?=$pid?>">戻る</a></form></div></body></html>
