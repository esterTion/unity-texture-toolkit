<?php


class LightWeightEncryptor {
  private $swizzleBytes; // byte[] 0x10
  private $Coef = 1; // int 0x18
  private $offset = 0; // int 0x1c
  function __construct($seed, $initSpin, $tableSize)
  {
    $XorShift = new XorShift();
    $XorShift->Init($seed & 0x7fffffff);
    if ($initSpin >= 1) {
      do {
        $XorShift->Next();
        --$initSpin;
      } while ($initSpin);
    }
    $this->swizzleBytes = str_repeat(chr(0), $tableSize);
    $XorShift->MakeSwizzleTable($this->swizzleBytes);
    $this->Coef = ($XorShift->Next() & 0xf) + 3; // $v10 + 8
    $result = $XorShift->Next();
    $this->offset = ($result & 0x1f) + 1; // $v10 + 12
    //print_r($this);
    //echo bin2hex($this->swizzleBytes);
    return $this;
  }
  function Modify(string &$dataBytes, $offset, $count, $streamOffset, $salt) {
    $v13 = 0;
    $v14 = strlen($this->swizzleBytes) - 1;// ((count($this->swizzleBytes) << 32) - 0x100000000) >> 32;
    do {
      $v20 = ($this->offset + ($streamOffset + $offset + $salt + $v13) * $this->Coef) & $v14;
      $dataBytes[$offset + $v13] = $this->swizzleBytes[$v20] ^ $dataBytes[$offset + $v13];
      ++$v13;
    } while ($count != $v13);
  }
  function Transform($inputBytes, $inputOffset, $inputCount) {
    throw new BadMethodCallException();
  }
}
class RijndaelEncryptor {
  private $key;
  private $iv;
  private $isDecrypt;
  function __construct($pw, $salt, bool $isDecrypt)
  {
    $rijnIter = 117;
    $buf = openssl_pbkdf2($pw, $salt, 32, $rijnIter);
    $this->key = substr($buf, 0, 16);
    $this->iv = substr($buf, 16, 16);
    $this->isDecrypt = $isDecrypt;
  }
  function Modify(string &$dataBytes, $offset, $count, $streamOffset, $salt) {
    throw new BadMethodCallException();
  }
  function Transform($inputBytes, $inputOffset, $inputCount) {
    $data = substr($inputBytes, $inputOffset, $inputCount);
    if ($this->isDecrypt) {
      return openssl_decrypt($data, 'AES-128-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv);
    } else {
      return openssl_encrypt($data, 'AES-128-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv);
    }
  }
}

class XorShift {
  private $lockObject; // object 0x10
  private $seed; // uint 0x18
  private $y; //uint 0x1c
  function __construct()
  {
    return $this;
  }
  function Init($seed) {
    $this->seed = $seed;
    $this->y = $seed;
  }
  function Next() {
    $v3 = $this->y ^ (($this->y << 13) & 0xffffffff);
    $v4 = $v3 ^ ($v3 >> 17) ^ (32 * ($v3 ^ ($v3 >> 17))) & 0xffffffff;
    $this->y = $v4;
    return $v4;
  }
  function MakeSwizzleTable(string &$dest) {
    $v5 = strlen($dest);
    if ($v5 >= 1) {
      $v4 = 0;
      do {
        $result = $this->Next();
        $dest[$v4++] = chr(($result >> 3) & 0xff);
      } while ($v5 != $v4);
    }
  }
  static function SwizzleBytes(&$target, $swizzle, $coef, $offset, $start) {
    $count = strlen($target);
    if ($count >= 1) {
      $mask = strlen($swizzle) - 1;
      $k = $count + $start;
      for ($i = 0; $i < $count; ) {
        $k = ($offset + $k * $coef) & 0xffffffff;
        $target[$i] = $target[$i] ^ $swizzle[$k & $mask];
        $i++;
      }
    }
  }
  function Range($min, $max) {
    //
  }
  function NextFloat() {
    //
  }
}

class Encryptor {
  static $encryptors;
}

Encryptor::$encryptors = [
  1001 => new RijndaelEncryptor('Pg97xygbey7aw', '857fesfd', true),
  1002 => new LightWeightEncryptor(1749853741, 11,	512),
  1003 => new LightWeightEncryptor(0xCE2B9A93, 13, 512),
  1004 => new LightWeightEncryptor(874156713, 17,	512),
  1501 => new RijndaelEncryptor('CgU6T5tehiGoZ', '37F741ED', true),
  2001 => new LightWeightEncryptor(0x51C1D53, 17, 512),
  2002 => new LightWeightEncryptor(0xF5F1B55, 13, 512),
  2003 => new LightWeightEncryptor(0x28087953, 11, 512),
];
