<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// File: common.php | Version: 2026101400 | Author: pro-web.at
//
// History:
// 2026101400: Added this history block. Explicit nullable parameter types, because PHP 8.4 deprecates the
//             implicit nullable type of a typed parameter with default null.
//             cachelib: no hardcoded plugin names anymore. The session flag to disable the cache is now the plugin
//             neutral "common_cache_disabled", still shared by all copies, so one switch disables the cache of every
//             plugin. A user cache without a logged in user throws a coding_exception, the strings never existed.
// 2026081000: Cleanup for the Moodle code style (phpcs): docblocks for every class, explicit public visibility,
//             short array syntax, defined('MOODLE_INTERNAL'). Exception messages encode the arguments with
//             json_encode() instead of print_r().
// 2026062900: Added db::sql_labels_from_set_column() and db::sql_concat_ws(), CONCAT_WS skips NULL values, while
//             $DB->sql_concat_join() returns NULL on postgres as soon as one argument is NULL.
// 2026020500: Added db::sql_json_array_from_query() and output::render_string_template().
//             cachelib: the 'user' area is a per user application cache, the 'session' area falls back to the
//             request cache when there is no session (CLI, webservice), callbackWithBypassCache() accepts
//             CACHE_CLEAR to only delete the entry.

/**
 * Base helper classes shared across plugins.
 *
 * Nothing in here is meant to be used directly. Each plugin derives its own class
 * from these, and the rest of the code only ever uses that one: for example
 * \plugin_name\common\db is the base class defined below, and \plugin_name\db in
 * classes/db.php is the subclass to use.
 *
 * @copyright  pro-web.at
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The helpers below are small and only make sense together, so they stay in one
// file instead of being scattered across one file per class. The trade-off is that
// they are not autoloadable, so files using them need a require_once.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

// Every class below is tagged @internal, which Moodle lists as valid but not
// recommended. Note that the sniff reports real typos in tag names under the same
// code, so those are no longer caught in this file either.
// phpcs:disable moodle.Commenting.ValidTags.Invalid

// Moodle wants all lower-case names without underscores. This file uses camelCase
// in places, partly because it mirrors the API it extends (SimpleXMLElement).
// phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
// phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

// No @package tags here. The expected value is the plugin name, which is different
// in every copy of this file, so the docblocks would have to be edited per plugin.
// phpcs:disable moodle.Commenting.Package.Missing

namespace block_exaport\common;

defined('MOODLE_INTERNAL') || die();

/**
 * Database helpers: writing records with an update-or-insert semantic, reading
 * column names and building SQL fragments that work on both MySQL and PostgreSQL.
 *
 * @internal
 */
class db {
    /**
     * Updates a record, either by the id in $data or by looking it up via $where first.
     * Returns false when $where matches no record.
     *
     * @param string $table
     * @param array|object $data
     * @param array|null $where
     * @return bool
     * @throws \moodle_exception
     */
    public static function update_record(string $table, array|object $data, array|null $where = null): bool {
        global $DB;

        if ($where === null) {
            return $DB->update_record($table, $data);
        }

        $data = (array)$data;

        if ($db_item = $DB->get_record($table, $where)) {
            $data['id'] = $db_item->id;
            $DB->update_record($table, (object)$data);

            return true;
        }

        return false;
    }

