<?php
chdir(__DIR__);
require_once 'UnityBundle.php';
if (!file_exists('last_version')) {
  $last_version = array('MasterVer'=>-1,'AppBuild'=>33);
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}
$logFile = fopen('haifuri.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo date('[m/d H:i] ').$s."\n";
}

function GeneratePBKDF2Key($password, $salt) {
  return openssl_pbkdf2($password, $salt, 32, 32);
}
define("SAVEDATA_IV", hex2bin('1AF932518C58DCE9C2FA487EE96FB587'));
define('SAVEDATA_KEY', GeneratePBKDF2Key('savedata', 'd1e95c245d961a35e3e354b73f3960de9a53a4b72197c53f9cfe7d3398f77eed'));
define('MASTER_PASSWORD', 'nCG80L6W3FA3PdSv');

function EncryptSaveData($data) {
  return openssl_encrypt($data, 'AES-256-CBC', SAVEDATA_KEY, OPENSSL_RAW_DATA, SAVEDATA_IV);
}
function DecryptSaveData($data) {
  return openssl_decrypt($data, 'AES-256-CBC', SAVEDATA_KEY, OPENSSL_RAW_DATA, SAVEDATA_IV);
}

function EncryptMaster($data, $iv, $salt) {
  return openssl_encrypt($data, 'AES-256-CBC', GeneratePBKDF2Key(MASTER_PASSWORD, $salt), OPENSSL_RAW_DATA, hex2bin($iv));
}
function DecryptMaster($data, $iv, $salt) {
  return openssl_decrypt($data, 'AES-256-CBC', GeneratePBKDF2Key(MASTER_PASSWORD, $salt), OPENSSL_RAW_DATA, hex2bin($iv));
}


function main() {

global $last_version;
chdir(__DIR__);

//check app ver at 00:00
$appver = file_exists('appver') ? file_get_contents('appver') : '1.0.6';
$itunesid = 1304807840;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL=>'https://itunes.apple.com/lookup?id='.$itunesid.'&lang=ja_jp&country=jp&rnd='.rand(10000000,99999999),
  CURLOPT_HEADER=>0,
  CURLOPT_RETURNTRANSFER=>1,
  CURLOPT_SSL_VERIFYPEER=>false
));
$appinfo = curl_exec($curl);
curl_close($curl);
if ($appinfo !== false) {
  $appinfo = json_decode($appinfo, true);
  if (!empty($appinfo['results'][0]['version'])) {
    $prevappver = $appver;
    $appver = $appinfo['results'][0]['version'];

    if (version_compare($prevappver,$appver, '<')) {
      _log('new game version: '. $appver);
      file_put_contents('appver', $appver);
      $data = json_encode(array(
        'game'=>'haifuri',
        'ver'=>$appver,
        'link'=>'https://itunes.apple.com/jp/app/id'.$itunesid,
        'desc'=>$appinfo['results'][0]['releaseNotes']
      ));
      $header = [
        'X-GITHUB-EVENT: app_update',
        'X-HUB-SIGNATURE: sha1='.hash_hmac('sha1', $data, file_get_contents(__DIR__.'/../webhook_secret'), false)
      ];
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'https://redive.estertion.win/masterdb_subscription/webhook.php',
        CURLOPT_HEADER=>0,
        CURLOPT_RETURNTRANSFER=>1,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>$header,
        CURLOPT_POST=>1,
        CURLOPT_POSTFIELDS=>$data
      ));
      curl_exec($curl);
      curl_close($curl);
    }
  }
}

//check update
$curl = curl_init();
$appbuildUpper = $last_version['AppBuild'] + 25;
while (1) {
  $game_start_header = [
    'User-Agent: ',
    'Content-Length: 0',
    'Content-Type: application/octet-stream',
    'X-ANX-AppVersion: '.$last_version['AppBuild'],
    'X-ANX-MasterVersion: '.$last_version['MasterVer'],
    'X-ANX-Nonce: '.preg_replace('/(.{8})(.{4})(.{4})(.{4})(.{12})/', '$1-$2-$3-$4-$5', bin2hex(openssl_random_pseudo_bytes(16))),
    'X-ANX-Serialize: application/x-msgpack',
    'X-ANX-Store: 1',
    'X-ANX-Ts: '.time()
  ];
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://prd-app.haifuri.app/base/system',
    //CURLOPT_PROXY=>'127.0.0.1:50000',
    CURLOPT_HTTPHEADER=>$game_start_header,
    CURLOPT_HEADER=>0,
    CURLOPT_ENCODING=>'gzip',
    CURLOPT_RETURNTRANSFER=>1,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  $response = @msgpack_unpack(curl_exec($curl));
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($code == 426) {
    _log('Build '.$last_version['AppBuild'].' outdated');
    $last_version['AppBuild']++;
    if ($last_version['AppBuild'] >= $appbuildUpper) {
      _log('Max build guess exceeded, exiting');
      return;
    }
    continue;
  } else if ($code == 410) {
    file_put_contents('last_version', json_encode($last_version));
    break;
  } else if ($code == 200) {
    _log('no update found');
    file_put_contents('last_version', json_encode($last_version));
    return;
  } else {
    _log('invalid response: ['.$code.'] '. json_encode($response));
    return;
  }
}
curl_close($curl);

