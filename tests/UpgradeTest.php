<?php
declare(strict_types=1);
use Passport\{Database,Registry};
use PHPUnit\Framework\TestCase;

final class UpgradeTest extends TestCase
{
    public function testUpgradePreservesAnExistingRegistrationAndLedger(): void
    {
        $config=require dirname(__DIR__).'/config/test.php';
        if (!str_contains($config['dsn'],'port=3317;') || !str_contains($config['dsn'],'dbname=story_passport_test;')) {throw new RuntimeException('Isolated database required.');}
        $connection=Database::connect($config);$name='story_passport_upgrade_test_'.bin2hex(random_bytes(5));
        $connection->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
        try {
            $config['dsn']=str_replace('dbname=story_passport_test;','dbname='.$name.';',$config['dsn']);$db=Database::connect($config);
            foreach (preg_split('/;\s*(?:\r?\n|$)/',(string)file_get_contents(dirname(__DIR__).'/database/migrations/001_registry.sql')) as $sql) {if(trim($sql)!==''){$db->exec($sql);}}
            $db->exec('CREATE TABLE migrations(version VARCHAR(100) PRIMARY KEY,applied_at DATETIME(6) NOT NULL) ENGINE=InnoDB');
            Database::run($db,"INSERT INTO migrations VALUES('001_registry.sql',UTC_TIMESTAMP(6))");
            $person=str_repeat('a',32);$request=str_repeat('b',32);$entry=str_repeat('c',32);
            Database::run($db,"INSERT INTO creators VALUES(?,'PERSON','Legacy fictional creator','','English',UTC_TIMESTAMP(6))",[$person]);
            Database::run($db,"INSERT INTO stories(id,creator_id,title,media_type,text_body,language,place_coarse,context_note,sensitivity,content_hash,created_at) VALUES('ABCD-EFGH',?,'Legacy placeholder','text','Retained placeholder text.','English','','Legacy fictional context.','OPEN',?,UTC_TIMESTAMP(6))",[$person,hash('sha256','Retained placeholder text.')]);
            Database::run($db,"INSERT INTO contributors VALUES(?,'ABCD-EFGH','Legacy fictional contributor','Narrator',0)",[$person]);
            Database::run($db,"INSERT INTO licenses VALUES('ABCD-EFGH','ALL_BY_PERMISSION',0,'legacy@example.com','Legacy terms.')");
            Database::run($db,"INSERT INTO split_shares VALUES('ABCD-EFGH',?,10000)",[$person]);
            Database::run($db,"INSERT INTO consents(contributor_id,purpose,granted,recorded_at) VALUES(?,'PUBLISH',1,UTC_TIMESTAMP(6))",[$person]);
            Database::run($db,"INSERT INTO permission_requests(id,story_id,requester_name,requester_contact,intended_use,is_commercial,is_ai_training,currency,status,approval_snapshot,created_at) VALUES(?,'ABCD-EFGH','Legacy fictional requester','requester@example.com','Demo booklet',1,0,'ZAR','APPROVED','{\"legacy\":true}',UTC_TIMESTAMP(6))",[$request]);
            Database::run($db,"INSERT INTO ledger_entries(id,story_id,permission_req_id,contributor_id,amount_minor,currency,kind,payment_key,note,created_at) VALUES(?,'ABCD-EFGH',?,?,17,'ZAR','MOCK_PAYMENT',?,'Legacy demo allocation.',UTC_TIMESTAMP(6))",[$entry,$request,$person,$request.':'.$person]);
            $old=$db->query('SELECT * FROM stories')->fetch();$ledger=$db->query('SELECT * FROM ledger_entries')->fetchAll();$approval=$db->query('SELECT approval_snapshot FROM permission_requests')->fetchColumn();
            Database::migrate($db);Database::migrate($db);
            $new=$db->query('SELECT * FROM stories')->fetch();
            foreach ($old as $key=>$value) {self::assertSame($value,$new[$key],$key);}
            self::assertSame($ledger,$db->query('SELECT * FROM ledger_entries')->fetchAll());
            self::assertSame($approval,$db->query('SELECT approval_snapshot FROM permission_requests')->fetchColumn());
            $registry=new Registry($db,$config);self::assertSame(1,$registry->story('ABCD-EFGH')['revision']);
            self::assertSame('Existing story collection',$registry->story('ABCD-EFGH')['communityName']);
            self::assertSame(17,$registry->ledger('ABCD-EFGH')['totals']['ZAR']);
            self::assertSame(0,(int)$db->query('SELECT COUNT(*) FROM accounts')->fetchColumn());
        } finally {
            // Only the fresh, randomly named database created above is removed.
            if (!preg_match('/^story_passport_upgrade_test_[a-f0-9]{10}$/',$name)) {throw new RuntimeException('Unsafe temporary database name.');}
            $connection->exec('DROP DATABASE `'.$name.'`');
        }
    }
}
