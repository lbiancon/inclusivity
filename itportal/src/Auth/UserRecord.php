<?php

declare(strict_types=1);

namespace ItPortal\Auth;

final class UserRecord
{
    public function __construct(
        public readonly string $login,
        public readonly string $emplId,
        public readonly string $nome,
        public readonly string $cognome,
        public readonly string $superuser,
        public readonly string $empQuaCode,
        public readonly string $roles,
    ) {
    }

    /** @return array<string, string> */
    public function toSession(): array
    {
        return [
            'login' => $this->login,
            'emplId' => $this->emplId,
            'nome' => $this->nome,
            'cognome' => $this->cognome,
            'superuser' => $this->superuser,
            'empQuaCode' => $this->empQuaCode,
            'roles' => $this->roles,
            'timestamp' => (string) time(),
            'id_kss' => $this->emplId,
        ];
    }
}
