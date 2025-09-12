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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();
\block_exaport\vendor::load();

use context_course;
use context_user;
use CURLFile;
use Firebase\JWT\JWT;
use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use stored_file;

/**
 * Different function to work with WordPress
 */
class wp_integration {

    private $courseId;
    private $passphrase;

    private $wpLoginData = null;

    private $filesToExport = [];

    public function __construct($courseId, $passphrase) {
        $this->courseId = $courseId;
        $this->passphrase = $passphrase;
    }

    public function handleWpAjaxRequest($action, $parameters) {
        global $CFG;

        switch ($action) {
            case 'login':
                $loginData = $this->loginToWp(true);
                echo json_encode($loginData);
                exit;
                break;
            case 'loginUpdate':
                $loginData = $this->loginToWp(true);
                echo json_encode($loginData);
                exit;
                break;
            case 'wpForm':
                $formContent = $this->exportFormView();
                echo $formContent;
                exit;
                break;
            case 'viewRemove':
                $viewId = @$parameters['viewId'];
                // check - only MY!!
                $views = block_exaport_get_my_views();
                if (array_key_exists($viewId, $views)) {
                    $view = $views[$viewId];
                    $removeResult = $this->removeView($view);
                    echo json_encode($removeResult);
                }
                exit;
                break;
            case 'viewExport':
            case 'viewUpdate':
                $withUpdate = false;
                if ($action == 'viewUpdate') {
                    $withUpdate = true;
                }
                $viewId = @$parameters['viewId'];
                // check - only MY!!
                $views = block_exaport_get_my_views();
                if (array_key_exists($viewId, $views)) {
                    $view = $views[$viewId];
                    $exportResult = $this->exportView($view, $withUpdate);
                    // convert time to Moodle format
                    if (isset($exportResult['response']['view']['timemodified'])) {
                        $exportResult['response']['view']['timemodified'] = userdate($exportResult['response']['view']['timemodified']);
                    }
                    echo json_encode($exportResult);
                }
                exit;
                break;
            case 'cvUpdate':
            case 'cvExport':
                $withUpdate = false;
                if ($action == 'cvUpdate') {
                    $withUpdate = true;
                }
                $resumeData = $this->prepareResume();
                $exportResult = $this->exportCV($resumeData, $withUpdate);
                // convert time to Moodle format
                if (isset($exportResult['response']['cv']['timemodified'])) {
                    $exportResult['response']['cv']['timemodified'] = userdate($exportResult['response']['cv']['timemodified']);
                }
                echo json_encode($exportResult);
                exit;
                break;
            case 'cvRemove':
                // check - only MY!!
                $removeResult = $this->removeCV();
                echo json_encode($removeResult);
                exit;
                break;
            case 'requestPassphrase':
                $requestResult = $this->ssoPassphraseRequest();
                echo json_encode($requestResult);
                exit;
                break;
            case 'removePassphrase':
                $requestResult = $this->ssoPassphraseRemove();
                echo json_encode($requestResult);
                exit;
                break;
            case 'testPassphrase':
                $requestResult = $this->testSSO();
                echo json_encode($requestResult, JSON_UNESCAPED_UNICODE);
                exit;
                break;
            default:
                echo 'no selected action!!!';
                exit;
                break;
        }

    }


