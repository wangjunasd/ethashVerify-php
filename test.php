<?php
include 'sha3.php';
include 'ethash.php';

$ethasher=new ethash();

$ethasher->getCache(300);

