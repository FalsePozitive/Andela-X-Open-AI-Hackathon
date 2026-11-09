<?php
declare(strict_types=1);
namespace Passport;

final class Registry
{
    public function __construct(public readonly \PDO $db, private readonly array $config) {}

    private function query(string $sql, array $params = []): \PDOStatement
    {
        return Database::run($this->db, $sql, $params);
    }

    public function create(array $data, ?array $file = null, ?string $creatorId = null): array
    {
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
        $r = $this->query('SELECT s.*, c.name AS creator_name,c.type AS creator_type,c.representative_name FROM stories s JOIN creators c ON c.id=s.creator_id WHERE s.id=?', [$id])->fetch();
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
            $this->query('SELECT id FROM stories WHERE id=? FOR UPDATE', [$id]);
            $story = $this->story($id);
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
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function status(string $id, array $data): void
    {
        $status = Input::choice($data, 'status', ['UNVERIFIED', 'AUTHOR_REVIEWED', 'COMMUNITY_ENDORSED']);
        $this->story($id);
        $this->query('UPDATE stories SET status=? WHERE id=?', [$status, $id]);
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
        $this->query('INSERT INTO permission_requests(id,story_id,requester_name,requester_contact,intended_use,is_commercial,is_ai_training,proposed_fee_minor,currency,created_at) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))', [$id, $storyId, $name, $contact, $use, (int) $commercial, (int) $ai, $fee, $currency]);
        // Public acknowledgement intentionally does not echo private requester data.
        return ['id' => $id, 'status' => 'PENDING', 'aiFlag' => $ai];
    }

    public function requests(): array
    {
        return array_map(fn(array $r): array => $this->mapRequest($r), $this->query('SELECT * FROM permission_requests ORDER BY created_at DESC')->fetchAll());
    }

    private function mapRequest(array $r): array
    {
        return ['id' => $r['id'], 'storyId' => $r['story_id'], 'requesterName' => $r['requester_name'], 'requesterContact' => $r['requester_contact'], 'intendedUse' => $r['intended_use'], 'isCommercial' => (bool) $r['is_commercial'], 'isAiTraining' => (bool) $r['is_ai_training'], 'proposedFeeMinor' => $r['proposed_fee_minor'] === null ? null : (int) $r['proposed_fee_minor'], 'currency' => $r['currency'], 'status' => $r['status'], 'counterFeeMinor' => $r['counter_fee_minor'] === null ? null : (int) $r['counter_fee_minor'], 'finalFeeMinor' => $r['final_fee_minor'] === null ? null : (int) $r['final_fee_minor'], 'decisionNote' => $r['decision_note'], 'createdAt' => $this->date($r['created_at'])];
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
                    throw new Problem('VALIDATION', 'Enter a MOCK counteroffer amount.');
                }
            }
            if ($decision === 'APPROVED') {
                $reasons = Rules::approvalReasons($request, $story);
                if ($reasons !== []) {
                    throw new Problem('CONSENT_BLOCKED', implode(' ', $reasons), 409, ['reasons' => $reasons]);
                }
                if (!Input::bool($data, 'feeConfirmed')) {
                    throw new Problem('CONFIRMATION', 'Confirm the final MOCK fee before approval.');
                }
                $fee = Input::money($data, 'finalFeeMinor');
                if ($request['isCommercial'] && $fee === null) {
                    throw new Problem('VALIDATION', 'Enter the final MOCK fee, including zero for no fee.');
                }
                if (!$request['isCommercial'] && ($fee ?? 0) !== 0) {
                    throw new Problem('VALIDATION', 'A noncommercial request cannot produce a fee.');
                }
                $snapshot = json_encode(['license' => $story['license'], 'shares' => $story['shares'], 'consents' => $story['contributors'], 'finalFeeMinor' => $fee, 'currency' => $request['currency']], JSON_THROW_ON_ERROR);
                if ($request['isCommercial']) {
                    foreach (Rules::allocate($fee, $story['shares']) as $person => $amount) {
                        $this->query('INSERT INTO ledger_entries(id,story_id,permission_req_id,contributor_id,amount_minor,currency,kind,payment_key,note,created_at) VALUES(?,?,?,?,?,?,\'MOCK_PAYMENT\',?,?,UTC_TIMESTAMP(6))', [bin2hex(random_bytes(16)), $story['id'], $id, $person, $amount, $request['currency'], $id . ':' . $person, 'MOCK allocation for approved use']);
                    }
                }
            }
            $this->query('UPDATE permission_requests SET status=?,counter_fee_minor=?,decision_note=?,final_fee_minor=?,approval_snapshot=?,decided_at=UTC_TIMESTAMP(6) WHERE id=?', [$decision, $counter, $note, $fee, $snapshot, $id]);
            $this->db->commit();
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
            throw new Problem('VALIDATION', 'Enter a nonzero signed MOCK adjustment.');
        }
        $entry = $this->query('SELECT * FROM ledger_entries WHERE id=?', [$entryId])->fetch();
        if (!$entry) {
            throw new Problem('NOT_FOUND', 'Entry not found.', 404);
        }
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
        return $this->query('SELECT * FROM claims ORDER BY created_at DESC')->fetchAll();
    }

    public function resolve(string $id, array $data): void
    {
        $status = Input::choice($data, 'status', ['OPEN', 'RESOLVED']);
        if (!$this->query('SELECT id FROM claims WHERE id=?', [$id])->fetch()) {
            throw new Problem('NOT_FOUND', 'Claim not found.', 404);
        }
        $this->query('UPDATE claims SET status=? WHERE id=?', [$status, $id]);
    }
}
