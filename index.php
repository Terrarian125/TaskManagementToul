<?php
require_once __DIR__.'/includes/bootstrap.php';$u=login_user();$pid=(int)($_GET['project_id']??0);if(!$pid){header('Location: MyPage.php');exit;}$u=require_member($pid);$pname=project_name($pid);
$search=trim($_GET['search']??'');$sort=$_GET['sort']??'update_date';$allowed=['status','id','creator_user_account','name','summary','created_at','updated_at'];if(!in_array($sort,$allowed,true))$sort='updated_at';
$sql="SELECT t.*,COALESCE(NULLIF(u.display_name,''),u.user_account) AS creator_display FROM tasks t LEFT JOIN user_tbl u ON u.user_account=t.creator_user_account WHERE t.project_id=?";$args=[$pid];
if($search!==''){$sql.=" AND (t.name LIKE ? OR t.summary LIKE ? OR t.id LIKE ? OR t.creator_user_account LIKE ?)";$q="%$search%";array_push($args,$q,$q,$q,$q);}
$sql.=" ORDER BY ".$sort." ASC";$s=db()->prepare($sql);$s->execute($args);$tasks=$s->fetchAll();$owner=false;$st=db()->prepare("SELECT 1 FROM projects WHERE id=? AND owner_user_account=?");$st->execute([$pid,$u]);$owner=(bool)$st->fetchColumn();
?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?=h(page_title($pname))?></title><link rel="stylesheet" href="css/style.css"></head><body><div class="app">
<header><div><h1><?=h(APP_NAME)?>：<?=h($pname)?></h1><small><?=h($u)?></small></div><div><a class="btn" href="MyPage.php">マイページ</a></div></header>
<div class="toolbar"><a class="btn primary" href="create.php?project_id=<?=$pid?>">「タスク作成」</a><?php if($owner):?><a class="btn" href="project_members.php?id=<?=$pid?>">メンバー管理</a><a class="btn" href="project_settings.php?id=<?=$pid?>">プロジェクト設定</a><a class="btn" href="project_logs.php?id=<?=$pid?>">活動ログ</a><?php endif;?></div>
<form class="search" method="get"><input type="hidden" name="project_id" value="<?=$pid?>"><input name="search" value="<?=h($search)?>" placeholder="キーワードで検索..."><button>検索</button></form>
<table class="task-table"><thead><tr><th>サムネイル</th><th>状態</th><th>ID</th><th>作成者</th><th>タスク名</th><th>ざっくり内容</th><th>作成日時</th><th>更新日時</th></tr></thead><tbody>
<?php foreach($tasks as $t):$thumb="Data/".$pid."/".$t['id']."/thumb.png";?><tr onclick="location.href='detail.php?project_id=<?=$pid?>&id=<?=urlencode($t['id'])?>'"><td><div class="thumb-box"><?php if(is_file($thumb)):?><img src="<?=h($thumb)?>?v=<?=time()?>"><?php else:?><span>No Image</span><?php endif;?></div></td><td><span class="badge"><?=h($t['status'])?></span></td><td><strong><?=h($t['id'])?></strong></td><td><?=h($t['creator_display'])?></td><td><strong><?=h($t['name'])?></strong></td><td><?=h($t['summary'])?></td><td><?=h($t['created_at'])?></td><td><?=h($t['updated_at'])?></td></tr><?php endforeach;?><?php if(!$tasks):?><tr><td colspan="8" class="empty">該当するタスクがありません。</td></tr><?php endif;?></tbody></table>
</div></body></html>
