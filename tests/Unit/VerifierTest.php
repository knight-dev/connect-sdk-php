<?php

declare(strict_types=1);

namespace Logicware\Connect\Tests\Unit;

use Logicware\Connect\Webhooks\Verifier;
use Logicware\Connect\Webhooks\WebhookVerificationException;
use PHPUnit\Framework\TestCase;

final class VerifierTest extends TestCase
{
    private const SECRET = 'test_webhook_secret';

    public function testReturnsTypedEventOnValidSignedPayload(): void
    {
        ['timestamp' => $ts, 'body' => $body, 'signature' => $sig] = $this->freshEvent();

        $event = Verifier::verify($body, $sig, $ts, self::SECRET);

        $this->assertSame('package.received', $event['event']);
    }

    public function testAcceptsBareHexSignature(): void
    {
        ['timestamp' => $ts, 'body' => $body, 'signature' => $sig] = $this->freshEvent();
        $bare = preg_replace('/^sha256=/', '', $sig);
        $this->assertIsString($bare);

        $event = Verifier::verify($body, $bare, $ts, self::SECRET);

        $this->assertSame('package.received', $event['event']);
    }

    public function testRejectsTamperedBody(): void
    {
        ['timestamp' => $ts, 'body' => $body, 'signature' => $sig] = $this->freshEvent();
        $this->expectException(WebhookVerificationException::class);
        Verifier::verify($body . ' ', $sig, $ts, self::SECRET);
    }

    public function testRejectsOldTimestamp(): void
    {
        $old = (string) (time() - 1000);
        $body = json_encode([
            'event' => 'package.received',
            'timestamp' => date('c'),
            'companyId' => 'c1',
            'companySlug' => 't',
            'data' => [],
        ], JSON_THROW_ON_ERROR);
        $sig = $this->sign($old, $body);

        try {
            Verifier::verify($body, $sig, $old, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::TIMESTAMP_OUT_OF_TOLERANCE, $e->errorCode);
        }
    }

    public function testRejectsMissingSignature(): void
    {
        ['timestamp' => $ts, 'body' => $body] = $this->freshEvent();
        try {
            Verifier::verify($body, null, $ts, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::MISSING_SIGNATURE, $e->errorCode);
        }
    }

    public function testRejectsMissingTimestamp(): void
    {
        ['body' => $body, 'signature' => $sig] = $this->freshEvent();
        try {
            Verifier::verify($body, $sig, null, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::MISSING_TIMESTAMP, $e->errorCode);
        }
    }

    public function testRejectsWrongSecret(): void
    {
        ['timestamp' => $ts, 'body' => $body] = $this->freshEvent();
        $badSig = $this->sign($ts, $body, 'wrong_secret');
        try {
            Verifier::verify($body, $badSig, $ts, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::SIGNATURE_MISMATCH, $e->errorCode);
        }
    }

    public function testRejectsReplayAcrossTimestamps(): void
    {
        $now = (string) time();
        $earlier = (string) ((int) $now - 5);
        $body = json_encode([
            'event' => 'package.received',
            'timestamp' => date('c'),
            'companyId' => 'c1',
            'companySlug' => 't',
            'data' => [],
        ], JSON_THROW_ON_ERROR);
        $sigForEarlier = $this->sign($earlier, $body);

        try {
            Verifier::verify($body, $sigForEarlier, $now, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::SIGNATURE_MISMATCH, $e->errorCode);
        }
    }

    public function testCustomToleranceSeconds(): void
    {
        $past = (string) (time() - 60);
        $body = json_encode([
            'event' => 'package.received',
            'timestamp' => date('c'),
            'companyId' => 'c1',
            'companySlug' => 't',
            'data' => [],
        ], JSON_THROW_ON_ERROR);
        $sig = $this->sign($past, $body);

        // Default 300s — 60s ago is fine.
        $event = Verifier::verify($body, $sig, $past, self::SECRET);
        $this->assertSame('package.received', $event['event']);

        // 30s — 60s ago is too old.
        try {
            Verifier::verify($body, $sig, $past, self::SECRET, toleranceSeconds: 30);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::TIMESTAMP_OUT_OF_TOLERANCE, $e->errorCode);
        }
    }

    public function testRejectsNonEnvelopeBody(): void
    {
        $now = (string) time();
        $body = json_encode(['notAnEnvelope' => true], JSON_THROW_ON_ERROR);
        $sig = $this->sign($now, $body);

        try {
            Verifier::verify($body, $sig, $now, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::INVALID_PAYLOAD, $e->errorCode);
        }
    }

    public function testRejectsMalformedSignatureHeader(): void
    {
        ['timestamp' => $ts, 'body' => $body] = $this->freshEvent();
        try {
            Verifier::verify($body, 'not-a-signature', $ts, self::SECRET);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertSame(WebhookVerificationException::INVALID_SIGNATURE_FORMAT, $e->errorCode);
        }
    }

    /**
     * @return array{timestamp: string, body: string, signature: string}
     */
    private function freshEvent(): array
    {
        $ts = (string) time();
        $body = json_encode([
            'event' => 'package.received',
            'timestamp' => date('c'),
            'companyId' => 'c1',
            'companySlug' => 'test',
            'data' => ['packageId' => 'p1'],
        ], JSON_THROW_ON_ERROR);
        return [
            'timestamp' => $ts,
            'body' => $body,
            'signature' => $this->sign($ts, $body),
        ];
    }

    private function sign(string $timestamp, string $body, string $secret = self::SECRET): string
    {
        $canonical = $timestamp . '.' . $body;
        return 'sha256=' . hash_hmac('sha256', $canonical, $secret);
    }
}
