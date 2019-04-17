<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityAsset.php';

$poseData = [];
$resourceToExport = [
  [ 'bundleNameMatch'=>'/^cdtl\d+$/', 'nameMatch'=>'/^cas_(\d+)$/', 'exportTo'=>'card/$1' ]
];

function shouldExportFile($name, $rule) {
  return preg_match($rule['nameMatch'], $name) != 0;
}

$cacheHashDb = new PDO('sqlite:'.__DIR__.'/cacheHash.db');
$chkHashStmt = $cacheHashDb->prepare('SELECT hash FROM cacheHash WHERE res=?');
function shouldUpdate($name, $hash) {
  global $chkHashStmt;
  $chkHashStmt->execute([$name]);
  $row = $chkHashStmt->fetch();
  return !(!empty($row) && $row['hash'] == $hash);
}
$setHashStmt = $cacheHashDb->prepare('REPLACE INTO cacheHash (res,hash) VALUES (?,?)');
function setHashCached($name, $hash) {
  global $setHashStmt;
  $setHashStmt->execute([$name, $hash]);
}

function findRule($name, $rules) {
  foreach ($rules as $rule) {
    if (preg_match($rule['bundleNameMatch'], $name) != 0) return $rule;
  }
  return false;
}

define('RESOURCE_PATH_PREFIX', '/data/home/web/_redive/haifuri/');
//define('RESOURCE_PATH_PREFIX', 'D:/cygwin64/home/ester/quickcode/cgss/');

function checkAndUpdateResource($ver) {
  global $resourceToExport;
  global $curl;
  global $poseData;
  chdir(__DIR__);
  $currenttime = time();
  curl_setopt_array($curl, array(
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  $manifest = json_decode(file_get_contents('data/AssetManifest_ios.json'), true);

  foreach ($manifest['Rows'] as $info) {
    $name = $info['FileName'];
    $hash = $info['ContentHash'];
    if (($rule = findRule($name, $resourceToExport)) !== false && shouldUpdate($name, $hash)) {
      _log('download '. $name);
      curl_setopt_array($curl, array(
        CURLOPT_URL=>"https://prd-static.haifuri.app/assets/ios/$ver/".$info['DownloadName'],
        CURLOPT_HTTPHEADER=>['Range: bytes='.$info['AssetOffset'].'-'.($info['AssetOffset'] + $info['AssetLength'] - 1)]
      ));
      $bundleData = curl_exec($curl);
      $dlHash = hash('sha1', $bundleData);
      if ($dlHash != $hash) {
        _log('download failed  '.$name. ' '.json_encode(['expected'=>$hash,'received'=>$dlHash,'len'=>strlen($bundleData)]));
        continue;
      }
      if ($info['Crypto']) $bundleData = DecryptSaveData($bundleData);
      $bundleData = new MemoryStream($bundleData);
      $assets = extractBundle($bundleData);

      try{
      
      foreach ($assets as $asset) {
        if (substr($asset, -4,4) == '.resS') continue;
        $asset = new AssetFile($asset);
    
        foreach ($asset->preloadTable as &$item) {
          if ($item->typeString == 'Texture2D') {
            $item = new Texture2D($item, true);
            if (isset($rule['print'])) {
              var_dump($item->name);
              continue;
            }
            $itemname = $item->name;
            if (isset($rule['namePrefix'])) {
              $itemname = preg_replace($rule['bundleNameMatch'], $rule['namePrefix'], $name).$itemname;
            }
            if (isset($rule['namePrefixCb'])) {
              $itemname = preg_replace_callback($rule['bundleNameMatch'], $rule['namePrefixCb'], $name).$itemname;
            }
            if (shouldExportFile($itemname, $rule)) {
              $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['nameMatch'], $rule['exportTo'], $itemname);
              $item->exportTo($saveTo, 'webp', '-lossless 1');
              touch($saveTo. '.webp', $currenttime);
            }
            unset($item);
          }
        }
        $asset->__desctruct();
        unset($asset);
        gc_collect_cycles();
      }

      } catch(Exception $e) {
        $asset->__desctruct();
        unset($asset);
        _log('Not supported: '. $e->getMessage());
      }

      foreach ($assets as $asset) {
        unlink($asset);
      }
      unset($bundleData);
      if (isset($rule['print'])) exit;
      setHashCached($name, $hash);
    }
  }
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
  function GeneratePBKDF2Key($password, $salt) {
    return openssl_pbkdf2($password, $salt, 32, 32);
  }
  define("SAVEDATA_IV", hex2bin('1AF932518C58DCE9C2FA487EE96FB587'));
  define('SAVEDATA_KEY', GeneratePBKDF2Key('savedata', 'd1e95c245d961a35e3e354b73f3960de9a53a4b72197c53f9cfe7d3398f77eed'));
  
  function EncryptSaveData($data) {
    return openssl_encrypt($data, 'AES-256-CBC', SAVEDATA_KEY, OPENSSL_RAW_DATA, SAVEDATA_IV);
  }
  function DecryptSaveData($data) {
    return openssl_decrypt($data, 'AES-256-CBC', SAVEDATA_KEY, OPENSSL_RAW_DATA, SAVEDATA_IV);
  }
  chdir(__DIR__);
  $curl = curl_init();
  function _log($s) {echo date('[m/d H:i:s] ').$s."\n";}
  $AssetVersions = json_decode(file_get_contents('data/AssetVersions.json'), true);
  $assetVer = [];
  foreach ($AssetVersions['Rows'] as $row) {
    $assetVer[['AndroidAssets'=>'android', 'iOSAssets'=>'ios'][$row['Name']]] = $row['Version'];
  }
  checkAndUpdateResource($assetVer['ios']);
  //print_r(extractBundle(new FileStream('bundle/-card_bg_100062.unity3d')));
  /*$asset = new AssetFile('CAB-ed8bb316f8c8bc10b8cb0f403c1e3f35');
  foreach ($asset->preloadTable as &$item) {
    if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      _log($item->name);
      $item->exportTo($item->name, 'bmp');
    }
  }*/
}
//print_r($asset);

