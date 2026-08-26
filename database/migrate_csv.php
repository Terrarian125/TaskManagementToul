<?php
// 既存 Data/task_list.csv を「現在ログインしている管理者」の新規プロジェクトへ移行する簡易ツール。
// 実行前にCSV/Dataのバックアップを取ってください。
// ブラウザから直接実行する場合は、完了後このファイルを削除してください。
require_once __DIR__.'/../includes/bootstrap.php';
$u=login_user();
$csv=__DIR__.'/../Data/task_list.csv';
if(!is_file($csv))exit('Data/task_list.csv がありません。');
if($_SERVER['REQUEST_METHOD']!=='POST'){
 echo '<h1>既存CSV移行</h1><form method="post"><input name="project_name" placeholder="移行先プロジェクト名" required><button>移行開始</button></form>';exit;
}
check_csrf();$name=trim($_POST['project_name']);$pdo=db();$pdo->beginTransaction();
$pdo->prepare("INSERT INTO projects(name,description,owner_user_account,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())")->execute([$name,'旧CSVからの移行',$u]);$pid=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO project_members(project_id,user_account,role,joined_at) VALUES(?,?, 'owner',NOW())")->execute([$pid,$u]);
$pdo->prepare("INSERT INTO project_storage(project_id,storage_type,base_path,config_json,created_at,updated_at) VALUES(?,'local','', '{}',NOW(),NOW())")->execute([$pid]);
$h=fopen($csv,'r');fgetcsv($h,0,",",'"',"\\");$count=0;
while(($d=fgetcsv($h,0,",",'"',"\\"))!==false){if(count($d)<7)continue;$id=$d[0];if($id==='')continue;
 $memo='';$oldDir=__DIR__.'/../Data/'.$id;if(is_file($oldDir.'/memo.txt'))$memo=file_get_contents($oldDir.'/memo.txt');
 $newDir=__DIR__.'/../Data/'.$pid.'/'.$id;@mkdir($newDir.'/attachments',0777,true);if($memo!=='')file_put_contents($newDir.'/memo.txt',$memo);if(is_file($oldDir.'/thumb.png'))copy($oldDir.'/thumb.png',$newDir.'/thumb.png');
 db()->prepare("INSERT INTO tasks(project_id,id,creator_user_account,name,summary,status,memo,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())")->execute([$pid,$id,$d[1],$d[2],$d[3],$d[6],$memo]);$count++;
}
fclose($h);$pdo->commit();activity($pid,$u,'既存CSVを移行',"count=$count");echo "<h1>移行完了</h1><p>$count 件を移行しました。</p><p><a href=\"../index.php?project_id=$pid\">プロジェクトを開く</a></p>";
