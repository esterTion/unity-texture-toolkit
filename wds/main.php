<?php

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

class WdsNotationConverter {

	static function _log($s) {
		echo date('[Y/m/d H:i:s] ')."$s\n";
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
	}
	static function downloadAddressable() {
		chdir(__DIR__);
		static::initCurl();
		$manifestUpdated = false;
		foreach (['2d', '3d', 'cri'] as $type) {
			$path = "$type-assets.json";
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl("catalog_1.0.1.json", "$type-assets"));
			$data = curl_exec(static::$ch);
			if (!empty($data) && md5($data) !== md5_file($path)) {
				$manifestUpdated = true;
				file_put_contents($path, $data);
			}
		}
		if ($manifestUpdated && file_exists('cache_addressable.dat')) {
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
			$path = __DIR__."/$type-assets.json";
			if (!file_exists($path)) {
				throw new Exception("$path not found");
			}
			$data = json_decode(file_get_contents($path), true);
			$parsed = static::parseAddressable($data);
			static::$addressable[$type] = $parsed;
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
		foreach (static::$addressable['3d'][0] as $item) {
			if (!preg_match('/(game_splitlaneelement_assets_spliteffectelements\/spliteffectelements.+|game_splitlane_assets_spliteffects\/\d+)_[0-9a-f]+\.bundle/', $item['primaryKey'])) continue;
			$path = static::getAssetPath($item['primaryKey']);
			if (file_exists($path)) continue;
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getAssetUrl($path, '3d-assets'));
			$data = curl_exec(static::$ch);
			@mkdir(dirname($path), 0777, true);
			file_put_contents($path, $data);
		}
	}

	static function getResourcePathPrefix() {
		return '/data/home/web/_redive/wds/';
	}
	static function getAssetUrlBase() {
		return 'https://assets.wds-stellarium.com/production';
	}
	static function getAssetPath($key) {
		return preg_replace('/^(.+)_[0-9a-f]+(\..+?)$/', '$1$2', $key);
	}
	static function getAssetHash($key) {
		return preg_replace('/^(.+)_([0-9a-f]+)(\..+?)$/', '$2', $key);
	}
	static function getAssetUrl($path, $type) {
		$base = static::getAssetUrlBase();
		return "$base/$type/iOS/1.0.1/$path";
	}
	static function getNotationConfigUrl($music) {
		$base = static::getAssetUrlBase();
		return "$base/Notations/$music/music_config.enc";
	}
	static function getNotationUrl($music, $level) {
		$base = static::getAssetUrlBase();
		return "$base/Notations/$music/$level.enc";
	}

	static $splitEffectElements = null;
	static function loadAllSplitEffectElements() {
		if (!empty(static::$splitEffectElements)) return;
		if (file_exists('cache_spliteffectelements.dat')){
			static::$splitEffectElements = json_decode(file_get_contents('cache_spliteffectelements.dat'), true);
			return;
		}
		static::downloadSplitlaneAssets();
		foreach (glob('game_splitlaneelement_assets_spliteffectelements/*.bundle') as $elementBundle) {
			static::_log('load '.$elementBundle);
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
		$boundariesCount = $type - 9;
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
		static::downloadJackets();
		//foreach (glob('cridata_remote_assets_criaddressables/music_*.bundle') as $jacketBundle) {
		foreach (glob('jacket_assets_jacket/*.bundle') as $jacketBundle) {
			$music = pathinfo($jacketBundle, PATHINFO_FILENAME);
			//$music = substr(pathinfo(pathinfo($jacketBundle, PATHINFO_FILENAME), PATHINFO_FILENAME), 6);

			$path = "Notations/$music/music_config.txt";
			static::_log('dl '.$path);
			curl_setopt(static::$ch, CURLOPT_URL, static::getNotationConfigUrl($music));
			$data = curl_exec(static::$ch);
			$httpCode = curl_getinfo(static::$ch, CURLINFO_HTTP_CODE);
			if ($httpCode != 200) continue;
			@mkdir(dirname($path), 0777, true);
			checkAndCreateFile($path, static::decryptMusicConfig($data));
			for ($i=1;$i<6;$i++) {
				$path = "Notations/$music/$i.txt";
				static::_log('dl '.$path);
				curl_setopt(static::$ch, CURLOPT_URL, static::getNotationUrl($music, $i));
				$data = curl_exec(static::$ch);
				checkAndCreateFile($path, static::decryptNotation($data));
			}
		}
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
		$saveTo = 'sound/music';
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
			$endOfChartTs = max($noteEnd, $noteEnd);
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
				case '11':
				case '12':
				case '13':
				case '14':
				case '15':
				case '16': { $splitSections[] = $note; $endOfChartTs = max($noteEnd + 1, $noteEnd); break;}
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
			$syncPoints[$noteTimeS][0] = min($syncPoints[$noteTimeS][0], $x + 20);
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
			$syncPoints[$noteTimeS][0] = min($syncPoints[$noteTimeS][0], $x + 20);
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
			$endPos = static::getNotePos($endFadeTime, 1);
			$line = [
				'type' => $note['gimmickType'],
				'effect' => $note['gimmickValue'],
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
				'isFinal' => false,
			];
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
					'isFinal' => false,
				];
				$line['start'] = $insertEnd;
			}
			$line['isFinal'] = true;
			$lines[] = $line;
		}
		$svgParts[] = '<g class="split_layer">';
		$fadeOutGradients = [];
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
						$svgParts[] = "<path d=\"M${x1},${y1}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadeout_$fadeOutId)\" clip-path=\"path('M${x1},${y1}m0,${h1}v${h2}h3v-${h2}')\" />";
						$x2 = $x1 + 200;
						$y2 = $y1 + 800;
						$svgParts[] = "<path d=\"M${x2},${y2}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadeout_$fadeOutId)\" clip-path=\"path('M${x2},${y2}v${h1}h3v-${h1}')\" />";
					} else {
						$svgParts[] = "<path d=\"M${x1},${y1}v${HeightPerSecond}h3v-${HeightPerSecond}z\" fill=\"url(#split_fadeout_$fadeOutId)\" />";
					}
				}
			}
		}
		$svgParts[] = '<defs>';
		foreach ($fadeOutGradients as $color=>$id) {
			$svgParts[] = "<linearGradient id=\"split_fadeout_$id\" x2=\"0\" y2=\"100%\">$color</linearGradient>";
		}
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
				$svgParts[] = "<path d=\"M$x1,$y H$x2\" stroke=\"#B4B4B4\" />";
			} else if ($t > 1 && $t - floor($t / $DurationPerColumn) * $DurationPerColumn < $DurationPerColumn * 0.01) {
				$x1 -= 200;
				$x2 -= 200;
				$y -= 800;
				$svgParts[] = "<path d=\"M$x1,$y H$x2\" stroke=\"#B4B4B4\" />";
			}
		}
		$syncLayerParts[] = '</g>';
		array_splice($svgParts, $syncLayerIdx, 0, $syncLayerParts);

		$svgParts[] = '<g class="text_mark_layer">';
		for ($i=0; $i<=$endOfChartTs; $i+=1) {
			$column = floor($i / $DurationPerColumn);
			$x = 200 * $column + 175;
			$y = 850 - $HeightPerSecond * ($i - $column * $DurationPerColumn);
			$svgParts[] = '<text class="section_mark" x="'.$x.'" y="'.$y.'">'.($i).'</text>';
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
			static::_log("export $f");
			$notation = static::parseNotation(file_get_contents($f));
			$svg = static::notationToSvg($notation);
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
				'internalId' => $list['m_InternalIds'][$internalId],
				'providerId' => $list['m_ProviderIds'][$providerIndex],
				'dependencyKey' => $dependencyKeyIndex < 0 ? null : $keys[$dependencyKeyIndex],
				'data'=> $data,
				'depHash'=> $depHash,
				'primaryKey'=> $keys[$primaryKey],
				'type'=> $list['m_resourceTypes'][$resourceType],
			];
		}
		$map = [];
		$dlToClass = [];
		foreach ($locations as $i) {
			if (!empty($i['dependencyKey'])) {
				if (empty($dlToClass[$i['dependencyKey']])) $dlToClass[$i['dependencyKey']] = ['class'=>[],'key'=>''];
				$dlToClass[$i['dependencyKey']]['class'][] = $i['type']['m_ClassName'];
				$dlToClass[$i['dependencyKey']]['key'] = $i['primaryKey'];
			}
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

	static function main($argc, $argv) {
		for ($i=1; $i<$argc; $i++) {
			switch ($argv[$i]) {
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
					static::downloadNotations();
					break;
				}
				case 'svg': {
					static::convertNotations();
					break;
				}
				case 'music': {
					static::exportMusics();
					break;
				}
			}
		}
	}
}

//WdsNotationConverter::downloadAddressable();
//WdsNotationConverter::exportJackets();
//WdsNotationConverter::downloadNotations();
//WdsNotationConverter::convertNotations();
WdsNotationConverter::main($argc, $argv);
