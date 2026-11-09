<?php
declare(strict_types=1);
use Passport\Database;
use Passport\Demo;
use Passport\Registry;
use PHPUnit\Framework\TestCase;

final class HttpTest extends TestCase
{
    private string $url;
    private string $cookies;
    private string $csrf;
    private Registry $registry;
    protected function setUp(): void
    {
        $url=getenv('PASSPORT_TEST_URL');
        if(!$url){self::markTestSkipped('Set PASSPORT_TEST_URL to the dedicated test server.');}
        if(!in_array($url,['http://127.0.0.1:8088','http://localhost:8088'],true)){throw new RuntimeException('HTTP tests are limited to the isolated loopback test server.');}
        $config=require dirname(__DIR__).'/config/test.php';
        $this->registry=new Registry(Database::connect($config),$config);
        $this->url=$url;$this->cookies=tempnam(__DIR__.'/.runtime','cookies-');
        $response=$this->call('GET','/api/session');$this->csrf=json_decode($response['body'],true,512,JSON_THROW_ON_ERROR)['csrf'];
    }
    protected function tearDown():void {if(isset($this->cookies)&&is_file($this->cookies)){unlink($this->cookies);}}
    private function call(string $method,string $path,?array $data=null,bool $token=true):array
    {
        $curl=curl_init($this->url.$path);
        $headers=[];
        if($token && isset($this->csrf)){$headers[]='X-CSRF-Token: '.$this->csrf;}
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEJAR=>$this->cookies,CURLOPT_COOKIEFILE=>$this->cookies,CURLOPT_TIMEOUT=>15]);
        if($data!==null){$headers[]='Content-Type: application/json';curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data,JSON_THROW_ON_ERROR));}
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);$raw=curl_exec($curl);
        if(!is_string($raw)){throw new RuntimeException(curl_error($curl));}
        $length=curl_getinfo($curl,CURLINFO_HEADER_SIZE);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        return ['status'=>$status,'headers'=>substr($raw,0,$length),'body'=>substr($raw,$length)];
    }
    private function login(string $username='steward',string $password='Test-only-password-1907'):void
    {
        $curl=curl_init($this->url.'/login');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_COOKIEJAR=>$this->cookies,CURLOPT_COOKIEFILE=>$this->cookies,CURLOPT_POSTFIELDS=>http_build_query(['_csrf'=>$this->csrf,'username'=>$username,'password'=>$password])]);
        curl_exec($curl);self::assertSame(303,curl_getinfo($curl,CURLINFO_RESPONSE_CODE));curl_close($curl);unset($curl);
        $this->csrf=json_decode($this->call('GET','/api/session')['body'],true,512,JSON_THROW_ON_ERROR)['csrf'];
    }
    public function testRestrictedStoryLeaksNothingInJsonHtmlOrMedia():void
    {
        $data=Demo::payload('SECRET_TITLE_1907');$data['sensitivity']='RESTRICTED';$data['textBody']='SECRET_CONTENT_1907';$data['contextNote']='SECRET_CONTEXT_1907';$data['placeCoarse']='SECRET_LOCATION_1907';
        $story=$this->registry->create($data);
        foreach(['/api/stories/'.$story['id'],'/p/'.$story['id'],'/'] as $path){$response=$this->call('GET',$path);self::assertSame(200,$response['status']);self::assertStringContainsString('no-store',$response['headers']);foreach(['SECRET_TITLE_1907','SECRET_CONTENT_1907','SECRET_CONTEXT_1907','SECRET_LOCATION_1907','filePath','contentHash'] as $secret){self::assertStringNotContainsString($secret,$response['body']);}}
        self::assertSame(404,$this->call('GET','/media/'.$story['id'])['status']);
        self::assertSame(404,$this->call('GET','/api/stories/'.$story['id'].'/cover')['status']);
        self::assertSame(404,$this->call('GET','/api/stories/'.$story['id'].'/download')['status']);
        $pdf=$this->call('GET','/api/stories/'.$story['id'].'/pdf');self::assertSame(200,$pdf['status']);self::assertStringStartsWith('%PDF-',$pdf['body']);file_put_contents(__DIR__.'/.runtime/exports/http-restricted.pdf',$pdf['body']);
        self::assertSame(404,$this->call('GET','/storage/uploads/anything')['status']);
        self::assertSame(404,$this->call('GET','/config/local.php')['status']);
    }
    public function testPrivateEndpointsRequireStewardAndWritesRequireCsrf():void
    {
        foreach(['/api/requests','/api/ledger','/api/claims'] as $path){self::assertSame(401,$this->call('GET',$path)['status']);}
        self::assertSame(403,$this->call('POST','/api/stories',Demo::payload(),false)['status']);
        self::assertSame(401,$this->call('POST','/api/stories',Demo::payload())['status']);
        $this->login();self::assertSame(200,$this->call('GET','/api/ledger')['status']);
    }
    public function testVisitorCanRequestAndReportWithoutExposingContacts():void
    {
        $story=$this->registry->create(Demo::payload());
        $response=$this->call('POST','/api/stories/'.$story['id'].'/requests',['requesterName'=>'Private Requester','requesterContact'=>'private-1907@example.com','intendedUse'=>'Training a model','isAiTraining'=>false]);
        self::assertSame(201,$response['status']);self::assertTrue(json_decode($response['body'],true)['aiFlag']);self::assertStringNotContainsString('private-1907',$response['body']);
        $response=$this->call('POST','/api/stories/'.$story['id'].'/claims',['reporterName'=>'Private Reporter','reporterContact'=>'private-1907@example.com','reason'=>'Please review the credit']);self::assertSame(201,$response['status']);
        self::assertStringNotContainsString('private-1907',$this->call('GET','/p/'.$story['id'])['body']);
    }
    public function testQrIsPngAndXssIsEscaped():void
    {
        $data=Demo::payload('<script>alert("unsafe")</script>');$story=$this->registry->create($data);
        $response=$this->call('GET','/p/'.$story['id']);self::assertSame(200,$response['status']);self::assertStringNotContainsString('<script>alert(',$response['body']);self::assertStringContainsString('&lt;script&gt;',$response['body']);
        $qr=$this->call('GET','/api/stories/'.$story['id'].'/qr?download=1');self::assertSame(200,$qr['status']);self::assertStringStartsWith("\x89PNG\r\n\x1a\n",$qr['body']);self::assertStringContainsString('attachment; filename=',$qr['headers']);
    }
    public function testStewardPagesRenderAndStoryCanBeCreatedViaApi():void
    {
        $this->login();$created=$this->call('POST','/api/stories',Demo::payload());self::assertSame(201,$created['status']);$id=json_decode($created['body'],true)['id'];
        foreach(['/dashboard','/dashboard/new','/dashboard/requests','/dashboard/claims','/dashboard/stories/'.$id,'/ledger'] as $path){$page=$this->call('GET',$path);self::assertSame(200,$page['status'],$path);if($path==='/dashboard/new'){file_put_contents(__DIR__.'/.runtime/wizard.html',$page['body']);}}
        $malformed=$this->call('POST','/api/stories',['title'=>[]]);self::assertSame(422,$malformed['status']);self::assertSame('VALIDATION',json_decode($malformed['body'],true)['error']['code']);
    }

    public function testPercentageRegistrationAndMarkedImageDownload():void
    {
        $this->login();$data=Demo::payload();$data['mediaType']='image';
        foreach($data['contributors'] as &$person){$person['percentage']=(string)($person['bps']/100);unset($person['bps']);}unset($person);
        $source=__DIR__.'/.runtime/upload.png';$image=imagecreatetruecolor(800,600);imagefill($image,0,0,imagecolorallocate($image,255,210,127));imagepng($image,$source);imagedestroy($image);
        $curl=curl_init($this->url.'/api/stories');curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_COOKIEJAR=>$this->cookies,CURLOPT_COOKIEFILE=>$this->cookies,CURLOPT_HTTPHEADER=>['X-CSRF-Token: '.$this->csrf],CURLOPT_POSTFIELDS=>['payload'=>json_encode($data,JSON_THROW_ON_ERROR),'media'=>new CURLFile($source,'image/png','fixture.png')]]);
        $body=curl_exec($curl);self::assertSame(201,curl_getinfo($curl,CURLINFO_RESPONSE_CODE),is_string($body)?$body:'No response');curl_close($curl);unset($curl);$id=json_decode($body,true,512,JSON_THROW_ON_ERROR)['id'];
        $story=$this->registry->story($id);self::assertSame([7000,3000],array_column($story['shares'],'bps'));
        foreach(['/media/'.$id,'/api/stories/'.$id.'/download'] as $url){$image=$this->call('GET',$url);self::assertSame(200,$image['status']);self::assertStringStartsWith("\x89PNG",$image['body']);self::assertStringContainsString('image/png',$image['headers']);}
    }

    public function testRequestTrackingAndCounterReplyThroughHttp():void
    {
        $story=$this->registry->create(Demo::payload());
        $response=$this->call('POST','/api/stories/'.$story['id'].'/requests',['requesterName'=>'Test requester','requesterContact'=>'test-requester@example.com','intendedUse'=>'Commercial booklet','isCommercial'=>true]);
        self::assertSame(201,$response['status']);$request=json_decode($response['body'],true);$token=basename($request['trackingUrl']);
        $this->registry->decide($request['id'],['status'=>'COUNTERED','counterFeeMinor'=>7,'note'=>'Proposed test fee.']);
        self::assertSame(200,$this->call('GET','/r/'.$token)['status']);
        self::assertSame(403,$this->call('POST','/api/tracking/'.$token,['response'=>'ACCEPTED','counterVersion'=>1],false)['status']);
        self::assertSame(200,$this->call('POST','/api/tracking/'.$token,['response'=>'ACCEPTED','counterVersion'=>1])['status']);
        self::assertSame(409,$this->call('POST','/api/tracking/'.$token,['response'=>'ACCEPTED','counterVersion'=>1])['status']);
        self::assertSame(404,$this->call('GET','/r/'.str_repeat('0',64))['status']);
        self::assertSame(401,$this->call('POST','/api/requests/'.$request['id'].'/link',[])['status']);
    }
    public function testWithdrawnPublicationStopsCachedContentAcrossPublicRoutes():void
    {
        $data=Demo::payload('WITHDRAWN_TITLE_1907');$data['textBody']='WITHDRAWN_BODY_1907';$story=$this->registry->create($data);
        self::assertStringContainsString('WITHDRAWN_TITLE_1907',$this->call('GET','/p/'.$story['id'])['body']);
        self::assertSame(200,$this->call('GET','/api/stories/'.$story['id'].'/pdf')['status']);
        $this->registry->consent($story['id'],['updates'=>[['contributorId'=>$story['contributors'][0]['id'],'purpose'=>'PUBLISH','granted'=>false]]]);
        foreach(['/api/stories/'.$story['id'],'/p/'.$story['id'],'/?q=WITHDRAWN_TITLE_1907'] as $path){$response=$this->call('GET',$path);self::assertSame(200,$response['status']);self::assertStringNotContainsString('WITHDRAWN_BODY_1907',$response['body']);if(!str_contains($path,'?'))self::assertStringNotContainsString('WITHDRAWN_TITLE_1907',$response['body']);self::assertStringContainsString('no-store',$response['headers']);}
        self::assertSame(404,$this->call('GET','/api/stories/'.$story['id'].'/credit')['status']);
        $pdf=$this->call('GET','/api/stories/'.$story['id'].'/pdf');self::assertSame(200,$pdf['status']);file_put_contents(__DIR__.'/.runtime/exports/withdrawn.pdf',$pdf['body']);
    }
    public function testCommunityAccessChecksAlsoProtectDirectHttpLinks():void
    {
        $config=require dirname(__DIR__).'/config/test.php';$accounts=new Passport\Accounts($this->registry->db,$config);$owner=$accounts->login('steward','Test-only-password-1907');
        $community=$accounts->manage($owner,['action'=>'community','name'=>'HTTP test collection'])['id'];$name='http-'.bin2hex(random_bytes(4));
        $accounts->manage($owner,['action'=>'account','username'=>$name,'displayName'=>'HTTP test reviewer','email'=>'reviewer@example.com','password'=>'Temporary-test-password-1907','communityId'=>$community,'role'=>'REVIEWER']);
        $unrelated=$this->registry->create(Demo::payload('Private other-collection title.'));$payload=Demo::payload('Assigned review record.');$payload['communityId']=$community;$assigned=$this->registry->create($payload);
        $this->login($name,'Temporary-test-password-1907');
        foreach(['/api/steward/stories/'.$unrelated['id'],'/api/steward/stories/'.$unrelated['id'].'/history','/dashboard/stories/'.$unrelated['id'],'/dashboard/stories/'.$unrelated['id'].'/history/1','/api/ledger?storyId='.$unrelated['id']] as $path){self::assertSame(403,$this->call('GET',$path)['status'],$path);}
        $page=$this->call('GET','/dashboard/stories/'.$assigned['id']);self::assertSame(200,$page['status']);self::assertStringNotContainsString('data-consent=',$page['body']);
        self::assertSame(403,$this->call('PATCH','/api/stories/'.$assigned['id'].'/edit',['action'=>'credit','expectedRevision'=>1,'reason'=>'Attempted edit','preferredCredit'=>'Unapproved credit'])['status']);
        self::assertSame(403,$this->call('GET','/dashboard/team')['status']);
        $desk=$this->call('GET','/dashboard');self::assertStringNotContainsString('Private other-collection title.',$desk['body']);self::assertStringContainsString('Assigned review record.',$desk['body']);
    }
}
