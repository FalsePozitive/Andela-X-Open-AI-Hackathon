<?php
declare(strict_types=1);
namespace Passport;

final class Demo
{
    public static function payload(string $title = 'Placeholder story: a day of making', bool $commercial = true): array
    {
        $consent = array_fill_keys(Rules::PURPOSES, true);
        $consent['AI_TRAINING'] = false;
        return ['title' => $title, 'mediaType' => 'text', 'textBody' => 'This is placeholder text for a software demonstration. It does not represent a tradition, a real community, or a historical account.', 'language' => 'English', 'placeCoarse' => '', 'contextNote' => 'Demo data. Fictional people and organizations; placeholder content only. No cultural authenticity is claimed.', 'sensitivity' => 'OPEN', 'creator' => ['type' => 'PERSON', 'name' => 'Demo Person A', 'representativeName' => ''], 'license' => ['preset' => 'EDUCATION_FREE_COMMERCIAL_BY_PERMISSION', 'aiTrainingAllowed' => false, 'contactEmail' => 'steward@example.com', 'notes' => 'Demo data. Contact address is a placeholder.'], 'termsConfirmed' => true, 'sharesConfirmed' => true, 'contributors' => [
            ['name' => 'Demo Contributor A', 'role' => 'Story contributor', 'isCommunityFund' => false, 'consents' => array_replace($consent, ['COMMERCIAL' => $commercial]), 'bps' => 7000],
            ['name' => 'Demo Fund', 'role' => 'Fund represented by Demo Representative B', 'isCommunityFund' => true, 'consents' => $consent, 'bps' => 3000],
        ]];
    }

    public static function seed(Registry $registry, array $config): array
    {
        if ($registry->stories() !== []) {
            throw new Problem('SEED_NOT_EMPTY', 'Seed only an empty demo database. Use the guarded reset command for a fresh demo.');
        }
        $text = $registry->create(self::payload('Placeholder story: a day of making', false));
        $image = imagecreatetruecolor(900, 540);
        $background = imagecolorallocate($image, 231, 234, 217);
        $ink = imagecolorallocate($image, 22, 59, 56);
        imagefill($image, 0, 0, $background);
        imageellipse($image, 450, 220, 210, 210, $ink);
        imagestring($image, 5, 310, 390, 'DEMO PLACEHOLDER IMAGE', $ink);
        if (!is_dir($config['storage_path'])) {
            mkdir($config['storage_path'], 0700, true);
        }
        $temporary = tempnam($config['storage_path'], 'seed-');
        imagepng($image, $temporary);
        imagedestroy($image);
        $media = Media::process($temporary, 'image', $config['storage_path'] . '/uploads');
        unlink($temporary);
        // Seed uses the same registry validation for terms, consents and shares.
        // The generated local image is attached after registration, because it
        // did not arrive via HTTP's is_uploaded_file boundary.
        $data = self::payload('Placeholder image: shapes for a demo');
        $data['creator'] = ['type' => 'COMMUNITY', 'name' => 'Demo Story Group (fictional)', 'representativeName' => 'Demo Representative B'];
        $data['license']['preset'] = 'FREE_WITH_CREDIT';
        $picture = $registry->create($data);
        Database::run($registry->db, 'UPDATE stories SET media_type=\'image\',text_body=NULL,file_path=?,mime_type=?,content_hash=? WHERE id=?', [$media['filePath'], $media['mimeType'], $media['contentHash'], $picture['id']]);
        $data = self::payload('Private placeholder title');
        $data['creator'] = ['type' => 'COMMUNITY', 'name' => 'Demo Story Group (fictional)', 'representativeName' => 'Demo Representative B'];
        $data['sensitivity'] = 'RESTRICTED';
        $data['license']['preset'] = 'ALL_BY_PERMISSION';
        $data['placeCoarse'] = 'PRIVATE PLACE';
        $restricted = $registry->create($data, null, $picture['creatorId']);
        $registry->request($text['id'], ['requesterName' => 'Demo Publisher', 'requesterContact' => 'publisher@example.com', 'intendedUse' => 'Print a commercial booklet', 'isCommercial' => true, 'isAiTraining' => false, 'proposedFeeMinor' => 10001, 'currency' => 'ZAR']);
        $registry->request($picture['id'], ['requesterName' => 'Demo Researcher', 'requesterContact' => 'researcher@example.com', 'intendedUse' => 'Build a model training dataset', 'isCommercial' => false, 'isAiTraining' => true, 'currency' => 'ZAR']);
        $approved = $registry->request($picture['id'], ['requesterName' => 'Demo Publisher B', 'requesterContact' => 'publisher-b@example.com', 'intendedUse' => 'Commercial brochure illustration', 'isCommercial' => true, 'proposedFeeMinor' => 9999, 'currency' => 'ZAR']);
        $registry->decide($approved['id'], ['status' => 'APPROVED', 'finalFeeMinor' => 9999, 'feeConfirmed' => true, 'note' => 'MOCK demonstration only']);
        return ['text' => $text['id'], 'image' => $picture['id'], 'restricted' => $restricted['id']];
    }
}
