<?php

/**
 * https://github.com/esterTion/redive_master_db_diff
 * at commit
 * 74f1ee65403d99bb913ebeaf1176a3eee5568014
 */
$db = new PDO('sqlite:!redive-10052900.db');

$tableNames = $db->query('SELECT name FROM sqlite_master WHERE type="table"')->fetchAll(PDO::FETCH_COLUMN);

$normalTables = array_filter($tableNames, function($name) {
		return !preg_match('/^v1_/', $name);
});
$encryptedTables = array_filter($tableNames, function($name) {
		return preg_match('/^v1_/', $name);
});

sort($normalTables);
sort($encryptedTables);

$nameMap = [];
foreach ($encryptedTables as $encryptedTableName) {
	//echo "$encryptedTableName\t";
	$firstRow = $db->query("SELECT * FROM $encryptedTableName LIMIT 1")->fetch(PDO::FETCH_ASSOC);
	if ($firstRow === false) {
		//echo "\n";
		continue;
	}
	$columnCount = count($firstRow);
	$columnNames = array_keys($firstRow);
	$firstRowValues = array_values($firstRow);

	$matches = [];

	foreach ($normalTables as $normalTableName) {
		if (isset($nameMap[$normalTableName])) {
			continue;
		}
		$normalFirstRow = $db->query("SELECT * FROM $normalTableName LIMIT 1")->fetch(PDO::FETCH_ASSOC);
		if ($normalFirstRow === false) {
			continue;
		}
		$normalColumnCount = count($normalFirstRow);
		if ($columnCount !== $normalColumnCount) {
			continue;
		}
		$normalColumnNames = array_keys($normalFirstRow);
		$normalFirstRowValues = array_values($normalFirstRow);
		$diff = array_diff($firstRowValues, $normalFirstRowValues);
		if (!empty($diff)) {
			continue;
		}

		// check values row by row
		$isDiff = false;
		$encryptedRows = $db->query("SELECT * FROM $encryptedTableName");
		$normalRows = $db->query("SELECT * FROM $normalTableName");
		if ($encryptedRows->rowCount() !== $normalRows->rowCount()) {
			continue;
		}
		while ($encryptedRow = $encryptedRows->fetch(PDO::FETCH_NUM)) {
			$normalRow = $normalRows->fetch(PDO::FETCH_NUM);
			if ($encryptedRow !== $normalRow) {
				$isDiff = true;
				break;
			}
		}
		if ($isDiff) {
			continue;
		}
		//echo "$normalTableName\t";
		$nameMap[$normalTableName] = $encryptedTableName;
		$matches[] = $normalTableName;
		break;
	}
	//echo "\n";

	$columnMap = [];
	for ($i=0; $i<$columnCount; $i++) {
		$columnMap[$normalColumnNames[$i]] = $columnNames[$i];
	}
	$nameMap[$normalTableName] = [
		'table' => $nameMap[$normalTableName],
		'column' => $columnMap,
	];
}

ksort($nameMap);
//print_r($nameMap);
file_put_contents('nameMap.json', json_encode($nameMap, JSON_PRETTY_PRINT));

