<?php

require 'UnityBundle.php';

chdir(__DIR__);
$logFile = fopen('rizline.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo $s."\n";
}

main('lt-zhw', 'http://rizastcdn.pigeongames.cn/default/', 'https://lvdgjosdl.ltgamesglobal.net/testasset/');
main('intl-en', 'http://rizastcdn.pigeongames.cn/default/', 'http://rizlineassetstore.pigeongames.cn/pro/');

function main($branch, $dlReplace, $dlUrl) {

	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER=>true,
		CURLOPT_SSL_VERIFYPEER=>false,
		CURLOPT_HEADER=>false,
		CURLOPT_URL=>"${dlUrl}iOS/catalog_catalog.hash",
	]);
	$hash = curl_exec($ch);
	$oldHash = file_exists("catalog_hash-$branch.txt") ? file_get_contents("catalog_hash-$branch.txt") : '';
	var_dump($hash);
	if (empty($hash) || strlen($hash) != 32) {
		// failed
		_log("dl catalog hash failed for ${branch}");
		return;
	}
	if ($hash === $oldHash) {
		// same
		return;
	}
	curl_setopt($ch, CURLOPT_URL, "${dlUrl}iOS/catalog_catalog.json");
	$list = json_decode(curl_exec($ch), true);
	if ($list === NULL) {
		_log("dl catalog json failed for $branch");
		return;
	}

	_log("$branch new catalog hash - $hash");
	file_put_contents("catalog_hash-$branch.txt", $hash);
	chdir('data');
	exec("git symbolic-ref HEAD refs/heads/${branch}");
	exec('git reset');
	exec('git rm -r .');

	file_put_contents('catalog_catalog.json', json_encode($list, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE+JSON_PRETTY_PRINT));
	list($map, $dlToClass) = parseAddressable($list);
	file_put_contents('catalog_parsed.json', json_encode($map, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE+JSON_PRETTY_PRINT));
	file_put_contents('catalog_dltoclass.json', json_encode($dlToClass, JSON_UNESCAPED_SLASHES+JSON_UNESCAPED_UNICODE+JSON_PRETTY_PRINT));

	$list = [];
	foreach ($map as $i) {
		if (preg_match('/^[0-9a-f]{32}\.bundle$/', $i['dependencyKey'])) {
			$list[$i['primaryKey']] = $i['dependencyKey'];
		}
	}
	$t = count($list);
	$d = 1;
	foreach ($list as $f=>$b) {
		#echo "\r$d/$t $f     ";
		curl_setopt($ch, CURLOPT_URL, "${dlUrl}iOS/$b");
		$data = curl_exec($ch);
		file_put_contents("$f.bundle", $data);
		$d++;
	}
	exec('git add .');
	exec('git commit -m '.date('Ymd'));
	exec("git push origin ${branch}");

}

function parseAddressable($list) {
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
		$keys[] = ReadObjectFromStream($s, $buckets[$i]['dataOffset']);
	}
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
		$data = $dataIndex < 0 ? null : ReadObjectFromStream($es, $dataIndex);
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

function ReadObjectFromStream(Stream $s, $offset) {
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