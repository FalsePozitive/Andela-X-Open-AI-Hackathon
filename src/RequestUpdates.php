<?php
declare(strict_types=1);
namespace Passport;

final class RequestUpdates
{
    public function __construct(private readonly \PDO $db, private readonly array $config) {}

    public function link(string $id): string
    {
        $token=bin2hex(random_bytes(32));
        Database::run($this->db,'INSERT INTO request_tokens VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))',[hash('sha256',$token),$id]);
        return rtrim($this->config['base_url'],'/').'/r/'.$token;
    }

    public function event(string $id, string $event, string $note = ''): void
    {
        Database::run($this->db,'INSERT INTO request_events(request_id,event,note,created_at) VALUES(?,?,?,UTC_TIMESTAMP(6))',[$id,$event,$note]);
        $request=Database::run($this->db,'SELECT r.*,s.community_id FROM permission_requests r JOIN stories s ON s.id=r.story_id WHERE r.id=?',[$id])->fetch();
        if ($request['email_updates']) {
            (new Mailbox($this->db,$this->config))->queue($id,$request['community_id'],$request['requester_contact'],'Okwethu: request update',"Your permission request has an update: ".$event.".\n\nView the private record:\n".$this->link($id)."\n\nKeep this link private. It expires after 30 days. Declared terms do not prove ownership. All amounts are demo records; no real money moves.");
        }
    }

    public function get(string $token, bool $lock = false): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/',$token)) {throw new Problem('NOT_FOUND','This private link is invalid or expired.',404);}
        $r=Database::run($this->db,'SELECT r.* FROM request_tokens t JOIN permission_requests r ON r.id=t.request_id WHERE t.token_hash=? AND t.expires_at>UTC_TIMESTAMP()'.($lock?' FOR UPDATE':''),[hash('sha256',$token)])->fetch();
        if (!$r) {throw new Problem('NOT_FOUND','This private link is invalid or expired.',404);}
        unset($r['approval_snapshot']);
        $r['events']=Database::run($this->db,'SELECT event,note,created_at FROM request_events WHERE request_id=? ORDER BY id',[$r['id']])->fetchAll();
        return $r;
    }

    public function reply(string $token, array $data): array
    {
        $response=Input::choice($data,'response',['ACCEPTED','DECLINED']);
        $note=Input::text($data,'note',2000,false);
        $this->db->beginTransaction();
        try {
            $r=$this->get($token,true);
            if ($r['status']!=='COUNTERED' || $r['counter_response']!==null || ($data['counterVersion']??null)!==(int)$r['counter_version']) {
                throw new Problem('STALE_RECORD','This counteroffer has changed or already has a response. Reload this page.',409);
            }
            Database::run($this->db,"UPDATE permission_requests SET counter_response=?,counter_reply=?,status=IF(?='DECLINED','DECLINED',status) WHERE id=?",[$response,$note,$response,$r['id']]);
            $this->event($r['id'],'COUNTER_'.$response,$note);
            $this->db->commit();
            return ['saved'=>true];
        } catch (\Throwable $e) {$this->db->rollBack();throw $e;}
    }

    public function dispatch(string $requestId): void
    {
        // A bounded post-commit attempt; failures remain available to the steward.
        try {
            $job=Database::run($this->db,"SELECT id FROM mail_outbox WHERE request_id=? AND state='PENDING' ORDER BY created_at DESC LIMIT 1",[$requestId])->fetch();
            if ($job) {(new Mailbox($this->db,$this->config))->deliver($job['id']);}
        } catch (\Throwable) { /* A notification failure must not undo a committed decision. */ }
    }
}
