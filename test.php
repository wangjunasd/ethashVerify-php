<?php
include 'ethash.php';

$starttime=time();

$ethasher=new ethash();

$result=array(
    'nonce'=>pack('H*','54a566e0077828b2'),
    'mixDigest'=>pack('H*','b00dcb2a50747c7c8f3a672d7926f1b304c2492cc4a3b757264ca47d662a924f'),
    'header'=>pack('H*','1c95fb225d7c9aa61ef7718cf12bdf663d26954ec1d3710263af1dfe5724cf85'),
    'number'=>4038176
    
);


$result=$ethasher->verify($result['number'], $result['header'], $result['mixDigest'], $result['nonce'], '0xffff','0xffffffff');

var_dump($result);

echo "cost:".(time()-$starttime)."\r\n";