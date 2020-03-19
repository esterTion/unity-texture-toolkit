<?php

// required: php-xxhash (https://github.com/esterTion/php-xxhash/ )

chdir(__DIR__);
require_once 'UnityBundle.php';
require_once 'resource_fetch.php';
if (!file_exists('last_version')) {
  $last_version = [];
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}

$logFile = fopen('sbr.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo $s."\n";
}

function prettifyJSON($in, Stream $out = NULL, $returnData = true) {
  $in = new MemoryStream($in);
  if ($out == NULL) $out = new MemoryStream('');

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
  if (!$returnData) return;
  $out->seek(0);
  $output = $out->readData($out->size);
  unset($out);
  return $output;
}


function main () {
    global $last_version;

    /*
    $appver = file_exists('appver') ? file_get_contents('appver') : '2.1.0';
    $itunesid = 1195834442;
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
          file_put_contents('appver', $appver);
          _log('new game version: '. $appver);
          $data = json_encode(array(
            'game'=>'bangdream',
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
    */

    $curl = curl_init();
    $headers = [
        'User-Agent: STHREE(iOS) OS(iOS 12.4) Device(iPhone8)',
        'Content-Type: application/x-mpac',
        'method: POST',
        'X-Unity-Version: 2018.4.14f1'
    ];
    curl_setopt_array($curl, [
        //CURLOPT_PROXY=>'127.0.0.1:50000',
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HEADER=>false,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_POST=>true
    ]);

    $url = "title/game_start";
    $request_body = [
        'url' => $url,
        "device_token" => "04d20474144748c09c78ecc46aa2cad7a2a0",
        "terminal_id" => "2484BE00-CE7A-4AEE-9725-F4AC04BE0E20",
        "advertising_id" => "154D5C69-B7CE-4AD7-8816-470CA7245D45",
        "is_tracking_enabled" => true,
        "session_key" => "",
        "platform" => 1,
        "app_version" => 10000000,
        "masterdata_version" => 0,
        "retry_count" => 0,
        "request_hash" => floor(microtime(true) * 1000)
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL=>"https://prod1-api.showbyrock-fes.com/$url",
        CURLOPT_POSTFIELDS=>msgpack_pack($request_body)
    ]);

    $response = msgpack_unpack(curl_exec($curl));
    //$response = msgpack_unpack(file_get_contents("title-game_start.dat"));
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (empty($response['body']['session_key'])) {
        _log("login failed $code");
        return false;
    }
    $userid = $response['body']['user']['id'];
    $session = $response['body']['session_key'];

    $url = "masterdata/tables";
    $request_body = [
        'url' => $url,
        "user_id" => $userid,
        "session_key" => $session,
        "platform" => 1,
        "app_version" => 10000000,
        "masterdata_version" => 0,
        "retry_count" => 0,
        "request_hash" => floor(microtime(true) * 1000)
    ];
    curl_setopt_array($curl, [
        CURLOPT_URL=>"https://prod1-api.showbyrock-fes.com/$url",
        CURLOPT_POSTFIELDS=>msgpack_pack($request_body)
    ]);

    $response = msgpack_unpack(curl_exec($curl));
    //$response = msgpack_unpack(file_get_contents("masterdata-tables.dat"));
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    print_r($response);
    
    if (empty($response['body']['table_versions'])) {
        _log("get masterdata failed $code");
        return false;
    }
    $updated_tables = [];
    foreach ($response['body']['table_versions'] as $table) {
        $name = $table['table_name'];
        $version = $table['version'];
        if (!empty($last_version[$name]) && $last_version[$name] == $version) {
            continue;
        }
        $updated_tables[$name] = $version;
    }
    print_r($updated_tables);

    if (empty($updated_tables)) {
        _log('no update found');
        return false;
    }

    $url = "masterdata/records";
    foreach ($updated_tables as $name=>$version) {
        $request_body = [
            'url' => $url,
            "user_id" => $userid,
            'table_name' => $name,
            "session_key" => $session,
            "platform" => 1,
            "app_version" => 10000000,
            "masterdata_version" => $version,
            "retry_count" => 0,
            "request_hash" => floor(microtime(true) * 1000)
        ];
        curl_setopt_array($curl, [
            CURLOPT_URL=>"https://prod1-api.showbyrock-fes.com/$url",
            CURLOPT_POSTFIELDS=>msgpack_pack($request_body)
        ]);
    
        $response = msgpack_unpack(curl_exec($curl));
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (empty($response['body']['records'])) {
            _log("download $name failed $code");
            continue;
        }
        fclose(fopen("data/$name.json", 'w'));
        $out = new FileStream("data/$name.json");
        prettifyJSON(json_encode($response['body']['records'], JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES), $out, false);
        $last_version[$name] = $version;
    }
    file_put_contents('last_version', json_encode($last_version));
    file_put_contents('data/!version.json', json_encode($last_version, JSON_PRETTY_PRINT));
    return true;
}