    /**
     * Updates the record matching $where, or inserts it when there is none. $data may be
     * a callback that receives whether the record already existed.
     *
     * @param string $table
     * @param array|object $data
     * @param array|object|null $where
     * @return object
     * @throws \moodle_exception
     */
    public static function insert_or_update_record(string $table, array|object $data, array|object|null $where = null): object {
        global $DB;

        $original_data = $data;
        $where = $where === null ? $where : (array)$where;

        $get_data = function(bool $exists) use ($original_data): array {
            // Also allow to pass a callback for $data
            $data = is_callable($original_data) ? $original_data($exists) : $original_data;

            if (!is_object($data) && !is_array($data)) {
                throw new \moodle_exception('unsupported type for $data: ' . gettype($data));
            }

            return (array)$data;
        };

        $check_where = $where;
        if (!$check_where) {
            if (is_callable($data)) {
                throw new \moodle_exception('if $data is a callback, a $where has to be provided');
            }
            $check_where = (array)$data;
        }
        if (!$check_where) {
            throw new \moodle_exception('$where is empty');
        }
        if ($db_item = $DB->get_record($table, $check_where)) {
            $data = $get_data(true);
            if (!$data) {
                throw new \moodle_exception('$data is empty');
            }

            $data['id'] = $db_item->id;
            $DB->update_record($table, (object)$data);

            return (object)($data + (array)$db_item);
        } else {
            $data = $get_data(false);
            unset($data['id']);
            if ($where) {
                $data = $data + $where; // First the values of $data, then of $where, but don't override $data
            }
            $id = $DB->insert_record($table, (object)$data);
            $data['id'] = $id;

            return (object)$data;
        }
    }

    public static function get_column_names(string $table): array {
        global $DB;

        return array_keys($DB->get_columns($table));
    }

    public static function get_column_names_prefixed(string $table, string $prefix): array {
        $prefix = trim($prefix, '.');

        $columns = static::get_column_names($table);
        $columns = array_map(function($column) use ($prefix) {
            return $prefix . '.' . $column;
        }, $columns);

        return join(', ', $columns);
    }

    public static function find_in_set($needle, $haystack) {
        global $DB;

        // Determine the database family (mysql, pgsql, etc.)
        $dbfamily = $DB->get_dbfamily();

        if ($dbfamily === 'mysql') {
            return "FIND_IN_SET($needle, $haystack)";
        } elseif ($dbfamily === 'postgres') {
            // PostgreSQL doesn't have FIND_IN_SET, use string matching.
            return $DB->sql_cast_to_char($needle) . " = ANY (string_to_array($haystack, ','))";
        }

        // Default case (just in case we want to extend it to other databases)
        throw new \coding_exception('Unsupported database type for find_in_set');
    }

    public static function sql_json_array_from_query(array $values, string $from, string $sort = ''): string {
        global $DB;

        $select = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = preg_replace('!^.*\.!', '', $value);
            }
            $select[] = "'" . $key . "', {$value}";
        }
        $select = implode(',', $select);

        // Determine the database family (mysql, pgsql, etc.)
        $dbfamily = $DB->get_dbfamily();

        if ($dbfamily === 'mysql') {
            $select = "JSON_OBJECT({$select})";
        } elseif ($dbfamily === 'postgres') {
            // PostgreSQL doesn't have FIND_IN_SET, use string matching.
            $select = "json_build_object({$select})::text";
        } else {
            throw new \coding_exception('Unsupported database type for sql_aggregated_json_from_query');
        }

        $select = "(SELECT " . $DB->sql_group_concat($select, ',', $sort ?: join(', ', $values)) . "
            FROM " . $from . ")";

        return $DB->sql_concat("'['", $select, "']'");
    }

    /**
     * Translates a multi-value set column (comma-separated keys, e.g. "value1,value2,...") into an
     * aggregated label text ("Label 1, Label 2") - usable as a displayable, searchable and sortable
     * SQL column. One CASE WHEN per entry: if the key matches as a complete, comma-delimited token,
     * its label is returned (otherwise NULL, which CONCAT_WS skips). Value and pattern are wrapped in
     * commas ('%,key,%') so that e.g. "id1" does not wrongly also match "id12".
     *
     * @param string $column SQL column holding the comma-separated keys (set)
     * @param array $map key => label
     */
    public static function sql_labels_from_set_column(string $column, array $map, string $separator = ', '): string {
        global $DB;

        if (!$map) {
            return "''";
        }

        $wrapped = $DB->sql_concat("','", $column, "','");
        $cases = [];
        foreach ($map as $key => $label) {
            $cases[] = "CASE WHEN {$wrapped} LIKE '%," . $key . ",%' THEN '" . str_replace("'", '', $label) . "' END";
        }

        return static::sql_concat_ws($cases, $separator);
    }

    /**
     * NULL-skipping concatenation with a separator, using native CONCAT_WS. A non-matching CASE (NULL,
     * no ELSE) is therefore dropped from the result.
     *
     * Available on mysql/mariadb and postgres (>= 9.1) - both provide a NULL-skipping CONCAT_WS.
     * Use this instead of $DB->sql_concat_join() when arguments may be NULL: sql_concat_join uses '||'
     * on postgres, where a single NULL would turn the whole result into NULL.
     *
     * @param array $elements SQL expressions to join
     * @param string $separator literal separator, e.g. ', '
     */
    public static function sql_concat_ws(array $elements, string $separator = ', '): string {
        if (!$elements) {
            return "''";
        }

        return "CONCAT_WS('" . $separator . "', " . join(', ', $elements) . ")";
    }
}

