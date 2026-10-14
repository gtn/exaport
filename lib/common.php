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
    use block_exaport\moodle_exception;

    defined('MOODLE_INTERNAL') || die();

    /**
     * @deprecated use global $DB; for update_record() with a where condition and insert_or_update_record() use \block_exaport\db
     */
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
     *
     * @deprecated use global $DB;
     */
    class exadb_forwarder {
        public function __call($func, $args) {
            global $DB;

            return call_user_func_array([$DB, $func], $args);
        }
    }

    /**
     * @deprecated use global $DB; for update_record() with a where condition and insert_or_update_record() use \block_exaport\db
     */
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

    /**
     * @property string $wwwroot moodle url
     * @property string $dirroot moodle path
     * @property string $libdir lib path
     *
     * @deprecated use global $CFG;
     */
    class _globals_dummy_CFG {
    }

    /**
     * @deprecated use the Moodle globals in the function instead, eg. global $DB, $USER;
     */
    class globals {
        /**
         * @var exadb
         * @deprecated use global $DB; for update_record() with a where condition and insert_or_update_record() use \block_exaport\db
         */
        public static $DB;

        /**
         * @var \moodle_page
         * @deprecated use global $PAGE;
         */
        public static $PAGE;

        /**
         * @var \core_renderer
         * @deprecated use global $OUTPUT;
         */
        public static $OUTPUT;

        /**
         * @var \stdClass
         * @deprecated use global $COURSE;
         */
        public static $COURSE;

        /**
         * @var \stdClass
         * @deprecated use global $USER;
         */
        public static $USER;

        /**
         * @var \stdClass
         * @deprecated use global $SITE;
         */
        public static $SITE;

        /**
         * @var _globals_dummy_CFG
         * @deprecated use global $CFG;
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

    /**
     * @deprecated do not use
     */
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

        // if (true) { // quickfix
        //     $time = filemtime($totalfile);
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
     * @deprecated throw new \block_exaport\moodle_exception() instead
     */
    function print_error($errorcode, $module = 'error', $link = '', $a = null, $debuginfo = null) {
        throw new moodle_exception($errorcode, $module, $link, $a, $debuginfo);
    }
}

namespace block_exaport {
    /**
     * @deprecated use the Moodle globals in the function instead, eg. global $DB, $USER;
     */
    class globals extends common\globals {
    }
}
