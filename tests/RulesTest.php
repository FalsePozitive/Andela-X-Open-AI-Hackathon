<?php
declare(strict_types=1);
use Passport\Rules;
use Passport\Problem;
use Passport\Demo;
use PHPUnit\Framework\TestCase;

final class RulesTest extends TestCase
{
    public function testIdsHaveAnUnambiguousAlphabet(): void
    {
        $ids = [];
        for ($i = 0; $i < 500; $i++) {
            $id = Rules::storyId();
            self::assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}$/', $id);
            self::assertArrayNotHasKey($id, $ids);
            $ids[$id] = true;
        }
    }

    public function testAllocationPreservesTotalsForRandomSplits(): void
    {
        mt_srand(1907);
        for ($i = 0; $i < 500; $i++) {
            $first = mt_rand(0, 10000);
            $second = mt_rand(0, 10000 - $first);
            $shares = [['contributorId' => 'a','bps' => $first],['contributorId' => 'b','bps' => $second],['contributorId' => 'c','bps' => 10000-$first-$second]];
            $amount = [1,7,100,9999,Rules::MAX_MONEY,mt_rand(0,10000000)][$i % 6];
            $allocation = Rules::allocate($amount, $shares);
            self::assertSame($amount, array_sum($allocation));
            self::assertGreaterThanOrEqual(0, min($allocation));
        }
    }

    public function testRemainderGoesToLargestShareWithStableTieBreak(): void
    {
        self::assertSame(['b'=>0,'a'=>1,'c'=>0], Rules::allocate(1, [['contributorId'=>'b','bps'=>4000],['contributorId'=>'a','bps'=>4000],['contributorId'=>'c','bps'=>2000]]));
        self::assertSame(['a'=>0,'b'=>1], Rules::allocate(1, [['contributorId'=>'a','bps'=>0],['contributorId'=>'b','bps'=>10000]]));
    }

    public function testInvalidSplitsAndAmountsAreRejected(): void
    {
        foreach ([[], [['contributorId'=>'a','bps'=>9999]], [['contributorId'=>'a','bps'=>10000.0]], [['contributorId'=>'a','bps'=>-1]], [['contributorId'=>'a','bps'=>5000],['contributorId'=>'a','bps'=>5000]]] as $split) {
            try { Rules::split($split); self::fail('Accepted invalid split'); } catch (Problem $e) { self::assertSame('INVALID_SPLIT', $e->errorCode); }
        }
        $this->expectException(Problem::class);
        Rules::allocate(-1, [['contributorId'=>'a','bps'=>10000]]);
    }

    public function testMissingCommercialConsentBlocksApproval(): void
    {
        $story = ['license'=>['aiTrainingAllowed'=>false], 'contributors'=>[['name'=>'Person','consents'=>[]]]];
        $reasons = Rules::approvalReasons(['isCommercial'=>true,'isAiTraining'=>false,'intendedUse'=>'Print a booklet'], $story);
        self::assertCount(1, $reasons);
        $story['contributors'][0]['consents']['COMMERCIAL']['granted'] = false;
        self::assertCount(1, Rules::approvalReasons(['isCommercial'=>true,'isAiTraining'=>false,'intendedUse'=>'Print a booklet'], $story));
    }

    public function testAiRequiresBothTermsAndEveryConsent(): void
    {
        $story = ['license'=>['aiTrainingAllowed'=>false], 'contributors'=>[['name'=>'Person','consents'=>[]]]];
        $request = ['isCommercial'=>false,'isAiTraining'=>true,'intendedUse'=>'Research'];
        self::assertCount(2, Rules::approvalReasons($request,$story));
        $story['license']['aiTrainingAllowed'] = true;
        self::assertCount(1, Rules::approvalReasons($request,$story));
        $story['contributors'][0]['consents']['AI_TRAINING']['granted'] = true;
        self::assertSame([],Rules::approvalReasons($request,$story));
        foreach (['train', 'training data', 'dataset', 'fine-tune', 'LLM', 'model'] as $phrase) self::assertTrue(Rules::aiIntent($phrase));
        self::assertFalse(Rules::aiIntent('Print a school booklet'));
    }

    public function testRestrictedProjectionIsMinimal(): void
    {
        $story = Demo::payload();
        $story += ['id'=>'ABCD-EFGH','status'=>'UNVERIFIED','createdAt'=>'2026-01-01','filePath'=>'secret','contentHash'=>'secret'];
        $story['sensitivity'] = 'RESTRICTED';
        self::assertSame(['id','sensitivity','status','createdAt','creator','contactEmail'],array_keys(Rules::publicStory($story)));
        self::assertSame(['name','type'],array_keys(Rules::publicStory($story)['creator']));
    }

    public function testHtmlEscapingPreventsScriptInjection(): void
    {
        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', Passport\Web::escape('<script>alert("x")</script>'));
    }
}
