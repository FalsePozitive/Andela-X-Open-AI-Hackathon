<?php
declare(strict_types=1);
namespace Passport;

final class Registry
{
    /** Null is reserved for trusted CLI jobs. Web requests always set an actor. */
    public ?array $actor = null;

    public function authorizeCommunity(string $id, bool $write = false): void
    {
        if ($this->actor !== null) {(new Accounts($this->db,$this->config))->community($this->actor,$id,$write);}
    }

    public function privateStory(string $id, bool $write = false): array
    {
        $story=$this->story($id);$this->authorizeCommunity($story['communityId'],$write);return $story;
    }

    public function canWriteStory(string $id): bool
    {
        if ($this->actor===null) {return true;}
        $s=$this->story($id);
        return in_array($s['communityId'],array_column((new Accounts($this->db,$this->config))->communities($this->actor,true),'id'),true);
    }

    private function visibleToActor(string $id): bool
    {
        if ($this->actor===null) {return true;}
        $row=$this->query('SELECT community_id FROM stories WHERE id=?',[$id])->fetch();
        return $row && in_array($row['community_id'],array_column((new Accounts($this->db,$this->config))->communities($this->actor),'id'),true);
    }

    public function __construct(public readonly \PDO $db, private readonly array $config) {}

    private function query(string $sql, array $params = []): \PDOStatement
    {
        return Database::run($this->db, $sql, $params);
    }

