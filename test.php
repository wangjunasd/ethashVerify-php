<?php
include 'sha3.php';
include 'ethash.php';

$starttime=time();

$ethasher=new ethash();

//$ethasher->getCache(134926);

$result=array(
    'nonce'=>pack('H*','88707be08007acab40'),
    'mixDigest'=>pack('H*','58f759ede17a706c93f13030328bcea40c1d1341fb26f2facd21ceb0dae57017'),
    'header'=>pack('H*','ace141021a4c6567037c6af0145fc9d561b133cbcf99fb776a38f2f8c28f0b7c'),
    'number'=>4056700
    
);


$ethasher->verify($result['number'], $result['header'], $result['mixDigest'], $result['nonce'], 1241794215185927);


echo "cost:".(time()-$starttime)."\r\n";


