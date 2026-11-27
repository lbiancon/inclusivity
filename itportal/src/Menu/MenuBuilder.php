<?php

declare(strict_types=1);

namespace ItPortal\Menu;

use ItPortal\Auth\Session;
use ItPortal\Config;

final class MenuBuilder
{
    public function __construct(private readonly Session $session)
    {
    }

    /** @return list<array<string, mixed>> */
    public function visibleSections(string $active = ''): array
    {
        $menu = Config::get('menu', []);
        $user = $this->session->user();
        $loggedIn = $this->session->isLoggedIn();
        $out = [];

        foreach ($menu as $section) {
            $public = (bool) ($section['public'] ?? false);
            if (!$public && !$loggedIn) {
                continue;
            }
            if (!$public && !$this->sectionAllowed($section, $user)) {
                continue;
            }

            $children = [];
            foreach ($section['children'] ?? [] as $child) {
                if (!$this->childAllowed($child, $user, $loggedIn)) {
                    continue;
                }
                $children[] = $child;
            }

            if ($children === [] && !$public) {
                continue;
            }

            $section['children'] = $children;
            $section['active'] = $section['id'] === $active;
            $out[] = $section;
        }

        return $out;
    }

    /** @param array<string, mixed> $section */
    private function sectionAllowed(array $section, array $user): bool
    {
        $roles = $section['roles'] ?? null;
        if ($roles === null) {
            return true;
        }
        return $this->roleMatches($roles, $user);
    }

    /** @param array<string, mixed> $child */
    private function childAllowed(array $child, array $user, bool $loggedIn): bool
    {
        $roles = $child['roles'] ?? null;
        if ($roles === null) {
            return true;
        }
        if (!$loggedIn) {
            return false;
        }

        $superFor = $child['superuser_for'] ?? [];
        if (is_array($superFor) && in_array($user['roles'], $superFor, true) && $user['superuser'] !== 'Y') {
            // QA/QI need SuperUser=Y for some tracking items (legacy menu)
            if (!in_array($user['roles'], ['SU', 'D', 'CA'], true)) {
                return false;
            }
        }

        return $this->roleMatches($roles, $user);
    }

    /** @param list<string> $roles */
    private function roleMatches(array $roles, array $user): bool
    {
        if ($user['roles'] === 'SU' || $user['roles'] === 'D') {
            return true;
        }
        return in_array($user['roles'], $roles, true);
    }
}
