<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
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
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

namespace block_exaport\common {

    use block_exaport\developer;

    defined('MOODLE_INTERNAL') || die();

    class url extends \moodle_url {
        public static function create($url, array $params = null, $anchor = null) {
            return new static($url, $params, $anchor);
        }

        /**
         *
         * @param array $overrideparams new attributes for object
         * @return self
         */
        public function copy(array $overrideparams = null) {
            $object = new static($this);
            if ($overrideparams) {
                $object->params($overrideparams);
            }

            return $object;
        }

        protected function merge_overrideparams(array $overrideparams = null) {
            $params = parent::merge_overrideparams($overrideparams);

            $overrideparams = (array)$overrideparams;
            foreach ($overrideparams as $key => $value) {
                if ($value === null) {
                    unset($params[$key]);
                }
            }

            return $params;
        }

        public function params(array $params = null) {
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

            return new static(preg_replace('!^' . preg_quote(parse_url($CFG->wwwroot)['path'], '!') . '!', '',
                $_SERVER['REQUEST_URI']));
        }
    }

    abstract class event extends \core\event\base {

        protected static function preparedata(array &$data) {
            if (!isset($data['contextid'])) {
                if (!empty($data['courseid'])) {
                    $data['contextid'] = \context_course::instance($data['courseid'])->id;
                } else {
                    $data['contextid'] = \context_system::instance()->id;
                }
            }
        }

        public static function log(array $data) {
            static::preparedata($data);

            return static::create($data)->trigger();
        }
    }

    class moodle_exception extends \moodle_exception {
        public function __construct($errorcode, $module = '', $link = '', $a = null, $debuginfo = null) {

            // Try to get local error message (use namespace as $component).
            if (empty($module)) {
                if (get_string_manager()->string_exists($errorcode, _plugin_name())) {
                    $module = _plugin_name();
                }
            }

            return parent::__construct($errorcode, $module, $link, $a, $debuginfo);
        }
    }

    class SimpleXMLElement extends \SimpleXMLElement {
        /**
         * Adds a child with $value inside CDATA
         *
         * @param string $name
         * @param mixed $value
         * @return SimpleXMLElement
         */
        public function add_child_with_cdata($name, $value = null) {
            $newchild = $this->add_child($name);

            if ($newchild !== null) {
                $node = dom_import_simplexml($newchild);
                $no = $node->ownerDocument;
                $node->appendChild($no->createCDATASection($value));
            }

            return $newchild;
        }

        public static function create($rootelement) {
            return new static('<?xml version="1.0" encoding="UTF-8"?><' . $rootelement . ' />');
        }

        public function add_child_with_cdata_if_value($name, $value = null) {
            if ($value) {
                return $this->add_child_with_cdata($name, $value);
            } else {
                return $this->add_child($name, $value);
            }
        }

        public function add_child($name, $value = null, $namespace = null) {
            if ($name instanceof SimpleXMLElement) {
                $newnode = $name;
                $node = dom_import_simplexml($this);
                $newnode = $node->ownerDocument->importNode(dom_import_simplexml($newnode), true);
                $node->appendChild($newnode);

                // Return last child, this is the added child!
                $children = $this->children();

                return $children[$children->count() - 1];
            } else {
                return parent::add_child($name, $value, $namespace);
            }
        }

        public function as_pretty_xml() {
            $dom = dom_import_simplexml($this)->ownerDocument;
            $dom->formatOutput = true;

            return $dom->saveXML();
        }
    }

    abstract class exadb extends \moodle_database {
        /**
         * @param string $table
         * @param array|object $data
         * @param array|null $where
         * @return null|bool|object
         */
        public function update_record($table, $data, $where = null) {
        }

        public function insert_or_update_record($table, $data, $where = null) {
        }
    }

    /**
     * Class exadb_forwarder
     * exadb_extender extends this call,
     * which allows exadb_extender to call parent::function(), which gets forwarded to $DB->function()
     */
    class exadb_forwarder {
        public function __call($func, $args) {
            global $DB;

            return call_user_func_array([$DB, $func], $args);
        }
    }

    class exadb_extender extends exadb_forwarder {

