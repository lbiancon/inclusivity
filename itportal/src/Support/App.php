<?php

declare(strict_types=1);

namespace ItPortal\Support;

use ItPortal\Auth\AuthService;
use ItPortal\Auth\Session;
use ItPortal\Config;

/**
 * Tiny service locator used by pages and modules.
 * Services are created lazily — never load the whole codebase on every request.
 */
final class App
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    private function __construct(
        private readonly string $root,
        private readonly Session $session,
        private readonly AuthService $auth,
    ) {
        $this->registerDefaults();
    }

    public static function boot(string $root, Session $session, AuthService $auth): self
    {
        self::$instance = new self($root, $session, $auth);
        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App is not booted. Require bootstrap.php first.');
        }
        return self::$instance;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function auth(): AuthService
    {
        return $this->auth;
    }

    /**
     * Register a lazy factory. Overwrites are allowed for tests.
     *
     * @param callable(self): mixed $factory
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->shared[$id]);
    }

    /**
     * Resolve a service by id (e.g. tracking.serial).
     * Only that module class is autoloaded.
     */
    public function make(string $id): mixed
    {
        if (array_key_exists($id, $this->shared)) {
            return $this->shared[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \InvalidArgumentException("Unknown service [{$id}]. Register it in Support\\App or config/services.php.");
        }

        $service = ($this->factories[$id])($this);
        $this->shared[$id] = $service;
        return $service;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->shared);
    }

    private function registerDefaults(): void
    {
        $map = require $this->root . '/config/services.php';
        foreach ($map as $id => $factory) {
            if (!is_callable($factory)) {
                throw new \RuntimeException("Service [{$id}] factory must be callable.");
            }
            $this->bind($id, $factory);
        }
    }
}
