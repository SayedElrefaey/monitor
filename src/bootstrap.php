<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configFile=__DIR__.'/../config/config.php';
if(!file_exists($configFile)) die('Create config/config.php from config.example.php');
$config=require $configFile;
date_default_timezone_set('Africa/Cairo');
$dsn="mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
try{
 $pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
 ]);
}catch(Throwable $e){http_response_code(500);die('Database connection failed.');}

function require_login(){if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}}
function crypto_key(){
 global $config;$hex=$config['app']['encryption_key']??'';
 if(!preg_match('/^[0-9a-fA-F]{64}$/',$hex)) throw new RuntimeException('Invalid encryption_key');
 return hex2bin($hex);
}
function encrypt_secret($plain){
 $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',crypto_key(),OPENSSL_RAW_DATA,$iv,$tag);
 if($cipher===false) throw new RuntimeException('Encryption failed');
 return base64_encode($iv.$tag.$cipher);
}
function decrypt_secret($encoded){
 $raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<28) throw new RuntimeException('Invalid encrypted secret');
 $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',crypto_key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
 if($plain===false) throw new RuntimeException('Secret decrypt failed');return $plain;
}

function telegram_alert_enabled(string $type): bool
{
    global $pdo;

    static $settings = null;

    if ($settings === null) {
        $st = $pdo->query("SELECT * FROM monitor_settings WHERE id=1 LIMIT 1");
        $settings = $st->fetch() ?: [];
    }

    if (empty($settings['telegram_enabled'])) {
        return false;
    }

    $map = [
        'offline'         => 'alert_server_offline',
        'server_offline'  => 'alert_server_offline',
        'online'          => 'alert_server_online',
        'server_online'   => 'alert_server_online',
        'service_apache'  => 'alert_apache',
        'service_mysql'   => 'alert_mysql',
        'service_dns'     => 'alert_dns',
        'service_exim'    => 'alert_exim',
        'sites_warning'   => 'alert_sites_warning',
        'sites_hard'      => 'alert_sites_hard',
        'disk_high'       => 'alert_disk_high',
        'ram_high'        => 'alert_ram_high',
        'load_high'       => 'alert_load_high',
    ];

    if (!isset($map[$type])) {
        return true;
    }

    return !empty($settings[$map[$type]]);
}

function telegram_send($text, string $type = ''){
 global $pdo;

 if ($type !== '' && !telegram_alert_enabled($type)) {
     return false;
 }

 $st=$pdo->query("SELECT telegram_bot_token_enc, telegram_chat_id, telegram_enabled FROM monitor_settings WHERE id=1 LIMIT 1");
 $settings=$st->fetch() ?: [];

 if(empty($settings['telegram_enabled']))return false;
 if(empty($settings['telegram_bot_token_enc'])||empty($settings['telegram_chat_id']))return false;

 try{
     $token=decrypt_secret($settings['telegram_bot_token_enc']);
 }catch(Throwable $e){
     return false;
 }

 $chat=trim((string)$settings['telegram_chat_id']);
 if(!$token||!$chat)return false;

 $ch=curl_init("https://api.telegram.org/bot".rawurlencode($token)."/sendMessage");
 curl_setopt_array($ch,[
     CURLOPT_POST=>true,
     CURLOPT_POSTFIELDS=>http_build_query(['chat_id'=>$chat,'text'=>$text]),
     CURLOPT_RETURNTRANSFER=>true,
     CURLOPT_CONNECTTIMEOUT=>5,
     CURLOPT_TIMEOUT=>10,
     CURLOPT_SSL_VERIFYPEER=>true,
     CURLOPT_SSL_VERIFYHOST=>2
 ]);
 $body=curl_exec($ch);
 $httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
 curl_close($ch);

 if($body===false||$httpCode!==200)return false;
 $result=json_decode($body,true);
 return is_array($result)&&!empty($result['ok']);
}
