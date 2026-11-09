<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$config=require dirname(__DIR__,2).'/config/test.php';
if (PHP_SAPI!=='cli' || !str_contains($config['dsn'],'port=3317;') || !str_contains($config['dsn'],'dbname=story_passport_test;')) {exit(2);}
$registry=new Passport\Registry(Passport\Database::connect($config),$config);
echo "READY\n";flush();
try {$registry->decide($argv[1],['status'=>'APPROVED','finalFeeMinor'=>7,'feeConfirmed'=>true]);echo 'APPROVED';}
catch (Passport\Problem $e) {echo $e->errorCode;}