    public function exportFormView() {
        global $CFG;

        $html = '';
        $html .= '<div class="exaport-wp-integration">';
        $html .= '<form class="exaport-wp-form" data-ajaxUrl="' . $CFG->wwwroot . '/blocks/exaport/importexport.php?courseid=' . $this->courseId . '">';
        $html .= '<h4 class="text-center">WordPress integration</h4>';

        $userLoggedIn = $this->checkWpLogin();
        if ($userLoggedIn) {
            $html .= '
                <div class="alert alert-info">
                    You are logged in to WordPress as the user <strong>' . $this->wpLoginData['login'] . '</strong> (id: ' . $this->wpLoginData['id'] . ' <span class="text-danger">!!! remove ID after developing !!!</span>)
                </div>';
            $html .= '<div class="row my-2">';
            // "view my WP profile" button
            $directLoginToken = $this->generateJWTtoken('directLogin');
            $loginSsoUrl = $this->getWpUrl($directLoginToken);
            $html .= '<div class="col-sm-6">
                <a class="btn btn-primary exaport-wp-directLogin" href="' . $loginSsoUrl . '" target="_blank">
                    ' . block_exaport_fontawesome_icon('eye', 'solid', 1, [], [], [], [], [], [], [], []) . '
                    View my WordPress profile page
                </a>
            </div>';
            // update button
            $html .= '<div class="col-sm-6 text-right">
                <button class="btn btn-primary exaport-wp-loginUpdate">
                    ' . block_exaport_fontawesome_icon('arrows-rotate', 'solid', 1, [], [], [], [], [], [], [], []) . '
                    Update my WordPress profile data
                </button>
            </div>';
            $html .= '</div>';

            // CV import button
            $html .= '<h5 class="text-center">Curriculum Vitae</h5>';
            if ($this->wpLoginData['cv']) {
                $cvInfoClass = '';
                $cvExportClass = 'd-none';
            } else {
                $cvInfoClass = 'd-none';
                $cvExportClass = '';
            }

            $html .= '<div class="alert alert-info exaport-wp-cv-info ' . $cvInfoClass . '">';
            // convert time to Moodle format
            if (isset($this->wpLoginData['cv']['timemodified'])) {
                $this->wpLoginData['cv']['timemodified'] = userdate($this->wpLoginData['cv']['timemodified']);
            }
            $html .= '<p>Your CV is already exported to WordPress at <span class="date">' . @$this->wpLoginData['cv']['timemodified'] . '</span></p>';
            // view button
            $cvUrl = @$this->wpLoginData['cv']['shortUrl'] ?: @$this->wpLoginData['cv']['url'] ?: '';
            $html .= '<a class="btn btn-success btn-sm text-white exaport-wp-cvView" href="' . $cvUrl . '" target="_blank">
                        ' . block_exaport_fontawesome_icon('eye', 'solid', 1, [], [], [], [], [], [], [], []) . '
                        View
                       </a>';
            // update button
            $html .= '<button type="button" class="btn btn-primary btn-sm ml-3 text-white exaport-wp-cvUpdate">
                        ' . block_exaport_fontawesome_icon('arrows-rotate', 'solid', 1, [], [], [], [], [], [], [], []) . '
                        Update CV data in WordPress
                    </button>';
            // remove button
            $html .= '<button type="button" class="btn btn-danger btn-sm float-right text-white exaport-wp-cvRemove">
                        ' . block_exaport_fontawesome_icon('trash-can', 'solid', 1, [], [], [], [], [], [], [], []) . '
                        Remove
                       </button>';
            $html .= '</div>';

            // export to WordPress button
            $html .= '<div class="' . $cvExportClass . '">
                    <button type="button" class="btn btn-primary text-white exaport-wp-cvExport">
                        ' . block_exaport_fontawesome_icon('arrow-up-from-bracket', 'solid', 1, [], [], [], [], [], [], [], []) . '
                        Import CV into WordPress
                    </button>
                </div>';


            // Views - list of my Moodle views and the information about exported
            $html .= $this->myViewsForm();
        } else {
            $html .= $this->loginForm();
        }

        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    public function loginForm() {
        $html = '';
        $html .= '<div class="alert alert-info">';
        $html .= '<p>You do not have a user in the associated Wordpress instance</p>';
        $html .= '<button class="btn btn-primary exaport-wp-login">Register User</button>';
        $html .= '</div>';

        return $html;
    }


    public function checkWpLogin() {
        $loginData = $this->loginToWp();
        if (@$loginData['response']['success']) {
            $this->wpLoginData = $loginData['response']['user'];
            return true;
        }
        $this->wpLoginData = null;
        return false;
    }

    /**
     * @param bool $createNew used also for data updating
     * @return array
     */
    public function loginToWp($createNew = false) {
        global $USER;

        $files = [];
        $postData = [];

        $addData = [];
        if ($createNew) {
            $addData['createNew'] = 1;
            $addData['first_name'] = $USER->firstname ?: '';
            $addData['last_name'] = $USER->lastname ?: '';
        }
        // Add user's icon
        $usersIcon = $this->getUserIcon();
        if ($usersIcon) {
            $usersIconHash = $this->addFileToPost($usersIcon/*, 'u' . $USER->id . '_'*/);
            $postData['icon'] = $usersIconHash;
        }

        $token = $this->generateJWTtoken('getUser', $addData);
        $loginResponse = $this->sendTokenToWP($token, $postData, $this->filesToExport);

        return $loginResponse;
    }

    /**
     * generates the token with data
     * @param array $addData
     * @return string
     */
    public function generateJWTtoken($action = '', $addData = []) {
        global $CFG, $USER;

        // default data for all requests
        $payload = [
            'action' => $action,
            'iss' => $CFG->wwwroot,
            'iat' => time() - 10,
            'exp' => time() + (5 * 3600), // add 5 hours (possible bug time difference) - TODO: ....
            'userid' => $USER->id,
            'email' => $USER->email,
            'source' => get_config('block_exaport', 'mysource'),
        ];
        // additional data - put into JWT token
        if ($addData) {
            $payload = array_merge($payload, $addData);
        }
        $token = JWT::encode($payload, $this->passphrase, 'HS256');

        return $token;
    }

    /**
     * @param string $token
     * @param array $postData
     * @return array
     */
    public function sendTokenToWP($token, $postData = [], $files = []) {
        $url = $this->getWpUrl($token);

        $curl = curl_init();

        $headers = [
            'Authorization: Bearer ' . $token,
        ];

        if ($postData || $files) {

            $curl_postfields_flatten = function($data, $prefix = '') use (&$curl_postfields_flatten) {
                if (!is_array($data)) {
                    return $data; // in case someone sends an url-encoded string by mistake
                }
                $output = array();
                foreach ($data as $key => $value) {
                    $final_key = $prefix ? "{$prefix}[{$key}]" : $key;
                    if (is_array($value)) {
                        $output += $curl_postfields_flatten($value, $final_key);
                    } else {
                        $output[$final_key] = $value;
                    }
                }
                return $output;
            };
            $flatpostData = $curl_postfields_flatten($postData);

            // add FILES into request
            if ($files) {
                foreach ($files as $key => $fileData) {
                    if (file_exists($fileData['path'])) {
                        $flatpostData[$key] = new CURLFile($fileData['path'], $fileData['mime'], $fileData['filename']);
                    } else {
                        throw new \Exception('File not found: ' . $fileData['path']);
                    }
                }
                $headers[] = 'Content-Type: multipart/form-data';
            }

            if ($flatpostData) {
                curl_setopt_array($curl, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $flatpostData,
                ]);
            }
        } else {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $response = json_decode($response, true);

        return [
            'response' => $response,
            'status' => $httpcode,
        ];
    }

    private function getWpUrl($token) {
        global $CFG;

        $urlParams = [
            'exaport_token' => $token,
            'exaport_source' => get_config('block_exaport', 'mysource'), // needed to compare pairs: source<->passphrase
        ];

        $base = rtrim(\block_exaport\wordpress_lib::get_sso_url(), '/');
        $url = html_entity_decode($base . '/?' . http_build_query($urlParams));

        return $url;
    }

    /**
     * returns the form to manage WP integration with my views
     * @return string
     * @throws coding_exception
     */
    public function myViewsForm() {
        global $DB, $USER, $CFG;
        $html = '';

        // The list of existing views
        $views = block_exaport_get_my_views();

        $html .= '<h5 class="text-center">My views</h5>';

        if (!$views) {
            $html .= '<div class="alert alert-light">' . get_string("noviews", "block_exaport") . '</div>';
        } else {

            $table = new html_table();
            $table->width = "100%";

            $table->head = array();

            $table->head['name'] = get_string("name", "block_exaport");
            $table->head['timemodified'] = get_string("date", "block_exaport");
            $table->head['exported'] = 'Exported into WordPress';
            $table->head['timemodifiedWp'] = 'WordPress updated on';
            $table->head['wpView'] = '';
            $table->head['buttonUpdateExportToWp'] = '';
            $table->head['buttonRemoveFromWp'] = '';

            $exportedViews = $this->wpLoginData['views'];

            $table->data = array();
            $vi = -1;
            foreach ($views as $view) {
                $row = new html_table_row();
                // name
                $cell = new html_table_cell();
                $cell->text = '<a href="' .
                    s($CFG->wwwroot . '/blocks/exaport/shared_view.php?courseid=' . $this->courseId . '&access=id/' . $USER->id . '-' . $view->id) . '" target="_blank">' .
                    format_string($view->name) . "</a>";
                $row->cells[] = $cell;

                // timemodified (moodle)
                $cell = new html_table_cell();
                $cell->text = userdate($view->timemodified);
                $row->cells[] = $cell;

                // exported
                $cell = new html_table_cell();
                $cell->text = '<span class="wp-exported-icons-wrapper" data-viewId="' . $view->id . '">';
                if (array_key_exists($view->id, $exportedViews)) {
                    if ($exportedViews[$view->id]['status'] != 'publish') {
                        $cell->text .= block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exportedHidden']);
                    } else if ($exportedViews[$view->id]['timemodified'] < $view->timemodified) {
                        $cell->text .= block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exportedOld']);
                        $cell->text .= block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exported', 'd-none']); // Add the icon for JS using
                    } else {
                        $cell->text .= block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exported']);
                    }
                } else {
                    // only hidden "exported" icon
                    $cell->text .= block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exported', 'd-none']);
                }
                $row->cells[] = $cell;

                // timemodified (WP)
                $cell = new html_table_cell();
                $cell->text = '<span class="wp-exported-wptimemodified-wrapper" data-viewId="' . $view->id . '">';
                if (array_key_exists($view->id, $exportedViews)) {
                    $cell->text .= userdate($exportedViews[$view->id]['timemodified']);
                }
                $row->cells[] = $cell;

                // WP buttons
                // 1. view button
                $cell = new html_table_cell();
                $dNone = 'd-none';
                $wpUrl = '#';
                if (array_key_exists($view->id, $exportedViews)) {
                    $dNone = '';
                    $wpUrl = @$exportedViews[$view->id]['shortUrl'] ?: @$exportedViews[$view->id]['url'] ?: '';
                }
                $cell->text = '<a class="btn btn-success btn-sm exaport-wp-viewPreview ' . $dNone . '" data-viewId="' . $view->id . '" target="_blank" href="' . $wpUrl . '">
                        ' . block_exaport_fontawesome_icon('eye', 'solid', 1, [], [], [], [], [], [], [], []) . '
                        View in WordPress</a>';
                $cell->attributes['class'] = ' wpView';
                $row->cells[] = $cell;

                // wpExport buttons:
                // 2. export / update buttons
                $cell = new html_table_cell();
                $dNoneExport = '';
                $dNoneUpdate = 'd-none';
                $dNoneRemove = 'd-none';
                if (array_key_exists($view->id, $exportedViews)) {
                    $dNoneExport = 'd-none';
                    $dNoneUpdate = '';
                    $dNoneRemove = '';
                }
                $cell->text = '<button type="button" class="btn btn-primary btn-sm exaport-wp-viewExport ' . $dNoneExport . '" data-viewId="' . $view->id . '">
                    ' . block_exaport_fontawesome_icon('arrow-up-from-bracket', 'solid', 1, [], [], [], [], [], [], [], []) . '
                    Export to WordPress
                    </button>';
                $cell->text .= '<button type="button" class="btn btn-primary btn-sm exaport-wp-viewUpdate ' . $dNoneUpdate . '" data-viewId="' . $view->id . '">
                    ' . block_exaport_fontawesome_icon('arrows-rotate', 'solid', 1, [], [], [], [], [], [], [], []) . '
                    Update in WordPress
                    </button>';
                $cell->attributes['class'] = ' wpExport';
                $row->cells[] = $cell;

                // 3. remove button
                $cell = new html_table_cell();
                $cell->text = '<button type="button" class="btn btn-danger btn-sm exaport-wp-viewRemove ' . $dNoneRemove . '" data-viewId="' . $view->id . '">
                    ' . block_exaport_fontawesome_icon('trash-can', 'solid', 1, [], [], [], [], [], [], [], []) . '
                    Remove from WordPress
                    </button>';
                $cell->attributes['class'] = ' wpExport';
                $row->cells[] = $cell;

                $table->data[] = $row;
            }

            $tableOutput = html_writer::table($table);
            $html .= $tableOutput;
            // Legend
            $html .= '
                <div class="wp-viewsList-legend">
                    ' . block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exported']) . ' - ' . block_exaport_get_string('wp_exported_view') . '<br>
                    ' . block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exportedHidden']) . ' - ' . block_exaport_get_string('wp_exported_view_hidden_in_wp') . '<br>
                    ' . block_exaport_fontawesome_icon('check', 'solid', 1, [], [], [], [], [], [], [], ['wp-icon', 'wp-exportedOld']) . ' - ' . block_exaport_get_string('wp_exported_view_newer_than_wp') . '
                </div>
            ';

            // toaster html
            $html .= '
                <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
                  <div id="wpToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                      <div class="toast-body">
                        ---
                      </div>
                      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                  </div>
                </div>
                ';

        }

        $html .= '';

        return $html;
    }


    public function exportView($view, $withUpdate = false) {
        global $CFG;

        $dataToWp = [
            'view' => [
                'id' => $view->id,
                'name' => $view->name,
                'description' => $view->description,
                'timemodified' => $view->timemodified,
                'langid' => $view->langid, // TODO: lang?
                'layout' => $view->layout,
                'blocks' => $this->getViewBlocksForExport($view),
            ],
        ];
        if ($withUpdate) {
            $dataToWp['update'] = 1;
        }
        // generate the token
        $exportToken = $this->generateJWTtoken('exportView');

        // send the request to WP
        $exportResponse = $this->sendTokenToWP($exportToken, $dataToWp, $this->filesToExport);

        return $exportResponse;
    }

    public function exportCV($cvData, $withUpdate = false) {
        global $CFG;
        $postData = [
            'cvData' => $cvData,
        ];
        if ($withUpdate) {
            $postData['update'] = 1;
        }
        // generate the token
        $exportToken = $this->generateJWTtoken('exportCv');
        // send the request to WP
        $exportResponse = $this->sendTokenToWP($exportToken, $postData, $this->filesToExport);

        return $exportResponse;
    }

    public function removeCV() {
        // generate the token
        $exportToken = $this->generateJWTtoken('removeCv');
        // send the request to WP
        $exportResponse = $this->sendTokenToWP($exportToken);

        return $exportResponse;
    }

    public function removeView($view) {
        global $CFG;
        $dataToWp = [
            'viewId' => $view->id,
        ];
        // generate the token
        $exportToken = $this->generateJWTtoken('removeView');

        // send the request to WP
        $exportResponse = $this->sendTokenToWP($exportToken, $dataToWp);

        return $exportResponse;
    }

    /**
     * @param stdClass $view
     * @return array
     * @throws dml_exception
     */
    public function getViewBlocksForExport($view) {
        $viewBlocks = block_exaport_get_view_blocks($view);

        // select only needed options and generate some other data
        $blocksToExport = [];
        foreach ($viewBlocks as $block) {
            $blockId = $block->id;
            $blockData = [
                'id' => $blockId,
                'type' => $block->type,
                'positionx' => $block->positionx,
                'positiony' => $block->positiony,
                'title' => $block->block_title,
                'content' => $block->text,
            ];
            // fill additional data by block type - in JSON format
            $blockData['type_content'] = $this->getViewBlockTypeContent($block, $blockData);

            $blocksToExport[$blockId] = $blockData;
        }

        return $blocksToExport;
    }

    /**
     * fills the additional content - depends on block type
     * @param stdClass $block
     * @param array $blockData
     * @return array
     */
    public function getViewBlockTypeContent($block, &$blockData) {
        global $CFG, $USER;
        $fs = null;
        if ($fs === null) {
            $fs = get_file_storage();
        }

        $type_content = [];
        switch ($block->type) {
            case 'text':
                // nothing to add
                break;
            case 'item':
                $item = $block->item;
                $type_content = [
                    'name' => $item->name,
                    'type' => $item->type,
                    'content' => $item->intro,
                    'link' => $item->link,
                ];
                // add information about files:
                $type_content['files'] = $this->prepareItemFiles($item);
                break;
            case 'personal_information':
                if ($this->sendFilesAsUrls()) {
                    $userPicture = @$block->picture ?: '';
                } else {
                    if (@$block->picture) { // only if picture is enabled
                        if ($icon = $this->getUserIcon()) {
                            $userPicture = $this->addFileToPost($icon/*, 'u' . $USER->id . '_'*/);
                        }
                    }
                }

                $type_content = [
                    'firstname' => @$block->firstname ?: '',
                    'lastname' => @$block->lastname ?: '',
                    'email' => @$block->email ?: '',
                    'picture' => $userPicture,
                ];
                break;
            case 'headline':
                // if the block is 'headline' - make the title the same as a text:
                $blockData['title'] = $blockData['content'];
                // nothing to add to type_content
                break;
            case 'media':
                // nothing to add  - media is converting to item with type "note"
                break;
            case 'badge':
                if ($block->itemid) {
                    $badges = block_exaport_get_all_user_badges();
                    $badge = null;
                    foreach ($badges as $tmp) {
                        if ($tmp->id == $block->itemid) {
                            $badge = $tmp;
                            break;
                        };
                    };
                    if ($badge) {
                        // from largest to smaller:
                        $filenames = ['f3.png', 'f3.jpg', 'f2.png', 'f2.jpg', 'f1.png', 'f1.jpg'];
                        foreach ($filenames as $filename) {
                            if (!$badge->courseid) { // For badges with courseid = NULL.
                                // $imageUrl = (string)moodle_url::make_pluginfile_url(1, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
                                $badgeImage = $fs->get_file(1, 'badges', 'badgeimage', 0, '/', $filename);
                            } else {
                                $context = context_course::instance($badge->courseid);
                                // $imageUrl = (string)moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
                                $badgeImage = $fs->get_file($context->id, 'badges', 'badgeimage', $badge->id, '/', $filename);
                            }
                            if ($badgeImage) {
                                break;
                            }
                        }
                        $type_content = [
                            'name' => $badge->name,
                            // 'image' => $imageUrl,
                            'image' => $this->addFileToPost($badgeImage/*, 'b' . $badge->id . '_'*/),
                            'description' => format_text($badge->description, FORMAT_HTML),
                        ];
                    }
                }
                break;
            case 'cv_group':
                $blockData['content'] = ''; // $block->text contains technical values for the resume groups
                $type_content = $this->prepareCvBlock($block, true);
                break;
            case 'cv_information':
                $blockData['content'] = ''; // $block->text is empty???
                $type_content = $this->prepareCvBlock($block);
                break;
            default:
                // no type_content - empty. used only regular block data (text, title)
        }

        if ($type_content) {
            return $type_content;
            // return json_encode($type_content, JSON_UNESCAPED_UNICODE);
        }
        return [];
    }

    /**
     * @param stdClass $block
     * @return array
     */
    private function prepareCvBlock($block, $isGrouped = false) {
        // prepare my resume
        static $myResume = null;
        if ($myResume === null) {
            $myResume = block_exaport_get_resume_params(null, true, true);
        }
        if (!$myResume) {
            return [];
        }
        $cvGroupInfo = [
            'resume_itemtype' => $block->resume_itemtype,
            'resume_withfiles' => $block->resume_withfiles,
        ];

        switch ($block->resume_itemtype) {
            case 'cover':
                if ($myResume->cover) {
                    $cover = $myResume->cover;
                    $cover = file_rewrite_pluginfile_urls($cover, 'pluginfile.php',
                        context_user::instance($myResume->user_id)->id, 'block_exaport', 'resume_editor_cover', $myResume->id);
                    $cvGroupInfo['content'] = format_text($cover, FORMAT_HTML);
                }
                break;
            case 'edu':
            case 'employ':
            case 'certif':
            case 'public':
            case 'mbrship':
                // The same code, but different options for every type
                switch ($block->resume_itemtype) {
                    case 'edu':
                        $mainListName = 'educations';
                        $propList = ['institution', 'qualname', 'qualdescription', 'startdate', 'enddate'];
                        break;
                    case 'employ':
                        $mainListName = 'employments';
                        $propList = ['jobtitle', 'employer', 'positiondescription', 'startdate', 'enddate'];
                        break;
                    case 'certif':
                        $mainListName = 'certifications';
                        $propList = ['title', 'date', 'description'];
                        break;
                    case 'public':
                        $mainListName = 'publications';
                        $propList = ['title', 'contribution', 'date', 'contributiondetails', 'url'];
                        break;
                    case 'mbrship':
                        $mainListName = 'profmembershipments';
                        $propList = ['title', 'startdate', 'enddate', 'description'];
                        break;
                }
                $blockResumeItems = [];
                if ($myResume->{$mainListName}) {
                    if ($isGrouped && $block->text) {
                        $itemIds = explode(',', $block->text); // comma separated ids to array
                    } else if (!$isGrouped && $block->itemid) {
                        $itemIds = [$block->itemid]; // for single (non-grouped) blocks
                    }
                    foreach ($itemIds as $itemid) {
                        $blockResumeItem = [];
                        if ($myResume->{$mainListName}[$itemid]) {
                            $item_data = $myResume->{$mainListName}[$itemid];
                            foreach ($propList as $prop) {
                                $blockResumeItem[$prop] = @$item_data->{$prop} ?: '';
                            }
                            if ($block->resume_withfiles) {
                                $blockResumeItem['attachments'] = $this->addResumeAttachmentsToExportFlow($item_data->attachments/*, 'r' . $block->id . '_'*/);
                            } else {
                                $blockResumeItem['attachments'] = [];
                            }
                            $blockResumeItems[] = $blockResumeItem;
                        }
                    }
                }
                if ($isGrouped) {
                    $cvGroupInfo['groupItems'] = $blockResumeItems;
                } else if ($blockResumeItems) {
                    $cvGroupInfo['itemDetails'] = reset($blockResumeItems); // only first!
                }
                break;
            // types for grouped blocks
            case 'goals':
            case 'skills':
                // types for single (non-grouped) blocks
            case 'goalspersonal':
            case 'goalsacademic':
            case 'goalscareers':
            case 'skillspersonal':
            case 'skillsacademic':
            case 'skillscareers':
                $blockResumeItems = [];
                if ($myResume) {
                    if ($isGrouped && $block->text) {
                        $itemIds = explode(',', $block->text); // text keys for goals or skills...
                    } else if (!$isGrouped) {
                        $itemIds = [$block->resume_itemtype]; // for single (non-grouped) blocks
                    }
                    foreach ($itemIds as $goalSkillType) {
                        $blockResumeItem = [];
                        $description = '';
                        if ($tempContent = $myResume->{$goalSkillType}) {
                            $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                                context_user::instance($myResume->user_id)->id, 'block_exaport', 'resume_editor_' . $goalSkillType, $myResume->id);
                            $description .= format_text($tempContent, FORMAT_HTML);
                        }
                        $description = trim($description);
                        if ($description) {
                            $blockResumeItem['content'] = $description;
                        }
                        if ($block->resume_withfiles) {
                            $attachments = @$myResume->{$goalSkillType . '_attachments'} ?: [];
                        } else {
                            $attachments = [];
                        }
                        if ($attachments) {
                            $blockResumeItem['attachments'] = $this->addResumeAttachmentsToExportFlow($attachments/*, 'r' . $block->id . '_'*/);
                        }
                        if ($isGrouped) {
                            $blockResumeItem['resume_itemtype'] = $goalSkillType;
                        }
                        if ($blockResumeItem) {
                            $blockResumeItems[] = $blockResumeItem;
                        }
                    }
                }
                if ($isGrouped) {
                    $cvGroupInfo['groupItems'] = $blockResumeItems;
                } else if ($blockResumeItems) {
                    $cvGroupInfo['itemDetails'] = reset($blockResumeItems); // only first!
                }
                break;
            case 'interests':
                $description = '';
                if ($tempContent = $myResume->interests) {
                    $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                        context_user::instance($myResume->user_id)->id, 'block_exaport', 'resume_editor_interests', $myResume->id);
                    $description .= format_text($tempContent, FORMAT_HTML);
                }
                $cvGroupInfo['content'] = $description;
                break;
        }
        return $cvGroupInfo;
    }

    /**
     * returns info about attachments
     * @param array $attachments
     * @param string $filenamePrefix
     * @return array
     */
    private function addResumeAttachmentsToExportFlow($attachments, $filenamePrefix = '') {
        global $CFG;

        $addAttachments = [];
        if ($attachments && is_array($attachments) && count($attachments) > 0) {
            foreach ($attachments as $attachm) {
                if ($this->sendFilesAsUrls()) {
                    $addAttachments[] = [
                        'filename' => $attachm['filename'],
                        'fileurl' => $attachm['fileurl'],
                    ];
                } else {
                    /** @var stored_file $moodleFile */
                    if ($moodleFile = @$attachm['moodlefile']) {
                        /*$fileHash = $moodleFile->get_contenthash(); // stored hash
                        $filePath = $CFG->dataroot . '/filedir/' . substr($fileHash, 0, 2) . '/' . substr($fileHash, 2, 2) . '/' . $fileHash;
                        $this->filesToExport[$fileHash] = [
                            'path' => $filePath,
                            'mime' => $moodleFile->get_mimetype(),
                            'filename' => $moodleFile->get_filename(),
                        ];
                        $addAttachments[] = $fileHash;*/
                        $addAttachments[] = $this->addFileToPost($moodleFile, $filenamePrefix);
                    }
                }
            }
        }
        return $addAttachments;
    }

    /**
     * returns information about files for the block item
     */
    private function prepareItemFiles($item) {
        global $DB, $CFG, $USER;

        $userId = $USER->id; // only OWN !!! ???

        $itemFiles = [];

        // get files right from DB
        $select = "contextid='" . context_user::instance($userId)->id . "' " .
            " AND component='block_exaport' AND filearea='item_file' AND itemid='" . $item->id . "' AND filesize>0 ";

        if ($files = $DB->get_records_select('files', $select)) {
            if (is_array($files)) {

                foreach ($files as $file) {
                    // opt 1: files as URLs
                    if ($this->sendFilesAsUrls()) {
                        $isMedia = false;

                        if (strpos($file->mimetype, "image") !== false) {
                            // Link to file.
                            $fileUrl = $CFG->wwwroot . "/pluginfile.php/" . context_user::instance($userId)->id .
                                "/" . 'block_exaport' . "/" . 'item_file' . "/view/" . $access . "/itemid/" . $item->id . "/" .
                                $file->filename;
                        } else {
                            // Link to file.
                            $fileUrl = s("{$CFG->wwwroot}/blocks/exaport/portfoliofile.php?access=view/" . $access .
                                "&itemid=" . $item->id . "&inst=" . $file->pathnamehash);
                            if (block_exaport_is_valid_media_by_filename($file->filename)) {
                                $isMedia = true;
                            }
                        };

                        $itemFiles[] = [
                            'url' => $fileUrl,
                            'filename' => $file->filename,
                            'mimetype' => $file->mimetype,
                            'isMedia' => $isMedia, // needed?
                        ];
                    } else {

                        // opt 2: files for direct POST exporting
                        // relate the item to the file
                        $itemFiles[] = $this->addFileToPost($file/*, 'i' . $item->id . '_'*/);
                    }
                }
            }
        };

        return $itemFiles;
    }

    /**
     * send files only as links?
     * TODO: need to implement work with $access. so, this function is only as a plug
     * @return bool
     */
    public function sendFilesAsUrls() {
        return false;
    }

    /**
     * @param stored_file|stdClass $file
     * @param string $filename_prefix Sending files with the same 'postname' is a bad idea - better use $filename_prefix!
     * @return mixed
     */
    public function addFileToPost($file, $filename_prefix = '') {
        global $CFG;

        if ($file instanceof stored_file) {
            $fileHash = $file->get_contenthash(); // stored hash
            $mime = $file->get_mimetype();
            $filename = $file->get_filename();
        } else {
            // directly right from DB
            $fileHash = $file->contenthash;
            $mime = $file->mimetype;
            $filename = $file->filename;
        }
        $filePath = $CFG->dataroot . '/filedir/' . substr($fileHash, 0, 2) . '/' . substr($fileHash, 2, 2) . '/' . $fileHash;
        $this->filesToExport[$fileHash] = [
            'path' => $filePath,
            'mime' => $mime,
            'filename' => $filename_prefix . $filename,
        ];

        return $fileHash;
    }

    public function getUserIcon() {
        global $USER;
        $fs = get_file_storage();
        $contextid = \context_user::instance($USER->id)->id;
        // get icon from largest to smaller
        $filenames = ['f3.png', 'f3.jpg', 'f2.png', 'f2.jpg', 'f1.png', 'f1.jpg'];
        $icon = null;
        foreach ($filenames as $filename) {
            if ($icon = $fs->get_file($contextid, 'user', 'icon', 0, '/', $filename)) {
                break;
            }
        }

        return $icon;
    }

    /**
     * @param stdClass $resume
     * @return stdClass
     */
    private function prepareResume() {
        global $DB;

        // prepare my resume
        static $myResume = null;
        if ($myResume === null) {
            $myResume = block_exaport_get_resume_params(null, true, true);
        }
        if (!$myResume) {
            return null;
        }

        $resumeToExport = [];

        // cover
        $resumeToExport['cover'] = '';
        if ($myResume->cover) {
            $cover = $myResume->cover;
            $cover = file_rewrite_pluginfile_urls($cover, 'pluginfile.php',
                context_user::instance($myResume->user_id)->id, 'block_exaport', 'resume_editor_cover', $myResume->id);
            $resumeToExport['cover'] = format_text($cover, FORMAT_HTML);
        }
        // interests
        $resumeToExport['interests'] = '';
        if ($tempContent = $myResume->interests) {
            $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                context_user::instance($myResume->user_id)->id, 'block_exaport', 'resume_editor_interests', $myResume->id);
            $resumeToExport['interests'] = format_text($tempContent, FORMAT_HTML);
        }

        // Diff parts of resume: educations, employments, ...
        $resumeParts = [
            'educations',
            'employments',
            'certifications',
            'publications',
            'profmembershipments',
            'badges',
        ];

        foreach ($resumeParts as $partName) {
            switch ($partName) {
                case 'educations':
                    $propList = ['id', 'institution', 'institutionaddress', 'qualname', 'qualtype', 'qualdescription', 'startdate', 'enddate'];
                    break;
                case 'employments':
                    $propList = ['id', 'jobtitle', 'employer', 'employeraddress', 'positiondescription', 'startdate', 'enddate'];
                    break;
                case 'certifications':
                    $propList = ['id', 'title', 'date', 'description'];
                    break;
                case 'publications':
                    $propList = ['id', 'title', 'contribution', 'date', 'contributiondetails', 'url'];
                    break;
                case 'profmembershipments':
                    $propList = ['id', 'title', 'startdate', 'enddate', 'description'];
                    break;
                case 'badges':
                    $propList = ['id', 'name', 'description', 'date', 'image'];
                    break;
            }
            ${'tmp' . $partName} = [];
            if ($myResume->{$partName} ?? false) {
                foreach ($myResume->{$partName} as $itemid => $propertyData) {
                    $itemTempData = [];
                    $item_data = $myResume->{$partName}[$itemid];
                    foreach ($propList as $prop) {
                        $itemTempData[$prop] = @$item_data->{$prop} ?: '';
                    }
                    $itemTempData['attachments'] = $this->addResumeAttachmentsToExportFlow($item_data->attachments/*, 'r_'*/);
                    ${'tmp' . $partName}[] = $itemTempData;
                }
            }
            $resumeToExport[$partName] = ${'tmp' . $partName};

        }

        // Diff TEXT parts: skills, goals
        $resumeParts = [
            'goalscomp',
            'goalspersonal',
            'goalsacademic',
            'goalscareers',
            'skillscomp',
            'skillspersonal',
            'skillsacademic',
            'skillscareers',
        ];
        foreach ($resumeParts as $partName) {
            $description = '';
            $partData = [];
            // goalscomp and skillsomp is a list of selected descriptors from the exacomp:
            switch ($partName) {
                case 'goalscomp':
                    $rtype = 'goals';
                case 'skillscomp':
                    if ($partName == 'skillscomp') {
                        $rtype = 'skills';
                    }
                    $comptitles = '';
                    if (block_exaport_check_competence_interaction() && @$DB->get_manager()->table_exists(BLOCK_EXACOMP_DB_DESCRIPTORS)) {
                        $competences = $DB->get_records('block_exaportcompresume_mm', array("resumeid" => $myResume->id, "comptype" => $rtype));
                        foreach ($competences as $competence) {
                            $competencesdb = $DB->get_record(BLOCK_EXACOMP_DB_DESCRIPTORS, array('id' => $competence->compid), '*', IGNORE_MISSING);
                            if ($competencesdb != null) {
                                $comptitles .= $competencesdb->title . '<br>';
                            };
                        };
                    }
                    $partData['description'] = $comptitles;
                    break;
                default:
                    if ($myResume->{$partName}) {
                        $tempContent = $myResume->{$partName};
                        $tempContent = file_rewrite_pluginfile_urls($tempContent, 'pluginfile.php',
                            context_user::instance($myResume->user_id)->id, 'block_exaport', 'resume_editor_' . $partName, $myResume->id);
                        $description .= format_text($tempContent, FORMAT_HTML);
                    }
                    $description = trim($description);
                    $partData['description'] = $description;
                    $attachments = @$myResume->{$partName . '_attachments'} ?: [];
                    if ($attachments) {
                        $partData['attachments'] = $this->addResumeAttachmentsToExportFlow($attachments/*, 'r_'*/);
                    }
            }
            $resumeToExport[$partName] = $partData;

        }

        return $resumeToExport;
    }

    /**
     * requests the passphrase from WP server.
     * needs to have special secret code - get it after registration the exaport source in the WP special form http://wpServer/exabis-e-portfolio/moodle-register/
     * @return void
     */
    public function ssoPassphraseRequest() {
        global $CFG, $OUTPUT, $USER;

        if (!is_siteadmin()) {
            echo 'You has not admin rights';
            exit;
        }

        if (!\block_exaport\wordpress_lib::get_sso_url()) {
            echo 'No configured WP SSO url!';
            exit;
        }

        $timestamp = time();
        $data = get_config('block_exaport', 'mysource') . '|' . $timestamp;
        $secret = required_param('secret', PARAM_RAW);

        $signature = hash_hmac('sha256', $data, $secret);

        $payload = [
            'exaport_source' => get_config('block_exaport', 'mysource'),
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];

        $ch = curl_init(rtrim(\block_exaport\wordpress_lib::get_sso_url(), '/') . '/wp-json/axaport-sso/getpassphrase');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $return = [];

        $responseObj = json_decode($response);
        if ($responseObj->exaport_source == get_config('block_exaport', 'mysource')) {
            $getPassphrase = $responseObj->jwt_passphrase;

            set_config('wp_sso_passphrase', $getPassphrase, 'block_exaport');

            $return = [
                'result' => 'success',
                'html' => $this->exaportSettingsPasshpraseForm(),
            ];
        } else {
            // something wrong
            $return = [
                'result' => 'error',
                'message' => $responseObj->message,
                'code' => $responseObj->code,
            ];
        }

        return $return;

    }

    public function ssoPassphraseRemove() {

        if (!is_siteadmin()) {
            echo 'You has not admin rights';
            exit;
        }

        // reset passphrase
        set_config('wp_sso_passphrase', '', 'block_exaport');

        $return = [
            'result' => 'success',
            'html' => $this->exaportSettingsPasshpraseNotRegisteredForm(),
        ];

        return $return;
    }

    public function exaportSettingsPasshpraseForm() {
        global $OUTPUT, $CFG;

        // show the reset button only if the passphrase is configured not in the main config.php
        $showResetButton = !isset($CFG->forced_plugin_settings['block_exaport']['wp_sso_passphrase']);

        // send "WP SSO fully configured form
        $data = [
            'removePasssphraseUrl' => '#',
            'testPassphraseUrl' => '#',
            'showResetButton' => $showResetButton,
        ];
        $html = $OUTPUT->render_from_template('block_exaport/settings_wp_sso_passphrase', $data);

        return $html;
    }

    public function exaportSettingsPasshpraseNotRegisteredForm() {
        global $OUTPUT;

        $urlToPasshphraseRegister = rtrim(\block_exaport\wordpress_lib::get_sso_url(), '/') . '/exabis-e-portfolio/moodle-register/'
            . '?' . http_build_query([
                'exaport_source' => get_config('block_exaport', 'mysource'),
                'name' => get_site()->fullname,
            ], arg_separator: '&');
        $data = (object)[
            'getSecretUrl' => $urlToPasshphraseRegister,
        ];

        $element = $OUTPUT->render_from_template('block_exaport/settings_wp_sso_passphrase_not_registered', $data);
        return $element;
    }

    public function testSSO() {
        global $CFG;

        // generate the token
        $exportToken = $this->generateJWTtoken('testPassphrase');
        // send the request to WP
        $exportResponse = $this->sendTokenToWP($exportToken);

        if (@$exportResponse['response']['success'] == 1) {
            $return = [
                'result' => 'alert',
                'message' => $exportResponse['response']['message'],
            ];
        } else {
            $return = [
                'result' => 'error',
                'message' => @$exportResponse['response']['message'] ?: 'Connection not successful ',
            ];
        }

        return $return;
    }


}
