<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
$config=require dirname(__DIR__,2).'/config/test.php';
if(!str_contains($config['dsn'],'dbname=story_passport_test;')){exit(1);}
$registry=new Passport\Registry(Passport\Database::connect($config),$config);
$registry->decide($argv[1],['status'=>'APPROVED','finalFeeMinor'=>9999,'feeConfirmed'=>true,'note'=>'MOCK concurrency test']);
