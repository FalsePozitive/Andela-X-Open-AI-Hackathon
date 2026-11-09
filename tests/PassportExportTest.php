<?php
declare(strict_types=1);
use Passport\Demo;
use Passport\Input;
use Passport\Media;
use Passport\PassportPdf;
use Passport\Problem;
use Passport\Rules;
use Passport\Watermark;
use PHPUnit\Framework\TestCase;

final class PassportExportTest extends TestCase
{
    private array $config;
    protected function setUp(): void
    {
        $directory=__DIR__.'/.runtime/exports';
        if(!is_dir($directory.'/uploads')){mkdir($directory.'/uploads',0700,true);}
        $this->config=['storage_path'=>$directory,'base_url'=>'https://okwethu.wilmans.co.za'];
    }
    private function story():array
    {
        $story=Demo::payload('Placeholder image passport');
        $source=$this->config['storage_path'].'/fixture.png';$img=imagecreatetruecolor(800,600);imagefill($img,0,0,imagecolorallocate($img,255,210,127));imagepng($img,$source);imagedestroy($img);
        $media=Media::process($source,'image',$this->config['storage_path'].'/uploads');
        $story=array_replace($story,$media,['id'=>'ABCD-EFGH','status'=>'UNVERIFIED','createdAt'=>'2026-11-09T10:00:00Z','mediaType'=>'image']);
        foreach($story['contributors'] as $i=>&$person){$person['id']='person-'.$i;foreach($person['consents'] as &$granted){$granted=['granted'=>$granted,'recordedAt'=>'2026-11-09T10:00:00Z'];}unset($granted);}unset($person);
        $story['shares']=[['contributorId'=>'person-0','bps'=>7000],['contributorId'=>'person-1','bps'=>3000]];
        return $story;
    }
    public function testPercentagesConvertExactlyWithoutFloatingPoint():void
    {
        foreach(['0'=>0,'0.01'=>1,'33.33'=>3333,'33.34'=>3334,'70'=>7000,'100.00'=>10000] as $value=>$expected){self::assertSame($expected,Input::percentage((string)$value));}
        foreach(['100.01','-1','33.333','1e2','NaN',true,null,[],0.1] as $invalid){try{Input::percentage($invalid);self::fail('Invalid percentage accepted');}catch(Problem $e){self::assertSame('INVALID_SPLIT',$e->errorCode);}}
        $shares=[['contributorId'=>'a','bps'=>Input::percentage('33.33')],['contributorId'=>'b','bps'=>Input::percentage('33.33')],['contributorId'=>'c','bps'=>Input::percentage('33.34')]];
        self::assertSame(7,array_sum(Rules::allocate(7,$shares)));
    }
    public function testMarkedImageKeepsOriginalAndProducesARealDerivative():void
    {
        $story=$this->story();$original=Watermark::original($story,$this->config);$hash=hash_file('sha256',$original);
        $marked=Watermark::image($story,$this->config);
        self::assertNotSame($original,$marked);self::assertSame($hash,hash_file('sha256',$original));self::assertSame(IMAGETYPE_PNG,getimagesize($marked)[2]);
        copy($marked,$this->config['storage_path'].'/marked-image.png');
        self::assertSame($marked,Watermark::image($story,$this->config));
        $changed=$this->config;$changed['base_url']='https://other.example';self::assertNotSame($marked,Watermark::image($story,$changed));
    }
    public function testAudioPackageContainsTheUnmodifiedSoundAndQrCover():void
    {
        $story=$this->story();$story['mediaType']='audio';$story['mimeType']='audio/wav';
        $source=Watermark::original($story,$this->config);$wave='RIFF'.pack('V',36+8000).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,8000,1,8).'data'.pack('V',8000).str_repeat(chr(128),8000);file_put_contents($source,$wave);
        $zip=new ZipArchive();self::assertTrue($zip->open(Watermark::audioZip($story,$this->config)));
        self::assertSame($wave,$zip->getFromName('audio-ABCD-EFGH.wav'));self::assertStringStartsWith("\x89PNG",$zip->getFromName('okwethu-qr-cover.png'));self::assertStringContainsString('/p/ABCD-EFGH',$zip->getFromName('READ-ME.txt'));$zip->close();
    }
    public function testPdfSnapshotsIncludeOpenAndRestrictedExports():void
    {
        $story=$this->story();$pdf=PassportPdf::render($story,$this->config);self::assertStringStartsWith('%PDF-',$pdf);file_put_contents($this->config['storage_path'].'/passport-open.pdf',$pdf);
        $story['sensitivity']='RESTRICTED';$story['title']='PRIVATE_TITLE_SENTINEL';$story['textBody']='PRIVATE_BODY_SENTINEL';$story['contextNote']='PRIVATE_CONTEXT_SENTINEL';$story['placeCoarse']='PRIVATE_PLACE_SENTINEL';$story['contributors'][0]['name']='PRIVATE_PERSON_SENTINEL';
        $restricted=PassportPdf::render($story,$this->config);self::assertStringStartsWith('%PDF-',$restricted);file_put_contents($this->config['storage_path'].'/passport-restricted.pdf',$restricted);
        $this->expectException(Problem::class);Watermark::image($story,$this->config);
    }
}