/**
 * Base for the plugin's events. Fills in the context from the course id (or the
 * system context) and adds log() as a shortcut for create()->trigger().
 *
 * @internal
 */
abstract class event extends \core\event\base {
    protected static function prepareData(array &$data): void {
        if (!isset($data['contextid'])) {
            if (!empty($data['courseid'])) {
                $data['contextid'] = \context_course::instance($data['courseid'])->id;
            } else {
                $data['contextid'] = \context_system::instance()->id;
            }
        }
    }

    public static function log(array $data): void {
        static::prepareData($data);

        static::create($data)->trigger();
    }
}

/**
 * Language strings: get_string() looks in the plugin's own strings first and falls
 * back to the global ones, trans() resolves inline multi-language strings.
 *
 * @internal
 */
class lang {
    /**
     * get a language string from current plugin or else from global language strings
     *
     * @param string $identifier The identifier of the string to search for
     * @param string $component The module the string is associated with
     * @param string|object|array $a An object, string or number that can be used
     *      within translation strings
     * @param string $lang moodle translation language, null means use current
     * @return string The String !
     */
    public static function get_string($identifier, $component = null, $a = null) {
        $manager = get_string_manager();

        if ($component === null) {
            $component = helper::plugin_name();
        }

        if ($manager->string_exists($identifier, $component)) {
            return $manager->get_string($identifier, $component, $a);
        }

        return $manager->get_string($identifier, '', $a);
    }

    /**
     * translator function
     */
    public static function trans($string_or_strings, $arg_or_args = null) {

        $origArgs = $args = func_get_args();

        $identifier = '';
        $a = null;

        if (empty($args)) {
            throw new \moodle_exception('no args');
        }

        $arg = array_shift($args);
        if (is_string($arg) && !static::check_identifier($arg)) {
            $identifier = $arg;
            $arg = array_shift($args);
        }

        if ($arg === null) {
            // Just id submitted
            $languagestrings = [];
        } else if (is_array($arg)) {
            $languagestrings = $arg;
        } else if (is_string($arg) && $matches = static::check_identifier($arg)) {
            $languagestrings = [$matches[1] => $matches[2]];
        } else {
            throw new \moodle_exception('wrong args: ' . json_encode($origArgs));
        }

        if ($args) {
            $a = array_shift($args);
        }

        if ($args) {
            throw new \moodle_exception('too many arguments: ' . json_encode($origArgs));
        }

        // Parse $languagestrings
        foreach ($languagestrings as $key => $string) {
            if (is_number($key)) {
                if ($matches = static::check_identifier($string)) {
                    $languagestrings[$matches[1]] = $matches[2];
                    unset($languagestrings[$key]);
                } else {
                    throw new \moodle_exception('wrong language string: ' . $origArgs);
                }
            }
        }

        $lang = current_language();

        $manager = get_string_manager();
        $component = helper::plugin_name();

        // Try with $identifier from args
        if ($identifier && $manager->string_exists($identifier, $component)) {
            return $manager->get_string($identifier, $component, $a);
        }

        // Try submitted language strings
        if (isset($languagestrings[$lang])) {
            return static::parse_string($languagestrings[$lang], $a);
        }

        // Try language string
        $identifier = reset($languagestrings);
        $identifier = key($languagestrings) . ':' . $identifier;
        if ($manager->string_exists($identifier, $component)) {
            return $manager->get_string($identifier, $component, $a);
        }

        if ($languagestrings) {
            return static::parse_string(reset($languagestrings), $a);
        } else {
            throw new \moodle_exception("language string '{$origArgs[0]}' not found, did you forget to prefix a language? 'en:{$origArgs[0]}'");
        }
    }

