<?php

namespace App\Services\Connectors;

use App\Models\Connector;
use App\Models\ConnectorMembership;
use App\Models\ConnectorRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConnectorRoleService
{
    public function allPermissionKeys(): array
    {
        return collect(config('connector_permissions.permissions', []))
            ->flatMap(fn (array $group) => array_keys($group))
            ->values()
            ->all();
    }

    public function validatePermissionKeys(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        $allowed = $this->allPermissionKeys();

        foreach ($keys as $key) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'permissions' => 'Invalid connector permission: '.$key,
                ]);
            }
        }

        return $keys;
    }

    public function createDefaultOwnerRole(Connector $connector): ConnectorRole
    {
        return DB::transaction(function () use ($connector) {
            $role = $connector->roles()->firstOrCreate(
                ['name' => 'Owner'],
                [
                    'description' => 'Protected connector owner with full connector permissions.',
                    'is_owner' => true,
                    'is_protected' => true,
                ]
            );

            $role->permissions()->delete();
            foreach ($this->allPermissionKeys() as $permissionKey) {
                $role->permissions()->create(['permission_key' => $permissionKey]);
            }

            return $role->fresh('permissions');
        });
    }

    public function defaultMemberRole(Connector $connector): ConnectorRole
    {
        $role = $connector->roles()->firstOrCreate(
            ['name' => 'Member'],
            [
                'description' => 'View-only connector member with Community Hub access.',
                'is_owner' => false,
                'is_protected' => true,
            ]
        );

        if (! in_array('community.view_space', $role->permissionKeys(), true)) {
            $role->permissions()->create(['permission_key' => 'community.view_space']);
        }

        return $role->fresh('permissions');
    }

    public function createRole(Connector $connector, array $attributes): ConnectorRole
    {
        return DB::transaction(function () use ($connector, $attributes) {
            $role = $connector->roles()->create([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_owner' => false,
                'is_protected' => false,
            ]);

            $this->syncPermissions($role, $attributes['permissions'] ?? []);

            return $role->fresh('permissions');
        });
    }

    public function updateRole(ConnectorRole $role, array $attributes): ConnectorRole
    {
        if ($role->is_owner && (($attributes['name'] ?? $role->name) !== 'Owner')) {
            throw ValidationException::withMessages(['role' => 'The Owner role name cannot be changed.']);
        }

        return DB::transaction(function () use ($role, $attributes) {
            $role->update([
                'name' => $role->is_owner ? $role->name : ($attributes['name'] ?? $role->name),
                'description' => $attributes['description'] ?? $role->description,
            ]);

            $this->syncPermissions($role, $attributes['permissions'] ?? []);

            return $role->fresh('permissions');
        });
    }

    public function syncPermissions(ConnectorRole $role, array $keys): void
    {
        $keys = $this->validatePermissionKeys($keys);
        $role->permissions()->delete();

        foreach ($keys as $key) {
            $role->permissions()->create(['permission_key' => $key]);
        }
    }

    public function deleteRole(ConnectorRole $role): void
    {
        if ($role->is_owner || $role->is_protected) {
            throw ValidationException::withMessages(['role' => 'The Owner role cannot be deleted.']);
        }

        $role->delete();
    }

    public function assertCanChangeMembershipRole(ConnectorMembership $membership, ConnectorRole $newRole): void
    {
        if ($membership->role?->is_owner && ! $newRole->is_owner && $this->isLastActiveOwner($membership)) {
            throw ValidationException::withMessages(['member' => 'Every connector must keep at least one active Owner.']);
        }
    }

    public function assertCanRemoveMembership(ConnectorMembership $membership): void
    {
        if ($membership->role?->is_owner && $this->isLastActiveOwner($membership)) {
            throw ValidationException::withMessages(['member' => 'The last active Owner cannot be removed.']);
        }
    }

    public function isLastActiveOwner(ConnectorMembership $membership): bool
    {
        if (! $membership->role?->is_owner) {
            return false;
        }

        return ConnectorMembership::query()
            ->where('connector_id', $membership->connector_id)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('is_owner', true))
            ->whereKeyNot($membership->getKey())
            ->doesntExist();
    }
}
