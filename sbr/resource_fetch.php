<?php
if (count(get_included_files()) == 1) define ('TEST_SUITE', __FILE__);

require_once 'UnityAsset.php';
define('SBR_HASH_SEED', 0x33DC7CB65408BDF);

$resourceToExport = [
    [ 'bundleNameMatch'=>'/^charactercard\/charactercard(\d+)$/', 'nameMatch'=>'/^charactercard(.+)$/',     'exportTo'=>'card/$1' ],
    [ 'bundleNameMatch'=>'/^humanmodel\/humanmodel(\d+)$/', 'customAssetProcessor' => 'exportHumanModel' ],
];

function GeneratePBKDF2Key($password, $salt) {
    return hash_pbkdf2('SHA1', $password, $salt, 1024, 32+16, true);
}
function getAssetUrl(string $path) {
    $path = explode('/', trim($path, '/'));
    if (count($path) > 3) {
        array_splice(
            $path, 1, 0,
            implode('/', array_splice($path, 1, count($path) - 2))
        );
    }
    $path = array_map(function ($i) {
        return xxhash64($i, SBR_HASH_SEED, true);
    }, $path);
    return implode('/', $path);
}
function decryptAsset_SBR(string $data) {
    $key = hex2bin("d9e7f9f78d35ef76f8e377bd6f7ddde3c7dadb5e78e3bef4");
    $salt = hex2bin("ef4d3adfbeb871bf76e3cd356f671de1c6b57396f46fddf4");
    $aesData = GeneratePBKDF2Key($key, $salt);
    return openssl_decrypt($data, 'AES-256-CBC', substr($aesData, 0, 32), OPENSSL_RAW_DATA, substr($aesData, 32, 16));
}

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

define('RESOURCE_PATH_PREFIX', '/data/home/web/_redive/sbr/');

function exportHumanModel($asset, $remoteTime) {
  foreach ($asset->preloadTable as $item) {
    if ($item->typeString == 'TextAsset') {
      $item = new TextAsset($item, true);
      checkAndCreateFile(RESOURCE_PATH_PREFIX.'spine/model/'.$item->name, $item->data, $remoteTime);
    } else if ($item->typeString == 'Texture2D') {
      $item = new Texture2D($item, true);
      $saveTo = RESOURCE_PATH_PREFIX.'spine/model/'.$item->name;
      $item->exportTo($saveTo, 'png');
      if (filemtime($saveTo.'.png') > $remoteTime)
      touch($saveTo.'.png', $remoteTime);
    }
  }
}

function checkAndUpdateResource() {
    global $resourceToExport;
    $curl = curl_init();
    chdir(__DIR__);
    $currenttime = time();
    curl_setopt_array($curl, array(
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_ENCODING=>'gzip',
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HEADER=>0,
        CURLOPT_SSL_VERIFYPEER=>false
    ));
    $manifest = json_decode(file_get_contents('data/asset_infos.json'), true);
    
    foreach ($manifest as $name => $info) {
        if (($rule = findRule($name, $resourceToExport)) !== false && shouldUpdate($name, $info['hash'])) {
            _log('download '. $name);
            curl_setopt_array($curl, array(
                CURLOPT_URL=>'https://prod-dlc-cache.showbyrock-fes.com/asset/'.getAssetUrl('ios/'.$name),
            ));
            $bundleData = curl_exec($curl);
            $gotHash = xxhash64($bundleData, SBR_HASH_SEED);
            $expectedHash = str_pad(dechex($info['hash']), 16, '0', STR_PAD_LEFT);
            if ($gotHash != $expectedHash) {
                _log('download failed  '.$name . " expected $expectedHash got $gotHash");
                continue;
            }
            $bundleData = new MemoryStream(decryptAsset_SBR($bundleData));
            $assets = extractBundle($bundleData);
            
            try{

                foreach ($assets as $asset) {
                    if (substr($asset, -4,4) == '.resS') continue;
                    $asset = new AssetFile($asset);
                    
                    if (isset($rule['customAssetProcessor'])) {
                        call_user_func($rule['customAssetProcessor'], $asset, $currenttime);
                    } else
                    foreach ($asset->preloadTable as &$item) {
                        if ($item->typeString == 'Texture2D') {
                            $item = new Texture2D($item, true);
                            $itemname = $item->name;
                            if (isset($rule['namePrefix'])) {
                                $itemname = preg_replace($rule['bundleNameMatch'], $rule['namePrefix'], $name).$itemname;
                            }
                            if (isset($rule['print'])) {
                                var_dump($itemname);
                                continue;
                            }
                            if (shouldExportFile($itemname, $rule)) {
                                $saveTo = RESOURCE_PATH_PREFIX. preg_replace($rule['nameMatch'], $rule['exportTo'], $itemname);
                                $param = '-lossless 1';
                                if (isset($rule['extraParam'])) $param .= ' '.$rule['extraParam'];
                                if (isset($rule['extraParamCb'])) $param .= ' '.call_user_func($rule['extraParamCb'], $item);
                                $item->exportTo($saveTo, 'webp', $param);
                                if (filemtime($saveTo. '.webp') > $currenttime)
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
            //if (isset($rule['print'])) exit;
            setHashCached($name, $info['hash']);
        }
    }
}

if (defined('TEST_SUITE') && TEST_SUITE == __FILE__) {
    chdir(__DIR__);
    function _log($s) {echo "$s\n";}
    checkAndUpdateResource();
    /*$asset = new AssetFile('CAB-4856cccde53d6f3bfd0054253f1639a8');
    foreach ($asset->preloadTable as &$item) {
        if ($item->typeString == 'Texture2D') {
            $item = new Texture2D($item, true);
            _log($item->name);
            $item->exportTo($item->name, 'webp', '-lossless 1');
        }
    }*/
}