    protected static function check_identifier($string) {
        if (preg_match('!^([^:]+):(.*)$!s', $string, $matches)) {
            return $matches;
        } else {
            return null;
        }
    }

    protected static function parse_string($string, $a) {
        // Copy from moodle/lib/classes/string_manager_standard.php
        // Process array's and objects (except lang_strings).
        if (is_array($a) || (is_object($a) && !($a instanceof \lang_string))) {
            $a = (array)$a;
            $search = [];
            $replace = [];
            foreach ($a as $key => $value) {
                if (is_int($key)) {
                    // We do not support numeric keys - sorry!
                    continue;
                }
                if (is_array($value) || (is_object($value) && !($value instanceof \lang_string))) {
                    // We support just string or lang_string as value.
                    continue;
                }
                $search[] = '{$a->' . $key . '}';
                $replace[] = (string)$value;
            }
            if ($search) {
                $string = str_replace($search, $replace, $string);
            }
        } else {
            $string = str_replace('{$a}', (string)$a, $string);
        }

        return $string;
    }
}

/**
 * moodle_exception that uses the plugin as the string component automatically,
 * whenever the error code exists in the plugin's language file.
 *
 * @internal
 */
class moodle_exception extends \moodle_exception {
    public function __construct($errorcode, $module = '', $link = '', $a = null, $debuginfo = null) {

        // Try to get local error message (use namespace as $component)
        if (empty($module)) {
            if (get_string_manager()->string_exists($errorcode, helper::plugin_name())) {
                $module = helper::plugin_name();
            }
        }

        return parent::__construct($errorcode, $module, $link, $a, $debuginfo);
    }
}

/**
 * Reads and cleans request parameters against a definition, also for nested
 * arrays, objects and JSON payloads.
 *
 * @internal
 */
class param {
    public static function clean_object($values, $definition) {
        if (!is_object($values) && !is_array($values)) {
            return null;
        }

        // Some value => type
        $ret = (object)[];
        $values = (object)$values;
        $definition = (array)$definition;

        foreach ($definition as $key => $valueType) {
            $value = isset($values->$key) ? $values->$key : null;

            $ret->$key = static::clean($value, $valueType);
        }

        return $ret;
    }

    public static function clean_array($values, $definition) {
        $definition = (array)$definition;

        if (is_object($values)) {
            $values = (array)$values;
        } else if (!is_array($values)) {
            return [];
        }

        $keyType = key($definition);
        $valueType = reset($definition);

        // Allow clean_array(PARAM_TEXT): which means PARAM_INT=>PARAM_TEXT
        if ($keyType === 0) {
            $keyType = PARAM_SEQUENCE;
        }

        if ($keyType !== PARAM_INT && $keyType !== PARAM_TEXT && $keyType !== PARAM_SEQUENCE) {
            throw new moodle_exception('wrong key type: ' . $keyType);
        }

        $ret = [];
        foreach ($values as $key => $value) {
            $value = static::clean($value, $valueType);
            if ($value === null) {
                continue;
            }

            if ($keyType == PARAM_SEQUENCE) {
                $ret[] = $value;
            } else {
                $ret[clean_param($key, $keyType)] = $value;
            }
        }

        return $ret;
    }

    protected static function clean($value, $definition) {
        if (is_object($definition)) {
            return static::clean_object($value, $definition);
        } else if (is_array($definition)) {
            return static::clean_array($value, $definition);
        } else {
            return clean_param($value, $definition);
        }
    }

