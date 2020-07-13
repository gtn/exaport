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

defined('MOODLE_INTERNAL') || die();

call_user_func(function() {

    // Next functinality is using only after changes in services functions. (Only for exaport developers)
    // So, use (uncomment it) only after such changes.
    // For productive installtion it must be commented!

    /*
    $servicesfile = __DIR__.'/../db/services.php';

    // Get copyright. From this file.
    $thisfile = file_get_contents(__FILE__);
    if (!preg_match('!(//.*\r?\n)+!', $thisfile, $matches)) {
        throw new moodle_exception('copyright not found');
    }
    $copyright = $matches[0];

    if (file_exists($servicesfile)) {
        if (!is_writable($servicesfile)) {
            // No change possible.
            return;
        }
        $time = filemtime(__DIR__.'/../externallib.php');
        //if (filemtime($servicesfile) == ($time)) {
            // No change required
            // return.
        //}
    }

    $services = array(
        'exaportservices' => array(
            'requiredcapability' => '',
            'restrictedusers' => 0,
            'enabled' => 1,
            'shortname' => 'exaportservices',
            'functions' => [],
        ),
    );

    $functions = [];

    $doku = '';

    //extract($GLOBALS);
    $CFG = $GLOBALS['CFG'];
    $OUTPUT = $GLOBALS['OUTPUT'];
    $DB = $GLOBALS['DB'];
    $USER = $GLOBALS['USER'];
    $PAGE = $GLOBALS['PAGE'];
    $COURSE = $GLOBALS['COURSE'];
    $SITE = $GLOBALS['SITE'];
    $ME = $GLOBALS['ME'];
    $FULLME = $GLOBALS['FULLME'];
    $SCRIPT = $GLOBALS['SCRIPT'];
    require_once(__DIR__.'/../externallib.php');

    $rc = new ReflectionClass('block_exaport_external');
    $methods = $rc->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
    foreach ($methods as $method) {
        if (!preg_match('!@ws-type-(read|write)!', $method->getDocComment(), $matches)) {
            continue;
        }

        $description = preg_replace('!^[/\t \\*]+!m', '', $method->getDocComment());
        $description = trim(preg_replace('!@.*!sm', '', $description));

        $func = 'block_exaport_'.$method->getName();

        $functions[$func] = [                                   // Web service function name.
            'classname' => 'block_exaport_external',            // Class containing the external function.
            'methodname' => $method->getName(),                 // External function name, strip block_exacomp_ for function name.
            'classpath' => 'blocks/exaport/externallib.php',    // File containing the class/external function.
            'description' => $description,                      // Human readable description of the web service function.
            'type' => $matches[1],                              // Database rights of the web service function (read, write).
        ];

        $services['exaportservices']['functions'][] = $func;

        // Doku.
        $doku .= "<h2>$func</h2>\n";
        $doku .= "<div>$description</div>\n";
        $doku .= "<div>type: $matches[1]</div>\n";

        $parammethod = $rc->getMethod($method->getName().'_parameters');
        // * @var external_function_parameters $params
        $params = $parammethod->invoke(null)->keys;
        $doku .= "Params: <table>\n";
        foreach ($params as $paramname => $paraminfo) {
            $doku .= "<tr>\n";
            $doku .= '<td>'.$paramname."</td>\n";
            $doku .= '<td>'.$paraminfo->type."</td>\n";
            $doku .= '<td>'.($paraminfo->allownull ? 'null' : 'not null')."</td>\n";
            $doku .= '<td>'.($paraminfo->required ? 'required' : 'optional')."</td>\n";
            if (!$paraminfo->required) {
                ob_start();
                var_dump($paraminfo->default);
                $default = ob_get_clean();
                $doku .= '<td>default: '.$default."</td>\n";
            } else {
                $doku .= '<td>'."</td>\n";
            }
            $doku .= '<td>'.$paraminfo->desc."</td>\n";
        }
        $doku .= "</table>\n";

        $returnmethod = $rc->getMethod($method->getName().'_returns');
        // * @var external_description $returns
        $returns = $returnmethod->invoke(null);

        $recursor = function($o) use (&$recursor) {
            if ($o instanceof external_multiple_structure) {
                $ret = [];
                $ret[] = $recursor($o->content);
                if ($o->desc) {
                    $ret[] = '... '.$o->desc.' ...';
                } else {
                    $ret[] = '...';
                }
                return $ret;
            } else if ($o instanceof external_single_structure) {
                $data = [];
                foreach ($o->keys as $paramname => $paraminfo) {
                    if ($paraminfo instanceof external_value) {
                        $data[$paramname] = $paraminfo->type.
                            ' '.($paraminfo->allownull ? 'null' : 'not null').
                            ' ('.$paraminfo->desc.')';
                    } else if ($paraminfo instanceof external_multiple_structure
                                || $paraminfo instanceof external_single_structure) {
                        $data[$paramname] = $recursor($paraminfo);
                    } else {
                        die('o');
                    }
                }

                return $data;
            } else {
                die('x');
                $doku .= get_class($o);
            }
        };
        $data = $recursor($returns);

        $doku .= "Returns:<pre>".json_encode($data, JSON_PRETTY_PRINT)."</pre>\n";
    }

    // Save to services.php.
    $content = "<?php\n";
    $content .= $copyright."\n";
    $content .= "defined('MOODLE_INTERNAL') || die();\n\n";
    $content .= '$functions = '.var_export($functions, true).";\n\n";
    $content .= '$services = '.var_export($services, true).";\n\n";
    // For moodle code checker.
    $content = preg_replace('/=>\s*\n\s*array\s*\(/', '=> array (', $content);
    file_put_contents($servicesfile, $content);
    @touch($servicesfile, $time);

    // Save doku.
    $doku = '<style>
        table {
            border-collapse: collapse;
        }
        td {
            border: 1px solid black;
            padding: 2px 5px;
        }
    </style>'.$doku;

    file_put_contents(__DIR__.'/services.htm', $doku);
    @touch(__DIR__.'/services.htm', $time);
    */
});
