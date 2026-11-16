# User tours: merge and release review

Reviewed on 16 November 2026 for [PR #287](https://github.com/gtn/exaport/pull/287),
`feat/upload-user-tours` into `dev`. This review covers the tour lifecycle,
associated upload interface, and relevant access checks. It is not a security
audit of every Exaport feature.

## Recommendation

Ready to merge and release within the tested environment below, with the fixes
included in this PR. No remaining release blocker was found in the reviewed
changes. The PR was not merged or released as part of this review.

The implementation follows Moodle's native User tours model: bundled JSON,
native import/removal APIs, per-tour revisions in `configdata`, native per-user
preferences, and deferred post-upgrade registration. See the
[implementation and maintenance documentation](upload-user-tours.md) for the
API references and the procedure for adding or revising a guide.

## Findings fixed during review

1. **Failed imports needed explicit rollback.** A malformed deployment file
   could leave a delegated transaction open until Moodle's outer exception
   handler intervened. Registration now rolls back before releasing its lock
   and rethrows the original failure. Regression coverage verifies that the old
   tours, steps, completion preferences and signature survive, the transaction
   is closed, the lock is available, and a repaired deployment succeeds.
2. **The upload add button was hidden until hover on Moodle 4.5.** The component
   reused course-editor CSS classes whose styles hide their contents. Those
   classes have been removed from this component. The button now uses ordinary
   Bootstrap styling and remains visible without hovering. Real uploads and
   the guide targeting that button were retested on both Moodle versions.
3. **Existing test fixtures needed compatibility updates.** Upgrade tests now
   allow later savepoints; the compatibility fixture resolves Moodle's clock
   before substituting its temporary Moodle root; three parameterized security
   tests now declare their providers for both PHPUnit 9 and 11. The latter change
   ensures those security cases actually run on Moodle 4.5.

## Test environment and results

Both sites used PHP 8.3.35, MariaDB 10.11.19 and the Boost theme. Browser checks
used headless Chromium 151. Tests ran against local review sites, not a customer
installation.

| Check | Moodle 4.5.16 | Moodle 5.2.4 |
| --- | --- | --- |
| Full Exaport PHPUnit suite | 604 tests, 10,597 assertions; pass | 604 tests, 10,597 assertions; pass |
| PHPUnit version | 9.6.34 | 11.5.55 |
| Native tour and upload browser scenarios | 6 passed | 6 passed |
| Tour lifecycle and uninstall callback tests | Passed | Passed |

The Moodle 4.5 source was commit
`69ef83ad2948db19595106671f03a1429679c870`; Moodle 5.2 was
`2df605b1e093248e8f8d1e2fc081fb3b1f65665f`.
Changed PHP files passed syntax checks. All 12 Dutch tour strings and five
related workflow labels/hints were verified.

The Moodle 5.2 full-suite run returned success with 67 existing PHPUnit metadata
deprecations and two notices from the existing thumbnail helper's use of
`file_encode_url()`, deprecated since Moodle 5.1. These are maintenance work,
not failures in the tour code. The Moodle 4.5 full-suite run was clean.

### Uninstall isolation

The tests invoke the real `xmldb_block_exaport_uninstall()` callback. They check
ordinary administrator-created tours, tours with identical names and URLs,
other-plugin metadata, core shipped-tour metadata, malformed/non-string markers,
and markers that merely start with an Exaport identity. All unrelated tour
definitions, steps and users' completion/reset preferences survive.

Renaming an Exaport guide does not prevent its cleanup. A copied/imported guide
that retains an exact known `block_exaport_tour` identity is treated as
Exaport-owned and is removed too. Uninstall is safe to repeat. Preference cleanup
uses exact tour IDs, so a different ID sharing a numeric prefix is retained.
The callback also preserves another component's identically named signature.
Moodle's removal API may renumber remaining tours' numeric sort orders.

An additional end-to-end test ran Moodle's actual CLI uninstaller on the
disposable Moodle 4.5 site:

```sh
php admin/cli/uninstall_plugins.php --plugins=block_exaport --run
```

Core confirmed successful uninstallation and removed the Exaport tables and
installed version. All three owned guides, including an imported copy, were
removed. All ten unrelated tours and their steps and per-user tour preferences
were retained. Another component's setting was retained as well.

The plugin was then reinstalled through Moodle's CLI upgrade process. A single
registration task was queued, and executing it restored both native guides at
revision 1. The temporary review users, courses, objects and sentinel tours were
cleaned up afterwards.

### Registration, updates and concurrency

Permanent PHPUnit coverage verifies:

- Initial installation, install recovery and upgrade queue one native ad-hoc
  registration task without importing tours during schema upgrades.
- First-page registration works before cron; the queued task is then a no-op.
- Repeated registration preserves IDs, steps, enabled states and completion.
- Adding a new guide preserves an existing guide's completion.
- A revision change replaces and replays only the outdated guide, for each user.
- A newer installed revision is not downgraded by older code.
- Missing native tour tables defer registration until they become available.
- Completion and replay are independent for each user and guide.
- URL and selector filters exclude unrelated/read-only pages.
- Failed imports roll back and can be retried safely.
- A current signature uses no additional database queries once Moodle's
  configuration cache is warm.

Eight separate processes were also released simultaneously on Moodle 4.5 with
stale cached registration markers. Every process observed the same two installed
guide IDs and revisions; no duplicates were created.

### Browser and access checks

The six scenarios passed on each Moodle version:

1. Guests cannot reach the editor; direct requests to internal tour class,
   task and uninstall PHP files produce no output.
2. A student follows both two-step guides, uploads an actual text document,
   saves it, and downloads exactly the uploaded bytes. Completion persists
   after reload, and the add button is visible without hovering.
3. A second student has independent completion and cannot edit or download the
   first student's private object. An invalid session key is rejected.
4. An administrator expands a student in Shared Categories, navigates nested
   Coach Intensive / Learning Journals folders, creates an object with the
   intended category selected, and uploads/downloads a document. As in the
   existing workflow, the administrator owns the object created in that category.
5. Dutch plugin tour text and the matching controls render at a 390-by-844
   mobile viewport; the tour popover fits horizontally.
6. Moodle's footer reset link replays a completed guide.

An additional positive sharing test on Moodle 4.5 confirms that a recipient can
read an explicitly shared object while seeing neither the editing guide nor
the content add button. Direct access to content editing remains denied.

No exploitable security issue was found in the reviewed tour code or these
access paths. Registration reads fixed, bundled JSON files; it accepts no
user-supplied tour definition or filesystem path. The page fallback runs after
login and the Exaport use capability check. Native Moodle APIs handle tour
state and cleanup. This result does not claim exhaustive penetration testing
of the whole plugin.

## Observations and limits

- An artificially fast automated Next/End sequence, before the native tour's
  reveal animation completed, exposed a race in Moodle core's
  `tool_usertours` JavaScript (`revealStep` calling `announceStep` after the tour
  had ended). Normal interaction after the dialog becomes ready passed.
  Uploads and completion remained functional. No Moodle core code was changed;
  this is an upstream observation rather than a release blocker for this PR.
- Dutch plugin translations were exercised using a minimal local Dutch
  child-language fixture with English core fallback. Downloading the official
  Dutch language pack was blocked by the test environment. A complete Dutch
  core language pack was therefore not browser-tested.
- The browser matrix covers Chromium and Boost, not every browser, custom theme,
  assistive technology, database engine or supported PHP version.
- No GitHub CI check runs were configured/reported for the reviewed PR head;
  the validation above was executed locally in the review environment.

To repeat the permanent suite from an initialized Moodle PHPUnit checkout:

```sh
php vendor/bin/phpunit --testsuite block_exaport_testsuite
php vendor/bin/phpunit --testsuite block_exaport_testsuite --filter user_tours_test
```

The browser, concurrency and CLI-uninstall checks described above were separate
review harnesses. They are not represented as permanent Behat coverage in this
PR. Credentials, raw browser sessions and local database snapshots are not
included in the repository.