    public static function get_param($parname, $definition = null) {
        // POST has precedence.
        if (isset($_POST[$parname])) {
            if (is_array($_POST[$parname])) {
                return static::clean_array($_POST[$parname], $definition);
            } else {
                return clean_param($_POST[$parname], PARAM_TEXT);
            }
        } else if (isset($_GET[$parname])) {
            if (is_array($_GET[$parname])) {
                return static::clean_array($_GET[$parname], $definition);
            } else {
                return clean_param($_GET[$parname], PARAM_TEXT);
            }
        } else {
            return null;
        }
    }

    public static function get_required_param($parname) {
        $param = static::get_param($parname);

        if ($param === null) {
            throw new moodle_exception('param not found: ' . $parname);
        }

        return $param;
    }

    public static function optional_array($parname, $definition) {
        $param = static::get_param($parname, $definition);
        if ($param === null) {
            return [];
        } else {
            return $param;
        }
    }

    public static function required_array($parname, $definition) {
        $param = static::get_required_param($parname);

        if (!is_array($param)) {
            throw new moodle_exception("required parameter '$parname' is not an array");
        }

        return static::clean_array($param, $definition);
    }

    public static function optional_object($parname, $definition) {
        $param = static::get_param($parname);

        if ($param === null) {
            return null;
        } else {
            return static::clean_object($param, $definition);
        }
    }

    public static function required_object($parname, $definition) {
        $param = static::get_required_param($parname);

        if (!is_array($param)) {
            throw new moodle_exception("required parameter '$parname' is not an array an can not converted to object");
        }

        return static::clean_object($param, $definition);
    }

    public static function required_json($parname, $definition = null) {
        $data = required_param($parname, PARAM_RAW);

        $data = json_decode($data, true);
        if ($data === null) {
            throw new moodle_exception('missingparam', '', '', $parname);
        }

        if ($definition === null) {
            return $data;
        } else {
            return static::clean($data, $definition);
        }
    }
}

/**
 * SimpleXMLElement that can add CDATA children, skip empty values and serialize
 * itself as indented XML.
 *
 * @internal
 */
class SimpleXMLElement extends \SimpleXMLElement {
    /**
     * Adds a child with $value inside CDATA
     *
     * @param string $name
     * @param mixed $value
     * @return SimpleXMLElement
     */
    public function addChildWithCDATA($name, $value = null) {
        $new_child = $this->addChild($name);

        if ($new_child !== null) {
            $node = dom_import_simplexml($new_child);
            $no = $node->ownerDocument;
            $node->appendChild($no->createCDATASection($value));
        }

        return $new_child;
    }

    public static function create($rootElement) {
        return new static('<?xml version="1.0" encoding="UTF-8"?><' . $rootElement . ' />');
    }

    public function addChildWithCDATAIfValue($name, $value = null) {
        if ($value) {
            return $this->addChildWithCDATA($name, $value);
        } else {
            return $this->addChild($name, $value);
        }
    }

    /**
     * Info: don't add parameter types in the overridden addChild-Method, this ensures compatibility with php7.4!
     * @param \SimpleXMLElement|string $name
     * @param string|null $value
     * @param string|null $namespace
     * @return \SimpleXMLElement|null
     */
    public function addChild($name, $value = null, $namespace = null): ?\SimpleXMLElement {
        if ($name instanceof \SimpleXMLElement) {
            $newNode = $name;
            $node = dom_import_simplexml($this);
            $newNode = $node->ownerDocument->importNode(dom_import_simplexml($newNode), true);
            $node->appendChild($newNode);

            // Return last child, this is the added child!
            $children = $this->children();

            return $children[$children->count() - 1];
        } else {
            return parent::addChild($name, $value, $namespace);
        }
    }

    public function asPrettyXML() {
        $dom = dom_import_simplexml($this)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}

/**
 * moodle_url with shortcuts for creating, copying and overriding parameters, plus
 * access to the current request URI relative to wwwroot.
 *
 * @internal
 */
class url extends \moodle_url {
    public static function create($url, ?array $params = null, $anchor = null) {
        return new static($url, $params, $anchor);
    }

