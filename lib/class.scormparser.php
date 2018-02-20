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

require_once($CFG->dirroot.'/mod/scorm/datamodels/scormlib.php');

/**
 * SCORMParser: Parsing a SCORM File
 */
class SCORMParser {
    public $error = false;
    public $errormsg = '';
    public $warning = false;
    public $warningmsg = '';
    public $dir = '';
    public $deletefiles = false;

    /**
     * set_error(): set the error message
     *
     * @param msg The error-message
     */
    public function set_error($msg) {
        $this->error = true;
        $this->errormsg .= "Error: ".$msg."\n";
    }

    /**
     * set_warning(): set the warning message
     * even with a warning message the file can be parsed
     *
     * @param msg The warning-message
     */
    public function set_warning($msg) {
        $this->warning = true;
        $this->warningmsg .= "Warning: ".$msg."\n";
    }

    /**
     * is_error(): check if there was an error at parsing
     *
     * @return boolean error
     */
    public function is_error() {
        return $this->error;
    }

    /**
     * is_warning(): check if there was a warning at parsing
     *
     * @return boolean warning
     */
    public function is_warning() {
        return $this->warning;
    }

    /**
     * get_error(): returns the error string
     *
     * @return Error message
     */
    public function get_error() {
        return $this->errormsg;
    }

    /**
     * get_warning(): returns the warning string
     *
     * @return Warning message
     */
    public function get_warning() {
        return $this->warningmsg;
    }

    /**
     * parse(): Parses an SCORM-File
     *
     * @param msg The location of the imsmanifest.xml
     * @return The page-tree
     */
    public function parse($scormfile) {
        $tree = array();

        // Check if file exists and extract path from filename.
        if (preg_match('/^(.*)imsmanifest.xml$/', $scormfile, $regs) && is_file($scormfile)) {
            $this->dir = $regs[1];
            $imsfile = $regs[0];
        } else {
            $this->set_error("File ".$scormfile." is no SCORM-File.");
            return false;
        }

        // Read content of file, parse the xml to an array and parse the SCORM-File if there is a root-element.
        $xmlstring = file_get_contents($imsfile);                        // Read content of file.
        $objxml = new xml2Array();
        $arroutput = $objxml->parse($xmlstring);                         // Parse the xml to an array.
        if ($arroutput === false) {
            $this->set_error($objxml->error);
            return false;
        } else if (count($arroutput) == 1) {
            $manifest = $this->parse_manifest(array_shift($arroutput));  // Parse the manifest (if a manifest exists).
        } else {
            $this->set_error("XML not well formed");
            return false;
        }

        // Merge the organisation with the resources.
        if (isset($manifest["organization"]) && isset($manifest["resources"])) {
            $tree = $this->combine($manifest["organization"], $manifest["resources"]);
        }

        // If there was an error at parsing, false is returned, else the tree.
        if ($this->error) {
            return false;
        }

        return $tree;
    }