    public function create(array $data, ?array $file = null, ?string $creatorId = null): array
    {
        $community=$data['communityId']??Accounts::DEFAULT_COMMUNITY;
        if (!is_string($community)) {throw new Problem('VALIDATION','Choose a community.');}
        $this->authorizeCommunity($community,true);
        if ($this->actor!==null && $creatorId!==null) {throw new Problem('FORBIDDEN','Existing creators cannot be linked through this interface.',403);}
        $title = Input::text($data, 'title');
        $type = Input::choice($data, 'mediaType', ['text', 'image', 'audio', 'video']);
        $language = Input::text($data, 'language', 100);
        $place = Input::text($data, 'placeCoarse', 100, false);
        $context = Input::text($data, 'contextNote', 4000);
        $sensitivity = Input::choice($data, 'sensitivity', ['OPEN', 'RESTRICTED']);
        $text = Input::text($data, 'textBody', 100000, $type === 'text');
        $creator = $data['creator'] ?? [];
        $license = $data['license'] ?? [];
        if (!is_array($creator) || !is_array($license)) {
            throw new Problem('VALIDATION', 'Creator and license must be objects.');
        }
        $creatorType = Input::choice($creator, 'type', ['PERSON', 'COMMUNITY']);
        $creatorName = Input::text($creator, 'name');
        $representative = Input::text($creator, 'representativeName', 200, $creatorType === 'COMMUNITY');
        $preset = Input::choice($license, 'preset', Rules::PRESETS);
        $ai = Input::bool($license, 'aiTrainingAllowed');
        $contact = Input::email($license, 'contactEmail');
        $notes = Input::text($license, 'notes', 2000, false);
        if (!Input::bool($data, 'termsConfirmed') || !Input::bool($data, 'sharesConfirmed')) {
            throw new Problem('CONFIRMATION', 'Explicitly confirm the declared terms and revenue shares.');
        }
        $people = $data['contributors'] ?? [];
        if (!is_array($people) || !array_is_list($people) || count($people) < 1 || count($people) > 50) {
            throw new Problem('VALIDATION', 'Add between one and fifty contributors.');
        }
        $contributors = [];
        $shares = [];
        // A random story-scoped prefix plus ordered suffix retains contributor
        // order as the stable ID tie-break used by the browser's split preview.
        $contributorPrefix = bin2hex(random_bytes(12));
        foreach ($people as $person) {
            if (!is_array($person)) {
                throw new Problem('VALIDATION', 'Invalid contributor.');
            }
            $id = $contributorPrefix . str_pad(dechex(count($contributors)), 8, '0', STR_PAD_LEFT);
            $consents = $person['consents'] ?? [];
            if (!is_array($consents)) {
                throw new Problem('VALIDATION', 'Consent must be recorded per purpose.');
            }
            $values = [];
            foreach (Rules::PURPOSES as $purpose) {
                $values[$purpose] = Input::bool($consents, $purpose);
            }
            $contributors[] = ['id' => $id, 'name' => Input::text($person, 'name'), 'role' => Input::text($person, 'role', 100), 'fund' => Input::bool($person, 'isCommunityFund'), 'consents' => $values];
            $shareBps = array_key_exists('percentage', $person) ? Input::percentage($person['percentage']) : ($person['bps'] ?? null);
            if (array_key_exists('percentage', $person) && array_key_exists('bps', $person) && $person['bps'] !== $shareBps) {
                throw new Problem('INVALID_SPLIT', 'The percentage and legacy share value disagree.');
            }
            $shares[] = ['contributorId' => $id, 'bps' => $shareBps];
        }
        Rules::split($shares);
        $media = null;
        if ($type !== 'text') {
            $media = Media::store($file ?? [], $type, $this->config['storage_path'] . '/uploads');
        }
        $this->db->beginTransaction();
        try {
            if ($creatorId === null) {
                $creatorId = bin2hex(random_bytes(16));
                $this->query('INSERT INTO creators VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))', [$creatorId, $creatorType, $creatorName, $representative, $language]);
            } elseif (!$this->query('SELECT id FROM creators WHERE id=?', [$creatorId])->fetch()) {
                throw new Problem('NOT_FOUND', 'Creator not found.', 404);
            }
            // A DB unique key is authoritative; retry only an actual ID collision.
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $id = Rules::storyId();
                try {
                    $this->query('INSERT INTO stories(id,creator_id,title,media_type,text_body,file_path,mime_type,language,place_coarse,context_note,sensitivity,content_hash,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))', [$id, $creatorId, $title, $type, $type === 'text' ? $text : null, $media['filePath'] ?? null, $media['mimeType'] ?? null, $language, $place, $context, $sensitivity, $media['contentHash'] ?? hash('sha256', $text)]);
                    break;
                } catch (\PDOException $e) {
                    if (($e->errorInfo[1] ?? 0) !== 1062 || $attempt === 9) {
                        throw $e;
                    }
                }
            }
            $this->query('UPDATE stories SET community_id=? WHERE id=?',[$community,$id]);
            foreach ($contributors as $person) {
                $this->query('INSERT INTO contributors VALUES(?,?,?,?,?)', [$person['id'], $id, $person['name'], $person['role'], (int) $person['fund']]);
                foreach ($person['consents'] as $purpose => $granted) {
                    $this->query('INSERT INTO consents(contributor_id,purpose,granted,recorded_at) VALUES(?,?,?,UTC_TIMESTAMP(6))', [$person['id'], $purpose, (int) $granted]);
                }
            }
            $this->query('INSERT INTO licenses VALUES(?,?,?,?,?)', [$id, $preset, (int) $ai, $contact, $notes]);
            foreach ($shares as $share) {
                $this->query('INSERT INTO split_shares VALUES(?,?,?)', [$id, $share['contributorId'], $share['bps']]);
            }
            (new StoryChanges($this))->capture($id,'Initial registration claim.');
            $this->db->commit();
            return $this->story($id);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($media) {
                unlink($this->config['storage_path'] . '/uploads/' . $media['filePath']);
            }
            throw $e;
        }
    }

    public function story(string $id): array
    {
        $r = $this->query('SELECT s.*, c.name AS creator_name,c.type AS creator_type,c.representative_name,g.name AS community_name FROM stories s JOIN creators c ON c.id=s.creator_id JOIN communities g ON g.id=s.community_id WHERE s.id=?', [$id])->fetch();
        if (!$r) {
            throw new Problem('NOT_FOUND', 'Story not found.', 404);
        }
        $l = $this->query('SELECT * FROM licenses WHERE story_id=?', [$id])->fetch();
        $people = [];
        foreach ($this->query('SELECT * FROM contributors WHERE story_id=? ORDER BY id', [$id])->fetchAll() as $p) {
            $consents = [];
            foreach ($this->query('SELECT purpose,granted,recorded_at FROM consents WHERE contributor_id=? ORDER BY id', [$p['id']])->fetchAll() as $c) {
                $consents[$c['purpose']] = ['granted' => (bool) $c['granted'], 'recordedAt' => $this->date($c['recorded_at'])];
            }
            $people[] = ['id' => $p['id'], 'name' => $p['name'], 'role' => $p['role'], 'isCommunityFund' => (bool) $p['is_community_fund'], 'consents' => $consents];
        }
        $shares = array_map(static fn(array $s): array => ['contributorId' => $s['contributor_id'], 'bps' => (int) $s['bps']], $this->query('SELECT * FROM split_shares WHERE story_id=? ORDER BY contributor_id', [$id])->fetchAll());
        return [
            'id' => $r['id'], 'title' => $r['title'], 'creatorId' => $r['creator_id'],
            'communityName'=>$r['community_name'], 'communityId'=>$r['community_id'], 'sharingState'=>$r['sharing_state'], 'revision'=>(int)$r['revision'], 'preferredCredit'=>$r['preferred_credit'], 'updatedAt'=>$r['updated_at'] ? $this->date($r['updated_at']) : null,
            'creator' => ['name' => $r['creator_name'], 'type' => $r['creator_type'], 'representativeName' => $r['representative_name']],
            'mediaType' => $r['media_type'], 'textBody' => $r['text_body'], 'filePath' => $r['file_path'], 'mimeType' => $r['mime_type'],
            'language' => $r['language'], 'placeCoarse' => $r['place_coarse'], 'contextNote' => $r['context_note'],
            'sensitivity' => $r['sensitivity'], 'status' => $r['status'], 'contentHash' => $r['content_hash'], 'createdAt' => $this->date($r['created_at']),
            'license' => ['preset' => $l['preset'], 'aiTrainingAllowed' => (bool) $l['ai_training_allowed'], 'contactEmail' => $l['contact_email'], 'notes' => $l['notes']],
            'contributors' => $people, 'shares' => $shares,
        ];
    }

    private function date(string $date): string
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    public function stories(bool $private = false): array
    {
        $rows = $this->query('SELECT id FROM stories ORDER BY created_at DESC')->fetchAll();
        if ($private) {$rows=array_values(array_filter($rows,fn(array $r): bool=>$this->visibleToActor($r['id'])));}
        return array_map(fn(array $r): array => $private ? $this->story($r['id']) : Rules::publicStory($this->story($r['id'])), $rows);
    }

    public function consent(string $id, array $data): void
    {
        $updates = $data['updates'] ?? null;
        if (!is_array($updates) || !array_is_list($updates) || count($updates) < 1 || count($updates) > 250) {
            throw new Problem('VALIDATION', 'Provide a list of consent updates.');
        }
        $this->db->beginTransaction();
        try {
            $changes=new StoryChanges($this);
            $story = $changes->begin($id,$data);
            $ids = array_column($story['contributors'], 'id');
            foreach ($updates as $update) {
                if (!is_array($update) || !in_array($update['contributorId'] ?? null, $ids, true)) {
                    throw new Problem('VALIDATION', 'This contributor does not belong to the story.');
                }
                $purpose = Input::choice($update, 'purpose', Rules::PURPOSES);
                if (!array_key_exists('granted', $update)) {
                    throw new Problem('VALIDATION', 'Record an explicit consent choice.');
                }
                $granted = Input::bool($update, 'granted');
                $this->query('INSERT INTO consents(contributor_id,purpose,granted,recorded_at) VALUES(?,?,?,UTC_TIMESTAMP(6))', [$update['contributorId'], $purpose, (int) $granted]);
            }
            $changes->finish($id,$data,'Declared consent updated.');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function status(string $id, array $data): void
    {
        $status = Input::choice($data, 'status', ['UNVERIFIED', 'AUTHOR_REVIEWED', 'COMMUNITY_ENDORSED']);
        $this->db->beginTransaction();
        try {
            $changes=new StoryChanges($this);$changes->begin($id,$data);
            $this->query('UPDATE stories SET status=? WHERE id=?', [$status, $id]);
            $changes->finish($id,$data,'Declared review updated.',false);$this->db->commit();
        } catch (\Throwable $e) {$this->db->rollBack();throw $e;}
    }

    public function request(string $storyId, array $data): array
    {
        $this->story($storyId);
        $name = Input::text($data, 'requesterName');
        $contact = Input::email($data, 'requesterContact');
        $use = Input::text($data, 'intendedUse', 4000);
        $commercial = Input::bool($data, 'isCommercial');
        $ai = Input::bool($data, 'isAiTraining') || Rules::aiIntent($use);
        $fee = Input::money($data, 'proposedFeeMinor');
        $currency = Input::currency($data);
        $id = bin2hex(random_bytes(16));
        $this->db->beginTransaction();
        try {
            $this->query('SELECT id FROM stories WHERE id=? FOR UPDATE',[$storyId]);
            if ($this->story($storyId)['sharingState']!=='ACTIVE') {throw new Problem('SHARING_PAUSED','The steward has paused new permission requests for this record.',409);}
            $counts=$this->query('SELECT COUNT(*) AS total,SUM(requester_contact=?) AS personal FROM permission_requests WHERE story_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)',[$contact,$storyId])->fetch();
            if ((int)$counts['total']>=40 || (int)$counts['personal']>=5) {throw new Problem('RATE_LIMIT','Please wait before sending another request for this story.',429);}
            $this->query('INSERT INTO permission_requests(id,story_id,requester_name,requester_contact,intended_use,is_commercial,is_ai_training,proposed_fee_minor,currency,email_updates,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))', [$id, $storyId, $name, $contact, $use, (int) $commercial, (int) $ai, $fee, $currency,(int)Input::bool($data,'emailUpdates')]);
            $updates=new RequestUpdates($this->db,$this->config);$link=$updates->link($id);$updates->event($id,'PENDING');
            $this->db->commit();
        } catch (\Throwable $e) {$this->db->rollBack();throw $e;}
        $updates->dispatch($id);
        return ['id' => $id, 'status' => 'PENDING', 'aiFlag' => $ai,'trackingUrl'=>$link,'emailDelivery'=>(new Mailbox($this->db,$this->config))->enabled()?'queued':'disabled'];
    }

    public function requests(): array
    {
        return array_map(fn(array $r): array => $this->mapRequest($r), array_values(array_filter($this->query('SELECT * FROM permission_requests ORDER BY created_at DESC')->fetchAll(),fn(array $r):bool=>$this->visibleToActor($r['story_id']))));
    }

    private function mapRequest(array $r): array
    {
        return ['counterResponse'=>$r['counter_response'],'counterReply'=>$r['counter_reply'],'counterVersion'=>(int)$r['counter_version'], 'id' => $r['id'], 'storyId' => $r['story_id'], 'requesterName' => $r['requester_name'], 'requesterContact' => $r['requester_contact'], 'intendedUse' => $r['intended_use'], 'isCommercial' => (bool) $r['is_commercial'], 'isAiTraining' => (bool) $r['is_ai_training'], 'proposedFeeMinor' => $r['proposed_fee_minor'] === null ? null : (int) $r['proposed_fee_minor'], 'currency' => $r['currency'], 'status' => $r['status'], 'counterFeeMinor' => $r['counter_fee_minor'] === null ? null : (int) $r['counter_fee_minor'], 'finalFeeMinor' => $r['final_fee_minor'] === null ? null : (int) $r['final_fee_minor'], 'decisionNote' => $r['decision_note'], 'createdAt' => $this->date($r['created_at'])];
    }

    public function decide(string $id, array $data): array
    {
        $decision = Input::choice($data, 'status', ['APPROVED', 'DECLINED', 'COUNTERED']);
        $note = Input::text($data, 'note', 2000, $decision === 'COUNTERED');
        $this->db->beginTransaction();
        try {
            $raw = $this->query('SELECT * FROM permission_requests WHERE id=? FOR UPDATE', [$id])->fetch();
            if (!$raw) {
                throw new Problem('NOT_FOUND', 'Request not found.', 404);
            }
            // Acquire the story lock before any consistent read establishes a snapshot.
            $this->query('SELECT id FROM stories WHERE id=? FOR UPDATE',[$raw['story_id']]);
            $this->privateStory($raw['story_id'],true);
            $request = $this->mapRequest($raw);
            if ($request['status'] === 'APPROVED' && $decision === 'APPROVED') {
                $this->db->commit();
                return $request;
            }
            if (in_array($request['status'], ['APPROVED', 'DECLINED'], true)) {
                throw new Problem('DECIDED', 'This request is already final. Submit a new request for another use.', 409);
            }
            // Same lock is used by consent updates: approval sees one coherent state.
            $this->query('SELECT id FROM stories WHERE id=? FOR UPDATE', [$request['storyId']]);
            $story = $this->story($request['storyId']);
            $fee = null;
            $snapshot = null;
            $counter = $request['counterFeeMinor'];
            if ($decision === 'COUNTERED') {
                $counter = Input::money($data, 'counterFeeMinor');
                if ($counter === null) {
                    throw new Problem('VALIDATION', 'Enter a demo counteroffer amount.');
                }
            }
            if ($decision === 'APPROVED') {
                $reasons = Rules::approvalReasons($request, $story);
                if ($reasons !== []) {
                    throw new Problem('CONSENT_BLOCKED', implode(' ', $reasons), 409, ['reasons' => $reasons]);
                }
                if (!Input::bool($data, 'feeConfirmed')) {
                    throw new Problem('CONFIRMATION', 'Confirm the final demo fee before approval.');
                }
                $fee = Input::money($data, 'finalFeeMinor');
                if ($request['isCommercial'] && $fee === null) {
                    throw new Problem('VALIDATION', 'Enter the final demo fee, including zero for no fee.');
                }
                if ($request['counterResponse']==='ACCEPTED' && $fee!==$request['counterFeeMinor']) {throw new Problem('FEE_CHANGED','The final fee must match the accepted counteroffer. Send a new counteroffer to change it.',409);}
                if (!$request['isCommercial'] && ($fee ?? 0) !== 0) {
                    throw new Problem('VALIDATION', 'A noncommercial request cannot produce a fee.');
                }
                $snapshot = json_encode(['revision'=>$story['revision'],'storyTitle'=>$story['title'],'license' => $story['license'], 'shares' => $story['shares'], 'consents' => $story['contributors'], 'finalFeeMinor' => $fee, 'currency' => $request['currency']], JSON_THROW_ON_ERROR);
                if ($request['isCommercial']) {
                    foreach (Rules::allocate($fee, $story['shares']) as $person => $amount) {
                        $this->query('INSERT INTO ledger_entries(id,story_id,permission_req_id,contributor_id,amount_minor,currency,kind,payment_key,note,created_at) VALUES(?,?,?,?,?,?,\'MOCK_PAYMENT\',?,?,UTC_TIMESTAMP(6))', [bin2hex(random_bytes(16)), $story['id'], $id, $person, $amount, $request['currency'], $id . ':' . $person, 'MOCK allocation for approved use']);
                    }
                }
            }
            $this->query('UPDATE permission_requests SET status=?,counter_fee_minor=?,decision_note=?,final_fee_minor=?,approval_snapshot=?,decided_at=UTC_TIMESTAMP(6) WHERE id=?', [$decision, $counter, $note, $fee, $snapshot, $id]);
            if ($decision==='COUNTERED') {$this->query('UPDATE permission_requests SET counter_response=NULL,counter_reply=NULL,counter_version=counter_version+1 WHERE id=?',[$id]);}
            $updates=new RequestUpdates($this->db,$this->config);$updates->event($id,$decision,$note);
            $this->db->commit();
            $updates->dispatch($id);
            return ['id' => $id, 'status' => $decision];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function ledger(?string $storyId = null): array
    {
        $where = $storyId ? ' WHERE e.story_id=?' : '';
        $entries = $this->query('SELECT e.*,c.name AS contributor_name FROM ledger_entries e JOIN contributors c ON c.id=e.contributor_id' . $where . ' ORDER BY e.created_at DESC,e.id', $storyId ? [$storyId] : [])->fetchAll();
        $entries=array_values(array_filter($entries,fn(array $r):bool=>$this->visibleToActor($r['story_id'])));
        if ($storyId) {$this->privateStory($storyId);}
        $balances = [];
        $totals = [];
        foreach ($entries as &$entry) {
            $entry['amount_minor'] = (int) $entry['amount_minor'];
            $key = $entry['contributor_id'] . ':' . $entry['currency'];
            $balances[$key] ??= ['contributorId' => $entry['contributor_id'], 'name' => $entry['contributor_name'], 'currency' => $entry['currency'], 'amountMinor' => 0];
            $balances[$key]['amountMinor'] += $entry['amount_minor'];
            $totals[$entry['currency']] = ($totals[$entry['currency']] ?? 0) + $entry['amount_minor'];
        }
        return ['entries' => $entries, 'balances' => array_values($balances), 'totals' => $totals];
    }

    public function adjust(string $entryId, array $data): array
    {
        $amount = Input::money($data, 'amountMinor', true);
        $note = Input::text($data, 'note', 2000);
        if ($amount === null || $amount === 0) {
            throw new Problem('VALIDATION', 'Enter a nonzero signed demo adjustment.');
        }
        $entry = $this->query('SELECT * FROM ledger_entries WHERE id=?', [$entryId])->fetch();
        if (!$entry) {
            throw new Problem('NOT_FOUND', 'Entry not found.', 404);
        }
        $this->privateStory($entry['story_id'],true);
        $id = bin2hex(random_bytes(16));
        $this->query('INSERT INTO ledger_entries(id,story_id,permission_req_id,contributor_id,amount_minor,currency,kind,original_entry_id,note,created_at) VALUES(?,?,?,?,?,?,\'MOCK_ADJUSTMENT\',?,?,UTC_TIMESTAMP(6))', [$id, $entry['story_id'], $entry['permission_req_id'], $entry['contributor_id'], $amount, $entry['currency'], $entryId, $note]);
        return ['id' => $id];
    }

    public function claim(string $storyId, array $data): array
    {
        $this->story($storyId);
        $id = bin2hex(random_bytes(16));
        $this->query('INSERT INTO claims(id,story_id,reporter_name,reporter_contact,reason,created_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))', [$id, $storyId, Input::text($data, 'reporterName'), Input::email($data, 'reporterContact'), Input::text($data, 'reason', 4000)]);
        return ['id' => $id, 'status' => 'OPEN'];
    }

    public function claims(): array
    {
        return array_values(array_filter($this->query('SELECT * FROM claims ORDER BY created_at DESC')->fetchAll(),fn(array $r):bool=>$this->visibleToActor($r['story_id'])));
    }

    public function resolve(string $id, array $data): void
    {
        $status = Input::choice($data, 'status', ['OPEN', 'RESOLVED']);
        $claim=$this->query('SELECT story_id FROM claims WHERE id=?',[$id])->fetch();
        if (!$claim) {
            throw new Problem('NOT_FOUND', 'Claim not found.', 404);
        }
        $this->privateStory($claim['story_id'],true);
        $this->query('UPDATE claims SET status=? WHERE id=?', [$status, $id]);
    }
}
