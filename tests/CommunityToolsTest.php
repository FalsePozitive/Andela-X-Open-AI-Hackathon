<?php
declare(strict_types=1);
use Passport\{Accounts,Catalog,Database,Demo,Mailbox,Problem,Registry,RequestUpdates,Rules,StoryChanges,Watermark};
use PHPUnit\Framework\TestCase;

final class CommunityToolsTest extends TestCase
{
    private Registry $registry;
    private array $config;
    private Accounts $accounts;
    private array $owner;

    protected function setUp(): void
    {
        $this->config=require dirname(__DIR__).'/config/test.php';
        if (!str_contains($this->config['dsn'],'port=3317;') || !str_contains($this->config['dsn'],'dbname=story_passport_test;')) {throw new RuntimeException('Isolated database required.');}
        $this->registry=new Registry(Database::connect($this->config),$this->config);
        $this->accounts=new Accounts($this->registry->db,$this->config);
        $this->owner=$this->accounts->login('steward','Test-only-password-1907');
    }

    private function member(string $role='STEWARD'): array
    {
        $community=$this->accounts->manage($this->owner,['action'=>'community','name'=>'Test collection '.bin2hex(random_bytes(4))])['id'];
        $username='test-'.bin2hex(random_bytes(5));
        $id=$this->accounts->manage($this->owner,['action'=>'account','username'=>$username,'displayName'=>'Fictional test steward','email'=>$username.'@example.com','password'=>'Temporary-test-password-1907','communityId'=>$community,'role'=>$role])['id'];
        $actor=$this->accounts->login($username,'Temporary-test-password-1907');
        return ['community'=>$community,'actor'=>$actor,'id'=>$id];
    }

    private function denied(callable $action, string $code='FORBIDDEN'): void
    {
        try {$action();self::fail('The operation should have been rejected.');} catch (Problem $e) {self::assertSame($code,$e->errorCode);}
    }

    private function request(array $story,bool $email=false): array
    {
        return $this->registry->request($story['id'],['requesterName'=>'Private test requester','requesterContact'=>'requester-'.bin2hex(random_bytes(4)).'@example.com','intendedUse'=>'Commercial booklet','isCommercial'=>true,'emailUpdates'=>$email,'currency'=>'ZAR']);
    }

    public function testCommunityBoundariesCoverAllPrivateRecordsAndWrites(): void
    {
        $member=$this->member();$other=$this->member();
        $payload=Demo::payload();$payload['communityId']=$member['community'];$story=$this->registry->create($payload);
        $r=$this->request($story);$claim=$this->registry->claim($story['id'],['reporterName'=>'Test reporter','reporterContact'=>'reporter@example.com','reason'=>'Review this credit.']);
        $this->registry->decide($r['id'],['status'=>'APPROVED','finalFeeMinor'=>7,'feeConfirmed'=>true]);
        $entry=$this->registry->ledger($story['id'])['entries'][0];
        $this->registry->actor=$other['actor'];
        $this->denied(fn()=>$this->registry->privateStory($story['id']));
        $this->denied(fn()=>$this->registry->decide($r['id'],['status'=>'APPROVED']));
        $this->denied(fn()=>$this->registry->adjust($entry['id'],['amountMinor'=>1,'note'=>'Attempted edit.']));
        $this->denied(fn()=>$this->registry->resolve($claim['id'],['status'=>'RESOLVED']));
        $this->denied(fn()=>(new StoryChanges($this->registry))->history($story['id']));
        $this->denied(fn()=>$this->registry->ledger($story['id']));
        self::assertNotContains($story['id'],array_column($this->registry->stories(true),'id'));
        self::assertNotContains($r['id'],array_column($this->registry->requests(),'id'));
        self::assertNotContains($claim['id'],array_column($this->registry->claims(),'id'));
        self::assertNotContains($entry['id'],array_column($this->registry->ledger()['entries'],'id'));
        $this->denied(fn()=>$this->registry->create($payload));
        $this->registry->actor=$member['actor'];self::assertSame($story['id'],$this->registry->privateStory($story['id'])['id']);
    }

