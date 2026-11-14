# Skill: Security, Access Control and Clinical Data Privacy

This project may eventually handle clinic/patient information. Treat all real health/clinical data as sensitive even if detailed compliance requirements have not yet been provided.

## Mandatory controls
1. Never commit credentials, tokens, private keys or `.env` values. Keep secrets server-side and redact logs/reports.
2. Enforce authentication and resource-level authorization on the backend for every protected operation. Check role and record ownership/clinic scope server-side.
3. Apply least privilege. Do not grant broad admin access by default.
4. Validate and normalize all inputs; use parameterized ORM/query-builder APIs and avoid unsafe raw SQL interpolation.
5. Prevent mass assignment, IDOR, injection, XSS, CSRF/session mistakes and insecure direct object references relevant to the chosen auth architecture.
6. Return minimal data; avoid exposing internal errors, stack traces, SQL, file paths or sensitive fields.
7. Do not log patient names, identifiers, medical details, access tokens or full request bodies by default.
8. Use synthetic test data only. Avoid patient data in screenshots, prompts, telemetry, issue descriptions or model context.
9. Use HTTPS and secure cookie/session settings in deployed environments; do not weaken TLS or auth to make tests pass.
10. Rate-limit sensitive/authentication endpoints where appropriate and follow existing project architecture.
11. Review file uploads, exports, downloads and report generation for authorization, content validation and data leakage.
12. Flag security or privacy requirements that remain unknown; do not invent claims of legal compliance.
13. For any critical/high vulnerability, block approval until fixed or explicitly risk-accepted by an authorized owner with documented rationale.
