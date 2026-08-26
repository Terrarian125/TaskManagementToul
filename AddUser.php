<?php
require_once __DIR__.'/includes/bootstrap.php';
$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();$email=trim($_POST['email']??'');$password=trim($_POST['password']??'');
 if(function_exists('mb_convert_kana')){$email=trim(mb_convert_kana($email,'as','UTF-8'));$password=trim(mb_convert_kana($password,'as','UTF-8'));}
 if(!filter_var($email,FILTER_VALIDATE_EMAIL))$err='有効なメールアドレスを入力してください。';
 elseif(strlen($password)<8)$err='パスワードは8文字以上で入力してください。';
 elseif(!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password))$err='パスワードには英字と数字の両方を含めてください。';
 else{$s=db()->prepare("SELECT COUNT(*) FROM user_tbl WHERE user_account=?");$s->execute([$email]);
  if($s->fetchColumn())$err='このメールアドレスは既に登録されています。';
  else{db()->prepare("INSERT INTO user_tbl(user_account,user_password,display_name) VALUES(?,?,?)")->execute([$email,password_hash($password,PASSWORD_DEFAULT),'']);$msg='ユーザー登録が完了しました！';}
 }
}
?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?=h(page_title('ユーザー登録'))?></title><link rel="stylesheet" href="css/style.css"></head><body>
<div class="login-card"><h1><?=h(APP_NAME)?>：ユーザー登録</h1><?php if($msg):?><div class="success"><?=h($msg)?></div><?php endif;?><?php if($err):?><div class="error"><?=h($err)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><label>メールアドレス<input type="email" name="email" required></label><label>パスワード<input type="password" name="password" required></label><button class="primary full">登録する</button></form><a href="login.php">ログインへ戻る</a></div></body></html>