    public function testReviewerReadsButCannotWriteAndRoleChangesApplyImmediately(): void
    {
        $member=$this->member('REVIEWER');$data=Demo::payload();$data['communityId']=$member['community'];$story=$this->registry->create($data);
        $r=$this->request($story);$this->registry->actor=$member['actor'];
        self::assertSame($story['id'],$this->registry->privateStory($story['id'])['id']);
        self::assertFalse($this->registry->canWriteStory($story['id']));
        $this->denied(fn()=>$this->registry->create($data));
        $this->denied(fn()=>$this->registry->consent($story['id'],['updates'=>[['contributorId'=>$story['contributors'][0]['id'],'purpose'=>'PUBLISH','granted'=>false]],'expectedRevision'=>1,'reason'=>'Test']));
        $this->denied(fn()=>$this->registry->decide($r['id'],['status'=>'DECLINED']));
        $this->accounts->manage($this->owner,['action'=>'membership','accountId'=>$member['id'],'communityId'=>$member['community'],'role'=>'STEWARD']);
        self::assertTrue($this->registry->canWriteStory($story['id']));
        $this->registry->status($story['id'],['status'=>'AUTHOR_REVIEWED','expectedRevision'=>1,'reason'=>'Declared review completed.']);
        self::assertSame(2,$this->registry->story($story['id'])['revision']);
        $this->accounts->manage($this->owner,['action'=>'membership','accountId'=>$member['id'],'communityId'=>$member['community'],'role'=>'REMOVE']);
        $this->denied(fn()=>$this->registry->privateStory($story['id']));
    }

    public function testEditingRetainsVersionsAndRejectsStaleOrInvalidUpdates(): void
    {
        $story=$this->registry->create(Demo::payload());$this->registry->actor=$this->owner;$changes=new StoryChanges($this->registry);
        $payload=['action'=>'details','title'=>'Corrected placeholder title','language'=>'English','placeCoarse'=>'','contextNote'=>'Corrected fictional context.','textBody'=>'Corrected placeholder text.','expectedRevision'=>1,'reason'=>'The contributor supplied a correction.'];
        $changes->update($story['id'],$payload);
        $new=$this->registry->story($story['id']);self::assertSame(2,$new['revision']);self::assertSame(hash('sha256',$payload['textBody']),$new['contentHash']);
        self::assertSame($story['title'],$changes->version($story['id'],1)['title']);
        self::assertCount(2,$changes->history($story['id']));
        $this->denied(fn()=>$changes->update($story['id'],$payload),'STALE_RECORD');
        $invalid=['action'=>'terms','expectedRevision'=>2,'reason'=>'Invalid shares','termsConfirmed'=>true,'sharesConfirmed'=>true,'preset'=>'FREE_WITH_CREDIT','contactEmail'=>'new@example.com','shares'=>[['contributorId'=>$story['contributors'][0]['id'],'percentage'=>'100']]];
        $this->denied(fn()=>$changes->update($story['id'],$invalid),'INVALID_SPLIT');
        self::assertSame($story['license'],$this->registry->story($story['id'])['license']);
        self::assertSame(2,$this->registry->story($story['id'])['revision']);
    }

    public function testConsentWithdrawalAndPausePreserveApprovalAndBlockLaterUse(): void
    {
        $story=$this->registry->create(Demo::payload());$r=$this->request($story);
        $approve=['status'=>'APPROVED','finalFeeMinor'=>7,'feeConfirmed'=>true];$this->registry->decide($r['id'],$approve);
        $snapshot=Database::run($this->registry->db,'SELECT approval_snapshot FROM permission_requests WHERE id=?',[$r['id']])->fetchColumn();
        $pending=$this->request($story);$this->registry->actor=$this->owner;
        $this->registry->consent($story['id'],['updates'=>[['contributorId'=>$story['contributors'][0]['id'],'purpose'=>'PUBLISH','granted'=>false]],'expectedRevision'=>1,'reason'=>'Contributor withdrew publication consent.']);
        $hidden=$this->registry->story($story['id']);self::assertFalse(Rules::isPublic($hidden));self::assertArrayNotHasKey('title',Rules::publicStory($hidden));
        $this->denied(fn()=>$this->registry->decide($pending['id'],$approve),'CONSENT_BLOCKED');
        self::assertSame($snapshot,Database::run($this->registry->db,'SELECT approval_snapshot FROM permission_requests WHERE id=?',[$r['id']])->fetchColumn());
        self::assertSame(7,$this->registry->ledger($story['id'])['totals']['ZAR']);
        (new StoryChanges($this->registry))->update($story['id'],['action'=>'sharing','sharingState'=>'PAUSED','sensitivity'=>'OPEN','expectedRevision'=>2,'reason'=>'Pause while the community discusses sharing.']);
        $this->registry->consent($story['id'],['updates'=>[['contributorId'=>$story['contributors'][0]['id'],'purpose'=>'PUBLISH','granted'=>true]],'expectedRevision'=>3,'reason'=>'Contributor granted publication consent again.']);
        self::assertFalse(Rules::isPublic($this->registry->story($story['id'])));
        $this->denied(fn()=>$this->request($story),'SHARING_PAUSED');
    }

