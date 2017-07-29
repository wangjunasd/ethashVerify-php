<?php
include 'ethash.php';

$starttime=time();

$ethasher=new ethash();

//$ethasher->getCache(134926);

$result=array(
    'nonce'=>pack('H*','b77683680317d940'),
    'mixDigest'=>pack('H*','285ecd9720aa5d1d49e0f9e2b0ab2d582e9df3472d8695b2ba7a00147dc4b9fc'),
    'header'=>pack('H*','b220fec3161f3c9445150661f611de6d08674ba6980d3b2a2a1dcdc60f936e3f'),
    'number'=>4188176
    
);


$ethasher->verify($result['number'], $result['header'], $result['mixDigest'], $result['nonce'], 1241794215185927);


echo "cost:".(time()-$starttime)."\r\n";



