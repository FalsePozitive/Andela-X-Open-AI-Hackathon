<?php
declare(strict_types=1);
use Passport\Web;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private function config(): array
    {
        return ['environment'=>'production','base_url'=>'https://okwethu.wilmans.co.za'];
    }
    public function testHttpRedirectsToCanonicalHttpsAndPreservesPathAndQuery():void
    {
        self::assertSame('https://okwethu.wilmans.co.za/login?return=dashboard',Web::httpsRedirect($this->config(),['HTTPS'=>'off','SERVER_PORT'=>80,'REQUEST_URI'=>'/login?return=dashboard','HTTP_HOST'=>'attacker.example','HTTP_X_FORWARDED_PROTO'=>'https']));
    }
    public function testHttpsDoesNotRedirectOrLoop():void
    {
        self::assertNull(Web::httpsRedirect($this->config(),['HTTPS'=>'on','REQUEST_URI'=>'/login']));
        self::assertNull(Web::httpsRedirect($this->config(),['HTTPS'=>'1','REQUEST_URI'=>'/login']));
        self::assertNull(Web::httpsRedirect($this->config(),['SERVER_PORT'=>443,'REQUEST_URI'=>'/login']));
    }
    public function testLocalDevelopmentCanUseHttp():void
    {
        self::assertNull(Web::httpsRedirect(['environment'=>'development','base_url'=>'http://127.0.0.1:8087'],['REQUEST_URI'=>'/login']));
    }
    public function testInvalidRequestTargetFallsBackToRoot():void
    {
        self::assertSame('https://okwethu.wilmans.co.za/',Web::httpsRedirect($this->config(),['REQUEST_URI'=>"/login\r\nX-Injected: 1"]));
    }
}
