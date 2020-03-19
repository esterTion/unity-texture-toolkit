<?php

require_once 'UnityAsset.php';
define('SBR_HASH_SEED', 0x33DC7CB65408BDF);

function GeneratePBKDF2Key($password, $salt) {
    return hash_pbkdf2('SHA1', $password, $salt, 1024, 32+16, true);
}
function getAssetUrl(string $path) {
    $path = explode('//', trim($path, '/'));
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