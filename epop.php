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
global $DB, $USER, $COURSE, $CFG;

$action = optional_param('action', 0, PARAM_ALPHANUMEXT);

if ($action == "login") {

    $uname = optional_param('username', 0, PARAM_USERNAME);
    $pword = optional_param('password', 0, PARAM_TEXT);

    if ($uname != "0" && $pword != "0") {

        $uname = kuerzen($uname, 100);
        $pword = kuerzen($pword, 50);
        $uhash = 0;
        $conditions = array("username" => $uname, "password" => $pword);
        if (!$user = $DB->get_record("user", $conditions)) {

            $condition = array("username" => $uname);
            if ($user = $DB->get_record("user", $condition)) {
                $validiert = authenticate_user_login($uname, $pword);
            } else {
                $validiert = false;
            }
        } else {
            $validiert = true; // Alte version bei der die passw�rter verschl�sselt geschickt werden.
        }

        if ($validiert == true) {

            if ($user->auth == 'nologin' || $user->confirmed == 0 || $user->suspended != 0 || $user->deleted != 0) {
                $uhash = 0;
            } else {
                if (!$userhash = $DB->get_record("block_exaportuser", array("user_id" => $user->id))) {
                    if ($uhash = block_exaport_create_exaportuser($user->id)) {
                        block_exaport_installoez($user->id);
                    }
                } else {
                    if (empty($userhash->user_hash_long)) {
                        $uhash = block_exaport_update_userhash($userhash->id);
                    } else {
                        $uhash = $userhash->user_hash_long;
                    }
                    if ($userhash->oezinstall == 0) {
                        block_exaport_installoez($user->id);
                    } else {
                        if (block_exaport_checkifupdate($user->id)) {
                            block_exaport_installoez($user->id, true);
                        }
                    }
                }
            }
        } else {
            $uhash = 0;
        }
        if (empty($uhash)) {
            $uhash = 0;
        }
        echo "key=".$uhash;
    } else {
        echo "key=0";
    }
} else if ($action == "child_categories") {
    $catid = optional_param('catid', 0, PARAM_INT);
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $conditions = array("userid" => $user->id, "pid" => $catid);
        if (write_xml_categories($conditions, $catid, $user->id) == false) {
            $conditions = array("userid" => $user->id, "categoryid" => $catid);
            $competencecategory = "";
            if (block_exaport_check_competence_interaction()) {
                $sql = "SELECT st.id,st.title FROM {block_exaportcate} cat ".
                        " INNER JOIN {".BLOCK_EXACOMP_DB_SUBJECTS."} s ON s.id=cat.subjid ".
                        " INNER JOIN {".BLOCK_EXACOMP_DB_SCHOOLTYPES."} st ON st.id=s.stid ";
                $sql .= "WHERE cat.id=?";
                if ($schoolt = $DB->get_record_sql($sql, array($catid))) {
                    if ($schoolt->title == "Soziale Kompetenzen") {
                        $competencecategory = "sozial";
                    } else if ($schoolt->title == "Personale Kompetenzen") {
                        $competencecategory = "personal";
                    } else if ($schoolt->title == "Digitale Kompetenzen") {
                        $competencecategory = "digital";
                    }
                }
            }
            write_xml_items($conditions, 0, $competencecategory);
        }
    }
} else if ($action == "get_lastitemID") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $sql = "SELECT id FROM {block_exaportitem} WHERE userid=? ORDER BY timemodified DESC LIMIT 0,1";
        if ($rs = $DB->get_record_sql($sql, array($user->id))) {
            echo $rs->id;
        } else {
            echo "0";
        }
    }
} else if ($action == "get_courseid") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $sql = "SELECT c.id FROM {course} c INNER JOIN {enrol} e on e.courseid=c.id ".
                " INNER JOIN {user_enrolments} ue ON ue.enrolid=e.id";
        $sql .= " WHERE c.visible=1 AND ue.userid=?";
        $sql .= " ORDER BY ue.timemodified DESC LIMIT 0,1";
        if ($rs = $DB->get_record_sql($sql, array($user->id))) {
            echo $rs->id;
        } else {
            echo "0";
        }
    }
} else if ($action == "newCat") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $parentcat = optional_param('parent_cat', 0, PARAM_INT);
        $catname = optional_param('name', ' ', PARAM_TEXT);
        if ($newid = $DB->insert_record('block_exaportcate',
                array("pid" => $parentcat, "userid" => $user->id, "name" => $catname, "subjid" => 0, "topicid" => 0, "stid" => 0,
                        "isoez" => 0, "source" => 0, "sourceid" => 0, "sourcemod" => 0, "timemodified" => time()))
        ) {
            echo $newid;
        } else {
            echo "-1";
        }
    }
} else if ($action == "save_selected_user") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $selecteduser = optional_param('selected_user', 0, PARAM_ALPHANUMEXT);
        $shareall = optional_param('shareall', 0, PARAM_INT);
        $viewid2 = optional_param('view_id', ' ', PARAM_INT);
        $user = explode("_", $selecteduser);
        $DB->delete_records("block_exaportviewshar", array("viewid" => $viewid2));

        if ($shareall == 1) {
            $DB->update_record('block_exaportview', array("id" => $viewid2, "timemodified" => time(), "shareall" => 1));
        } else {
            $DB->update_record('block_exaportview', array("id" => $viewid2, "timemodified" => time(), "shareall" => 0));
            foreach ($user as $k => $v) {
                if (is_numeric($v)) {
                    $DB->insert_record('block_exaportviewshar', array("viewid" => $viewid2, "userid" => $v));
                }
            }
        }
    }
} else if ($action == "save_selected_items") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $selecteditems = optional_param('selected_items', 0, PARAM_ALPHANUMEXT);
        $text = optional_param('text', '', PARAM_ALPHANUMEXT);
        $viewid2 = optional_param('view_id', ' ', PARAM_INT);
        $items = explode("_", $selecteditems);
        $DB->delete_records("block_exaportviewblock", array("viewid" => $viewid2));
        $i = 1;
        foreach ($items as $k => $v) {
            if (is_numeric($v)) {
                $DB->insert_record('block_exaportviewblock',
                        array("viewid" => $viewid2, "type" => "item", "itemid" => $v, "text" => $text, "positionx" => 1,
                                "positiony" => $i));
                $i++;
            }
        }
    }
} else if ($action == "save_selected_competences") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $selectedcompetences = optional_param('selected_competences', 0, PARAM_ALPHANUMEXT);
        $competences = explode("_", $selectedcompetences);
        $itemid3 = optional_param('item_id', ' ', PARAM_INT);
        $subjectid2 = optional_param('subject_id', '0', PARAM_INT);

        // Die kompetenzen werden nach subject gruppiert angezeigt, daher nur diese gruppe l�schen.
        $sql = "SELECT descr.id FROM {".BLOCK_EXACOMP_DB_SUBJECTS."} subj ".
                "INNER JOIN {".BLOCK_EXACOMP_DB_TOPICS."} top ON top.subjid=subj.id ".
                "INNER JOIN {".BLOCK_EXACOMP_DB_DESCTOPICS."} tmm ON tmm.topicid=top.id ".
                "INNER JOIN {".BLOCK_EXACOMP_DB_DESCRIPTORS."} descr ON descr.id=tmm.descrid";
        if ($subjectid2 > 0) {
            $sql .= " WHERE subj.id=".$subjectid2;
        }
        $descriptors = $DB->get_records_sql($sql);
        $dlist = "0"; // Init.
        foreach ($descriptors as $descriptor) {
            $dlist .= ",".$descriptor->id;
        }
        $select = 'activityid='.$itemid3.' AND eportfolioitem=1';
        if ($dlist != "0") {
            $select .= ' AND compid IN ('.$dlist.')';
        }

        $DB->delete_records_select(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, $select);
        foreach ($competences as $k => $v) {
            if (is_numeric($v)) {
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                        array("activityid" => $itemid3, "eportfolioitem" => "1", "compid" => $v, "activitytitle" => "",
                                "coursetitle" => ""));
            }
        }
    }
} else if ($action == "save_view_title") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {

        $title = optional_param('title', ' ', PARAM_TEXT);
        $description = optional_param('description', '', PARAM_TEXT);
        $viewid2 = optional_param('view_id', ' ', PARAM_INT);
        if ($view = $DB->get_record("block_exaportview", array("id" => $viewid2))) {
            $DB->update_record('block_exaportview',
                    array("id" => $viewid2, "name" => $title, "description" => $description, "timemodified" => time()));
        } else {
            do {
                $hash = substr(md5(microtime()), 3, 8);
            } while ($DB->record_exists("block_exaportview", array("hash" => $hash)));

            if ($newid = $DB->insert_record('block_exaportview',
                    array("name" => $title, "userid" => $user->id, "description" => $description, "timemodified" => time(),
                            "shareall" => 0, "externaccess" => 0, "externcomment" => 0, "langid" => 0, "hash" => $hash,
                            "layout" => "2"))
            ) {
                echo $newid;
            } else {
                echo "-1";
            }
        }

    }
} else if ($action == "all_users") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $tusers = array();
        $tusers = exaport_get_shareable_users();
        block_exaport_write_xml_user($tusers);
    }
} else if ($action == "delete_item") {
    $user = checkhash();
    $url = "";
    if (!$user) {
        echo "invalid hash";
    } else {
        $itemid = optional_param('itemid', ' ', PARAM_INT);
        $result = $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $itemid));
        $result = $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM, array("activityid" => $itemid));
        $sql = "SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
        WHERE i.id=?";
        if ($resu = $DB->get_records_sql($sql, array($itemid))) {
            foreach ($resu as $rs) {
                delete_file($rs->pathnamehash);
            }
        }
        $result = $DB->delete_records('block_exaportviewblock', array("itemid" => $itemid));
        $result = $DB->delete_records('block_exaportitemshar', array("itemid" => $itemid));
        $result = $DB->delete_records('block_exaportitemcomm', array("itemid" => $itemid));
        $result = $DB->delete_records('block_exaportitem', array("id" => $itemid));

    }
} else if ($action == "delete_view") {
    $user = checkhash();
    $url = "";
    if (!$user) {
        echo "invalid hash";
    } else {
        $viewid = optional_param('viewid', ' ', PARAM_INT);
        $result = $DB->delete_records('block_exaportviewblock', array("viewid" => $viewid));
        $result = $DB->delete_records('block_exaportview', array("id" => $viewid));
    }
} else if ($action == "get_users_for_view") {
    $user = checkhash();
    $viewid2 = optional_param('view_id', 0, PARAM_INT);
    if (!$user) {
        echo "invalid hash";
    } else {
        header("Content-Type:text/xml");
        $view = $DB->get_record("block_exaportview", array("id" => $viewid2));
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= block_exaport_getshares($view, $user->id, false, "selected", true);
        echo $inhalt;
    }
} else if ($action == "get_Extern_Link") {
    $user = checkhash();
    $url = "";
    if (!$user) {
        echo "invalid hash";
    } else {
        $viewid2 = optional_param('view_id', ' ', PARAM_INT);
        if ($view = $DB->get_record("block_exaportview", array("id" => $viewid2))) {
            $DB->update_record('block_exaportview', array("id" => $viewid2, "timemodified" => time(), "externaccess" => 1));
            $url = block_exaport_get_external_view_url($view, $user->id);
        }
    }
    echo "externLink=".$url;
} else if ($action == "all_items") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $conditions = array("userid" => $user->id);
        write_xml_items($conditions);
    }
} else if ($action == "get_items_for_view") {
    $user = checkhash();
    $viewid2 = optional_param('view_id', 0, PARAM_INT);
    if (!$user) {
        echo "invalid hash";
    } else {
        $conditions = array("userid" => $user->id);
        write_xml_items($conditions, $viewid2);
    }
} else if ($action == "oezepsinstalltonull") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $sql = "UPDATE {block_exaportuser} SET oezinstall=0 WHERE user_id=".$user->id;
        $DB->execute($sql);
    }
} else if ($action == "deleteFile_OezepsExample") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $itemid = optional_param('id', 0, PARAM_INT);
        block_exaport_delete_oezepsitemfile($itemid);
        block_exaport_delete_competences($itemid, $user->id);
    }
} else if ($action == "delete_all_oezeps") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $sql = "SELECT * FROM {block_exaportitem} WHERE isoez=1 AND userid=?";
        $items = $DB->get_records_sql($sql, array($user->id));
        foreach ($items as $item) {
            block_exaport_delete_oezepsitemfile($item->id);
            block_exaport_delete_competences($item->id, $user->id);
        }
        $DB->delete_records('block_exaportitem', array("isoez" => 1, "userid" => $user->id));
        echo "delete userid".$user->id;
        $DB->delete_records('block_exaportcate', array("isoez" => 1, "userid" => $user->id));
        $sql = "UPDATE {block_exaportuser} SET oezinstall=0 WHERE user_id=".$user->id;
        $DB->execute($sql);
    }
} else if ($action == "getViews") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $sql = "SELECT vi.id as viid, i.id as itemid,v.id,v.name,v.description,v.externaccess, ".
                " v.shareall,v.hash,i.name as itemname, i.categoryid as catid,i.type, i.url,i.intro ";
        $sql .= " FROM {block_exaportview} v LEFT JOIN {block_exaportviewblock} vi ON v.id=vi.viewid ".
                " INNER JOIN {block_exaportitem} i ON i.id=vi.itemid WHERE v.userid=?";

        $sql = "SELECT * FROM {block_exaportview} WHERE userid=?";

        $views = $DB->get_records_sql($sql, array($user->id));
        header("Content-Type:text/xml");
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= '<views>'."\r\n";
        foreach ($views as $view) {
            $inhalt .= "<view  id='".$view->id."'>";
            $inhalt .= '<name>'.cdatawrap($view->name).'</name>'."\r\n";
            $inhalt .= '<description>'.cdatawrap($view->description).'</description>'."\r\n";
            $sql = "SELECT vi.id as viid, i.id as itemid,i.name as itemname,i.categoryid as catid,i.type, i.url,i.intro FROM ";
            $sql .= " {block_exaportviewblock} vi INNER JOIN {block_exaportitem} i ON i.id=vi.itemid WHERE vi.viewid=?";
            $items = $DB->get_records_sql($sql, array($view->id));
            foreach ($items as $item) {
                $inhalt .= "<item  id='".$item->itemid."' catid='".$item->catid."' url='".$item->url."' type='".
                        $item->type."'>";
                $inhalt .= '<name>'.cdatawrap($item->itemname).'</name>'."\r\n";
                $inhalt .= cdatawrap($item->intro);
                $inhalt .= "</item>"."\r\n";
            }

            $inhalt .= block_exaport_getshares($view, $user->id);
            $inhalt .= "</view>"."\r\n";
        }
        $inhalt .= "</views>"."\r\n";
        echo $inhalt;

    }

} else if ($action == "getTopics") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $sql = "SELECT t.id,t.title ";
        $sql .= " FROM {".BLOCK_EXACOMP_DB_DESCRIPTORS."} d, {".BLOCK_EXACOMP_DB_MDLTYPES."} mt, {".BLOCK_EXACOMP_DB_TOPICS."} t, ";
        $sql .= " {".BLOCK_EXACOMP_DB_SUBJECTS."} s, {".BLOCK_EXACOMP_DB_SCHOOLTYPES."} ty, {".BLOCK_EXACOMP_DB_DESCTOPICS."} dt ";
        $sql .= " WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id ";
        $sql .= " AND dt.descrid=d.id AND (ty.isoez=1)";
        $sql .= " GROUP BY t.id,t.title";
        $topics = $DB->get_records_sql($sql);
        header("Content-Type:text/xml");
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= '<result>'."\r\n";
        foreach ($topics as $topic) {
            $inhalt .= "<topic id='".$topic->id."'>"."\r\n";
            $inhalt .= "<name>".cdatawrap($topic->title)."</name>"."\r\n";
            $inhalt .= "</topic>"."\r\n";
        }
        $inhalt .= '</result> '."\r\n";
        echo $inhalt;
    }
} else if ($action == "getSubjects") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        $itemid = optional_param('itemid', 0, PARAM_INT);
        $sql = "SELECT s.id,s.title FROM {".BLOCK_EXACOMP_DB_DESCRIPTORS."} d, {".BLOCK_EXACOMP_DB_MDLTYPES."} mt, ";
        $sql .= " {".BLOCK_EXACOMP_DB_TOPICS."} t, {".BLOCK_EXACOMP_DB_COURSETOPICS."} ctt, {".BLOCK_EXACOMP_DB_SUBJECTS."} s, ";
        $sql .= " {".BLOCK_EXACOMP_DB_SCHOOLTYPES."} ty, {".BLOCK_EXACOMP_DB_DESCTOPICS."} dt ";
        $sql .= " WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id ";
        $sql .= "   AND ctt.topicid=t.id AND dt.descrid=d.id AND (ty.isoez=1)";
        $sql .= " GROUP BY s.id,s.title";
        $subjects = $DB->get_records_sql($sql);
        header("Content-Type:text/xml");
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= '<result>'."\r\n";
        foreach ($subjects as $subject) {
            $inhalt .= "<subject  id='".$subject->id."'";
            if (block_exaport_competence_selected($subject->id, $user->id, $itemid)) {
                $inhalt .= " competence_selected='true'";
            } else {
                $inhalt .= " competence_selected='false'";
            }
            $inhalt .= ">"."\r\n";
            $inhalt .= "<name>".cdatawrap($subject->title)."</name>"."\r\n";
            $inhalt .= "</subject>"."\r\n";
        }
        $inhalt .= '</result> '."\r\n";
        echo $inhalt;
    }
} else if ($action == "getCompetences" || $action == "getExamples") {
    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        if ($action == "getExamples") {
            if (block_exaport_checkifupdate($user->id)) {
                block_exaport_installoez($user->id, true);
            }
        }
        if (block_exaport_check_competence_interaction()) {
            $itemid = optional_param('itemid', 0, PARAM_INT);
            $subjectid = optional_param('subjectid', 0, PARAM_INT);
            $clist = ",";
            if ($itemid > 0) {
                $compok = $DB->get_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $itemid));
                foreach ($compok as $k => $v) {
                    $clist .= $v->compid.",";
                }
            }
            // Commented code: $sql = "SELECT CONCAT(dt.id,'_',ctt.id) as uniqueid,dt.id as dtid,d.id, d.title,
            // - t.title as topic, s.title as subject
            // - FROM {block_exacompdescriptors} d, {block_exacompmdltype_mm} mt, {block_exacomptopics} t,
            // - {block_exacompcoutopi_mm} ctt, {block_exacompsubjects} s, {block_exacompschooltypes} ty,
            // - {block_exacompdescrtopic_mm} dt
            // - WHERE mt.stid = ty.id // AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND ctt.topicid=t.id
            // - AND dt.descrid=d.id AND (ty.isoez=1)";
            // Neu am 20.5.2014 weil descriptoren mehrfach vorkommen.
            $sql = "SELECT CONCAT(dt.id,'_',ctt.id) as uniqueid,dt.id as dtid,d.id, d.title, t.title as topic, s.title as subject ";
            $sql .= " FROM {".BLOCK_EXACOMP_DB_DESCRIPTORS."} d, {".BLOCK_EXACOMP_DB_MDLTYPES."} mt, {".BLOCK_EXACOMP_DB_TOPICS."} t, ";
            $sql .= " {".BLOCK_EXACOMP_DB_COURSETOPICS."} ctt, {".BLOCK_EXACOMP_DB_SUBJECTS."} s, {".BLOCK_EXACOMP_DB_SCHOOLTYPES."} ty, ";
            $sql .= " {".BLOCK_EXACOMP_DB_DESCTOPICS."} dt ";
            $sql .= " WHERE mt.stid = ty.id AND s.stid = ty.id AND t.subjid = s.id AND dt.topicid=t.id AND ctt.topicid=t.id ";
            $sql .= " AND dt.descrid=d.id AND (ty.isoez=1)";
            if ($subjectid > 0 && $action == "getCompetences") {
                $sql .= " AND s.id=".$subjectid;
            }
            $sql .= " GROUP BY d.id, d.title ORDER BY d.sorting";

            $descriptors = $DB->get_records_sql($sql);
            header("Content-Type:text/xml");
            $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
            $inhalt .= '<result>'."\r\n";
            foreach ($descriptors as $descriptor) {
                if ($action == "getExamples") {
                    $bsp = get_examples($descriptor->id);
                } else {
                    $bsp = "<id>".$descriptor->id."</id>"."\r\n";
                }
                if ($bsp != "") {
                    $inhalt .= "<competences id='".$descriptor->id."' ";

                    if (strpos($clist, ",".$descriptor->id.",") === false) {
                        $inhalt .= " selected='false'";
                    } else {
                        $inhalt .= " selected='true'";
                    }
                    $inhalt .= ">";
                    $inhalt .= $bsp;
                    $inhalt .= "<name>".cdatawrap($descriptor->title)."</name>"."\r\n";
                    $inhalt .= "</competences>"."\r\n";
                }
            }
            $inhalt .= '</result> '."\r\n";
            echo $inhalt;

        } else {
            echo "no interaction";
        }
    }

} else if ($action == "parent_categories") {

    $user = checkhash();
    if (!$user) {
        echo "invalid hash";
    } else {
        header("Content-Type:text/xml");
        $catid = optional_param('catid', 0, PARAM_INT);

        if ($category = $DB->get_record("block_exaportcate", array("id" => $catid))) {
            $conditions = array("pid" => $category->pid, "userid" => $user->id);
        } else {
            $conditions = array("pid" => "-1");
        }
        write_xml_categories($conditions, $catid, $user->id);
    }
} else if ($action == "upload" || $action == "updatePic") {
    $user = checkhash();

    if (!$user) {
        echo "invalid hash";
    } else {
        $filepath = "/";
        $title = addslashes(optional_param('title', 'mytitle', PARAM_TEXT));
        $description = addslashes(optional_param('description', '', PARAM_TEXT));
        $itemid = optional_param('itemid', 0, PARAM_INT);
        if ($itemid > 0) {
            $itemrs = $DB->get_record("block_exaportitem", array("id" => $itemid));
            if (!empty($itemrs)) {
                if ($action == "updatePic") {
                    $sql = "SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
                            WHERE i.id=?";
                    $res = $DB->get_records_sql($sql, array($itemid));
                    foreach ($res as $rs) {

                        if (!empty($rs)) {
                            if (delete_file($rs->pathnamehash)) {
                                $DB->update_record('block_exaportitem', array("id" => $itemid, "attachment" => ""));
                            }
                        }
                    }

                }
            } else {
                if ($action == "updatePic") {
                    // No update possible, nothing to do.
                    echo "0";
                    die;
                }
                $itemrs = new stdClass();
                $itemrs->isoez = 0;
            }
        } else {
            $itemrs = new stdClass();
            $itemrs->isoez = 0;
            $itemrs->type = "note";
        }
        $new = new stdClass();
        if ($itemid > 0) {
            $new->id = $itemid;
        }
        if ($action == "updatePic") {
            // Only update picture.
            $new->timemodified = time();
        } else {
            if ($itemrs->isoez != 1) { // Wenn neues item, �zeps items k�nnen eh nicht neu sein.
                $new->userid = $user->id;
                $new->name = $title;
                $new->courseid = $COURSE->id;
                $new->categoryid = optional_param('catid', 0, PARAM_INT);
            }
            $new->url = optional_param('url', "", PARAM_URL);
            if (!empty($new->url)) {
                if (!preg_match('/^(http|https|ftp):\/\//i', $new->url)) {
                    $new->url = "http://".$new->url;
                }
            }
            $new->intro = $description;
            $new->timemodified = time();
            /* Was ist der richtige typ?
                wenn datei dabei ist, immer datei, das wird unten immer gemacht, wenn if(block_exaport_checkfiles()
                wenn neu: url+text->note, url->link, text->note
                wenn update: wenn vorher datei, bleibts datei, sonst wie bei neu.
            */
            if ($itemid != 0 && $itemrs->type == "file") {
                // Update und type file, type bleibt immer file, weil file kann nicht updated werden,
                // es muss hier also datei dabei sein.
                $new->type = 'file';
            } else {
                if (!empty($new->url) && empty($description)) {
                    $new->type = 'link';
                } else {
                    $new->type = 'note';
                } // Weil wenn datei dabei ist, wird type nachher sowieso type.
            }

        }
        if (block_exaport_checkfiles()) {

            $fs = get_file_storage();
            $totalsize = 0;
            $context = context_user::instance($user->id);
            foreach ($_FILES as $fieldname => $uploadedfile) {
                // Check upload errors.
                if (!empty($_FILES[$fieldname]['error'])) {
                    switch ($_FILES[$fieldname]['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            throw new moodle_exception('upload_error_ini_size', 'repository_upload');
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            throw new moodle_exception('upload_error_form_size', 'repository_upload');
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            throw new moodle_exception('upload_error_partial', 'repository_upload');
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            throw new moodle_exception('upload_error_no_file', 'repository_upload');
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            throw new moodle_exception('upload_error_no_tmp_dir', 'repository_upload');
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            throw new moodle_exception('upload_error_cant_write', 'repository_upload');
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            throw new moodle_exception('upload_error_extension', 'repository_upload');
                            break;
                        default:
                            throw new moodle_exception('nofile');
                    }
                }
                $file = new stdClass();
                $file->filename = clean_param($_FILES[$fieldname]['name'], PARAM_FILE);

                // Check system maxbytes setting.
                if (($_FILES[$fieldname]['size'] > get_max_upload_file_size($CFG->maxbytes))) {
                    // Oversize file will be ignored, error added to array to notify.
                    // Web service client.
                    $file->errortype = 'fileoversized';
                    $file->error = get_string('maxbytes', 'error');
                } else {
                    $file->filepath = $_FILES[$fieldname]['tmp_name'];
                    // Calculate total size of upload.
                    $totalsize += $_FILES[$fieldname]['size'];
                }
                $files[] = $file;
            }

            $usedspace = 0;
            $privatefiles = $fs->get_area_files($context->id, 'block_exaport', 'item_file', false, 'id', false);
            foreach ($privatefiles as $file) {
                $usedspace += $file->get_filesize();
            }
            if ($totalsize > ($CFG->userquota - $usedspace)) {
                throw new file_exception('userquotalimit');
            }
            $results = array();

            foreach ($files as $file) {
                if (!empty($file->error)) {
                    // Including error and filename.
                    $results[] = $file;
                    continue;
                }
                $filerecord = new stdClass;
                $filerecord->component = 'block_exaport';
                $filerecord->contextid = $context->id;
                $filerecord->userid = $user->id;
                $filerecord->filearea = 'item_file';
                $filerecord->filename = $file->filename;
                $filerecord->filepath = $filepath;
                $filerecord->itemid = 0;
                $filerecord->license = $CFG->sitedefaultlicense;
                $filerecord->author = $user->lastname." ".$user->firstname;
                $filerecord->source = '';

                $filerecord->filename = get_unique_filename($fs, $filerecord, $filerecord->filename);
                $new->type = 'file';

                if ($itemid > 0) {
                    if ($action != "updatePic") {
                        $DB->update_record('block_exaportitem', $new);
                    } else {
                        $new2 = new stdClass;
                        $new2->id = $new->id;
                        $new2->type = "file"; // Wenn note soll file werden.
                        $DB->update_record('block_exaportitem', $new2);
                    }
                    if ($itemrs->isoez != 1) {
                        $temporaryvar = 1;
                        // Nicht mehr beim upload dabei.
                    } else {
                        block_exaport_delete_competences($itemid, $user->id);
                        $competencesoez = block_exaport_get_oezcompetencies($itemrs->exampid);
                        block_exaport_save_competences($competencesoez, $new, $user->id, $itemrs->name);
                    }

                } else {
                    if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                        echo $new->id;
                    }
                }

                if ($tempfile = $fs->create_file_from_pathname($filerecord, $file->filepath)) {

                    if (strcmp(mimeinfo('type', $file->filename), "image/jpeg") == 0) {
                        $imageinfo = $tempfile->get_imageinfo();

                        $filerecordimg = new stdClass;
                        $filerecordimg->component = 'block_exaport';
                        $filerecordimg->contextid = $context->id;
                        $filerecordimg->userid = $user->id;
                        $filerecordimg->filearea = 'item_file';
                        $filerecordimg->filename = $file->filename;
                        $filerecordimg->filepath = $filepath;
                        $filerecordimg->itemid = $new->id;
                        $filerecordimg->license = $CFG->sitedefaultlicense;
                        $filerecordimg->author = $user->lastname." ".$user->firstname;
                        $filerecordimg->source = '';

                        $iw = intval($imageinfo['width']);
                        $ih = intval($imageinfo['height']);
                        $fakt = 1;
                        if ($iw > 2000) {
                            $fakt = (2000 / $iw);
                            $iw = ceil($iw * $fakt);
                            $ih = ceil($ih * $fakt);
                        }
                        if ($ih > 2000) {
                            $fakt = (2000 / $ih);
                            $iw = ceil($iw * $fakt);
                            $ih = ceil($ih * $fakt);
                        }
                        if ($fakt <> 1) {
                            $newfile = $fs->convert_image($filerecordimg, $tempfile->get_id(), $iw, $ih);
                        } else {
                            $filerecord->itemid = $new->id;
                            $newfile = $fs->create_file_from_pathname($filerecord, $file->filepath);
                        }
                        if ($tempfile) {
                            $tempfile->delete();
                        }
                    } else {
                        $filerecord->itemid = $new->id;
                        $newfile = $fs->create_file_from_pathname($filerecord, $file->filepath);
                        if ($tempfile) {
                            $tempfile->delete();
                        }
                    }

                    $attachm = $newfile->get_id();
                    echo "ID=".$attachm;
                    $new2 = $DB->update_record('block_exaportitem', array("id" => $new->id, "attachment" => $attachm));
                }
                $results[] = $filerecord;
            }
        } else {
            if ($action != "updatePic") {
                if ($itemid > 0) {
                    $new->id = $itemid;
                    $DB->update_record('block_exaportitem', $new);
                    if ($itemrs->isoez != 1) {
                        $temporaryvar = 1;
                    } else {
                        block_exaport_delete_competences($itemid, $user->id);
                        // Wenn text oder link, dann beispiel gel�st, wenn type file ist datei dabei, auch gel�st.
                        if (!empty($new->intro) || !empty($new->url) || $new->type == "file") {
                            $competencesoez = block_exaport_get_oezcompetencies($itemrs->exampid);
                            block_exaport_save_competences($competencesoez, $new, $user->id, $itemrs->name);
                        }
                        // Kein fileupload, keine kompetenzen erworben.
                    }
                    echo $new->id;
                } else {
                    if ($new->id = $DB->insert_record('block_exaportitem', $new)) {
                        echo $new->id;
                    }
                }
            }
        }
    }
}
function block_exaport_get_oezcompetencies($exampid) {
    global $DB;
    $comp = array();
    $descr = $DB->get_records(BLOCK_EXACOMP_DB_DESCEXAMP, array("exampid" => $exampid));
    foreach ($descr as $rs) {
        $comp[] = $rs->descrid;
    }
    return $comp;
}

