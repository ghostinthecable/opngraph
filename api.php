<?php
/**
 * OPNsense API;
 * Fetches aliases, firewall rules, and live filter logs from OPNsense
 * and returns them as JSON for the frontend graph.
 */

header('Content-Type: application/json');

$config = require __DIR__ . '/config.php';

// Well-known port names
const PORT_NAMES = [
    '20' => 'FTP-Data', '21' => 'FTP', '22' => 'SSH', '23' => 'Telnet',
    '25' => 'SMTP', '53' => 'DNS', '67' => 'DHCP', '68' => 'DHCP',
    '80' => 'HTTP', '110' => 'POP3', '123' => 'NTP', '143' => 'IMAP',
    '161' => 'SNMP', '162' => 'SNMP-Trap', '389' => 'LDAP', '443' => 'HTTPS',
    '445' => 'SMB', '465' => 'SMTPS', '514' => 'Syslog', '587' => 'SMTP-Sub',
    '636' => 'LDAPS', '993' => 'IMAPS', '995' => 'POP3S', '1194' => 'OpenVPN',
    '1433' => 'MSSQL', '1723' => 'PPTP', '3306' => 'MySQL', '3389' => 'RDP',
    '5060' => 'SIP', '5432' => 'PostgreSQL', '5900' => 'VNC', '6379' => 'Redis',
    '8080' => 'HTTP-Alt', '8443' => 'HTTPS-Alt', '8888' => 'HTTP-Alt2',
    '9090' => 'Prometheus', '9200' => 'Elasticsearch', '27017' => 'MongoDB',
    '51820' => 'WireGuard',
];

function portName(string $port): string
{
    return PORT_NAMES[$port] ?? '';
}

/**
 * Make an API call to OPNsense.
 */
function opnsenseApi(string $method, string $endpoint, array $config, array $postData = []): array
{
    $url = rtrim($config['host'], '/') . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERPWD        => $config['api_key'] . ':' . $config['api_secret'],
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_SSL_VERIFYPEER => $config['verify_ssl'],
        CURLOPT_SSL_VERIFYHOST => $config['verify_ssl'] ? 2 : 0,
    ]);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => "cURL error: $error"];
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['error' => "HTTP $httpCode", 'body' => $response];
    }

    $decoded = json_decode($response, true);
    return $decoded ?: ['error' => 'Invalid JSON response', 'raw' => substr($response, 0, 500)];
}

function fetchAliases(array $config): array
{
    $result = opnsenseApi('GET', '/api/firewall/alias/searchItem?rowCount=-1&current=1', $config);

    $aliases = [];
    if (isset($result['rows'])) {
        foreach ($result['rows'] as $row) {
            $aliases[$row['name']] = [
                'uuid'        => $row['uuid'] ?? '',
                'name'        => $row['name'],
                'type'        => $row['type'] ?? '',
                'description' => $row['description'] ?? '',
                'content'     => $row['content'] ?? '',
                'enabled'     => ($row['enabled'] ?? '1') === '1',
            ];
        }
    }

    return $aliases;
}

function fetchRules(array $config): array
{
    $result = opnsenseApi('GET', '/api/firewall/filter/searchRule?rowCount=-1&current=1', $config);

    $rules = [];
    if (isset($result['rows'])) {
        foreach ($result['rows'] as $row) {
            $rules[] = [
                'uuid'            => $row['uuid'] ?? '',
                'enabled'         => ($row['enabled'] ?? '1') === '1',
                'action'          => $row['action'] ?? 'pass',
                'direction'       => $row['direction'] ?? 'in',
                'interface'       => $row['interface'] ?? '',
                'protocol'        => $row['protocol'] ?? 'any',
                'source_net'      => $row['source_net'] ?? 'any',
                'source_port'     => $row['source_port'] ?? 'any',
                'destination_net' => $row['destination_net'] ?? 'any',
                'destination_port'=> $row['destination_port'] ?? 'any',
                'description'     => $row['description'] ?? '',
            ];
        }
    }

    return $rules;
}

