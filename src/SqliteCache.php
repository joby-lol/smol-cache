<?php

/**
 * smolCache
 * https://github.com/joby-lol/smol-cache
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Cache;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Cache implementation that keeps all data in a single SQLite database file.
 */
class SqliteCache implements CacheInterface
{

    protected PDO $pdo;

    /**
     * @param string $file_path
     * @param int $ttl
     * @param int $cleanup_odds Odds (in the form of 1 in X) of cleaning up expired items on each instantiation. Set to 0 to disable automatic cleanup.
     */
    public function __construct(
        protected string $file_path,
        protected int $ttl = 300,
        int $cleanup_odds = 0,
    )
    {
        $this->pdo = $this->initializePdo($file_path);
        // Randomly clean up expired items based on the given odds
        if ($cleanup_odds > 0 && random_int(1, $cleanup_odds) == 1)
            $this->clean();
    }

    /**
     * @inheritDoc
     */
    public function clear(string|array $tags, bool $recursive = false): static
    {
        $tags = is_array($tags) ? $tags : [$tags];
        if ($recursive) {
            foreach ($tags as $tag) {
                $this->clearRecursiveStatement()->execute([
                    ':tag'        => $tag,
                    ':tag_prefix' => $tag . '/%',
                ]);
            }
        }
        else {
            foreach ($tags as $tag) {
                $this->clearStatement()->execute([
                    ':tag' => $tag,
                ]);
            }
        }
        return $this;
    }

    /**
     * Completely flush the cache, deleting all items.
     */
    public function flush(): static
    {
        $this->pdo->exec("DELETE FROM cache_data");
        return $this;
    }

    /**
     * Compact the database file, reclaiming unused storage space.
     * 
     * WARNING: This can be slow and locks the database during the operation.
     */
    public function compact(): static
    {
        $this->pdo->exec("VACUUM;");
        return $this;
    }

    /**
     * Remove all expired items from the cache.
     */
    public function clean(): static
    {
        $this->pdo
            ->prepare("DELETE FROM cache_data WHERE expires <= :now")
            ->execute([':now' => time()]);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key, bool $recursive = false): static
    {
        $this->deleteStatement()->execute([
            ':key' => $key,
        ]);
        if ($recursive) {
            $this->deleteRecursiveStatement()->execute([
                ':key_prefix' => $key . '/%',
            ]);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null, int|null $ttl = null, string|array $tags = []): mixed
    {
        // Prepare and execute the statement
        $statement = $this->getStatement();
        $result = $statement->execute([
            ':key' => $key,
            ':now' => time(),
        ]);
        if ($result === false)
            throw new RuntimeException('Failed to execute statement to get cache item');
        $result = $statement->fetchColumn();
        // If no row found, return default null or set default value
        if ($result === false) {
            if ($default === null) {
                return null;
            }
            else {
                return $this->set($key, $default, $ttl, $tags);
            }
        }
        // Decode and return the value
        if (!is_string($result))
            throw new RuntimeException('Cache item value is not a string');
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $statement = $this->hasStatement();
        $statement->execute([
            ':key' => $key,
            ':now' => time(),
        ]);
        return $statement->fetchColumn() !== false;
    }

    /**
     * @inheritDoc
     */
    public function namespace(string $namespace, string|array $tags = [], int|null $ttl = null): CacheNamespace
    {
        return new CacheNamespace(
            $this,
            $namespace,
            $tags,
            $ttl,
        );
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int|null $ttl = null, string|array $tags = []): mixed
    {
        if (is_callable($value)) {
            $value = $value();
        }
        $this->insertValueStatement()->execute([
            ':key'     => $key,
            ':value'   => json_encode($value, JSON_THROW_ON_ERROR),
            ':expires' => time() + ($ttl ?? $this->ttl),
        ]);
        $tags = is_array($tags) ? $tags : [$tags];
        $tags = array_unique($tags);
        foreach ($tags as $tag) {
            $this->insertTagStatement()->execute([
                ':tag' => $tag,
                ':key' => $key,
            ]);
        }
        return $value;
    }

    protected function initializePdo(string $file_path): PDO
    {
        // First check if file exists, so that we can initialize the schema if necessary
        $existing = file_exists($file_path);
        // Create and configure the PDO
        $pdo = new PDO('sqlite:' . $file_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Enable foreign keys (required for cascading deletes)
        $pdo->exec("PRAGMA foreign_keys = ON;");
        // WAL mode for better concurrency
        $pdo->exec("PRAGMA journal_mode = WAL;");
        // Relaxed durability for better performance (at the cost of some durability in case of crashes)
        $pdo->exec("PRAGMA synchronous = NORMAL;");
        // Keep temporary tables in memory
        $pdo->exec("PRAGMA temp_store = MEMORY;");
        // If the file didn't exist, initialize the schema
        if (!$existing)
            $this->initializeDatabase($pdo);
        return $pdo;
    }

    /**
     * Do initial setup on a brand new database file, creating necessary tables and indexes.
     */
    protected function initializeDatabase(PDO $pdo): void
    {
        // Main data table
        $pdo->exec("
            CREATE TABLE cache_data (
                key TEXT PRIMARY KEY,
                value BLOB NOT NULL,
                expires INTEGER NOT NULL
            );
            ");
        // Tags table
        $pdo->exec("
            CREATE TABLE cache_tags (
                tag TEXT NOT NULL,
                key TEXT NOT NULL,
                PRIMARY KEY (tag, key),
                FOREIGN KEY (key) REFERENCES cache_data(key) ON DELETE CASCADE
            );
            ");
        // Indexes
        $pdo->exec("CREATE INDEX idx_expires ON cache_data (expires);");
        $pdo->exec("CREATE INDEX idx_tag ON cache_tags (tag, key);");
    }

    protected function getStatement(): PDOStatement
    {
        return $this->pdo->prepare("
            SELECT value FROM cache_data
            WHERE key = :key AND expires > :now;
        ");
    }

    protected function insertValueStatement(): PDOStatement
    {
        return $this->pdo->prepare("
            INSERT OR REPLACE INTO cache_data (key, value, expires)
            VALUES (:key, :value, :expires);
        ");
    }

    protected function insertTagStatement(): PDOStatement
    {
        return $this->pdo->prepare("
            INSERT OR REPLACE INTO cache_tags (tag, key)
            VALUES (:tag, :key);
        ");
    }

    protected function deleteStatement(): PDOStatement
    {
        return $this->pdo->prepare(
            "DELETE FROM cache_data WHERE key = :key;",
        );
    }

    protected function deleteRecursiveStatement(): PDOStatement
    {
        return $this->pdo->prepare(
            "DELETE FROM cache_data WHERE key LIKE :key_prefix;",
        );
    }

    protected function clearStatement(): PDOStatement
    {
        return $this->pdo->prepare("
            DELETE FROM cache_data
            WHERE key IN (
                SELECT key FROM cache_tags WHERE tag = :tag
            );
        ");
    }

    protected function clearRecursiveStatement(): PDOStatement
    {
        return $this->pdo->prepare("
            DELETE FROM cache_data
            WHERE key IN (
                SELECT key FROM cache_tags WHERE tag = :tag OR tag LIKE :tag_prefix
            );
        ");
    }

    protected function hasStatement(): PDOStatement
    {
        return $this->pdo->prepare("
            SELECT 1 FROM cache_data 
            WHERE key = :key AND expires > :now
            LIMIT 1;
        ");
    }

}
