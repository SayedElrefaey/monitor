<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WHM Monitor API Test</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:20px}
.box{max-width:1100px;margin:auto;background:#fff;border-radius:14px;padding:22px;box-shadow:0 4px 18px #0001}
h2{margin-top:0}
input{width:100%;padding:12px;margin:7px 0 12px;box-sizing:border-box;border:1px solid #ddd;border-radius:8px}
button{padding:11px 16px;border:0;border-radius:8px;cursor:pointer;margin:5px}
.primary{background:#172033;color:#fff}.secondary{background:#e9eef5}
.status{padding:12px;border-radius:8px;margin:12px 0;background:#eef5ff}
pre{background:#111;color:#eee;padding:15px;border-radius:10px;white-space:pre-wrap;word-break:break-word;direction:ltr;text-align:left;min-height:120px}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:center}
th{background:#eef1f5}.online{color:#16843a;font-weight:bold}.offline{color:#c62828;font-weight:bold}
.limit{background:#ffd6d6}.warn{background:#fff0c7}
</style>
<script type="text/javascript" nonce="99a8f80d85b34666befec119ea7" src="//local.adguard.org?ts=1787696527836&amp;type=content-script&amp;dmn=chatgpt.com&amp;url=https%3A%2F%2Fchatgpt.com%2Fbackend-api%2Festuary%2Fcontent%3Fid%3Dfile_00000000e8f881f48cbf6232c0048f2e%26fn%3Dtest-api.html%26cd%3Dattachment%26ts%3D496582%26p%3Dfs%26cid%3D1%26sig%3De8120faed392f6860db056d397e6d7019cb0886b5d589ada2a5372ca2ce3bc4d%26v%3D0&amp;app=chrome.exe&amp;css=2&amp;js=1&amp;rel=1&amp;rji=1&amp;sbe=1&amp;stealth=1&amp;st-push&amp;st-loc&amp;st-dnt"></script><script type="text/javascript" nonce="99a8f80d85b34666befec119ea7" src="//local.adguard.org?ts=1787696527836&amp;name=AdGuard%20Extra&amp;name=AdGuard%20Popup%20Blocker&amp;type=user-script"></script></head>
<body>
<div class="box">
<h2>اختبار WHM Monitor API</h2>

<label>اسم المستخدم</label>
<input id="username" placeholder="Sayed">

<label>كلمة المرور</label>
<input id="password" type="password" placeholder="كلمة المرور">

<button class="primary" onclick="login()">تسجيل الدخول</button>
<button class="secondary" onclick="getServers()">جلب السيرفرات</button>
<button class="secondary" onclick="getAlerts()">جلب التنبيهات</button>

<div id="status" class="status">جاهز للاختبار</div>
<div id="serversBox"></div>
<pre id="result"></pre>
</div>

<script>
let authToken = '';

function setStatus(text, ok=false){
  const e=document.getElementById('status');
  e.textContent=text;
  e.style.background=ok?'#eaf8ee':'#eef1f5';
}

async function login(){
  setStatus('جاري تسجيل الدخول...');
  try{
    const r=await fetch('login.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        username:document.getElementById('username').value.trim(),
        password:document.getElementById('password').value
      })
    });
    const data=await r.json();
    if(r.ok && data.status==='ok'){
      authToken=data.token;
      setStatus('تم تسجيل الدخول بنجاح ✅',true);
    }else setStatus('فشل تسجيل الدخول ❌');
    document.getElementById('result').textContent=JSON.stringify(data,null,2);
  }catch(e){
    setStatus('حدث خطأ أثناء الاتصال');
    document.getElementById('result').textContent=String(e);
  }
}

async function getServers(){
  if(!authToken){setStatus('سجل الدخول أولاً');return;}
  setStatus('جاري جلب السيرفرات...');
  try{
    const r=await fetch('servers.php',{headers:{'Authorization':'Bearer '+authToken}});
    const data=await r.json();
    if(!r.ok || data.status!=='ok'){
      setStatus('تعذر جلب السيرفرات ❌');
      document.getElementById('result').textContent=JSON.stringify(data,null,2);
      return;
    }
    setStatus('تم جلب السيرفرات بنجاح ✅',true);
    renderServers(data.servers||[]);
    document.getElementById('result').textContent=JSON.stringify(data,null,2);
  }catch(e){
    setStatus('حدث خطأ أثناء جلب السيرفرات');
    document.getElementById('result').textContent=String(e);
  }
}

async function getAlerts(){
  if(!authToken){setStatus('سجل الدخول أولاً');return;}
  setStatus('جاري جلب التنبيهات...');
  try{
    const r=await fetch('alerts.php',{headers:{'Authorization':'Bearer '+authToken}});
    const data=await r.json();
    if(!r.ok || data.status!=='ok'){
      setStatus('تعذر جلب التنبيهات ❌');
    }else{
      setStatus('تم جلب التنبيهات بنجاح ✅',true);
    }
    document.getElementById('result').textContent=JSON.stringify(data,null,2);
  }catch(e){
    setStatus('حدث خطأ أثناء جلب التنبيهات');
    document.getElementById('result').textContent=String(e);
  }
}

function renderServers(servers){
  const box=document.getElementById('serversBox');
  if(!servers.length){box.innerHTML='<p>لا توجد سيرفرات.</p>';return;}
  let html='<table><tr><th>#</th><th>السيرفر</th><th>الحالة</th><th>المواقع</th><th>RAM</th><th>Disk</th><th>Backup</th><th>الخدمات</th><th>آخر فحص</th></tr>';
  for(const s of servers){
    const row=s.limit_reached?'limit':(s.warning_reached?'warn':'');
    const status=s.last_status==='online'?'online':'offline';
    const services=`Apache ${s.apache_status||'-'} | MySQL ${s.mysql_status||'-'} | DNS ${s.dns_status||'-'} | Exim ${s.exim_status||'-'}`;
    html+=`<tr class="${row}">
      <td>${esc(s.sort_order??'')}</td>
      <td>${esc(s.name??'')}</td>
      <td class="${status}">${esc(s.last_status??'-')}</td>
      <td>${esc(String(s.accounts??0))}${s.website_hard_limit>0?' / '+esc(String(s.website_hard_limit)):''}</td>
      <td>${esc(String(s.ram_percent??'-'))}%</td>
      <td>${esc(String(s.disk_percent??'-'))}%</td>
      <td>${esc(String(s.backup_percent??'-'))}%</td>
      <td>${esc(services)}</td>
      <td>${esc(s.last_checked_at??'-')}</td>
    </tr>`;
  }
  box.innerHTML=html+'</table>';
}

function esc(v){
  return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}
</script>
</body>
</html>
