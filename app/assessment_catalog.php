<?php
declare(strict_types=1);

function assessment_frameworks(): array
{
    return [
        [
            'id' => 'nist_csf_2',
            'name' => 'NIST Cybersecurity Framework 2.0',
            'description' => 'Govern, Identify, Protect, Detect, Respond and Recover themes.',
        ],
        [
            'id' => 'cis_controls_8_1',
            'name' => 'CIS Critical Security Controls v8.1',
            'description' => 'Prioritised safeguards for common organisational risks.',
        ],
        [
            'id' => 'iso_27001_2022',
            'name' => 'ISO/IEC 27001:2022',
            'description' => 'Information security management system themes.',
        ],
        [
            'id' => 'owasp_web',
            'name' => 'OWASP web application themes',
            'description' => 'Practical web application and software security themes.',
        ],
        [
            'id' => 'malawi_community',
            'name' => 'Malawi & community organisation baseline',
            'description' => 'Plain-language safeguards for local teams, connectivity and service continuity.',
        ],
        [
            'id' => 'database_resilience',
            'name' => 'Database & data resilience',
            'description' => 'Practical safeguards for database access, protection, maintenance and recovery.',
        ],
    ];
}

function assessment_controls(): array
{
    $allFrameworks = [
        'nist_csf_2',
        'cis_controls_8_1',
        'iso_27001_2022',
        'malawi_community',
    ];
    $databaseFrameworks = array_merge($allFrameworks, ['database_resilience']);

    return [
        [
            'id' => 'security-ownership',
            'area' => 'Leadership & planning',
            'title' => 'Security ownership',
            'question' => 'Is someone clearly responsible for making security decisions and following up on issues?',
            'guidance' => 'A named person or small team is enough. They should know who to contact when they need help.',
            'recommendation' => 'Name a security contact, agree who can approve changes, and review responsibilities at least yearly.',
            'weight' => 4,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'asset-inventory',
            'area' => 'Leadership & planning',
            'title' => 'Important technology list',
            'question' => 'Do you keep a current list of the important websites, devices, accounts and services your organisation uses?',
            'guidance' => 'Include who owns each item and which provider to contact. Do not put passwords in the list.',
            'recommendation' => 'Create a simple, access-controlled inventory and assign an owner to review it regularly.',
            'weight' => 4,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'account-access',
            'area' => 'Accounts & people',
            'title' => 'Individual accounts and access',
            'question' => 'Does each person use their own account, with access limited to what they need?',
            'guidance' => 'Avoid shared administrator accounts. Remove access promptly when a person changes role or leaves.',
            'recommendation' => 'Use named accounts, review who has access, and remove accounts that are no longer needed.',
            'weight' => 5,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'multi-factor',
            'area' => 'Accounts & people',
            'title' => 'Extra sign-in protection',
            'question' => 'Is an extra sign-in step turned on for email, administrator and other important accounts?',
            'guidance' => 'This is often called two-step verification or multi-factor authentication. Prefer an authenticator app or security key where available.',
            'recommendation' => 'Enable multi-factor authentication first on email, finance, cloud and administrator accounts.',
            'weight' => 5,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'staff-awareness',
            'area' => 'Accounts & people',
            'title' => 'Staff security guidance',
            'question' => 'Do staff know how to recognise suspicious messages and report a security concern?',
            'guidance' => 'Simple onboarding guidance and a clear reporting contact are useful starting points.',
            'recommendation' => 'Give staff short, practical guidance and make it easy to report suspicious activity without blame.',
            'weight' => 3,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'updates',
            'area' => 'Devices & services',
            'title' => 'Software updates',
            'question' => 'Are computers, phones, applications and routers updated with security fixes in a reasonable time?',
            'guidance' => 'Turn on automatic updates where practical and identify devices or services that no longer receive updates.',
            'recommendation' => 'Enable automatic updates, assign someone to check update status, and replace unsupported technology.',
            'weight' => 4,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'endpoint-protection',
            'area' => 'Devices & services',
            'title' => 'Device protection',
            'question' => 'Are organisation devices protected with screen locks and appropriate built-in security tools?',
            'guidance' => 'Consider device encryption, reputable endpoint protection, and the ability to disable or erase a lost device.',
            'recommendation' => 'Require screen locks, keep built-in protections enabled, and protect sensitive laptops and phones if lost.',
            'weight' => 4,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'backups',
            'area' => 'Devices & services',
            'title' => 'Backups and recovery',
            'question' => 'Are important files backed up, and has someone checked that they can be restored?',
            'guidance' => 'Keep at least one backup separate from everyday accounts or devices. Test recovery, not just backup completion.',
            'recommendation' => 'Back up critical information regularly, protect a separate copy, and practise restoring a small sample.',
            'weight' => 5,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'incident-plan',
            'area' => 'Responding & recovery',
            'title' => 'Incident response plan',
            'question' => 'Would staff know whom to contact and what first steps to take if an account or device was compromised?',
            'guidance' => 'Write down contacts, steps to contain the issue, how to preserve information and how to keep essential services running.',
            'recommendation' => 'Prepare a short incident contact sheet and practise a realistic scenario with the people responsible.',
            'weight' => 4,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'service-continuity',
            'area' => 'Responding & recovery',
            'title' => 'Continuity during outages',
            'question' => 'Can your organisation continue essential work during an internet, power or key-provider outage?',
            'guidance' => 'Consider local copies of essential contacts, alternative communication methods and provider support details.',
            'recommendation' => 'Identify essential services and agree a simple fallback for connectivity, power and provider outages.',
            'weight' => 3,
            'frameworks' => ['nist_csf_2', 'iso_27001_2022', 'malawi_community'],
        ],
        [
            'id' => 'data-handling',
            'area' => 'Information & suppliers',
            'title' => 'Sensitive information',
            'question' => 'Do staff know which information is sensitive and how to share or store it safely?',
            'guidance' => 'Consider personal, financial, health and community information. Share only with people who need it.',
            'recommendation' => 'Agree simple handling rules, limit access to sensitive information and use trusted sharing methods.',
            'weight' => 4,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'supplier-security',
            'area' => 'Information & suppliers',
            'title' => 'Supplier and service-provider contacts',
            'question' => 'Do you know which outside providers support important services and how to reach them during an issue?',
            'guidance' => 'Keep service ownership, support contacts and renewal details in one place. Avoid storing secrets in contact notes.',
            'recommendation' => 'List important providers, review access they have, and keep support and escalation contacts current.',
            'weight' => 3,
            'frameworks' => $allFrameworks,
        ],
        [
            'id' => 'web-change-management',
            'area' => 'Websites & applications',
            'title' => 'Website and application changes',
            'question' => 'Are changes to websites and applications checked before they are released?',
            'guidance' => 'Use a test environment when possible, restrict who can publish changes, and keep a way to undo a faulty release.',
            'recommendation' => 'Limit publishing access, test important changes and keep a documented rollback or recovery option.',
            'weight' => 4,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['Website', 'Web application'],
        ],
        [
            'id' => 'web-access',
            'area' => 'Websites & applications',
            'title' => 'Website administration access',
            'question' => 'Are website and application administrator accounts protected with strong sign-in security and limited access?',
            'guidance' => 'Use separate named administrator accounts, multi-factor authentication and remove old accounts.',
            'recommendation' => 'Enable multi-factor authentication for administrators and review publishing and hosting access.',
            'weight' => 5,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['Website', 'Web application'],
        ],
        [
            'id' => 'web-data-protection',
            'area' => 'Websites & applications',
            'title' => 'Website data protection',
            'question' => 'Are forms and online services designed to collect only the information they need and protect it?',
            'guidance' => 'Review what is collected, where it is stored, who can access it, and when it should be deleted.',
            'recommendation' => 'Reduce unnecessary collection, restrict access to submissions and agree retention and deletion practices.',
            'weight' => 5,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['Website', 'Web application'],
        ],
        [
            'id' => 'web-review-findings',
            'area' => 'Websites & applications',
            'title' => 'Website issues and updates',
            'question' => 'Are website issues reviewed and fixed, and are the underlying platform and extensions kept supported?',
            'guidance' => 'Keep a record of identified issues and who will address them. Remove components that are no longer maintained.',
            'recommendation' => 'Track website issues to resolution and keep the platform and extensions supported and updated.',
            'weight' => 4,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['Website', 'Web application'],
        ],
        [
            'id' => 'api-access-controls',
            'area' => 'API access & identity',
            'title' => 'API authentication and permissions',
            'question' => 'Does each API operation verify who is calling and whether they are allowed to access that specific record or action?',
            'guidance' => 'Authentication identifies a caller; authorization must also check their permission for each requested action and object. Review this with safe test accounts.',
            'recommendation' => 'Require appropriate authentication for private operations and enforce authorization on every request, including access to individual records.',
            'weight' => 5,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['API'],
        ],
        [
            'id' => 'api-data-handling',
            'area' => 'API data protection',
            'title' => 'API data minimization',
            'question' => 'Does the API return only the information required by the caller, with sensitive fields excluded by default?',
            'guidance' => 'Review normal and error responses using approved test accounts. Do not copy real personal or confidential data into assessment notes.',
            'recommendation' => 'Return only necessary fields, classify data exposed by each operation and ensure errors do not include secrets or internal details.',
            'weight' => 5,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['API'],
        ],
        [
            'id' => 'api-validation',
            'area' => 'API data protection',
            'title' => 'API input validation',
            'question' => 'Are API inputs validated against clear types, formats, size limits and allowed values before processing?',
            'guidance' => 'Review documented validation rules and use non-destructive test data in an approved test environment.',
            'recommendation' => 'Validate requests on the server, reject unexpected fields or values safely, and apply sensible request and payload size limits.',
            'weight' => 5,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['API'],
        ],
        [
            'id' => 'api-abuse-protection',
            'area' => 'API operations & resilience',
            'title' => 'API usage limits',
            'question' => 'Are reasonable usage limits and safeguards in place to reduce accidental overload and abusive request patterns?',
            'guidance' => 'Consider per-account limits, request size limits and protections for costly operations. Avoid load testing production systems.',
            'recommendation' => 'Set proportionate per-client and per-operation limits, monitor service health and document a safe response to unusually high traffic.',
            'weight' => 4,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['API'],
        ],
        [
            'id' => 'api-secret-management',
            'area' => 'API access & identity',
            'title' => 'API key and secret management',
            'question' => 'Are API keys and service credentials kept out of source code, logs and client-side applications, and rotated when needed?',
            'guidance' => 'Check approved configuration and deployment records. Never paste keys or tokens into this review.',
            'recommendation' => 'Store secrets in an access-controlled secret manager, limit each key to its required purpose and rotate exposed or unused credentials.',
            'weight' => 5,
            'frameworks' => [
                'nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022',
                'owasp_web', 'malawi_community',
            ],
            'asset_types' => ['API'],
        ],
        [
            'id' => 'database-access',
            'area' => 'Database access & identity',
            'title' => 'Database least-privilege access',
            'question' => 'Do application and staff database accounts have only the permissions needed for their role?',
            'guidance' => 'Review account grants and ownership with the database administrator. Do not enter usernames, passwords or connection strings here.',
            'recommendation' => 'Use separate named accounts, remove unused accounts and grant only the minimum permissions required for each service.',
            'weight' => 5,
            'frameworks' => $databaseFrameworks,
            'asset_types' => ['Database'],
        ],
        [
            'id' => 'database-network-boundary',
            'area' => 'Database access & identity',
            'title' => 'Database network access',
            'question' => 'Is database network access limited to the application services and administrators that need it?',
            'guidance' => 'Check firewall, cloud network and provider settings with an authorised administrator; this review does not connect to or probe the database.',
            'recommendation' => 'Keep databases off public networks where possible and allow connections only from required private services and approved administration paths.',
            'weight' => 5,
            'frameworks' => $databaseFrameworks,
            'asset_types' => ['Database'],
        ],
        [
            'id' => 'database-encryption',
            'area' => 'Database data protection',
            'title' => 'Database encryption and key ownership',
            'question' => 'Is sensitive database information protected with encryption at rest and in transit, with keys managed separately?',
            'guidance' => 'Confirm settings with the database or cloud provider. Never record encryption keys or sensitive values in evidence notes.',
            'recommendation' => 'Enable supported encryption at rest and TLS for connections, restrict key access and document a safe key-rotation process.',
            'weight' => 5,
            'frameworks' => $databaseFrameworks,
            'asset_types' => ['Database'],
        ],
        [
            'id' => 'database-backup-recovery',
            'area' => 'Database resilience',
            'title' => 'Database backup and restore',
            'question' => 'Are database backups protected from ordinary administrator access and has a restore been practised?',
            'guidance' => 'Review backup schedules, retention and restore-test evidence. Avoid placing backup files or personal data in this workspace.',
            'recommendation' => 'Keep protected, separate backups, define recovery priorities and practise restoring a representative sample.',
            'weight' => 5,
            'frameworks' => $databaseFrameworks,
            'asset_types' => ['Database'],
        ],
        [
            'id' => 'database-updates-monitoring',
            'area' => 'Database resilience',
            'title' => 'Database updates and activity review',
            'question' => 'Are database software updates planned, and are important access or configuration changes reviewed?',
            'guidance' => 'Confirm supported versions, a safe update process, audit coverage and who responds to unusual activity.',
            'recommendation' => 'Keep the database platform supported, apply security updates through a tested change process and review relevant audit alerts.',
            'weight' => 4,
            'frameworks' => $databaseFrameworks,
            'asset_types' => ['Database'],
        ],
    ];
}

