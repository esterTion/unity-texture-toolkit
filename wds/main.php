<?php

require 'msgpack.phar';

ini_set('precision', 7);
ini_set('serialize_precision', 7);
ini_set('memory_limit', '256M');

// mod from https://github.com/rybakit/msgpack.php/blob/master/src/Extension/TimestampExtension.php
class TimestampExtension implements \MessagePack\Extension
{
    private const TYPE = -1;
    public function getType() : int
    {
        return self::TYPE;
    }
    public function pack(\MessagePack\Packer $packer, $value) : ?string
    {
			throw new Exception('not implemented');
    }

    public function unpackExt(\MessagePack\BufferUnpacker $unpacker, int $extLength)
    {
        if (4 === $extLength) {
            $data = $unpacker->read(4);

            $sec = \ord($data[0]) << 24
                | \ord($data[1]) << 16
                | \ord($data[2]) << 8
                | \ord($data[3]);

            return gmdate('Y-m-d H:i:s', $sec);
        }
        if (8 === $extLength) {
            $data = $unpacker->read(8);

            $num = \unpack('J', $data)[1];
            $nsec = $num >> 34;
            if ($nsec < 0) {
                $nsec += 0x40000000;
            }

            return gmdate('Y-m-d H:i:s', $num & 0x3ffffffff);
            //return new Timestamp($num & 0x3ffffffff, $nsec);
        }

        $data = $unpacker->read(12);

        $nsec = \ord($data[0]) << 24
            | \ord($data[1]) << 16
            | \ord($data[2]) << 8
            | \ord($data[3]);

        return gmdate('Y-m-d H:i:s', \unpack('J', $data, 4)[1]);
        //return new Timestamp(\unpack('J', $data, 4)[1], $nsec);
    }
}
class Lz4BlockExtension implements \MessagePack\Extension {
	private const TYPE = 99;
	public function getType() : int
	{
		return self::TYPE;
	}
	public function pack(\MessagePack\Packer $packer, $value) : ?string
	{
		throw new Exception('not implemented');
	}

	public function unpackExt(\MessagePack\BufferUnpacker $unpacker, int $extLength)
	{
		$subData = $unpacker->read($extLength);
		$subData = lz4_uncompress($subData[4].$subData[3].$subData[2].$subData[1].substr($subData, 5));
		$subUnpacker = $unpacker->withBuffer($subData);
		return $subUnpacker->reset($subData)->unpack();
	}
}
class Lz4BlockArrayExtension implements \MessagePack\Extension {
	private const TYPE = 98;
	public function getType() : int
	{
		return self::TYPE;
	}
	public function pack(\MessagePack\Packer $packer, $value) : ?string
	{
		throw new Exception('not implemented');
	}

	public function unpackExt(\MessagePack\BufferUnpacker $unpacker, int $extLength)
	{
		$lenData = $unpacker->read($extLength);
		$subUnpacker = $unpacker->withBuffer($lenData);
		$uncompressedSizes = [];
		while ($subUnpacker->hasRemaining()) {
			$uncompressedSizes[] = $subUnpacker->unpack();
		}
		$uncompressedDatas = [];
		foreach ($uncompressedSizes as $uncompressedSize) {
			$bin = $unpacker->unpack();
			$uncompressedDatas[] = lz4_uncompress(pack('V', $uncompressedSize) . $bin);
		}
		$dataAfter = $unpacker->read($unpacker->getRemainingCount());
		$unpacker->reset(str_repeat("\xC0", count($uncompressedSizes)). $dataAfter);
		return $subUnpacker->reset(implode('', $uncompressedDatas))->unpack();
	}
}

require 'UnityAsset.php';

class WdsNoteType {
	const None = 0;
	const Normal = 10;
	const Critical = 20;
	const Sound = 30;
	const SoundPurple = 31;
	const Scratch = 40;
	const Flick = 50;
	const HoldStart = 80;
	const CriticalHoldStart = 81;
	const ScratchHoldStart = 82;
	const ScratchCriticalHoldStart = 83;
	const Hold = 100;
	const CriticalHold = 101;
	const ScratchHold = 110;
	const ScratchCriticalHold = 111;
	const HoldEighth = 900;
}

class PersistentStorage {
	static $data = null;
	static function init() {
		if (empty(static::$data)) {
			static::$data = json_decode(file_get_contents(__DIR__.'/storages.json'), true);
		}
	}
	static function get($key, $default = '') {
		static::init();
		return empty(static::$data[$key]) ? $default : static::$data[$key];
	}
	static function set($key, $val) {
		static::$data = json_decode(file_get_contents(__DIR__.'/storages.json'), true);
		static::$data[$key] = $val;
		file_put_contents(__DIR__.'/storages.json', json_encode(static::$data));
		return $val;
	}
}
class MsgPackHelper {
	static $typesDb = null;
	static $getTypeByTypeStmt;
	static $getTypeByTableNameStmt;
	static $enumTables = [];
	static function loadTypes() {
		if (!static::$typesDb) {
			static::$typesDb = new PDO('sqlite:'.__DIR__.'/types.db');
			static::$getTypeByTypeStmt = static::$typesDb->prepare('SELECT keys FROM types WHERE class = ?');
			static::$getTypeByTableNameStmt = static::$typesDb->prepare('SELECT keys FROM types WHERE table_name = ?');
			$enumTables = static::$typesDb->query('SELECT `enum`,`table` FROM enums')->fetchAll(PDO::FETCH_NUM);
			foreach ($enumTables as $row) {
				static::$enumTables[$row[0]] = json_decode($row[1], true);
			}
		}
	}
	static function toEnumName($type, $value) {
		$type = preg_replace('(^Nullable<(.+)>$)', '$1', $type);
		if (!isset(static::$enumTables[$type])) return $value;
		if (!isset(static::$enumTables[$type][$value])) return $value;
		return static::$enumTables[$type][$value];
	}
	static function fromEnumName($type, $value) {
		$type = preg_replace('(^Nullable<(.+)>$)', '$1', $type);
		static::loadTypes();
		if (!isset(static::$enumTables[$type])) return $value;
		$key = array_search($value, static::$enumTables[$type]);
		return $key === false ? $value : $key;
	}
	static function applyTypeName(&$data, $keys) {
		$count = count($data);
		for ($i = 0; $i < $count; $i++) {
			if ($data[$i] === null) {
				unset($data[$i]);
				continue;
			}
			if (empty($keys[$i])) continue;
			if (is_array($data[$i])) {
				static::applyTypeNameByType($data[$i], $keys[$i]['type']);
			}

			$data[$keys[$i]['name']] = static::toEnumName($keys[$i]['type'], $data[$i]);
			unset($data[$i]);
		}
	}
	static function applyTypeNameByType(&$data, $name) {
		static::loadTypes();

		$name = preg_replace('(^Nullable<(.+)>$)', '$1', $name);
		$isArray = substr($name, -2, 2) === '[]';
		if ($isArray) $name = substr($name, 0, -2);

		static::$getTypeByTypeStmt->execute([$name]);
		$row = static::$getTypeByTypeStmt->fetch(PDO::FETCH_NUM);
		if (empty($row)) return;
		$keys = json_decode($row[0], true);
		if ($isArray) {
			for ($i=0; $i<count($data); $i++) {
				static::applyTypeName($data[$i], $keys);
			}
		} else {
			static::applyTypeName($data, $keys);
		}
	}
	static function applyTypeNameByTableName(&$data, $name) {
		static::loadTypes();
		static::$getTypeByTableNameStmt->execute([$name]);
		$row = static::$getTypeByTableNameStmt->fetch(PDO::FETCH_NUM);
		if (empty($row)) return;
		$keys = json_decode($row[0], true);

		for ($i=0; $i<count($data); $i++) {
			static::applyTypeName($data[$i], $keys);
		}
	}
	static function parseServerResponse(string $resp, string $resultType = '') {
		$timestampExtension = new TimestampExtension;
		$lz4BlockArrayExtension = new Lz4BlockArrayExtension;
		$unpacker = new \MessagePack\BufferUnpacker;
		$unpacker = $unpacker->extendWith($timestampExtension)->extendWith($lz4BlockArrayExtension);
		$unpacker->reset($resp);
		$responseBlocks = [];
		while ($unpacker->hasRemaining()) {
			$responseBlocks[] = $unpacker->unpack();
		}
		if (!empty($resultType) && !empty($responseBlocks[1][0])) {
			static::applyTypeNameByType($responseBlocks[1][0], $resultType);
		}
		return $responseBlocks;
	}
	static function prettifyJSON($in) {
		$a = $in;
		if (!is_array($a)) $a = json_decode($a);
		$a = json_encode($a, JSON_UNESCAPED_UNICODE+JSON_UNESCAPED_SLASHES+JSON_PRETTY_PRINT);
		$a = preg_replace("/([^\]\}]),\n +/", "$1, ", $a);
		$a = preg_replace('/("[^"]+?":) /', '$1', $a);
		$a = preg_replace_callback("/\n +/", function ($m) { return "\n".str_repeat(' ', (strlen($m[0])-1) / 2); }, $a);
		return $a;
	}
}
class CurlMultiHelper {
	// function (CurlHandle|null) -> CurlHandle|null
	public $setupCb;
	// function (CurlHandle) -> void
	public $finishCb;
	public $baseCurl;
	public $maxParallel = 5;
	private $currentParallel = 0;
	private $mh;
	function __construct() {
		$this->mh = curl_multi_init();
	}
	function run() {
		while ($this->currentParallel < $this->maxParallel) {
			if (!$this->add()) {
				break;
			}
		}
		while ($this->currentParallel > 0) {
			$finishedHandle = $this->anyFinished();
			curl_multi_remove_handle($this->mh, $finishedHandle);
			call_user_func($this->finishCb, $finishedHandle);
			$this->currentParallel--;
			$this->add($finishedHandle);
		}
	}
	function add($handle = null) {
		$nextHandle = call_user_func($this->setupCb, $handle);
		if ($nextHandle) {
			$this->currentParallel++;
			curl_multi_add_handle($this->mh, $nextHandle);
			return true;
		}
		return false;
	}
	function anyFinished() {
		do {
			while (($code = curl_multi_exec($this->mh, $active)) == CURLM_CALL_MULTI_PERFORM) ;
			if ($code != CURLM_OK) {
				break;
			}
			usleep(1000);
			if ($done = curl_multi_info_read($this->mh)) {
				return $done['handle'];
			}
			//var_dump($active, $done);
		} while ($active);
		throw new Exception("curl multi error: ".curl_multi_strerror($code));
	}
}

class WdsNotationConverter {
	static $assetUrl;
	static $apiBase;
	static $assetVersion;
	static $appVersion;
	static $appVersionFull;
	static $masterUrl;

	static function init() {
		static::$assetUrl = PersistentStorage::get('assetUrl', 'https://assets.wds-stellarium.com/production');
		static::$apiBase = PersistentStorage::get('apiBase', 'https://ag-api.wds-stellarium.com');
		static::$assetVersion = PersistentStorage::get('assetVersion', '1.1.0');
		static::$appVersion = PersistentStorage::get('appVersion', '1.4.0');
		static::$appVersionFull = PersistentStorage::get('appVersionFull', '1.4.0.79');
		static::$masterUrl = PersistentStorage::get('masterUrl', 'https://assets.wds-stellarium.com/master-data/production');
	}

