<?php
include 'sha3.php';
include 'ethash.php';

$starttime=time();

$ethasher=new ethash();

$ethasher->getCache(134926);

echo "cost:".(time()-$starttime)."\r\n";
