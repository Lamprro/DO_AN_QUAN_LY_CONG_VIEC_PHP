<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Enums\WorkspaceKind;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceAccess;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class InvitationService
{
    public function __construct(private WorkspaceAccess $access) {}

    private function store(): Repository
    {
        $name = config('workspace.invitation_store');
        if (app()->environment('production') && $name !== 'redis') {
            throw new LogicException('Production phải cấu hình INVITATION_CACHE_STORE=redis.');
        }

        return Cache::store($name);
    }

    private function recordKey(string $hash): string
    {
        return 'wm:invite:'.$hash;
    }

    private function indexKey(string $workspace): string
    {
        return 'wm:invite-index:'.$workspace;
    }

    public function find(string $token): array
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{64}$/', $token), 410, 'Lời mời không hợp lệ hoặc đã hết hạn.');
        $record = $this->store()->get($this->recordKey(hash('sha256', $token)));
        abort_unless($record && $record['expires_at'] > now()->timestamp, 410, 'Lời mời đã hết hạn, bị thu hồi hoặc đã sử dụng.');
        abort_unless(Workspace::whereKey($record['workspace_id'])->exists(), 410, 'Workspace không còn tồn tại.');

        return $record;
    }

    public function pending(Workspace $workspace): array
    {
        $store = $this->store();
        $records = [];
        foreach ($store->get($this->indexKey($workspace->id), []) as $id => $hash) {
            $row = $store->get($this->recordKey($hash));
            if ($row && $row['expires_at'] > now()->timestamp) {
                $records[$id] = $row;
            }
        }

        return $records;
    }

    public function issue(User $actor, Workspace $workspace, array $data): array
    {
        $store = $this->store();
        $result = $store->lock('wm:invite-lock:'.$workspace->id, 15)->block(5, function () use ($store, $actor, $workspace, $data) {
            return DB::transaction(function () use ($store, $actor, $workspace, $data) {
                $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
                Gate::forUser($actor)->authorize('manageMembers', $workspace);
                if ($workspace->kind === WorkspaceKind::PERSONAL) {
                    throw ValidationException::withMessages(['invitation' => 'Workspace cá nhân không nhận thêm thành viên.']);
                }
                $role = $workspace->roles()->findOrFail($data['role_id']);
                $this->access->assertAssignable($actor, $workspace, $role);
                $existing = $workspace->members()->whereHas('user', fn ($q) => $q->where('email', $data['email']))->first();
                if ($existing && $existing->status !== MembershipStatus::LEFT) {
                    throw ValidationException::withMessages(['email' => 'Người này đã là thành viên hoặc đang bị đình chỉ; hãy dùng quản lý thành viên.']);
                }
                $index = $store->get($this->indexKey($workspace->id), []);
                foreach ($this->pending($workspace) as $id => $old) {
                    if ($old['email'] === $data['email']) {
                        $store->forget($this->recordKey($old['token_hash']));
                        unset($index[$id]);
                    }
                }
                $token = Str::random(64);
                $record = ['id' => (string) Str::uuid(), 'token_hash' => hash('sha256', $token), 'workspace_id' => $workspace->id,
                    'workspace_name' => $workspace->name, 'email' => $data['email'], 'role_id' => $role->id, 'role_name' => $role->name,
                    'invited_by' => $actor->id, 'expires_at' => now()->addMinutes(config('workspace.invitation_ttl_minutes'))->timestamp];
                $store->put($this->recordKey($record['token_hash']), $record, $record['expires_at'] - now()->timestamp);
                $index[$record['id']] = $record['token_hash'];
                $store->put($this->indexKey($workspace->id), $index, 8 * 24 * 3600);

                return ['record' => $record, 'url' => route('invitations.show', ['token' => $token])];
            });
        });
        $result['mail_sent'] = false;
        if (config('workspace.send_invitation_mail')) {
            try {
                Mail::to($data['email'])->send(new WorkspaceInvitationMail($result['record'], $result['url']));
                $result['mail_sent'] = true;
            } catch (Throwable $error) {
                Log::warning('Invitation mail failed', ['invitation_id' => $result['record']['id'], 'exception' => $error::class]);
            }
        }

        return $result;
    }

    public function revoke(User $actor, Workspace $workspace, string $id): void
    {
        $store = $this->store();
        $store->lock('wm:invite-lock:'.$workspace->id, 15)->block(5, function () use ($store, $actor, $workspace, $id) {
            $workspace = Workspace::findOrFail($workspace->id);
            Gate::forUser($actor)->authorize('manageMembers', $workspace);
            $index = $store->get($this->indexKey($workspace->id), []);
            abort_unless(isset($index[$id]), 404);
            $store->forget($this->recordKey($index[$id]));
            unset($index[$id]);
            $store->put($this->indexKey($workspace->id), $index, 8 * 24 * 3600);
        });
    }

    public function accept(User $user, string $token): Workspace
    {
        $record = $this->find($token);
        $store = $this->store();

        return $store->lock('wm:invite-lock:'.$record['workspace_id'], 15)->block(5, function () use ($store, $user, $token) {
            $record = $this->find($token);
            if (! hash_equals($record['email'], mb_strtolower($user->email))) {
                throw ValidationException::withMessages(['invitation' => 'Hãy đăng nhập bằng đúng email nhận lời mời.']);
            }
            $workspace = DB::transaction(function () use ($record, $user) {
                $workspace = Workspace::lockForUpdate()->findOrFail($record['workspace_id']);
                $inviter = User::find($record['invited_by']);
                abort_unless($inviter && $inviter->status === UserStatus::ACTIVE && Gate::forUser($inviter)->allows('manageMembers', $workspace), 403, 'Người mời không còn quyền quản lý thành viên.');
                $role = $workspace->roles()->find($record['role_id']);
                if (! $role) {
                    throw ValidationException::withMessages(['invitation' => 'Vai trò đã bị xóa; cần gửi lời mời mới.']);
                }
                $this->access->assertAssignable($inviter, $workspace, $role);
                $member = $workspace->members()->where('user_id', $user->id)->lockForUpdate()->first();
                if ($member && $member->status === MembershipStatus::SUSPENDED) {
                    throw ValidationException::withMessages(['invitation' => 'Thành viên đang bị đình chỉ; cần quản trị viên kích hoạt lại.']);
                }
                if (! $member) {
                    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);
                } elseif ($member->status === MembershipStatus::LEFT) {
                    $member->update(['role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);
                }

                return $workspace;
            });
            // Consume only after SQL commit. Unique membership makes retry after a cache failure safe.
            $store->forget($this->recordKey($record['token_hash']));
            $index = $store->get($this->indexKey($workspace->id), []);
            unset($index[$record['id']]);
            $store->put($this->indexKey($workspace->id), $index, 8 * 24 * 3600);

            return $workspace;
        });
    }
}