function get_examples($descrid) {
    global $DB;
    $inhalt = '';
    $sql = "SELECT examp.* FROM {".BLOCK_EXACOMP_DB_EXAMPLES."} examp INNER JOIN {".BLOCK_EXACOMP_DB_DESCEXAMP."} mm ON examp.id=mm.exampid ";
    $sql .= " WHERE examp.externalurl<>'' AND mm.descrid=?";

    $examples = $DB->get_records_sql($sql, array($descrid));
    foreach ($examples as $example) {
        $inhalt .= '<example id="'.$example->id.'"';
        if (isoezeps($example->externalurl)) {
            $inhalt .= ' oezeps="1"';
        } else {
            $inhalt .= ' oezeps="0"';
        }
        $inhalt .= '>'."\r\n";
        $inhalt .= "<name>".cdatawrap($example->title)."</name>"."\r\n";

        $inhalt .= "<description>".cdatawrap($example->description)."</description>"."\r\n";
        $inhalt .= "<task>".cdatawrap($example->task)."</task>"."\r\n";
        $inhalt .= "<solution>".cdatawrap($example->solution)."</solution>"."\r\n";
        $inhalt .= "<attachement>".cdatawrap($example->attachement)."</attachement>"."\r\n";
        $inhalt .= "<completefile>".cdatawrap($example->completefile)."</completefile>"."\r\n";
        $inhalt .= "<externalurl>".cdatawrap(create_autologin_moodle_example_link($example->externalurl))."</externalurl>"."\r\n";
        $inhalt .= "<externalsolution>".cdatawrap($example->externalsolution)."</externalsolution>"."\r\n";
        $inhalt .= "<externaltask>".cdatawrap($example->externaltask)."</externaltask>"."\r\n";

        $inhalt .= "</example>"."\r\n";
    }
    return $inhalt;
}

