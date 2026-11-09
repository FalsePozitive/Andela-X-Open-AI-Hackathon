<?php
declare(strict_types=1);
// Overlay for an existing Xneelo shared-folder installation. No runtime data.
if(PHP_SAPI!=='cli'){exit(1);}
$root=dirname(__DIR__);$directory=$root.'/dist';
if(!is_dir($directory)){mkdir($directory,0700,true);}
$zip=new ZipArchive();$file=$directory.'/okwethu-design-update.zip';
if($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){throw new RuntimeException('Cannot create design update.');}
$assets=['app.css','app.js','okwethu-mark.svg','okwethu-logo.svg','okwethu-logo.png'];
foreach($assets as $asset){$path=$root.'/public/assets/'.$asset;if(!is_file($path)){throw new RuntimeException('Missing asset: '.$asset);}$zip->addFile($path,'assets/'.$asset);}
foreach(glob($root.'/templates/*.php')?:[] as $template){$zip->addFile($template,'_passport/templates/'.basename($template));}
foreach(['src/Copy.php','src/Web.php','resources/copy.php'] as $path){$zip->addFile($root.'/'.$path,'_passport/'.$path);}
$entry=(string)file_get_contents($root.'/public/index.php');
$entry=str_replace("dirname(__DIR__) . '/src/bootstrap.php'","__DIR__ . '/_passport/src/bootstrap.php'",$entry);
$zip->addFromString('index.php',$entry);$zip->close();
$check=new ZipArchive();$check->open($file);
for($i=0;$i<$check->numFiles;$i++){
    $name=$check->getNameIndex($i);
    if(str_contains($name,'config/')||str_contains($name,'storage/')||str_contains($name,'database/')||str_ends_with($name,'.sql')||str_contains($name,'.htaccess')){throw new RuntimeException('Unexpected private/runtime file in update.');}
}
if(!str_contains($check->getFromName('index.php'),"__DIR__ . '/_passport/src/bootstrap.php'")){throw new RuntimeException('Invalid shared-folder bootstrap.');}
if(!str_contains($check->getFromName('_passport/src/Copy.php'),"'brand' => 'Okwethu'")){throw new RuntimeException('Brand is missing.');}
echo 'Verified Okwethu design-only update: '.$check->numFiles.' files; no configuration, database or uploaded stories.'.PHP_EOL;$check->close();echo $file.PHP_EOL;
