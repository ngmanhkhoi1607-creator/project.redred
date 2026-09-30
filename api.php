<?php
// Circular Law Lab – API có đăng nhập + phân quyền (PHP 7.4+, không cần database).
ob_start();ini_set('display_errors','0');
const HDR="<?php exit;?>\n"; // dòng đầu chặn tải trực tiếp file dữ liệu
$cfg=['origins'=>[]];@include __DIR__.'/config.php'; // tùy chọn: $cfg['origins']=['https://ten.github.io'];
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$o=$_SERVER['HTTP_ORIGIN']??'';
if($o&&(in_array($o,$cfg['origins'],true)||in_array('*',$cfg['origins'],true))){header("Access-Control-Allow-Origin: $o");header('Access-Control-Allow-Headers: Content-Type');header('Vary: Origin');}
if(($_SERVER['REQUEST_METHOD']??'')==='OPTIONS'){http_response_code(204);exit;}
function out($a){while(ob_get_level())ob_end_clean();echo json_encode($a,JSON_UNESCAPED_UNICODE);exit;}
function E($m,$x=[]){return['ok'=>0,'err'=>$m]+$x;}
set_exception_handler(function($e){out(E('Lỗi máy chủ: '.$e->getMessage()));});
register_shutdown_function(function(){$e=error_get_last();if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_COMPILE_ERROR,E_CORE_ERROR],true)){while(ob_get_level())ob_end_clean();echo json_encode(['ok'=>0,'err'=>'Lỗi PHP: '.$e['message']],JSON_UNESCAPED_UNICODE);}});
$d=__DIR__.'/data';@mkdir($d,0755,true);if(!is_writable($d)){$d=sys_get_temp_dir().'/cll_data';@mkdir($d,0755,true);}
$in=json_decode(file_get_contents('php://input'),true);if(!is_array($in))$in=[];
$op=(string)($in['op']??$_GET['op']??'');
if($op==='ping')out(['ok'=>1,'php'=>PHP_VERSION,'writable'=>is_writable($d)]);
function db($f,$fn){if(!is_file($f))file_put_contents($f,HDR.'{}');$h=fopen($f,'c+');flock($h,LOCK_EX);
 $r=json_decode((string)substr(stream_get_contents($h),strlen(HDR)),true);if(!is_array($r))$r=[];$dirty=false;$res=$fn($r,$dirty);
 if($dirty){ftruncate($h,0);rewind($h);fwrite($h,HDR.json_encode($r,JSON_UNESCAPED_UNICODE));fflush($h);}flock($h,LOCK_UN);fclose($h);return $res;}
function sess(&$r,$u){foreach($r['sessions'] as $k=>$s)if($s['exp']<time())unset($r['sessions'][$k]);$t=bin2hex(random_bytes(24));$r['sessions'][$t]=['u'=>$u,'exp'=>time()+604800];return $t;}
$uf="$d/_users.php";$RK=['player'=>1,'host'=>2,'admin'=>3];

// ---------- Đăng ký / đăng nhập ----------
if($op==='register'||$op==='login')out(db($uf,function(&$r,&$dirty)use($op,$in){
 $r+=['users'=>[],'sessions'=>[],'fails'=>[],'open'=>true];
 $u=strtolower(trim((string)($in['u']??'')));$pw=(string)($in['pw']??'');
 if($op==='register'){
  if(!preg_match('/^[a-z0-9_]{3,20}$/',$u))return E('Tên đăng nhập 3–20 ký tự: a-z, 0-9, _');
  if(strlen($pw)<6)return E('Mật khẩu tối thiểu 6 ký tự');
  if(isset($r['users'][$u]))return E('Tên đăng nhập đã tồn tại');
  $first=!$r['users'];if(!$first&&!$r['open'])return E('Máy chủ đang đóng đăng ký – hãy nhờ Quản trị tạo tài khoản');
  $nm=mb_substr(trim((string)($in['name']??'')),0,14)?:$u;$nm=htmlspecialchars($nm,ENT_QUOTES);
  $r['users'][$u]=['name'=>$nm,'hash'=>password_hash($pw,PASSWORD_DEFAULT),'role'=>$first?'admin':'player','t'=>time()];
 }else{
  $f=$r['fails'][$u]??[0,0];if($f[0]>=5&&time()-$f[1]<300)return E('Sai quá nhiều lần, thử lại sau 5 phút');
  if(!isset($r['users'][$u])||!password_verify($pw,$r['users'][$u]['hash'])){$r['fails'][$u]=[$f[0]+1,time()];$dirty=true;return E('Sai tên đăng nhập hoặc mật khẩu');}
  unset($r['fails'][$u]);
 }
 $dirty=true;$x=$r['users'][$u];
 return['ok'=>1,'token'=>sess($r,$u),'user'=>['u'=>$u,'name'=>$x['name'],'role'=>$x['role']]];
}));