    public function testCounterReplyRequiresTheCurrentOfferAndFinalFee(): void
    {
        $story=$this->registry->create(Demo::payload());$r=$this->request($story);$token=basename($r['trackingUrl']);$updates=new RequestUpdates($this->registry->db,$this->config);
        $this->registry->decide($r['id'],['status'=>'COUNTERED','counterFeeMinor'=>120,'note'=>'Proposed demo fee.']);
        $updates->reply($token,['response'=>'ACCEPTED','counterVersion'=>1,'note'=>'Agreed for the stated use.']);
        self::assertSame('ACCEPTED',$updates->get($token)['counter_response']);self::assertSame([],$this->registry->ledger($story['id'])['entries']);
        $this->denied(fn()=>$updates->reply($token,['response'=>'DECLINED','counterVersion'=>1]),'STALE_RECORD');
        $this->denied(fn()=>$this->registry->decide($r['id'],['status'=>'APPROVED','finalFeeMinor'=>121,'feeConfirmed'=>true]),'FEE_CHANGED');
        $this->registry->decide($r['id'],['status'=>'COUNTERED','counterFeeMinor'=>130,'note'=>'Revised proposed demo fee.']);
        $this->denied(fn()=>$updates->reply($token,['response'=>'ACCEPTED','counterVersion'=>1]),'STALE_RECORD');
        $updates->reply($token,['response'=>'ACCEPTED','counterVersion'=>2]);
        $this->registry->decide($r['id'],['status'=>'APPROVED','finalFeeMinor'=>130,'feeConfirmed'=>true]);
        self::assertSame(130,$this->registry->ledger($story['id'])['totals']['ZAR']);
        self::assertArrayNotHasKey('approval_snapshot',$updates->get($token));
        self::assertSame(6,count($updates->get($token)['events']));
        $this->denied(fn()=>$updates->get(str_repeat('a',64)),'NOT_FOUND');
        Database::run($this->registry->db,'UPDATE request_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE token_hash=?',[hash('sha256',$token)]);
        $this->denied(fn()=>$updates->get($token),'NOT_FOUND');
    }

    public function testEmailOptInQueuesOnlyPrivateUpdatesAndSpoolDoesNotSendMail(): void
    {
        $story=$this->registry->create(Demo::payload());$without=$this->request($story);
        self::assertSame(0,(int)Database::run($this->registry->db,'SELECT COUNT(*) FROM mail_outbox WHERE request_id=?',[$without['id']])->fetchColumn());
        $r=$this->request($story,true);$job=Database::run($this->registry->db,'SELECT * FROM mail_outbox WHERE request_id=?',[$r['id']])->fetch();
        self::assertSame('PENDING',$job['state']);self::assertSame('disabled',$r['emailDelivery']);
        self::assertStringNotContainsString($story['title'],$job['body']);
        $config=$this->config;$config['mail']=['transport'=>'spool','from'=>'steward@okwethu.wilmans.co.za'];
        $mail=new Mailbox($this->registry->db,$config);self::assertTrue($mail->deliver($job['id']));self::assertFalse($mail->deliver($job['id']));
        $sent=Database::run($this->registry->db,'SELECT * FROM mail_outbox WHERE id=?',[$job['id']])->fetch();
        self::assertSame('SENT',$sent['state']);self::assertSame('',$sent['body']);self::assertSame(1,(int)$sent['attempts']);
        self::assertFileExists($this->config['storage_path'].'/mail-spool/'.$job['id'].'.json');
    }

    public function testPasswordsAndDisabledAccountsInvalidateSessionsWithoutConfigFallback(): void
    {
        $member=$this->member();$user=$member['actor'];
        $_SESSION=['accountId'=>$user['id'],'authVersion'=>(int)$user['auth_version'],'steward'=>true];
        self::assertSame($user['id'],$this->accounts->current()['id']);
        $this->accounts->changePassword($user,['currentPassword'=>'Temporary-test-password-1907','password'=>'Changed-test-password-1907']);
        self::assertNull($this->accounts->current());
        $this->denied(fn()=>$this->accounts->login($user['username'],'Temporary-test-password-1907'),'LOGIN');
        $new=$this->accounts->login($user['username'],'Changed-test-password-1907');
        $_SESSION=['accountId'=>$new['id'],'authVersion'=>(int)$new['auth_version']];
        $this->accounts->manage($this->owner,['action'=>'active','accountId'=>$new['id'],'active'=>false]);self::assertNull($this->accounts->current());
        $_SESSION=['steward'=>true];self::assertNull($this->accounts->current());
        $config=$this->config;$config['steward_name']='fake-legacy';$config['steward_password_hash']=password_hash('Fake-legacy-password-1907',PASSWORD_DEFAULT);
        $this->denied(fn()=>(new Accounts($this->registry->db,$config))->login('fake-legacy','Fake-legacy-password-1907'),'LOGIN');
    }