function assessment_controls_for_asset_type(string $assetType): array
{
    return array_values(array_filter(
        assessment_controls(),
        static fn (array $control): bool => !isset($control['asset_types'])
            || in_array($assetType, $control['asset_types'], true)
    ));
}

function save_guided_assessment_answers(int $assessmentId, int $organizationId, array $answers): void
{
    $pdo = db();
    $assessmentQuery = $pdo->prepare(
        'SELECT ga.id, ga.frameworks_json, ga.status, a.type AS asset_type
         FROM guided_assessments ga
         JOIN assets a ON a.id = ga.asset_id AND a.organization_id = ga.organization_id
         WHERE ga.id = :id AND ga.organization_id = :organization_id
         FOR UPDATE'
    );
    $assessmentQuery->execute(['id' => $assessmentId, 'organization_id' => $organizationId]);
    $assessment = $assessmentQuery->fetch();
    if ($assessment === false) {
        throw new ApiException(404, 'Assessment not found.');
    }
    if ($assessment['status'] !== 'Draft') {
        throw new ApiException(409, 'Completed assessments are read-only.');
    }

    $frameworks = json_decode($assessment['frameworks_json'], true, 16, JSON_THROW_ON_ERROR);
    $controls = array_values(array_filter(
        assessment_controls_for_asset_type($assessment['asset_type']),
        static fn (array $control): bool => count(array_intersect($control['frameworks'], $frameworks)) > 0
    ));
    $allowedControls = array_fill_keys(array_column($controls, 'id'), true);
    $validAnswers = ['implemented', 'partial', 'not_implemented', 'not_sure', 'not_applicable'];
    $statement = $pdo->prepare(
        'INSERT INTO guided_assessment_answers
            (organization_id, guided_assessment_id, control_id, answer, notes)
         VALUES
            (:organization_id, :assessment_id, :control_id, :answer, :notes)
         ON DUPLICATE KEY UPDATE answer = VALUES(answer), notes = VALUES(notes)'
    );
    $validatedAnswers = [];

    foreach ($answers as $answer) {
        if (!($answer instanceof stdClass)) {
            throw new ApiException(422, 'Each assessment response must be an object.');
        }
        $response = get_object_vars($answer);
        $controlId = $response['control_id'] ?? null;
        $answerValue = $response['answer'] ?? null;
        $notes = $response['notes'] ?? '';
        if (!is_string($controlId) || !isset($allowedControls[$controlId])) {
            throw new ApiException(422, 'An assessment response refers to an unknown question.');
        }
        if (!is_string($answerValue) || !in_array($answerValue, $validAnswers, true)) {
            throw new ApiException(422, 'Choose a response for each assessment question.');
        }
        if (!is_string($notes) || mb_strlen($notes) > 4000) {
            throw new ApiException(422, 'Evidence notes must be 4,000 characters or fewer.');
        }
        $validatedAnswers[] = [
            'control_id' => $controlId,
            'answer' => $answerValue,
            'notes' => trim($notes),
        ];
    }

    foreach ($validatedAnswers as $answer) {
        $statement->execute([
            'organization_id' => $organizationId,
            'assessment_id' => $assessmentId,
            'control_id' => $answer['control_id'],
            'answer' => $answer['answer'],
            'notes' => $answer['notes'],
        ]);
    }
}