$MasterVer = $response['Version'];

global $curl;
$curl = curl_init();
$last_version['MasterVer'] = $MasterVer;
_log("MasterVer: ${MasterVer}");
file_put_contents('data/!MasterVer.txt', $MasterVer."\n");
file_put_contents('data/MasterHashes.json', prettifyJSON(json_encode($response, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE)));
curl_setopt_array($curl, [
  CURLOPT_RETURNTRANSFER=>true,
  //CURLOPT_PROXY=>'127.0.0.1:50000',
  CURLOPT_HEADER=>false,
  CURLOPT_SSL_VERIFYPEER=>false,
  CURLOPT_CONNECTTIMEOUT=>5
]);

$updateAsset = false;
foreach ($response['Signatures'] as $item) {
  $fname = 'data/'.$item['MasterName'].'.json';
  if (isset($last_version[$item['MasterName']]) && $last_version[$item['MasterName']] == $item['Sha1']) continue;
  if ($item['MasterName'] == 'AssetVersions') $updateAsset = true;
  _log('Downloading '. $item['MasterName']. ', Size: '.$item['Size'].' Sha1: '.$item['Sha1']);
  curl_setopt($curl, CURLOPT_URL, 'https://prd-static.haifuri.app/masters/'.$item['Signature'].'.j');
  $response = curl_exec($curl);
  if (strlen($response) != $item['Size'] || sha1($response) != $item['Sha1']) {
    _log('failed');
    continue;
  }
  $dec = DecryptMaster($response, $item['Iv'], $item['Salt']);
  file_put_contents($fname, prettifyJSON(json_encode(json_decode($dec, true), JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE)));
  $last_version[$item['MasterName']] = $item['Sha1'];
}

if ($updateAsset) {
  $AssetVersions = json_decode(file_get_contents('data/AssetVersions.json'), true);
  $assetVer = [];
  foreach ($AssetVersions['Rows'] as $row) {
    $assetVer[['AndroidAssets'=>'android', 'iOSAssets'=>'ios'][$row['Name']]] = $row['Version'];
  }
  foreach ($assetVer as $platform => $ver) {
    _log("Downloading assets manifest ver $ver for $platform");
    curl_setopt($curl, CURLOPT_URL, "https://prd-static.haifuri.app/assets/$platform/$ver/FileSystemOverrideRecords");
    $manifest = gzdecode(DecryptSaveData(curl_exec($curl)));
    file_put_contents("data/AssetManifest_${platform}.json", prettifyJSON(json_encode(json_decode($manifest), JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE)));
  }
}

file_put_contents('last_version', json_encode($last_version));

chdir('data');
exec('git add *.json !MasterVer.txt');
exec('git commit -m '.$MasterVer);
exec('git push origin master');
//checkAndUpdateResource();
_log('finished');

}

main();


function prettifyJSON($in) {
  $in = new MemoryStream($in);
  $out = new MemoryStream('');

  $offset = 0;
  $length = $in->size;
  
  $level = 0;
  while($offset < $length) {
    $char = $in->readData(1);
    switch($char) {
      case '"':
        // write until unqoute
        $out->write($char);
        $skipNext = false;
        while (1) {
          $char = $in->readData(1);
          $out->write($char);
          $offset++;
          if ($skipNext) $skipNext = false;
          else {
            if ($char == '\\') $skipNext = true;
            else if ($char == '"') break;
          }
        }
        break;
      case '{':
      case '[':
        // increase level
        $level++;
        $out->write($char);
        $out->write("\n".str_repeat('  ', $level));
        break;
      case '}':
      case ']':
        $level--;
        $nextChar = $in->readData(1);
        if ($nextChar == ',') {
          $out->write("\n".str_repeat('  ', $level));
          $out->write($char);
          $out->write($nextChar);
          $out->write("\n".str_repeat('  ', $level));
          $offset++;
        } else {
          $out->write("\n".str_repeat('  ', $level));
          $out->write($char);
          if ($nextChar == '') $out->write("\n");
          $in->seek($offset+1);
        }
        break;
      case ',':
        // add space after comma
        $out->write($char);
        $out->write(' ');
        break;
      default:
        $out->write($char);
    }
    $offset++;
  }
  $out->seek(0);
  $output = $out->readData($out->size);
  unset($out);
  return $output;
}
