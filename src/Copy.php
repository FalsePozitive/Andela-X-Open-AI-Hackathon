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
    public const ABOUT = [
        'eyebrow' => 'THE POINT BEHIND OKWETHU',
        'title' => 'Our stories. Our say.',
        'accent' => 'Ours, together.',
        'intro' => 'Okwethu means “Ours”. It starts with a simple idea: when a story is shared, the people behind it should have a voice in what happens next.',
        'whyTitle' => 'A story carries more than its words.',
        'why' => 'Stories can carry a person’s work, a group’s experiences, and a community’s relationships. As a story travels, its context, contributors and wishes can get left behind. Okwethu brings those details into one place, so people can ask, listen and make informed choices before using it.',
        'promiseTitle' => 'More room for community decisions.',
        'promise' => 'The app does not decide what a community should share or who may speak for it. Creators and stewards record declared choices. The conversation, the authority to make those choices, and the relationships behind them remain with people.',
        'pillars' => [
            ['title' => 'Make the people visible', 'body' => 'Name contributors, describe their roles, and keep credit and context alongside the story.'],
            ['title' => 'Make consent specific', 'body' => 'Record separate choices for recording, publishing, translation, commercial use and AI training. Permission for one purpose is not permission for every purpose.'],
            ['title' => 'Make the terms easy to find', 'body' => 'Give each story a public passport, QR code and branded PDF snapshot. QR-marked image and video exports, and an audio cover package, help the record travel with the story. Visitors can read the declared terms, ask for permission or report a conflicting claim.'],
            ['title' => 'Make the shares visible', 'body' => 'Record declared revenue shares, including a community fund where chosen, and show how an agreed demo fee would be allocated.'],
        ],
        'passportTitle' => 'A passport for the story, not proof of ownership.',
        'passport' => 'A story passport records a claim, a registration date, named contributors, declared consent and terms. Review badges record declarations made by stewards; identity, ownership and authority are not independently verified.',
        'careTitle' => 'Some stories stay close.',
        'care' => 'Restricted stories show only a minimal record and a way to contact the steward. Their title, content, context and location stay private. Sharing less can be a deliberate community choice.',
        'limitsTitle' => 'A useful record, with honest limits.',
        'limits' => 'Okwethu is a hackathon proof of concept. Its declared terms are not a legal contract, and the app does not enforce rights or settle ownership disputes. The ledger shows demo allocations: no real money moves. Community governance is bigger than any one app.',
        'ctaTitle' => 'Start with the people behind the story.',
        'cta' => 'Explore a passport, ask a respectful question, or learn how to steward a record.',
        'explore' => 'Explore the stories', 'learn' => 'Learn how Okwethu works',
    ];
    public const FAQ = [
        'title' => 'A little guidance. A clearer next step.',
        'intro' => 'Whether you are exploring a story or caring for its record, here is how to use Okwethu.',
        'sections' => [
            'visitors' => ['title' => 'For visitors and story users', 'intro' => 'You can explore passports and send requests without an account.', 'items' => [
                ['q' => 'How do QR-marked media downloads work?', 'a' => 'Images served by Okwethu have a real QR mark in their bottom-right pixels, so saving the image keeps it. For video, use Create QR-marked video: the browser draws the QR into the exported frames in real time. Keep the tab active. The output is WebM, or MP4 if supported; use a recent Chrome or Edge browser. Export supports up to 10 minutes and 50 MB of output. The original playback video is not permanently marked, so use the export button for a marked copy. For audio, Download audio + QR cover gives you a ZIP with the sound file and a QR cover image; keep them together. The QR is not part of the sound.'],
                ['q' => 'Does a QR watermark protect the story from copying?', 'a' => 'It helps people find the passport and its declared terms. It is not copy protection, proof of ownership or permission to use a story. Marks can be cropped or removed, and screenshots or original playback files can bypass them. Restricted media is not available through these downloads. Always read the current passport and obtain any required permission.'],
                ['q' => 'Where do I start?', 'a' => 'Choose Our stories in the navigation, open a passport, or scan its QR code with your phone’s camera. Read the context, contributor information and declared terms before planning to use the story.', 'link' => ['href' => '/', 'label' => 'Explore the stories']],
                ['q' => 'How do I request permission?', 'a' => 'Open the passport and find Request permission. Enter your name, email and intended use. Mark whether it is commercial or involves AI/model training. You may propose a demo fee and choose a currency. Submit the form; the acknowledgement confirms your request was recorded, not approved.'],
                ['q' => 'How will I hear back? Can I accept a counteroffer online?', 'a' => 'The steward reviews your request and contacts you using the email you supplied. Okwethu does not send automatic email or provide a visitor request-tracking account. Counteroffers are discussed outside the app; there is no visitor acceptance button. The steward records the agreed final decision.'],
                ['q' => 'What do the declared terms allow?', 'a' => 'The passport explains whether uses are free with credit, educational use is free but commercial use needs permission, or every use needs permission. Read contributor consent as well as the preset. Do not assume a visible story is available for every purpose; contact the steward when you are unsure.'],
                ['q' => 'Why can’t I see a restricted story?', 'a' => 'Its stewards have chosen to keep its title, content, context and location private. The passport shows that a record exists and how to contact the steward. Sending a request does not automatically reveal the content.'],
                ['q' => 'What if I disagree with the claim or credit?', 'a' => 'Use Report a conflicting claim on the passport. Provide your name, email and a clear explanation. The report goes privately to the steward for review. A resolved report records the steward’s review; it does not settle ownership.'],
                ['q' => 'What becomes public when I contact a steward?', 'a' => 'Your permission-request or claim-report contact details are kept in the steward’s private inbox, not displayed on the passport. The designated steward contact email is intentionally public. Share only the information needed for the request.'],
                ['q' => 'Can I download or print a passport?', 'a' => 'Use Download passport PDF for a styled Okwethu document with the logo, goldenrod colours, passport QR, declared terms, consent and percentage shares. It is a dated snapshot: scan the QR for the current record. Long text is labelled as an excerpt, and restricted PDFs keep private details hidden. Audio/video files are not embedded. Download QR PNG saves the QR separately; Print passport opens your browser’s print options.'],
            ]],
            'stewards' => ['title' => 'For stewards', 'intro' => 'A steward maintains declared records and reviews requests. Sign in using the account supplied by the site operator.', 'items' => [
                ['q' => 'How do I sign in?', 'a' => 'Open https://okwethu.wilmans.co.za/login and use your steward credentials. Always use HTTPS: the security cookie cannot work over plain HTTP. If the form says it expired, reopen the HTTPS address and try again. Contact the site operator for account help; there is no self-service password reset.', 'link' => ['href' => '/login', 'label' => 'Steward sign in']],
                ['q' => 'How do I register a story?', 'a' => 'From Steward desk, choose Register a story and work through the five steps.', 'steps' => ['Story and context: enter the title, language, creator or community, representative where needed, visibility, and text or a media file.', 'Contributors and consent: list each contributor or fund representative and record their choice for each purpose. Unchecked means not granted.', 'Declared terms: choose the license preset, decide whether AI training is allowed, and provide the steward email that may be published. Explicitly confirm the terms.', 'Revenue shares: set each contributor’s percentage, preview a demo amount, and explicitly confirm the shares.', 'Review: check the full record before registering it and opening the passport.']],
                ['q' => 'Which files can I upload?', 'a' => 'Choose text, image, audio or video. Supported uploads are JPEG, PNG, WebP, MP3, WAV, MP4 and WebM, up to 10 MB each. Images must be no more than 16 megapixels and are re-encoded to remove embedded image metadata. Use only content you are authorised to record and share.'],
                ['q' => 'How do shares and the calculator work?', 'a' => 'Enter shares as percentages that total 100%, such as 70% and 30%, with up to two decimal places. The split calculator takes a sample amount in ZAR (100 means ZAR 100.00) and previews demo allocations. Permission-request and correction fee fields still use minor units: 100 means 1.00 of the chosen currency. Remainder cents go in descending share order, with a stable contributor-order tie-break. No funds are transferred.'],
                ['q' => 'How do I update consent or a review badge?', 'a' => 'In Steward desk, choose Manage consent for the story. Record only the choices actually declared by each contributor, then choose Save declared consent. Consent changes retain a dated history. You can also choose and save a declared review status. Author reviewed and Community endorsed are manual declarations, not independent verification.'],
                ['q' => 'How do I review a permission request?', 'a' => 'Open Permission inbox. Check the intended use, the contributor consent, any AI warning and the requester’s contact details. Choose Approve, Decline or Counter. For approval, enter the final agreed demo fee, including zero for no fee, and confirm it. Noncommercial requests cannot produce a fee. For a counteroffer, enter a demo counteroffer amount and an explanatory note, then contact the requester yourself.'],
                ['q' => 'Why is an approval blocked?', 'a' => 'Commercial use needs every contributor’s commercial consent. AI training also needs the license’s AI-training flag and every contributor’s specific AI-training consent. Missing consent counts as not granted. The inbox explains what is missing. Only update consent when the contributor actually makes that choice; do not change it just to bypass a block.'],
                ['q' => 'What does the AI-training warning mean?', 'a' => 'A request is flagged when the visitor declares AI training or its wording matches a conservative keyword check. The check can flag unrelated wording or miss a use. Review the actual request carefully. A flagged request still has to meet the license and specific consent requirements before approval.'],
                ['q' => 'When does the ledger change?', 'a' => 'Approving a commercial request with a confirmed fee creates one demo allocation per declared share. Approving it again does not duplicate those allocations. Declining or countering creates no allocations. The ledger keeps contributor balances and totals separate for each currency. All amounts are simulated; no real money moves.'],
                ['q' => 'How do I correct a ledger entry?', 'a' => 'Open Community ledger, find the entry and expand Append a demo correction. Enter a signed adjustment in minor units and a reason, then append it. A negative amount reduces the recorded balance. The original entry remains visible so there is a history of the correction.'],
                ['q' => 'What happens if consent changes after approval?', 'a' => 'The new consent choice applies to later approvals. An existing approval keeps a snapshot of the terms, shares and consent recorded at that time; its ledger entries are not automatically reversed. The app does not enforce revocation outside the registry. Discuss the situation with the people involved.'],
                ['q' => 'How do I review a conflicting claim?', 'a' => 'From Steward desk, open Conflicting claims. Read the report, contact the reporter where appropriate, then record Open or Reviewed / resolved and save the review. This records your review status and does not establish who owns the story.'],
                ['q' => 'Can I edit every part of a registered story?', 'a' => 'The current steward screens support consent changes, declared review badges, permission decisions, claim review and appended ledger corrections. They do not include a general editor for story content, licenses or shares after registration. Contact the site operator before correcting those details; do not create an unexplained duplicate record.'],
            ]],
            'limits' => ['title' => 'What the record means', 'intro' => 'Useful records work best when their limits are clear.', 'items' => [
                ['q' => 'Does Okwethu prove ownership or create a legal contract?', 'a' => 'No. It records a claim and declared terms. It does not independently verify identity, community authority or ownership, enforce rights, or settle disputes. Review badges describe declared review, not verified ownership.'],
                ['q' => 'Are the displayed payments real?', 'a' => 'No. Okwethu currently demonstrates revenue allocation using a demo ledger. It has no payment-provider connection, does not collect bank details, and does not transfer funds.'],
                ['q' => 'Why are some stories labelled demo or placeholder?', 'a' => 'The starter records use fictional people, organisations and placeholder content to show the workflow. They do not represent real cultural traditions or permission to use a real community’s stories.'],
            ]],
        ],
    ];
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
        'about' => 'About', 'faq' => 'FAQ', 'aboutTitle' => 'About Okwethu',
        'faqTitle' => 'Questions, answered.', 'faqEyebrow' => 'HOW TO USE OKWETHU',
        'footerNavigation' => 'More about Okwethu', 'guideNavigation' => 'Find your guidance',
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
        'addFile' => 'Choose a media file.', 'splitTotal' => 'Shares must total exactly 100%.',
        'amountLabel' => 'Demo',
        'videoUnsupported' => 'This browser cannot create the marked video. Try a recent Chrome or Edge browser.',
        'videoLoadError' => 'The video or QR could not be loaded. Please try again.',
        'videoExportError' => 'The marked video could not be created. Please try again.',
        'videoPlayError' => 'The browser could not start playback for export. Please try again.',
        'videoTooLong' => 'Browser export supports videos up to 10 minutes long.',
        'videoTooLarge' => 'The marked video exceeded the 50 MB browser-export limit.',
        'videoCancelled' => 'Video export cancelled.',
        'videoWorking' => 'Creating QR-marked video. Keep this tab active.',
        'videoDone' => 'Your QR-marked video is ready. No permission is granted by downloading it.',
    ];
}