    /**
     * combine(): Merges the organisation with the resources
     *
     * @param organizations The organization tree
     * @param resources The resources tree
     * @return The combined tree
     */
    public function combine($organizations, $resources) {
        $elements = array();
        foreach ($organizations as $organization) {
            $element = array();

            // If the page is not visible, don't use it.
            if ((!isset($organization["data"]["ISVISIBLE"])) ||
                    (isset($organization["data"]["ISVISIBLE"]) &&
                            (($organization["data"]["ISVISIBLE"] == 'true') ||
                                    ($organization["data"]["ISVISIBLE"] == '1')))
            ) {

                // If there is no Organization title, use "SCORM Import".
                if (isset($organization['title'])) {
                    $element['data']['title'] = $organization['title'];
                } else {
                    $element['data']['title'] = 'SCORM Import';
                }

                if (isset($organization['identifier'])) {
                    $element['data']['identifier'] = $organization['identifier'];
                }

                if (isset($organization["data"]["ID"])) {
                    $element['data']['id'] = $organization["data"]["ID"];
                }

                if (isset($organization["data"]["IDENTIFIERREF"])) { // If the IDENTIFIERREF is set...
                    if (isset($resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"])) { // ... and the resource exists
                        // If the file exists and the type is 'webcontent' ist - that means, it is content,
                        // that can be hosted or launched by a Webbrowser. (the type "webcontent" is mandatory).
                        $element['data']['extlink'] = false;
                        if (count($resources[$organization["data"]["IDENTIFIERREF"]]["files"]) == 0) {
                            // No local resources -> link!
                            $element['data']['url'] = $resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"];
                            $element['data']['extlink'] = true;
                        } else if (is_file($this->dir.$resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"]) &&
                                ($resources[$organization["data"]["IDENTIFIERREF"]]["info"]["TYPE"] == 'webcontent')
                        ) {
                            // Check every file on which the resource depends.
                            foreach ($resources[$organization["data"]["IDENTIFIERREF"]]["files"] as $file) {
                                if (!is_file($this->dir.$file)) {
                                    $this->set_error("File ".$this->dir.$file." not found");
                                }
                            }
                            foreach ($resources[$organization["data"]["IDENTIFIERREF"]]["dependency"] as $dependency) {
                                if (!isset($resources[$dependency])) {
                                    $this->set_error("Dependent Resource $dependency in Element ".
                                            $organization["data"]["IDENTIFIER"]." not found!");
                                }
                                foreach ($resources[$dependency]["files"] as $file) {
                                    if (!is_file($this->dir.$file)) {
                                        $this->set_error("File ".$this->dir.$file." not found");
                                    }
                                }
                            }
                            $element['data']['url'] = $resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"];
                        } else {
                            $this->set_error("File ".$resources[$organization["data"]["IDENTIFIERREF"]]["info"]["HREF"].
                                    " in Element ".$organization["data"]["IDENTIFIER"]." not found.");
                        }
                    } else {
                        $this->set_error("Ressource ".$organization["data"]["IDENTIFIERREF"]." in Element ".
                                $organization["data"]["IDENTIFIER"]." not found.");
                    }
                }
            }

            // Recursive if subpages exist.
            if (isset($organization["items"])) {
                $element["items"] = $this->combine($organization["items"], $resources);
            }
            $elements[] = $element;
            unset($element);
        }
        return $elements;
    }

    /**
     * parse_manifest(): Parses the manifest-tag
     *
     * @param element : The element MANIFEST and the subtree
     * @return The parsed tree
     */
    public function parse_manifest($element) {
        $manifest = array();
        // The outer element is one <MANIFEST>.
        if ($element["name"] == 'MANIFEST') {
            /* A <manifest> can have the following children:
                <metadata> (0 or 1 time)
                <organizations> (1 time)
                <resources> (1 time)
                <manifest> (0 to many times)
                Call the right subfunction for every element
            */
            foreach ($element["children"] as $child) {
                switch ($child["name"]) {
                    case 'RESOURCES':
                        $manifest["resources"] = $this->get_resources($child["children"]);
                        break;
                    case 'ORGANIZATIONS':
                        if (isset($child["children"])) {
                            $manifest["organization"] = $this->get_organizations($child["children"]);
                        }
                        break;
                    case 'METADATA':
                        if (isset($child["children"])) {
                            $manifest["metadata"] = $this->get_metadata($child["children"]);
                        }
                        break;
                    case 'MANIFEST':
                        $submanifest = $this->parse_manifest($child["children"]);
                        $manifest["metadata"] += $submanifest["metadata"];
                        $manifest["organization"] = array_merge($manifest["organization"],
                                $submanifest["organization"]); // Identifier des arrays sind egal, deshalb array_merge.
                        // Identifier des arrays m�ssen erhalten bleiben, deshalb +.
                        $manifest["resources"] += $submanifest["resources"];
                        break;
                    default:
                        $this->set_warning("Missed Tag '".$child["name"]."' inside <manifest>");
                        break;
                }
            }
        }
        return $manifest;
    }

    /**
     * get_organizations(): Parses the three at the ORGANIZATIONS element
     *
     * @param element : The element ORGIANIZATIONS and the subtree
     * @return The organization-tree
     */
    public function get_organizations($elements) {
        /* It's possible that the ORGANIZATIONS-Element has no Subelements,
            this is the case when we have a Ressource-Package.
            ORGANIZATIONS-Element can ONLY have ORGANIZATION as Subeleement: */
        $organization = array();
        foreach ($elements as $element) {
            // Identifier.
            if ($element["name"] == 'ORGANIZATION') {
                // Interpret each subelement into the $neworganization[] and add it to the $organization.
                $neworganization = array();
                if (isset($element["attrs"]["IDENTIFIER"])) {
                    $neworganization["identifier"] = $element["attrs"]["IDENTIFIER"];
                }
                foreach ($element["children"] as $subelement) {
                    switch ($subelement["name"]) {
                        case 'TITLE':
                            $neworganization["title"] = $subelement["tagData"];
                            break;
                        case 'ITEM':
                            $tempitem = $this->rec_item_search($subelement);
                            $neworganization["items"][addslashes($subelement["attrs"]["IDENTIFIER"])] = $tempitem;
                            break;
                        case 'METADATA':
                            if (isset($subelement["children"])) {
                                $neworganization["metadata"] = $this->get_metadata($subelement["children"]);
                            }
                            break;
                        default:
                            $this->set_warning("Missed Tag '".$subelement["name"]."' inside ORGANIZATION");
                            break;
                    }
                }
                $organization[] = $neworganization;
                unset($neworganization);
            } else {
                $this->set_warning("Missed Tag '".$element["name"]."' inside <organizations>");
            }
        }
        return $organization;
    }

    /**
     * rec_item_search(): Parses the ITEM and children (also ITEMs)
     *
     * @param element : The element ITEM and the subtree
     * @return The items
     */
    public function rec_item_search($elements) {
        $items = array();
        $items["data"] = $elements["attrs"];
        if (array_key_exists('children', $elements)) {
            foreach ($elements["children"] as $subelement) {
                switch ($subelement["name"]) {
                    case 'TITLE':
                        $items["title"] = $subelement["tagData"];
                        break;
                    case 'ITEM':
                        $items["items"][addslashes($subelement["attrs"]["IDENTIFIER"])] = $this->rec_item_search($subelement);
                        break;
                    case 'METADATA':
                        if (isset($subelement["children"])) {
                            $items["metadata"] = $this->get_metadata($subelement["children"]);
                        }
                        break;
                    default:
                        $this->set_warning("Missed Tag '".$subelement["name"]."' inside ITEM");
                        break;
                }
            }
        }
        return $items;
    }

    /**
     * get_resources(): Parses the RESOURCES and children (also ITEMs)
     *                 XML:BASE (realative pathoffset) is not implemented yet.
     *
     * @param element : The element RESOURCES and the subtree
     * @return The resources
     */
    public function get_resources($elements) {
        $resources = array();
        // Element RESOURCES can only have RESOURCE-Elements as children.
        if (count($elements) > 0) {
            foreach ($elements as $element) {
                switch ($element["name"]) {
                    case 'RESOURCE':
                        $resources[addslashes($element["attrs"]["IDENTIFIER"])] = $this->get_resource($element);
                        break;
                    default:
                        $this->set_warning("Missed Tag '".$element["name"]."' inside RESOURCES");
                        break;
                }
            }
        }
        return $resources;
    }

    /**
     * get_resources(): Parses the RESOURCE-element
     *
     * @param element : The element RESOURCE
     * @return The resource with dependencies
     */
    public function get_resource($element) {
        $resource = array();
        $resource["info"] = $element["attrs"];
        $resource["dependency"] = array();
        $resource["files"] = array();
        if (isset($element["children"])) {
            foreach ($element["children"] as $subelement) {
                switch ($subelement["name"]) {
                    case 'FILE':
                        if (isset($subelement['attrs']['HREF'])) {
                            if (is_file($this->dir.$subelement['attrs']['HREF'])) {
                                $resource["files"][] = $subelement['attrs']['HREF'];
                            }
                            // If the file doen't exist, don't produce an error. maybe the ressource isn't needed and the user
                            // forgot to delete it in the resource tree. if it's needed it's checked afterwards.
                        }
                        break;
                    case 'DEPENDENCY':
                        if (isset($subelement['attrs']['IDENTIFIERREF'])) {
                            $resource["dependency"][] = $subelement['attrs']['IDENTIFIERREF'];
                        }
                        break;
                    case 'METADATA':
                        if (isset($element["children"])) {
                            $resource["metadata"] = $this->get_metadata($subelement["children"]);
                        }
                        break;
                    default:
                        $this->set_warning("Missed Tag '".$subelement["name"]."' inside RESOURCE");
                        break;
                }
            }
        }
        return $resource;
    }

    /**
     * get_metadata(): Parses the METADATA. Empty function, the Metadata of the SCORM-file is not needed yet.
     *
     * @param element : The element METADATA and subtree
     * @return NULL
     */
    public function get_metadata($elements) {
        return null;
    }
}