    /**
     *
     * @param array $overrideparams new attributes for object
     * @return self
     */
    public function copy(?array $overrideparams = null) {
        $object = new static($this);
        if ($overrideparams) {
            $object->params($overrideparams);
        }

        return $object;
    }

    protected function merge_overrideparams(?array $overrideparams = null) {
        $params = parent::merge_overrideparams($overrideparams);

        $overrideparams = (array)$overrideparams;
        foreach ($overrideparams as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            }
        }

        return $params;
    }

    public function params(?array $params = null) {
        parent::params($params);

        $params = (array)$params;
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($this->params[$key]);
            }
        }

        return $this->params;
    }

    public static function request_uri() {
        global $CFG;

        return new static(preg_replace('!^' . preg_quote(parse_url($CFG->wwwroot)['path'], '!') . '!', '', $_SERVER['REQUEST_URI']));
    }
}

/**
 * Returns the plugin name, derived from the namespace this file was copied into.
 *
 * @internal
 */
class helper {
    public static function plugin_name() {
        // Four backslashes in the single quoted string become two, which the regex reads as one
        // literal backslash - so everything from the first backslash on is cut off.
        return preg_replace('!\\\\.*$!', '', __NAMESPACE__);
    }
}

/**
 * Wrapper around Moodle's cache API: get, set, delete and purge with a TTL, plus
 * callback() to compute a value only on a cache miss. Handles the per user and
 * the session-less (CLI, webservice) case.
 *
 * @internal
 */
class cachelib {
    const CACHE_CLEAR = 2;

    /** @var array Cache instances per area, created on first use. */
    protected static array $caches = [];

    /** @var bool|null Set by set_enabled(), null means the cache is enabled. */
    protected static ?bool $cache_disabled = null;

    protected static function get_area(string $area) {
        if (!empty(static::$caches[$area])) {
            // Already created?
            return static::$caches[$area];
        }

        if ($area == 'user') {
            // The user area is special, it is per user
            global $USER;

            // User cache is only supported for loggedin user and not for guests.
            if (!$USER->id) {
                throw new \coding_exception('The user cache needs a logged in user');
            }
            if (isguestuser()) {
                throw new \coding_exception('The user cache is not available for guests');
            }

            // Use application cache, but split it for the specific user with the $identifiers parameter
            $cache = \cache::make(helper::plugin_name(), 'application', ['user-' . $USER->id]);
        } elseif ($area == 'session' && !session_id()) {
            // There is no session, so maybe this is a Webservice or a CLI Script
            // so use the request cache instead
            $cache = \cache::make(helper::plugin_name(), 'request');
        } else {
            $cache = \cache::make(helper::plugin_name(), $area);
        }

        static::$caches[$area] = $cache;

        return $cache;
    }

    /**
     * Getter for caches.
     * @param string $area either application or session.
     * @param mixed $key used to get a certain key from the cache.
     * @param ?int $ttl null forttl, > 0 for a specific ttl in seconds
     * @return mixed the cached value, or null if no cache! (this differs to moodle, which returns false on cache on no cache
     */
    public static function get(string $area, mixed $key, ?int $ttl = null): mixed {
        if (!($ttl === null || $ttl > 0)) {
            throw new \moodle_exception('wrong ttl');
        }

        $data = static::get_internal($area, $key);

        if (!$data) {
            return null;
        } elseif ($ttl && $data->timestamp <= time() - $ttl) {
            // Check ttl
            return null;
        } else {
            return $data->value;
        }
    }

    protected static function get_internal(string $area, mixed $key): mixed {
        if (static::$cache_disabled === null) {
            // The key is the same in every plugin with a copy of this file, so one developer switch disables all their caches
            static::$cache_disabled = $_SESSION['common_cache_disabled'] ?? false;
        }
        if (static::$cache_disabled) {
            return null;
        }

        $key = static::get_cache_key($key);
        $ret = static::get_area($area)->get($key);

        if (!$ret) {
            return null;
        } else {
            return $ret;
        }
    }

