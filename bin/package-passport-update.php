<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(1);}
$root=dirname(__DIR__);$source=new ZipArchive();
$release=$argv[1]??'';
if(!is_file($release)||$source->open($release)!==true){throw new RuntimeException('Pass a freshly built production release ZIP.');}
if($source->locateName('vendor/dompdf/dompdf/src/Dompdf.php')===false||$source->locateName('src/PassportPdf.php')===false){throw new RuntimeException('Release lacks the new PDF runtime.');}
$file=$root.'/dist/okwethu-passport-update.zip';$zip=new ZipArchive();
if($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){throw new RuntimeException('Cannot create update.');}
for($i=0;$i<$source->numFiles;$i++){
    $name=$source->getNameIndex($i);
    if(str_contains($name,'..')||str_starts_with($name,'/')){throw new RuntimeException('Unsafe archive path.');}
    if($name==='public/index.php'){
        $entry=str_replace("dirname(__DIR__) . '/src/bootstrap.php'","__DIR__ . '/_passport/src/bootstrap.php'",$source->getFromIndex($i));$zip->addFromString('index.php',$entry);
    }elseif(str_starts_with($name,'public/assets/')){$zip->addFromString(substr($name,7),$source->getFromIndex($i));}
    elseif(preg_match('~^(src|templates|resources|vendor)/~',$name)){$zip->addFromString('_passport/'.$name,$source->getFromIndex($i));}
}
$zip->close();$source->close();$check=new ZipArchive();$check->open($file);
for($i=0;$i<$check->numFiles;$i++){$name=$check->getNameIndex($i);if(str_contains($name,'/config/')||str_contains($name,'/storage/')||str_ends_with($name,'.sql')||str_contains($name,'.htaccess')||str_contains($name,'/phpunit/')){throw new RuntimeException('Unexpected runtime/private file in update.');}}
foreach(['_passport/src/Watermark.php','_passport/src/PassportPdf.php','_passport/resources/okwethu-logo.png','assets/media-export.js','_passport/vendor/autoload.php'] as $required){if($check->locateName($required)===false){throw new RuntimeException('Missing '.$required);}}
echo 'Verified passport/media update with production PDF dependencies; no configuration, SQL, uploads or session data.'.PHP_EOL;$check->close();echo $file.PHP_EOL;