// ---------- Xác thực ----------
$tk=(string)($in['auth']??'');
$A=db($uf,function(&$r,&$dirty)use($tk){$s=$r['sessions'][$tk]??null;$x=($s&&$s['exp']>=time())?($r['users'][$s['u']]??null):null;
 return['user'=>$x?['u'=>$s['u'],'name'=>$x['name'],'role'=>$x['role']]:null,'has'=>!empty($r['users']),'open'=>$r['open']??true];});
$U=$A['user'];
if($op==='whoami')out(['ok'=>1,'user'=>$U,'has'=>$A['has'],'open'=>$A['open']]);
if(!$U)out(E('Cần đăng nhập',['login'=>1]));
$rank=$RK[$U['role']]??0;
if($op==='logout'){db($uf,function(&$r,&$dirty)use($tk){unset($r['sessions'][$tk]);$dirty=true;});out(['ok'=>1]);}

// ---------- Quản trị người dùng (admin) ----------
if($op==='users'||$op==='setopen'||$op==='setuser'){
 if($rank<3)out(E('Chỉ Quản trị mới có quyền này'));
 out(db($uf,function(&$r,&$dirty)use($op,$in,$U,$RK){
  $r+=['users'=>[],'sessions'=>[],'open'=>true];
  if($op==='setopen'){$r['open']=!empty($in['open']);$dirty=true;return['ok'=>1];}
  if($op==='setuser'){$t=strtolower((string)($in['target']??''));if(!isset($r['users'][$t]))return E('Không có tài khoản này');
   $na=count(array_filter($r['users'],function($x){return $x['role']==='admin';}));$isA=$r['users'][$t]['role']==='admin';
   if(!empty($in['del'])){if($isA&&$na<=1)return E('Không thể xóa Quản trị cuối cùng');unset($r['users'][$t]);}
   else{
    if(isset($in['role'])){if(!isset($RK[$in['role']]))return E('Quyền không hợp lệ');if($isA&&$in['role']!=='admin'&&$na<=1)return E('Phải còn ít nhất 1 Quản trị');$r['users'][$t]['role']=$in['role'];}
    if(!empty($in['pw'])){if(strlen((string)$in['pw'])<6)return E('Mật khẩu tối thiểu 6 ký tự');$r['users'][$t]['hash']=password_hash((string)$in['pw'],PASSWORD_DEFAULT);}
   }
   foreach($r['sessions'] as $k=>$s)if($s['u']===$t&&(!isset($r['users'][$t])||isset($in['pw'])))unset($r['sessions'][$k]);
   $dirty=true;return['ok'=>1];}
  $L=[];foreach($r['users'] as $k=>$x)$L[]=['u'=>$k,'name'=>$x['name'],'role'=>$x['role']];
  return['ok'=>1,'users'=>$L,'open'=>$r['open']];
 }));
}

