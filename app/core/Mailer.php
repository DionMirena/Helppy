<?php
declare(strict_types=1);

final class Mailer {
    /** Send a plain-text email via SMTP. Throws RuntimeException on any failure. */
    public static function send(string $to, string $subject, string $bodyText): bool {
        $cfg = CONFIG['mailer'];
        if (($cfg['username'] ?? '') === '' || ($cfg['password'] ?? '') === '') {
            throw new RuntimeException('Mailer not configured (mailer.username/password empty in config.php)');
        }

        $sock = @stream_socket_client(
            "tcp://{$cfg['host']}:{$cfg['port']}",
            $errno, $errstr,
            (float)($cfg['timeout'] ?? 10),
            STREAM_CLIENT_CONNECT
        );
        if (!$sock) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($sock, (int)($cfg['timeout'] ?? 10));

        try {
            self::readResponse($sock, 220);
            self::cmd($sock, "EHLO helppy.com", 250);

            if ((int)$cfg['port'] === 587) {
                self::cmd($sock, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException("STARTTLS handshake failed");
                }
                self::cmd($sock, "EHLO helppy.com", 250);
            }

            self::cmd($sock, "AUTH LOGIN", 334);
            self::cmd($sock, base64_encode($cfg['username']), 334);
            self::cmd($sock, base64_encode($cfg['password']), 235);

            $fromAddr = self::extractAddr($cfg['from']);
            self::cmd($sock, "MAIL FROM:<$fromAddr>", 250);
            self::cmd($sock, "RCPT TO:<$to>", [250, 251]);
            self::cmd($sock, "DATA", 354);

            $headers = [
                'From: ' . $cfg['from'],
                'To: ' . $to,
                'Subject: ' . self::encodeHeader($subject),
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@helppy.com>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            if (!empty($cfg['reply_to'])) $headers[] = 'Reply-To: ' . $cfg['reply_to'];

            $body = preg_replace('/^\./m', '..', $bodyText);

            fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            self::readResponse($sock, 250);

            self::cmd($sock, "QUIT", 221);
            return true;
        } finally {
            @fclose($sock);
        }
    }

    private static function cmd($sock, string $line, $expected): string {
        fwrite($sock, $line . "\r\n");
        return self::readResponse($sock, $expected);
    }

    private static function readResponse($sock, $expected): string {
        $resp = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) throw new RuntimeException("SMTP read failed");
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        $code = (int)substr($resp, 0, 3);
        $ok = is_array($expected) ? in_array($code, $expected, true) : $code === (int)$expected;
        if (!$ok) {
            throw new RuntimeException("SMTP unexpected response: " . trim($resp));
        }
        return $resp;
    }

    private static function extractAddr(string $field): string {
        if (preg_match('/<([^>]+)>/', $field, $m)) return $m[1];
        return trim($field);
    }

    private static function encodeHeader(string $s): string {
        if (preg_match('/[\x80-\xff]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }
}
