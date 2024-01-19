<?php

if ($argc < 2) {
  echo "php exportTable.php <dump.cs>\n";
  exit;
}
$dumpcs = file_get_contents($argv[1]);

/*preg_match_all("((\[MemoryTable\(\"[^\)]]+\"\)\]\n)?(\[.+\]\n)*public class [^{}]*?\{[^{}]+\[Key\(\d+\)\](((?>[^{}]+)|{(?-2)})*)\})", $dumpcs, $classes);*/
preg_match_all("(// Namespace: ([^\n]*)\n(\[.+\]\n)*(\[MemoryTable\(\"[^\)]]+\"\)\]\n)?(\[.+\]\n)*public class [^{}]*?\{[^{}]+\[Key\(\d+\)\](((?>[^{}]+)|{(?-2)})*)\})", $dumpcs, $classes);

$types = [];
$typesDb = new PDO('sqlite:'.__DIR__.'/types.db');
$typesDb->beginTransaction();
$typesDb->query('DELETE FROM types');
$insert = $typesDb->prepare('INSERT INTO types VALUES (?,?,?)');
$rename = $typesDb->prepare('UPDATE types SET class=? WHERE class=?');

$f = fopen(__DIR__.'/tables.cs', 'w');

preg_match_all("(// Namespace: ([^\n]*)\npublic enum ([^ ]+) [^{]*?\{(((?>[^{}]+)|{(?-2)})*)\})", $dumpcs, $enums);
$typesDb->query('DELETE FROM enums');
$enumInsert = $typesDb->prepare('INSERT INTO enums VALUES (?,?)');
$enumTables = [];

foreach ($enums[0] as $i=>$enum) {
  $namespace = $enums[1][$i];
  $enumName = $enums[2][$i];
  preg_match_all('(public const [^ ]+ ([^ ]+) = (\d+);)', $enum, $enumEntries);
  $enumTable = [''];
  for ($i=0; $i<count($enumEntries[0]); $i++) {
    $enumTable[$enumEntries[2][$i]] = $enumEntries[1][$i];
  }
  if (isset($enumTables[$enumName])) {
    if ($namespace === 'SiriusApi.Shared') {
      $enumTables[$enumName] = $enumTable;
    }
  } else {
    $enumTables[$enumName] = $enumTable;
  }
}
$usedEnumTables = [];

$seenTypes = [];
$typesIdx = [];

foreach ($classes[0] as $i=>$class) {
  $class = preg_replace('(\[CompilerGenerated\][\w\W]+?\n\n)', "\n", $class);
  $class = preg_replace('(public void .ctor\(\) \{ \})', "\n", $class);
  $class = preg_replace('(//.+)', "", $class);
  $class = preg_replace('(/\*[\w\W]+?\*/)', "", $class);
  $class = preg_replace('((?<=\n)	*\n)', "", $class);
  fwrite($f, $class."\n\n");

  $namespace = $classes[1][$i];
  $type = [];
  preg_match('(class ([\w\.]+))', $class, $className);
  $className = $className[1];
  if (isset($seenTypes[$className])) {
    $prev = $seenTypes[$className];
    echo "Duplicate class $className $prev $namespace\n";
    if ($namespace === 'SiriusApi.Shared') {
      $rename->execute([$prev.'.'.$className, $className]);
      $typesIdx[$prev.'.'.$className] = $typesIdx[$className];
      unset($typesIdx[$className]);
    } else {
      $className = $prev.'.'.$className;
    }
  }
  $typesIdx[$className] = count($types);
  $type['class'] = $className;
  preg_match('(\[MemoryTable\(\"([\w]+)\"\)\])', $class, $tableName);
  $type['tableName'] = empty($tableName[1]) ? null : $tableName[1];
  preg_match_all('(\[Key\((\d+)\)\](\n	\[[^\]]+?\])*\n	[^{\n]*? ([^{]+) ([^ {]+) )', $class, $keys);
  $type['keys'] = [];

  preg_match('(class [\w\.]+ : ([\w\.]+))', $class, $classInherit);
  $inherit = empty($classInherit[1]) ? null : $classInherit[1];
  if ($inherit !== null) {
    if (isset($typesIdx[$inherit])) {
      $type['keys'] = array_merge($type['keys'], $types[$typesIdx[$inherit]]['keys']);
    }
  }

  for ($i=0; $i < count($keys[1]); $i++) {
    $typeName = $keys[3][$i];
    $type['keys'][$keys[1][$i]] = [
      'type' => $typeName,
      'name' => $keys[4][$i]
    ];
    if (isset($enumTables[$typeName]) && !isset($usedEnumTables[$typeName])) {
      $usedEnumTables[$typeName] = $enumTables[$typeName];
    }
		$typeName = preg_replace('(^Nullable<(.+)>$)', '$1', $typeName);
    if (isset($enumTables[$typeName]) && !isset($usedEnumTables[$typeName])) {
      $usedEnumTables[$typeName] = $enumTables[$typeName];
    }
  }
  $type['keys'] = $type['keys'] + array_fill(0, max(array_keys($type['keys'])), 0);
  ksort($type['keys']);
  $types[] = $type;

  $type['keys'] = json_encode($type['keys'], JSON_PRETTY_PRINT);
  $insert->execute(array_values($type));
  $seenTypes[$type['class']] = $namespace;
}
foreach ($usedEnumTables as $enumName=>$enumTable) {
  $enumInsert->execute([
    $enumName,
    json_encode($enumTable, JSON_PRETTY_PRINT),
  ]);
}
fclose($f);
file_put_contents(__DIR__.'/types.json', json_encode($types, JSON_PRETTY_PRINT));
$typesDb->commit();
