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
    private function login():void
    {
        $curl=curl_init($this->url.'/login');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_COOKIEJAR=>$this->cookies,CURLOPT_COOKIEFILE=>$this->cookies,CURLOPT_POSTFIELDS=>http_build_query(['_csrf'=>$this->csrf,'username'=>'steward','password'=>'Test-only-password-1907'])]);
        curl_exec($curl);self::assertSame(303,curl_getinfo($curl,CURLINFO_RESPONSE_CODE));curl_close($curl);unset($curl);
        $this->csrf=json_decode($this->call('GET','/api/session')['body'],true,512,JSON_THROW_ON_ERROR)['csrf'];
    }
    public function testRestrictedStoryLeaksNothingInJsonHtmlOrMedia():void
    {
        $data=Demo::payload('SECRET_TITLE_1907');$data['sensitivity']='RESTRICTED';$data['textBody']='SECRET_CONTENT_1907';$data['contextNote']='SECRET_CONTEXT_1907';$data['placeCoarse']='SECRET_LOCATION_1907';
        $story=$this->registry->create($data);
        foreach(['/api/stories/'.$story['id'],'/p/'.$story['id'],'/'] as $path){$response=$this->call('GET',$path);self::assertSame(200,$response['status']);self::assertStringContainsString('no-store',$response['headers']);foreach(['SECRET_TITLE_1907','SECRET_CONTENT_1907','SECRET_CONTEXT_1907','SECRET_LOCATION_1907','filePath','contentHash'] as $secret){self::assertStringNotContainsString($secret,$response['body']);}}
        self::assertSame(404,$this->call('GET','/media/'.$story['id'])['status']);
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
}
