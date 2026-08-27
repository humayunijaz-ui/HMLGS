<?php
/**
 * Minimal, dependency-free SMTP mail client.
 * Supports STARTTLS / implicit SSL, AUTH LOGIN, and HTML bodies.
 *
 * Usage:
 *   $mailer = new SmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION);
 *   $mailer->send($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody);
 */
class SmtpMailer
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption; // 'tls', 'ssl', or ''
    private $timeout = 15;
    public $lastError = '';

    public function __construct($host, $port, $username, $password, $encryption = 'tls')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }

    public function send($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody)
    {
        $this->lastError = '';
        $transport = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $sock = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno, $errstr, $this->timeout
        );

        if (!$sock) {
            $this->lastError = "Connection failed: {$errstr} ({$errno})";
            return false;
        }
        stream_set_timeout($sock, $this->timeout);

        try {
            $this->expect($sock, '220');
            $this->command($sock, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');

            if ($this->encryption === 'tls') {
                $this->command($sock, "STARTTLS", '220');
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Failed to enable TLS encryption.');
                }
                $this->command($sock, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
            }

            if ($this->username !== '') {
                $this->command($sock, "AUTH LOGIN", '334');
                $this->command($sock, base64_encode($this->username), '334');
                $this->command($sock, base64_encode($this->password), '235');
            }

            $this->command($sock, "MAIL FROM:<{$fromEmail}>", '250');
            $this->command($sock, "RCPT TO:<{$toEmail}>", ['250', '251']);
            $this->command($sock, "DATA", '354');

            $boundary = md5(uniqid((string)time(), true));
            $headers = [];
            $headers[] = "From: " . $this->encodeHeader($fromName) . " <{$fromEmail}>";
            $headers[] = "To: " . $this->encodeHeader($toName) . " <{$toEmail}>";
            $headers[] = "Subject: " . $this->encodeHeader($subject);
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
            $headers[] = "Date: " . date('r');

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $this->stuffDots($htmlBody) . "\r\n.";
            $this->command($sock, $data, '250');

            $this->command($sock, "QUIT", '221');
            fclose($sock);
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            @fclose($sock);
            return false;
        }
    }

    private function stuffDots($body)
    {
        // RFC 5321 dot-stuffing: lines starting with "." get an extra "."
        return preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $body));
    }

    private function encodeHeader($value)
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function readLine($sock)
    {
        $line = fgets($sock, 515);
        if ($line === false) {
            throw new Exception('Connection to SMTP server lost while reading response.');
        }
        return $line;
    }

    private function expect($sock, $codes)
    {
        $codes = (array)$codes;
        $response = '';
        do {
            $line = $this->readLine($sock);
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-'); // multi-line response continues with "-"

        $code = substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new Exception("Unexpected SMTP response: {$response}");
        }
        return $response;
    }

    private function command($sock, $command, $expectedCodes)
    {
        fwrite($sock, $command . "\r\n");
        return $this->expect($sock, $expectedCodes);
    }
}
