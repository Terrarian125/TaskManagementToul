<?php
require_once __DIR__.'/includes/bootstrap.php';
if(isset($_COOKIE['remember_token'])){db()->prepare("DELETE FROM remember_token_tbl WHERE rt_remember_token=?")->execute([$_COOKIE['remember_token']]);setcookie('remember_token','',time()-42000,'/');}
$_SESSION=[];session_destroy();header('Location: login.php');exit;
