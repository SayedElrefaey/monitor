<?php
require_once __DIR__.'/src/bootstrap.php';require_login();
$id=(int)($_GET['id']??0);

$s=[
    'name'=>'',
    'agent_url'=>'',
    'sort_order'=>1,
    'website_warning_limit'=>0,
    'website_hard_limit'=>0,
    'enabled'=>1
];

$msg='';
if($id){
    $st=$pdo->prepare("SELECT * FROM servers WHERE id=?");
    $st->execute([$id]);
    $row=$st->fetch();
    if($row)$s=array_merge($s,$row);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??'');
    $url=trim($_POST['agent_url']??'');
    $token=trim($_POST['token']??'');
    $sort=max(1,(int)($_POST['sort_order']??1));
    $warn=max(0,(int)($_POST['website_warning_limit']??0));
    $hard=max(0,(int)($_POST['website_hard_limit']??0));
    $enabled=!empty($_POST['enabled'])?1:0;

    if($name==='' || !filter_var($url,FILTER_VALIDATE_URL)){
        $msg='تحقق من الاسم والرابط.';
    }elseif(!$id && $token===''){
        $msg='Agent Token مطلوب.';
    }else{
        if($id){
            if($token!==''){
                $st=$pdo->prepare("UPDATE servers
                    SET name=?,agent_url=?,agent_token_enc=?,sort_order=?,website_warning_limit=?,website_hard_limit=?,enabled=?
                    WHERE id=?");
                $st->execute([
                    $name,
                    $url,
                    encrypt_secret($token),
                    $sort,
                    $warn,
                    $hard,
                    $enabled,
                    $id
                ]);
            }else{
                $st=$pdo->prepare("UPDATE servers
                    SET name=?,agent_url=?,sort_order=?,website_warning_limit=?,website_hard_limit=?,enabled=?
                    WHERE id=?");
                $st->execute([
                    $name,
                    $url,
                    $sort,
                    $warn,
                    $hard,
                    $enabled,
                    $id
                ]);
            }
        }else{
            $st=$pdo->prepare("INSERT INTO servers(
                name,
                agent_url,
                agent_token_enc,
                sort_order,
                website_warning_limit,
                website_hard_limit,
                enabled
            ) VALUES(?,?,?,?,?,?,?)");
            $st->execute([
                $name,
                $url,
                encrypt_secret($token),
                $sort,
                $warn,
                $hard,
                $enabled
            ]);
        }
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>Server</title>
<style>
body{font-family:Arial;background:#f4f6f8}
.box{max-width:700px;margin:40px auto;background:#fff;padding:25px;border-radius:12px}
input{width:100%;padding:11px;margin:6px 0 14px;box-sizing:border-box}
button{padding:11px 20px}
.hint{background:#eef5ff;padding:12px;border-radius:8px}
</style>
<div class="box">
<h2><?=$id?'تعديل السيرفر':'إضافة سيرفر'?></h2>
<div class="hint">الـWHM API Token الرسمي يبقى داخل Agent على سيرفر WHM. هنا ندخل فقط Agent URL وAgent Token.</div>
<?php if($msg):?><p><?=$msg?></p><?php endif;?>
<form method="post">
<label>اسم السيرفر</label>
<input name="name" value="<?=htmlspecialchars($s['name'] ?? '',ENT_QUOTES,'UTF-8')?>" required>

<label>ترتيب السيرفر</label>
<input
    name="sort_order"
    type="number"
    min="1"
    step="1"
    value="<?=htmlspecialchars($s['sort_order'] ?? 1,ENT_QUOTES,'UTF-8')?>"
    required
>

<label>Agent URL</label>
<input name="agent_url" value="<?=htmlspecialchars($s['agent_url'] ?? '',ENT_QUOTES,'UTF-8')?>" required>

<label>Agent Token <?= $id?'(فارغ = الاحتفاظ بالحالي)':''?></label>
<input name="token" type="password" <?= $id?'':'required'?> >

<label>Website Warning Limit</label>
<input type="number" name="website_warning_limit" min="0" value="<?=htmlspecialchars($s['website_warning_limit'] ?? 0,ENT_QUOTES,'UTF-8')?>">

<label>Website Hard Limit</label>
<input type="number" name="website_hard_limit" min="0" value="<?=htmlspecialchars($s['website_hard_limit'] ?? 0,ENT_QUOTES,'UTF-8')?>">

<label><input style="width:auto" type="checkbox" name="enabled" <?=!empty($s['enabled'])?'checked':''?>> تفعيل</label><br><br>
<button type="submit">حفظ</button> <a href="dashboard.php">رجوع</a>
</form>
</div>
