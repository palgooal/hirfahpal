#!/usr/bin/env bash
# Tests for deploy/cpanel-deploy.sh step 4b (site availability report parsing).
# Run: bash tests/deploy/cpanel-deploy-availability.test.sh
#
# The real step 4b is extracted from the script (from "# 4b." to its pass line) and run with a stub PHP that prints a
# given report on stdout, a fake secret on stderr, and exits with a given code. Each case checks PASS or FAIL, the
# logged message, and that neither the stderr secret nor rejected report content reaches the output.

set -u

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/deploy/cpanel-deploy.sh"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/hirfah-deploy-test.XXXXXX")"
trap 'rm -rf -- "$TMP"' EXIT

sed -n '/^# 4b\. /,/^pass "site availability/p' "$SCRIPT" > "$TMP/step.sh"
grep -q '^availability_out=' "$TMP/step.sh" && grep -q '^pass "site availability' "$TMP/step.sh" \
    || { echo "FAIL: could not extract step 4b from $SCRIPT"; exit 1; }

PASSED=0
FAILED=0
SECRET="secret-user@db-host"

# run_case <name> <PASS|FAIL> <expected substring> <exit code> <report (printf %b)> [<forbidden substring>]
run_case() {
    local name="$1" outcome="$2" expected="$3" code="$4" report="$5" forbidden="${6:-}" output
    printf '%b' "$report" > "$TMP/report"
    cat > "$TMP/php" <<STUB
#!/bin/sh
cat "$TMP/report"
echo "SQLSTATE[HY000] Access denied for user $SECRET" >&2
exit $code
STUB
    chmod +x "$TMP/php"
    output="$(bash -c 'set -eu; set -o pipefail; APP="$1"; PHP_BIN="$2"
        fail() { echo "FAIL: $*"; exit 1; }; pass() { echo "PASS: $*"; }
        . "$3"' _ "$TMP" "$TMP/php" "$TMP/step.sh" 2>&1)"

    local problems=""
    [[ "$output" == "$outcome: "* ]] || problems+=" outcome is not $outcome;"
    [[ "$output" == *"$expected"* ]] || problems+=" missing '$expected';"
    [[ "$output" != *"$SECRET"* ]] || problems+=" stderr secret leaked;"
    if [ -n "$forbidden" ] && [[ "$output" == *"$forbidden"* ]]; then problems+=" '$forbidden' leaked;"; fi
    [ "$(printf '%s\n' "$output" | wc -l)" -eq 1 ] || problems+=" more than one output line;"

    if [ -z "$problems" ]; then
        PASSED=$((PASSED + 1)); echo "ok   - $name"
    else
        FAILED=$((FAILED + 1)); echo "FAIL - $name:$problems"; echo "       output: $output"
    fi
}

AUTO_PASS='RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=database\nREASON=auto: settings.site_availability_mode is coming_soon\n'
FORCED_PASS='RESULT=PASS\nENV_OVERRIDE=coming_soon\nCONFIG_CACHE=present\nSTATE=coming_soon\nSOURCE=environment\nREASON=forced coming_soon by the environment override\n'

# Valid reports.
run_case "valid auto report passes" PASS "HIRFAH_SITE_AVAILABILITY=auto, source database, config cache absent" 0 "$AUTO_PASS"
run_case "valid forced report passes" PASS "HIRFAH_SITE_AVAILABILITY=coming_soon, source environment, config cache present" 0 "$FORCED_PASS"
run_case "valid FAIL report is refused with its reason" FAIL "does not allow deployment: the effective site availability is 'open' (from database), not 'coming_soon' (HIRFAH_SITE_AVAILABILITY: auto; config cache: absent)" 1 \
    "RESULT=FAIL\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=open\nSOURCE=database\nREASON=the effective site availability is 'open' (from database), not 'coming_soon'\n"
run_case "exception reported by class name is shown" FAIL "unexpected error (Illuminate\\Database\\QueryException)" 1 \
    'RESULT=FAIL\nENV_OVERRIDE=unknown\nCONFIG_CACHE=unknown\nSTATE=unknown\nSOURCE=none\nREASON=unexpected error (Illuminate\\Database\\QueryException)\n'

# Incomplete reports.
run_case "missing field" FAIL "unusable report: field SOURCE appears 0 times" 0 \
    'RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=coming_soon\nREASON=auto: settings.site_availability_mode is coming_soon\n'
run_case "empty report with exit 0" FAIL "unusable report: empty report (exit 0)" 0 ''
run_case "only RESULT=PASS" FAIL "unusable report: field ENV_OVERRIDE appears 0 times" 0 'RESULT=PASS\n'

