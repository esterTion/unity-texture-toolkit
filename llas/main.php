<?php
require_once 'UnityBundle.php';

chdir(__DIR__);
$logFile = fopen('llas.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo date('[m/d H:i] ').$s."\n";
}

$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://allstars.kirara.ca/api/v1/master_version.json',
  CURLOPT_HEADER=>0,
  CURLOPT_RETURNTRANSFER=>1,
  CURLOPT_SSL_VERIFYPEER=>false
));

$verInfo = json_decode(curl_exec($curl), true);
if (empty($verInfo) || empty($verInfo['version'])) {
  _log('fetch version info failed');
  exit;
}
$version = $verInfo['version'];
if ($version == trim(file_get_contents('data/!masterdata_version.txt'))) {
  _log('no update');
  exit;
}
_log("new version: $version");
$urlBase = "https://jp-real-prod-v4tadlicuqeeumke.api.game25.klabgames.net/ep1001/static/$version";
curl_setopt($curl, CURLOPT_URL, "$urlBase/masterdata_i_ja");
$masterdata = curl_exec($curl);

$f = new MemoryStream($masterdata);
$f->position = 20;
$hash = ReadString($f);
$lang = ReadString($f);
$rows = ReadInt($f);

$out = [];
for ($i=0; $i < $rows; $i++) {
  $v15 = ReadString($f); // db name
  $v16 = ReadString($f); // db keys
  $out[] = [$v15, $v16, GetKeyData($v16)];
}
for ($i=0; $i < $rows; $i++) {
  $out[$i][] = bin2hex($f->readData(20)); // download sha1 hash
  $out[$i][] = ReadInt($f); // download file size
}
$db = new PDO('sqlite::memory:');
$db->query('pragma JOURNAL_MODE=MEMORY');
$db->query('CREATE TABLE masterdata(name TEXT NOT NULL, keys TEXT NOT NULL, hash TEXT NOT NULL, size INTEGER NOT NULL)');
$insert = $db->prepare('INSERT INTO masterdata (name,keys,hash,size) VALUES (?,?,?,?)');
foreach ($out as $row) {
  $insert->execute([$row[0], $row[1], $row[3], $row[4]]);
}
chdir('data');
$output = [];
exec('git rm -rf --ignore-unmatch -q */', $output);
chdir('..');
exportSqlite($db, 'data');

$constKeys = [
  0x3039, // 12345
  0x10932,// 67890
  0x7AB7  // 31415
];
$retry = 0;
for ($i=0; $i<count($out); $i++) {
  $row = $out[$i];
  $keys = [
    $constKeys[0] ^ $row[2][0],
    $constKeys[1] ^ $row[2][1],
    $constKeys[2] ^ $row[2][2],
  ];
  $name = $row[0];
  $hash = $row[3];
  $size = $row[4];
  _log("download $name, size: $size, hash: $hash");
  curl_setopt($curl, CURLOPT_URL, "$urlBase/$name");
  $data = curl_exec($curl);
  $recv = strlen($data);
  $recv_hash = hash('sha1', $data);
  if ($recv !== $size || $recv_hash !== $hash) {
    _log("download $name failed, received: $recv $recv_hash");
    if ($retry++ < 3) $i--;
    continue;
  }

  $data = gzinflate(SIFAS_Decrypt_Stream($data, ...$keys));
  file_put_contents('temp', $data);
  _log("export $name");
  exportSqlite(new PDO('sqlite:temp'), "data/$name");
  unlink('temp');
}
file_put_contents('data/!masterdata_version.txt', "$version\n");

chdir('data');
exec('git add *', $output);
exec('git commit -m "'.$version.'"', $output);
exec('git push origin master', $output);


function ReadByte(Stream $f) {
  return hexdec(bin2hex($f->byte));
}
function ReadInt(Stream $f) {
  $val = ReadByte($f);
  if ($val >= 128) {
    $b2 = ReadByte($f);
    $b3 = ReadByte($f);
    $b4 = ReadByte($f);
    $val = $val + (($b2 + (($b3 + ($b4 << 8)) << 8)) << 7);
  }
  return $val;
}
function ReadString(Stream $f) {
  $strlen = ReadInt($f);
  return $f->readData($strlen);
}
function GetKeyData($k) {
  $keys = [];
  for ($i=0; $i<strlen($k); $i+=8) {
    $keys[] = hexdec(substr($k, $i, 8));
  }
  return $keys;
}
function SIFAS_Decrypt_Stream(string $data, int $key0, int $key1, int $key2) {
  for ($j=0; $j<strlen($data); $j++) {
    $data[$j] = $data[$j] ^ chr((($key1 ^ $key0 ^ $key2) >> 24) & 0xff);
    $key0 = (0x343fd * $key0 + 0x269ec3) & 0xFFFFFFFF;
    $key1 = (0x343fd * $key1 + 0x269ec3) & 0xFFFFFFFF;
    $key2 = (0x343fd * $key2 + 0x269ec3) & 0xFFFFFFFF;
  }
  return $data;
}

function encodeValue($value) {
  $arr = [];
  foreach ($value as $key=>$val) {
    $arr[] = '/*'.$key.'*/' . (is_numeric($val) ? $val : ('"'.str_replace('"','\\"',$val).'"'));
  }
  return implode(", ", $arr);
}
function exportSqlite(PDO $db, string $path) {
  if (!is_dir($path)) {
    if (file_exists($path)) throw new Exception('output path is not directory');
    mkdir($path, 0777, true);
  }
  
  $tables = $db->query('SELECT * FROM sqlite_master')->fetchAll(PDO::FETCH_ASSOC);
  foreach ($tables as $entry) {
    if ($entry['name'] == 'sqlite_stat1') continue;
    if ($entry['type'] == 'table') {
      $tblName = $entry['name'];
      $f = fopen("$path/${tblName}.sql", 'w');
      fwrite($f, $entry['sql'].";\n");
      $values = $db->query("SELECT * FROM ${tblName}");
      while($value = $values->fetch(PDO::FETCH_ASSOC)) {
        fwrite($f, "INSERT INTO `${tblName}` VALUES (".encodeValue($value).");\n");
      }
      fclose($f);
    } else if ($entry['type'] == 'index' && $entry['sql']) {
      $tblName = $entry['tbl_name'];
      file_put_contents("$path/${tblName}.sql", $entry['sql'].";\n", FILE_APPEND);
    }
    //echo "\r".++$i.'/'.count($tables);
  }
}

/*
Read assets: (not tested)
SIFAS_Decrypt_Stream($encrypted_asset_data, 12345, $key1, $key2)
$key1 & $key2 from asset_i_ja_0.db
*/
