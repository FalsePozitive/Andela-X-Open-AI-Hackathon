<?php
declare(strict_types=1);
// Offline shared-hosting repackager. No installer or setup route is exposed.
if (PHP_SAPI !== 'cli') {exit(1);}
$root=dirname(__DIR__);
$release=$argv[1]??'';
if(!is_file($release)){fwrite(STDERR,"Usage: php bin/package-shared.php release.zip [demo-export-directory]\n");exit(1);}
$source=new ZipArchive();
if($source->open($release)!==true){throw new RuntimeException('Cannot open production release.');}
$stamp=gmdate('Ymd-His');$output=$root.'/dist/xneelo-shared-'.$stamp;
if(!is_dir($output)){mkdir($output,0700,true);}
$password=bin2hex(random_bytes(16));
$config=require $root.'/config/example.php';
$config['dsn']='mysql:host=REPLACE_DB_HOST;dbname=REPLACE_DB_NAME;charset=utf8mb4';
$config['db_user']='REPLACE_DB_USER';$config['db_password']='REPLACE_DB_PASSWORD';
$config['steward_password_hash']=password_hash($password,PASSWORD_DEFAULT);
// This expression must be evaluated on the hosting account, not this computer.
$configuration="<?php\ndeclare(strict_types=1);\nreturn ".var_export($config,true).";\n";
$configuration=str_replace(var_export($config['storage_path'],true),"dirname(__DIR__) . '/storage'",$configuration);
$destination=new ZipArchive();$webZip=$output.'/upload-to-subdomain.zip';
if($destination->open($webZip,ZipArchive::CREATE|ZipArchive::EXCL)!==true){throw new RuntimeException('Cannot create shared-hosting package.');}
for($i=0;$i<$source->numFiles;$i++){
    $name=$source->getNameIndex($i);
    if(str_contains($name,'..') || str_starts_with($name,'/')){throw new RuntimeException('Unsafe archive path.');}
    if($name==='public/index.php'){
        $entry=$source->getFromIndex($i);
        $entry=str_replace("dirname(__DIR__) . '/src/bootstrap.php'","__DIR__ . '/_passport/src/bootstrap.php'",$entry);
        $destination->addFromString('index.php',$entry);
    }elseif(str_starts_with($name,'public/assets/')){
        $destination->addFromString(substr($name,7),$source->getFromIndex($i));
    }elseif(preg_match('~^(src|templates|vendor|resources)/~',$name)){
        $destination->addFromString('_passport/'.$name,$source->getFromIndex($i));
    }
}
$source->close();
$destination->addFromString('_passport/config/local.php',$configuration);
$destination->addFromString('_passport/.htaccess',"Require all denied\n");
$destination->addFromString('_passport/access-check.txt',"PRIVATE FOLDER CHECK: this text must never be publicly readable.\n");
$destination->addEmptyDir('_passport/storage/uploads');
$destination->addFromString('.htaccess',"Options -Indexes -MultiViews\nDirectoryIndex index.php\nRequire all granted\nRewriteEngine On\nRewriteRule ^_passport(?:/|$) - [F,L,NC]\nRewriteCond %{HTTPS} !=on\nRewriteRule ^ ".$config['base_url']."%{REQUEST_URI} [R=308,L,NE]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [QSA,L]\n<FilesMatch \"(^\\.|\\.(zip|sql|log|bak|env|ini)$)\">\n Require all denied\n</FilesMatch>\n");
$schema=(string)file_get_contents($root.'/database/migrations/001_registry.sql');
$schema.="\nCREATE TABLE IF NOT EXISTS migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME(6) NOT NULL) ENGINE=InnoDB;\nINSERT INTO migrations VALUES ('001_registry.sql',UTC_TIMESTAMP(6));\n";
$seedDir=$argv[2]??null;
if($seedDir!==null){
    if(!is_file($seedDir.'/demo-data.sql')){throw new RuntimeException('Demo export is missing.');}
    $schema.="\nSTART TRANSACTION;\n".file_get_contents($seedDir.'/demo-data.sql')."\nCOMMIT;\n";
    foreach(glob($seedDir.'/uploads/*')?:[] as $media){
        if(!preg_match('/^[a-f0-9]{48}$/',basename($media))){throw new RuntimeException('Unexpected demo media filename.');}
        $destination->addFile($media,'_passport/storage/uploads/'.basename($media));
    }
}
$destination->close();
file_put_contents($output.'/import-into-empty-database.sql',$schema);
file_put_contents($output.'/STEWARD-LOGIN-KEEP-ON-YOUR-COMPUTER.txt',"Website: https://okwethu.wilmans.co.za/login\nUsername: steward\nPassword: ".$password."\n\nKeep this file on your computer. Do not upload it to the website or commit it.\nThis login matches only the upload-to-subdomain.zip generated alongside it.\n");
copy($root.'/docs/DEPLOYMENT-SHARED.md',$output.'/READ-FIRST.md');
echo $output.PHP_EOL;