function isoezeps($url) {
    if (strpos($url, "oezeps.at") === false) {
        return false;
    } else {
        return true;
    }
}

function delete_file($hash) {
    $fs = get_file_storage();
    $file = $fs->get_file_by_hash($hash);
    if ($file) {
        $file->delete();
        return true;
    } else {
        return false;
    }

}

function oezepsbereinigung($url, $nuroezeps = 1) {
    $url = str_replace("http://www.oezeps.at/moodle", "", $url);
    $url = str_replace("http://oezeps.at/moodle", "", $url);
    $url = str_replace("http://www.oezeps.at", "", $url);
    $url = str_replace("http://oezeps.at", "", $url);
    if ($nuroezeps == 0) {
        $url = str_replace("http://www.digikomp.at", "http://www.digikomp.at/blocks/exaport/epopal.php?url=", $url);
    }
    return $url;
}

function create_autologin_moodle_example_link($url) {
    $url = str_replace("oezeps.at/moodle", "oezeps.at/moodle/blocks/exaport/epopal.php?url=", $url);
    $url = str_replace("digikomp.at", "digikomp.at/blocks/exaport/epopal.php?url=", $url);
    $url = str_replace("www2.edumoodle.at/epop", "www2.lernplattform.schule.at/epop/blocks/exaport/epopal.php?url=", $url);
    $url = str_replace("www2.lernplattform.schule.at/epop", "www2.lernplattform.schule.at/epop/blocks/exaport/epopal.php?url=",
            $url);

    return $url;
}

