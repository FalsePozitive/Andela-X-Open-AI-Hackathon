<?php
declare(strict_types=1);
use Passport\Database;
use Passport\Registry;
use Passport\Demo;
use Passport\Problem;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    private Registry $registry;
    protected function setUp(): void
    {
        $file = dirname(__DIR__) . '/config/test.php';
        if (!is_file($file)) {self::markTestSkipped('Set up the isolated MySQL test database first.');}
        $config = require $file;
        if (!str_contains($config['dsn'], 'dbname=story_passport_test;')) {throw new RuntimeException('Tests require the isolated test database.');}
        $this->registry = new Registry(Database::connect($config),$config);
    }
    private function request(array $story, string $currency = 'ZAR'): array
    {
        return $this->registry->request($story['id'],['requesterName'=>'Demo Requester','requesterContact'=>'requester@example.com','intendedUse'=>'Commercial booklet','isCommercial'=>true,'isAiTraining'=>false,'proposedFeeMinor'=>9999,'currency'=>$currency]);
    }
    private function approval(): array
    {
        return ['status'=>'APPROVED','finalFeeMinor'=>9999,'feeConfirmed'=>true,'note'=>'MOCK test'];
    }
    public function testRegistrationIsAtomicAndHasContentHash(): void
    {
        $count = (int)$this->registry->db->query('SELECT COUNT(*) FROM stories')->fetchColumn();
        $data=Demo::payload();$data['contributors'][0]['bps']=7001;
        try {$this->registry->create($data);self::fail('Invalid split accepted');} catch(Problem $e) {self::assertSame('INVALID_SPLIT',$e->errorCode);}
        self::assertSame($count,(int)$this->registry->db->query('SELECT COUNT(*) FROM stories')->fetchColumn());
        $data=Demo::payload();$story=$this->registry->create($data);
        self::assertSame(hash('sha256',$data['textBody']),$story['contentHash']);
        self::assertCount(2,$story['contributors']);self::assertCount(5,$story['contributors'][0]['consents']);
    }
    public function testBlockedApprovalConsentEditAndIdempotence(): void
    {
        $story=$this->registry->create(Demo::payload('Blocked demo',false));$request=$this->request($story);
        try {$this->registry->decide($request['id'],$this->approval());self::fail('Approval bypassed consent');} catch(Problem $e) {self::assertSame(409,$e->status);}
        self::assertSame([],$this->registry->ledger($story['id'])['entries']);
        $person=array_values(array_filter($story['contributors'],static fn(array $p):bool=>!$p['consents']['COMMERCIAL']['granted']))[0];
        $this->registry->consent($story['id'],['updates'=>[['contributorId'=>$person['id'],'purpose'=>'COMMERCIAL','granted'=>true]]]);
        $this->registry->decide($request['id'],$this->approval());$this->registry->decide($request['id'],$this->approval());
        $ledger=$this->registry->ledger($story['id']);self::assertCount(2,$ledger['entries']);self::assertSame(9999,$ledger['totals']['ZAR']);
        $this->registry->consent($story['id'],['updates'=>[['contributorId'=>$person['id'],'purpose'=>'COMMERCIAL','granted'=>false]]]);
        $raw=Database::run($this->registry->db,'SELECT approval_snapshot FROM permission_requests WHERE id=?',[$request['id']])->fetchColumn();
        $snapshot=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        self::assertTrue(array_values(array_filter($snapshot['consents'],static fn(array $p):bool=>$p['id']===$person['id']))[0]['consents']['COMMERCIAL']['granted']);
        self::assertSame(9999,$this->registry->ledger($story['id'])['totals']['ZAR']);
    }
    public function testCounterAndDeclineDoNotCreatePayments(): void
    {
        $story=$this->registry->create(Demo::payload());$request=$this->request($story);
        $this->registry->decide($request['id'],['status'=>'COUNTERED','counterFeeMinor'=>12000,'note'=>'Please confirm with requester.']);
        self::assertSame([],$this->registry->ledger($story['id'])['entries']);
        $this->registry->decide($request['id'],['status'=>'DECLINED','note'=>'Not agreed.']);
        self::assertSame([],$this->registry->ledger($story['id'])['entries']);
        $this->expectException(Problem::class);$this->registry->decide($request['id'],$this->approval());
    }
    public function testCorrectionsAppendAndCurrenciesStaySeparate(): void
    {
        $story=$this->registry->create(Demo::payload());
        foreach(['ZAR','USD'] as $currency) {$request=$this->request($story,$currency);$this->registry->decide($request['id'],$this->approval());}
        $ledger=$this->registry->ledger($story['id']);self::assertSame(['USD'=>9999,'ZAR'=>9999],array_replace(['USD'=>0,'ZAR'=>0],$ledger['totals']));
        $entry=$ledger['entries'][0];$this->registry->adjust($entry['id'],['amountMinor'=>-$entry['amount_minor'],'note'=>'MOCK reversal']);
        $updated=$this->registry->ledger($story['id']);self::assertCount(5,$updated['entries']);
        $original=array_values(array_filter($updated['entries'],static fn(array $e):bool=>$e['id']===$entry['id']))[0];self::assertSame($entry,$original);
    }
    public function testConcurrentApprovalsCreateOneAllocation(): void
    {
        $story=$this->registry->create(Demo::payload());$request=$this->request($story);$processes=[];
        for($i=0;$i<2;$i++) {
            $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/workers/approve.php',$request['id']],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
            self::assertIsResource($process);$processes[]=[$process,$pipes];
        }
        foreach($processes as [$process,$pipes]) {$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$out.$err);}
        $ledger=$this->registry->ledger($story['id']);self::assertCount(2,$ledger['entries']);self::assertSame(9999,$ledger['totals']['ZAR']);
    }
    public function testNegativeFeeAndForeignContributorAreRejected(): void
    {
        $story=$this->registry->create(Demo::payload());
        try {$this->registry->request($story['id'],['requesterName'=>'Demo','requesterContact'=>'demo@example.com','intendedUse'=>'Book','proposedFeeMinor'=>-1]);self::fail('Negative fee accepted');}catch(Problem $e){self::assertSame('INVALID_AMOUNT',$e->errorCode);}
        $this->expectException(Problem::class);$this->registry->consent($story['id'],['updates'=>[['contributorId'=>'foreign','purpose'=>'COMMERCIAL','granted'=>true]]]);
    }
}
