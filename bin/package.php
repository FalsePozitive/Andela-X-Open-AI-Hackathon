<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {exit(1);}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (!is_file($root . '/public/assets/app.css')) {throw new RuntimeException('Build CSS before packaging.');}
$directory = $root . '/dist';
if (!is_dir($directory)) {mkdir($directory,0700,true);}
$path = $directory . '/story-passport-' . gmdate('Ymd-His') . '.zip';
$zip = new ZipArchive();
if ($zip->open($path,ZipArchive::CREATE|ZipArchive::EXCL) !== true) {throw new RuntimeException('Cannot create release.');}
$folders = ['src','templates','public','database','docs','resources'];
foreach ($folders as $folder) {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) {
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
        if ($relative === 'public/router.php') {continue;}
        $zip->addFile($file->getPathname(),$relative);
    }
}
foreach(['composer.json','composer.lock','config/example.php','bin/console.php','README.md','.htaccess','LICENSE'] as $file) {
    if(is_file($root.'/'.$file)) {$zip->addFile($root.'/'.$file,$file);}
}
// Composer's production autoloader is generated in a separate release staging
// directory. Never copy the development autoloader, which references test tools.
$staging=$directory.'/runtime-'.bin2hex(random_bytes(6));mkdir($staging,0700,true);
copy($root.'/composer.json',$staging.'/composer.json');copy($root.'/composer.lock',$staging.'/composer.lock');
mkdir($staging.'/src');
$composer=getenv('COMPOSER_PHAR');
if(!$composer || !is_file($composer)){ $zip->close();unlink($path);throw new RuntimeException('Set COMPOSER_PHAR to a local Composer PHAR for production dependency installation.'); }
$command=[PHP_BINARY,$composer,'install','--no-dev','--prefer-dist','--no-interaction','--classmap-authoritative','--working-dir='.$staging];
$process=proc_open($command,[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root);
if(!is_resource($process)||proc_close($process)!==0){$zip->close();unlink($path);throw new RuntimeException('Production dependency installation failed.');}
// PSR-4 needs a real source tree when building a classmap. Generate with the
// actual source, then copy the matching relative src layout into the archive.
foreach(glob($root.'/src/*.php')?:[] as $source){copy($source,$staging.'/src/'.basename($source));}
$process=proc_open([PHP_BINARY,$composer,'dump-autoload','--no-dev','--classmap-authoritative','--working-dir='.$staging],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root);
if(!is_resource($process)||proc_close($process)!==0){$zip->close();unlink($path);throw new RuntimeException('Release autoload generation failed.');}
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging.'/vendor',FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){$relative=str_replace('\\','/',substr($file->getPathname(),strlen($staging)+1));$zip->addFile($file->getPathname(),$relative);}
$zip->close();
echo $path.PHP_EOL;
