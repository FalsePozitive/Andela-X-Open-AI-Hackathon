<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class PackageTest extends TestCase
{
    public function testProductionArchiveContainsNoRuntimeSecretsOrDevelopmentDependencies():void
    {
        $files=glob(dirname(__DIR__).'/dist/story-passport-*.zip')?:[];
        if(!$files){self::markTestSkipped('Build a production release first.');}
        sort($files);$archive=end($files);$zip=new ZipArchive();self::assertTrue($zip->open($archive));
        foreach(['src/Registry.php','public/index.php','public/.htaccess','resources/copy.php','vendor/autoload.php','config/example.php','database/migrations/001_registry.sql','database/migrations/002_community_tools.sql','src/Accounts.php','src/StoryChanges.php','src/RequestUpdates.php','src/Mailbox.php','templates/tracking.php','vendor/phpmailer/phpmailer/src/PHPMailer.php'] as $required){self::assertNotFalse($zip->locateName($required),$required);}
        for($i=0;$i<$zip->numFiles;$i++) {
            $name=$zip->getNameIndex($i);
            self::assertDoesNotMatchRegularExpression('~(^|/)(local\.php|test\.php|\.env|\.git|node_modules|phpunit|phpstan|\.tools|preview-login\.txt)(/|$)~',$name);
            self::assertFalse(str_starts_with($name,'storage/uploads/'),$name);
        }
        self::assertStringContainsString('https://okwethu.wilmans.co.za',$zip->getFromName('config/example.php'));
        $directory=__DIR__.'/.runtime/release-'.bin2hex(random_bytes(5));mkdir($directory,0700,true);self::assertTrue($zip->extractTo($directory));$zip->close();
        $code='require $argv[1]."/vendor/autoload.php"; if(!class_exists("Passport\\Registry")){exit(2);} $x=Passport\\Rules::allocate(7,[["contributorId"=>"a","bps"=>7000],["contributorId"=>"b","bps"=>3000]]);if(array_sum($x)!==7){exit(3);} $qr=(new Endroid\\QrCode\\Writer\\PngWriter())->write(Endroid\\QrCode\\QrCode::create("https://okwethu.wilmans.co.za"));if(!str_starts_with($qr->getString(),"\x89PNG")){exit(4);}';
        $process=proc_open([PHP_BINARY,'-r',$code,$directory],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process);$output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);self::assertSame(0,proc_close($process),$output);
    }
}