function fetchLogs(array $config): array
{
    $limit = $config['log_limit'] ?? 500;
    $result = opnsenseApi('GET', "/api/diagnostics/firewall/log/", $config);

    $entries = [];
    $logRows = $result;
    if (isset($result['rows'])) {
        $logRows = $result['rows'];
    } elseif (isset($result['logs'])) {
        $logRows = $result['logs'];
    }

    if (is_array($logRows)) {
        $count = 0;
        foreach ($logRows as $entry) {
            if (!is_array($entry)) continue;
            if ($count >= $limit) break;

            $dstPort = $entry['dstport'] ?? $entry['dst_port'] ?? '';

            $entries[] = [
                'action'    => strtolower($entry['action'] ?? $entry['act'] ?? ''),
                'direction' => $entry['dir'] ?? $entry['direction'] ?? '',
                'interface' => $entry['interface'] ?? $entry['if'] ?? '',
                'protocol'  => strtoupper($entry['protoname'] ?? $entry['proto'] ?? $entry['protocol'] ?? ''),
                'src'       => $entry['src'] ?? $entry['srcip'] ?? $entry['source'] ?? '',
                'src_port'  => $entry['srcport'] ?? $entry['src_port'] ?? '',
                'dst'       => $entry['dst'] ?? $entry['dstip'] ?? $entry['destination'] ?? '',
                'dst_port'  => $dstPort,
                'port_name' => portName($dstPort),
                'time'      => $entry['__timestamp__'] ?? $entry['time'] ?? $entry['timestamp'] ?? '',
                'label'     => $entry['label'] ?? $entry['rid'] ?? '',
            ];
            $count++;
        }
    }

    return $entries;
}

function resolveAlias(string $value, array $aliases): ?string
{
    $value = trim($value);
    if ($value === '' || $value === 'any') return null;

    if (isset($aliases[$value])) {
        return $value;
    }

    foreach ($aliases as $name => $alias) {
        $contents = array_map('trim', explode("\n", $alias['content'] ?? ''));
        foreach ($contents as $item) {
            if ($item === $value) return $name;
            if (strpos($item, '/') !== false && ipInCidr($value, $item)) {
                return $name;
            }
        }
    }

    return null;
}

function ipInCidr(string $ip, string $cidr): bool
{
    if (strpos($cidr, '/') === false) return $ip === $cidr;

    [$subnet, $bits] = explode('/', $cidr, 2);
    $bits = (int) $bits;

    $ipLong    = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) return false;

    $mask = -1 << (32 - $bits);
    return ($ipLong & $mask) === ($subnetLong & $mask);
}

/**
 * Get /24 subnet from an IP.
 */
function getSubnet24(string $ip): string
{
    $parts = explode('.', $ip);
    if (count($parts) !== 4) return $ip;
    return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
}

/**
 * Check if IP is private/RFC1918.
 */
function isPrivateIp(string $ip): bool
{
    $long = ip2long($ip);
    if ($long === false) return false;

    // 10.0.0.0/8
    if (($long & 0xFF000000) === 0x0A000000) return true;
    // 172.16.0.0/12
    if (($long & 0xFFF00000) === 0xAC100000) return true;
    // 192.168.0.0/16
    if (($long & 0xFFFF0000) === 0xC0A80000) return true;

    return false;
}

// --- Main dispatch ---

$action = $_GET['action'] ?? 'all';

