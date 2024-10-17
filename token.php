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

define('AJAX_SCRIPT', true);
define('REQUIRE_CORRECT_ACCESS', true);
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/inc.php');

function block_exaport_load_service($service) {
    $CFG = $GLOBALS['CFG'];
    $OUTPUT = $GLOBALS['OUTPUT'];
    $DB = $GLOBALS['DB'];

    ob_start();
    try {
        $_POST['service'] = $service;
        require(__DIR__ . '/../../login/token.php');
    } catch (moodle_exception $e) {
        if ($e->errorcode == 'servicenotavailable') {
            return null;
        } else {
            throw $e;
        }
    }
    $ret = ob_get_clean();

    $data = json_decode($ret);
    if ($data && $data->token) {
        return $data->token;
    } else {
        return null;
    }
}

// Allow CORS requests.
header('Access-Control-Allow-Origin: *');
echo $OUTPUT->header();

required_param('app', PARAM_TEXT);
required_param('app_version', PARAM_TEXT);

if (optional_param('testconnection', false, PARAM_BOOL)) {
    echo json_encode([
        'moodleName' => $DB->get_field('course', 'fullname', ['id' => 1]),
    ], JSON_PRETTY_PRINT);
    exit;
}

$exatokens = [];

$services = optional_param('services', '', PARAM_TEXT);
// Default services + .
$services = array_keys(
    ['moodle_mobile_app' => 1, 'exaportservices' => 1] + ($services ? array_flip(explode(',', $services)) : []));

foreach ($services as $service) {
    $token = block_exaport_load_service($service);
    $exatokens[] = [
        'service' => $service,
        'token' => $token,
    ];
}

require_once(__DIR__ . '/externallib.php');

// Get login data.
$data = \block_exaport\externallib\externallib::login();
// Add tokens.
$data['tokens'] = $exatokens;

// Clean output.
$data = external_api::clean_returnvalue(\block_exaport\externallib\externallib::login_returns(), $data);

echo json_encode($data, JSON_PRETTY_PRINT);