function asset() {
    global $last_version;
    $curl = curl_init();
    curl_setopt_array($curl, [
        //CURLOPT_PROXY=>'127.0.0.1:50000',
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HEADER=>false,
        CURLOPT_SSL_VERIFYPEER=>false
    ]);
    
    curl_setopt($curl, CURLOPT_URL, 'https://prod-dlc-cache.showbyrock-fes.com/asset/'.getAssetUrl('ios/filelist'));
    $assets_infos_enc = curl_exec($curl);
    $hash = hash('sha1', $assets_infos_enc);
    if (!empty($last_version['asset_infos']) && $last_version['asset_infos'] == $hash) {
        return false;
    }
    _log('new asset versions');
    $key = base64_decode('KAjyg6DTjC5ScfH9');
    $salt = base64_decode('2Ae2C7jQEJuU6h2j');
    $aesData = GeneratePBKDF2Key($key, $salt);
    $assets_infos_dec = openssl_decrypt($assets_infos_enc, 'AES-256-CBC', substr($aesData, 0, 32), OPENSSL_RAW_DATA, substr($aesData, 32, 16));
    $s = new MemoryStream($assets_infos_dec);
    $s->littleEndian = true;
    if ($s->ulong != 0xBFC2A1C2) {
        _log('invalid asset info format');
        return false;
    }
    $s->ulong;
    $assets = [];
    while ($s->position < $s->size) {
        $name = $s->readData($s->ulong);
        $assets[$name] = [
            'hash' => $s->ulonglong,
            'size' => $s->long,
            'category' => $s->long,
            'is_individual_download' => ord($s->byte)
        ];
    }
    ksort($assets);
    $assetInfosJson = json_encode($assets, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);

    try {
      curl_setopt($curl, CURLOPT_URL, 'https://prod-dlc-cache.showbyrock-fes.com/asset/'.getAssetUrl('ios/ios'));
      $assets_manifest_enc = curl_exec($curl);
      if (xxhash64($assets_manifest_enc, SBR_HASH_SEED) != dechex($assets[getAssetUrl('ios')]['hash'])) {
        throw new Exception('manfiest asset hash mismatch');
      }
      $assets_manifest_dec = decryptAsset_SBR($assets_manifest_enc);
      $assets_manifest = extractBundle(new MemoryStream($assets_manifest_dec));
      $asset = new AssetFile($assets_manifest[0]);
      $item = $asset->preloadTable[2];
      $asset->stream->position = $item->offset;
      $data = $asset->stream->readData($item->size);
      
      $s = new MemoryStream($data);
      $s->littleEndian = true;
      $name = $s->readAlignedString($s->long);
      $c = $s->long;
      $assetManifest = [];
      $assetManifest['name'] = [];
      $hashParts = [];
      $replaceParts = [[], []];
      foreach (['ios', 'cri/sound/livese', 'cri/sound/music', 'cri/sound/commonbgm', 'cri/sound/advbgm', 'cri/sound/commonse', 'cri/sound/advvoice', 'cri/sound/skillvoice', 'cri/sound/partnervoice', 'cri/sound/gamevoice', 'cri/sound/generalvoice', 'cri/sound/gachavoice', 'cri/sound/systemvoice', 'cri/movie', 'cri/titlemovie', 'commonse'] as $pathPart) {
        $hashParts[$pathPart] = xxhash64($pathPart, SBR_HASH_SEED, true);
        $replaceParts[0][] = $hashParts[$pathPart];
        $replaceParts[1][] = $pathPart;
      }
      for ($i=0; $i<$c; $i++) {
          $a = $s->long;
          $b = $s->readAlignedString($s->long);
          $assetManifest['name'][$a] = $b;
          foreach (explode('/', $b) as $pathPart) {
            if (!isset($hashParts[$pathPart])) {
              $hashParts[$pathPart] = xxhash64($pathPart, SBR_HASH_SEED, true);
              $replaceParts[0][] = $hashParts[$pathPart];
              $replaceParts[1][] = $pathPart;
            }
          }
      }
      $assetManifest['variant'] = [];
      $c = $s->long;
      for ($i=0; $i<$c; $i++) {
          $assetManifest['variant'][] = $s->long;
      }
      $assetManifest['info'] = [];
      $c = $s->long;
      for ($i=0; $i<$c; $i++) {
          $a = $s->long;
          $hash = bin2hex($s->readData(16));
          $dep_c = $s->long;
          $dep = [];
          for ($j=0; $j<$dep_c; $j++) {
              $dep[] = $s->long;
          }
          $assetManifest['info'][$a] = [
              'hash' => $hash,
              'dependencies' => $dep,
          ];
      }
      file_put_contents('data/asset_manifest.json', json_encode($assetManifest, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT));
      $assetInfosJson = str_replace($replaceParts[0], $replaceParts[1], $assetInfosJson);
      $assets = json_decode($assetInfosJson, true);
      $assetInfosJson = json_encode($assets, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);
      unset($asset);
      array_map('unlink', $assets_manifest);
    } catch (Exception $e) {
      _log('dl manifest failed '. $e->getMessage());
    }

    fclose(fopen("data/asset_infos.json", 'w'));
    $out = new FileStream("data/asset_infos.json");
    prettifyJSON($assetInfosJson, $out, false);
    $last_version['asset_infos'] = $hash;
    file_put_contents('last_version', json_encode($last_version));
    file_put_contents('data/!version.json', json_encode($last_version, JSON_PRETTY_PRINT));
}

$dbUpdate = main();
$assetUpdate = asset();
if ($dbUpdate || $assetUpdate) {
    chdir('data');
    exec('git add .');
    exec('git commit -m update');
    exec('git push origin master');
    chdir(__DIR__);
}
if ($assetUpdate) {
    // checkAndUpdateResource();
}