        /**
         * @param string $table
         * @param array|object $data
         * @param array|null $where
         * @return null|bool|object
         */
        public function update_record($table, $data, $where = null) {
            if ($where === null) {
                return parent::update_record($table, $data);
            }

            $where = (array)$where;
            $data = (array)$data;

            if ($dbitem = $this->get_record($table, $where)) {
                if ($data) {
                    $data['id'] = $dbitem->id;
                    parent::update_record($table, (object)$data);
                }

                return (object)($data + (array)$dbitem);
            }

            return null;
        }

        /**
         * @param $table
         * @param $data
         * @param null $where
         * @return object
         * @throws moodle_exception
         */
        public function insert_or_update_record($table, $data, $where = null) {
            $data = (array)$data;

            if ($dbitem = $this->get_record($table, $where !== null ? $where : $data)) {
                if (empty($data)) {
                    throw new moodle_exception('$data is empty');
                }

                $data['id'] = $dbitem->id;
                $this->update_record($table, (object)$data);

                return (object)($data + (array)$dbitem);
            } else {
                unset($data['id']);
                if ($where !== null) {
                    $data = $data + $where; // First the values of $data, then of $where, but don't override $data.
                }
                $id = $this->insert_record($table, (object)$data);
                $data['id'] = $id;

                return (object)$data;
            }
        }

        public function get_column_names($table) {
            return array_keys($this->get_columns($table));
        }

        public function get_column_names_prefixed($table, $prefix = '') {
            $prefix = trim($prefix, '.');

            $columns = $this->get_column_names($table);
            $columns = array_map(function($column) use ($prefix) {
                return $prefix . '.' . $column;
            }, $columns);

            return join(', ', $columns);
        }
    }

    class param {
        public static function clean_object($values, $definition) {
            if (!is_object($values) && !is_array($values)) {
                return null;
            }

            // Some value => type.
            $ret = new \stdClass;
            $values = (object)$values;
            $definition = (array)$definition;

            foreach ($definition as $key => $valuetype) {
                $value = isset($values->$key) ? $values->$key : null;

                $ret->$key = static::_clean($value, $valuetype);
            }

            return $ret;
        }

        public static function clean_array($values, $definition) {
            $definition = (array)$definition;

            if (is_object($values)) {
                $values = (array)$values;
            } else if (!is_array($values)) {
                return array();
            }

            $keytype = key($definition);
            $valuetype = reset($definition);

            // Allow clean_array(PARAM_TEXT): which means PARAM_INT=>PARAM_TEXT.
            if ($keytype === 0) {
                $keytype = PARAM_SEQUENCE;
            }

            if ($keytype !== PARAM_INT && $keytype !== PARAM_TEXT && $keytype !== PARAM_SEQUENCE) {
                throw new moodle_exception('wrong key type: ' . $keytype);
            }

            $ret = array();
            foreach ($values as $key => $value) {
                $value = static::_clean($value, $valuetype);
                if ($value === null) {
                    continue;
                }

                if ($keytype == PARAM_SEQUENCE) {
                    $ret[] = $value;
                } else {
                    $ret[clean_param($key, $keytype)] = $value;
                }
            }

            return $ret;
        }

        protected static function _clean($value, $definition) {
            if (is_object($definition)) {
                return static::clean_object($value, $definition);
            } else if (is_array($definition)) {
                return static::clean_array($value, $definition);
            } else {
                return clean_param($value, $definition);
            }
        }

        public static function get_param($parname, $isarray = false) {
            // POST has precedence.
            if ($isarray) {
                return optional_param_array($parname, null, PARAM_RAW);
            } else {
                return optional_param($parname, null, PARAM_RAW);
            }
        }

        public static function get_required_param($parname, $isarray = false) {
            $param = static::get_param($parname, $isarray);

            if ($param === null) {
                throw new moodle_exception('param not found: ' . $parname);
            }

            return $param;
        }

        public static function optional_array($parname, $definition) {
            $param = static::get_param($parname, true);

            if ($param === null) {
                return array();
            } else {
                return static::clean_array($param, $definition);
            }
        }

