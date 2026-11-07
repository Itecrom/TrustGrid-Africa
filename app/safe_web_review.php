<?php
declare(strict_types=1);

function public_ipv4_for_host(string $host): string
{
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        throw new ApiException(422, 'Website reviews require a public domain name, not an IP address.');
    }

    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });
    try {
        $records = dns_get_record($host, DNS_A);
    } catch (ErrorException) {
        throw new ApiException(502, 'The website domain could not be resolved.');
    } finally {
        restore_error_handler();
    }
    $addresses = [];
    foreach (is_array($records) ? $records : [] as $record) {
        if (isset($record['ip'])) {
            $addresses[] = $record['ip'];
        }
    }

    if ($addresses === []) {
        throw new ApiException(422, 'This domain does not resolve to a public IPv4 address.');
    }

    foreach (array_unique($addresses) as $address) {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false) {
            throw new ApiException(422, 'Reviews are limited to domains that resolve only to public IPv4 addresses.');
        }
    }

    return $addresses[0];
}

function fetch_public_endpoint(string $scheme, string $host, string $address, string $path, bool $isApi): array
{
    if (!extension_loaded('curl')) {
        throw new ApiException(503, 'The safe website review service is unavailable.');
    }

    $port = $scheme === 'https' ? 443 : 80;
    $headers = [];
    $headerBytes = 0;
    $bodyBytes = 0;
    $url = $scheme . '://' . $host . $path;
    $handle = curl_init($url);
    if ($handle === false) {
        throw new ApiException(503, 'Could not start the safe website review.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $address],
        CURLOPT_PROXY => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CERTINFO => $scheme === 'https',
        CURLOPT_USERAGENT => 'TrustGrid-Africa-SafeReview/1.0',
        CURLOPT_HTTPHEADER => [
            $isApi
                ? 'Accept: application/json,application/*+json;q=0.9'
                : 'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
        ],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$bodyBytes): int {
            $bodyBytes += strlen($chunk);
            return $bodyBytes > 32768 ? 0 : strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers, &$headerBytes): int {
            $length = strlen($line);
            $headerBytes += $length;
            if ($headerBytes > 24576) {
                return 0;
            }

            if (preg_match('/^HTTP\/\S+\s+\d{3}\b/i', $line) === 1) {
                $headers = [];
                return $length;
            }

            $separator = strpos($line, ':');
            if ($separator !== false) {
                $name = strtolower(trim(substr($line, 0, $separator)));
                if (in_array($name, [
                    'strict-transport-security',
                    'content-security-policy',
                    'x-content-type-options',
                    'x-frame-options',
                    'referrer-policy',
                    'set-cookie',
                    'location',
                    'content-type',
                    'access-control-allow-origin',
                    'access-control-allow-credentials',
                    'server',
                ], true)) {
                    $value = preg_replace('/[\x00-\x1F\x7F]/', '', trim(substr($line, $separator + 1, 2048)));
                    $headers[$name][] = $value ?? '';
                }
            }

            return $length;
        },
    ]);

    $result = curl_exec($handle);
    $errorNumber = curl_errno($handle);
    $errorMessage = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $certificateInfo = curl_getinfo($handle, CURLINFO_CERTINFO);
    $tlsErrorCodes = [];
    foreach (['CURLE_SSL_CONNECT_ERROR', 'CURLE_SSL_CERTPROBLEM', 'CURLE_SSL_CACERT'] as $constant) {
        if (defined($constant)) {
            $tlsErrorCodes[] = constant($constant);
        }
    }
    $tlsFailure = $scheme === 'https' && in_array($errorNumber, $tlsErrorCodes, true);
    curl_close($handle);

    if ($result === false && !($errorNumber === CURLE_WRITE_ERROR && $bodyBytes > 32768)) {
        return [
            'available' => false,
            'status' => $status,
            'headers' => $headers,
            'tls_failure' => $tlsFailure,
            'certificate_info' => is_array($certificateInfo) ? $certificateInfo : [],
            'error' => $errorMessage,
        ];
    }

    return [
        'available' => $status > 0,
        'status' => $status,
        'headers' => $headers,
        'tls_failure' => false,
        'certificate_info' => is_array($certificateInfo) ? $certificateInfo : [],
        'error' => '',
    ];
}

