<?php

namespace App\Services;

/**
 * Resolves file conflicts without performing side effects.
 */
class ConflictResolver
{
    public function __construct(
        private array $fileDecisions,
        private bool $overwriteAll,
        private bool $skipAll,
    ) {}

    /**
     * Decide what to do with destination file.
     */
    public function resolve(
        string $src,
        string $dst,
        ?int $offset = null,
        bool $exists = true
    ): ConflictResult
    {
        if (!$exists) {
            return ConflictResult::proceed($dst);
        }

        $decisionData = $this->fileDecisions[$src] ?? null;

        $decision = null;
        $newName  = null;

        if (is_array($decisionData)) {
            $decision = $decisionData['action'] ?? null;
            $newName  = $decisionData['name'] ?? null;
        } else {
            $decision = $decisionData;
        }

        if ($this->overwriteAll) {
            $decision = 'overwrite';
        }

        if ($this->skipAll) {
            $decision = 'skip';
        }

        return match (true) {
            $decision === 'overwrite' => ConflictResult::overwrite($dst),
            $decision === 'skip'      => ConflictResult::skip(),
            $decision === 'rename' && $newName =>
                ConflictResult::rename(dirname($dst) . '/' . $newName),

            $decision === null && ($offset ?? 0) === 0 =>
                ConflictResult::conflict($src, $dst),

            default => ConflictResult::proceed($dst),
        };
    }
}