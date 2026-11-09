<?php
declare(strict_types=1);
namespace Passport;

final class Accounts
{
    public const DEFAULT_COMMUNITY = '11111111111111111111111111111111';
    public function __construct(private readonly \PDO $db, private readonly array $config) {}

    public function current(): ?array
    {
        $user = Database::run($this->db, 'SELECT * FROM accounts WHERE id=? AND active=1', [$_SESSION['accountId'] ?? ''])->fetch();
        if (!$user || (int) $user['auth_version'] !== ($_SESSION['authVersion'] ?? null)) {
            unset($_SESSION['accountId'], $_SESSION['authVersion'], $_SESSION['steward']);
            return null;
        }
        unset($user['password_hash']);
        return $user;
    }

    public function login(string $username, string $password): array
    {
        $username = strtolower(trim($username));
        if (strlen($username) > 100 || strlen($password) > 1024) {
            throw new Problem('LOGIN', 'The sign-in details were not accepted.', 403);
        }
        $key = hash('sha256', $username);
        $this->db->beginTransaction();
        try {
            Database::run($this->db, 'INSERT IGNORE INTO account_login_guard(identity_hash) VALUES(?)', [$key]);
            $guard = Database::run($this->db, 'SELECT *,blocked_until>UTC_TIMESTAMP() AS blocked FROM account_login_guard WHERE identity_hash=? FOR UPDATE', [$key])->fetch();
            if ($guard['blocked']) {
                throw new Problem('RATE_LIMIT', 'Please wait fifteen minutes before trying this account again.', 429);
            }
            $state = Database::run($this->db, 'SELECT * FROM site_state WHERE id=1 FOR UPDATE')->fetch();
            $user = Database::run($this->db, 'SELECT * FROM accounts WHERE username=?', [$username])->fetch();
            // Compatibility is consumed once, in the same locked transaction.
            if (!$user && !$state['bootstrap_account_id'] && hash_equals(strtolower($this->config['steward_name']), $username)
                && ($this->config['steward_password_hash'] ?? '') !== '' && password_verify($password, $this->config['steward_password_hash'])) {
                $id = bin2hex(random_bytes(16));
                Database::run($this->db, 'INSERT INTO accounts(id,username,display_name,email,password_hash,is_owner,created_at) VALUES(?,?,?,?,?,1,UTC_TIMESTAMP(6))', [$id,$username,'Site steward',$this->config['mail']['from'] ?? 'steward@okwethu.wilmans.co.za',$this->config['steward_password_hash']]);
                Database::run($this->db, 'INSERT INTO memberships VALUES(?,?,\'ADMIN\')', [$id,self::DEFAULT_COMMUNITY]);
                Database::run($this->db, 'UPDATE site_state SET bootstrap_account_id=? WHERE id=1', [$id]);
                $user = Database::run($this->db, 'SELECT * FROM accounts WHERE id=?', [$id])->fetch();
            }
            $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            if (!password_verify($password, $hash) || !$user || !$user['active']) {
                Database::run($this->db, 'UPDATE account_login_guard SET failures=IF(blocked_until IS NOT NULL AND blocked_until<=UTC_TIMESTAMP(),1,failures+1),blocked_until=IF(failures>=5,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),NULL) WHERE identity_hash=?', [$key]);
                $this->db->commit();
                throw new Problem('LOGIN', 'The sign-in details were not accepted.', 403);
            }
            Database::run($this->db, 'DELETE FROM account_login_guard WHERE identity_hash=?', [$key]);
            $this->db->commit();
            return $user;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {$this->db->rollBack();}
            throw $e;
        }
    }

    public function communities(array $actor, bool $write = false): array
    {
        if (!empty($actor['is_owner'])) {
            return Database::run($this->db, 'SELECT * FROM communities ORDER BY name')->fetchAll();
        }
        return Database::run($this->db, 'SELECT c.*,m.role FROM communities c JOIN memberships m ON m.community_id=c.id WHERE m.account_id=?' . ($write ? " AND m.role IN ('ADMIN','STEWARD')" : '') . ' ORDER BY c.name', [$actor['id'] ?? ''])->fetchAll();
    }

    public function community(array $actor, string $id, bool $write = false): void
    {
        if (!in_array($id,array_column($this->communities($actor,$write),'id'),true)) {
            throw new Problem('FORBIDDEN', 'This community record is not available to your account.', 403);
        }
    }

    public function owner(array $actor): void
    {
        if (empty($actor['is_owner'])) {throw new Problem('FORBIDDEN','Only the site owner can manage the team.',403);}
    }

    public function adminCommunities(array $actor): array
    {
        return array_values(array_filter($this->communities($actor),static fn(array $c):bool=>!empty($actor['is_owner']) || ($c['role']??'')==='ADMIN'));
    }

    private function admin(array $actor, string $community): void
    {
        if (!in_array($community,array_column($this->adminCommunities($actor),'id'),true)) {throw new Problem('FORBIDDEN','Community admin access is required.',403);}
    }