# Repeated fields.
run_case "repeated field, same value" FAIL "unusable report: field RESULT appears 2 times" 0 "${AUTO_PASS}RESULT=PASS\n"
run_case "repeated field, contradicting values" FAIL "unusable report: field STATE appears 2 times" 0 "${AUTO_PASS}STATE=open\n"

# Contradictions.
run_case "auto with source environment" FAIL "RESULT=PASS with ENV_OVERRIDE=auto and SOURCE=environment" 0 \
    'RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=environment\nREASON=auto: settings.site_availability_mode is coming_soon\n'
run_case "coming_soon with source database" FAIL "RESULT=PASS with ENV_OVERRIDE=coming_soon and SOURCE=database" 0 \
    'RESULT=PASS\nENV_OVERRIDE=coming_soon\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=database\nREASON=forced coming_soon by the environment override\n'
run_case "PASS with state open" FAIL "RESULT=PASS with STATE=open" 0 \
    'RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=open\nSOURCE=database\nREASON=auto: settings.site_availability_mode is coming_soon\n'
run_case "PASS with an unresolved env override" FAIL "RESULT=PASS with ENV_OVERRIDE=invalid and SOURCE=database" 0 \
    'RESULT=PASS\nENV_OVERRIDE=invalid\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=database\nREASON=auto: settings.site_availability_mode is coming_soon\n'
run_case "PASS with config cache unknown" FAIL "RESULT=PASS with CONFIG_CACHE=unknown" 0 \
    'RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=unknown\nSTATE=coming_soon\nSOURCE=database\nREASON=auto: settings.site_availability_mode is coming_soon\n'
run_case "PASS with the other mode's reason" FAIL "RESULT=PASS with a REASON that does not match ENV_OVERRIDE=auto" 0 \
    'RESULT=PASS\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=database\nREASON=forced coming_soon by the environment override\n'

# Unexpected lines and values.
run_case "unexpected field" FAIL "unusable report: unexpected line in the report" 0 "${AUTO_PASS}DEBUG=leaked-value\n" "leaked-value"
run_case "free text line" FAIL "unusable report: unexpected line in the report" 0 "Access denied for user leaked-user\n${AUTO_PASS}" "leaked-user"
run_case "blank line inside the report" FAIL "unusable report: unexpected line in the report" 0 \
    'RESULT=PASS\n\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=coming_soon\nSOURCE=database\nREASON=auto: settings.site_availability_mode is coming_soon\n'
run_case "lower-case key" FAIL "unusable report: unexpected line in the report" 0 "${AUTO_PASS}result=PASS\n"
run_case "CRLF line endings, otherwise valid" FAIL "unusable report: control character in the report" 0 \
    'RESULT=PASS\r\nENV_OVERRIDE=auto\r\nCONFIG_CACHE=absent\r\nSTATE=coming_soon\r\nSOURCE=database\r\nREASON=auto: settings.site_availability_mode is coming_soon\r\n'
run_case "escape sequence in a value" FAIL "unusable report: control character in the report" 1 \
    'RESULT=FAIL\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=unknown\nSOURCE=none\nREASON=x\033[2K\n'
run_case "unknown STATE value" FAIL "unusable report: invalid STATE" 1 \
    'RESULT=FAIL\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=closed\nSOURCE=database\nREASON=x\n'
run_case "unknown ENV_OVERRIDE value is not shown" FAIL "unusable report: invalid ENV_OVERRIDE" 1 \
    'RESULT=FAIL\nENV_OVERRIDE=base64-leaked-key\nCONFIG_CACHE=absent\nSTATE=unknown\nSOURCE=none\nREASON=x\n' "base64-leaked-key"
run_case "REASON outside the safe characters is not shown" FAIL "unusable report: invalid REASON" 1 \
    'RESULT=FAIL\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=unknown\nSOURCE=none\nREASON=denied for leaked@host\n' "leaked@host"
run_case "empty REASON" FAIL "unusable report: invalid REASON" 1 \
    'RESULT=FAIL\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=unknown\nSOURCE=none\nREASON=\n'

# Exit codes.
run_case "valid PASS report with non-zero exit" FAIL "unusable report: RESULT=PASS with exit 1" 1 "$AUTO_PASS"
run_case "FAIL report with exit 0" FAIL "unusable report: RESULT=FAIL with exit 0" 0 \
    "RESULT=FAIL\nENV_OVERRIDE=auto\nCONFIG_CACHE=absent\nSTATE=open\nSOURCE=database\nREASON=the effective site availability is 'open' (from database), not 'coming_soon'\n"
run_case "crash with no report" FAIL "unusable report: empty report (exit 255)" 255 ''

echo "$PASSED passed, $FAILED failed"
[ "$FAILED" -eq 0 ]
