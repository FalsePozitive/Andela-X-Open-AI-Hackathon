<?php
declare(strict_types=1);
use Passport\Media;
use Passport\Problem;
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase
{
    private string $directory;
    protected function setUp(): void
    {
        $this->directory = __DIR__ . '/.runtime/media-' . bin2hex(random_bytes(6));
        mkdir($this->directory,0700,true);
    }
    public function testImagesAreReencodedAndRenamed(): void
    {
        $source = $this->directory . '/unsafe.php.png';
        $image = imagecreatetruecolor(10,10);
        imagepng($image,$source); imagedestroy($image);
        file_put_contents($source,'PRIVATE_METADATA',FILE_APPEND);
        $result = Media::process($source,'image',$this->directory.'/out');
        self::assertSame('image/webp',$result['mimeType']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}$/',$result['filePath']);
        self::assertStringNotContainsString('PRIVATE_METADATA',(string)file_get_contents($this->directory.'/out/'.$result['filePath']));
        self::assertSame(hash_file('sha256',$this->directory.'/out/'.$result['filePath']),$result['contentHash']);
    }
    public function testForgedMimeIsRejected(): void
    {
        $source=$this->directory.'/forged.jpg'; file_put_contents($source,'<?php echo "unsafe";');
        $this->expectException(Problem::class); Media::process($source,'image',$this->directory.'/out');
    }
    public function testOversizedFileIsRejected(): void
    {
        $source=$this->directory.'/large'; $file=fopen($source,'wb'); ftruncate($file,10*1024*1024+1); fclose($file);
        $this->expectException(Problem::class); Media::process($source,'video',$this->directory.'/out');
    }
}
