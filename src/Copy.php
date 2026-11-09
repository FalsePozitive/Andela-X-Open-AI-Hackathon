<?php
declare(strict_types=1);
namespace Passport;

final class Copy
{
    public static function text(string $key): string
    {
        static $catalog;
        $catalog ??= require dirname(__DIR__) . '/resources/copy.php';
        return str_replace(['MOCK', 'Mock', 'mock'], ['Demo', 'Demo', 'demo'], self::UI[$key] ?? $catalog[$key] ?? $key);
    }
    public const LIMITS = 'These are terms set by the story’s stewards. They are a declared record, not a legal contract. Registration records a claim, not proof of ownership.';
    public const AI = 'This request may involve AI training. Review carefully. Automated flags can miss uses or flag unrelated wording.';
    public const RESTRICTED = 'This story is held with care. Its content, context and location are private. Contact the steward to ask about access.';
    public const STATUS = ['UNVERIFIED' => 'Unverified claim', 'AUTHOR_REVIEWED' => 'Author reviewed · declared review', 'COMMUNITY_ENDORSED' => 'Community endorsed · declared endorsement'];
    public const PURPOSES = ['RECORD' => 'Record', 'PUBLISH' => 'Publish', 'TRANSLATE' => 'Translate', 'COMMERCIAL' => 'Commercial use', 'AI_TRAINING' => 'AI training'];
    public const PRESETS = ['FREE_WITH_CREDIT' => 'Free with credit', 'EDUCATION_FREE_COMMERCIAL_BY_PERMISSION' => 'Education free · ask for commercial use', 'ALL_BY_PERMISSION' => 'Ask before any use'];
    public static function ledgerKind(string $kind): string
    {
        return $kind === 'MOCK_PAYMENT' ? 'Demo allocation' : 'Demo correction';
    }
    public static function ledgerNote(string $note): string
    {
        return str_starts_with($note, 'MOCK ') ? 'Demo · ' . substr($note, 5) : $note;
    }
    public const UI = [
        'brand' => 'Okwethu', 'subtitle' => 'Our stories. Our say. Our future.',
        'register' => 'Register a story', 'passport' => 'Open passport', 'dashboard' => 'Steward desk',
        'requests' => 'Permission inbox', 'ledger' => 'Community ledger', 'claims' => 'Conflicting claims',
        'restricted' => 'Restricted story', 'login' => 'Steward sign in', 'logout' => 'Sign out',
        'saved' => 'Saved. The record is up to date.', 'requestSaved' => 'Your request has been recorded. The steward will contact you using the email you provided.',
        'claimSaved' => 'Your report has been recorded for the steward to review.',
        'empty' => 'Your next story starts here.', 'demo' => 'Demo · no real money moves',
        'brandMeaning' => 'Ours. Together.', 'paymentDemo' => 'Demo · no real money moves',
    ];
    public const CLIENT = [
        'serverError' => 'The server could not complete this request. Please try again.',
        'formError' => 'Please check the form and try again.',
        'aiFlag' => 'This request may involve AI training and needs careful review.',
        'total' => 'Total', 'basisPoints' => 'basis points', 'minorUnits' => 'minor units',
        'previewNote' => 'Equal-share rounding ties use contributor order, retained in the saved contributor IDs.',
        'terms' => 'Terms', 'aiTraining' => 'AI training', 'aiAllowed' => 'allowed subject to consent', 'aiNotAllowed' => 'not allowed',
        'stewardContact' => 'Steward contact', 'granted' => 'Granted', 'none' => 'none',
        'addRepresentative' => 'Add a community representative.', 'addText' => 'Add the story text.',
        'addFile' => 'Choose a media file.', 'splitTotal' => 'Shares must total exactly 10,000 basis points.',
        'amountLabel' => 'Demo',
    ];
}
