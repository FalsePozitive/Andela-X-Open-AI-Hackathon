<?php
declare(strict_types=1);
namespace Passport;

final class StoryChanges
{
    public function __construct(private readonly Registry $registry) {}

    public function begin(string $id, array $data): array
    {
        Database::run($this->registry->db,'SELECT id FROM stories WHERE id=? FOR UPDATE',[$id]);
        $story=$this->registry->privateStory($id,true);
        if ($this->registry->actor !== null && (!is_int($data['expectedRevision']??null) || $data['expectedRevision']!==$story['revision'])) {
            throw new Problem('STALE_RECORD','This story changed since you opened it. Reload before saving.',409);
        }
        $this->capture($id,'Baseline retained before the first update after this upgrade.');
        return $story;
    }

    public function capture(string $id, string $reason): void
    {
        $story=$this->registry->story($id);
        Database::run($this->registry->db,'INSERT IGNORE INTO story_revisions VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[$id,$story['revision'],json_encode($story,JSON_THROW_ON_ERROR),$this->registry->actor['id']??null,$reason]);
    }

    public function finish(string $id, array $data, string $fallback, bool $reset = true): void
    {
        $reason=$this->registry->actor===null ? ($data['reason']??$fallback) : Input::text($data,'reason',2000);
        Database::run($this->registry->db,'UPDATE stories SET revision=revision+1,updated_at=UTC_TIMESTAMP(6)' . ($reset ? ",status='UNVERIFIED'" : '') . ' WHERE id=?',[$id]);
        $this->capture($id,$reason);
    }

    public function history(string $id): array
    {
        $story=$this->registry->privateStory($id);
        $rows=Database::run($this->registry->db,'SELECT r.revision,r.reason,r.created_at,a.display_name AS actor FROM story_revisions r LEFT JOIN accounts a ON a.id=r.actor_id WHERE story_id=? ORDER BY revision DESC',[$id])->fetchAll();
        return $rows ?: [['revision'=>$story['revision'],'reason'=>'Current legacy record. Earlier edits cannot be reconstructed.','created_at'=>$story['createdAt'],'actor'=>null]];
    }

    public function version(string $id, int $revision): array
    {
        $this->registry->privateStory($id);
        $row=Database::run($this->registry->db,'SELECT snapshot FROM story_revisions WHERE story_id=? AND revision=?',[$id,$revision])->fetch();
        if (!$row) {throw new Problem('NOT_FOUND','Saved revision not found.',404);}
        return json_decode($row['snapshot'],true,32,JSON_THROW_ON_ERROR);
    }

    public function update(string $id, array $data): array
    {
        $db=$this->registry->db;
        $db->beginTransaction();
        try {
            $story=$this->begin($id,$data);
            $action=Input::choice($data,'action',['details','sharing','terms','credit','community']);
            if ($action==='sharing') {
                $state=Input::choice($data,'sharingState',['ACTIVE','PAUSED','WITHDRAWN']);
                $sensitivity=Input::choice($data,'sensitivity',['OPEN','RESTRICTED']);
                Database::run($db,'UPDATE stories SET sharing_state=?,sensitivity=? WHERE id=?',[$state,$sensitivity,$id]);
            } elseif ($action==='credit') {
                Database::run($db,'UPDATE stories SET preferred_credit=? WHERE id=?',[Input::text($data,'preferredCredit',2000,false),$id]);
            } elseif ($action==='community') {
                (new Accounts($db,[]))->owner($this->registry->actor??[]);
                $community=Input::text($data,'communityId',32);
                $this->registry->authorizeCommunity($community,true);
                Database::run($db,'UPDATE stories SET community_id=? WHERE id=?',[$community,$id]);
                Database::run($db,'UPDATE mail_outbox o JOIN permission_requests r ON r.id=o.request_id SET o.community_id=? WHERE r.story_id=?',[$community,$id]);
            } elseif ($action==='details') {
                $text=$story['mediaType']==='text' ? Input::text($data,'textBody',100000) : $story['textBody'];
                Database::run($db,'UPDATE stories SET title=?,language=?,place_coarse=?,context_note=?,text_body=?,content_hash=? WHERE id=?',[
                    Input::text($data,'title'),Input::text($data,'language',100),Input::text($data,'placeCoarse',100,false),Input::text($data,'contextNote',4000),$text,
                    $story['mediaType']==='text' ? hash('sha256',$text) : $story['contentHash'],$id]);
            } else {
                if (!Input::bool($data,'termsConfirmed') || !Input::bool($data,'sharesConfirmed')) {throw new Problem('CONFIRMATION','Confirm the new declared terms and shares.');}
                $shares=$data['shares']??[];
                if (!is_array($shares) || !array_is_list($shares)) {throw new Problem('INVALID_SPLIT','Provide the share percentages.');}
                $converted=[];
                foreach ($shares as $s) {
                    if (!is_array($s)) {throw new Problem('INVALID_SPLIT','Invalid contributor share.');}
                    $converted[]=['contributorId'=>Input::text($s,'contributorId',32),'bps'=>Input::percentage($s['percentage']??null)];
                }
                Rules::split($converted);
                $actual=array_column($story['contributors'],'id');$sent=array_column($converted,'contributorId');sort($actual);sort($sent);
                if ($actual!==$sent) {throw new Problem('INVALID_SPLIT','Include every existing contributor exactly once.');}
                Database::run($db,'UPDATE licenses SET preset=?,ai_training_allowed=?,contact_email=?,notes=? WHERE story_id=?',[
                    Input::choice($data,'preset',Rules::PRESETS),(int)Input::bool($data,'aiTrainingAllowed'),Input::email($data,'contactEmail'),Input::text($data,'notes',2000,false),$id]);
                foreach ($converted as $s) {Database::run($db,'UPDATE split_shares SET bps=? WHERE story_id=? AND contributor_id=?',[$s['bps'],$id,$s['contributorId']]);}
            }
            $this->finish($id,$data,'Story updated.');
            $db->commit();
            return ['saved'=>true,'revision'=>$story['revision']+1];
        } catch (\Throwable $e) {$db->rollBack();throw $e;}
    }
}
