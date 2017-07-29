<?php
include 'ethash.php';

$starttime=time();

$ethasher=new ethash();

//$ethasher->getCache(134926);

$result=array(
    'nonce'=>pack('H*','4c7bee700ff3a6e1'),
    'mixDigest'=>pack('H*','abefdef554d24afada3960e78b809a5d51f6ffa068a52f392a314fcebb6dd132'),
    'header'=>pack('H*','747eafbc7e3a343ab390c1942711a8285b40ce2e6417d25f4aa2e34c2226160c'),
    'number'=>4090000
    
);


$ethasher->verify($result['number'], $result['header'], $result['mixDigest'], $result['nonce'], 1241794215185927);


echo "cost:".(time()-$starttime)."\r\n";