function block_exaport_delete_competences($itemid, $userid) {
    global $DB;
    $result = $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY, array("activityid" => $itemid));
    $result = $DB->delete_records(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM, array("activityid" => $itemid));
}

function block_exaport_save_competences($competences, $new, $userid, $aname) {
    global $DB;
    if (count($competences) > 0) {
        foreach ($competences as $k => $v) {
            if (is_numeric($v)) {
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY,
                        array("compid" => $v, "activityid" => $new->id, "eportfolioitem" => 1, "activitytitle" => $aname));
                $DB->insert_record(BLOCK_EXACOMP_DB_COMPETENCE_USER_MM,
                        array("compid" => $v, "activityid" => $new->id, "userid" => $userid, "reviewerid" => $userid,
                                "eportfolioitem" => 1, "role" => 0));
            }
        }
    }
}

function exaport_get_shareable_users() {
    $tusers = array();
    $courses = exaport_get_shareable_courses_with_users('sharing');
    foreach ($courses as $course) {
        foreach ($course->users as $user) {
            $tusers[$user->id] = $user->name;
        }
    }
    return $tusers;
}

function block_exaport_getshares($view, $usrid, $sharetag = true, $strshared = "viewShared", $viewusers = false) {
    global $DB;
    $inhalt = "";
    if ($sharetag) {
        $inhalt = "<shares>"."\r\n";
    }
    if ($view->externaccess == 1 && $viewusers == false) {
        $url = block_exaport_get_external_view_url($view, $usrid);
        $inhalt .= "	<extern>".$url."</extern>"."\r\n";
    }
    if ($viewusers == false) {
        $inhalt .= "	<intern>"."\r\n";
    }
    $inhalt .= "		<users>"."\r\n";
    $tusers = array();

    $tusers = exaport_get_shareable_users();
    if ($view->shareall == 1) {
        foreach ($tusers as $k => $v) {
            $inhalt .= '<user name="'.$v.'" id="'.$k.'" '.$strshared.'="true" >'."\r\n";
            $inhalt .= '<name>'.cdatawrap($v).'</name>'."\r\n";
            $inhalt .= '</user>'."\r\n";
        }
    } else {
        $tusers2 = array();
        $sql = "SELECT u.id,u.firstname,u.lastname FROM ";
        $sql .= " {block_exaportviewshar} s INNER JOIN {user} u ON s.userid=u.id WHERE s.viewid=?";

        $users = $DB->get_records_sql($sql, array($view->id));
        foreach ($users as $user) {
            $tusers2[$user->id] = $user->lastname." ".$user->firstname;
        }

        foreach ($tusers as $k => $v) {
            $inhalt .= '<user name="'.$v.'" id="'.$k.'" ';
            if (!empty($tusers2[$k])) {
                $inhalt .= $strshared.'="true"';
            } else {
                $inhalt .= $strshared.'="false"';
            }
            $inhalt .= '>'."\r\n";
            $inhalt .= '<name>'.cdatawrap($v).'</name>'."\r\n";
            $inhalt .= '</user>'."\r\n";
        }
    }

    $inhalt .= '		</users>'."\r\n";
    if ($viewusers == false) {
        $inhalt .= '	</intern>'."\r\n";
    }
    if ($sharetag) {
        $inhalt .= "	</shares>"."\r\n";
    }
    return $inhalt;
}

