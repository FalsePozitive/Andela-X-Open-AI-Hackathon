<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$config=require dirname(__DIR__).'/config/test.php';
if (PHP_SAPI!=='cli' || !str_contains($config['dsn'],'port=3317;') || !str_contains($config['dsn'],'dbname=story_passport_test;')) {exit(2);}
$registry=new Passport\Registry(Passport\Database::connect($config),$config);
$registry->actor=Passport\Database::run($registry->db,'SELECT id,is_owner FROM accounts WHERE id=? AND active=1',[$argv[2]??''])->fetch()?:[];
usleep(150000);
try {
    (new Passport\StoryChanges($registry))->update($argv[1],['action'=>'credit','preferredCredit'=>$argv[3],'expectedRevision'=>1,'reason'=>'Concurrent edit test.']);
    echo 'SAVED';
} catch (Passport\Problem $e) {echo $e->errorCode;}