    /**
     * Method used as setter for caches.
     * @param string $area either application or session.
     * @param mixed $key used to get a certain key from the cache.
     * @param mixed $value used to set a value for a certain key in the cache.
     */
    public static function set(string $area, mixed $key, mixed $value): void {
        $key = static::get_cache_key($key);

        static::get_area($area)->set($key, (object)[
            'timestamp' => time(),
            'value' => $value,
        ]);
    }

    /**
     * This disables or enables the caching.
     * Used by local_bipidp to disable cache while syncing. This prevents errors,
     * as new items may not be cached and hence are created repeatedly.
     * @param bool $enabled by default true.
     */
    public static function set_enabled(bool $enabled = true): void {
        static::$cache_disabled = !$enabled;
    }

    /**
     * Delete cache entry
     * @param string $area either application or session.
     * @param mixed $key used to get a certain key from the cache.
     */
    public static function delete(string $area, mixed $key): void {
        $key = static::get_cache_key($key);
        static::get_area($area)->delete($key);
    }

    protected static function get_cache_key($key): string {
        if (!is_scalar($key)) {
            return json_encode($key);
        } else {
            return $key;
        }
    }

    public static function purge(string $area): void {
        static::get_area($area)->purge();
    }

    public static function callbackWithBypassCache(string $area, mixed $key, int $bypasscache, callable $callback, ?int $ttl = null): mixed {
        if ($bypasscache === static::CACHE_CLEAR) {
            static::delete($area, $key);
            return null;
        }

        if (!$bypasscache) {
            $ret = static::get($area, $key, $ttl);
            if ($ret !== null) {
                return $ret;
            }
        }

        $ret = $callback();

        if ($ret !== null) {
            static::set($area, $key, $ret);
        }

        return $ret;
    }

    public static function callback(string $area, mixed $key, callable $callback, ?int $ttl = null): mixed {
        return static::callbackWithBypassCache($area, $key, false, $callback, $ttl);
    }
}

/**
 * Login and capability checks, including require_all() and require_any() to combine
 * several checks in one call.
 *
 * @internal
 */
class permissions {
    /**
     * require_login(), but additionally no guests are allowed
     * @return void
     */
    public static function require_login(int|null $courseorid = null, bool $allow_guests = false): void {
        global $SESSION;

        require_login($courseorid);

        if (!$allow_guests && isguestuser()) {
            // Don't allow guest login
            $SESSION->wantsurl = qualified_me();
            redirect(get_login_url());
        }
    }

    public static function isloggedin() {
        return isloggedin() && !isguestuser();
    }

    public static function require_all(array $checks): void {
        foreach ($checks as $key => $check) {
            if (!$check) {
                throw new \moodle_exception('access_denied', debuginfo: 'check: ' . $key);
            }
        }
    }

    public static function require_any(array $checks): void {
        if (!static::has_any($checks)) {
            $key = '((' . implode(' || ', array_keys($checks)) . ') = false)';
            throw new \moodle_exception('access_denied', debuginfo: 'check: ' . $key);
        }
    }

    public static function has_all(array $checks): bool {
        foreach ($checks as $check) {
            if (!$check) {
                return false;
            }
        }

        return true;
    }

    public static function has_any(array $checks): bool {
        foreach ($checks as $check) {
            if ($check) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Renders a mustache template that is given as a string instead of a template file.
 *
 * @internal
 */
class output {
    public static function render_string_template(string $string, array|object $context): string {
        static $renderer = null;
        if (!$renderer) {
            $renderer = new class extends \renderer_base {
                public function __construct() {
                    global $PAGE;

                    parent::__construct($PAGE, RENDERER_TARGET_GENERAL);
                }

                public function render_string_template($str, $context) {
                    $mustache = $this->get_mustache();
                    return $mustache->loadLambda($str)->render($context);
                }
            };
        }

        return $renderer->render_string_template($string, $context);
    }
}