function block_exaport_write_xml_user($tusers) {
    header("Content-Type:text/xml");
    if ($tusers) {
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= '<users>'."\r\n";
        foreach ($tusers as $k => $v) {
            $inhalt .= '<user id="'.$k.'">'."\r\n";
            $inhalt .= '<name>'.cdatawrap($v).'</name>'."\r\n";
            $inhalt .= '</user>'."\r\n";
        }
        $inhalt .= '</users> '."\r\n";
        echo $inhalt;
    }
}

function get_unique_filename($fs, $filerecord, $fn) {
    $existingfile = $fs->file_exists($filerecord->contextid, $filerecord->component, $filerecord->filearea,
            $filerecord->itemid, $filerecord->filepath, $fn);
    if ($existingfile) {
        $laenge = strlen($fn);
        $ext = strrchr($fn, ".");
        $rest = substr($fn, 0, ($laenge - strlen($ext)));

        if (strpos($rest, "_") === false) {
            $fnnew = $rest."_1".$ext;
        } else {
            $num = substr(strrchr($rest, "_"), 1);
            if (is_numeric($num)) {
                $numn = intval($num);
            } else {
                $numn = "0";
            }
            $numn++;
            $fnnew = substr($rest, 0, (strlen($rest) - strlen($num) - 1))."_".$numn.$ext;
        }
        $fn = get_unique_filename($fs, $filerecord, $fnnew);
    }
    return $fn;

}

function get_number_subcats($id) {
    global $DB;
    $conditions = array("pid" => $id);
    if ($items = $DB->get_records("block_exaportcate", $conditions, " isoez DESC")) {
        return count($items);
    } else {
        return 0;
    }
}