try {
    switch ($action) {
        case 'aliases':
            echo json_encode(['aliases' => fetchAliases($config)]);
            break;

        case 'rules':
            echo json_encode(['rules' => fetchRules($config)]);
            break;

        case 'logs':
            echo json_encode(['logs' => fetchLogs($config)]);
            break;

        case 'graph':
            $aliases = fetchAliases($config);
            $rules   = fetchRules($config);
            $logs    = fetchLogs($config);

            $nodes = [];
            $links = [];
            $nodeSet = [];
            $nodeCounts = []; // track hit counts per node

            // Add the firewall as the central node
            $nodeSet['fw:core'] = true;
            $nodes[] = [
                'id'    => 'fw:core',
                'label' => 'OPNsense',
                'type'  => 'firewall',
                'title' => 'OPNsense Firewall',
                'group' => 'firewall',
                'hits'  => count($logs),
            ];

            // Add alias nodes
            foreach ($aliases as $name => $alias) {
                $nodeId = 'alias:' . $name;
                $nodeSet[$nodeId] = true;
                $nodeCounts[$nodeId] = 0;
                $nodes[] = [
                    'id'          => $nodeId,
                    'label'       => $name,
                    'type'        => 'alias',
                    'title'       => $alias['description'] ?: $alias['type'] . ' alias',
                    'group'       => 'alias',
                    'aliasType'   => $alias['type'],
                    'content'     => $alias['content'],
                    'hits'        => 0,
                ];
            }

            // Subnet aggregation for unknown IPs
            $subnetIps = []; // subnet => [ips]
            $ipToSubnet = []; // ip => subnet node id

            // First pass: figure out which IPs need subnet aggregation
            $unknownSrcIps = [];
            $unknownDstIps = [];
            foreach ($logs as $entry) {
                $src = $entry['src'];
                $dst = $entry['dst'];
                if ($src && !resolveAlias($src, $aliases)) {
                    $unknownSrcIps[$src] = ($unknownSrcIps[$src] ?? 0) + 1;
                }
                if ($dst && !resolveAlias($dst, $aliases)) {
                    $unknownDstIps[$dst] = ($unknownDstIps[$dst] ?? 0) + 1;
                }
            }

            // Group unknown IPs by /24 subnet
            $subnetMembers = [];
            foreach (array_merge(array_keys($unknownSrcIps), array_keys($unknownDstIps)) as $ip) {
                $subnet = getSubnet24($ip);
                if (!isset($subnetMembers[$subnet])) {
                    $subnetMembers[$subnet] = [];
                }
                if (!in_array($ip, $subnetMembers[$subnet])) {
                    $subnetMembers[$subnet][] = $ip;
                }
            }

            // Create subnet nodes for subnets with >1 IP, individual nodes for lone IPs
            foreach ($subnetMembers as $subnet => $ips) {
                if (count($ips) >= 2) {
                    $subnetNodeId = 'subnet:' . $subnet;
                    $isPrivate = isPrivateIp($ips[0]);
                    if (!isset($nodeSet[$subnetNodeId])) {
                        $nodeSet[$subnetNodeId] = true;
                        $nodeCounts[$subnetNodeId] = 0;
                        $nodes[] = [
                            'id'      => $subnetNodeId,
                            'label'   => $subnet,
                            'type'    => 'subnet',
                            'title'   => count($ips) . ' IPs from ' . $subnet,
                            'group'   => $isPrivate ? 'internal' : 'external',
                            'members' => $ips,
                            'hits'    => 0,
                        ];
                    }
                    foreach ($ips as $ip) {
                        $ipToSubnet[$ip] = $subnetNodeId;
                    }
                } else {
                    $ip = $ips[0];
                    $ipNodeId = 'ip:' . $ip;
                    $isPrivate = isPrivateIp($ip);
                    if (!isset($nodeSet[$ipNodeId])) {
                        $nodeSet[$ipNodeId] = true;
                        $nodeCounts[$ipNodeId] = 0;
                        $nodes[] = [
                            'id'    => $ipNodeId,
                            'label' => $ip,
                            'type'  => 'ip',
                            'title' => ($isPrivate ? 'Internal' : 'External') . " IP: $ip",
                            'group' => $isPrivate ? 'internal' : 'external',
                            'hits'  => 0,
                        ];
                    }
                }
            }

            // Process log entries
            $linkMap = [];
            $recentActivity = [];
            $topSources = [];
            $topDestinations = [];
            $protocolCounts = [];
            $portCounts = [];
            $blockCount = 0;
            $passCount = 0;

            foreach ($logs as $entry) {
                $src     = $entry['src'];
                $dst     = $entry['dst'];
                $dstPort = $entry['dst_port'];
                $proto   = $entry['protocol'];
                $act     = $entry['action'];
                $portN   = $entry['port_name'];

                if (!$src || !$dst) continue;

                // Stats
                $isBlock = in_array($act, ['block', 'reject', 'deny', 'drop']);
                if ($isBlock) $blockCount++; else $passCount++;

                $topSources[$src] = ($topSources[$src] ?? 0) + 1;
                $dstLabel = $dst . ($dstPort ? ':' . $dstPort : '');
                $topDestinations[$dstLabel] = ($topDestinations[$dstLabel] ?? 0) + 1;
                if ($proto) $protocolCounts[$proto] = ($protocolCounts[$proto] ?? 0) + 1;
                if ($dstPort) {
                    $pLabel = $portN ? "$dstPort ($portN)" : $dstPort;
                    $portCounts[$pLabel] = ($portCounts[$pLabel] ?? 0) + 1;
                }

                $srcAlias = resolveAlias($src, $aliases);
                $dstAlias = resolveAlias($dst, $aliases);

                // Source node ID
                if ($srcAlias) {
                    $srcNodeId = 'alias:' . $srcAlias;
                } elseif (isset($ipToSubnet[$src])) {
                    $srcNodeId = $ipToSubnet[$src];
                } else {
                    $srcNodeId = 'ip:' . $src;
                    if (!isset($nodeSet[$srcNodeId])) {
                        $isPrivate = isPrivateIp($src);
                        $nodeSet[$srcNodeId] = true;
                        $nodeCounts[$srcNodeId] = 0;
                        $nodes[] = [
                            'id'    => $srcNodeId,
                            'label' => $src,
                            'type'  => 'ip',
                            'title' => ($isPrivate ? 'Internal' : 'External') . " IP: $src",
                            'group' => $isPrivate ? 'internal' : 'external',
                            'hits'  => 0,
                        ];
                    }
                }

                // Destination node ID
                if ($dstAlias) {
                    $dstNodeId = 'alias:' . $dstAlias;
                } elseif (isset($ipToSubnet[$dst])) {
                    $dstNodeId = $ipToSubnet[$dst];
                } else {
                    // For destination, group by port service if known
                    $dstNodeId = 'ip:' . $dst;
                    if (!isset($nodeSet[$dstNodeId])) {
                        $isPrivate = isPrivateIp($dst);
                        $nodeSet[$dstNodeId] = true;
                        $nodeCounts[$dstNodeId] = 0;
                        $nodes[] = [
                            'id'    => $dstNodeId,
                            'label' => $dst,
                            'type'  => 'ip',
                            'title' => ($isPrivate ? 'Internal' : 'External') . " IP: $dst",
                            'group' => $isPrivate ? 'internal' : 'external',
                            'hits'  => 0,
                        ];
                    }
                }

                // Increment hit counts
                if (isset($nodeCounts[$srcNodeId])) $nodeCounts[$srcNodeId]++;
                if (isset($nodeCounts[$dstNodeId])) $nodeCounts[$dstNodeId]++;

                // Determine link colour
                if ($isBlock) {
                    $colour = 'red';
                } elseif ($srcAlias) {
                    $colour = 'green';
                } else {
                    $colour = 'orange';
                }

                $linkKey = "$srcNodeId|$dstNodeId|$colour";
                if (!isset($linkMap[$linkKey])) {
                    $linkMap[$linkKey] = [
                        'source'    => $srcNodeId,
                        'target'    => $dstNodeId,
                        'colour'    => $colour,
                        'action'    => $act,
                        'protocol'  => $proto,
                        'count'     => 0,
                        'ports'     => [],
                        'portNames' => [],
                    ];
                }
                $linkMap[$linkKey]['count']++;
                if ($dstPort && !in_array($dstPort, $linkMap[$linkKey]['ports'])) {
                    $linkMap[$linkKey]['ports'][] = $dstPort;
                    if ($portN) $linkMap[$linkKey]['portNames'][$dstPort] = $portN;
                }

                // Recent activity (last 20)
                if (count($recentActivity) < 20) {
                    $recentActivity[] = [
                        'time'     => $entry['time'],
                        'action'   => $act,
                        'src'      => $srcAlias ?? $src,
                        'dst'      => $dstAlias ?? $dst,
                        'port'     => $dstPort,
                        'portName' => $portN,
                        'protocol' => $proto,
                        'colour'   => $colour,
                        'label'    => $entry['label'],
                    ];
                }
            }

            // Inject hit counts back into nodes
            foreach ($nodes as &$node) {
                if (isset($nodeCounts[$node['id']])) {
                    $node['hits'] = $nodeCounts[$node['id']];
                }
            }
            unset($node);

            // Sort stats
            arsort($topSources);
            arsort($topDestinations);
            arsort($protocolCounts);
            arsort($portCounts);

            $links = array_values($linkMap);

            echo json_encode([
                'nodes'         => $nodes,
                'links'         => $links,
                'aliasCount'    => count($aliases),
                'ruleCount'     => count($rules),
                'logCount'      => count($logs),
                'blockCount'    => $blockCount,
                'passCount'     => $passCount,
                'timestamp'     => date('c'),
                'recent'        => $recentActivity,
                'topSources'    => array_slice($topSources, 0, 10, true),
                'topDests'      => array_slice($topDestinations, 0, 10, true),
                'protocols'     => $protocolCounts,
                'topPorts'      => array_slice($portCounts, 0, 10, true),
            ]);
            break;

        case 'all':
        default:
            $aliases = fetchAliases($config);
            $rules   = fetchRules($config);
            echo json_encode([
                'aliases' => $aliases,
                'rules'   => $rules,
            ]);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
