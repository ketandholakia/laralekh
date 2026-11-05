<?php
declare(strict_types=1);
namespace App\Domain\Shared\Tenancy;

use Closure;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

final class TenantContext
{
    private ?int $companyId = null;

    public function id(): int
    {
        return $this->companyId ?? throw new LogicException('A company context is required.');
    }

    // Call only after authorization; never derive this directly from untrusted request input.
    public function run(int $companyId, Closure $callback): mixed
    {
        if ($companyId < 1) {
            throw new LogicException('Invalid company.');
        }
        $previous = $this->companyId;
        $previousTeam = getPermissionsTeamId();
        $registrar = app(PermissionRegistrar::class);
        $previousCacheKey = $registrar->cacheKey;
        $switching = $previous !== $companyId;
        $this->companyId = $companyId;
        setPermissionsTeamId($companyId);
        // Spatie caches permissions together with the roles visible at build
        // time, and roles are company-scoped here. A shared cache would freeze
        // one company's view for every other company, so the key is switched
        // with the team, and on an actual company change any cached view —
        // including the in-memory one — is dropped and rebuilt for this team.
        $registrar->cacheKey = self::permissionCacheKey($companyId);
        if ($switching) {
            $registrar->forgetCachedPermissions();
        }
        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
            setPermissionsTeamId($previousTeam);
            $registrar->cacheKey = $previousCacheKey;
        }
    }

    public static function permissionCacheKey(int $companyId): string
    {
        return config('permission.cache.key', 'spatie.permission.cache').'.company.'.$companyId;
    }
}