function write_xml_items($conditions, $viewid = 0, $competencecategory = "") {
    global $DB, $CFG;
    header("Content-Type:text/xml");
    if ($viewid > 0) {
        $vitemar = array();
        $vitems = $DB->get_records("block_exaportviewblock", array("viewid" => $viewid));
        foreach ($vitems as $k => $v) {
            $vitemar[$v->itemid] = 1;
        }
    }

    if ($items = $DB->get_records("block_exaportitem", $conditions, " isoez DESC,name")) {
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= '<result>'."\r\n";
        foreach ($items as $item) {
            /* Itemauswahl f�r view: nur gel�ste aufgaben/items anzeigen. */
            if ($viewid > 0) {
                if ($item->attachment != "" || $item->intro != "" || $item->url != "") {
                    $showitem = true;
                } else {
                    $showitem = false;
                }
            } else {
                $showitem = true;
            }
            /* Itemauswahl f�r view ende. */

            if ($showitem == true) {
                if (empty($item->parentid) || $item->parentid == 0 ||
                        block_exaport_parent_is_solved($item->parentid, $item->userid)
                ) {
                    $inhalt .= '<item id="'.$item->id.'"';
                    if ($viewid > 0) {
                        if (!empty($vitemar[$item->id])) {
                            $inhalt .= ' selected="true"';
                        } else {
                            $inhalt .= ' selected="false"';
                        }
                    }
                    if ($item->attachment != "") {
                        $progress = 1;
                        $userhash = optional_param('key', 0, PARAM_ALPHANUM);
                        $fileurl = $CFG->wwwroot.'/blocks/exaport/portfoliofile.php?access=portfolio/id/'.$item->userid.
                                '&itemid='.$item->id.'&att='.$item->id.'&hv='.$userhash;
                    } else {
                        $fileurl = "";
                        if (!empty($item->intro) || !empty($item->url)) {
                            $progress = 1;
                        } else {
                            $progress = 0;
                        }
                    }
                    $inhalt .= ' competence_category="'.$competencecategory.'"';
                    $inhalt .= ' catid="'.$item->categoryid.'" type="'.$item->type.'" progress="'.$progress.
                            '" isOezepsItem="'.block_exaport_numtobool($item->isoez).'">'."\r\n";
                    $inhalt .= '<name>'.cdatawrap($item->name).'</name>'."\r\n";
                    $inhalt .= '<description>'.cdatawrap($item->intro).'</description>'."\r\n";
                    $inhalt .= '<url>'.cdatawrap($item->url).'</url>'."\r\n";
                    $ispicture = "false";
                    if (!empty($fileurl)) {
                        if ($dateien = $DB->get_records("files", array("component" => "block_exaport", "itemid" => $item->id))) {
                            foreach ($dateien as $datei) {
                                if ($datei->filesize > 0) {
                                    if (preg_match('/.+\/(jpeg|jpg|gif|png)$/', $datei->mimetype)) {
                                        $ispicture = "true";
                                    }
                                }
                            }
                        }
                    }
                    $inhalt .= '<fileUrl isPicture="'.$ispicture.'">'.cdatawrap(block_exaport_ers_null($fileurl)).
                            '</fileUrl>'."\r\n";
                    $inhalt .= '<beispiel_url>';
                    if ($item->isoez == 1) {
                        $inhalt .= cdatawrap(create_autologin_moodle_example_link(block_exaport_ers_null($item->beispiel_url)));
                    } else {
                        $inhalt .= cdatawrap(block_exaport_ers_null($item->beispiel_url));
                    }
                    $inhalt .= '</beispiel_url>'."\r\n";
                    $inhalt .= '<beispiel_description>'.cdatawrap($item->beispiel_angabe).'</beispiel_description>'."\r\n";
                    $texteingabe = 0;
                    $bildbearbeiten = 0;
                    if ($item->iseditable == 1 && !empty($item->example_url)) {
                        $bildbearbeiten = 1;
                    } else if ($item->iseditable == 1) {
                        $texteingabe = 1;
                    }

                    $inhalt .= '<texteingabe>'.block_exaport_numtobool($texteingabe).'</texteingabe>'."\r\n";
                    $inhalt .= '<bildbearbeiten>'.block_exaport_numtobool($bildbearbeiten).'</bildbearbeiten>'."\r\n";
                    $inhalt .= '<originalbild>'.cdatawrap($item->example_url).'</originalbild>'."\r\n";
                    $inhalt .= '</item>'."\r\n";
                }
            }
        }
        $inhalt .= '</result> '."\r\n";
        echo $inhalt;
    }
}

function block_exaport_ers_null($wert) {
    if ($wert == "") {
        return " ";
    } else {
        return $wert;
    }
}

function write_xml_categories($conditions, $catid, $userid) {
    global $DB;

    header("Content-Type:text/xml");
    $catkomparr = array();
    if ($categories = $DB->get_records("block_exaportcate", $conditions, " isoez DESC")) {
        if ($sozkomp = $DB->get_records(BLOCK_EXACOMP_DB_SCHOOLTYPES, array("title" => "Soziale Kompetenzen"))) {
            foreach ($sozkomp as $ks) {
                if ($sozsubjs = $DB->get_records(BLOCK_EXACOMP_DB_SUBJECTS, array("stid" => $ks->id))) {
                    foreach ($sozsubjs as $k => $v) {
                        $catkomparr[$v->id] = "sozial";
                    }
                }
            }
        }
        if ($sozkomp = $DB->get_records(BLOCK_EXACOMP_DB_SCHOOLTYPES, array("title" => "Personale Kompetenzen"))) {
            foreach ($sozkomp as $ks) {
                if ($sozsubjs = $DB->get_records(BLOCK_EXACOMP_DB_SUBJECTS, array("stid" => $ks->id))) {
                    foreach ($sozsubjs as $k => $v) {
                        $catkomparr[$v->id] = "personal";
                    }
                }
            }
        }
        if ($sozkomp = $DB->get_records(BLOCK_EXACOMP_DB_SCHOOLTYPES, array("title" => "Digitale Kompetenzen"))) {
            foreach ($sozkomp as $ks) {
                if ($sozsubjs = $DB->get_records(BLOCK_EXACOMP_DB_SUBJECTS, array("stid" => $ks->id))) {
                    foreach ($sozsubjs as $k => $v) {
                        $catkomparr[$v->id] = "digital";
                    }
                }
            }
        }
        $inhalt = '<?xml version="1.0" encoding="UTF-8" ?>'."\r\n";
        $inhalt .= '<result>'."\r\n";
        foreach ($categories as $categorie) {

            $numsubcats = get_number_subcats($categorie->id);
            $prog = block_exaport_get_progress($categorie->id, $catid, $userid);
            $inhalt .= '<categorie catid="'.$categorie->id.'" numsubcats="'.$numsubcats.'" progress="'.$prog->progress.
                    '" numItems="'.$prog->anzahl.'" isOezepsItem="'.block_exaport_numtobool($categorie->isoez).'"';
            if (!empty($catkomparr[$categorie->subjid])) {
                $inhalt .= ' competence_category="'.$catkomparr[$categorie->subjid].'"';
            } else {
                $inhalt .= ' competence_category=""';
            }
            $inhalt .= '>'."\r\n";
            $inhalt .= '<name>'.cdatawrap($categorie->name).'</name>'."\r\n";
            $inhalt .= '<description>'.cdatawrap(htmlwrap($categorie->description, $categorie->name)).'</description>'."\r\n";
            $inhalt .= '</categorie>'."\r\n";
        }
        $inhalt .= '</result> '."\r\n";
        echo $inhalt;
        return true;
    } else {
        return false;
    }
}

function block_exaport_numtobool($wert) {
    if ($wert == "1") {
        return "true";
    } else {
        return "false";
    }
}

function kuerzen($wert, $laenge) {
    if (strlen($wert) > $laenge) {
        $wert = substr($wert, 0, $laenge); // Gibt "abcd" zur�ck.
    }
    return $wert;
}

function block_exaport_checkfiles() {
    if (empty($_FILES)) {
        return false;
    } else {
        $ret = true;

        foreach ($_FILES as $datei) {

            if ($datei["error"] > 0) {
                $ret = false;
            }
        }
        return $ret;
    }
}

function checkhash() {
    global $DB;
    global $USER;
    $userhash = optional_param('key', 0, PARAM_ALPHANUM);
    if (empty($userhash) or $userhash == "0") {
        return false;
    } else {
        $sql = "SELECT u.* FROM {user} u INNER JOIN {block_exaportuser} eu ON eu.user_id=u.id WHERE eu.user_hash_long=?";
        if (!$user = $DB->get_record_sql($sql, array($userhash))) {
            return false;
        } else {
            $USER = $user;
            return $user;
        }
    }
}

function block_exaport_create_exaportuser($userid) {
    global $DB;
    $uhash = block_exaport_unique_hash();
    $newid = $DB->insert_record('block_exaportuser',
            array("user_id" => $userid, "persinfo_timemodified" => time(), "user_hash_long" => $uhash, "description" => ""));
    return $uhash;
}

function block_exaport_update_userhash($id) {
    global $DB;
    $uhash = block_exaport_unique_hash();
    $DB->update_record('block_exaportuser', array("id" => $id, "persinfo_timemodified" => time(), "user_hash_long" => $uhash));
    return $uhash;
}

function block_exaport_unique_hash() {
    global $DB;
    $id = substr(md5(uniqid(rand(), true)), 0, 29);
    return $id;
}

