<?php
declare(strict_types=1);
namespace Passport;

final class Rules
{
    public const PURPOSES = ['RECORD', 'PUBLISH', 'TRANSLATE', 'COMMERCIAL', 'AI_TRAINING'];
    public const PRESETS = ['FREE_WITH_CREDIT', 'EDUCATION_FREE_COMMERCIAL_BY_PERMISSION', 'ALL_BY_PERMISSION'];
    public const MAX_MONEY = 1000000000;

    public static function storyId(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    public static function split(array $shares): void
    {
        if ($shares === [] || count($shares) > 50) {
            throw new Problem('INVALID_SPLIT', 'Add between one and fifty contributors.');
        }
        $total = 0;
        $ids = [];
        foreach ($shares as $share) {
            if (!is_array($share) || !isset($share['contributorId'], $share['bps']) || !is_string($share['contributorId']) || !is_int($share['bps']) || $share['bps'] < 0 || $share['bps'] > 10000 || isset($ids[$share['contributorId']])) {
                throw new Problem('INVALID_SPLIT', 'Shares must be unique, nonnegative whole basis points.');
            }
            $ids[$share['contributorId']] = true;
            $total += $share['bps'];
        }
        if ($total !== 10000) {
            throw new Problem('INVALID_SPLIT', 'Shares must total exactly 100%.');
        }
    }

    public static function allocate(int $amount, array $shares): array
    {
        self::split($shares);
        if ($amount < 0 || $amount > self::MAX_MONEY) {
            throw new Problem('INVALID_AMOUNT', 'MOCK amounts must be between 0 and 1,000,000,000 minor units.');
        }
        $result = [];
        foreach ($shares as $share) {
            $result[$share['contributorId']] = intdiv($amount * $share['bps'], 10000);
        }
        // Descending share, then ascending stable contributor ID. At most n-1
        // remainder units exist; each eligible share receives one in that order.
        usort($shares, static fn(array $a, array $b): int => ($b['bps'] <=> $a['bps']) ?: strcmp($a['contributorId'], $b['contributorId']));
        $remaining = $amount - array_sum($result);
        foreach ($shares as $share) {
            if ($remaining === 0) {
                break;
            }
            if ($share['bps'] > 0) {
                $result[$share['contributorId']]++;
                $remaining--;
            }
        }
        return $result;
    }

    public static function aiIntent(string $text): bool
    {
        // Conservative heuristic: false positives (e.g. train travel) and
        // false negatives are possible. This is a review flag, never proof.
        return preg_match('/\b(train(?:ing)?|dataset|fine[ -]?tun(?:e|ing)|llms?|models?)\b/iu', $text) === 1;
    }

    public static function approvalReasons(array $request, array $story): array
    {
        $reasons = [];
        $ai = $request['isAiTraining'] || self::aiIntent($request['intendedUse']);
        if ($ai && !$story['license']['aiTrainingAllowed']) {
            $reasons[] = 'The declared terms do not allow AI training.';
        }
        foreach ($story['contributors'] as $person) {
            if ($request['isCommercial'] && !($person['consents']['COMMERCIAL']['granted'] ?? false)) {
                $reasons[] = sprintf(Copy::text('%s has not consented to commercial use.'), $person['name']);
            }
            if ($ai && !($person['consents']['AI_TRAINING']['granted'] ?? false)) {
                $reasons[] = sprintf(Copy::text('%s has not consented to AI training.'), $person['name']);
            }
        }
        return $reasons;
    }

    public static function publicStory(array $story): array
    {
        $minimal = [
            'id' => $story['id'], 'sensitivity' => $story['sensitivity'],
            'status' => $story['status'], 'createdAt' => $story['createdAt'],
            'creator' => ['name' => $story['creator']['name'], 'type' => $story['creator']['type']],
            'contactEmail' => $story['license']['contactEmail'],
        ];
        if ($story['sensitivity'] === 'RESTRICTED') {
            return $minimal;
        }
        // Explicit allowlist: never serialize the internal record to the public.
        return $minimal + [
            'title' => $story['title'], 'mediaType' => $story['mediaType'],
            'textBody' => $story['textBody'], 'mediaUrl' => $story['filePath'] ? '/media/' . $story['id'] : null,
            'language' => $story['language'], 'placeCoarse' => $story['placeCoarse'],
            'contextNote' => $story['contextNote'], 'license' => $story['license'],
            'contributors' => $story['contributors'], 'shares' => $story['shares'],
        ];
    }

    public static function license(string $preset, bool $ai): array
    {
        $lines = match ($preset) {
            'FREE_WITH_CREDIT' => ['Use and share with credit to the listed creators, subject to each person’s recorded consent.'],
            'EDUCATION_FREE_COMMERCIAL_BY_PERMISSION' => ['Educational use is free with credit, subject to recorded consent.', 'Ask the stewards before commercial use.'],
            default => ['Ask the stewards before using this story.'],
        };
        $lines[] = $ai ? 'AI training also requires every contributor’s specific consent.' : 'AI training is not allowed under these declared terms.';
        return array_map(Copy::text(...), $lines);
    }
}