    public function testCommunityAdminCannotGrantAccessOutsideTheirCollection(): void
    {
        $admin=$this->member('ADMIN');$other=$this->member();
        $this->denied(fn()=>$this->accounts->manage($admin['actor'],['action'=>'membership','accountId'=>$other['id'],'communityId'=>$other['community'],'role'=>'ADMIN']));
        $this->denied(fn()=>$this->accounts->manage($admin['actor'],['action'=>'membership','accountId'=>$other['id'],'communityId'=>$admin['community'],'role'=>'ADMIN']));
        $this->denied(fn()=>$this->accounts->manage($admin['actor'],['action'=>'active','accountId'=>$other['id'],'active'=>false]));
        $this->denied(fn()=>$this->accounts->manage($admin['actor'],['action'=>'community','name'=>'Unauthorised collection']));
        $team=$this->accounts->team($admin['actor']);self::assertNotContains($other['id'],array_column($team['accounts'],'id'));
        $new=$this->accounts->manage($admin['actor'],['action'=>'account','username'=>'admin-created-'.bin2hex(random_bytes(4)),'displayName'=>'Test reviewer','email'=>'reviewer@example.com','password'=>'Temporary-test-password-1907','communityId'=>$admin['community'],'role'=>'REVIEWER']);
        self::assertContains($new['id'],array_column($this->accounts->team($admin['actor'])['accounts'],'id'));
    }

    public function testPublicSearchCannotFindHiddenFieldsAndCreditIsSafe(): void
    {
        $data=Demo::payload('Secret-only-title-1907');$data['contextNote']='Secret-only-context-1907';$data['sensitivity']='RESTRICTED';$story=$this->registry->create($data);$public=Rules::publicStory($story);
        self::assertSame(0,Catalog::search([$public],['q'=>'Secret-only-title-1907'])['total']);
        self::assertSame(0,Catalog::search([$public],['language'=>'English'])['total']);
        self::assertSame(1,Catalog::search([$public],['q'=>$story['id']])['total']);
        self::assertStringNotContainsString('Secret-only',Rules::credit($story,$this->config['base_url']));
        $story['sensitivity']='OPEN';$story['preferredCredit']='Credit the named test contributors.';
        self::assertStringContainsString('Credit the named test contributors.',Rules::credit($story,$this->config['base_url']));
        self::assertStringContainsString('/p/'.$story['id'],Rules::credit($story,$this->config['base_url']));
        $story['sharingState']='PAUSED';self::assertFalse(Rules::isPublic($story));
        $this->denied(fn()=>Watermark::original($story,$this->config),'NOT_FOUND');
    }

    public function testSimultaneousEditsKeepOneWinningVersion(): void
    {
        $story=$this->registry->create(Demo::payload());$children=[];
        foreach (['First test credit','Second test credit'] as $credit) {
            $process=proc_open([PHP_BINARY,__DIR__.'/concurrent-edit.php',$story['id'],$this->owner['id'],$credit],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            self::assertIsResource($process);$children[]=[$process,$pipes];
        }
        $results=[];
        foreach ($children as [$process,$pipes]) {
            $results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error);
        }
        sort($results);self::assertSame(['SAVED','STALE_RECORD'],$results);
        self::assertSame(2,$this->registry->story($story['id'])['revision']);
        self::assertCount(2,(new StoryChanges($this->registry))->history($story['id']));
    }

    public function testApprovalWaitingForWithdrawalReadsConsentAfterTheLock(): void
    {
        $story=$this->registry->create(Demo::payload());$request=$this->request($story);$db=$this->registry->db;
        $db->beginTransaction();Database::run($db,'SELECT id FROM stories WHERE id=? FOR UPDATE',[$story['id']]);
        Database::run($db,"INSERT INTO consents(contributor_id,purpose,granted,recorded_at) VALUES(?,'COMMERCIAL',0,UTC_TIMESTAMP(6))",[$story['contributors'][0]['id']]);
        $process=proc_open([PHP_BINARY,__DIR__.'/workers/approve-after-withdrawal.php',$request['id']],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);
        try {self::assertSame("READY\n",fgets($pipes[1]));usleep(250000);$db->commit();}
        finally {if($db->inTransaction()){$db->rollBack();}}
        $result=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$error);
        self::assertSame('CONSENT_BLOCKED',$result);self::assertSame([],$this->registry->ledger($story['id'])['entries']);
    }
}
