<?php
/**
 * Cafe captive DNS — Android/iOS/Windows ko "WiFi has internet" dikhata hai.
 * Port 53 (Admin). Router DHCP DNS = is PC ka IP (192.168.1.105).
 *
 * Usage (Admin):
 *   C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe scripts\cafe-captive-dns.php
 */

$listenIp = '0.0.0.0';
$port = 53;
$serverIp = '192.168.1.105';

// Detect LAN IP
foreach (gethostbynamel(gethostname()) ?: [] as $ip) {
    if (preg_match('/^192\.168\./', $ip)) {
        $serverIp = $ip;
        break;
    }
}

$captiveHosts = [
    'connectivitycheck.gstatic.com',
    'www.gstatic.com',
    'connectivitycheck.android.com',
    'clients3.google.com',
    'captive.apple.com',
    'www.apple.com',
    'www.msftconnecttest.com',
    'www.msftncsi.com',
    'dns.msftncsi.com',
    'detectportal.firefox.com',
];

$upstream = '8.8.8.8';

echo "Cafe Captive DNS on {$listenIp}:{$port}\n";
echo "Captive hosts → {$serverIp}\n";
echo "Other queries → forward {$upstream}\n";
echo "Router DHCP DNS set to: {$serverIp}\n\n";

$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($sock === false) {
    fwrite(STDERR, "socket_create failed — PHP sockets extension?\n");
    exit(1);
}
if (! @socket_bind($sock, $listenIp, $port)) {
    fwrite(STDERR, "Bind :53 failed — Admin se chalao, ya koi aur DNS already port 53 use kar raha hai.\n");
    exit(1);
}

socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);

function dns_qname_read(string $data, int &$offset): string
{
    $labels = [];
    while ($offset < strlen($data)) {
        $len = ord($data[$offset]);
        if ($len === 0) {
            $offset++;
            break;
        }
        if (($len & 0xC0) === 0xC0) {
            // pointer — skip
            $offset += 2;
            break;
        }
        $offset++;
        $labels[] = substr($data, $offset, $len);
        $offset += $len;
    }

    return strtolower(implode('.', $labels));
}

function dns_encode_name(string $name): string
{
    $out = '';
    foreach (array_filter(explode('.', $name)) as $label) {
        $out .= chr(strlen($label)).$label;
    }

    return $out.chr(0);
}

function dns_a_response(string $query, string $qname, string $ip): string
{
    $id = substr($query, 0, 2);
    $flags = "\x81\x80"; // standard response, recursion available
    $counts = "\x00\x01\x00\x01\x00\x00\x00\x00"; // 1 Q, 1 Ans
    $offset = 12;
    $name = dns_qname_read($query, $offset);
    $qtype = substr($query, $offset, 2);
    $qclass = substr($query, $offset + 2, 2);
    $question = substr($query, 12, $offset + 4 - 12);

    $answer = dns_encode_name($qname)
        .$qtype
        .$qclass
        ."\x00\x00\x00\x3c" // TTL 60
        ."\x00\x04"
        .inet_pton($ip);

    return $id.$flags.$counts.$question.$answer;
}

while (true) {
    $from = '';
    $portFrom = 0;
    $buf = @socket_recvfrom($sock, $data, 512, 0, $from, $portFrom);
    if ($buf === false || $data === null || $data === '') {
        continue;
    }
    if (strlen($data) < 12) {
        continue;
    }

    $offset = 12;
    $qname = dns_qname_read($data, $offset);
    $qtype = strlen($data) >= $offset + 2 ? unpack('n', substr($data, $offset, 2))[1] : 0;

    $isCaptive = false;
    foreach ($captiveHosts as $h) {
        if ($qname === $h || str_ends_with($qname, '.'.$h)) {
            $isCaptive = true;
            break;
        }
    }

    if ($isCaptive && ($qtype === 1 || $qtype === 255)) {
        $resp = dns_a_response($data, $qname, $serverIp);
        @socket_sendto($sock, $resp, strlen($resp), 0, $from, $portFrom);
        echo date('H:i:s')." CAPTIVE {$qname} -> {$serverIp} ({$from})\n";
        continue;
    }

    // Forward other queries to upstream
    $fwd = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($fwd === false) {
        continue;
    }
    socket_set_option($fwd, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
    @socket_sendto($fwd, $data, strlen($data), 0, $upstream, 53);
    $reply = '';
    $rip = '';
    $rport = 0;
    $got = @socket_recvfrom($fwd, $reply, 2048, 0, $rip, $rport);
    socket_close($fwd);
    if ($got !== false && $reply !== '') {
        @socket_sendto($sock, $reply, strlen($reply), 0, $from, $portFrom);
    }
}