    private function password(array $data, string $key = 'password'): string
    {
        $value = Input::text($data,$key,72);
        if (strlen($value)<12 || strlen($value)>72) {throw new Problem('VALIDATION','Use a password between 12 and 72 bytes.');}
        return $value;
    }

    public function changePassword(array $actor, array $data): void
    {
        $old = Input::text($data,'currentPassword',1024);
        $new = $this->password($data);
        $user = Database::run($this->db,'SELECT * FROM accounts WHERE id=?',[$actor['id']])->fetch();
        if (!password_verify($old,$user['password_hash'])) {throw new Problem('LOGIN','The current password was not accepted.',403);}
        Database::run($this->db,'UPDATE accounts SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$actor['id']]);
        // All sessions, including this one, must sign in again.
    }

    public function team(array $actor): array
    {
        $groups=$this->adminCommunities($actor);
        if (!$groups) {throw new Problem('FORBIDDEN','Community admin access is required.',403);}
        $ids=array_column($groups,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
        $members=Database::run($this->db,'SELECT m.*,a.username,c.name FROM memberships m JOIN accounts a ON a.id=m.account_id JOIN communities c ON c.id=m.community_id WHERE m.community_id IN ('.$marks.') ORDER BY c.name,a.username',$ids)->fetchAll();
        $users=!empty($actor['is_owner']) ? Database::run($this->db,'SELECT id,username,display_name,email,active,is_owner FROM accounts ORDER BY username')->fetchAll()
            : Database::run($this->db,'SELECT DISTINCT a.id,a.username,a.display_name,a.email,a.active,a.is_owner FROM accounts a JOIN memberships m ON m.account_id=a.id WHERE m.community_id IN ('.$marks.') ORDER BY username',$ids)->fetchAll();
        return ['accounts'=>$users,'memberships'=>$members,'adminCommunities'=>$groups];
    }

    public function manage(array $actor, array $data): array
    {
        $action = Input::choice($data,'action',['community','account','membership','active','resetPassword']);
        if ($action==='community') {
            $this->owner($actor);
            $id=bin2hex(random_bytes(16));
            Database::run($this->db,'INSERT INTO communities VALUES(?,?,UTC_TIMESTAMP(6))',[$id,Input::text($data,'name',200)]);
            return ['id'=>$id];
        }
        if ($action==='account') {
            $username=strtolower(Input::text($data,'username',100));
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/',$username)) {throw new Problem('VALIDATION','Use 3–100 letters, numbers, dots, underscores or hyphens for the username.');}
            $password=$this->password($data);
            $community=Input::text($data,'communityId',32);
            $this->admin($actor,$community);
            $role=Input::choice($data,'role',['ADMIN','STEWARD','REVIEWER']);
            $id=bin2hex(random_bytes(16));
            $this->db->beginTransaction();
            try {
                Database::run($this->db,'INSERT INTO accounts(id,username,display_name,email,password_hash,created_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[$id,$username,Input::text($data,'displayName'),Input::email($data,'email'),password_hash($password,PASSWORD_DEFAULT)]);
                Database::run($this->db,'INSERT INTO memberships VALUES(?,?,?)',[$id,$community,$role]);
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                if ($e instanceof \PDOException && ($e->errorInfo[1]??0)===1062) {throw new Problem('VALIDATION','This username is already in use.');}
                throw $e;
            }
            return ['id'=>$id];
        }
        $id=Input::text($data,'accountId',32);
        $user=Database::run($this->db,'SELECT * FROM accounts WHERE id=?',[$id])->fetch();
        if (!$user) {throw new Problem('NOT_FOUND','Account not found.',404);}
        if ($action==='membership') {
            $community=Input::text($data,'communityId',32);
            $this->admin($actor,$community);
            if (empty($actor['is_owner']) && !Database::run($this->db,'SELECT 1 FROM memberships WHERE account_id=? AND community_id=?',[$id,$community])->fetchColumn()) {throw new Problem('FORBIDDEN','Ask the site owner to add an existing account to your community.',403);}
            $role=Input::choice($data,'role',['ADMIN','STEWARD','REVIEWER','REMOVE']);
            if ($role==='REMOVE') {Database::run($this->db,'DELETE FROM memberships WHERE account_id=? AND community_id=?',[$id,$community]);}
            else {Database::run($this->db,'INSERT INTO memberships VALUES(?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)',[$id,$community,$role]);}
        } elseif ($action==='active') {
            $this->owner($actor);
            if ($user['is_owner']) {throw new Problem('VALIDATION','The site owner cannot be disabled.');}
            Database::run($this->db,'UPDATE accounts SET active=?,auth_version=auth_version+1 WHERE id=?',[(int)Input::bool($data,'active'),$id]);
        } else {
            $this->owner($actor);
            if ($user['is_owner']) {throw new Problem('VALIDATION','Use your own password form for the site owner.');}
            Database::run($this->db,'UPDATE accounts SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($this->password($data),PASSWORD_DEFAULT),$id]);
        }
        return ['saved'=>true];
    }
}