        public static function required_array($parname, $definition) {
            $param = static::get_required_param($parname, true);

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
                return static::_clean($data, $definition);
            }
        }
    }

    /**
     * @property string $wwwroot moodle url
     * @property string $dirroot moodle path
     * @property string $libdir lib path
     */
    class _globals_dummy_CFG {
    }

    class globals {
        /**
         * @var exadb
         */
        public static $DB;

        /**
         * @var \moodle_page
         */
        public static $PAGE;

        /**
         * @var \core_renderer
         */
        public static $OUTPUT;

        /**
         * @var \stdClass
         */
        public static $COURSE;

        /**
         * @var \stdClass
         */
        public static $USER;

        /**
         * @var \stdClass
         */
        public static $SITE;

        /**
         * @var _globals_dummy_CFG
         */
        public static $CFG;

        public static function init() {
            global $PAGE, $OUTPUT, $COURSE, $USER, $CFG, $SITE;
            globals::$DB = new exadb_extender();
            globals::$PAGE =& $PAGE;
            globals::$OUTPUT =& $OUTPUT;
            globals::$COURSE =& $COURSE;
            globals::$USER =& $USER;
            globals::$CFG =& $CFG;
            globals::$SITE =& $SITE;
        }
    }

    globals::init();

    function _plugin_name() {
        return preg_replace('!\\\\.*$!', '', __NAMESPACE__); // The \\\\ syntax matches a \ (backslash)!
    }

    call_user_func(function() {
        if (!globals::$CFG->debugdeveloper) {
            return;
        }

        $lang = current_language();
        $langdir = dirname(__DIR__) . '/lang';
        $totalfile = $langdir . '/total.php';
        $langfile = $langdir . '/' . $lang . '/' . _plugin_name() . '.php';

        if (file_exists($totalfile) && file_exists($langfile) && ($time = filemtime($totalfile)) != filemtime($langfile) &&
            is_writable($langfile)
        ) {
            // Regenerate must be enabled by developer with uncommenting below code.
            // It is needed for security reasons

            // comment/uncomment from here:


            // Regenerate.

            // Test require, check if file has a parse error etc.
            require($totalfile);

            // Get copyright.
            $content = file_get_contents($totalfile);
            if (!preg_match('!(//.*\r?\n)+!', $content, $matches)) {
                throw new moodle_exception('copyright not found');
            }

            $copyright = $matches[0];
            $content = str_replace($copyright, '', $content);

            $content = preg_replace_callback('!^(?<comment>\s*\/\/\s*.*)!m', function($matches) { // also may be '//' instead of '\/\/';
                return var_export(preg_replace('!^[ \t]+!m', '', $matches['comment']), true) . ',';
            }, $content);

            $totallanguages = eval('?>' . $content);

            $bylang = [];

            foreach ($totallanguages as $key => $langs) {
                if (is_int($key)) {
                    $bylang['de'][] = $langs;
                    $bylang['en'][] = $langs;
                    continue;
                }
                if (!$langs) {
                    $bylang['de'][$key] = null;
                    $bylang['en'][$key] = null;
                    continue;
                }
                foreach ($langs as $lang => $value) {
                    if ($lang === 0) {
                        $lang = 'de';
                    } else if ($lang === 1) {
                        $lang = 'en';
                    }
                    if ($value === null && preg_match('!^' . $lang . ':(.*)$!', $key, $matches)) {
                        $bylang[$lang][$key] = $matches[1];
                    } else {
                        $bylang[$lang][$key] = $value;
                    }
                }
            }

            foreach ($bylang as $lang => $strings) {
                $output = '<?php' . "\n{$copyright}\n";

                foreach ($strings as $key => $value) {
                    if (is_int($key)) {
                        $output .= $value . "\n";
                    } else if (strpos($key, '===') === 0) {
                        // Group.
                        $output .= "\n\n// " . trim($key, ' =') . "\n";
                    } else if ($value === null) {
                        // For code checker.
                        $tempvar = 1;
                    } else {
                        $output .= '$string[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
                    }
                }

                file_put_contents($langdir . '/' . $lang . '/' . _plugin_name() . '.php', $output);
                @touch($langdir . '/' . $lang . '/' . _plugin_name() . '.php', $time);
            }

            /* Uncomment to here for language file changing */
        }

        // Include other developer scripts.
        // include other developer scripts
        developer::developer_actions();
    });

    /**
     * get a language string from current plugin or else from global language strings
     *
     * @param $identifier
     * @param null $component
     * @param null $a
     * @return string
     */
    function get_string($identifier, $component = null, $a = null) {
        $manager = get_string_manager();

        if ($component === null) {
            $component = _plugin_name();
        }

        if ($manager->string_exists($identifier, $component)) {
            return $manager->get_string($identifier, $component, $a);
        }

        return $manager->get_string($identifier, '', $a);
    }

    function print_error($errorcode, $module = 'error', $link = '', $a = null, $debuginfo = null) {
        throw new moodle_exception($errorcode, $module, $link, $a, $debuginfo);
    }

    function _t_check_identifier($string) {
        if (preg_match('!^([^:]+):(.*)$!s', $string, $matches)) {
            return $matches;
        } else {
            return null;
        }
    }

    function _t_parse_string($string, $a) {
        // Copy from moodle/lib/classes/string_manager_standard.php
        // Process array's and objects (except lang_strings).
        if (is_array($a) or (is_object($a) && !($a instanceof \lang_string))) {
            $a = (array)$a;
            $search = array();
            $replace = array();
            foreach ($a as $key => $value) {
                if (is_int($key)) {
                    // We do not support numeric keys - sorry!
                    continue;
                }
                if (is_array($value) or (is_object($value) && !($value instanceof \lang_string))) {
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

    /**
     * translator function
     */
    function trans($stringorstrings, $argorargs = null) {

        $origargs = $args = func_get_args();

        $languagestrings = null;
        $identifier = '';
        $a = null;

        if (empty($args)) {
            throw new moodle_exception('no args');
        }

        $arg = array_shift($args);
        if (is_string($arg) && !_t_check_identifier($arg)) {
            $identifier = $arg;
            $arg = array_shift($args);
        }

        if ($arg === null) {
            // Just id submitted.
            $languagestrings = [];
        } else if (is_array($arg)) {
            $languagestrings = $arg;
        } else if (is_string($arg) && $matches = _t_check_identifier($arg)) {
            $languagestrings = [$matches[1] => $matches[2]];
        } else {
            throw new moodle_exception('wrong args: ' . json_encode($origargs));
        }

        if ($args) {
            $a = array_shift($args);
        }

        if ($args) {
            throw new moodle_exception('too many arguments: ' . json_encode($origargs, true));
        }

        // Parse $languagestrings.
        foreach ($languagestrings as $key => $string) {
            if (is_number($key)) {
                if ($matches = _t_check_identifier($string)) {
                    $languagestrings[$matches[1]] = $matches[2];
                    unset($languagestrings[$key]);
                } else {
                    throw new moodle_exception('wrong language string: ' . $origargs);
                }
            }
        }

        $lang = current_language();

        $manager = get_string_manager();
        $component = _plugin_name();

        // Try with $identifier from args.
        if ($identifier && $manager->string_exists($identifier, $component)) {
            return $manager->get_string($identifier, $component, $a);
        }

        // Try submitted language strings.
        if (isset($languagestrings[$lang])) {
            return _t_parse_string($languagestrings[$lang], $a);
        }

        // Try language string.
        $identifier = reset($languagestrings);
        $identifier = key($languagestrings) . ':' . $identifier;
        if ($manager->string_exists($identifier, $component)) {
            return $manager->get_string($identifier, $component, $a);
        }

        if ($languagestrings) {
            return _t_parse_string(reset($languagestrings), $a);
        } else {
            $message = "language string '{$origargs[0]}' not found, did you forget to prefix a language? 'en:{$origargs[0]}'";
            throw new moodle_exception($message);
        }
    }
}

// Exporting all classes and functions from the common namespace to the plugin namespace
// the whole part below is done, so eclipse knows the common classes and functions.

namespace block_exaport {

    function _should_export_class($classname) {
        return !class_exists(__NAMESPACE__ . '\\' . $classname);
    }

    function _export_function($function) {
        if (!function_exists(__NAMESPACE__ . '\\' . $function)) {
            /*eval('
                namespace '.__NAMESPACE__.' {
                    function '.$function.'() {
                        return call_user_func_array(\'\\'.__NAMESPACE__.'\common\\'.$function.'\', func_get_args());
                    }
                }
            ');*/
        }

        return false;
    }

    // Export classnames, if not already existing.
    if (_should_export_class('event')) {
        abstract class event extends common\event {
        }
    }
    if (_should_export_class('moodle_exception')) {
        class moodle_exception extends common\moodle_exception {
        }
    }
    if (_should_export_class('globals')) {
        class globals extends common\globals {
        }
    }
    if (_should_export_class('param')) {
        class param extends common\param {
        }
    }
    if (_should_export_class('SimpleXMLElement')) {
        class SimpleXMLElement extends common\SimpleXMLElement {
        }
    }
    if (_should_export_class('url')) {
        class url extends common\url {
        }
    }
}
