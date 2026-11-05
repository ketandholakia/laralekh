<?php
declare(strict_types=1);
namespace App\Domain\Shared\Tenancy;

use Closure;
use LogicException;

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
        $this->companyId = $companyId;
        setPermissionsTeamId($companyId);
        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
            setPermissionsTeamId($previousTeam);
        }
    }
}

