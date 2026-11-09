<?php
declare(strict_types=1);
namespace Passport;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailbox
{
    public function __construct(private readonly \PDO $db, private readonly array $config) {}

    public function queue(string $requestId, string $community, string $recipient, string $subject, string $body): string
    {
        $id=bin2hex(random_bytes(16));
        Database::run($this->db,'INSERT INTO mail_outbox(id,request_id,community_id,recipient,subject,body,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(6))',[$id,$requestId,$community,$recipient,$subject,$body]);
        return $id;
    }

    public function enabled(): bool {return ($this->config['mail']['transport']??'disabled')!=='disabled';}

    public function deliver(string $id): bool
    {
        if (!$this->enabled()) {return false;}
        $claim=Database::run($this->db,"UPDATE mail_outbox SET state='SENDING',lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),attempts=attempts+1 WHERE id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 29 DAY) AND attempts<5 AND (state IN ('PENDING','FAILED') OR (state='SENDING' AND lease_until<UTC_TIMESTAMP()))",[$id]);
        if (!$claim->rowCount()) {return false;}
        $job=Database::run($this->db,'SELECT * FROM mail_outbox WHERE id=?',[$id])->fetch();
        try {
            $settings=$this->config['mail'];
            if (($settings['transport']??'')==='spool' && ($this->config['environment']??'production')!=='production') {
                $dir=$this->config['storage_path'].'/mail-spool';
                if (!is_dir($dir)) {mkdir($dir,0700,true);}
                file_put_contents($dir.'/'.$id.'.json',json_encode($job,JSON_THROW_ON_ERROR));
            } else {
                $mail=new PHPMailer(true);$mail->isSMTP();$mail->SMTPDebug=0;
                if (($settings['transport']??'')!=='smtp') {throw new \RuntimeException('Invalid mail transport.');}
                $mail->Host=$settings['host'];$mail->Port=(int)($settings['port']??465);$mail->SMTPAuth=true;
                $mail->Username=$settings['username'];$mail->Password=$settings['password'];
                $mail->SMTPSecure=($settings['encryption']??'smtps')==='starttls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $mail->Timeout=8;$mail->getSMTPInstance()->Timelimit=10;$mail->CharSet='UTF-8';
                $mail->setFrom($settings['from']??'steward@okwethu.wilmans.co.za','Okwethu');$mail->addAddress($job['recipient']);
                $mail->Subject=$job['subject'];$mail->Body=$job['body'];
                $domain=parse_url($this->config['base_url'],PHP_URL_HOST);
                $mail->MessageID='<okwethu-'.$id.'@'.$domain.'>';
                $mail->send();
            }
            Database::run($this->db,"UPDATE mail_outbox SET state='SENT',sent_at=UTC_TIMESTAMP(6),lease_until=NULL,body='' WHERE id=?",[$id]);
            return true;
        } catch (\Throwable) {
            // Do not expose SMTP credentials, recipients or private tracking links in logs.
            Database::run($this->db,"UPDATE mail_outbox SET state='FAILED',lease_until=NULL WHERE id=?",[$id]);
            return false;
        }
    }

    public function pending(array $communities): array
    {
        if (!$communities) {return [];}
        return Database::run($this->db,'SELECT id,community_id,recipient,subject,state,attempts,created_at,sent_at,created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 29 DAY) AS fresh FROM mail_outbox WHERE community_id IN ('.implode(',',array_fill(0,count($communities),'?')).') ORDER BY created_at DESC LIMIT 100',$communities)->fetchAll();
    }
}