function check_result(string $key, string $title, string $status, string $severity, string $evidence, string $recommendation): array
{
    return [
        'key' => $key,
        'title' => $title,
        'status' => $status,
        'severity' => $severity,
        'evidence' => $evidence,
        'recommendation' => $recommendation,
    ];
}

function run_safe_asset_review(string $target, string $assetType): array
{
    $parts = parse_url($target);
    if (!is_array($parts)
        || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        throw new ApiException(422, 'Reviews support public HTTP/HTTPS domains on standard ports only.');
    }

    $scheme = strtolower($parts['scheme']);
    $isApi = $assetType === 'API';
    if ($isApi && ($scheme !== 'https' || isset($parts['query']) || isset($parts['fragment']))) {
        throw new ApiException(422, 'API reviews require an HTTPS endpoint without query strings or fragments.');
    }

    $expectedPort = $scheme === 'https' ? 443 : 80;
    if (isset($parts['port']) && (int) $parts['port'] !== $expectedPort) {
        throw new ApiException(422, 'Reviews only use the standard port for the selected HTTP scheme.');
    }

    $host = strtolower(rtrim($parts['host'], '.'));
    if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        || !str_contains($host, '.')
    ) {
        throw new ApiException(422, 'Enter a fully qualified public domain before starting a review.');
    }

    $address = public_ipv4_for_host($host);
    $path = $isApi ? (string) ($parts['path'] ?? '/') : '/';
    if ($path === '' || $path[0] !== '/' || strlen($path) > 1024 || preg_match('/[\x00-\x20\x7F]/', $path) === 1) {
        throw new ApiException(422, 'The API review target must use a valid endpoint path.');
    }

    $https = fetch_public_endpoint('https', $host, $address, $path, $isApi);
    if (!$https['available'] && !$https['tls_failure']) {
        throw new ApiException(502, 'The endpoint could not be reached for a safe HTTPS review. No findings were recorded.');
    }

    $checks = [];
    $score = 0;
    if ($https['tls_failure']) {
        $checks[] = check_result(
            'tls_certificate',
            'HTTPS certificate validation',
            'fail',
            'Critical',
            'The HTTPS TLS handshake failed or certificate chain validation did not succeed.',
            'Install a valid certificate for this hostname and verify the full certificate chain.'
        );
        $score += 70;
    } else {
        $checks[] = check_result(
            'tls_certificate',
            'HTTPS certificate validation',
            'pass',
            'Info',
            'The HTTPS connection succeeded with certificate validation enabled.',
            'Continue monitoring certificate validity and renew before expiry.'
        );
    }

    if ($https['available']) {
        $checks[] = check_result(
            'https_available',
            $isApi ? 'API endpoint HTTPS availability' : 'HTTPS availability',
            'pass',
            'Info',
            'The public endpoint returned an HTTPS response (HTTP ' . $https['status'] . ').',
            'Keep all public pages and resources available over HTTPS.'
        );
        $headers = $https['headers'];
        $headerChecks = [
            [
                'hsts', 'HTTP Strict Transport Security', 'strict-transport-security',
                'Medium', 15,
                'Enable HSTS on the HTTPS virtual host after confirming the entire domain is HTTPS-ready.',
            ],
            [
                'content_security_policy', 'Content Security Policy', 'content-security-policy',
                'Medium', 15,
                'Define a Content-Security-Policy suited to the application and test it in report-only mode first.',
            ],
            [
                'content_type_options', 'Content type protection', 'x-content-type-options',
                'Low', 8,
                'Set X-Content-Type-Options: nosniff.',
            ],
            [
                'frame_protection', 'Frame embedding protection', 'x-frame-options',
                'Low', 8,
                'Set X-Frame-Options or a frame-ancestors directive in Content-Security-Policy.',
            ],
            [
                'referrer_policy', 'Referrer Policy', 'referrer-policy',
                'Low', 5,
                'Set a Referrer-Policy appropriate for your site, such as strict-origin-when-cross-origin.',
            ],
        ];
        if ($isApi) {
            $headerChecks = array_values(array_filter(
                $headerChecks,
                static fn (array $check): bool => in_array($check[0], ['hsts', 'content_type_options'], true)
            ));
        }
        foreach ($headerChecks as [$key, $title, $headerName, $severity, $weight, $recommendation]) {
            $values = $headers[$headerName] ?? [];
            $present = $values !== [];
            if ($key === 'hsts') {
                $present = false;
                foreach ($values as $value) {
                    if (preg_match('/(?:^|;)\s*max-age=(\d+)(?:;|$)/i', $value, $match) === 1
                        && (int) $match[1] > 0
                    ) {
                        $present = true;
                        break;
                    }
                }
            } elseif ($key === 'content_type_options') {
                $present = in_array('nosniff', array_map('strtolower', array_map('trim', $values)), true);
            } elseif ($key === 'frame_protection') {
                $present = count(array_filter(
                    $values,
                    static fn (string $value): bool => in_array(strtoupper(trim($value)), ['DENY', 'SAMEORIGIN'], true)
                )) > 0;
                if (!$present && isset($headers['content-security-policy'])) {
                    $present = preg_match(
                        '/(?:^|;)\s*frame-ancestors\s+(?!;)\S[^;]*/i',
                        implode(';', $headers['content-security-policy'])
                    ) === 1;
                }
            } elseif ($key === 'referrer_policy') {
                $policies = [
                    'no-referrer',
                    'no-referrer-when-downgrade',
                    'origin',
                    'origin-when-cross-origin',
                    'same-origin',
                    'strict-origin',
                    'strict-origin-when-cross-origin',
                    'unsafe-url',
                ];
                $present = count(array_filter(
                    $values,
                    static fn (string $value): bool => in_array(strtolower(trim($value)), $policies, true)
                )) > 0;
            } elseif ($key === 'content_security_policy') {
                $present = count(array_filter($values, static fn (string $value): bool => trim($value) !== '')) > 0;
            }
            $checks[] = check_result(
                $key,
                $title,
                $present ? 'pass' : 'fail',
                $present ? 'Info' : $severity,
                $present ? 'The expected response header or equivalent directive was observed.' : 'The expected response header or equivalent directive was not observed on the endpoint response.',
                $present ? 'Keep this control configured and review it when application behavior changes.' : $recommendation
            );
            if (!$present) {
                $score += $weight;
            }
        }

        $cookies = $headers['set-cookie'] ?? [];
        if ($cookies === []) {
            $checks[] = check_result(
                'cookie_security',
                'Cookie security attributes',
                'not_tested',
                'Info',
                'The endpoint response did not set cookies, so cookie attributes could not be assessed.',
                'Review cookies on authenticated application routes separately.'
            );
        } else {
            $unsafeCookieCount = 0;
            foreach ($cookies as $cookie) {
                if (preg_match('/(?:^|;)\s*secure(?:;|$)/i', $cookie) !== 1
                    || preg_match('/(?:^|;)\s*httponly(?:;|$)/i', $cookie) !== 1
                    || preg_match('/(?:^|;)\s*samesite=(?:strict|lax|none)(?:;|$)/i', $cookie) !== 1
                ) {
                    $unsafeCookieCount++;
                }
            }
            $checks[] = check_result(
                'cookie_security',
                'Cookie security attributes',
                $unsafeCookieCount === 0 ? 'pass' : 'fail',
                $unsafeCookieCount === 0 ? 'Info' : 'Medium',
                $unsafeCookieCount === 0
                    ? 'All endpoint cookies included Secure, HttpOnly and SameSite attributes.'
                    : $unsafeCookieCount . ' of ' . count($cookies) . ' endpoint cookie(s) were missing one or more expected security attributes. Cookie values were not stored.',
                $unsafeCookieCount === 0
                    ? 'Continue reviewing cookie attributes on authenticated application routes.'
                    : 'Set Secure, HttpOnly and an appropriate SameSite attribute on cookies where applicable.'
            );
            if ($unsafeCookieCount > 0) {
                $score += 15;
            }
        }
    } else {
        $checks[] = check_result(
            'https_available',
            'HTTPS availability',
            'not_tested',
            'Info',
            'HTTPS could not be assessed because certificate validation failed.',
            'Resolve the certificate issue, then run the review again.'
        );
    }

    if ($isApi) {
        $contentTypes = $https['headers']['content-type'] ?? [];
        $returnsJson = count(array_filter(
            $contentTypes,
            static fn (string $value): bool => preg_match(
                '~^application/(?:[a-z0-9.+-]+\+)?json(?:\s*;|$)~i',
                trim($value)
            ) === 1
        )) > 0;
        $checks[] = check_result(
            'api_json_content_type',
            'API response format',
            $returnsJson ? 'pass' : 'fail',
            $returnsJson ? 'Info' : 'Low',
            $returnsJson
                ? 'The endpoint declared a JSON response content type.'
                : 'The endpoint did not declare a JSON response content type. The response body was not inspected.',
            $returnsJson
                ? 'Keep API responses on the documented JSON media type.'
                : 'Confirm that this is the correct API URL and return an appropriate JSON content type for API responses.'
        );
        if (!$returnsJson) {
            $score += 5;
        }

        $origins = $https['headers']['access-control-allow-origin'] ?? [];
        if (in_array('*', array_map('trim', $origins), true)) {
            $checks[] = check_result(
                'api_cors_wildcard',
                'Cross-origin access policy',
                'review',
                'Info',
                'The endpoint allows browser requests from any origin. This can be intentional for public APIs; the scan cannot determine whether the data is public.',
                'Confirm this API is intended to be public. If it serves private data, allow only the specific trusted application origins.'
            );
        }

        if (isset($https['headers']['server'])) {
            $checks[] = check_result(
                'api_server_header',
                'Server identification header',
                'review',
                'Info',
                'The response includes a Server header. Its value is not stored.',
                'Consider removing unnecessary product or version details from public response headers.'
            );
        }
    } else {
        $http = fetch_public_endpoint('http', $host, $address, '/', false);
        if (!$http['available']) {
            $checks[] = check_result(
                'http_redirect',
                'HTTP to HTTPS redirect',
                'not_tested',
                'Info',
                'The HTTP endpoint did not respond; redirect behavior could not be confirmed.',
                'If HTTP is enabled, redirect all requests to HTTPS.'
            );
        } else {
            $location = $http['headers']['location'][0] ?? '';
            $locationParts = $location !== '' ? parse_url($location) : false;
            $redirectsToSameHttpsHost = $http['status'] >= 300
                && $http['status'] < 400
                && is_array($locationParts)
                && strtolower((string) ($locationParts['scheme'] ?? '')) === 'https'
                && strtolower(rtrim((string) ($locationParts['host'] ?? ''), '.')) === $host
                && !isset($locationParts['user'])
                && !isset($locationParts['pass'])
                && (!isset($locationParts['port']) || (int) $locationParts['port'] === 443);
            $redirectsToHttps = $redirectsToSameHttpsHost;
            $checks[] = check_result(
                'http_redirect',
                'HTTP to HTTPS redirect',
                $redirectsToHttps ? 'pass' : 'fail',
                $redirectsToHttps ? 'Info' : 'High',
                $redirectsToHttps
                    ? 'The HTTP homepage redirects to the HTTPS version of this domain.'
                    : 'The HTTP homepage did not return a confirmed redirect to HTTPS (HTTP ' . $http['status'] . ').',
                $redirectsToHttps
                    ? 'Keep HTTP-to-HTTPS redirection enabled.'
                    : 'Configure the web server to redirect all HTTP requests to HTTPS.'
            );
            if (!$redirectsToHttps) {
                $score += 25;
            }
        }
    }

    $score = min(100, $score);
    $riskLevel = match (true) {
        $score >= 80 => 'Critical',
        $score >= 60 => 'High',
        $score >= 35 => 'Moderate',
        default => 'Low',
    };
    $failedChecks = count(array_filter(
        $checks,
        static fn (array $check): bool => $check['status'] === 'fail'
    ));

    return [
        'risk_score' => $score,
        'risk_level' => $riskLevel,
        'summary' => $failedChecks === 0
            ? 'Review complete: no issues were identified across ' . count($checks) . ' read-only endpoint checks.'
            : 'Review complete: ' . $failedChecks . ' security issue(s) were identified across ' . count($checks) . ' read-only endpoint checks.',
        'checks' => $checks,
    ];
}
