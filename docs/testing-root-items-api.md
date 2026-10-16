# Reproduce and verify root items in the all-items API

This fixes `block_exaport_get_all_user_items`: its synthetic root category (`id: 0`)
was empty even when the current user had uncategorized items. Root means an item
with **no rows in `block_exaportitemcate`**, matching the portfolio UI. The legacy
`block_exaportitem.categoryid` column is not used to decide membership.

This PR targets **`experimental-item-upgrade-simple-steps-cleanup` only**.
Use a disposable local site already installed/upgraded with both experimental
plugins (Exaport and the matching Exacomp `experimental-exaport-item-rework`).
Keep Exacomp in place while comparing the API. If updating an older experimental
installation first, deploy both target plugins and complete the ordinary Moodle
upgrade before creating these fixtures.

The unpatched baseline tested here is Exaport
`a6938863e0b62e73cbc7819446a70aeb9eb2ce18` (version `2026101502`) with Exacomp
`836aef933b48fde84c8048ee32688645b9b1ed54`. The fix changes no plugin version,
schema or data; the same database, fixtures and token are used before and after.
Do not switch this database to Main code.

## 1. Prepare representative data through the UI

As a normal portfolio user A:

1. Create a note named **API root regression** in Root, without selecting a category.
2. Create a category named **API category check**.
3. Create a note named **API categorized control** in that category.
4. Confirm both items appear in the appropriate places in the portfolio UI. Record
   their item IDs, so an existing item with the same title cannot confuse the check.

As another user B, create a root note named **API foreign root**. User A must never
receive B's item from this API.

## 2. Enable a local REST service

As administrator, use Site administration → Server → Web services:

1. Enable web services and the REST protocol.
2. Create an enabled custom external service and add the function
   `block_exaport_get_all_user_items`.
3. If the service restricts authorized users, authorize user A.
4. Create a token for user A for this service. The user must be allowed the
   `webservice/rest:use` capability in the system context; grant it through an
   appropriate local test role if needed.

Use user A's token, not the administrator's: results belong to the token's user.
If the response is an authentication/capability error instead of a category list,
complete the service setup before testing this bug.

## 3. Reproduce on the unpatched experimental branch

In the local `blocks/exaport` checkout (with no unrelated local changes):

```bash
git fetch origin experimental-item-upgrade-simple-steps-cleanup fix/root-items-api
git switch --detach a6938863e0b62e73cbc7819446a70aeb9eb2ce18
```

Purge Moodle caches and, if PHP opcode caching does not check changed files,
restart the local PHP workers. With Moodle 5.2 the cache CLI path is
`public/admin/cli/purge_caches.php`; older layouts use `admin/cli/purge_caches.php`.

Run this Bash request. Set the URL to the base URL of the local Moodle site,
without a trailing slash. The example uses `curl` and `jq`.

```bash
MOODLE_URL='http://localhost/moodle'
read -rsp 'User A REST token: ' EXAPORT_WS_TOKEN
printf '\n'

curl --silent --show-error --fail \
  "$MOODLE_URL/webservice/rest/server.php" \
  --data-urlencode "wstoken=$EXAPORT_WS_TOKEN" \
  --data-urlencode 'wsfunction=block_exaport_get_all_user_items' \
  --data-urlencode 'moodlewsrestformat=json' \
  | jq '.[] | {id, name, items: [.items[] | {id, name}]}'
```

**Before the fix:** the category with `id: 0` has `items: []`; **API root regression**
is missing even though it is visible in the UI. **API categorized control** still
appears under **API category check**. **API foreign root** must be absent everywhere.

## 4. Apply the fix and repeat the identical request

```bash
git switch --detach origin/fix/root-items-api
```

Purge caches/restart PHP workers as above, then repeat the same `curl` command with
user A's existing token and the same fixture data.

**After the fix:** category `id: 0` includes **API root regression** exactly once.
**API categorized control** remains in its category and is absent from Root.
**API foreign root** remains absent everywhere. Other root items owned by A are
also expected; this is an all-items service, not a fixture-only response.

Finish with these checks:

- Move **API root regression** into **API category check** and repeat the request:
  it leaves Root and appears in the category.
- Remove its category assignment and repeat: it returns to Root.
- If multiple categories are enabled, assign it to two categories: it is absent
  from Root until its last category assignment is removed.
- With a token for user B, confirm B sees B's root item and does not see A's items.
- A user with an empty portfolio still receives category `id: 0` with `items: []`.

```bash
unset EXAPORT_WS_TOKEN
```

## Automated regression test

On the fix branch, with Moodle's separate PHPUnit database/dataroot initialized,
run from the Moodle root:

```bash
# Moodle 5.2 public-root layout:
php vendor/bin/phpunit public/blocks/exaport/tests/externallib_get_all_user_items_test.php
# Older Moodle layout:
# php vendor/bin/phpunit blocks/exaport/tests/externallib_get_all_user_items_test.php
```

The test validates the declared external response structure, multiple root items,
categorized/nested/multiple-category items, removing the last relation, empty
portfolios, and owner restrictions (including a foreign-owned item linked to the
current user's category). Applied to the unpatched experimental query, the root inclusion and
return-to-root assertions fail.
