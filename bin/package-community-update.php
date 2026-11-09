<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {exit(1);}
$root=dirname(__DIR__);$release=$argv[1]??'';$source=new ZipArchive();
if (!is_file($release) || $source->open($release)!==true) {throw new RuntimeException('Pass a fresh production ZIP.');}
foreach (['src/Accounts.php','src/StoryChanges.php','src/RequestUpdates.php','src/Mailbox.php','vendor/phpmailer/phpmailer/src/PHPMailer.php','database/migrations/002_community_tools.sql'] as $required) {
    if ($source->locateName($required)===false) {throw new RuntimeException('Release missing '.$required);}
}
$file=$root.'/dist/okwethu-community-update.zip';$zip=new ZipArchive();
if ($zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) {throw new RuntimeException('Cannot create update.');}
for ($i=0;$i<$source->numFiles;$i++) {
    $name=$source->getNameIndex($i);
    if (str_contains($name,'..') || str_starts_with($name,'/')) {throw new RuntimeException('Unsafe archive path.');}
    if ($name==='public/index.php') {
        $entry=str_replace("dirname(__DIR__) . '/src/bootstrap.php'","__DIR__ . '/_passport/src/bootstrap.php'",$source->getFromIndex($i));
        $zip->addFromString('index.php',$entry);
    } elseif (str_starts_with($name,'public/assets/')) {
        $zip->addFromString(substr($name,7),$source->getFromIndex($i));
    } elseif (preg_match('~^(src|templates|resources|vendor)/~',$name)) {
        $zip->addFromString('_passport/'.$name,$source->getFromIndex($i));
    }
}
$zip->close();$check=new ZipArchive();$check->open($file);
for ($i=0;$i<$check->numFiles;$i++) {
    $name=$check->getNameIndex($i);
    if (preg_match('~(^|/)(config|storage|tests|phpunit|phpstan|\.git)(/|$)~',$name) || str_ends_with($name,'.sql') || str_contains($name,'.htaccess')) {throw new RuntimeException('Unexpected private/runtime file: '.$name);}
}
foreach (['_passport/src/Accounts.php','_passport/vendor/phpmailer/phpmailer/src/PHPMailer.php','_passport/vendor/autoload.php','assets/app.css','assets/app.js','index.php'] as $required) {if($check->locateName($required)===false){throw new RuntimeException('Missing '.$required);}}
$check->close();
$sql="-- Okwethu community tools upgrade. Import ONCE into the EXISTING application database.\n-- Export a database backup first. Do not import 001 or reset/seed.\nSET time_zone = '+00:00';\n";
$sql.=$source->getFromName('database/migrations/002_community_tools.sql');
$sql.="\nINSERT INTO migrations(version,applied_at) VALUES ('002_community_tools.sql',UTC_TIMESTAMP(6));\n";
file_put_contents($root.'/dist/okwethu-community-upgrade.sql',$sql);
file_put_contents($root.'/dist/OKWETHU-COMMUNITY-UPGRADE.md',$source->getFromName('docs/COMMUNITY-TOOLS.md'));
$source->close();
echo "Verified shared-host update: no secrets, uploads, sessions, SQL or Apache rules in the web ZIP.\n".$file."\n".$root."/dist/okwethu-community-upgrade.sql\n";
