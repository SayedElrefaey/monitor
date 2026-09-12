<?php
require_once __DIR__.'/src/bootstrap.php';
if(!empty($_SESSION['user_id'])){header('Location: dashboard.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $st=$pdo->prepare("SELECT * FROM users WHERE username=? LIMIT 1");$st->execute([trim($_POST['username']??'')]);$u=$st->fetch();
 if($u&&password_verify($_POST['password']??'',$u['password_hash'])){session_regenerate_id(true);$_SESSION['user_id']=$u['id'];$_SESSION['username']=$u['username'];header('Location: dashboard.php');exit;}
 $error='بيانات الدخول غير صحيحة.';
}
?>
<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>WHM Monitor</title>
<style>body{font-family:Arial;background:#f5f6fa}.box{max-width:420px;margin:80px auto;background:#fff;padding:25px;border-radius:12px}input,button{width:100%;padding:12px;margin:8px 0}</style>
<div class="box"><h2>WHM Server Monitor</h2><?php if($error):?><p><?=$error?></p><?php endif;?>
<form method="post"><input name="username" placeholder="اسم المستخدم" required><input type="password" name="password" placeholder="كلمة المرور" required><button>دخول</button></form></div>
