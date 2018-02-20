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

require_once(__DIR__.'/inc.php');
$url = optional_param('url', 0, PARAM_URL);
$url = str_replace("http://", "", $url); // Sicherheit, nur interne links.

if ((!isloggedin() || isguestuser()) && $_GET["url"] != "") {
    $formsub = true;
} else {
    $formsub = false;
    if ($url != "") {
        redirect($CFG->wwwroot.$url);
    }
}
?>
<html>
<head>

</head>
<body>
<?php

if ($formsub) {
    $SESSION->wantsurl = $CFG->wwwroot.$url;

    ?>
    <div style="visibility:hidden">
        <form id="login" name="form" action="<?php echo get_login_url() ?>" method="post">
            <ul>
                <!--<li><input id="login_username" class="loginform" type="text"  value="visitor" name="username"></li>
                <li><input id="login_password" class="loginform" type="password"  value="Visitor123!" name="password"></li>-->
                <li><input id="login_username" class="loginform" type="text" value="visitor" name="username"></li>
                <li><input id="login_password" class="loginform" type="password" value="Visitor123!" name="password"></li>
                <li>
                    <input type="submit" value="Login">
                </li>
            </ul>
        </form>
    </div>
    <script type="text/javascript">
        //<![CDATA[
        document.form.submit();
        //]]>
    </script>
    <?php
}
?>
</body>

</html>