// ---------- Phòng ----------
function rf($d,$c){return "$d/".preg_replace('/[^A-Z]/','',strtoupper($c)).".php";}
if($op==='rooms'){
 if($rank<2)out(E('Bạn không có quyền'));$L=[];
 foreach(glob("$d/[A-Z][A-Z][A-Z][A-Z].php")?:[] as $f){$x=json_decode((string)substr(file_get_contents($f),strlen(HDR)),true);
  if(is_array($x)&&($rank>=3||$x['owner']===$U['u']))$L[]=['code'=>basename($f,'.php'),'owner'=>$x['owner'],'players'=>count($x['players']),'started'=>$x['started']];}
 out(['ok'=>1,'rooms'=>$L]);
}
if($op==='create'){
 if($rank<2)out(E('Chỉ Quản trò hoặc Quản trị mới được tạo phòng'));
 foreach(glob("$d/[A-Z][A-Z][A-Z][A-Z].php")?:[] as $f)if(filemtime($f)<time()-172800)@unlink($f);
 do{$c='';for($i=0;$i<4;$i++)$c.='ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0,23)];}while(is_file("$d/$c.php"));
 if(!file_put_contents("$d/$c.php",HDR.json_encode(['owner'=>$U['u'],'started'=>0,'seed'=>0,'players'=>[],'moves'=>[]])))out(E('Không ghi được thư mục data/ (đặt quyền 755/775)'));
 out(['ok'=>1,'code'=>$c]);
}
$f=rf($d,(string)($in['room']??''));if(!is_file($f))out(E('Không tìm thấy phòng'));
if($op==='close'){$x=json_decode((string)substr(file_get_contents($f),strlen(HDR)),true);
 if($rank>=3||($x&&$x['owner']===$U['u'])){@unlink($f);out(['ok'=>1]);}out(E('Chỉ Quản trò của phòng hoặc Quản trị mới được đóng phòng'));}
out(db($f,function(&$r,&$dirty)use($op,$in,$U,$rank){
 $me=-1;foreach($r['players'] as $i=>$p)if($p['u']===$U['u'])$me=$i;
 $mgr=$r['owner']===$U['u']||$rank>=3;$err=null;
 switch($op){
  case 'join':if($me<0&&!$r['started']){if(count($r['players'])>=6)$err='Phòng đã đủ 6 người';
    else{$used=array_column($r['players'],'role');for($k=0;in_array($k,$used);$k++);$r['players'][]=['u'=>$U['u'],'name'=>$U['name'],'role'=>$k];$me=count($r['players'])-1;$dirty=true;}}break;
  case 'leave':if($me>=0&&!$r['started']){array_splice($r['players'],$me,1);$me=-1;$dirty=true;}break;
  case 'set':if($me>=0&&!$r['started']){$k=(int)($in['role']??-1);if($k>=0&&$k<6&&!in_array($k,array_column($r['players'],'role'))){$r['players'][$me]['role']=$k;$dirty=true;}}break;
  case 'kick':if($mgr&&!$r['started']){$i=(int)($in['i']??-1);if(isset($r['players'][$i])&&$i!==$me){array_splice($r['players'],$i,1);$me=-1;foreach($r['players'] as $j=>$p)if($p['u']===$U['u'])$me=$j;$dirty=true;}}break;
  case 'start':if($mgr&&!$r['started']){if(count($r['players'])<2)$err='Cần ít nhất 2 người chơi';else{$r['started']=1;$r['seed']=random_int(1,2000000000);$dirty=true;}}elseif(!$mgr)$err='Chỉ Quản trò mới được bắt đầu';break;
  case 'move':if($r['started']&&$me>=0&&is_array($in['m']??null)&&count($r['moves'])<30000){$m=$in['m'];
    $r['moves'][]=['p'=>$me,'f'=>(string)($m['f']??''),'a'=>array_values(array_slice((array)($m['a']??[]),0,4))];$dirty=true;}break;
 }
 $since=max(0,(int)($in['since']??0));
 return['ok'=>$err?0:1,'err'=>$err,'me'=>$me,'mgr'=>$mgr,'owner'=>$r['owner'],'started'=>$r['started'],'seed'=>$r['seed'],
  'players'=>array_map(function($p){return['name'=>$p['name'],'role'=>$p['role']];},$r['players']),'from'=>$since,'moves'=>array_slice($r['moves'],$since)];
}));
