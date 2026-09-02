<?php
/**
 * Standalone SMTP Mailer Class
 * Pure PHP implementation adhering to RFC 5321 and RFC 5322.
 * Supports TLS / STARTTLS / SSL with AUTH LOGIN and fallback handling.
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

class SmtpMailer {
    protected $host;
    protected $port;
    protected $secure;
    protected $timeout;
    protected $username;
    protected $password;
    protected $fromEmail;
    protected $fromName;
    protected $toEmail;
    protected $toName;
    protected $lastError = '';

    public function __construct(array $config) {
        $this->host = $config['smtp_host'] ?? 'localhost';
        $this->port = (int)($config['smtp_port'] ?? 587);
        $this->secure = strtolower($config['smtp_secure'] ?? 'tls');
        $this->timeout = (int)($config['smtp_timeout'] ?? 15);
        $this->username = $config['smtp_user'] ?? '';
        $this->password = $config['smtp_pass'] ?? '';
        $this->fromEmail = $config['from_email'] ?? 'no-reply@example.com';
        $this->fromName = $config['from_name'] ?? 'Portfolio';
        $this->toEmail = $config['to_email'] ?? '';
        $this->toName = $config['to_name'] ?? '';
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * Send email with structured data
     */
    public function send(array $data): bool {
        $senderName = trim($data['name'] ?? 'Visitor');
        $senderEmail = trim($data['email'] ?? '');
        $subject = trim($data['subject'] ?? 'New Portfolio Contact Form Submission');
        $messageText = trim($data['message'] ?? '');
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $timestamp = date('Y-m-d H:i:s T');

        if (empty($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Invalid sender email address.';
            return false;
        }

        if (empty($messageText)) {
            $this->lastError = 'Message content cannot be empty.';
            return false;
        }

        // HTML Body
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; background-color: #f8fafc; margin: 0; padding: 24px; }
    .card { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
    .header { background: #080d0f; color: #f4f2eb; padding: 24px 28px; border-bottom: 2px solid #f2a93c; }
    .header h2 { margin: 0 0 4px 0; font-size: 20px; color: #f2a93c; }
    .header p { margin: 0; font-size: 13px; color: #93a3aa; }
    .content { padding: 28px; }
    .field-row { margin-bottom: 16px; }
    .field-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #64748b; margin-bottom: 4px; }
    .field-value { font-size: 15px; color: #0f172a; font-weight: 500; }
    .field-value a { color: #0284c7; text-decoration: none; }
    .message-box { margin-top: 20px; padding: 18px 20px; background: #f1f5f9; border-left: 4px solid #f2a93c; border-radius: 4px; font-size: 14.5px; color: #334155; white-space: pre-wrap; word-break: break-word; }
    .footer { padding: 16px 28px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h2>📬 New Portfolio Inquiry</h2>
      <p>Received via akhilvv.github.io contact form</p>
    </div>
    <div class="content">
      <div class="field-row">
        <div class="field-label">Sender Name / Company</div>
        <div class="field-value">{$this->escape($senderName)}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Sender Email</div>
        <div class="field-value"><a href="mailto:{$this->escape($senderEmail)}">{$this->escape($senderEmail)}</a></div>
      </div>
      <div class="field-row">
        <div class="field-label">Subject</div>
        <div class="field-value">{$this->escape($subject)}</div>
      </div>
      <div class="field-row">
        <div class="field-label">Message</div>
        <div class="message-box">{$this->escape($messageText)}</div>
      </div>
    </div>
    <div class="footer">
      Received on {$timestamp} • IP: {$clientIp}
    </div>
  </div>
</body>
</html>
HTML;

        // Plain Text Body
        $plainBody = "=== New Portfolio Inquiry ===\n\n"
            . "From: {$senderName} <{$senderEmail}>\n"
            . "Subject: {$subject}\n"
            . "Date: {$timestamp}\n"
            . "IP Address: {$clientIp}\n\n"
            . "--- Message ---\n"
            . "{$messageText}\n\n"
            . "=============================\n";

        // Check if SMTP credentials are configured
        if (empty($this->username) || empty($this->password) || $this->host === 'smtp.example.com') {
            $this->lastError = 'SMTP credentials are not configured. Please set SMTP_USER and SMTP_PASS in .env or config.php.';
            return false;
        }

        return $this->sendViaSmtpSocket($senderName, $senderEmail, $subject, $plainBody, $htmlBody);
    }

    /**
     * Send email via low-level SMTP stream socket
     */
    protected function sendViaSmtpSocket(string $senderName, string $senderEmail, string $subject, string $plainBody, string $htmlBody): bool {
        $host = $this->host;
        $port = $this->port;
        $secure = $this->secure;

        $hostPrefix = ($secure === 'ssl') ? 'ssl://' : '';
        $remoteSocket = $hostPrefix . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ]
        ]);

        $socket = @stream_socket_client(
            $remoteSocket,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $this->lastError = "Could not connect to SMTP server ({$host}:{$port}): {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        // Read initial greeting
        $response = $this->readResponse($socket);
        if (!$this->isCode($response, [220])) {
            $this->closeSocket($socket);
            $this->lastError = "SMTP Server greeting failed: {$response}";
            return false;
        }

        // EHLO
        $clientDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->sendCommand($socket, "EHLO {$clientDomain}");
        $response = $this->readResponse($socket);

        // Handle STARTTLS if configured
        if ($secure === 'tls') {
            $this->sendCommand($socket, 'STARTTLS');
            $response = $this->readResponse($socket);
            if (!$this->isCode($response, [220])) {
                $this->closeSocket($socket);
                $this->lastError = "STARTTLS failed: {$response}";
                return false;
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            if (!$cryptoEnabled) {
                $this->closeSocket($socket);
                $this->lastError = "Failed to establish TLS encryption with SMTP server.";
                return false;
            }

            // Resend EHLO after TLS handshake
            $this->sendCommand($socket, "EHLO {$clientDomain}");
            $response = $this->readResponse($socket);
        }

        // AUTH LOGIN
        if (!empty($this->username)) {
            $this->sendCommand($socket, 'AUTH LOGIN');
            $response = $this->readResponse($socket);
            if (!$this->isCode($response, [334])) {
                $this->closeSocket($socket);
                $this->lastError = "AUTH LOGIN rejected: {$response}";
                return false;
            }

            $this->sendCommand($socket, base64_encode($this->username));
            $response = $this->readResponse($socket);
            if (!$this->isCode($response, [334])) {
                $this->closeSocket($socket);
                $this->lastError = "SMTP Username rejected: {$response}";
                return false;
            }

            $this->sendCommand($socket, base64_encode($this->password));
            $response = $this->readResponse($socket);
            if (!$this->isCode($response, [235])) {
                $this->closeSocket($socket);
                $this->lastError = "SMTP Authentication failed (invalid credentials): {$response}";
                return false;
            }
        }

        // MAIL FROM
        $fromEmail = $this->fromEmail;
        $this->sendCommand($socket, "MAIL FROM:<{$fromEmail}>");
        $response = $this->readResponse($socket);
        if (!$this->isCode($response, [250])) {
            $this->closeSocket($socket);
            $this->lastError = "MAIL FROM rejected: {$response}";
            return false;
        }

        // RCPT TO
        $toEmail = $this->toEmail;
        $this->sendCommand($socket, "RCPT TO:<{$toEmail}>");
        $response = $this->readResponse($socket);
        if (!$this->isCode($response, [250, 251])) {
            $this->closeSocket($socket);
            $this->lastError = "RCPT TO rejected for {$toEmail}: {$response}";
            return false;
        }

        // DATA
        $this->sendCommand($socket, 'DATA');
        $response = $this->readResponse($socket);
        if (!$this->isCode($response, [354])) {
            $this->closeSocket($socket);
            $this->lastError = "DATA command rejected: {$response}";
            return false;
        }

        // Construct MIME Message
        $boundary = '=_mb_' . md5(uniqid((string)microtime(true), true));
        $messageId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . ($clientDomain ?: 'akhilvv.github.io') . '>';

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'To: ' . (!empty($this->toName) ? "=?UTF-8?B?" . base64_encode($this->toName) . "?= <{$toEmail}>" : $toEmail);
        $headers[] = 'From: ' . "=?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$fromEmail}>";
        $headers[] = 'Reply-To: ' . "=?UTF-8?B?" . base64_encode($senderName) . "?= <{$senderEmail}>";
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'Message-ID: ' . $messageId;
        $headers[] = 'X-Mailer: Portfolio-PHP-SMTP/2.0';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $bodyPayload = implode("\r\n", $headers) . "\r\n\r\n";
        $bodyPayload .= "--{$boundary}\r\n";
        $bodyPayload .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $bodyPayload .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $bodyPayload .= chunk_split(base64_encode($plainBody)) . "\r\n";

        $bodyPayload .= "--{$boundary}\r\n";
        $bodyPayload .= "Content-Type: text/html; charset=UTF-8\r\n";
        $bodyPayload .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $bodyPayload .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $bodyPayload .= "--{$boundary}--\r\n";

        // Dot termination
        $bodyPayload .= ".\r\n";

        fwrite($socket, $bodyPayload);
        $response = $this->readResponse($socket);

        if (!$this->isCode($response, [250])) {
            $this->closeSocket($socket);
            $this->lastError = "Failed to deliver message data: {$response}";
            return false;
        }

        // QUIT
        $this->sendCommand($socket, 'QUIT');
        $this->closeSocket($socket);

        return true;
    }

    /**
     * Fallback sending via standard PHP mail() function
     */
    protected function sendViaMailFunction(string $senderName, string $senderEmail, string $subject, string $plainBody, string $htmlBody): bool {
        $to = $this->toEmail;
        $boundary = '=_mb_' . md5(uniqid((string)microtime(true), true));

        $headers = [
            'From: ' . "=?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>",
            'Reply-To: ' . "=?UTF-8?B?" . base64_encode($senderName) . "?= <{$senderEmail}>",
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Portfolio-PHP-Fallback/2.0'
        ];

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($plainBody)) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($htmlBody)) . "\r\n"
            . "--{$boundary}--\r\n";

        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $sent = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        if (!$sent) {
            $this->lastError = 'Mail delivery failed. Please verify SMTP credentials in .env or config.php.';
            return false;
        }
        return true;
    }

    protected function sendCommand($socket, string $cmd): void {
        fwrite($socket, $cmd . "\r\n");
    }

    protected function readResponse($socket): string {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            // SMTP response lines: 3-digit code followed by space or hyphen (hyphen means continuation)
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    protected function isCode(string $response, array $validCodes): bool {
        $code = (int)substr($response, 0, 3);
        return in_array($code, $validCodes, true);
    }

    protected function closeSocket($socket): void {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }

    protected function escape(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