function block_exaport_get_progress($catid, $catidparent, $userid) {
    global $DB;
    $result = new stdClass();
    $result->progress = 0;
    $result->anzahl = 0;
    $catlist = block_exaport_get_subcategories($catid, $catidparent, $userid);
    $catlist = preg_replace("/^,/", "", $catlist);

    if ($catidparent == 0) {// Kontinent, eine ebene tiefer graben.
        $catarr = explode(",", $catlist);
        $catlistt = "";
        foreach ($catarr as $catl) {
            $catlistt .= block_exaport_get_subcategories($catl, "", $userid);
        }
        $catlist = preg_replace("/^,/", "", $catlistt);
    }
    $sql = "SELECT count(id) as alle,sum(IF(attachment<>'' or intro<>'' or url<>'',1,0)) as mitfile ";
    $sql .= " FROM {block_exaportitem} WHERE isoez=1 AND categoryid IN (".$catlist.")";

    if ($rs = $DB->get_record_sql($sql)) {
        if ($rs->alle > 0) {
            $result->progress = ($rs->mitfile / $rs->alle);
            $result->anzahl = $rs->alle;
        }
    }
    return $result;
}

function block_exaport_get_subcategories($catid, $catlist, $userid) {
    global $DB;
    $catlist .= ",".$catid;
    $sql = "SELECT id FROM {block_exaportcate} WHERE pid=? AND userid=?";

    $cats = $DB->get_records_sql($sql, array($catid, $userid));
    foreach ($cats as $cat) {
        $catlist .= ",".$cat->id;
    }

    return $catlist;
}

function block_exaport_delete_oezepsitemfile($itemid) {
    global $DB;
    $sql = "SELECT f.* FROM {block_exaportitem} i INNER JOIN {files} f ON i.id=f.itemid
                    WHERE i.attachment<>0 AND i.id=?";

    $res = $DB->get_records_sql($sql, array($itemid));
    foreach ($res as $rs) {
        if (!empty($rs)) {
            if (delete_file($rs->pathnamehash)) {
                $DB->update_record('block_exaportitem', array("id" => $itemid, "attachment" => ""));
            }
        }
    }

    if ($item = $DB->get_record("block_exaportitem", array("id" => $itemid))) {

        if (!empty($item->intro)) {
            $DB->update_record('block_exaportitem', array("id" => $itemid, "type" => "note"));
        } else if (!empty($item->url)) {
            $DB->update_record('block_exaportitem', array("id" => $itemid, "type" => "link"));
        }
    }
}

function cdatawrap($wert) {
    if (!empty($wert) && $wert != " ") {
        $wert = '<![CDATA['.$wert.']]>';
    }
    return $wert;
}

function htmlwrap($wert, $title = "E-Pop") {
    if (!empty($wert) && $wert != " ") {
        $wert = '<!doctype html><html><head><meta charset="utf-8"><title>'.$title.'</title></head><body><div>'.$wert.
                '</div></body></html>';
    }
    return $wert;
}

function sauber($wert) {
    $wert = strip_tags($wert, "<br><b><p><i><h1><h2>");
    $wert = str_replace("'", "", $wert);
    $wert = addslashes($wert);
    return $wert;
}

function block_exaport_competence_selected($subjid, $userid, $itemid) {
    global $DB;
    $sql = "SELECT CONCAT(dmm.id,'_',descr.id,'_',item.id) as uniqueid, dmm.id,descr.id FROM {".BLOCK_EXACOMP_DB_SUBJECTS."} subj";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_TOPICS."} top ON top.subjid=subj.id ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_DESCTOPICS."} tmm ON tmm.topicid=top.id ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_DESCRIPTORS."} descr ON descr.id=tmm.descrid ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_COMPETENCE_ACTIVITY."} dmm ON dmm.compid=descr.id ";
    $sql .= " INNER JOIN {block_exaportitem} item ON item.id=dmm.activityid AND eportfolioitem=1 AND dmm.comptype = 0 ";
    $sql .= " WHERE dmm.comptype = 0 AND subj.id=".$subjid." AND item.userid=".$userid;
    if ($itemid > 0) {
        $sql .= " AND dmm.activityid=".$itemid;
    }
    if ($res = $DB->get_records_sql($sql)) {
        return true;
    } else {
        return false;
    }
}

function block_exaport_checkifupdate($userid) {
    global $DB;
    $sql = "SELECT * FROM {".BLOCK_EXACOMP_DB_SETTINGS."} WHERE courseid=0 AND activities='importxml'";
    if ($modsetting = $DB->get_record_sql($sql)) {
        if ($usersetting = $DB->get_record("block_exaportuser", array("user_id" => $userid))) {
            if (!empty($usersetting->import_oez_tstamp)) {
                if ($usersetting->import_oez_tstamp >= $modsetting->tstamp) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return true;
            }
        } else {
            return true;
        }
    } else {
        return true;
    }
}