	static $logFile = null;
	static function _log($s, $logFile = true) {
		echo date('[Y/m/d H:i:s] ')."$s\n";
		if (!static::$logFile) {
			static::$logFile = fopen(__DIR__.'/wds.log', 'a');
		}
		if ($logFile) {
			fwrite(static::$logFile, date('[Y/m/d H:i:s] ')."$s\n");
		}
	}
	static function delTree($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? static::delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	static $ch = null;
	static function initCurl() {
		if (static::$ch !== null) return;
		static::$ch = curl_init();
		curl_setopt_array(static::$ch, [
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_HEADER=>false,
			CURLOPT_ENCODING=>'',
			//CURLOPT_PROXY=>'127.0.0.1:50000',
		]);
		if (file_exists(__DIR__.'/useproxy.txt')) {
			curl_setopt(static::$ch, CURLOPT_PROXY, file_get_contents(__DIR__.'/useproxy.txt'));
		}
	}
	static $apiCh = null;
	static function getApiHeaders($append = []) {
		return array_merge(['Content-Type: application/vnd.msgpack', 'X-Platform: app-store', 'X-FM: 0', 'Accept: application/vnd.msgpack'], $append);
	}
	static function initApiCurl() {
		if (static::$apiCh !== null) return;
		static::initCurl();
		static::$apiCh = curl_copy_handle(static::$ch);
		curl_setopt_array(static::$apiCh, [
			CURLOPT_USERAGENT=>'BestHTTP/2 v2.8.3',
			CURLOPT_HTTPHEADER=>static::getApiHeaders(),
		]);
	}
	static function downloadAddressable() {
		chdir(__DIR__);
		static::initCurl();
		static::initCache();
		$manifestUpdated = false;
		$ver = static::$assetVersion;
		static::_log('check addressable');
		foreach (['2d', '3d', 'cri'] as $type) {
			$path = "$type-assets.json";
			static::_log('check '.$path, false);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl("catalog_$ver.hash", "$type-assets"));
			$hash = curl_exec(static::$ch);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl("catalog_$ver.json.br", "$type-assets"));
			$data = brotli_uncompress(curl_exec(static::$ch));
			$manifestUpdated = true;
			checkAndCreateFile("manifest/$path", $data);
			static::setCachedHash($path, $hash);
		}
		if ($manifestUpdated && file_exists('cache_addressable.dat')) {
			static::_log('found update, clearing cache');
			unlink('cache_addressable.dat');
		}
	}
	static $addressable = null;
	static function loadAddressable() {
		if (!empty(static::$addressable)) return;
		if (file_exists('cache_addressable.dat')) {
			static::$addressable = json_decode(file_get_contents('cache_addressable.dat'), true);
			return;
		}
		static::$addressable = [];
		foreach (['2d', '3d', 'cri'] as $type) {
			static::_log("load $type");
			$path = __DIR__."/manifest/$type-assets.json";
			if (!file_exists($path)) {
				throw new Exception("$path not found");
			}
			$data = json_decode(file_get_contents($path), true);
			$parsed = static::parseAddressable($data);
			foreach ($parsed[0] as $i=>$item) $parsed[0][$i] = ['primaryKey' => $item['primaryKey']];
			static::$addressable[$type] = $parsed;
			unset($data);
		}
		file_put_contents('cache_addressable.dat', json_encode(static::$addressable));
	}
	static function unloadAddressable() {
		static::$addressable = null;
	}

	static function downloadSplitlaneAssets() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$splitlaneUpdated = false;
		foreach (static::$addressable['3d'][0] as $item) {
			if (!preg_match('/(game_splitlaneelement_assets_spliteffectelements\/spliteffectelements.+|game_splitlane_assets_spliteffects\/\d+)_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, '3d-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
			static::setCachedHash($path, $hash);
			$splitlaneUpdated = true;
		}
		if ($splitlaneUpdated && file_exists('cache_spliteffectelements.dat')) {
			static::_log('found update, clearing cache');
			unlink('cache_spliteffectelements.dat');
		}
	}

	static function getResourcePathPrefix() {
		return PersistentStorage::get('resource_path', '/data/home/web/_redive/wds/');
	}
	static function getAssetUrlBase() {
		return static::$assetUrl;
	}
	static function getAssetPath($key) {
		return preg_replace('/^(.+)_[0-9a-f]+(\..+?)$/', '$1$2', $key);
	}
	static function getAssetHash($key) {
		return preg_replace('/^(.+)_([0-9a-f]+)(\..+?)$/', '$2', $key);
	}
	static function getAssetUrl($path, $type) {
		$base = static::getAssetUrlBase();
		$ver = static::$assetVersion;
		return "$base/$type/iOS/$ver/$path";
	}
	static function getNotationConfigUrl($music) {
		$base = static::getAssetUrlBase();
		$base = PersistentStorage::get('assetUrlOverride', $base);
		return "$base/Notations/$music/music_config.enc";
	}
	static function getNotationUrl($music, $level) {
		$base = static::getAssetUrlBase();
		$base = PersistentStorage::get('assetUrlOverride', $base);
		return "$base/Notations/$music/$level.enc";
	}
	static function getSceneUrl($id) {
		$base = static::$masterUrl;
		$base = PersistentStorage::get('masterUrlOverride', $base);
		return "$base/scenes/$id.bin";
	}

	static $splitEffectElements = null;
	static function loadAllSplitEffectElements() {
		if (!empty(static::$splitEffectElements)) return;
		static::downloadSplitlaneAssets();
		if (file_exists('cache_spliteffectelements.dat')){
			static::$splitEffectElements = json_decode(file_get_contents('cache_spliteffectelements.dat'), true);
			return;
		}
		foreach (glob('game_splitlaneelement_assets_spliteffectelements/*.bundle') as $elementBundle) {
			static::_log('load '.$elementBundle, false);
			$assets = extractBundle(new FileStream($elementBundle));
			$asset = new AssetFile($assets[0]);
			foreach ($asset->preloadTable as $item) {
				if ($item->typeString == 'MonoBehaviour') {
					$id = $item->m_PathID;
					$stream = $asset->stream;
					$stream->position = $item->offset;
					if (isset($asset->ClassStructures[$item->type1])) {
						$deserializedStruct = ClassStructHelper::DeserializeStruct($stream, $asset->ClassStructures[$item->type1]['members']);
						$organizedStruct = ClassStructHelper::OrganizeStruct($deserializedStruct);
						static::$splitEffectElements[$id] = $organizedStruct['_lineColor'];
					}
				}
			}
			unset($asset);
			array_map('unlink', $assets);
		}
		file_put_contents('cache_spliteffectelements.dat', json_encode(static::$splitEffectElements));
	}
	static $splitLaneColorCache = [];
	static function getSplitColors($effectId) {
		static::loadAllSplitEffectElements();
		if (isset(static::$splitLaneColorCache[$effectId])) {
			return static::$splitLaneColorCache[$effectId];
		}
		$splitLaneBundle = "game_splitlane_assets_spliteffects/$effectId.bundle";
		if (!file_exists($splitLaneBundle)) {
			throw new Exception("$splitLaneBundle not found");
		}
		$elements = null;
		$assets = extractBundle(new FileStream($splitLaneBundle));
		$asset = new AssetFile($assets[0]);
		foreach ($asset->preloadTable as $item) {
			if ($item->typeString == 'MonoBehaviour') {
				$stream = $asset->stream;
				$stream->position = $item->offset;
				if (isset($asset->ClassStructures[$item->type1])) {
					$deserializedStruct = ClassStructHelper::DeserializeStruct($stream, $asset->ClassStructures[$item->type1]['members']);
					$organizedStruct = ClassStructHelper::OrganizeStruct($deserializedStruct);
					if (isset($organizedStruct['_splitEffectElements'])) {
						$elements = $organizedStruct['_splitEffectElements'];
					}
				}
			}
		}
		unset($asset);
		array_map('unlink', $assets);

		if (empty($elements)) {
			throw new Exception("cannot find _splitEffectElements in $splitLaneBundle");
		}
		$colors = [];
		foreach ($elements as $e) {
			$path = $e['data']['m_PathID'];
			if (!isset(static::$splitEffectElements[$path])) {
				throw new Exception("PathID ".$path." from $splitLaneBundle not found");
			}
			$color = static::$splitEffectElements[$path];
			$colors[] = $color;
		}
		static::$splitLaneColorCache[$effectId] = $colors;
		return $colors;
	}
	static function getSplitBoundaries($type, $effectId) {
		$colors = static::getSplitColors($effectId);
		$boundariesCount = ($type % 10) + 1;
		$bgWidth = 120;
		$stepOffsets = [
			2 => [$bgWidth, 0],
			3 => [$bgWidth/2, $bgWidth/2, 0],
			4 => [$bgWidth/3, $bgWidth/3, $bgWidth/3, 0],
			5 => [$bgWidth/4, $bgWidth/4, $bgWidth/4, $bgWidth/4, 0],
			6 => [$bgWidth/12*3, $bgWidth/12*2, $bgWidth/12*2, $bgWidth/12*2, $bgWidth/12*3, 0],
			7 => [$bgWidth/6, $bgWidth/6, $bgWidth/6, $bgWidth/6, $bgWidth/6, $bgWidth/6, 0],
		];
		$boundaries = [];
		$x = 0;
		for ($i=0; $i<$boundariesCount; $i++) {
			$color = $colors[$i % count($colors)];
			$colorStops = [];
			if ($color['a'] == 1) {
				$color = [$color['r'], $color['g'], $color['b']];
				$colorStops[] = '<stop offset="0%" stop-color="rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).',0)" />';
				$colorStops[] = '<stop offset="30%" stop-color="rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).',0.6)" />';
				$colorStops[] = '<stop offset="100%" stop-color="rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).',0.8)" />';
				$color = 'rgb('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).')';
			} else {
				$a = $color['a'];
				$color = [$color['r'], $color['g'], $color['b'], $color['a']];
				$color[3] = $a * 0.0; $colorStops[] = '<stop offset="0%" stop-color="rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).')" />';
				$color[3] = $a * 0.6; $colorStops[] = '<stop offset="30%" stop-color="rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).')" />';
				$color[3] = $a * 0.8; $colorStops[] = '<stop offset="100%" stop-color="rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).')" />';
				$color[3] = $a; $color = 'rgba('.implode(',', array_map(function ($i) { return ($i*100).'%';}, $color)).')';
			}
			$boundary = [
				'x' => $x,
				'color' => $color,
				'colorFadeout' => implode('', $colorStops),
			];
			$boundaries[] = $boundary;
			$x += $stepOffsets[$boundariesCount][$i];
		}
		return $boundaries;
	}

	static $cacheDb;
	static function initCache() {
		if (!empty(static::$cacheDb)) return;
		static::$cacheDb = new PDO('sqlite:'.__DIR__.'/cacheHash.db');
	}
	static function getCachedHash($path) {
		static::initCache();
		$chkHashStmt = static::$cacheDb->prepare('SELECT hash FROM cacheHash WHERE res=?');
		$chkHashStmt->execute([$path]);
		$row = $chkHashStmt->fetch();
		return empty($row) ? '' : $row['hash'];
	}
	static function shouldDownload($path, $hash) {
		return static::getCachedHash($path) !== $hash;
	}
	static function setCachedHash($path, $hash) {
		static::initCache();
		$setHashStmt = static::$cacheDb->prepare('REPLACE INTO cacheHash (res,hash) VALUES (?,?)');
		$setHashStmt->execute([$path, $hash]);
	}
	static function getCachedTextureHash($key) {
		static::initCache();
		$chkHashStmt = static::$cacheDb->prepare('SELECT hash FROM textureHash WHERE res=?');
		$chkHashStmt->execute([$key]);
		$row = $chkHashStmt->fetch();
		return empty($row) ? '' : $row['hash'];
	}
	static function shouldExportTexture($key, Texture2D &$item) {
		$hash = crc32($item->imageData);
		$item->imageDataHash = $hash;
		return static::getCachedTextureHash($key) !== "$hash";
	}
	static function setCachedTextureHash($path, Texture2D &$item) {
		static::initCache();
		$setHashStmt = static::$cacheDb->prepare('REPLACE INTO textureHash (res,hash) VALUES (?,?)');
		$setHashStmt->execute([$path, $item->imageDataHash]);
	}
	static function downloadJackets() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$downloadedBundles = [];
		foreach (static::$addressable['2d'][0] as $item) {
			if (!preg_match('/jacket_assets_jacket\/.+_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, '2d-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
			static::setCachedHash($path, $hash);
			$downloadedBundles[] = $path;
		}
		return $downloadedBundles;
	}
	static function downloadCardTextures() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$downloadedBundles = [];
		foreach (static::$addressable['2d'][0] as $item) {
			if (!preg_match('/charactercardtextures_assets_charactercardtextures\/.+_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, '2d-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
			static::setCachedHash($path, $hash);
			$downloadedBundles[] = $path;
		}
		return $downloadedBundles;
	}
	static function downloadPosterTextures() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$downloadedBundles = [];
		foreach (static::$addressable['2d'][0] as $item) {
			if (!preg_match('/posters_assets_posters\/.+_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, '2d-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
			static::setCachedHash($path, $hash);
			$downloadedBundles[] = $path;
		}
		return $downloadedBundles;
	}
	static function downloadMusics() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$downloadedBundles = [];
		foreach (static::$addressable['cri'][0] as $item) {
			if (!preg_match('/cridata_remote_assets_criaddressables\/music_.+_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, 'cri-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
			static::setCachedHash($path, $hash);
			$downloadedBundles[] = $path;
		}
		return $downloadedBundles;
	}
	static function downloadNotations() {
		static::loadAddressable();
		static::initCurl();
		$curlId = 0;
		$curlInfo = [];
		$downloadQueue = [];
		$multiHelper = new CurlMultiHelper;
		$multiHelper->maxParallel = 10;
		$multiHelper->setupCb = function ($ch) use (&$downloadQueue, &$curlId, &$curlInfo) {
			if (empty($downloadQueue)) return null;
			if ($ch == null) {
				$ch = curl_copy_handle(static::$ch);
			}
			$nextItem = array_shift($downloadQueue);
			switch ($nextItem['type']) {
				case 'config': {
					curl_setopt($ch, CURLOPT_URL, static::getNotationConfigUrl($nextItem['music']));
					curl_setopt($ch, CURLOPT_PRIVATE, $curlId);
					$curlInfo[$curlId] = $nextItem;
					$curlId++;
					break;
				}
				case 'notation': {
					curl_setopt($ch, CURLOPT_URL, static::getNotationUrl($nextItem['music'], $nextItem['i']));
					curl_setopt($ch, CURLOPT_PRIVATE, $curlId);
					$curlInfo[$curlId] = $nextItem;
					$curlId++;
					break;
				}
			}
			return $ch;
		};
		$multiHelper->finishCb = function ($ch) use (&$downloadQueue, &$curlInfo) {
			$curlId = curl_getinfo($ch, CURLINFO_PRIVATE);
			$item = $curlInfo[$curlId];
			$data = curl_multi_getcontent($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			switch ($item['type']) {
				case 'config': {
					if ($httpCode != 200) return;
					$path = $item['path'];
					$music = $item['music'];
					@mkdir(dirname($path), 0777, true);
					$hasNewMusicConfig = checkAndCreateFile($path, static::decryptMusicConfig($data));
					if ($hasNewMusicConfig) static::_log('save '.$path);
					for ($i=1;$i<6;$i++) {
						$path = "Notations/$music/$i.txt";
						static::_log('check '.$path, false);
						$downloadQueue[] = [
							'type' => 'notation',
							'path' => $path,
							'music' => $music,
							'i' => $i,
						];
					}
					break;
				}
				case 'notation': {
					if ($httpCode != 200) return;
					$path = $item['path'];
					$hasNewNotation = checkAndCreateFile($path, static::decryptNotation($data));
					if ($hasNewNotation) static::_log('save '.$path);
					break;
				}
			}
		};
		$musics = [];
		foreach (static::$addressable['2d'][0] as $item) {
			if (!preg_match('/jacket_assets_jacket\/.+_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$musics[pathinfo($path, PATHINFO_FILENAME)] = 1;
		}
		foreach (static::$addressable['cri'][0] as $item) {
			if (!preg_match('/cridata_remote_assets_criaddressables\/music_.+_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$musics[substr(pathinfo(pathinfo($path, PATHINFO_FILENAME), PATHINFO_FILENAME), 6)] = 1;
		}
		foreach ($musics as $music => $_) {
			$path = "Notations/$music/music_config.txt";
			static::_log('check '.$path, false);
			$downloadQueue[] = [
				'type' => 'config',
				'path' => $path,
				'music' => $music,
			];
		}
		$multiHelper->run();
	}
	static function decryptMusicConfig($data) {
		return static::decrypt($data, 'X)|9Vs+&AB5qKBrzqWq)quqEjFug8LaK');
	}
	static function decryptNotation($data) {
		return brotli_uncompress(static::decrypt($data, 'k8teTB%QH.v-hY+e)7wees8bxYSLQdAg'));
	}
	static function decrypt($data, $pwd) {
		$iv = substr($data, 0, 16);
		$enc = substr($data, 16);
		return  openssl_decrypt($enc, 'AES-256-CBC', $pwd, OPENSSL_RAW_DATA, $iv);
	}

	static function exportJackets() {
		$downloadedJackets = static::downloadJackets();
		$currenttime = time();
		foreach ($downloadedJackets as $bundle) {
			static::_log('export '.$bundle);
			$assets = extractBundle(new FileStream($bundle));

			try{
			
			foreach ($assets as $asset) {
				if (substr($asset, -5,5) == '.resS') continue;
				$asset = new AssetFile($asset);
		
				foreach ($asset->preloadTable as &$item) {
					if ($item->typeString == 'Texture2D') {
						try {
							$item = new Texture2D($item, true);
						} catch (Exception $e) { continue; }
						$itemname = $item->name;
						if (!static::shouldExportTexture("$bundle:$itemname", $item)) {
							continue;
						}
						$saveTo = static::getResourcePathPrefix(). 'jacket/'. $itemname;
						$param = '-lossless 1';
						$item->exportTo($saveTo, 'webp', $param);
						if (filemtime($saveTo. '.webp') > $currenttime)
						touch($saveTo. '.webp', $currenttime);
						static::setCachedTextureHash("$bundle:$itemname", $item);
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
		}
	}
	static function exportCardTextures() {
		$downloadedTextures = static::downloadCardTextures();
		//$downloadedTextures = glob('charactercardtextures_assets_charactercardtextures/*.bundle');
		$currenttime = time();
		static::unloadAddressable();
		foreach ($downloadedTextures as $bundle) {
			static::_log('export '.$bundle);
			$assets = extractBundle(new FileStream($bundle));

			try{
			
			foreach ($assets as $asset) {
				if (substr($asset, -5,5) == '.resS') continue;
				$asset = new AssetFile($asset);
		
				foreach ($asset->preloadTable as &$item) {
					// find card sprite
					if ($item->typeString == 'Sprite') {
						if (!isset($asset->ClassStructures[$item->type1]) || $item->fullSize > 1000) {
							continue;
						}
						$stream = $asset->stream;
						$stream->position = $item->offset;
						$deserializedStruct = ClassStructHelper::DeserializeStruct($stream, $asset->ClassStructures[$item->type1]['members']);
						$organizedStruct = ClassStructHelper::OrganizeStruct($deserializedStruct);
						if (!preg_match('/^\d{6}_\d$/', $organizedStruct['m_Name'])) continue;
						$texturePath = $organizedStruct['m_RD']['texture']['m_PathID'];
						unset($deserializedStruct, $organizedStruct);
						if (!isset($asset->preloadTable[$texturePath])) {
							static::_log("texture path $texturePath not found in $bundle");
						}
						$item = $asset->preloadTable[$texturePath];
						try {
							$item = new Texture2D($item, true);
						} catch (Exception $e) { continue; }
						$itemname = $item->name;
						if (!static::shouldExportTexture("$bundle:$itemname", $item)) {
							continue;
						}
						$saveTo = static::getResourcePathPrefix(). 'card/'. $itemname;
						$param = '-lossless 1';
						$item->exportTo($saveTo, 'webp', $param);
						if (filemtime($saveTo. '.webp') > $currenttime)
						touch($saveTo. '.webp', $currenttime);
						static::setCachedTextureHash("$bundle:$itemname", $item);
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
				static::_log('Not supported: '. $e->getMessage());
			}

			foreach ($assets as $asset) {
				unlink($asset);
			}
		}
	}
	static function exportPosterTextures() {
		$downloadedTextures = static::downloadPosterTextures();
		//$downloadedTextures = glob('posters_assets_posters/210030.bundle');
		$currenttime = time();
		static::unloadAddressable();
		foreach ($downloadedTextures as $bundle) {
			static::_log('export '.$bundle);
			$assets = extractBundle(new FileStream($bundle));

			try{
			
			foreach ($assets as $asset) {
				if (substr($asset, -5,5) == '.resS') continue;
				$asset = new AssetFile($asset);
		
				foreach ($asset->preloadTable as &$item) {
					if ($item->typeString == 'Texture2D') {
						try {
							$item = new Texture2D($item, true);
						} catch (Exception $e) { continue; }
						$itemname = $item->name;
						$saveDir = preg_match('/_0$/', $itemname) ? 'poster/' : 'poster_parts/';
						if (!static::shouldExportTexture("$bundle:$itemname", $item)) {
							continue;
						}
						$saveTo = static::getResourcePathPrefix(). $saveDir. $itemname;
						$param = '-lossless 1';
						$item->exportTo($saveTo, 'webp', $param);
						if (filemtime($saveTo. '.webp') > $currenttime)
						touch($saveTo. '.webp', $currenttime);
						static::setCachedTextureHash("$bundle:$itemname", $item);
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
				static::_log('Not supported: '. $e->getMessage());
			}

			foreach ($assets as $asset) {
				unlink($asset);
			}
		}
	}
	static function exportMusics() {
		$downloadedBundles = static::downloadMusics();
		//$downloadedBundles = glob('cridata_remote_assets_criaddressables/music_*.acb.bundle');
		$saveTo = static::getResourcePathPrefix() . 'sound/music';
		foreach ($downloadedBundles as $acbName) {
			static::_log('exporting '. $acbName);
			$nullptr = NULL;
			exec('acb2wavs '.$acbName.' -b 0 -a 0 -n', $nullptr);
			$acbUnpackDir = dirname($acbName).'/_acb_'.pathinfo($acbName, PATHINFO_BASENAME);
			foreach (['internal', 'external'] as $awbFolder) {
				if (file_exists($acbUnpackDir .'/'. $awbFolder)) {
					foreach (glob($acbUnpackDir .'/'. $awbFolder.'/*.wav') as $waveFile) {
						$m4aFile = substr($waveFile, 0, -3).'m4a';
						$finalPath = $saveTo.'/'.pathinfo($m4aFile, PATHINFO_BASENAME);
						exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$waveFile.' -vbr 5 -movflags faststart '.$m4aFile, $nullptr);
						checkAndMoveFile($m4aFile, $finalPath);
					}
				}
			}
			static::delTree($acbUnpackDir);
		}
	}

	static function parseNotation($text) {
		if (empty(trim($text))) return [];
		return array_map(function ($line) {
			$parts = str_getcsv(trim($line));
			return [
				'start'        => floatval($parts[0]),
				'end'          => floatval($parts[1]),
				'type'         => intval($parts[2]),
				'lane'         => intval($parts[3]),
				'width'        => intval($parts[4]),
				'gimmickType'  => isset($parts[5]) ? $parts[5] : '0',
				'gimmickValue' => isset($parts[6]) ? $parts[6] : '0',
			];
		}, explode("\n", $text));
	}
	static function roundReal($n) {
		return round($n * 100000) / 100000;
	}
	static function roundBeat($n) {
		return round($n * 4) / 4;
	}

	static function notationToSvg($notation) {
		$DurationPerColumn = 5;
		$HeightPerSecond = 800 / $DurationPerColumn;
		$endOfChartTs = 0;
		$normalNotes = [];
		$scratchNotes = [];
		$holdNotes = [];
		$soundNotes = [];
		$holdJudgeNotes = [];
		$splitSections = [];
		usort($notation, function ($a, $b) { return $a['start'] >= $b['start'] ? $a['start'] > $b['start'] ? 1 : 0 : -1;});
		foreach ($notation as $note) {
			$noteEnd = $note['end'] > 0 ? $note['end'] : $note['start'];
			$endOfChartTs = max($noteEnd, $endOfChartTs);
			switch ($note['type']) {
				case WdsNoteType::Normal:
				case WdsNoteType::Critical:
				case WdsNoteType::HoldStart:
				case WdsNoteType::CriticalHoldStart:
				case WdsNoteType::ScratchHoldStart:
				case WdsNoteType::ScratchCriticalHoldStart: { $normalNotes[] = $note; break; }
				case WdsNoteType::Scratch:
				case WdsNoteType::Flick: { $scratchNotes[] = $note; break; }
				case WdsNoteType::Sound:
				case WdsNoteType::SoundPurple: { $soundNotes[] = $note; break; }
				case WdsNoteType::Hold:
				case WdsNoteType::CriticalHold:
				case WdsNoteType::ScratchHold:
				case WdsNoteType::ScratchCriticalHold: { $holdNotes[] = $note; break; }
				case WdsNoteType::HoldEighth: { $holdJudgeNotes[] = $note; break; }
			}
			switch ($note['gimmickType']) {
				case '0':
				case '1':
				case 'JumpScratch':
				case '2':
				case 'OneDirection': {break;}
				case '11': case '31': case '51': case '71':
				case '12': case '32': case '52': case '72':
				case '13': case '33': case '53': case '73':
				case '14': case '34': case '54': case '74':
				case '15': case '35': case '55': case '75':
				case '16': case '36': case '56': case '76': { $splitSections[] = $note; $endOfChartTs = max($noteEnd + 1, $endOfChartTs); break;}
				default: {
					print_r($note);//break;
				}
			}
		}
		if (empty($notation) || empty($endOfChartTs)) {
			return '';
		}
		$svgWidth = ceil($endOfChartTs / $DurationPerColumn) * 200;

		$svgParts = ['<?xml version="1.0" standalone="no"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" ><svg xmlns="http://www.w3.org/2000/svg" width="'.$svgWidth.'" height="900" style="background:black">'];
		$svgParts[] = '<style>text{font:12px Arial;fill:#CDCDCD;-webkit-user-select:none;user-select:none}.bpm_mark{fill:#15CD15}</style>';
		$svgParts[] = '<defs>';
		$svgParts[] = '  <linearGradient id="line_hold_gradient"><stop offset="0%" stop-color="rgba(155,255,255,0.9)" /><stop offset="30%" stop-color="rgba(119,200,200,0.6)" /><stop offset="70%" stop-color="rgba(119,200,200,0.6)" /><stop offset="100%" stop-color="rgba(155,255,255,0.9)" /></linearGradient>';
		$svgParts[] = '  <linearGradient id="line_scratch_gradient"><stop offset="0%" stop-color="rgba(228,128,255,0.9)" /><stop offset="30%" stop-color="rgba(151,83,215,0.6)" /><stop offset="70%" stop-color="rgba(151,83,215,0.6)" /><stop offset="100%" stop-color="rgba(228,128,255,0.9)" /></linearGradient>';
		$svgParts[] = '  <g width="20" height="8" id="normal"  ><path d="M0,2v4a2,2,90,0,0,2,2h16a2,-2,90,0,0,2,-2v-4a-2,-2,90,0,0,-2,-2h-16a-2,2,90,0,0,-2,2" fill="rgb(253,98,163)" /><path d="M1,3v2a2,2,90,0,0,2,2h14a2,-2,90,0,0,2,-2v-2a-2,-2,90,0,0,-2,-2h-14a-2,2,90,0,0,-2,2" stroke="rgb(255,225,104)" fill="transparent" /></g>';
		$svgParts[] = '  <g width="20" height="8" id="critical"><path d="M0,2v4a2,2,90,0,0,2,2h16a2,-2,90,0,0,2,-2v-4a-2,-2,90,0,0,-2,-2h-16a-2,2,90,0,0,-2,2" fill="rgb(254,166,43)" /><path d="M1,3v2a2,2,90,0,0,2,2h14a2,-2,90,0,0,2,-2v-2a-2,-2,90,0,0,-2,-2h-14a-2,2,90,0,0,-2,2" stroke="rgb(255,225,104)" fill="transparent" /></g>';
		$svgParts[] = '  <g width="20" height="8" id="hold"    ><path d="M0,2v4a2,2,90,0,0,2,2h16a2,-2,90,0,0,2,-2v-4a-2,-2,90,0,0,-2,-2h-16a-2,2,90,0,0,-2,2" fill="rgb(18,155,250)" /><path d="M1,3v2a2,2,90,0,0,2,2h14a2,-2,90,0,0,2,-2v-2a-2,-2,90,0,0,-2,-2h-14a-2,2,90,0,0,-2,2" stroke="rgb(255,225,104)" fill="transparent" /></g>';
		$svgParts[] = '  <g width="20" height="8" id="scratch" ><path d="M0,2v4a2,2,90,0,0,2,2h16a2,-2,90,0,0,2,-2v-4a-2,-2,90,0,0,-2,-2h-16a-2,2,90,0,0,-2,2" fill="rgb(129,59,236)" /><path d="M1,3v2a2,2,90,0,0,2,2h14a2,-2,90,0,0,2,-2v-2a-2,-2,90,0,0,-2,-2h-14a-2,2,90,0,0,-2,2" stroke="rgb(255,225,104)" fill="transparent" /></g>';
		$svgParts[] = '  <g width="20" height="12" id="scratch_arrow_left"><path d="M5,1l-4,6l4,6h3l-4,-6l4,-6zM11,1l-4,6l4,6h3l-4,-6l4,-6z" stroke="rgb(123,81,251)" fill="rgb(244,237,255)" /></g>';
		$svgParts[] = '  <g width="20" height="12" id="scratch_arrow_right"><path d="M9,1l4,6l-4,6h-3l4,-6l-4,-6zM15,1l4,6l-4,6h-3l4,-6l-4,-6z" stroke="rgb(123,81,251)" fill="rgb(244,237,255)" /></g>';
		$svgParts[] = '  <g width="20" height="12" id="scratch_arrow_both"><path d="M6,1l-4,6l4,6h3l-4,-6l4,-6zM14,1l4,6l-4,6h-3l4,-6l-4,-6z" stroke="rgb(123,81,251)" fill="rgb(244,237,255)" /></g>';
		$svgParts[] = '  <g width="20" height="20" id="sound"><path d="M10,1l3,7l7,3l-7,3l-3,7l-3,-7l-7,-3l7,-3l3,-7" stroke="rgb(56,173,198)" fill="rgb(147,243,252)" /></g>';
		$svgParts[] = '  <g width="20" height="20" id="sound_purple"><path d="M10,1l3,7l7,3l-7,3l-3,7l-3,-7l-7,-3l7,-3l3,-7" stroke="rgb(121,77,211)" fill="rgb(197,146,253)" /></g>';
		$svgParts[] = '  <g width="20" height="20" id="line_hold"><path d="M0,0h20v20h-20z" fill="url(\'#line_hold_gradient\')" /></g>';
		$svgParts[] = '  <g width="20" height="20" id="line_scratch"><path d="M0,0h20v20h-20z" fill="url(\'#line_scratch_gradient\')" /></g>';
		$svgParts[] = '  <g id="beat_section" width="160" height="160"><path d="M20,0v160M140,0v160M0,0" stroke="#CCCCCC" /><path d="M40,0v160M60,0v160M80,0v160M100,0v160M120,0v160M0,0" stroke="#444444" /></g>';
		$svgParts[] = '  <g id="beat_section_top" width="160" height="160"><path d="M20,0v160M140,0v160M0,0" stroke="#CCCCCC" /><path d="M20,0h120M40,0v160M60,0v160M80,0v160M100,0v160M120,0v160M0,0" stroke="#444444" /></g>';
		$svgParts[] = '  <g id="beat_section_bottom" width="160" height="160"><path d="M20,0v160M140,0v160M0,0" stroke="#CCCCCC" /><path d="M20,160h120M40,0v160M60,0v160M80,0v160M100,0v160M120,0v160M0,0" stroke="#444444" /></g>';
		$svgParts[] = '</defs>';

		$svgParts[] = '<g class="bg_layer">';
		for ($i=0; $i<$endOfChartTs+1; $i++) {
			$isBottom = ($i%$DurationPerColumn) == 0;
			$isTop = $i >= $endOfChartTs || ($i%$DurationPerColumn) == $DurationPerColumn - 1;
			$x = 200 * floor($i / $DurationPerColumn) + 30;
			$y = 850 - $HeightPerSecond - $HeightPerSecond * ($i % $DurationPerColumn);
			$svgParts[] = '<use href="#beat_section'.($isTop?'_top':($isBottom?'_bottom':'')).'" x="'.$x.'" y="'.$y.'" />';
		}
		$svgParts[] = '</g>';

		$syncLayerIdx = count($svgParts);

		$lines = [];
		foreach ($holdNotes as $note) {
			if ($note['start'] > $note['end']) {
				print_r($note);continue;
			}
			$startPos = static::getNotePos($note['start'], $note['lane']);
			$endPos = static::getNotePos($note['end'], $note['lane']);
			$line = [
				'start' => [
					't' => $note['start'],
					'l' => $note['lane'],
					'w' => $note['width'],
					'x' => $startPos[0],
					'y' => $startPos[1],
				],
				'end' => [
					't' => $note['end'],
					'l' => $note['lane'],
					'w' => $note['width'],
					'x' => $endPos[0],
					'y' => $endPos[1],
				],
				'type' => in_array($note['type'], [WdsNoteType::ScratchHold, WdsNoteType::ScratchCriticalHold]) ? 'line_scratch' : 'line_hold'
			];
			// 结尾追加
			$addEnd = [
				'start'       => $note['end'],
				'end'         => -1.0,
				'type'        => $line['type'] == 'line_hold' ? WdsNoteType::HoldStart : WdsNoteType::Scratch,
				'lane'        => $note['lane'],
				'width'       => $note['width'],
				'gimmickType' => $note['gimmickType'],
				'gimmickValue'=> $note['gimmickValue'],
			];
			$notation[] = $addEnd;
			if ($line['type'] == 'line_hold') {
				$normalNotes[] = $addEnd;
			} else {
				$scratchNotes[] = $addEnd;
			}
			while (floor($line['start']['t'] / $DurationPerColumn) != floor($line['end']['t'] / $DurationPerColumn)) {
				$insertEndTime = floor($line['start']['t'] / $DurationPerColumn) * $DurationPerColumn + $DurationPerColumn;
				$insertEnd = [
					't' => $insertEndTime,
					'l' => $line['end']['l'],
					'w' => $line['end']['w'],
					'x' => $line['start']['x'] + 200,
					'y' => 850,
				];
				$lines[] = [
					'start' => $line['start'],
					'end' => [
						't' => $insertEnd['t'],
						'l' => $insertEnd['l'],
						'w' => $insertEnd['w'],
						'x' => $insertEnd['x'] - 200,
						'y' => $insertEnd['y'] - 800,
					],
					'type' => $line['type']
				];
				$line['start'] = $insertEnd;
			}
			$lines[] = $line;
		}
		$svgParts[] = '<g class="line_layer">';
		foreach ($lines as $line) {
			$x = $line['end']['x'];
			$y = $line['end']['y'];
			$width = $line['end']['w'] * 20 / 2;
			$height = $line['start']['y'] - $line['end']['y'];
			if ($height == 0) continue;
			$transformScaleX = static::roundReal($width / 20);
			$transformScaleY = static::roundReal($height / 20);
			$transform = [];
			if ($transformScaleX != 1) $transform[] = "scaleX(${transformScaleX})";
			if ($transformScaleY != 1) $transform[] = "scaleY(${transformScaleY})";
			$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.static::roundReal($x).'px '.static::roundReal($y).'px"' : '';
			if ($height == 0) print_r($line);
			$noteType = $line['type'];
			$svgParts[] = '<use href="#'.$noteType.'" x="'.static::roundReal($x).'" y="'.static::roundReal($y).'" b1="'.$line['start']['t'].'" b2="'.$line['end']['t'].'" '.$style.' />';
		}
		usort($soundNotes, function ($a, $b) { return $a['start'] >= $b['start'] ? $a['start'] > $b['start'] ? 1 : 0 : -1;});
		foreach ($soundNotes as $note) {
			$noteTime = $note['start'];
			list($x, $y) = static::getNotePos($noteTime, $note['lane']);
			$x = static::roundReal($x);
			$width = $note['width'] * 20 / 2;
			$x += $width / 2 - 10;
			$y = static::roundReal($y) - 10;
			switch ($note['type']) {
				case WdsNoteType::Sound: { $noteType = 'sound'; break; }
				case WdsNoteType::SoundPurple: { $noteType = 'sound_purple'; break; }
			}
			$svgParts[] = '<use href="#'.$noteType.'" x="'.$x.'" y="'.$y.'" />';
		}
		$svgParts[] = '</g>';

		$syncPoints = [];
		$svgParts[] = '<g class="note_layer">';
		usort($normalNotes, function ($a, $b) { return $a['start'] >= $b['start'] ? $a['start'] > $b['start'] ? 1 : 0 : -1;});
		foreach ($normalNotes as $note) {
			$noteTime = $note['start'];
			$noteTimeS = "$noteTime";
			list($x, $y) = static::getNotePos($noteTime, $note['lane']);
			$x = static::roundReal($x);
			$width = $note['width'] * 20 / 2;
			$y = static::roundReal($y) - 4;
			if (!isset($syncPoints[$noteTimeS])) {
				$syncPoints[$noteTimeS] = [$x + $width, $x, $y + 4];
			}
			$syncPoints[$noteTimeS][0] = min($syncPoints[$noteTimeS][0], $x + $width);
			$syncPoints[$noteTimeS][1] = max($syncPoints[$noteTimeS][1], $x);
			switch ($note['type']) {
				case WdsNoteType::Normal:
				case WdsNoteType::ScratchHoldStart: { $noteType = 'normal'; break; }
				case WdsNoteType::Critical:
				case WdsNoteType::CriticalHoldStart:
				case WdsNoteType::ScratchCriticalHoldStart: { $noteType = 'critical'; break; }
				case WdsNoteType::Sound: { $noteType = 'sound'; break; }
				case WdsNoteType::SoundPurple: { $noteType = 'sound_purple'; break; }
				case WdsNoteType::HoldStart: { $noteType = 'hold'; break; }
			}
			$transformScaleX = static::roundReal($width / 20);
			$transform = [];
			if ($transformScaleX != 1) $transform[] = "scaleX(${transformScaleX})";
			$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.static::roundReal($x).'px '.static::roundReal($y).'px"' : '';
			
			$svgParts[] = '<use href="#'.$noteType.'" x="'.$x.'" y="'.$y.'" '.$style.' />';
			if ($noteTime - floor($noteTime / $DurationPerColumn) * $DurationPerColumn > $DurationPerColumn * 0.99) {
				$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.static::roundReal($x + 200).'px '.static::roundReal($y + 800).'px"' : '';
				$svgParts[] = '<use href="#'.$noteType.'" x="'.($x + 200).'" y="'.($y + 800).'" '.$style.' />';
			} else if ($noteTime > 1 && $noteTime - floor($noteTime / $DurationPerColumn) * $DurationPerColumn < $DurationPerColumn * 0.01) {
				$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.static::roundReal($x - 200).'px '.static::roundReal($y - 800).'px"' : '';
				$svgParts[] = '<use href="#'.$noteType.'" x="'.($x - 200).'" y="'.($y - 800).'" '.$style.' />';
			}
		}
		$arrowParts = [];
		usort($scratchNotes, function ($a, $b) { return $a['start'] >= $b['start'] ? $a['start'] > $b['start'] ? 1 : 0 : -1;});
		foreach ($scratchNotes as $note) {
			$noteTime = $note['start'];
			$noteTimeS = "$noteTime";
			$scracthDirection = 'both';
			switch ($note['gimmickType']) {
				case '0': {break;}
				case '1':
				case 'JumpScratch': {
					$val = $note['gimmickValue'];
					if ($val < 0) {
						$scracthDirection = 'left';
						$note['lane'] = $note['lane'] + $note['width'] + $val;
						$note['width'] = -$val;
					} else {
						$scracthDirection = 'right';
						$note['width'] = $val;
					}
					break;
				}
				case '2':
				case 'OneDirection': {
					$val = $note['gimmickValue'];
					if ($val == 0) {
						$scracthDirection = 'left';
					} else {
						$scracthDirection = 'right';
					}
					break;
				}
				default: {
					print_r($note);break;
				}
			}
			list($x, $y) = static::getNotePos($noteTime, $note['lane']);
			$x = static::roundReal($x);
			$width = $note['width'] * 20 / 2;
			$y = static::roundReal($y) - 4;
			if (!isset($syncPoints[$noteTimeS])) {
				$syncPoints[$noteTimeS] = [$x + $width, $x, $y + 4];
			}
			$syncPoints[$noteTimeS][0] = min($syncPoints[$noteTimeS][0], $x + $width);
			$syncPoints[$noteTimeS][1] = max($syncPoints[$noteTimeS][1], $x);
			$noteType = 'scratch';
			$transformScaleX = static::roundReal($width / 20);
			$transform = [];
			if ($transformScaleX != 1) $transform[] = "scaleX(${transformScaleX})";
			$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.$x.'px '.$y.'px"' : '';
			
			$svgParts[] = '<use href="#'.$noteType.'" x="'.$x.'" y="'.$y.'" '.$style.' />';
			$arrowParts[] = '<use href="#scratch_arrow_'.$scracthDirection.'" x="'.($x + $width / 2 - 10).'" y="'.($y - 12).'" />';
			if ($noteTime - floor($noteTime / $DurationPerColumn) * $DurationPerColumn > $DurationPerColumn * 0.99) {
				$x += 200;
				$y += 800;
				$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.$x.'px '.$y.'px"' : '';
				$svgParts[] = '<use href="#'.$noteType.'" x="'.$x.'" y="'.$y.'" '.$style.' />';
			} else if ($noteTime > 1 && $noteTime - floor($noteTime / $DurationPerColumn) * $DurationPerColumn < $DurationPerColumn * 0.01) {
				$x -= 200;
				$y -= 800;
				$style = count($transform) ? 'style="transform:'.implode(' ', $transform).';transform-origin:'.$x.'px '.$y.'px"' : '';
				$svgParts[] = '<use href="#'.$noteType.'" x="'.$x.'" y="'.$y.'" '.$style.' />';
			}
		}
		$svgParts[] = '</g>';
		$svgParts[] = '<g class="arrow_layer">';
		$svgParts = array_merge($svgParts, $arrowParts);
		$svgParts[] = '</g>';

		$lines = [];
		foreach ($splitSections as $note) {
			$startPos = static::getNotePos($note['start'], 1);
			$endFadeTime = max($note['start'], $note['end'] - 1);
			$fadeStartPos = static::getNotePos($note['end'] - 1, 1);
			$fadeInStartPos = static::getNotePos($note['start'] - 1, 1);
			$endPos = static::getNotePos($endFadeTime, 1);
			$line = [
				'type' => $note['gimmickType'],
				'effect' => $note['gimmickValue'],
				'fadein' => [
					't' => $note['start'] - 1,
					'x' => $fadeInStartPos[0],
					'y' => $fadeInStartPos[1],
				],
				'start' => [
					't' => $note['start'],
					'x' => $startPos[0],
					'y' => $startPos[1],
				],
				'fade' => [
					't' => $endFadeTime,
					'x' => $fadeStartPos[0],
					'y' => $fadeStartPos[1],
				],
				'end' => [
					't' => $note['end'],
					'x' => $endPos[0],
					'y' => $endPos[1],
				],
				'isFirst' => true,
				'isFinal' => false,
			];
			if ($note['start'] >= $note['end']) $line['isFirst'] = false;
			while (floor($line['start']['t'] / $DurationPerColumn) != floor($line['fade']['t'] / $DurationPerColumn)) {
				$insertEndTime = floor($line['start']['t'] / $DurationPerColumn) * $DurationPerColumn + $DurationPerColumn;
				$insertEnd = [
					't' => $insertEndTime,
					'x' => $line['start']['x'] + 200,
					'y' => 850,
				];
				$lines[] = [
					'type' => $line['type'],
					'effect' => $line['effect'],
					'start' => $line['start'],
					'fade' => $line['fade'],
					'end' => [
						't' => $insertEnd['t'],
						'x' => $insertEnd['x'] - 200,
						'y' => $insertEnd['y'] - 800,
					],
					'isFirst' => false,
					'isFinal' => false,
				];
				$line['start'] = $insertEnd;
			}
			$line['isFinal'] = true;
			$lines[] = $line;
		}
		$svgParts[] = '<g class="split_layer">';
		$fadeInGradients = [];
		$fadeOutGradients = [];
		$lines = array_reverse($lines);
		foreach ($lines as $line) {
			$x = $line['end']['x'];
			$y1 = $line['start']['y'];
			$y2 = $line['end']['y'];
			$height = $line['start']['y'] - $line['end']['y'];
			$splitBoundaries = static::getSplitBoundaries($line['type'], $line['effect']);
			if ($height > 0) {
				foreach ($splitBoundaries as $boundary) {
					$x1 = $x + $boundary['x'];
					$color = $boundary['color'];
					$svgParts[] = "<path d=\"M$x1,$y1 V$y2\" stroke=\"$color\" stroke-width=\"3\" />";
				}
			}

			if ($line['isFirst']) {
				$x = $line['fadein']['x'];
				$y1 = $line['fadein']['y'] - $HeightPerSecond;
				foreach ($splitBoundaries as $boundary) {
					$x1 = $x + $boundary['x'] - 1.5;
					$color = $boundary['colorFadeout'];
					if (isset($fadeInGradients[$color])) {
						$fadeInId = $fadeInGradients[$color];
					}
					$fadeInId = isset($fadeInGradients[$color]) ? $fadeInGradients[$color] : ($fadeInGradients[$color] = count($fadeInGradients));
					if ($y1 < 50) {
						$h1 = 50 - $y1;
						$h2 = $HeightPerSecond - $h1;
						$svgParts[] = "<path d=\"M${x1},${y1}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadein_$fadeInId)\" clip-path=\"url(#split_fadeout_clip)\" />";
						$x2 = $x1 + 200;
						$y2 = $y1 + 800;
						$svgParts[] = "<path d=\"M${x2},${y2}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadein_$fadeInId)\" clip-path=\"url(#split_fadeout_clip)\" />";
					} else {
						$svgParts[] = "<path d=\"M${x1},${y1}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadein_$fadeInId)\" />";
					}
				}
			}

			if ($line['isFinal']) {
				$x = $line['fade']['x'];
				$y1 = $line['fade']['y'] - $HeightPerSecond;
				foreach ($splitBoundaries as $boundary) {
					$x1 = $x + $boundary['x'] - 1.5;
					$color = $boundary['colorFadeout'];
					if (isset($fadeOutGradients[$color])) {
						$fadeOutId = $fadeOutGradients[$color];
					}
					$fadeOutId = isset($fadeOutGradients[$color]) ? $fadeOutGradients[$color] : ($fadeOutGradients[$color] = count($fadeOutGradients));
					if ($y1 < 50) {
						$h1 = 50 - $y1;
						$h2 = $HeightPerSecond - $h1;
						$svgParts[] = "<path d=\"M${x1},${y1}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadeout_$fadeOutId)\" clip-path=\"url(#split_fadeout_clip)\" />";
						$x2 = $x1 + 200;
						$y2 = $y1 + 800;
						$svgParts[] = "<path d=\"M${x2},${y2}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadeout_$fadeOutId)\" clip-path=\"url(#split_fadeout_clip)\" />";
					} else {
						$svgParts[] = "<path d=\"M${x1},${y1}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadeout_$fadeOutId)\" />";
					}
				}
			}
		}
		$svgParts[] = '<defs>';
		foreach ($fadeInGradients as $color=>$id) {
			$svgParts[] = "<linearGradient id=\"split_fadein_$id\" x2=\"0\" y1=\"100%\" y2=\"0\">$color</linearGradient>";
		}
		foreach ($fadeOutGradients as $color=>$id) {
			$svgParts[] = "<linearGradient id=\"split_fadeout_$id\" x2=\"0\" y2=\"100%\">$color</linearGradient>";
		}
		$svgParts[] = '<clipPath id="split_fadeout_clip"><rect x="50" y="50" width="'.($svgWidth - 100).'" height="800" /></clipPath>';
		$svgParts[] = '</defs>';
		$svgParts[] = '</g>';

		$syncLayerParts = [];
		$syncLayerParts[] = '<g class="sync_line_layer">';
		foreach ($syncPoints as $t=>$sync) {
			list($x1, $x2, $y) = $sync;
			if ($x1 >= $x2) continue;
			$syncLayerParts[] = "<path d=\"M$x1,$y H$x2\" stroke=\"#B4B4B4\" />";
			
			if ($t - floor($t / $DurationPerColumn) * $DurationPerColumn > $DurationPerColumn * 0.99) {
				$x1 += 200;
				$x2 += 200;
				$y += 800;
				$syncLayerParts[] = "<path d=\"M$x1,$y H$x2\" stroke=\"#B4B4B4\" />";
			} else if ($t > 1 && $t - floor($t / $DurationPerColumn) * $DurationPerColumn < $DurationPerColumn * 0.01) {
				$x1 -= 200;
				$x2 -= 200;
				$y -= 800;
				$syncLayerParts[] = "<path d=\"M$x1,$y H$x2\" stroke=\"#B4B4B4\" />";
			}
		}
		foreach ($splitSections as $note) {
			list($x1, $y1) = static::getNotePos($note['start'], 1);
			list($x2, $y2) = static::getNotePos($note['end'], 1);
			if ($note['start'] >= $note['end']) {
				$syncLayerParts[] = "<path d=\"M$x1,${y1}h120\" stroke=\"#606060\" />";
			} else {
				$syncLayerParts[] = "<path d=\"M$x1,${y1}h120\" stroke=\"#606060\" />";
				$syncLayerParts[] = "<path d=\"M$x2,${y2}h120\" stroke=\"#606060\" />";
			}
		}
		$syncLayerParts[] = '</g>';
		array_splice($svgParts, $syncLayerIdx, 0, $syncLayerParts);

		$svgParts[] = '<g class="text_mark_layer">';
		for ($i=0; $i<=$endOfChartTs; $i+=1) {
			$column = floor($i / $DurationPerColumn);
			$x = 200 * $column + 175;
			$y = 850 - $HeightPerSecond * ($i - $column * $DurationPerColumn);
			$svgParts[] = '<text class="second_mark" x="'.$x.'" y="'.$y.'">'.($i ?: 'seconds').'</text>';
		}
		foreach ($splitSections as $note) {
			$startPos = static::getNotePos($note['start'], 1);
			$endPos = static::getNotePos($note['end'], 1);
			if ($note['start'] >= $note['end']) {
				$svgParts[] = '<text class="split_mark" text-anchor="end" x="'.($startPos[0] - 2).'" y="'.($startPos[1] - 2).'">split '.$note['gimmickValue'].'</text>';
				$svgParts[] = '<text class="split_mark" text-anchor="end" x="'.($startPos[0] - 2).'" y="'.($startPos[1] + 10).'">no length</text>';
			} else {
				$svgParts[] = '<text class="split_mark" text-anchor="end" x="'.($startPos[0] - 2).'" y="'.($startPos[1] + 4).'">split '.$note['gimmickValue'].'</text>';
				$svgParts[] = '<text class="split_mark" text-anchor="end" x="'.($endPos[0] - 2).'" y="'.($endPos[1] + 4).'">split end</text>';
			}
		}
		$svgParts[] = '</g>';

		$svgParts[] = '</svg>';
		return implode("\n",$svgParts);
	}
	static function getNotePos($time, $track) {
		$DurationPerColumn = 5;
		$HeightPerSecond = 800 / $DurationPerColumn;
		$timeInt = floor($time);
		$x = 200 * floor($timeInt / $DurationPerColumn) + 40 + 10 * $track;
		$y = 850 - $HeightPerSecond * (($timeInt % $DurationPerColumn) + ($time - $timeInt));
		return [$x, $y];
	}

	static function convertNotations() {
		foreach (glob('Notations/*/?.txt') as $f) {
			static::_log("export $f", false);
			$notation = static::parseNotation(file_get_contents($f));
			if (empty($notation)) continue;
			$svg = static::notationToSvg($notation);
			if (empty($svg)) continue;
			$saveTo = dirname($f).'/'.pathinfo($f, PATHINFO_FILENAME).'.svg';
			checkAndCreateFile($saveTo, $svg);
		}
	}

	static function parseAddressable($list) {
		// ref: https://github.com/needle-mirror/com.unity.addressables/blob/5a722ef6ae2d07e55cba9c07c751d8ce8ccad3c7/Runtime/ResourceLocators/ContentCatalogData.cs#L294
		$bucketData = base64_decode($list['m_BucketDataString']);
		$s = new MemoryStream($bucketData);
		$s->littleEndian = true;
		$bc = $s->long;
		$buckets = [];
		for ($i=0; $i<$bc; $i++) {
			$index = $s->long;
			$entryCount = $s->long;
			$entry = [];
			for ($c=0; $c<$entryCount; $c++) {
				$entry[] = $s->long;
			}
			$buckets[] = [
				'dataOffset' => $index,
				'entries' => $entry,
			];
		}
		$extraData = base64_decode($list['m_ExtraDataString']);
		$es = new MemoryStream($extraData);
		$es->littleEndian = true;
		$keyData = base64_decode($list['m_KeyDataString']);
		$s = new MemoryStream($keyData);
		$s->littleEndian = true;
		$kc = $s->long;
		$keys = [];
		for ($i=0; $i<$kc; $i++) {
			$keys[] = static::ReadObjectFromStream($s, $buckets[$i]['dataOffset']);
		}
		// expand internal id
		$list['m_InternalIds'] = array_map(function ($i) use($list) {
			return preg_replace_callback('/^(\d+)\#/', function ($m) use($list) {
				return $list['m_InternalIdPrefixes'][$m[1]];
			}, $i);
		}, $list['m_InternalIds']);
		$entryData = base64_decode($list['m_EntryDataString']);
		$s = new MemoryStream($entryData);
		$s->littleEndian = true;
		$ec = $s->long;
		$locations = [];
		for ($i=0; $i<$ec; $i++) {
			$internalId = $s->long;
			$providerIndex = $s->long;
			$dependencyKeyIndex = $s->long;
			$depHash = $s->long;
			$dataIndex = $s->long;
			$primaryKey = $s->long;
			$resourceType = $s->long;
			$data = $dataIndex < 0 ? null : static::ReadObjectFromStream($es, $dataIndex);
			$locations[] = [
				//'internalId' => $list['m_InternalIds'][$internalId],
				//'providerId' => $list['m_ProviderIds'][$providerIndex],
				//'dependencyKey' => $dependencyKeyIndex < 0 ? null : $keys[$dependencyKeyIndex],
				//'data'=> $data,
				//'depHash'=> $depHash,
				'primaryKey'=> $keys[$primaryKey],
				'type'=> $list['m_resourceTypes'][$resourceType],
			];
		}
		$map = [];
		$dlToClass = [];
		foreach ($locations as $i) {
			#if (!empty($i['dependencyKey'])) {
				#if (empty($dlToClass[$i['dependencyKey']])) $dlToClass[$i['dependencyKey']] = ['class'=>[],'key'=>''];
				#$dlToClass[$i['dependencyKey']]['class'][] = $i['type']['m_ClassName'];
				#$dlToClass[$i['dependencyKey']]['key'] = $i['primaryKey'];
			#}
			$map[$i['type']['m_ClassName'].'-'.$i['primaryKey']] = $i;
			unset($i['type']);
			unset($i['primaryKey']);
		}
		ksort($map);
		ksort($dlToClass);

		return [
			$map,
			$dlToClass,
		];
	}

	static function ReadObjectFromStream(Stream $s, $offset) {
		$ObjectType = [
			'AsciiString',
			'UnicodeString',
			'UInt16',
			'UInt32',
			'Int32',
			'Hash128',
			'Type',
			'JsonObject'
		];
		$s->position = $offset;
		$keyType = ord($s->byte);
		switch ($keyType) {
			case 0: { /*AsciiString  */ $len = $s->long; return $s->readData($len); }
			case 1: { /*UnicodeString*/ $len = $s->long; return $s->readData($len); }
			case 2: { /*UInt16       */ return $s->ushort; }
			case 3: { /*UInt32       */ return $s->ulong; }
			case 4: { /*Int32        */ return $s->long; }
			case 5: { /*Hash128      */ $len = ord($s->byte);return $s->readData($len); }
			case 6: { /*Type         */ $len = ord($s->byte);return $s->readData($len); }
			case 7: { /*JsonObject   */
				$anl = ord($s->byte);
				$assemblyName = $s->readData($anl);
				$cnl = ord($s->byte);
				$className = $s->readData($cnl);
				$jl = $s->long;
				$json = $s->readData($jl);
				return [
					'assemblyName' => $assemblyName,
					'className' => $className,
					'data' => json_decode($json, true)
				];
			}
			default : {var_dump($keyType);exit;}
		}
	}

	static function downloadCustom() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$downloadedBundles = [];
		$dlMatches = [
			'2d' => [
				'/gamehints_assets_gamehints\/.+_[0-9a-f]+\.bundle/',
				'/stamps_assets_stamp\/.+_[0-9a-f]+\.bundle/',
				'/spriteatlases_assets_spriteatlases\/.+_[0-9a-f]+\.bundle/',
				'/loginbonusbackground_assets_loginbonus\/.+_[0-9a-f]+\.bundle/',
			],
			'cri' => [
				'/cridata_remote_assets_criaddressables\/.+\.usm_[0-9a-f]+\.bundle/',
				//'/cridata_remote_assets_criaddressables\/\d+\.acb_[0-9a-f]+\.bundle/',
			],
		];
		$cat = "cri";
		foreach ($dlMatches as $cat=>$rules) {
		foreach (static::$addressable[$cat][0] as $item) {
			$shouldDl = false;
			foreach ($rules as $rule) {
				if (preg_match($rule, $item['primaryKey'])) {
					$shouldDl = true;
					break;
				}
			}
			if (!$shouldDl) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, $cat.'-assets'));
			@mkdir(dirname($path), 0777, true);
			$fhandle = fopen($path, 'wb');
			curl_setopt(static::$ch, CURLOPT_FILE, $fhandle);
			curl_exec(static::$ch);
			fclose($fhandle);
			static::setCachedHash($path, $hash);
			$downloadedBundles[] = $path;
		}
		}
		return $downloadedBundles;
	}
	static function main($argc, $argv) {
		$isCron = false;
		for ($i=1; $i<$argc; $i++) {
			switch ($argv[$i]) {
				case 'dl': {
					static::downloadCustom();
					break;
				}
				case 'manifest': {
					static::downloadAddressable();
					break;
				}
				case 'jacket': {
					static::exportJackets();
					break;
				}
				case 'card': {
					static::exportCardTextures();
					break;
				}
				case 'poster': {
					static::exportPosterTextures();
					break;
				}
				case 'dlnote': {
					// 仅在 12:00 & 17:00 +9 自动刷新谱面
					if ($isCron && !in_array(date('H'), [11, 16])) break;
					static::downloadNotations();
					break;
				}
				case 'svg': {
					// 仅在 12:00 & 17:00 +9 自动刷新谱面
					if ($isCron && !in_array(date('H'), [11, 16])) break;
					static::convertNotations();
					break;
				}
				case 'music': {
					static::exportMusics();
					break;
				}
				case 'cron': {
					$isCron = true;
					$cronTime = $argv[++$i];
					break;
				}
				case 'getConfig': {
					static::getConfig();
					break;
				}
				case 'master': {
					static::updateMaster();
					break;
				}
				case 'reexport_master': {
					static::reexportMaster();
					break;
				}
				case 'scene': {
					// 仅在 12:00 & 17:00 +9 自动刷新剧情
					if ($isCron && !in_array(date('H'), [11, 16])) break;
					static::fetchEpisodeTitles();
					static::downloadScenes();
					static::convertScenes();
					break;
				}
				case 'scene_voice': {
					static::exportSceneVoice();
					break;
				}
				case 'league_info': {
					// 仅在 每周二 05:00 +9 自动刷新剧情
					if ($isCron && date('DH') !== "Tue04") break;
					$info = static::getLeagueInfo();
					if ($info) {
						$leagueInfo = json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
						$now = date('Ymd-His');
						file_put_contents(__DIR__."/league/league_info-$now.json", $leagueInfo);
					}
					break;
				}
			}
		}
	}

	// api access /api/Environment
	static function getConfig() {
		static::initApiCurl();
		$appVer = static::$appVersion;
		$apiEndpoint = PersistentStorage::get('api_endpoint', 'https://api.wds-stellarium.com');
		curl_setopt(static::$apiCh, CURLOPT_URL, "$apiEndpoint/api/Environment?applicationVersion=$appVer&gameVersion=1");
		curl_setopt(static::$apiCh, CURLOPT_POSTFIELDS, "");
		$resp = curl_exec(static::$apiCh);
		$resp = MsgPackHelper::parseServerResponse($resp, 'EnvironmentResult');
		if (!empty($resp[1][0]['AssetVersion'])) {
			$appEnvironmentConfig = $resp[1][0];
			static::$assetVersion = PersistentStorage::set('assetVersion', $appEnvironmentConfig['AssetVersion']);
			static::$apiBase = PersistentStorage::set('apiBase', $appEnvironmentConfig['ApiEndpoint']);
			static::$assetUrl = PersistentStorage::set('assetUrl', $appEnvironmentConfig['AssetUrl']);
			static::$masterUrl = PersistentStorage::set('masterUrl', $appEnvironmentConfig['MasterDataUrl']);
		}
	}
	// api access /api/Account/Authenticate
	static function authenticate() {
		static::initApiCurl();
		$acctoken = PersistentStorage::get('account_token');
		if (empty($acctoken)) return false;
		$payload = [
			$acctoken,
			1, null, null,
			static::$appVersionFull
		];
		$ch = curl_copy_handle(static::$apiCh);
		curl_setopt_array($ch, [
			CURLOPT_URL=> static::$apiBase . '/api/Account/Authenticate',
			CURLOPT_POSTFIELDS => msgpack_pack($payload)
		]);
		$resp = curl_exec($ch);
		$resp = MsgPackHelper::parseServerResponse($resp, 'AuthenticateResult');
		if (empty($resp[1][0]['Token'])) return false;
		PersistentStorage::set('login_token', $resp[1][0]['Token']);
		return true;
	}
	// api access /api/data/master
	// auto reload token
	static function getMasterDataUrl($retry = false) {
		static::initApiCurl();
		$token = PersistentStorage::get('login_token');
		if (empty($token)) {
			static::authenticate();
			return static::getMasterDataUrl(true);
		}
		$ch = curl_copy_handle(static::$apiCh);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_URL=> static::$apiBase . '/api/data/master',
			CURLOPT_HTTPHEADER => static::getApiHeaders(["Authorization: Bearer ".$token]),
		]);
		$resp = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode == 403 || $httpCode == 440) {
			if ($retry) return;
			static::authenticate();
			return static::getMasterDataUrl(true);
		}
		$resp = MsgPackHelper::parseServerResponse($resp, 'MasterDataManifest');
		if (empty($resp[1][0]['Uri'])) return null;
		return $resp[1][0];
	}
	// api access /api/Leagues/TopMenuInformation
	static function getLeagueInfo() {
		static::initApiCurl();
		$token = PersistentStorage::get('login_token');
		if (empty($token)) {
			return;
		}
		$ch = curl_copy_handle(static::$apiCh);
		curl_setopt_array($ch, [
			CURLOPT_URL=> static::$apiBase . '/api/Leagues/TopMenuInformation',
			CURLOPT_HTTPHEADER => static::getApiHeaders([
				"Authorization: Bearer ".$token,
				"X-MasterData-Version: ".file_get_contents("master/!version.txt"),
			]),
			CURLOPT_POSTFIELDS => "",
		]);
		$resp = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode == 403 || $httpCode == 440) {
			return;
		}
		$resp = MsgPackHelper::parseServerResponse($resp, 'LeagueTopMenuInformationResult');
		if (empty($resp[1][0]['EnrolledGroupNumber'])) return null;
		return $resp;
	}
	// api access /api/Episodes/{episodeId}/GetDetails?episodeMasterId={episodeId}
	static function getEpisodeTitle($episodeId) {
		static::initApiCurl();
		$token = PersistentStorage::get('login_token');
		if (empty($token)) {
			return false;
		}
		$ch = curl_copy_handle(static::$apiCh);
		curl_setopt_array($ch, [
			CURLOPT_URL=> static::$apiBase . "/api/Episodes/${episodeId}/GetDetails?episodeMasterId=${episodeId}",
			CURLOPT_HTTPHEADER => static::getApiHeaders([
				"Authorization: Bearer ".$token,
				"X-MasterData-Version: ".file_get_contents("master/!version.txt"),
			]),
			CURLOPT_POSTFIELDS => "",
		]);
		$resp = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode == 403 || $httpCode == 440) {
			return false;
		}
		$resp = MsgPackHelper::parseServerResponse($resp, 'EpisodeResult');
		if (empty($resp[1][0]['EpisodeTitle'])) return null;
		return $resp[1][0]['EpisodeTitle'];
	}
	static function fetchEpisodeTitles() {
		/*
		// ??? 【エピソード:110011の閲覧条件を満たしていません。】
		$cardEpisodes = static::loadKeyedMaster('CharacterEpisodeMaster');
		foreach ($cardEpisodes as $e) {
			$sceneIds[] = $e['Id'];
		}
		*/

		$bootSplashScreens = static::loadKeyedMaster('SplashMaster');
		foreach ($bootSplashScreens as $s) {
			if ($s['SplashType'] !== 'Episode') continue;
			$sceneIds[] = $s['SplashValue'];
		}

		static::initCache();
		$stmt = static::$cacheDb->prepare('SELECT episode_title FROM episode_titles WHERE episode_id = ?');
		$insert = static::$cacheDb->prepare('INSERT INTO episode_titles (episode_id, episode_title) VALUES (?, ?)');
		foreach ($sceneIds as $sceneId) {
			$stmt->execute([$sceneId]);
			$result = $stmt->fetch(PDO::FETCH_NUM);
			if ($result) continue;
			$title = static::getEpisodeTitle($sceneId);
			if ($title) {
				$insert->execute([$sceneId, $title]);
			}
			break;
		}
	}
	static function getCachedEpisodeTitle($episodeId) {
		static::initCache();
		$stmt = static::$cacheDb->prepare('SELECT episode_title FROM episode_titles WHERE episode_id = ?');
		$stmt->execute([$episodeId]);
		$result = $stmt->fetch(PDO::FETCH_NUM);
		return $result ? $result[0] : '';
	}

	static function updateMaster() {
		$lastMasterVersion = PersistentStorage::get('master_version', '');
		$currentMaster = static::getMasterDataUrl();
		static::_log("server master data version: ".$currentMaster['Version'], false);
		if (!$currentMaster) return;
		if ($lastMasterVersion >= $currentMaster['Version']) return;

		static::_log("dumping new master ".$currentMaster['Version']);
		// download master
		static::initCurl();
		curl_setopt(static::$ch, CURLOPT_URL, static::$masterUrl.'/'.$currentMaster['Uri']);
		$data = curl_exec(static::$ch);
		$httpCode = curl_getinfo(static::$ch, CURLINFO_HTTP_CODE);
		if ($httpCode != 200) return;

		chdir(__DIR__);
		$lastMasterVersion = PersistentStorage::set('master_version', $currentMaster['Version']);
		if (md5($data) == md5_file('mastermemory.dat')) {
			static::_log("master data not changed");
			return;
		}
		file_put_contents('mastermemory.dat', $data);

		file_put_contents("master/!version.txt", $currentMaster['Version']);
		static::exportMaster($currentMaster['Version'], $data);

		static::updateWebpageIndex();
	}
	static function exportMaster($commit, $data) {
		chdir(__DIR__);
		// delete old entries
		chdir('master');
		exec('git rm *.json');

		// dump master
		$timestampExtension = new TimestampExtension;
		$lz4BlockExtension = new Lz4BlockExtension;
		$unpacker = new \MessagePack\BufferUnpacker;
		$unpacker = $unpacker->extendWith($timestampExtension)->extendWith($lz4BlockExtension);
		$unpacker->reset($data);

		$tableInfo = $unpacker->unpack();
		$totalDataLen = array_reduce($tableInfo, function ($s, $i){ return max($i[0]+$i[1], $s); }, 0);
		$dataOffset = strlen($data) - $totalDataLen;

		foreach ($tableInfo as $name=>$info) {
			$unpacker->seek($dataOffset + $info[0]);
			$table = $unpacker->unpack();
			MsgPackHelper::applyTypeNameByTableName($table, $name);
			file_put_contents("$name.json", MsgPackHelper::prettifyJSON($table));
			file_put_contents("$name.json", "\n", FILE_APPEND);
		}

		// add new entries
		exec('git add *.json !version.txt');
		exec('git commit -m "'.$commit.'"');
		exec('git push origin master');
		chdir('..');
	}
	static function reexportMaster() {
		chdir(__DIR__);
		if (!file_exists('mastermemory.dat')) return;
		$data = file_get_contents('mastermemory.dat');
		$masterVersion = PersistentStorage::get('master_version');
		static::exportMaster("$masterVersion (re-export)", $data);
	}
	static function loadKeyedMaster($table, $key = 'Id') {
		return array_reduce(json_decode(file_get_contents("master/$table.json"), true), function ($s, $i) use ($key) { $s[$i[$key]] = $i; return $s; }, []);
	}
	static function updateWebpageIndex() {
		chdir(__DIR__);
		// jacket
		$musics = static::loadKeyedMaster('MusicVocalVersionMaster');
		$musicIndex = [];
		foreach ($musics as $m) {
			$k = $m['MusicMasterId'] . ($m['VocalVersion'] > 1 ? '_'.$m['VocalVersion'] : '');
			$musicIndex[$k] = $m['Name'];
		}
		file_put_contents(static::getResourcePathPrefix().'jacket/index.json', json_encode($musicIndex));

		// notation level
		$levels = static::loadKeyedMaster('LiveMaster');
		$musicLevelIndex = [];
		foreach ($levels as $l) {
			$m = $l['MusicMasterId'];
			if (!isset($musicLevelIndex[$m])) $musicLevelIndex[$m] = [0];
			$musicLevelIndex[$m][MsgPackHelper::fromEnumName('MusicDifficulties', $l['Difficulty'])] = $l['Level'] > 100 ? [
				'I', 'II', 'III', 'IV', 'V',
				'VI', 'VII', 'VIII', 'IX', 'X'
			][$l['Level'] - 101] : $l['Level'];
		}
		file_put_contents(static::getResourcePathPrefix().'jacket/index-level.json', json_encode($musicLevelIndex));

		// card
		$cards = static::loadKeyedMaster('CharacterMaster');
		$charas = static::loadKeyedMaster('CharacterBaseMaster');
		$cardIndex = [];
		$cardReleaseTime = [];
		foreach ($cards as $c) {
			$cardIndex[$c['Id']] = str_repeat('★', MsgPackHelper::fromEnumName('CharacterRarities', $c['Rarity'])).'【'.$c['Name'].'】'.$charas[$c['CharacterBaseMasterId']]['Name'];
			$releaseTime = $c['DisplayStartAt'];
			if (!isset($cardReleaseTime[$releaseTime])) $cardReleaseTime[$releaseTime] = [];
			$cardReleaseTime[$releaseTime][] = $c['Id'];
		}
		file_put_contents(static::getResourcePathPrefix().'card/index.json', json_encode($cardIndex));

		// poster
		$posters = static::loadKeyedMaster('PosterMaster');
		$posterIndex = [];
		foreach ($posters as $p) {
			$posterIndex[$p['Id']] = $p['Rarity'].' '.$p['Name'];
		}
		file_put_contents(static::getResourcePathPrefix().'poster/index.json', json_encode($posterIndex));

		// scene spot story
		$spotConversations = static::loadKeyedMaster('SpotConversationMaster');
		$sceneIndexPage = fopen(static::getResourcePathPrefix().'scenes/index.htm', 'w');
		fwrite($sceneIndexPage, '<!DOCTYPE HTML><html><head><meta name="format-detection" content="telephone=no"><meta charset=utf8><meta name=viewport content="width=devide-width,initial-scale=1.0,user-scalable=0"><style>html{-webkit-text-size-adjust:none;font-family:Arial,Meiryo}h1{font-size:22px}li{font-size:16px}a:link,a:visited,a:active{text-decoration:none;color:#1E90FF}a:hover{color:#555}</style><title>ユメステ シナリオ</title></head><body><h1>ユメステ シナリオ</h1><h4><label style="display:none"><input type="checkbox" id="show-name" checked>Show name</label><div><input type="text" placeholder="search id/name" id="searchinput"></div></h4>');
		fwrite($sceneIndexPage, "\n<base href=\"script/\" />\n");
		$spotConversationGroups = array_reduce($spotConversations, function ($s, $i){
			if (!isset($s[$i['Spot']])) $s[$i['Spot']] = [];
			$c = [$i['Id']];
			for ($j=1; $j<6; $j++) {
				if (isset($i["CharacterId$j"])) {
					$c[] = $i["CharacterId$j"];
				}
			}
			$s[$i['Spot']][] = $c;
			return $s;
		}, []);
		$spotNames = [
			'UtagawaHighSchool'=>'歌川高等学校',
			'HigashiUenoHighSchool'=>'東上野高校',
			'Park'=>'公園',
			'Cafe'=>'カフェ',
			'ElectricTown'=>'電気街',
			'ThemePark'=>'テーマパーク',
		];
		foreach ($spotConversationGroups as $spot=>$conversations) {
			if (empty($conversations)) continue;
			fwrite($sceneIndexPage, '<details><summary>'.(isset($spotNames[$spot]) ? $spotNames[$spot] : $spot).'</summary><ul>');
			foreach ($conversations as $c) {
				$id = array_shift($c);
				$charaNames = array_map(function ($i)use($charas) { return $charas[$i]['Name']; }, $c);
				fwrite($sceneIndexPage, '<div data-search="'.implode(',', array_merge([$id], $charaNames)).'"><li><a href="'.$id.'.htm">'.$id.'</a> '.implode(' & ', $charaNames).'</li></div>');
				fwrite($sceneIndexPage, "\n");
			}
			fwrite($sceneIndexPage, "</ul></details>\n");
		}
		fwrite($sceneIndexPage, "<hr>\n");

		// scene main story
		$episodes = static::loadKeyedMaster('EpisodeMaster');
		$stories = static::loadKeyedMaster('StoryMaster');
		$companies = static::loadKeyedMaster('CompanyMaster');
		$storyGroups = array_reduce($episodes, function ($s, $i){
			if (!isset($s[$i['StoryMasterId']])) $s[$i['StoryMasterId']] = [];
			$s[$i['StoryMasterId']][] = [$i['Id'], $i['Title']];
			return $s;
		}, []);
		foreach ($storyGroups as $story=>$eps) {
			if (empty($eps)) continue;
			@fwrite($sceneIndexPage, '<details><summary>'.$companies[$stories[$story]['CompanyMasterId']]['Name'].' 第'.$stories[$story]['ChapterOrder'].'章</summary><ul>');
			foreach ($eps as $c) {
				list($id, $title) = $c;
				fwrite($sceneIndexPage, '<div data-search="'.implode(',', $c).'"><li><a href="'.$id.'.htm">'.$id.'</a> '.$title.'</li></div>');
				fwrite($sceneIndexPage, "\n");
			}
			fwrite($sceneIndexPage, "</ul></details>\n");
		}
		fwrite($sceneIndexPage, "<hr>\n");

		// scene event story
		$eventEpisodes = static::loadKeyedMaster('StoryEventEpisodeMaster');
		$events = static::loadKeyedMaster('StoryEventMaster');
		$eventGroups = array_reduce($eventEpisodes, function ($s, $i){
			if (!isset($s[$i['StoryMasterId']])) $s[$i['StoryMasterId']] = [];
			$s[$i['StoryMasterId']][] = [$i['Id'], $i['Title']];
			return $s;
		}, []);
		$eventIds = array_keys($eventGroups);
		usort($eventIds, function ($a, $b) use($events) {
			return $events[$a]['StartDate'] <=> $events[$b]['StartDate'];
		});
		foreach ($eventIds as $story) {
		//foreach ($eventGroups as $story=>$eps) {
			$eps = $eventGroups[$story];
			if (empty($eps)) continue;
			$startDate = explode(' ', $events[$stories[$story]['EventMasterId']]['StartDate'])[0];
			@fwrite($sceneIndexPage, '<details><summary>'.$startDate.' '.$events[$stories[$story]['EventMasterId']]['Title'].'</summary><ul>');
			foreach ($eps as $c) {
				list($id, $title) = $c;
				fwrite($sceneIndexPage, '<div data-search="'.implode(',', $c).'"><li><a href="'.$id.'.htm">'.$id.'</a> '.$title.'</li></div>');
				fwrite($sceneIndexPage, "\n");
			}
			fwrite($sceneIndexPage, "</ul></details>\n");
		}
		fwrite($sceneIndexPage, "<hr>\n");

		// scene splash story
		$bootSplashScreens = static::loadKeyedMaster('SplashMaster');
		@fwrite($sceneIndexPage, '<ul>');
		foreach ($bootSplashScreens as $s) {
			if ($s['SplashType'] !== 'Episode') continue;
			$episodeId = $s['SplashValue'];
			$epTitle = static::getCachedEpisodeTitle($episodeId);
			$shownPeriod = explode(' ', $s['StartDate'])[0].' ~ '.explode(' ', $s['EndDate'])[0];
			fwrite($sceneIndexPage, '<div data-search="'.implode(',', [$episodeId, $epTitle]).'"><li>'.$shownPeriod.' <a href="'.$episodeId.'.htm">'.$episodeId.'</a> '.$epTitle.'</li></div>');
		}
		fwrite($sceneIndexPage, "</ul>\n");
		fwrite($sceneIndexPage, "<hr>\n");

		// scene card story
		$cardEpisodes = static::loadKeyedMaster('CharacterEpisodeMaster');
		$cardEpisodeGroups = array_reduce($cardEpisodes, function ($s, $i){
			if (!isset($s[$i['CharacterMasterId']])) $s[$i['CharacterMasterId']] = [];
			$s[$i['CharacterMasterId']][$i['EpisodeOrder']] = $i['Id'];
			return $s;
		}, []);
		fwrite($sceneIndexPage, "<ul>\n");
		uksort($cardReleaseTime, function ($a, $b) { return $a <=> $b; });
		foreach ($cardReleaseTime as $releaseTime=>$cards) {
			foreach ($cards as $card) {
				if (empty($cardEpisodeGroups[$card])) continue;
				$eps = $cardEpisodeGroups[$card];
				/*
				$searchTerms = @[$card, $cardIndex[$card]];
				$epTitle = [];
				foreach ($eps as $key=>$ep) {
					$title = static::getCachedEpisodeTitle($ep);
					$epTitle[$key] = $title;
					$searchTerms[] = $title;
				}
				fwrite($sceneIndexPage, '<div data-search="'.implode(',', [$card, $cardIndex[$card]]).'"><li>');
				fwrite($sceneIndexPage, '<div>'. $cardIndex[$card] .'</div>');
				@fwrite($sceneIndexPage, '<div>　　<a href="'.$eps['First'].'.htm">前編</a> '.$epTitle['First'].'</div>');
				@fwrite($sceneIndexPage, '<div>　　<a href="'.$eps['Second'].'.htm">後編</a> '.$epTitle['Second'].'</div>');
				fwrite($sceneIndexPage, "</li></div>\n");
				*/

				@fwrite($sceneIndexPage, '<div data-search="'.implode(',', [$card, $cardIndex[$card]]).'"><li>');
				fwrite($sceneIndexPage, '<a href="'.$eps['First'].'.htm">前編</a> | ');
				fwrite($sceneIndexPage, '<a href="'.$eps['Second'].'.htm">後編</a> ');
				fwrite($sceneIndexPage, $cardIndex[$card]);
				fwrite($sceneIndexPage, "</li></div>\n");
			}
		}
		fwrite($sceneIndexPage, "</ul>\n");

		fwrite($sceneIndexPage, '<script src="https://redive.estertion.win/static/common_listpage.min.js"></script></body></html>');
	}

	static function downloadScenes() {
		chdir(__DIR__);
		static::initCurl();
		$sceneIds = [];

		$spotConversations = static::loadKeyedMaster('SpotConversationMaster');
		foreach ($spotConversations as $c) {
			$sceneIds[] = $c['Id'];
		}

		$episodes = static::loadKeyedMaster('EpisodeMaster');
		foreach ($episodes as $e) {
			$sceneIds[] = $e['Id'];
		}

		$eventEpisodes = static::loadKeyedMaster('StoryEventEpisodeMaster');
		foreach ($eventEpisodes as $e) {
			$sceneIds[] = $e['Id'];
		}

		$cardEpisodes = static::loadKeyedMaster('CharacterEpisodeMaster');
		foreach ($cardEpisodes as $e) {
			$sceneIds[] = $e['Id'];
		}

		$bootSplashScreens = static::loadKeyedMaster('SplashMaster');
		foreach ($bootSplashScreens as $s) {
			if ($s['SplashType'] !== 'Episode') continue;
			$sceneIds[] = $s['SplashValue'];
		}

		$timestampExtension = new TimestampExtension;
		$lz4BlockArrayExtension = new Lz4BlockArrayExtension;
		$unpacker = new \MessagePack\BufferUnpacker;
		$unpacker = $unpacker->extendWith($timestampExtension)->extendWith($lz4BlockArrayExtension);

		$curlId = 0;
		$curlInfo = [];
		$multiHelper = new CurlMultiHelper;
		$multiHelper->maxParallel = 20;
		$multiHelper->setupCb = function ($ch) use (&$sceneIds, &$curlId, &$curlInfo) {
			if (empty($sceneIds)) return null;
			if ($ch == null) {
				$ch = curl_copy_handle(static::$ch);
			}
			$nextItem = array_shift($sceneIds);
			curl_setopt($ch, CURLOPT_URL, static::getSceneUrl($nextItem));
			curl_setopt($ch, CURLOPT_PRIVATE, $curlId);
			$curlInfo[$curlId] = $nextItem;
			$curlId++;
			return $ch;
		};
		$multiHelper->finishCb = function ($ch) use (&$curlInfo, &$unpacker) {
			$curlId = curl_getinfo($ch, CURLINFO_PRIVATE);
			$id = $curlInfo[$curlId];
			$data = curl_multi_getcontent($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($httpCode != 200) {
				static::_log("$id $httpCode");
				return;
			}
			$path = "scenes/$id.json";
			$unpacker->reset($data);
			$data = $unpacker->unpack();
			$data = $data[0];
			MsgPackHelper::applyTypeNameByType($data, 'EpisodeDetailResult[]');
			$hasNewScene = checkAndCreateFile($path, MsgPackHelper::prettifyJSON($data));
			if ($hasNewScene) static::_log('save '.$path);
		};
		$multiHelper->run();
	}
	static function convertScenes() {
		foreach (glob('scenes/*.json') as $f) {
			static::_log("export $f", false);
			$page = static::sceneToHtml(file_get_contents($f));
			if (empty($page)) continue;
			$saveTo = dirname($f).'/'.pathinfo($f, PATHINFO_FILENAME).'.htm';
			file_put_contents($saveTo, $page);
			//checkAndCreateFile($saveTo, $page);
		}
	}
	static function sceneToHtml($data) {
		$data = json_decode($data, true);
		if (empty($data)) return '';
		usort($data, function ($a, $b) {
			return ($a['GroupOrder'] * 10 + $a['Order']) - ($b['GroupOrder'] * 10 + $b['Order']);
		});
		$output = [
			'<!DOCTYPE HTML><html><head><title>ユメステ '.$data[0]['EpisodeMasterId'].'</title><meta name="format-detection" content="telephone=no"><meta charset="UTF-8" name="viewport" content="width=device-width"><style>.cmd{color:#DDD;font-size:12px;cursor:pointer}.line{white-space:pre-wrap}.voice,.speaker,.line{display:block}.highlight{background:#ccc;color:#000}</style></head><body style="background:#444;color:#FFF;font-family:Meiryo;-webkit-text-size-adjust:none;cursor:default">'
		];
		$group = [];
		$groupNumber = 0;
		$voice = [];
		foreach ($data as $line) {
			if ($groupNumber !== $line['GroupOrder']) {
				if (!empty($group)) {
					$group = array_merge(['<p>'], $voice, $group, ['</p>']);
					$output[] = implode("\n", $group);
					$group = [];
					$voice = [];
				}
				$groupNumber = $line['GroupOrder'];
			}
			if (!empty($line['VoiceFileName'])) {
				$voice[] = '<span class="voice cmd" data-voice-path="'.$line['VoiceFileName'].'">voice: '.$line['VoiceFileName'].'</span>';
			}
			if ($line['Order'] == 1 && !empty($line['SpeakerName'])) {
				$group[] = '<span class="speaker">'.$line['SpeakerName'].'</span>';
			}
			if (!empty($line['Phrase'])) {
				$group[] = '<span class="line">'.str_replace('/n', "\n", $line['Phrase']).'</span>';
			}
		}
		if (!empty($group)) {
			$group = array_merge(['<p>'], $voice, $group, ['</p>']);
			$output[] = implode("\n", $group);
		}
		$output[] = '<script src="/wds/story_data.min.js"></script></body></html>';
		return implode("\n", $output);
	}
	static function downloadSceneVoice() {
		chdir(__DIR__);
		static::loadAddressable();
		static::initCurl();
		$downloadedBundles = [];
		foreach (static::$addressable['cri'][0] as $item) {
			if (!preg_match('/cridata_remote_assets_criaddressables\/(\d{4}|1[1234]\d{4}|[123]\d{6})\.acb_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			$hash = static::getAssetHash($item['primaryKey']);
			if (!static::shouldDownload($path, $hash)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, 'cri-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
			static::setCachedHash($path, $hash);
			$downloadedBundles[] = $path;
		}
		return $downloadedBundles;
	}
	static function exportSceneVoice() {
		$downloadedBundles = static::downloadSceneVoice();
		$saveTo = static::getResourcePathPrefix() . 'sound/scene';
		foreach ($downloadedBundles as $acbName) {
			static::_log('exporting '. $acbName);
			$nullptr = NULL;
			exec('acb2wavs '.$acbName.' -b 0 -a 0 -n', $nullptr);
			$acbUnpackDir = dirname($acbName).'/_acb_'.pathinfo($acbName, PATHINFO_BASENAME);
			foreach (['internal', 'external'] as $awbFolder) {
				if (file_exists($acbUnpackDir .'/'. $awbFolder)) {
					foreach (glob($acbUnpackDir .'/'. $awbFolder.'/*.wav') as $waveFile) {
						$m4aFile = substr($waveFile, 0, -3).'m4a';
						$finalPath = $saveTo.'/'.pathinfo($m4aFile, PATHINFO_BASENAME);
						exec('ffmpeg -hide_banner -loglevel quiet -y -i '.$waveFile.' -vbr 5 -movflags faststart '.$m4aFile, $nullptr);
						checkAndMoveFile($m4aFile, $finalPath);
					}
				}
			}
			static::delTree($acbUnpackDir);
		}
	}
}

WdsNotationConverter::init();
WdsNotationConverter::main($argc, $argv);
//WdsNotationConverter::authenticate();
//WdsNotationConverter::test();