function block_exaport_installoez($userid, $isupdate = false) {
    global $DB;

    $remids = array();
    $where = "";
    $catold = array();

    if (!$kont = $DB->get_records("block_exaportcate", array("userid" => $userid, "isoez" => 2))) {
        $newkontid = $DB->insert_record('block_exaportcate',
                array("name" => "Eigener Kontinent", "userid" => $userid, "pid" => 0, "timemodified" => time(), "courseid" => 0,
                        "description" => "Eigener Kontinent", "isoez" => 2, "stid" => 0, "subjid" => 0, "topicid" => 0,
                        "source" => 0, "sourceid" => 0, "sourcemod" => 0));
        $sql = "UPDATE {block_exaportcate} SET pid=".$newkontid." WHERE pid=0 AND isoez=0 AND userid=".$userid;
        $DB->execute($sql);
    }

    if ($isupdate == true) {
        // Exacomp: timestamp hinterlegen, wann update
        // nur wenn neue daten, dann update
        // zuerst export_cate in array schreiben mit stid#subjid#topid, um abfragen zu sparen
        // dann neue daten durchlaufen, wenn neu dann insert, wenn vorhanden dann title und parentid pr�fen und bei bedarf update,
        // f�r l�schen merker machen.
        if ($cats = $DB->get_records("block_exaportcate", array("userid" => $userid))) {

            foreach ($cats as $cat) {
                $catold[$cat->source."#".$cat->sourceid."#".$cat->sourcemod] = array(
                        "name" => $cat->name,
                        "pid" => $cat->pid,
                        "id" => $cat->id);
            }
        }
    }
    $sql = "SELECT DISTINCT concat(top.id,'_',examp.id) as id,st.title as kat0, st.id as stid,st.source as stsource, ";
    $sql .= " st.sourceid as stsourceid, subj.title as kat1,st.description as stdescription, subj.titleshort as kat1s, ";
    $sql .= " subj.id as subjid,subj.source as subsource,subj.sourceid as subsourceid,subj.description as subjdescription, ";
    $sql .= " top.title as kat2,top.titleshort as kat2s,top.id as topid,top.description as topdescription,top.source as topsource,";
    $sql .= " top.sourceid as topsourceid, examp.title as item,examp.titleshort as items,examp.description as exampdescription, ";
    $sql .= " examp.externalurl,examp.externaltask,examp.task,examp.source as sourceexamp,examp.id as exampid,examp.completefile, ";
    $sql .= " examp.iseditable,examp.source,examp.sourceid,examp.parentid,examp.solution";
    $sql .= " FROM {".BLOCK_EXACOMP_DB_SCHOOLTYPES."} st INNER JOIN {".BLOCK_EXACOMP_DB_SUBJECTS."} subj ON subj.stid=st.id ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_TOPICS."} top ON top.subjid=subj.id ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_DESCTOPICS."} tmm ON tmm.topicid=top.id ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_DESCRIPTORS."} descr ON descr.id=tmm.descrid ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_DESCEXAMP."} emm ON emm.descrid=descr.id ";
    $sql .= " INNER JOIN {".BLOCK_EXACOMP_DB_EXAMPLES."} examp ON examp.id=emm.exampid ";
    $sql .= " WHERE st.isoez=1 OR st.epop=1 OR subj.epop=1 OR top.epop=1 OR descr.epop=1 OR examp.epop=1 ";
    $sql .= " OR (st.isoez=2 AND examp.source=2) OR (examp.source=3)".$where." ";
    $sql .= " ORDER BY st.id,subj.id,top.id";
    $row = $DB->get_records_sql($sql);
    $stid = -1;
    $subjid = -1;
    $topid = -1;
    $beispielurl = "";
    $catlist = '0';
    $itemlist = '0';
    foreach ($row as $rs) {
        $parentidisold = false;
        if ($stid != $rs->stid) {
            $keyst = $rs->stsource."#".$rs->stsourceid."#3";
            $jetzn = time();
            if (array_key_exists($keyst, $catold)) {
                $newstid = $catold[$keyst]["id"];
                $DB->update_record('block_exaportcate',
                        array("id" => $newstid, "name" => $rs->kat0, "stid" => $rs->stid, "timemodified" => $jetzn,
                                "description" => $rs->stdescription));
            } else {
                $datas = array("pid" => 0, "stid" => $rs->stid, "sourcemod" => "3", "userid" => $userid, "name" => $rs->kat0,
                        "timemodified" => $jetzn, "course" => "0", "isoez" => "1", "subjid" => 0, "topicid" => 0,
                        "source" => $rs->stsource, "sourceid" => $rs->stsourceid, "description" => $rs->stdescription);
                $newstid = $DB->insert_record('block_exaportcate', $datas);
            }
            $stid = $rs->stid;
            $catlist .= ','.$newstid;
        }
        if ($subjid != $rs->subjid) {
            $keysub = $rs->subsource."#".$rs->subsourceid."#5";
            if (!empty($rs->kat1s)) {
                $kat1s = $rs->kat1s;
            } else {
                $kat1s = $rs->kat1;
            }
            if (array_key_exists($keysub, $catold)) {
                $newsubjid = $catold[$keysub]["id"];
                $DB->update_record('block_exaportcate',
                        array("id" => $newsubjid, "name" => $kat1s, "pid" => $newstid, "timemodified" => time(), "stid" => $stid,
                                "subjid" => $rs->subjid, "description" => $rs->subjdescription));
            } else {
                $newsubjid = $DB->insert_record('block_exaportcate',
                        array("pid" => $newstid, "userid" => $userid, "name" => $kat1s, "timemodified" => time(), "course" => 0,
                                "isoez" => "1", "stid" => $stid, "subjid" => $rs->subjid, "topicid" => 0,
                                "source" => $rs->subsource, "sourceid" => $rs->subsourceid, "sourcemod" => 5,
                                "description" => $rs->subjdescription));
            }
            $subjid = $rs->subjid;
            $catlist .= ','.$newsubjid;
        }
        if ($topid != $rs->topid) {
            $keytop = $rs->topsource."#".$rs->topsourceid."#7";

            if (!empty($rs->kat2s)) {
                $kat2s = $rs->kat2s;
            } else {
                $kat2s = $rs->kat2;
            }
            if (array_key_exists($keytop, $catold)) {
                $newtopid = $catold[$keytop]["id"];
                $DB->update_record('block_exaportcate',
                        array("id" => $newtopid, "name" => $kat2s, "pid" => $newsubjid, "timemodified" => time(),
                                "description" => $rs->topdescription, "stid" => $stid, "subjid" => $rs->subjid,
                                "topicid" => $rs->topid));
            } else {
                $newtopid = $DB->insert_record('block_exaportcate',
                        array("pid" => $newsubjid, "userid" => $userid, "name" => $kat2s, "timemodified" => time(), "course" => 0,
                                "isoez" => "1", "description" => $rs->topdescription, "stid" => $stid, "subjid" => $rs->subjid,
                                "topicid" => $rs->topid, "source" => $rs->topsource, "sourceid" => $rs->topsourceid,
                                "sourcemod" => 7));
            }
            $topid = $rs->topid;
            $catlist .= ','.$newtopid;
        }
        $beispielurl = "";
        if ($rs->externaltask != "") {
            $beispielurl = $rs->externaltask;
        }
        if ($rs->externalurl != "") {
            $beispielurl = $rs->externalurl;
        }
        if ($rs->task != "") {
            $beispielurl = $rs->task;
        }

        if (!empty($rs->items)) {
            $items = $rs->items;
        } else {
            $items = $rs->item;
        }
        $iteminsert = true;

        $pid = intval($rs->parentid);
        if ($pid > 0) {
            if (!empty($remids[0][$pid])) {
                $pid = $remids[0][$pid];
            } else {
                $parentidisold = true;
            }
        }

        if ($isupdate == true) {
            if ($rs->source == 3) {
                // If example created from teacher in moodle, there is no sourceid.
                // because sourceid is from komet xml tool exacomp_data.xml.
                $sourceidtemp = $rs->exampid;
                $exampleurl = $rs->completefile;
            } else {
                $sourceidtemp = $rs->sourceid;
                $exampleurl = $rs->completefile;
            }
            if ($itemrs = $DB->get_records("block_exaportitem",
                    array("isoez" => 1, "source" => $rs->source, "sourceid" => $sourceidtemp, "userid" => $userid,
                            "categoryid" => $newtopid))
            ) {
                // KategoryId mitnehmen, weil ein item kopiert und auf verschiedene kategorien zugeordnet werden kann.
                // beim update soll dann nur das jeweilige item aktualisiert werden, sonst ist categorie falsch.
                $iteminsert = false;
                foreach ($itemrs as $item) {
                    $itemlist .= ','.$item->id;
                    $remids[0][$rs->exampid] = $item->id; // Remark relation for parentids later.
                    $data = array("id" => $item->id, "userid" => $userid, "categoryid" => $newtopid, "name" => $items,
                            "beispiel_angabe" => $rs->exampdescription, "timemodified" => time(), "courseid" => 0, "isoez" => "1",
                            "beispiel_url" => $beispielurl, "exampid" => $rs->exampid, "iseditable" => $rs->iseditable,
                            "source" => $rs->source, "sourceid" => $rs->sourceid, "example_url" => $exampleurl,
                            "parentid" => $pid);
                    $DB->update_record('block_exaportitem', $data);
                    if ($parentidisold) {
                        $remids[1][$item->id] = intval($rs->parentid);
                    } // Save old parentid from new id.
                }
            }
        }
        if ($iteminsert == true) {
            if (!empty($items)) {
                if ($rs->source == 3) {
                    // If example created from teacher in moodle, there is no sourceid.
                    // because sourceid is from komet xml tool exacomp_data.xml.
                    $sourceidtemp = $rs->exampid;
                    $exampleurl = $rs->completefile;
                } else {
                    $sourceidtemp = $rs->sourceid;
                    $exampleurl = $rs->completefile;
                }
                $newid = $DB->insert_record('block_exaportitem',
                        array("userid" => $userid, "type" => "note", "categoryid" => $newtopid, "name" => $items, "url" => "",
                                "intro" => "", "beispiel_angabe" => $rs->exampdescription, "attachment" => "",
                                "timemodified" => time(), "courseid" => 0, "isoez" => "1", "beispiel_url" => $beispielurl,
                                "exampid" => $rs->exampid, "iseditable" => $rs->iseditable, "source" => $rs->source,
                                "sourceid" => $sourceidtemp, "example_url" => $exampleurl, "parentid" => $pid));
                $itemlist .= ','.$newid;
                $remids[0][$rs->exampid] = $newid; // Remark relation for parentids later.
                if ($parentidisold) {
                    $remids[1][$newid] = intval($rs->parentid);
                }
            }
        }
    } // End foreach $row.

    $sql = 'DELETE FROM {block_exaportitem} WHERE id NOT IN ('.$itemlist.') AND userid='.$userid.
            ' AND isoez=1 AND intro="" AND url="" AND attachment=""';
    $DB->execute($sql);

    $sql = 'SELECT * FROM {block_exaportcate} WHERE id NOT IN ('.$catlist.') AND userid='.$userid.' AND isoez=1';
    $rows = $DB->get_records_sql($sql);
    foreach ($rows as $row) {
        if (!$DB->get_record("block_exaportitem", array("categoryid" => $row->id))) {
            $DB->delete_records("block_exaportcate", array("id" => $row->id));
        }
    }

    $sql = "UPDATE {block_exaportuser} SET oezinstall=1,import_oez_tstamp=".time()." WHERE user_id=".$userid;
    $DB->execute($sql);
    block_exaport_update_unset_pids('block_exaportitem', $remids);
}

function block_exaport_update_unset_pids($utable, $remids) {
    global $DB;

    if (!empty($remids[1])) {
        foreach ($remids[1] as $newid => $v) {
            $DB->update_record($utable, array("id" => $newid, "parentid" => $remids[0][$v]));
        }
    }
}

function block_exaport_parent_is_solved($id, $userid) {
    global $DB;
    $sql = "SELECT i.id FROM {block_exaportitem} i WHERE id=? AND userid=? AND (attachment<>'' || url<>'' || intro<>'')";
    if ($DB->get_record_sql($sql, array($id, $userid))) {
        return true;
    } else {
        return false;
    }
}
